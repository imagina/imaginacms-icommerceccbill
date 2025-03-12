# imaginacms-icommerceccbill (PaymentMethod)

## Install
```bash
composer require imagina/icommerceccbill-module=v10.x-dev
```

## Enable the module
```bash
php artisan module:enable Icommerceccbill
```

## Seeder

```bash
php artisan module:seed Icommerceccbill
```

## Add response Urls
https://ccbill.com/doc/managing-flexforms-payment-flows#ftoc-heading-10

add this URL:
https://mydomain/icommerceccbill/payment/response/

## URL Notifications
https://ccbill.com/doc/webhooks-overview#introduction

add this url:
https://mydomain/api/icommerceccbill/v1/confirmation

Check only this events: NewSaleSuccess, NewSaleFailure

## Tests

Configurations: https://ccbill.com/doc/admin-portal-faq#ftoc-heading-11

Testing Cards: https://ccbill.com/kb/test-credit-card-numbers#ftoc-heading-3