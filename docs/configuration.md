# Settings and payment states

The main settings are available on **HelloAsso settings**. Some technical flags
remain configurable through configuration or API but are deliberately hidden
from the interface to prevent accidental removal of core protections.

## Technical settings

- `helloasso_v2_standard_frontend_bridge` (enabled, hidden) enables the
  `CRM.payment` / `mjwshared` bridge used by traditional forms and Webform.
- `helloasso_v2_safe_abort_urls` (enabled, hidden) replaces fragile cancel or
  error URLs with a safe URL in AJAX or internal CiviCRM contexts.
- `helloasso_v2_queue_webhooks` (enabled) queues webhooks through
  `PaymentprocessorWebhook`.
- `helloasso_v2_followup_enabled` (enabled) schedules short checks at `T+5`,
  `T+15` and `T+45`.
- `helloasso_v2_afform_checkout` (enabled) exposes the HelloAsso Checkout Option
  to Form Builder/Afform.
- `helloasso_v2_cron_limit` (default `15`) limits contributions processed per
  processor during maintenance jobs.

## Payment features

- `helloasso_enable_refunds` (disabled) enables full CiviCRM refunds and
  requires partner mode.
- `helloasso_enable_installments` (disabled) enables finite monthly schedules
  of 2 to 12 payments in Afform, Webform and traditional forms. An empty field
  in QuickForm keeps a one-time payment.
- `helloasso_enable_sepa` (enabled) asks HelloAsso to offer SEPA. Availability
  still depends on HelloAsso eligibility and organization settings.
- `helloasso_quickform_redirect_message` defines the redirect explanation shown
  only when HelloAsso is selected on traditional contribution or event forms.

The default redirect message is provided in three languages. If a replacement
must remain multilingual, translate it manually under **Administer >
Localization > Translate Strings**.

## Partner settings

- `helloasso_v2_require_webhook_signature` controls the legacy `invoiceID` /
  `sig` signature.
- `helloasso_v2_require_partner_webhook_signature` controls the partner
  `x-ha-signature` header.
- `helloasso_partner_auth_enabled` exposes partner connection pages.
- `helloasso_partner_client_id_test` and
  `helloasso_partner_client_secret_test` contain sandbox partner credentials.
- `helloasso_partner_client_id_live` and
  `helloasso_partner_client_secret_live` contain production partner credentials.
- `helloasso_partner_authorize_url` and `helloasso_partner_token_url` define the
  OAuth authorization and token endpoints.

Operational partner data is stored per processor in the extension table when
the schema is current.

## Future installments

Short `T+5` / `T+15` / `T+45` checks are disabled for future installments.
When a webhook is missing, the long cron checks card payments at D+1, D+7 and
D+30 after the due date, and SEPA payments at D+9, D+15 and D+30.

HelloAsso states map as follows:

- `Authorized`, `Registered`, `AuthorizedPreprod`, `Corrected`: completed.
- `Pending`, `Unknown`, `Waiting`, `WaitingBankValidation`,
  `WaitingBankWithdraw`, `WaitingAuthentication`, `Init`: pending, keep follow-up.
- `Refused` on a future installment: failed installment, plan `Overdue`, keep
  recovery follow-up for 30 days.
- `Refused` outside recovery, `Error`, `Canceled`, `Abandoned`, `Deleted`,
  `Inconsistent`, `NoDonation`: failed, stop follow-up.
- `Refunding`: retain the current status until confirmation.
- `Refunded`: refunded, stop follow-up.
- `Contested`: CiviCRM `Chargeback`, stop follow-up.

An unknown HelloAsso state remains pending as a precaution. Completed payments
continue on the long rail during its window so that a late refund or dispute
can still be detected; webhooks remain primary after the final scheduled check.

## Recovery after refusal

A refused installment keeps its contribution failed and places the
`ContributionRecur` plan in `Overdue`. The long cron checks recovery at D+1,
D+7, D+15 and D+30. HelloAsso sends the recovery link directly to the payer;
the link is not exposed in its public API or webhook.

A successful recovery reactivates the plan cycle. At D+30, an installment that
is still refused becomes `RecoveryExpired` locally and the plan is failed.
