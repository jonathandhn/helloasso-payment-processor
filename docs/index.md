# HelloAsso Payment Processor

HelloAsso Payment Processor collects CiviCRM contributions through HelloAsso
Checkout, including donations, memberships, event registrations and custom
Form Builder or Webform journeys.

CiviCRM remains authoritative for contacts, contributions and business rules.
The extension creates the remote checkout, reconciles the resulting payment,
processes webhooks and performs scheduled API checks when an asynchronous
payment cannot be confirmed immediately.

## Main features

- HelloAsso Checkout in production and sandbox.
- Historical API-key authentication or partner authorization screen.
- Automatic partner webhook registration and signature verification.
- Webhook queueing through `PaymentprocessorWebhook`.
- Short reconciliation at `T+5`, `T+15` and `T+45` minutes.
- Independent long-term checks for late payment changes and refunds.
- Finite monthly installment plans from 2 to 12 payments.
- Optional SEPA payment requests.
- Traditional CiviCRM forms, Form Builder/Afform and Webform integrations.
- Drupal, WordPress and Standalone support.

## Documentation editions

This is the English edition. The
[French edition](https://docs.civicrm.org/helloasso-payment-processor/fr/latest/)
contains the same evergreen documentation.

HelloAsso provides online payment services to associations without charging
transaction fees. Its model is funded by voluntary contributions from payers.
