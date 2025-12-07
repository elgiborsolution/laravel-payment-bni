# eligibor-solution/laravel-payment-bni

Namespace: `ESolution\BNIPayment`

Fitur:

- VA: create, update, inquiry
- Webhook payment notification
- Audit trail ke DB (request/response)
- Error handling granular (kode BNI)
- QRIS client (pluggable path)
- DB Config (override config file)
- Mirror billing + reconcile (scheduler)
- Events: BniPaymentReceived, BniBillingPaid, BniBillingExpired
- Unit tests (Orchestra), Postman collection

## Install

```bash
composer require eligibor-solution/laravel-payment-bni
php artisan vendor:publish --provider="ESolution\BNIPayment\BNIPaymentServiceProvider" --tag=bni-config
php artisan vendor:publish --provider="ESolution\BNIPayment\BNIPaymentServiceProvider" --tag=bni-migrations
php artisan migrate
```

## Example

```php
use ESolution\BNIPayment\Clients\BniVaClient;

$res = app(BniVaClient::class)->createBilling([
  "type" => "createbilling",
  "client_id" => config('bni.client_id'),
  "trx_id" => "INV-1",
  "trx_amount" => "100000",
  "billing_type" => "c",
  "customer_name" => "Mr. X"
]);
```

### Inquiry

```php
$res = app(ESolution\BNIPayment\Clients\BniVaClient::class)->inquiryBilling('INV-1');
```

### Update

```php
$res = app(ESolution\BNIPayment\Clients\BniVaClient::class)->updateBilling([
  "client_id" => config('bni.client_id'),
  "trx_id" => "INV-1",
  "trx_amount" => "100000",
  "customer_name" => "Mr. X",
  "type" => "updatebilling"
]);
```

## Webhook

`POST /bni/va/payment-notification` → returns `{ "status": "000" }` and fires `BniPaymentReceived`.

## Reconcile

```
php artisan bni:reconcile --limit=200
```

Scheduler default tiap 5 menit (can be configured).

## DB Config

```
php artisan bni:config:set hostname api.bni.test
php artisan bni:config:get hostname
php artisan bni:config:cache
```
