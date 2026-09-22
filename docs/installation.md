# Requirements and installation

## Requirements

- CiviCRM 6.14 or later.
- `mjwshared` 1.5.11 or later.
- A PHP version supported by the selected CiviCRM release.
- A HelloAsso account.
- A [HelloAsso sandbox account](https://www.helloasso-sandbox.com/) for tests.
- API credentials and an organization slug for API-key authentication, or
  dedicated partner credentials for each partner authorization environment.

## Installation

1. Install the extension in a CiviCRM extension directory.
2. Enable `mjwshared`, then enable `helloasso-payment-processor`.
3. Apply database upgrades and clear CiviCRM caches:

```bash
cv updb
cv flush
```

4. Open **Administer > CiviContribute > Payment Processors** and create a
   processor of type **HelloAsso**.

New processors use `HelloAsso` as their default payment method. An existing
dedicated online payment method can be selected instead. Upgrades do not
replace a payment method already configured on an existing processor.

The extension also creates a `HelloAsso` payment method during upgrade to make
payment identification and accounting reconciliation clearer.

## Currency

HelloAsso expects euro payments. Forms and contributions sent to Checkout must
therefore use `EUR`.

## Cache refresh

After enabling the extension or changing a feature flag, run `cv flush`. If a
menu entry or settings page is still missing, also use **Administer > System
Settings > Cleanup Caches and Update Paths**.
