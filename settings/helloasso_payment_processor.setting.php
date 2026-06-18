<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

use CRM_HelloassoPaymentProcessor_ExtensionUtil as E;

return [
  'helloasso_v2_standard_frontend_bridge' => [
    'name' => 'helloasso_v2_standard_frontend_bridge',
    'type' => 'Boolean',
    'html_type' => 'checkbox',
    'default' => 1,
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts("Standard frontend bridge (mjwshared)"),
    'description' => E::ts("Enable the standard HelloAsso frontend integration based on CRM.payment and mjwshared."),
    'html_attributes' => [],
  ],
  'helloasso_v2_safe_abort_urls' => [
    'name' => 'helloasso_v2_safe_abort_urls',
    'type' => 'Boolean',
    'html_type' => 'checkbox',
    'default' => 1,
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts("Secure handling of failure and cancellation URLs"),
    'description' => E::ts("Avoid redirecting users to fragile CiviCRM or AJAX URLs when a payment is cancelled or fails."),
    'html_attributes' => [],
  ],
  'helloasso_v2_queue_webhooks' => [
    'name' => 'helloasso_v2_queue_webhooks',
    'type' => 'Boolean',
    'html_type' => 'checkbox',
    'default' => 1,
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts("Webhook processing queue"),
    'description' => E::ts('Place HelloAsso webhooks in the PaymentprocessorWebhook queue instead of processing them immediately.'),
    'html_attributes' => [],
    'settings_pages' => [
      'helloasso' => [
        'weight' => 40,
      ],
    ],
  ],
  'helloasso_v2_followup_enabled' => [
    'name' => 'helloasso_v2_followup_enabled',
    'type' => 'Boolean',
    'html_type' => 'checkbox',
    'default' => 1,
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts("Payment status reliability for automations (T+5 / T+15 / T+45 min)"),
    'description' => E::ts("Enable automatic checks at T+5, T+15 and T+45 after creation of a HelloAsso checkout. The final check expires an unpaid installment plan or marks a classic checkout as abandoned while keeping its contribution pending for cart reminders. Long-term monitoring of later changes, including refunds, remains independent."),
    'html_attributes' => [],
    'settings_pages' => [
      'helloasso' => [
        'weight' => 50,
      ],
    ],
  ],
  'helloasso_v2_afform_checkout' => [
    'name' => 'helloasso_v2_afform_checkout',
    'type' => 'Boolean',
    'html_type' => 'checkbox',
    'default' => 1,
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts("Afform / Form Builder integration"),
    'description' => E::ts("Publish a HelloAsso Checkout Option for Afform / Form Builder based on the CiviCRM core checkout mechanism."),
    'html_attributes' => [],
    'settings_pages' => [
      'helloasso' => [
        'weight' => 55,
      ],
    ],
  ],
  'helloasso_enable_refunds' => [
    'name' => 'helloasso_enable_refunds',
    'type' => 'Boolean',
    'html_type' => 'checkbox',
    'default' => 0,
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('Enable HelloAsso refunds'),
    'description' => E::ts('Allow CiviCRM users with refund permissions to request full HelloAsso refunds from the payment refund screen. Refunds require the HelloAsso authorization-screen mode. HelloAsso partial refunds remain unsupported by this integration.'),
    'html_attributes' => [],
    'settings_pages' => [
      'helloasso' => [
        'weight' => 58,
      ],
    ],
  ],
  'helloasso_enable_installments' => [
    'name' => 'helloasso_enable_installments',
    'type' => 'Boolean',
    'html_type' => 'checkbox',
    'default' => 0,
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('Enable HelloAsso installment payments'),
    'description' => E::ts('Allow finite monthly installment checkout payloads for HelloAsso payment processors.'),
    'html_attributes' => [],
    'settings_pages' => [
      'helloasso' => [
        'weight' => 59,
      ],
    ],
  ],
  'helloasso_quickform_redirect_message' => [
    'name' => 'helloasso_quickform_redirect_message',
    'type' => 'String',
    'html_type' => 'text',
    'default' => E::ts('You will be redirected to HelloAsso to complete your payment.'),
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('HelloAsso redirect message on standard forms'),
    'description' => E::ts('Message displayed on standard contribution and event forms when the selected payment processor is HelloAsso.'),
    'html_attributes' => ['size' => 80],
    'settings_pages' => [
      'helloasso' => [
        'weight' => 60,
      ],
    ],
  ],
  'helloasso_enable_sepa' => [
    'name' => 'helloasso_enable_sepa',
    'type' => 'Boolean',
    'html_type' => 'checkbox',
    'default' => 1,
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('Enable HelloAsso SEPA direct debit'),
    'description' => E::ts('Offer SEPA direct debit on HelloAsso Checkout, including installment checkouts. HelloAsso only displays it for eligible organizations and may keep card payment available.'),
    'html_attributes' => [],
    'settings_pages' => [
      'helloasso' => [
        'weight' => 61,
      ],
    ],
  ],
  'helloasso_v2_cron_limit' => [
    'name' => 'helloasso_v2_cron_limit',
    'type' => 'Integer',
    'html_type' => 'text',
    'default' => 15,
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts("Maximum processing batch size (Cron)"),
    'description' => E::ts('Maximum number of HelloAsso contributions processed per payment processor during a normal cron execution.'),
    'html_attributes' => ['size' => 6],
    'settings_pages' => [
      'helloasso' => [
        'weight' => 65,
      ],
    ],
  ],
  'helloasso_v2_require_webhook_signature' => [
    'name' => 'helloasso_v2_require_webhook_signature',
    'type' => 'Boolean',
    'html_type' => 'checkbox',
    'default' => 0,
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts("Strict legacy signature verification"),
    'description' => E::ts('Reject HelloAsso webhooks whose legacy invoiceID/sig signature is missing or invalid.'),
    'html_attributes' => [],
    'settings_pages' => [
      'helloasso' => [
        'weight' => 70,
      ],
    ],
  ],
  'helloasso_v2_require_partner_webhook_signature' => [
    'name' => 'helloasso_v2_require_partner_webhook_signature',
    'type' => 'Boolean',
    'html_type' => 'checkbox',
    'default' => 1,
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts("Strict partner signature verification"),
    'description' => E::ts('Reject HelloAsso partner webhooks whose x-ha-signature header is missing or invalid when a webhook signature key is stored for this processor.'),
    'html_attributes' => [],
    'settings_pages' => [
      'helloasso' => [
        'weight' => 75,
      ],
    ],
  ],
  'helloasso_partner_auth_enabled' => [
    'name' => 'helloasso_partner_auth_enabled',
    'type' => 'Boolean',
    'html_type' => 'checkbox',
    'default' => 0,
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('HelloAsso authorization screen: enable shared connection'),
    'description' => E::ts("Enable the shared HelloAsso OAuth authorization screen. When this setting is disabled, the authorization-screen interface is no longer offered on HelloAsso processor pages."),
    'html_attributes' => [],
    'settings_pages' => [
      'helloasso' => [
        'weight' => 80,
      ],
    ],
  ],
  'helloasso_partner_client_id_live' => [
    'name' => 'helloasso_partner_client_id_live',
    'type' => 'String',
    'html_type' => 'text',
    'default' => '',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('HelloAsso authorization screen: production client ID'),
    'description' => E::ts('Client ID dedicated to the HelloAsso production authorization screen.'),
    'html_attributes' => ['size' => 48],
  ],
  'helloasso_partner_client_id_test' => [
    'name' => 'helloasso_partner_client_id_test',
    'type' => 'String',
    'html_type' => 'text',
    'default' => '',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('HelloAsso authorization screen: sandbox client ID'),
    'description' => E::ts('Client ID dedicated to the HelloAsso sandbox authorization screen.'),
    'html_attributes' => ['size' => 48],
  ],
  'helloasso_partner_client_secret_live' => [
    'name' => 'helloasso_partner_client_secret_live',
    'type' => 'String',
    'html_type' => 'password',
    'default' => '',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('HelloAsso authorization screen: production client secret'),
    'description' => E::ts('Client secret dedicated to the HelloAsso production authorization screen.'),
    'html_attributes' => ['size' => 48],
  ],
  'helloasso_partner_client_secret_test' => [
    'name' => 'helloasso_partner_client_secret_test',
    'type' => 'String',
    'html_type' => 'password',
    'default' => '',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('HelloAsso authorization screen: sandbox client secret'),
    'description' => E::ts('Client secret dedicated to the HelloAsso sandbox authorization screen.'),
    'html_attributes' => ['size' => 48],
  ],
  'helloasso_partner_authorize_url' => [
    'name' => 'helloasso_partner_authorize_url',
    'type' => 'String',
    'html_type' => 'text',
    'default' => 'https://auth.helloasso.com/authorize',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts("HelloAsso authorization screen: authorization URL"),
    'description' => E::ts("HelloAsso authorization screen URL. In production, the default value is https://auth.helloasso.com/authorize."),
    'html_attributes' => ['size' => 64],
  ],
  'helloasso_partner_token_url' => [
    'name' => 'helloasso_partner_token_url',
    'type' => 'String',
    'html_type' => 'text',
    'default' => 'https://api.helloasso.com/oauth2/token',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('HelloAsso authorization screen: token URL'),
    'description' => E::ts("HelloAsso endpoint used to exchange the authorization code and refresh OAuth tokens."),
    'html_attributes' => ['size' => 64],
  ],
];
