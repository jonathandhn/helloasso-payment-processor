# HelloAsso Payment Processor for CiviCRM

HelloAsso Payment Processor collects CiviCRM contributions through HelloAsso
Checkout. It supports donations, memberships, event registrations, traditional
contribution pages, Webform integrations and Form Builder/Afform.

CiviCRM remains the source of truth for the business workflow. The extension
creates the HelloAsso checkout, reconciles payments with contributions, handles
webhooks and schedules API checks when the browser return or a notification is
not sufficient.

## Features

- Production and sandbox HelloAsso Checkout redirects.
- API-key and HelloAsso partner authorization connections.
- Signed webhook handling through the `PaymentprocessorWebhook` queue.
- Short and long reconciliation schedules for asynchronous payment states.
- Finite monthly installment plans and optional SEPA payments.
- Form Builder/Afform, Webform and traditional CiviCRM form support.
- Drupal, WordPress and Standalone compatibility.

## Requirements

- CiviCRM 6.14 or later.
- PHP 8.1 to 8.5, subject to the selected CiviCRM version.
- `mjwshared` 1.5.11 or later.
- A HelloAsso account and API or partner credentials.

## Documentation

- [English documentation](https://docs.civicrm.org/helloasso-payment-processor/en/latest/)
- [Documentation française](https://docs.civicrm.org/helloasso-payment-processor/fr/latest/)

The documentation is evergreen and describes installation, authentication,
webhooks, reconciliation, settings, integrations and development workflows.

## Issues and support

Report defects and feature requests in the
[GitLab issue tracker](https://lab.civicrm.org/extensions/helloasso-payment-processor/-/issues).
Commercial support is available from
[Jonathan Dahan](https://www.jonathan.dhn.one/).

## Credits

The current version is maintained by Jonathan Dahan. The original extension
was initiated by civiuser (Sidney) and Pierre Morvan. Version 1 was published
by Makoa and developed by Antoine Breheret and Dewy Mercerais. Active testing
and follow-up are provided by Guillaume Sorel / Gestad, with historical
contributions from Symbiotic.

## Support the project

The extension is independently developed and currently has no institutional or
corporate sponsor. Organizations using it can help fund maintenance,
documentation, testing and future development.

To sponsor work or request professional support, please
[contact Jonathan Dahan](https://www.jonathan.dhn.one/).

## License

This extension is distributed under the [GNU AGPL-3.0](LICENSE.txt). It is not
an official HelloAsso publication.