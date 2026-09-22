# Forms and integrations

## Traditional forms and Form Builder

- Form Builder/Afform exposes HelloAsso as a CiviCRM `Checkout Option`.
- After adding HelloAsso to an Afform, save the form once and reopen its
  administration interface. Additional parameters, including installments,
  appear on the second pass.
- The HelloAsso contributor is the contact selected on the **Contribution** tab,
  not necessarily the contact on the **Contact** tab. That contact supplies the
  first name, last name and email validated for Checkout.
- An organization payer sends its organization name to HelloAsso and uses the
  `billing email` as the default payer email.
- QuickForm installments require a frequency of one month and the installment
  option. Schedules sent to HelloAsso are always finite and never create
  perpetual payments.

## Webform

A Drupal extension can participate in the CiviCRM / HelloAsso payment flow
while this processor remains responsible for Checkout, webhooks and
reconciliation.

The Drupal
[HelloAsso Webform Validation](https://www.drupal.org/project/helloasso_webform_validation)
module builds on this integration point and is particularly useful when the
payment form is embedded in an iframe.

## Read-only service facade

Complementary extensions can reuse the processor connection without accessing
its credentials or creating competing OAuth clients:

```php
$service = new CRM_HelloassoPaymentProcessor_Service();
```

Classic methods select the preferred active HelloAsso processor for the
requested environment and work with either API-key or partner mode:

```php
$isTest = FALSE; // FALSE = production, TRUE = sandbox.

$processors = $service->getProcessors($isTest);
$processor = $service->getPreferredProcessor($isTest);
$payments = $service->listOrganizationPayments($isTest, [
  'from' => '2026-01-01',
  'pageSize' => 100,
]);
$payment = $service->getPayment($isTest, 123456789);
$checkoutIntent = $service->getCheckoutIntent($isTest, 987654321);
```

`Partner*` methods require an active partner-connected processor. Selection is
environment-specific: first the default processor for the requested mode when
it is partner-connected, then the first active partner-connected processor. A
`PaymentProcessorException` is raised when none is available.

```php
$isTest = TRUE; // Sandbox.

$organization = $service->getPartnerLinkedOrganization($isTest);
$payments = $service->listPartnerOrganizationPayments($isTest, [
  'pageSize' => 100,
]);
$payment = $service->getPartnerPayment(123456789, [], $isTest);
$checkoutIntent = $service->getPartnerCheckoutIntent(987654321, [], $isTest);
```

For backward compatibility, `listPartnerOrganizationPayments($query)` still
defaults to production. New integrations should call
`listPartnerOrganizationPayments($isTest, $query)` explicitly.

Proposed helpers or extension points should remain isolated and documented so
that specialized Webform or service workflows do not impose behavior on
standard CiviCRM payment journeys.
