<?php
namespace Concrete\Package\CommunityStoreBtcpay\Src\CommunityStore\Payment\Methods\CommunityStoreBtcpay;

use Core;
use URL;
use Config;
use Session;
use Log;

use \Concrete\Package\CommunityStore\Src\CommunityStore\Payment\Method as StorePaymentMethod;
use \Concrete\Package\CommunityStore\Src\CommunityStore\Cart\Cart as StoreCart;
use \Concrete\Package\CommunityStore\Src\CommunityStore\Order\Order as StoreOrder;
use \Concrete\Package\CommunityStore\Src\CommunityStore\Customer\Customer as StoreCustomer;
use \Concrete\Package\CommunityStore\Src\CommunityStore\Order\OrderStatus\OrderStatus as StoreOrderStatus;
use \Concrete\Core\Multilingual\Page\Section\Section;
use \BTCPayServer\Client\Invoice as Invoice;
use \BTCPayServer\Client\Webhook as Webhook;
use \BTCPayServer\Client\InvoiceCheckoutOptions as InvoiceCheckoutOptions;
use \BTCPayServer\Util\PreciseNumber as PreciseNumber;

class CommunityStoreBtcpayPaymentMethod extends StorePaymentMethod
{
    public function dashboardForm()
    {
        $this->set('btcpayCurrency',Config::get('community_store_btcpay.btcpayCurrency'));
        $this->set('btcpayId',Config::get('community_store_btcpay.btcpayId'));
        $this->set('btcpayUrl',Config::get('community_store_btcpay.btcpayUrl'));
        $this->set('btcpayKey',Config::get('community_store_btcpay.btcpayKey'));
        $this->set('btcpayWebhooksecret',Config::get('community_store_btcpay.btcpayWebhooksecret'));
        $this->set('btcpayTransactionDescription',Config::get('community_store_btcpay.btcpayTransactionDescription'));
        $currencies = array(
            'AUD' => "Australian Dollar",
            'CAD' => "Canadian Dollar",
            'CZK' => "Czech Koruna",
            'DKK' => "Danish Krone",
            'EUR' => "Euro",
            'HKD' => "Hong Kong Dollar",
            'HUF' => "Hungarian Forint",
            'ILS' => "Israeli New Sheqel",
            'JPY' => "Japanese Yen",
            'MXN' => "Mexican Peso",
            'NOK' => "Norwegian Krone",
            'NZD' => "New Zealand Dollar",
            'PHP' => "Philippine Peso",
            'PLN' => "Polish Zloty",
            'GBP' => "Pound Sterling",
            'SGD' => "Singapore Dollar",
            'SEK' => "Swedish Krona",
            'CHF' => "Swiss Franc",
            'TWD' => "Taiwan New Dollar",
            'THB' => "Thai Baht",
            'USD' => "U.S. Dollar"
        );
        $this->set('currencies',$currencies);
        $this->set('form',Core::make("helper/form"));
    }

    public function save(array $data = [])
    {
        Config::save('community_store_btcpay.btcpayUrl',$data['btcpayUrl']);
        Config::save('community_store_btcpay.btcpayId',$data['btcpayId']);
        Config::save('community_store_btcpay.btcpayKey',$data['btcpayKey']);
        Config::save('community_store_btcpay.btcpayWebhooksecret',$data['btcpayWebhooksecret']);
        Config::save('community_store_btcpay.btcpayCurrency',$data['btcpayCurrency']);
        Config::save('community_store_btcpay.btcpayTransactionDescription',$data['btcpayTransactionDescription']);
    }

    public function validate($args,$e)
    {
        $pm = StorePaymentMethod::getByHandle('community_store_btcpay');
        if($args['paymentMethodEnabled'][$pm->getID()]==1){
            if($args['btcpayUrl']==""){
                $e->add(t("BtcPay URL must be set"));
            }
            if($args['btcpayKey']==""){
                $e->add(t("BtcPay Api Key must be set"));
            }
            if($args['btcpayId']==""){
                $e->add(t("BtcPay Store ID must be set"));
            }
        }
        return $e;
    }

    public function submitPayment()
    {

        //nothing to do except return true
        return array('error'=>0, 'transactionReference'=>'');

    }

    public function redirectForm()
    {
        $apiKey = Config::get('community_store_btcpay.btcpayKey');
        $host = Config::get('community_store_btcpay.btcpayUrl');
        $storeId = Config::get('community_store_btcpay.btcpayId');
        $order = StoreOrder::getByID(Session::get('orderID'));
        $amount = $order->getTotal();
        $siteName = Config::get('concrete.site');

        $currency = Config::get('community_store_btcpay.btcpayCurrency');
        if(!$currency){
            $currency = "USD";
        }
        $orderId = $order->getOrderID();
        $customer = new StoreCustomer();
        $buyerEmail = $customer->getEmail();

        try {
            $client = new Invoice($host, $apiKey);
            $checkoutOptions = new InvoiceCheckoutOptions();
            $checkoutOptions
                ->setSpeedPolicy($checkoutOptions::SPEED_HIGH)
                ->setPaymentMethods(['BTC-LightningNetwork'])
                ->setRedirectURL(URL::to('/checkout/complete'));
            
            $response = $client->createInvoice(
                $storeId,
                $currency,
                PreciseNumber::parseString($amount),
                $orderId,
                $buyerEmail,
                $metaData,
                $checkoutOptions
            );
            $invoiceID = $response['id'];
            $url = $host . '/i/' . $invoiceID;

            $order->saveTransactionReference($invoiceID);
            $this->set('returnURL',URL::to('/checkout/complete'));
            $this->set('cancelReturn',URL::to('/checkout'));
            $this->set('host',$host);
            $this->set('InvoiceId',$invoiceID);

        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function getAction()
    {
       return false;
    }

    public static function validateCompletion()
    {
        // Fill in with your BTCPay Server data.
        $apiKey = Config::get('community_store_btcpay.btcpayKey');;
        $host = Config::get('community_store_btcpay.btcpayUrl');; // e.g. https://your.btcpay-server.tld
        $storeId = Config::get('community_store_btcpay.btcpayId');;
        $secret = Config::get('community_store_btcpay.btcpayWebhooksecret');; // webhook secret configured in the BTCPay UI

        $raw_post_data = file_get_contents('php://input');
        $date = date('m/d/Y h:i:s a');

        if (false === $raw_post_data) {
            Log::addError("Error. Could not read from the php://input stream or invalid BTCPayServer payload received.\n");
            throw new \Exception('Could not read from the php://input stream or invalid BTCPayServer payload received.');
        }

        $payload = json_decode($raw_post_data, false, 512, JSON_THROW_ON_ERROR);

        if (true === empty($payload)) {
            Log::addError($date . "Error. Could not decode the JSON payload from BTCPay.\n");
            throw new \Exception('Could not decode the JSON payload from BTCPay.');
        }
        // verify hmac256
        $headers = getallheaders();
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'btcpay-sig') {
                $sig = $value;
            }
        }
// needs testing
//      $sig = $headers['BTCPay-Sig'];

        $webhookClient = new Webhook($host, $apiKey);

        if (!$webhookClient->isIncomingWebhookRequestValid($raw_post_data, $sig, $secret)) {
            Log::addError($date . "Error. Invalid Signature detected! \n was: " . $sig . " should be: " . hash_hmac('sha256', $raw_post_data, $secret) . "\n");
            throw new \RuntimeException(
                'Invalid BTCPayServer payment notification message received - signature did not match.'
            );
        }
    // needs testing
    // if ($sig !== "sha256=" . hash_hmac('sha256', $raw_post_data, $secret)) {
    // Log::addError($date . "Error. Invalid Signature detected! \n was: " . $sig . " should be: " . hash_hmac('sha256', $raw_post_data, $secret) . "\n");       //     throw new \Exception('Invalid BTCPayServer payment notification message received - signature did not match.');
    //  }
        if (true === empty($payload->invoiceId)) {
            Log::addError($date . "Error. Invalid BTCPayServer payment notification message received - did not receive invoice ID.\n");
            throw new \Exception('Invalid BTCPayServer payment notification message received - did not receive invoice ID.');
        }

        try {
            $client = new Invoice($host, $apiKey);
            $invoice = $client->getInvoice($storeId, $payload->invoiceId);
        } catch (\Throwable $e) {
            Log::addError("Error: " . $e->getMessage());
            throw $e;
        }

        $invoicePrice = $invoice->getData()['amount'];
        $buyerEmail = $invoice->getData()['metadata']['buyerEmail'];
        $transReference = $payload->invoiceId;
        $em = \ORM::entityManager();
        $order = $em->getRepository('Concrete\Package\CommunityStore\Src\CommunityStore\Order\Order')->findOneBy(array('transactionReference' => $transReference));


        // optional: check whether your webhook is of the desired type
        if ($payload->type == "InvoiceInvalid") {
            if ($order) {
                $order->setCancelled($transReference);
                $order->updateStatus(StoreOrderStatus::getStartingStatus()->getHandle());
                Log::addInfo($date . "Order was cancelled" . $payload->invoiceId . " Type: " . $payload->type . " Price: " . $invoicePrice . " E-Mail: " . $buyerEmail . "\n");
                throw new \Exception('Invoice Invalid.');
            }
        }

        if ($payload->type == "InvoiceExpired") {
            if ($order) {
                $order->setCancelled($transReference);
                $order->updateStatus(StoreOrderStatus::getStartingStatus()->getHandle());
                Log::addInfo("Order has expired" . $payload->invoiceId . " Type: " . $payload->type . " Price: " . $invoicePrice . " E-Mail: " . $buyerEmail . "\n");
                throw new \Exception('Invoice Expired.');
            }
        }

        if ($payload->type == "InvoiceProcessing") {
            if ($order) {
                $order->completeOrder($transReference);
                $order->updateStatus(StoreOrderStatus::getStartingStatus()->getHandle());
                Log::addInfo("Payload received for BtcPay invoice " . $payload->invoiceId . " Type: " . $payload->type . " Price: " . $invoicePrice . " E-Mail: " . $buyerEmail . "\n");
            }
        }
        if ($payload->type == "InvoiceSettled") {
            if ($order) {
                $order->completeOrder($transReference);
                $order->updateStatus(StoreOrderStatus::getStartingStatus()->getHandle());
                Log::addInfo("Payload received for BtcPay invoice " . $payload->invoiceId . " Type: " . $payload->type . " Price: " . $invoicePrice . " E-Mail: " . $buyerEmail . "\n");
            }
        }
    }


    public function checkoutForm()
    {
        $pmID = StorePaymentMethod::getByHandle('community_store_btcpay')->getID();
        $this->set('pmID',$pmID);
    }

    public function getPaymentMinimum() {
        return 0.01;
    }


    public function getName()
    {
        return 'BTC Payserver';
    }

    public function isExternal() {
        return true;
    }
}
