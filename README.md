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

## Configure Response URLs
Add the response URLs in the CCBill configuration. See the official documentation for more details: [Managing FlexForms Payment.](https://ccbill.com/doc/managing-flexforms-payment-flows#ftoc-heading-10)

URL to response (BOTH):
```
https://mydomain/icommerceccbill/payment/response/
```

## Configure URL Notifications (Webhooks)
Set up Webhooks in CCBill following the official documentation: [Webhooks Overview.](https://ccbill.com/doc/webhooks-overview#introduction)

Confirmation URL:
```
https://mydomain/api/icommerceccbill/v1/confirmation
```

Allowed Events:

- NewSaleSuccess
- NewSaleFailure

## Tests

Follow the specific configurations described in the official documentation: [Admin Portal FAQ.](https://ccbill.com/doc/admin-portal-faq#ftoc-heading-11)

To perform tests with cards, use the numbers provided here: [Test Credit Card Numbers.](https://ccbill.com/kb/test-credit-card-numbers#ftoc-heading-6)
