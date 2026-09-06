<?php

namespace Concrete\Package\CommunityStoreBtcpay\Src\CommunityStore\Payment\Methods\CommunityStoreBtcpay;

use BTCPayServer\Client\Invoice;
use BTCPayServer\Client\InvoiceCheckoutOptions;
use BTCPayServer\Client\Webhook;
use BTCPayServer\Result\Invoice as InvoiceResult;
use BTCPayServer\Util\PreciseNumber;
use Concrete\Core\Support\Facade\Application;
use Concrete\Core\Support\Facade\Config;
use Concrete\Core\Support\Facade\DatabaseORM;
use Concrete\Core\Support\Facade\Log;
use Concrete\Core\Support\Facade\Session;
use Concrete\Core\Support\Facade\Url;
use Concrete\Package\CommunityStore\Src\CommunityStore\Customer\Customer as StoreCustomer;
use Concrete\Package\CommunityStore\Src\CommunityStore\Order\Order as StoreOrder;
use Concrete\Package\CommunityStore\Src\CommunityStore\Order\OrderStatus\OrderStatus as StoreOrderStatus;
use Concrete\Package\CommunityStore\Src\CommunityStore\Payment\Method as StorePaymentMethod;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CommunityStoreBtcpayPaymentMethod extends StorePaymentMethod
{
    private const WEBHOOK_EVENTS = [
        'InvoiceSettled',
        'InvoiceExpired',
        'InvoiceInvalid',
    ];

    public function dashboardForm()
    {
        $apiKey = trim((string) Config::get('community_store_btcpay.btcpayKey'));
        $webhookSecret = trim((string) Config::get('community_store_btcpay.btcpayWebhooksecret'));

        $this->set('btcpayId', (string) Config::get('community_store_btcpay.btcpayId'));
        $this->set('btcpayUrl', (string) Config::get('community_store_btcpay.btcpayUrl'));
        $this->set('btcpayKeyConfigured', $apiKey !== '');
        $this->set('btcpayWebhookSecretConfigured', $webhookSecret !== '');
        $this->set('storeCurrency', self::getStoreCurrency() ?: t('Not configured'));
        $this->set('webhookUrl', (string) Url::to('/checkout/btcpayresponse'));
        $this->set('form', Application::getFacadeApplication()->make('helper/form'));
    }

    public function save(array $data = [])
    {
        $host = rtrim(trim((string) ($data['btcpayUrl'] ?? '')), '/');
        $storeId = trim((string) ($data['btcpayId'] ?? ''));
        $apiKey = trim((string) ($data['btcpayKey'] ?? ''));
        $webhookSecret = trim((string) ($data['btcpayWebhooksecret'] ?? ''));

        Config::save('community_store_btcpay.btcpayUrl', $host);
        Config::save('community_store_btcpay.btcpayId', $storeId);

        if ($apiKey !== '') {
            Config::save('community_store_btcpay.btcpayKey', $apiKey);
        }
        if ($webhookSecret !== '') {
            Config::save('community_store_btcpay.btcpayWebhooksecret', $webhookSecret);
        }
    }

    public function validate($args, $e)
    {
        $method = StorePaymentMethod::getByHandle('community_store_btcpay');
        $enabled = $method
            && !empty($args['paymentMethodEnabled'][$method->getID()]);

        if (!$enabled) {
            return $e;
        }

        $host = rtrim(trim((string) ($args['btcpayUrl'] ?? '')), '/');
        $storeId = trim((string) ($args['btcpayId'] ?? ''));
        $submittedApiKey = trim((string) ($args['btcpayKey'] ?? ''));
        $submittedWebhookSecret = trim((string) ($args['btcpayWebhooksecret'] ?? ''));
        $savedApiKey = trim((string) Config::get('community_store_btcpay.btcpayKey'));
        $savedWebhookSecret = trim((string) Config::get('community_store_btcpay.btcpayWebhooksecret'));

        if ($host === '') {
            $e->add(t('BTCPay Server URL must be set.'));
        } elseif (filter_var($host, FILTER_VALIDATE_URL) === false) {
            $e->add(t('BTCPay Server URL must be a valid URL.'));
        }
        if ($storeId === '') {
            $e->add(t('BTCPay Server Store ID must be set.'));
        }
        if ($submittedApiKey === '' && $savedApiKey === '') {
            $e->add(t('BTCPay Server API key must be set.'));
        }
        if ($submittedWebhookSecret === '' && $savedWebhookSecret === '') {
            $e->add(t('BTCPay Server webhook secret must be set.'));
        }
        if (self::getStoreCurrency() === '') {
            $e->add(t('Community Store currency must be configured before enabling BTCPay Server.'));
        }

        return $e;
    }

    public function submitPayment()
    {
        return ['error' => 0, 'transactionReference' => ''];
    }

    public function redirectForm()
    {
        $this->set('cancelReturn', (string) Url::to('/checkout'));

        try {
            self::assertSdkAvailable();

            $host = self::getHost();
            $apiKey = self::getApiKey();
            $storeId = self::getStoreId();
            $currency = self::getStoreCurrency();
            $order = StoreOrder::getByID((int) Session::get('orderID'));

            if (!$order) {
                throw new \RuntimeException('Community Store order could not be loaded.');
            }
            if ($order->getCancelled()) {
                throw new \RuntimeException('The Community Store order has already been cancelled.');
            }
            if ($host === '' || $apiKey === '' || $storeId === '' || $currency === '') {
                throw new \RuntimeException('BTCPay Server payment method is not fully configured.');
            }

            $client = new Invoice($host, $apiKey);
            $invoice = $this->getReusableInvoice($client, $storeId, $order, $currency);

            if (!$invoice) {
                $checkoutOptions = (new InvoiceCheckoutOptions())
                    ->setSpeedPolicy(InvoiceCheckoutOptions::SPEED_HIGH)
                    ->setRedirectURL((string) Url::to('/checkout/complete'));

                $customer = new StoreCustomer();
                $invoice = $client->createInvoice(
                    $storeId,
                    $currency,
                    PreciseNumber::parseString((string) $order->getTotal()),
                    (string) $order->getOrderID(),
                    (string) $customer->getEmail(),
                    null,
                    $checkoutOptions
                );

                $order->saveTransactionReference($invoice->getId());
            }

            $this->set('host', $host);
            $this->set('invoiceId', $invoice->getId());
        } catch (\Throwable $exception) {
            Log::addError('BTCPay Server redirect failed: ' . $exception->getMessage());
            $this->set('error', t('Unable to start the BTCPay Server payment. Please return to checkout and try again.'));
        }
    }

    private function getReusableInvoice(Invoice $client, string $storeId, StoreOrder $order, string $currency): ?InvoiceResult
    {
        $invoiceId = trim((string) $order->getTransactionReference());
        if ($invoiceId === '') {
            return null;
        }

        $invoice = $client->getInvoice($storeId, $invoiceId);

        if (!self::invoiceMatchesOrder($invoice, $order, $currency)) {
            throw new \RuntimeException('Existing BTCPay Server invoice does not match the Community Store order.');
        }

        if ($invoice->isExpired() || $invoice->isInvalid() || $invoice->isPaidLate()) {
            self::cancelOrder($order, 'BTCPay Server invoice is no longer payable.');
            throw new \RuntimeException('Existing BTCPay Server invoice is expired or invalid.');
        }

        if (!$invoice->isNew() && !$invoice->isProcessing() && !$invoice->isSettled()) {
            throw new \RuntimeException('Existing BTCPay Server invoice has an unsupported state.');
        }

        return $invoice;
    }

    public static function validateCompletion()
    {
        $request = Request::createFromGlobals();
        $rawBody = $request->getContent();
        $signature = (string) $request->headers->get('BTCPay-Sig', '');
        $secret = self::getWebhookSecret();

        if ($rawBody === '' || $secret === '') {
            Log::addError('BTCPay Server webhook could not be processed because the request body or webhook secret is missing.');
            return new Response('', 400);
        }

        self::loadSdkAutoloaderIfNeeded();
        if (!class_exists(Webhook::class)) {
            Log::addError('BTCPay Server webhook could not be processed because the Greenfield PHP library is not installed.');
            return new Response('', 500);
        }

        if (!Webhook::isIncomingWebhookRequestValid($rawBody, $signature, $secret)) {
            Log::addWarning('BTCPay Server webhook signature validation failed.');
            return new Response('', 401);
        }

        try {
            $payload = json_decode($rawBody, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            Log::addWarning('BTCPay Server webhook contained invalid JSON.');
            return new Response('', 400);
        }

        $type = (string) ($payload->type ?? '');
        if (!in_array($type, self::WEBHOOK_EVENTS, true)) {
            return new Response('', 200);
        }

        $invoiceId = trim((string) ($payload->invoiceId ?? ''));
        if ($invoiceId === '') {
            Log::addWarning('BTCPay Server webhook did not contain an invoice ID.');
            return new Response('', 400);
        }

        $entityManager = DatabaseORM::entityManager();
        $order = $entityManager->getRepository(StoreOrder::class)->findOneBy([
            'transactionReference' => $invoiceId,
        ]);

        if (!$order) {
            Log::addWarning('BTCPay Server webhook references an invoice that is not associated with a Community Store order.');
            return new Response('', 200);
        }

        if ($type === 'InvoiceExpired' || $type === 'InvoiceInvalid') {
            if (!$order->getPaid()) {
                self::cancelOrder($order, sprintf('BTCPay Server %s.', $type));
            }
            return new Response('', 200);
        }

        // InvoiceSettled: fetch the invoice so amount, currency and late-payment state
        // can be verified before completing the Community Store order.
        try {
            self::assertSdkAvailable();
            $client = new Invoice(self::getHost(), self::getApiKey());
            $invoice = $client->getInvoice(self::getStoreId(), $invoiceId);
        } catch (\Throwable $exception) {
            Log::addError('BTCPay Server settled invoice verification failed: ' . $exception->getMessage());
            return new Response('', 500);
        }

        if ($order->getCancelled() || $invoice->isPaidLate()) {
            if (!$order->getPaid()) {
                self::cancelOrder($order, 'BTCPay Server late payment was rejected.');
            }
            Log::addWarning('BTCPay Server late settlement was ignored for cancelled order ' . $order->getOrderID() . '.');
            return new Response('', 200);
        }

        if ($order->getPaid()) {
            return new Response('', 200);
        }

        $currency = self::getStoreCurrency();
        if (!$invoice->isSettled() || !self::invoiceMatchesOrder($invoice, $order, $currency)) {
            Log::addError('BTCPay Server settled invoice did not match order ' . $order->getOrderID() . ' and was not completed.');
            return new Response('', 200);
        }

        try {
            $order->completeOrder($invoiceId);
            Log::addInfo('BTCPay Server invoice ' . $invoiceId . ' completed Community Store order ' . $order->getOrderID() . '.');
        } catch (\Throwable $exception) {
            Log::addError('BTCPay Server could not complete Community Store order ' . $order->getOrderID() . ': ' . $exception->getMessage());
            return new Response('', 500);
        }

        return new Response('', 200);
    }

    private static function invoiceMatchesOrder(InvoiceResult $invoice, StoreOrder $order, string $currency): bool
    {
        if ($currency === '' || strtoupper($invoice->getCurrency()) !== strtoupper($currency)) {
            return false;
        }

        return bccomp(
            $invoice->getAmount()->__toString(),
            (string) $order->getTotal(),
            8
        ) === 0;
    }

    private static function cancelOrder(StoreOrder $order, string $comment): void
    {
        if ($order->getPaid() || $order->getCancelled()) {
            return;
        }

        $order->setCancelled(new \DateTime());
        $order->save();
        $order->updateStatus(StoreOrderStatus::getStartingStatus()->getHandle(), $comment);
    }

    private static function getHost(): string
    {
        return rtrim(trim((string) Config::get('community_store_btcpay.btcpayUrl')), '/');
    }

    private static function getStoreId(): string
    {
        return trim((string) Config::get('community_store_btcpay.btcpayId'));
    }

    private static function getApiKey(): string
    {
        return trim((string) Config::get('community_store_btcpay.btcpayKey'));
    }

    private static function getWebhookSecret(): string
    {
        return trim((string) Config::get('community_store_btcpay.btcpayWebhooksecret'));
    }

    private static function getStoreCurrency(): string
    {
        return strtoupper(trim((string) Config::get('community_store.currency')));
    }

    private static function assertSdkAvailable(): void
    {
        self::loadSdkAutoloaderIfNeeded();
        if (!class_exists(Invoice::class) || !class_exists(Webhook::class)) {
            throw new \RuntimeException('BTCPay Server Greenfield PHP library is not installed. Run composer install in the package directory.');
        }
    }

    private static function loadSdkAutoloaderIfNeeded(): void
    {
        if (class_exists(Invoice::class, false)) {
            return;
        }

        $autoload = dirname(__DIR__, 5) . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }
    }

    public function checkoutForm()
    {
        $method = StorePaymentMethod::getByHandle('community_store_btcpay');
        $this->set('pmID', $method ? $method->getID() : 0);
    }

    public function getPaymentMinimum()
    {
        return 0.01;
    }

    public function getName()
    {
        return 'BTCPay Server';
    }

    public function isExternal()
    {
        return true;
    }
}
