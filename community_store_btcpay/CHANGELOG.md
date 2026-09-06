# Changelog

## 1.3.0 - 2026-09-05

### Changed

- Raised requirements to Concrete CMS 9.0+, Community Store 2.6+, and PHP 8.1+.
- Updated the BTCPay Server Greenfield PHP client requirement to 2.9.1.
- Always use the currency configured by Community Store; removed the separate BTCPay currency setting.
- Reuse the existing BTCPay invoice associated with an order instead of creating duplicate invoices when the redirect step is revisited.
- Registered the webhook endpoint as a POST-only Concrete CMS route.
- Simplified the payment method configuration and removed the unused transaction-description setting.
- API keys and webhook secrets are no longer rendered back into the settings form.
- Normalized BTCPay Server URLs by removing trailing slashes.
- Standardized package and payment-method naming to “BTCPay Server” while preserving custom payment-method display names during upgrades.
- Rebuilt German, French, and Italian translation catalogs and removed incomplete legacy catalogs.
- Normalized `icon.png` to 97×97 px RGB without transparency.

### Fixed

- Made `InvoiceSettled` handling idempotent so webhook redelivery cannot complete an order more than once.
- Prevented duplicate stock reductions, receipts, notifications, and order events caused by duplicate settled webhooks.
- Corrected order cancellation to store a cancellation date instead of incorrectly passing the invoice ID to `setCancelled()`.
- `InvoiceExpired` and `InvoiceInvalid` now return a successful webhook response after they are handled instead of deliberately throwing an exception and triggering redelivery.
- Expired, invalid, cancelled, and `PaidLate` payments cannot later complete the order.
- Settled invoices are verified against the Community Store order amount and currency before order completion.
- Removed a copied `community_store_sofort` configuration lookup from the checkout view.

### Removed

- Legacy Concrete CMS 8 / PHP 7 compatibility code.
- Manual Community Store installation checks that duplicated package dependencies.
- Unused transaction-description configuration.
- Unused package-specific currency configuration.
- Unused imports, variables, return URL data, and `getAction()` method.
- Logging of customer email addresses and webhook signature material.
- Incomplete Japanese and Russian translation catalogs.
