# Community Store BTCPay Server

BTCPay Server payment method for [Concrete CMS](https://www.concretecms.org/) Community Store.

This package creates BTCPay Server invoices for Community Store orders, opens the BTCPay checkout modal, and updates the order from signed BTCPay Server webhooks.

## Requirements

- Concrete CMS 9.0+
- Community Store 2.6+
- PHP 8.1+
- PHP extensions required by the BTCPay Greenfield client: `bcmath`, `curl`, `json`, and `mbstring`
- A working BTCPay Server instance
- Composer for installing the PHP dependency

The package uses `btcpayserver/btcpayserver-greenfield-php` 2.9.1.

## Installation

1. Copy the `community_store_btcpay` directory into your Concrete CMS `packages/` directory.
2. From the package directory, install the PHP dependency:

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. Install **BTCPay Server Payment Method** from **Dashboard → Extend Concrete**.
4. Open the Community Store payment method settings and configure BTCPay Server.

The package does not require a JavaScript or CSS build step.

## BTCPay Server configuration

### 1. Server URL

Enter the base URL of your BTCPay Server, for example:

```text
https://btcpay.example.com
```

A trailing slash is removed automatically.

### 2. Store ID

Enter the BTCPay Server Store ID for the store that should receive the invoices.

### 3. API key

Create an API key in BTCPay Server that is restricted to the selected store and can create and view invoices.

The API key is stored in Concrete CMS configuration. For security, the settings form never writes the saved key back into the HTML. Leave the field blank when editing the settings to keep the currently stored key.

### 4. Webhook

Create a webhook in BTCPay Server that points to:

```text
https://example.com/checkout/btcpayresponse
```

The exact URL for the current Concrete CMS installation is shown in the payment method settings.

Configure the webhook for these events:

- `InvoiceSettled`
- `InvoiceExpired`
- `InvoiceInvalid`

Copy the webhook secret into the Community Store payment method settings. As with the API key, leave the field blank later to keep the saved secret.

## Currency

The payment method always uses the currency configured in Community Store:

```text
community_store.currency
```

There is no separate BTCPay currency setting and no currency conversion in this package. The invoice amount and currency must match the Community Store order exactly before a settled invoice can complete an order.

## Payment flow

1. Community Store creates the order.
2. The package creates one BTCPay Server invoice for the order.
3. The BTCPay modal is opened for that invoice.
4. The invoice ID is stored as the Community Store transaction reference.
5. If the customer reloads or revisits the payment step, the existing invoice is reused instead of creating another invoice.
6. A signed `InvoiceSettled` webhook completes the Community Store order.

Before completing an order, the webhook handler verifies:

- the webhook signature,
- the invoice ID,
- the Community Store order association,
- the invoice amount,
- the invoice currency,
- the invoice status,
- and that the order has not already been paid or cancelled.

Webhook redelivery is safe: an already paid order is acknowledged without calling `completeOrder()` again.

## Expired and invalid invoices

`InvoiceExpired` and `InvoiceInvalid` are final for the associated Community Store order.

The order is marked cancelled and the package does not create a replacement invoice automatically. A later settlement for an already cancelled order is ignored and cannot reactivate or complete that order. BTCPay invoices marked as `PaidLate` are likewise rejected.

## Invoice reuse

An existing transaction reference is reused when its BTCPay invoice is still in a usable state (`New`, `Processing`, or `Settled`) and its amount and currency match the order.

If the existing invoice is expired, invalid, paid late, mismatched, or in an unsupported state, the package does not silently replace it with a second invoice.

## Webhook responses

Valid, handled webhook events return HTTP 200, including duplicate deliveries. Unknown but correctly signed BTCPay webhook event types are also acknowledged with HTTP 200 and ignored.

Malformed payloads and invalid signatures are rejected. Temporary failures while verifying or completing a settled payment return an error response so BTCPay Server can retry delivery.

## Logs

Operational failures are written to the Concrete CMS log. API keys, webhook secrets, webhook signatures, and customer email addresses are not written to the package log messages.

## Updating from 1.2

Version 1.3.0 removes the old package-specific currency and transaction-description settings. Existing stored values for those legacy settings are ignored.

Existing BTCPay Server URL, Store ID, API key, and webhook secret values continue to be used. After updating, run Composer in the package directory to install the required Greenfield PHP client version.

Review the payment method settings after the update and verify that the Community Store currency is correct before accepting payments.

## Testing

Before using the gateway in production, perform at least one low-value Lightning or on-chain transaction and verify all of the following:

- the invoice opens in the BTCPay modal,
- reloading the payment step does not create a second invoice,
- the settled webhook marks the order paid exactly once,
- webhook redelivery does not duplicate stock changes, emails, or order events,
- an expired invoice cancels the order,
- and an expired order cannot later be completed by a late settlement.

## License

GPL-3.0-or-later. See `LICENSE`.

## Related projects

- BTCPay Server: https://btcpayserver.org/
- BTCPay Server Greenfield PHP client: https://github.com/btcpayserver/btcpayserver-greenfield-php
- Community Store: https://github.com/concretecms-community-store/community_store
