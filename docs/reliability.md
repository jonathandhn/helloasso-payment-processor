# Webhooks and reconciliation

HelloAsso payment states can be asynchronous. Webhooks are the primary signal,
while scheduled API checks provide bounded recovery when a webhook or browser
return is missing.

## Webhook URL

Partner mode automatically registers the webhook URL and stores the key used to
verify the `x-ha-signature` header.

In API-key mode, register the processor notification URL manually in HelloAsso:

```text
/civicrm/payment/ipn/PROCESSOR_ID
```

Use the absolute URL generated for the instance and the actual production or
sandbox processor ID. IDs are database-specific; never infer a sandbox ID from
the production processor.

To print the correct absolute URL, including on WordPress:

```bash
cv ev 'echo CRM_HelloassoPaymentProcessor_Webhook::getWebhookPath(1), PHP_EOL;'
```

Replace `1` with the processor ID registered in HelloAsso.

## Webhook signatures

Two signature mechanisms coexist because sites may receive both manually
configured historical notifications and partner-managed notifications.

- `helloasso_v2_require_partner_webhook_signature` is enabled by default. In
  partner mode, `x-ha-signature` must match the signing key stored during
  webhook registration. Disable it only for a relay or multi-instance design
  that cannot preserve that header.
- `helloasso_v2_require_webhook_signature` is disabled by default. It requires
  the historical local HMAC based on `metadata.invoiceID` and `metadata.sig`.
  Enable it only if legacy webhooks sent to the instance include that signature.

A signature that is present and verifiable must always be correct. Depending on
the settings, a missing signature may be tolerated, but the payload is not
considered sufficient proof of payment.

For an unsigned or unverifiable webhook, the extension calls HelloAsso only
when the payload references a locally known `helloasso_payment_id` or
`checkout_intent_id`. A payload matching only a local `invoiceID` is not direct
proof and is left to scheduled reconciliation. This prevents a webhook for
another client from validating a local contribution or causing arbitrary API
lookups.

## Scheduled jobs

Keep the following CiviCRM jobs enabled:

- `Job.process_paymentprocessor_webhooks` processes queued notifications.
- `Job.process_helloasso` runs short checks at `T+5`, `T+15` and `T+45`.
- `Job.process_helloasso_long_followup` detects late changes, refunds and bank
  disputes.
- `Job.refresh_helloasso_partner_links` refreshes partner links before expiry.

At the final short check, a multi-installment checkout with no payment is
cancelled. A conventional abandoned checkout remains `Pending` so that the
contribution can still be used for follow-up.

## Useful commands

Process queued webhooks:

```bash
cv api3 Job.process_paymentprocessor_webhooks
```

Run short checks that are due:

```bash
cv api3 Job.process_helloasso only_scheduled=1 due_before=now limit=15
```

Force one contribution synchronization, even if automatic short checks are
disabled:

```bash
cv api3 Job.process_helloasso contribution_id=12345 payment_processor_id=1 only_scheduled=0 limit=1
```

Run long checks that are due:

```bash
cv api3 Job.process_helloasso_long_followup due_before=now limit=15
```

## Cancelling an installment plan

Cancel a HelloAsso plan from the contact's recurring contributions page using
the native recurring-contribution cancellation action, not from the refund
screen. This action is available only for processors connected through partner
OAuth.

When HelloAsso accepts cancellation, future unpaid installments are cancelled
locally without refunding payments already collected.

If an installment reports `Canceled`, the extension schedules an immediate
plan check during the next short cron. It reloads the `checkout_intent` and
checks the remaining installments without waiting for the long rail.
