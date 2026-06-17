<?php

require_once 'helloasso_payment_processor.civix.php';

use CRM_HelloassoPaymentProcessor_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function helloasso_payment_processor_civicrm_config(mixed &$config): void {
  _helloasso_payment_processor_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_angularModules().
 */
function helloasso_payment_processor_civicrm_angularModules(mixed &$angularModules): void {
  $module = include __DIR__ . '/ang/crmHelloassoPaymentProcessor.ang.php';
  $module['ext'] = E::LONG_NAME;
  $angularModules['crmHelloassoPaymentProcessor'] = $module;
  Civi::resources()->addVars('helloassoAfform', [
    'supportsInstallments' => helloasso_payment_processor_supports_afform_installments(),
  ]);
}

function helloasso_payment_processor_supports_afform_installments(): bool {
  return version_compare(CRM_Utils_System::version(), '6.15', '>=');
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function helloasso_payment_processor_civicrm_install(): void {
  _helloasso_payment_processor_civix_civicrm_install();
  helloasso_payment_processor_register_civirules_conditions();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function helloasso_payment_processor_civicrm_enable(): void {
  _helloasso_payment_processor_civix_civicrm_enable();
  helloasso_payment_processor_register_civirules_conditions();
}

/**
 * Register optional CiviRules conditions without making CiviRules a dependency.
 */
function helloasso_payment_processor_register_civirules_conditions(): void {
  if (!class_exists('CRM_Civirules_Utils_Upgrader')) {
    return;
  }

  $jsonFile = __DIR__ . DIRECTORY_SEPARATOR . 'civirules_conditions.json';
  if (!is_readable($jsonFile)) {
    return;
  }

  try {
    CRM_Civirules_Utils_Upgrader::insertConditionsFromJson($jsonFile);
  }
  catch (Throwable $e) {
    Civi::log()->warning('HelloAsso optional CiviRules conditions could not be registered: ' . $e->getMessage());
  }
}

/**
 * Implements hook_civicrm_alterMenu().
 */
function helloasso_payment_processor_civicrm_alterMenu(mixed &$items): void {
  foreach ([
    'civicrm/admin/setting/helloasso',
    'civicrm/admin/setting/preferences/helloasso',
  ] as $path) {
    if (isset($items[$path])) {
      $items[$path]['title'] = E::ts('HelloAsso settings');
      $items[$path]['desc'] = E::ts('HelloAsso online payments, authorization-screen connections, secure webhooks and payment reconciliation.');
    }
  }

  foreach ([
    'civicrm/admin/setting/helloasso',
    'civicrm/admin/setting/preferences/helloasso',
    'civicrm/helloasso/partner',
    'civicrm/helloasso/partner/callback',
  ] as $path) {
    if (isset($items[$path])) {
      $items[$path]['access_arguments'] = [['administer CiviCRM'], 'and'];
    }
  }
}

/**
 * Implements hook_civicrm_buildForm().
 */
function helloasso_payment_processor_civicrm_buildForm(string $formName, mixed &$form): void {
  if (in_array($formName, [
    'CRM_Contribute_Form_Contribution_Main',
    'CRM_Event_Form_Registration_Register',
  ], TRUE)) {
    helloasso_payment_processor_add_quickform_checkout_controls($formName, $form);
  }

  if ($formName === 'CRM_Mjwshared_Form_PaymentRefund') {
    helloasso_payment_processor_lock_mjwshared_refund_amount($form);
    return;
  }

  if ($formName === 'CRM_Admin_Form_Generic' && helloasso_payment_processor_is_helloasso_settings_form()) {
    helloasso_payment_processor_inject_settings_page_panel();
    return;
  }

  if ($formName !== 'CRM_Admin_Form_PaymentProcessor') {
    return;
  }

  helloasso_payment_processor_protect_test_processor_edit($form);

  $paymentProcessorType = $form->getVar('_paymentProcessorDAO');
  if (!$paymentProcessorType || $paymentProcessorType->class_name !== 'Payment_HelloAsso') {
    return;
  }

  $liveProcessorId = (int) $form->getVar('_id');
  if (!$liveProcessorId) {
    return;
  }

  if (!Civi::settings()->get('helloasso_partner_auth_enabled')) {
    return;
  }

  $sandboxProcessor = helloasso_payment_processor_get_editable_sandbox_processor($form);
  if (!$sandboxProcessor) {
    return;
  }

  helloasso_payment_processor_replace_payment_processor_form_rule($form);

  $sandboxProcessorId = (int) $sandboxProcessor['id'];
  $liveProcessorConfig = helloasso_payment_processor_get_payment_processor($liveProcessorId) ?? [];
  $processorAuthConfig = new CRM_HelloassoPaymentProcessor_ProcessorAuthConfig();
  $liveCurrentMode = $processorAuthConfig->getConnectionMode($liveProcessorId, $liveProcessorConfig);
  $liveLink = $processorAuthConfig->getLinkedOrganization($liveProcessorId);
  $liveWebhookRegistration = $processorAuthConfig->getWebhookRegistration($liveProcessorId);
  $liveWebhookOwnership = $processorAuthConfig->getWebhookOwnership($liveProcessorId);
  $currentMode = $processorAuthConfig->getConnectionMode($sandboxProcessorId, $sandboxProcessor);
  $link = $processorAuthConfig->getLinkedOrganization($sandboxProcessorId);
  $webhookRegistration = $processorAuthConfig->getWebhookRegistration($sandboxProcessorId);
  $webhookOwnership = $processorAuthConfig->getWebhookOwnership($sandboxProcessorId);
  $webhookAutoManaged = $webhookOwnership !== 'manual';
  $liveWebhookAutoManaged = $liveWebhookOwnership !== 'manual';
  $hasClassicLiveCredentials = $processorAuthConfig->hasClassicCredentials($liveProcessorConfig);
  $hasClassicSandboxCredentials = $processorAuthConfig->hasClassicCredentials($sandboxProcessor);
  $sandboxPluginPublicConfigured = helloasso_payment_processor_has_partner_client_credentials(TRUE);
  $livePluginPublicConfigured = helloasso_payment_processor_has_partner_client_credentials(FALSE);
  $liveConnectUrl = helloasso_payment_processor_get_partner_page_url('reset=1&processor_id=' . $liveProcessorId);
  $connectUrl = helloasso_payment_processor_get_partner_page_url('reset=1&processor_id=' . $sandboxProcessorId);
  $liveLinkedAtLabel = helloasso_payment_processor_format_datetime($liveLink['linked_at'] ?? NULL);
  $liveAccessExpiresAtLabel = helloasso_payment_processor_format_datetime($liveLink['expires_at'] ?? NULL);
  $liveRefreshExpiresAtLabel = helloasso_payment_processor_format_datetime($liveLink['refresh_expires_at'] ?? NULL);
  $linkedAtLabel = helloasso_payment_processor_format_datetime($link['linked_at'] ?? NULL);
  $accessExpiresAtLabel = helloasso_payment_processor_format_datetime($link['expires_at'] ?? NULL);
  $refreshExpiresAtLabel = helloasso_payment_processor_format_datetime($link['refresh_expires_at'] ?? NULL);
  $modeOptions = [];
  $modeOptions[] = '<option value="community"' . ($currentMode === 'community' ? ' selected="selected"' : '') . '>' . htmlspecialchars(E::ts('Classic API key'), ENT_QUOTES, 'UTF-8') . '</option>';
  $modeOptions[] = '<option value="plugin_public"' . ($currentMode === 'plugin_public' ? ' selected="selected"' : '') . ($sandboxPluginPublicConfigured ? '' : ' disabled="disabled"') . '>' . htmlspecialchars(E::ts('HelloAsso sandbox authorization screen'), ENT_QUOTES, 'UTF-8') . '</option>';
  $liveModeOptions = [];
  $livePluginPublicDisabled = !$livePluginPublicConfigured || $hasClassicLiveCredentials;
  $livePluginPublicDisabledReason = '';
  if ($hasClassicLiveCredentials) {
    $livePluginPublicDisabledReason = E::ts('Production authorization-screen mode is locked while classic live API credentials are still stored on this payment processor.');
  }
  elseif (!$livePluginPublicConfigured) {
    $livePluginPublicDisabledReason = E::ts('Production authorization-screen mode is locked until the production client ID and client secret are configured.');
  }
  $liveModeOptions[] = '<option value="community"' . ($liveCurrentMode === 'community' ? ' selected="selected"' : '') . '>' . htmlspecialchars(E::ts('Classic API key'), ENT_QUOTES, 'UTF-8') . '</option>';
  $liveModeOptions[] = '<option value="plugin_public"'
    . ($liveCurrentMode === 'plugin_public' ? ' selected="selected"' : '')
    . ($livePluginPublicDisabled ? ' disabled="disabled" title="' . htmlspecialchars($livePluginPublicDisabledReason, ENT_QUOTES, 'UTF-8') . '"' : '')
    . '>' . htmlspecialchars(E::ts('HelloAsso production authorization screen'), ENT_QUOTES, 'UTF-8') . '</option>';
  $liveConnectButton = helloasso_payment_processor_get_authorize_button_html(
    $liveConnectUrl . '&ha_action=connect',
    E::ts('Connect production to HelloAsso')
  );
  $sandboxConnectButton = helloasso_payment_processor_get_authorize_button_html(
    $connectUrl . '&ha_action=connect',
    E::ts('Connect sandbox to HelloAsso')
  );

  $help = [];
  $help[] = '<fieldset id="helloasso-live-auth-block"><legend>' . E::ts('HelloAsso production connection') . '</legend>';
  $help[] = '<div class="crm-block crm-form-block"><table class="form-layout-compressed">';
  $help[] = '<tr><td>' . E::ts('Live payment processor ID') . '</td><td><code>' . $liveProcessorId . '</code></td></tr>';
  $help[] = '<tr><td><label for="helloasso_live_connection_mode">' . E::ts('Live connection mode') . '</label></td><td><select id="helloasso_live_connection_mode" name="helloasso_live_connection_mode">' . implode('', $liveModeOptions) . '</select></td></tr>';
  $help[] = '<tr><td><label for="helloasso_live_webhook_auto_manage">' . E::ts('Automatically enable the webhook') . '</label></td><td><input type="checkbox" id="helloasso_live_webhook_auto_manage" name="helloasso_live_webhook_auto_manage" value="1"' . ($liveWebhookAutoManaged ? ' checked="checked"' : '') . '> <span class="description">' . E::ts("Enable automatic registration of the live HelloAsso webhook for this CiviCRM instance by default. Uncheck only if another instance retains control of the webhook URL.") . '</span></td></tr>';
  $help[] = '</table>';
  $help[] = '<p class="description">' . helloasso_payment_processor_get_partner_required_notice() . '</p>';
  $help[] = '<p class="description">' . E::ts("This block connects the production HelloAsso authorization screen on this live processor: OAuth link, linked organization and webhook registration. The live payment rail can switch to the authorization screen only when this processor no longer uses classic API keys.") . '</p>';
  if ($hasClassicLiveCredentials) {
    $help[] = '<p class="status warning">' . E::ts('The production authorization-screen option is greyed out because this live processor still contains classic API credentials. You can connect and test the authorization screen first, then remove the live API key fields from this processor and save the processor. The authorization-screen option will become selectable once those fields are empty.') . '</p>';
  }
  else {
    $help[] = '<p class="description">' . E::ts("No live API key is stored on this processor. You can therefore enable production authorization-screen mode on this processor once the OAuth link has been validated.") . '</p>';
  }
  if (!$livePluginPublicConfigured) {
    $help[] = '<p class="status warning">' . E::ts('The production authorization-screen option is also greyed out because the production client ID and client secret are not configured yet.') . '</p>';
  }
  if ($liveLink) {
    $help[] = '<p class="status success">' . E::ts('Production organization linked: %1', [1 => htmlspecialchars((string) $liveLink['organization_slug'], ENT_QUOTES, 'UTF-8')]) . '</p>';
    if ($liveLinkedAtLabel !== '') {
      $help[] = '<p class="description">' . E::ts('Linked on: %1', [1 => htmlspecialchars($liveLinkedAtLabel, ENT_QUOTES, 'UTF-8')]) . '</p>';
    }
    if ($liveAccessExpiresAtLabel !== '') {
      $help[] = '<p class="description">' . E::ts("Access token valid until: %1", [1 => htmlspecialchars($liveAccessExpiresAtLabel, ENT_QUOTES, 'UTF-8')]) . '</p>';
    }
    if ($liveRefreshExpiresAtLabel !== '') {
      $help[] = '<p class="description">' . E::ts("Authorization link valid until: %1", [1 => htmlspecialchars($liveRefreshExpiresAtLabel, ENT_QUOTES, 'UTF-8')]) . '</p>';
    }
    if ($liveWebhookRegistration) {
      $help[] = '<p class="description">' . E::ts('Webhook management: %1', [1 => htmlspecialchars(helloasso_payment_processor_describe_webhook_ownership($liveWebhookOwnership), ENT_QUOTES, 'UTF-8')]) . '</p>';
      if (!empty($liveWebhookRegistration['url'])) {
        $help[] = '<p class="description">' . E::ts('Registered webhook URL: %1', [1 => htmlspecialchars((string) $liveWebhookRegistration['url'], ENT_QUOTES, 'UTF-8')]) . '</p>';
      }
    }
  }
  else {
    $help[] = '<p class="status">' . E::ts("No HelloAsso production organization is linked to this processor yet.") . '</p>';
  }
  if ($livePluginPublicConfigured) {
    $help[] = '<p>' . $liveConnectButton . '</p>';
  }
  else {
    $help[] = '<p><button class="button" type="button" disabled="disabled">' . E::ts('Connect production to HelloAsso') . '</button> ';
    $help[] = '<span class="description">' . E::ts("Enter the shared client ID and client secret on the authorization-screen settings page first, then return to start the connection.") . '</span></p>';
  }
  $help[] = '<p><a class="button" href="' . htmlspecialchars($liveConnectUrl, ENT_QUOTES, 'UTF-8') . '">' . E::ts('Open production authorization-screen settings') . '</a></p>';
  $help[] = '</div></fieldset>';

  $help[] = '<fieldset id="helloasso-sandbox-auth-block"><legend>' . E::ts('HelloAsso sandbox connection') . '</legend>';
  $help[] = '<div class="crm-block crm-form-block"><table class="form-layout-compressed">';
  $help[] = '<tr><td>' . E::ts('Sandbox payment processor ID') . '</td><td><code>' . $sandboxProcessorId . '</code></td></tr>';
  $help[] = '<tr><td><label for="helloasso_test_connection_mode">' . E::ts('Sandbox connection mode') . '</label></td><td><select id="helloasso_test_connection_mode" name="helloasso_test_connection_mode">' . implode('', $modeOptions) . '</select></td></tr>';
  $help[] = '<tr><td><label for="helloasso_test_webhook_auto_manage">' . E::ts('Automatically enable the webhook') . '</label></td><td><input type="checkbox" id="helloasso_test_webhook_auto_manage" name="helloasso_test_webhook_auto_manage" value="1"' . ($webhookAutoManaged ? ' checked="checked"' : '') . '> <span class="description">' . E::ts("Enable automatic registration of the HelloAsso webhook for this CiviCRM instance by default. Uncheck only if multiple CiviCRM instances share the same HelloAsso organization and you want to manage the webhook manually.") . '</span></td></tr>';
  $help[] = '</table>';
  if ($hasClassicSandboxCredentials) {
    $help[] = '<p class="description">' . E::ts("Sandbox API credentials are already present on this processor. API key mode remains the safest choice until you explicitly switch.") . '</p>';
  }
  else {
    $help[] = '<p class="description">' . E::ts("No sandbox API key is stored on this processor. The HelloAsso sandbox authorization screen is therefore offered by default.") . '</p>';
  }

  if ($link) {
    $help[] = '<p class="status success">' . E::ts('Sandbox organization linked: %1', [1 => htmlspecialchars((string) $link['organization_slug'], ENT_QUOTES, 'UTF-8')]) . '</p>';
    if ($linkedAtLabel !== '') {
      $help[] = '<p class="description">' . E::ts('Linked on: %1', [1 => htmlspecialchars($linkedAtLabel, ENT_QUOTES, 'UTF-8')]) . '</p>';
    }
    if ($accessExpiresAtLabel !== '') {
      $help[] = '<p class="description">' . E::ts("Access token valid until: %1", [1 => htmlspecialchars($accessExpiresAtLabel, ENT_QUOTES, 'UTF-8')]) . '</p>';
    }
    if ($refreshExpiresAtLabel !== '') {
      $help[] = '<p class="description">' . E::ts("Authorization link valid until: %1", [1 => htmlspecialchars($refreshExpiresAtLabel, ENT_QUOTES, 'UTF-8')]) . '</p>';
    }
    if ($webhookRegistration) {
      $help[] = '<p class="description">' . E::ts('Webhook management: %1', [1 => htmlspecialchars(helloasso_payment_processor_describe_webhook_ownership($webhookOwnership), ENT_QUOTES, 'UTF-8')]) . '</p>';
      if (!empty($webhookRegistration['url'])) {
        $help[] = '<p class="description">' . E::ts('Registered webhook URL: %1', [1 => htmlspecialchars((string) $webhookRegistration['url'], ENT_QUOTES, 'UTF-8')]) . '</p>';
      }
    }
  }
  else {
    $help[] = '<p class="status">' . E::ts("No HelloAsso sandbox organization is linked to this processor yet.") . '</p>';
  }

  if ($sandboxPluginPublicConfigured) {
    $help[] = '<p>' . $sandboxConnectButton . '</p>';
    $help[] = '<p>';
    $help[] = '<a class="button" href="' . htmlspecialchars($connectUrl, ENT_QUOTES, 'UTF-8') . '">' . E::ts('Open authorization-screen settings') . '</a></p>';
  }
  else {
    $help[] = '<p><button class="button" type="button" disabled="disabled">' . E::ts('Connect sandbox to HelloAsso') . '</button> ';
    $help[] = '<span class="description">' . E::ts('This button remains disabled until the shared HelloAsso authorization-screen client ID and client secret are configured.') . '</span></p>';
    $help[] = '<p><a class="button" href="' . htmlspecialchars($connectUrl, ENT_QUOTES, 'UTF-8') . '">' . E::ts('Open authorization-screen settings') . '</a></p>';
  }
  $help[] = '</div></fieldset>';

  $panelHtml = implode('', $help);
  Civi::resources()->addScript("
    CRM.\$(function(\$) {
      var panelHtml = " . json_encode($panelHtml) . ";
      \$('#helloasso-live-auth-block, #helloasso-sandbox-auth-block').remove();
      var \$target = \$('.crm-paymentProcessor-form-block fieldset').last();
      if (\$target.length) {
        \$target.after(panelHtml);
      }
      else {
        \$('.crm-paymentProcessor-form-block .crm-submit-buttons').before(panelHtml);
      }

      var fieldHelp = {
        'HelloAsso_user_name': " . json_encode((string) E::ts('Enter the HelloAsso client ID used by this payment processor for the selected environment. Keep the live and test values aligned with the matching HelloAsso application.')) . ",
        'HelloAsso_password': " . json_encode((string) E::ts('Enter the HelloAsso client secret paired with the client ID above. Store the live and sandbox secrets separately.')) . ",
        'HelloAsso_subject': " . json_encode((string) E::ts('Enter the HelloAsso organization slug or organization name expected by this processor when building API calls and payment links.')) . ",
        'HelloAsso_url_site': " . json_encode((string) E::ts('Base HelloAsso API URL for live payments. Keep the default production URL unless HelloAsso explicitly instructs otherwise.')) . ",
        'HelloAsso_test_user_name': " . json_encode((string) E::ts('Sandbox client ID for HelloAsso test payments.')) . ",
        'HelloAsso_test_password': " . json_encode((string) E::ts('Sandbox client secret for HelloAsso test payments.')) . ",
        'HelloAsso_test_subject': " . json_encode((string) E::ts('Sandbox organization slug or name used for test API calls.')) . ",
        'HelloAsso_test_url_site': " . json_encode((string) E::ts('Base HelloAsso API URL for sandbox payments. Keep the sandbox default unless HelloAsso explicitly instructs otherwise.')) . "
      };

      \$.each(fieldHelp, function(helpId, description) {
        var \$helpIcon = \$('a.helpicon[data-help-id=\"' + helpId + '\"]');
        if (\$helpIcon.length) {
          \$helpIcon.remove();
        }
        var fieldName = helpId.replace('HelloAsso_', '');
        var \$row = \$('.crm-paymentProcessor-form-block-' + fieldName);
        if (\$row.length && !\$row.find('.helloasso-inline-help').length) {
          \$row.find('td').last().append('<br><span class=\"description helloasso-inline-help\">' + CRM.utils.escapeHtml(description) + '</span>');
        }
      });
    });
  ");
}

function helloasso_payment_processor_add_quickform_checkout_controls(
  string $formName,
  CRM_Core_Form $form
): void {
  try {
    $processors = civicrm_api3('PaymentProcessor', 'get', [
      'class_name' => 'Payment_HelloAsso',
      'is_active' => 1,
      'options' => ['limit' => 0],
    ]);
  }
  catch (Exception $e) {
    return;
  }

  $processorIds = [];
  foreach ($processors['values'] ?? [] as $processor) {
    if (!empty($processor['id'])) {
      $processorIds[] = (int) $processor['id'];
    }
  }
  if (!$processorIds) {
    return;
  }

  $supportsInstallments = $formName === 'CRM_Contribute_Form_Contribution_Main'
    && (bool) Civi::settings()->get('helloasso_enable_installments')
    && !empty($form->_values['is_recur_installments']);
  if ($supportsInstallments) {
    $form->add('text', 'helloasso_installments', E::ts('Number of installments'), [
      'min' => 2,
      'max' => 12,
      'inputmode' => 'numeric',
      'pattern' => '[0-9]*',
      'size' => 3,
      'class' => 'three',
    ]);
  }

  $configuredMessage = trim((string) Civi::settings()->get('helloasso_quickform_redirect_message'));
  $defaultMessage = 'You will be redirected to HelloAsso to complete your payment.';
  $message = $configuredMessage !== '' && $configuredMessage !== $defaultMessage
    ? $configuredMessage
    : E::ts($defaultMessage);
  $submittedInstallments = CRM_Utils_Request::retrieveValue(
    'helloasso_installments',
    'Positive',
    NULL,
    FALSE,
    'POST'
  );

  Civi::resources()->addVars('helloassoQuickForm', [
    'processorIds' => $processorIds,
    'supportsInstallments' => $supportsInstallments,
    'message' => $message,
    'installmentsValue' => $submittedInstallments ?: '',
    'installmentsLabel' => E::ts('Number of installments'),
    'oneTimeLabel' => E::ts('One-time payment'),
    'installmentsDescription' => E::ts('Pay in full or split this payment into a fixed schedule of 2 to 12 monthly installments handled by HelloAsso.'),
  ]);
  Civi::resources()->addScriptFile(E::LONG_NAME, 'js/quickform-checkout.js');
}

function helloasso_payment_processor_replace_payment_processor_form_rule(CRM_Core_Form $form): void {
  if (!isset($form->_formRules) || !is_array($form->_formRules)) {
    return;
  }

  $updatedRules = [];
  foreach ($form->_formRules as $ruleConfig) {
    if (!is_array($ruleConfig) || count($ruleConfig) < 2) {
      $updatedRules[] = $ruleConfig;
      continue;
    }

    [$rule, $options] = $ruleConfig;
    if ($rule === ['CRM_Admin_Form_PaymentProcessor', 'formRule']) {
      $updatedRules[] = ['helloasso_payment_processor_payment_processor_form_rule', $form];
      continue;
    }

    $updatedRules[] = $ruleConfig;
  }

  $form->_formRules = $updatedRules;
}

function helloasso_payment_processor_payment_processor_form_rule(mixed $fields, mixed $files, mixed $form): bool|array {
  if (!$form instanceof CRM_Core_Form) {
    return CRM_Admin_Form_PaymentProcessor::formRule($fields);
  }

  $normalizedFields = is_array($fields) ? $fields : [];
  $submittedLiveMode = (string) ($normalizedFields['helloasso_live_connection_mode'] ?? $_POST['helloasso_live_connection_mode'] ?? '');
  $submittedMode = (string) ($normalizedFields['helloasso_test_connection_mode'] ?? $_POST['helloasso_test_connection_mode'] ?? '');
  if ($submittedLiveMode === 'plugin_public') {
    // Allow the live section to be driven by the shared OAuth authorization
    // screen when classic API credentials are intentionally absent.
    $normalizedFields['user_name'] = '__helloasso_plugin_public__';
  }
  if ($submittedMode === 'plugin_public') {
    // Allow the sandbox/test section to be managed by the OAuth authorization
    // screen without reintroducing a requirement for a test API key.
    $normalizedFields['test_user_name'] = '__helloasso_plugin_public__';
  }

  return CRM_Admin_Form_PaymentProcessor::formRule($normalizedFields);
}

function helloasso_payment_processor_get_partner_page_url(string $query): string {
  $query = ltrim($query, '?&');
  $config = CRM_Core_Config::singleton();

  if ($config->userFramework === 'WordPress') {
    return CRM_Utils_System::url('civicrm/helloasso/partner', $query, TRUE, NULL, FALSE, FALSE);
  }

  return CRM_Utils_System::url('civicrm/helloasso/partner', $query, TRUE, NULL, FALSE, TRUE);
}

function helloasso_payment_processor_is_helloasso_settings_form(): bool {
  return in_array(CRM_Utils_System::currentPath(), [
    'civicrm/admin/setting/helloasso',
    'civicrm/admin/setting/preferences/helloasso',
  ], TRUE);
}

function helloasso_payment_processor_get_helloasso_settings_url(): string {
  $config = CRM_Core_Config::singleton();
  if ($config->userFramework === 'WordPress' && function_exists('admin_url')) {
    return admin_url('admin.php?page=CiviCRM&q=' . rawurlencode('civicrm/admin/setting/helloasso') . '&reset=1');
  }

  return CRM_Utils_System::url(
    'civicrm/admin/setting/helloasso',
    'reset=1',
    TRUE,
    NULL,
    FALSE,
    TRUE
  );
}

function helloasso_payment_processor_get_authorize_button_image_url(): string {
  return 'https://api.helloasso.com/v5/img/logo-ha.svg';
}

function helloasso_payment_processor_get_authorize_button_html(string $href, string $label): string {
  $escapedHref = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
  $escapedLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
  $imageUrl = htmlspecialchars(helloasso_payment_processor_get_authorize_button_image_url(), ENT_QUOTES, 'UTF-8');
  $visibleLabel = htmlspecialchars(E::ts('Connect to HelloAsso'), ENT_QUOTES, 'UTF-8');

  return helloasso_payment_processor_get_authorize_button_css()
    . '<a class="helloasso-authorize-button HaAuthorizeButton" href="' . $escapedHref . '" aria-label="' . $escapedLabel . '" title="' . $escapedLabel . '">'
    . '<span class="HaAuthorizeButtonLogoWrap" aria-hidden="true"><img src="' . $imageUrl . '" alt="" class="HaAuthorizeButtonLogo"></span>'
    . '<span class="HaAuthorizeButtonTitle" aria-hidden="true">' . $visibleLabel . '</span>'
    . '<span class="helloasso-authorize-button-sr">' . $escapedLabel . '</span></a>';
}

function helloasso_payment_processor_get_authorize_button_css(): string {
  static $printed = FALSE;
  if ($printed) {
    return '';
  }
  $printed = TRUE;

  return '<style>
.helloasso-authorize-button {
text-decoration: none;
}
.helloasso-authorize-button:hover,
.helloasso-authorize-button:focus,
.helloasso-authorize-button:active {
text-decoration: none;
}
.HaAuthorizeButton {
align-items: center;
background-color: #FFFFFF;
border: 0.0625rem solid #4B3FCF;
border-radius: 0.125rem;
display: inline-flex;
padding: 0;
}
.HaAuthorizeButton:focus {
box-shadow: 0 0 0 0.25rem rgba(73, 211, 138, 0.25);
outline: none;
}
.HaAuthorizeButtonLogoWrap {
align-items: center;
background-color: #FFFFFF;
display: inline-flex;
justify-content: center;
padding: 0 0.8rem;
}
.HaAuthorizeButtonLogo {
display: block;
width: 2.25rem;
}
.HaAuthorizeButtonTitle {
align-items: center;
background-color: #4B3FCF;
color: #FFFFFF;
display: inline-flex;
font-size: 1rem;
font-weight: 700;
line-height: 1;
padding: 0.78125rem 1.5rem;
white-space: nowrap;
}
.helloasso-authorize-button-sr {
border: 0;
clip: rect(0 0 0 0);
height: 1px;
margin: -1px;
overflow: hidden;
padding: 0;
position: absolute;
white-space: nowrap;
width: 1px;
}
</style>';
}

function helloasso_payment_processor_get_partner_required_notice(): string {
  return E::ts('HelloAsso helps associations collect online payments and provides its services free of charge. It covers all transaction fees so that you can receive the full amount paid by your supporters, without fees. Voluntary contributions left by them are its only source of revenue.');
}

function helloasso_payment_processor_describe_webhook_ownership(?string $ownership): string {
  return $ownership === 'manual'
    ? E::ts('manual / another instance in control')
    : E::ts('managed by this CiviCRM instance');
}

function helloasso_payment_processor_get_primary_processor(bool $isTest): ?array {
  try {
    $result = civicrm_api3('PaymentProcessor', 'get', [
      'class_name' => 'Payment_HelloAsso',
      'is_active' => 1,
      'is_test' => $isTest ? 1 : 0,
      'options' => [
        'limit' => 1,
        'sort' => 'id ASC',
      ],
    ]);
  }
  catch (Exception $e) {
    return NULL;
  }

  if (empty($result['values']) || !is_array($result['values'])) {
    return NULL;
  }

  $values = array_values($result['values']);
  return is_array($values[0] ?? NULL) ? $values[0] : NULL;
}

function helloasso_payment_processor_get_payment_processor(int $paymentProcessorId): ?array {
  if (!$paymentProcessorId) {
    return NULL;
  }

  try {
    return civicrm_api3('PaymentProcessor', 'getsingle', ['id' => $paymentProcessorId]);
  }
  catch (Exception $e) {
    return NULL;
  }
}

function helloasso_payment_processor_lock_mjwshared_refund_amount(CRM_Core_Form $form): void {
  $paymentProcessorId = helloasso_payment_processor_get_refund_form_payment_processor_id();
  $processor = $paymentProcessorId ? helloasso_payment_processor_get_payment_processor($paymentProcessorId) : NULL;
  if (($processor['class_name'] ?? NULL) !== 'Payment_HelloAsso') {
    return;
  }

  if (method_exists($form, 'elementExists') && !$form->elementExists('refund_amount')) {
    return;
  }

  try {
    $form->freeze(['refund_amount']);
  }
  catch (Throwable $e) {
    try {
      $form->getElement('refund_amount')->freeze();
    }
    catch (Throwable $ignored) {
    }
  }

  CRM_Core_Resources::singleton()->addScript(<<<'JS'
CRM.$(function($) {
  var $refundAmount = $('#refund_amount');
  if ($refundAmount.length) {
    $refundAmount.prop('readonly', true).attr('aria-readonly', 'true').addClass('crm-form-readonly');
  }
});
JS
  );
}

function helloasso_payment_processor_get_refund_form_payment_processor_id(): ?int {
  $paymentId = CRM_Utils_Request::retrieveValue('payment_id', 'Positive', NULL, FALSE, 'REQUEST');
  if ($paymentId) {
    $paymentProcessorId = CRM_Core_DAO::singleValueQuery(
      'SELECT payment_processor_id FROM civicrm_financial_trxn WHERE id = %1',
      [1 => [(int) $paymentId, 'Integer']]
    );
    return $paymentProcessorId ? (int) $paymentProcessorId : NULL;
  }

  $contributionId = CRM_Utils_Request::retrieveValue('contribution_id', 'Positive', NULL, FALSE, 'REQUEST');
  if (!$contributionId) {
    return NULL;
  }

  $paymentProcessorId = CRM_Core_DAO::singleValueQuery(
    "SELECT ft.payment_processor_id
     FROM civicrm_financial_trxn ft
     INNER JOIN civicrm_entity_financial_trxn eft ON eft.financial_trxn_id = ft.id
     WHERE eft.entity_table = 'civicrm_contribution'
       AND eft.entity_id = %1
       AND ft.is_payment = 1
       AND ft.total_amount > 0
     ORDER BY ft.id DESC
     LIMIT 1",
    [1 => [(int) $contributionId, 'Integer']]
  );

  return $paymentProcessorId ? (int) $paymentProcessorId : NULL;
}

function helloasso_payment_processor_render_settings_page_launch_panel(
  string $title,
  ?array $processor,
  CRM_HelloassoPaymentProcessor_ProcessorAuthConfig $processorAuthConfig,
  bool $enabled,
  bool $credentialsConfigured
): string {
  if (!$processor || empty($processor['id'])) {
    return '<fieldset class="helloasso-mire-settings-card"><legend>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</legend>'
      . '<p class="description">' . E::ts("No matching HelloAsso payment processor was found on this instance.") . '</p>'
      . '</fieldset>';
  }

  $processorId = (int) $processor['id'];
  $organizationLink = $processorAuthConfig->getLinkedOrganization($processorId);
  $webhookRegistration = $processorAuthConfig->getWebhookRegistration($processorId);
  $webhookOwnership = $processorAuthConfig->getWebhookOwnership($processorId);
  $settingsUrl = helloasso_payment_processor_get_partner_page_url('reset=1&processor_id=' . $processorId);
  $connectUrl = helloasso_payment_processor_get_partner_page_url('reset=1&processor_id=' . $processorId . '&ha_action=connect');
  $connectLabel = !empty($processor['is_test']) ? E::ts('Connect sandbox to HelloAsso') : E::ts('Connect production to HelloAsso');
  $linkedAtLabel = helloasso_payment_processor_format_datetime($organizationLink['linked_at'] ?? NULL);
  $accessExpiresAtLabel = helloasso_payment_processor_format_datetime($organizationLink['expires_at'] ?? NULL);
  $refreshExpiresAtLabel = helloasso_payment_processor_format_datetime($organizationLink['refresh_expires_at'] ?? NULL);
  $lastRefreshErrorDateLabel = helloasso_payment_processor_format_datetime($organizationLink['last_refresh_error_date'] ?? NULL);
  $processorTitle = trim((string) ($processor['title'] ?? $processor['name'] ?? ''));

  $html = '<fieldset class="helloasso-mire-settings-card"><legend>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</legend>';
  $html .= '<p class="description">' . E::ts('The operational choice between API key and authorization screen remains on the processor record. This page enables the authorization-screen flow, explains how it works and starts the connection.') . '</p>';
  $html .= '<p><strong>' . E::ts('Target processor:') . '</strong> '
    . htmlspecialchars($processorTitle !== '' ? sprintf('%s (#%d)', $processorTitle, $processorId) : sprintf('#%d', $processorId), ENT_QUOTES, 'UTF-8')
    . '</p>';

  if ($organizationLink) {
    $html .= '<p class="status success">' . E::ts('Organization linked: %1', [1 => htmlspecialchars((string) $organizationLink['organization_slug'], ENT_QUOTES, 'UTF-8')]) . '</p>';
    if (($organizationLink['refresh_status'] ?? '') === 'reconnect_required') {
      $httpStatus = !empty($organizationLink['last_refresh_http_status']) ? ' HTTP ' . (int) $organizationLink['last_refresh_http_status'] : '';
      $failureDate = $lastRefreshErrorDateLabel !== '' ? ' (' . htmlspecialchars($lastRefreshErrorDateLabel, ENT_QUOTES, 'UTF-8') . ')' : '';
      $html .= '<p class="status warning">' . E::ts("The HelloAsso authorization link on this rail can no longer be renewed%1%2. Reconnect the organization before accepting new payments through the authorization screen.", [1 => $httpStatus, 2 => $failureDate]) . '</p>';
    }
    elseif (($organizationLink['refresh_status'] ?? '') === 'organization_blocked') {
      $httpStatus = !empty($organizationLink['last_refresh_http_status']) ? ' HTTP ' . (int) $organizationLink['last_refresh_http_status'] : '';
      $failureDate = $lastRefreshErrorDateLabel !== '' ? ' (' . htmlspecialchars($lastRefreshErrorDateLabel, ENT_QUOTES, 'UTF-8') . ')' : '';
      $html .= '<p class="status warning">' . E::ts("The HelloAsso organization linked on this rail is not currently allowed to receive online payments%1%2. Check its administrative status in HelloAsso before accepting new payments through the authorization screen.", [1 => $httpStatus, 2 => $failureDate]) . '</p>';
    }
    elseif (($organizationLink['refresh_status'] ?? '') === 'refresh_failed') {
      $html .= '<p class="status warning">' . E::ts("The latest HelloAsso authorization-link renewal failed on this rail. Check the next maintenance job or reconnect the organization if the problem persists.") . '</p>';
    }
    if ($linkedAtLabel !== '') {
      $html .= '<p class="description">' . E::ts('Linked on: %1', [1 => htmlspecialchars($linkedAtLabel, ENT_QUOTES, 'UTF-8')]) . '</p>';
    }
    if ($accessExpiresAtLabel !== '') {
      $html .= '<p class="description">' . E::ts("Access token valid until: %1", [1 => htmlspecialchars($accessExpiresAtLabel, ENT_QUOTES, 'UTF-8')]) . '</p>';
    }
    if ($refreshExpiresAtLabel !== '') {
      $html .= '<p class="description">' . E::ts("Authorization link valid until: %1", [1 => htmlspecialchars($refreshExpiresAtLabel, ENT_QUOTES, 'UTF-8')]) . '</p>';
    }
  }
  else {
    $html .= '<p class="status">' . E::ts("No organization is linked on this rail yet.") . '</p>';
  }

  $html .= '<p class="description">' . E::ts('Webhook management: %1', [1 => htmlspecialchars(helloasso_payment_processor_describe_webhook_ownership($webhookOwnership), ENT_QUOTES, 'UTF-8')]) . '</p>';
  if (!empty($webhookRegistration['url'])) {
    $html .= '<p class="description">' . E::ts('Registered webhook URL: %1', [1 => htmlspecialchars((string) $webhookRegistration['url'], ENT_QUOTES, 'UTF-8')]) . '</p>';
  }

  $html .= '<p><a class="button" href="' . htmlspecialchars($settingsUrl, ENT_QUOTES, 'UTF-8') . '">' . E::ts('Open this authorization-screen settings page') . '</a></p>';
  if (!$enabled) {
    $html .= '<p class="description">' . E::ts("Enable the authorization-screen switch below and save the page before starting the connection.") . '</p>';
  }
  elseif (!$credentialsConfigured) {
    $html .= '<p class="description">' . E::ts("Open this authorization-screen settings page first to enter the shared client ID and client secret, then return to start the connection.") . '</p>';
  }
  else {
    $html .= '<p>' . helloasso_payment_processor_get_authorize_button_html($connectUrl, $connectLabel) . '</p>';
  }

  $html .= '</fieldset>';
  return $html;
}

function helloasso_payment_processor_inject_settings_page_panel(): void {
  $enabled = (bool) Civi::settings()->get('helloasso_partner_auth_enabled');
  $sandboxCredentialsConfigured = helloasso_payment_processor_has_partner_client_credentials(TRUE);
  $liveCredentialsConfigured = helloasso_payment_processor_has_partner_client_credentials(FALSE);
  $processorAuthConfig = new CRM_HelloassoPaymentProcessor_ProcessorAuthConfig();
  $sandboxProcessor = helloasso_payment_processor_get_primary_processor(TRUE);
  $liveProcessor = helloasso_payment_processor_get_primary_processor(FALSE);
  $settingsUrl = helloasso_payment_processor_get_helloasso_settings_url();

  $panel = [];
  $panel[] = '<div id="helloasso-mire-settings-panel" class="crm-block crm-form-block">';
  $panel[] = '<fieldset><legend>' . E::ts('HelloAsso authorization-screen flow') . '</legend>';
  $panel[] = '<p>' . helloasso_payment_processor_get_partner_required_notice() . '</p>';
  $panel[] = '<p class="description">' . E::ts("The switch below enables or hides the authorization-screen interface on HelloAsso processors. Shared client credentials and connection startup remain managed from the dedicated authorization-screen pages.") . '</p>';
  $panel[] = '<p class="description">' . E::ts("For an initial configuration: enable the authorization screen here, save the page, then open the sandbox or production rail to enter the client ID and client secret before starting the connection.") . '</p>';
  $panel[] = helloasso_payment_processor_render_settings_page_launch_panel(
    E::ts('HelloAsso sandbox authorization screen'),
    $sandboxProcessor,
    $processorAuthConfig,
    $enabled,
    $sandboxCredentialsConfigured
  );
  $panel[] = helloasso_payment_processor_render_settings_page_launch_panel(
    E::ts('HelloAsso production authorization screen'),
    $liveProcessor,
    $processorAuthConfig,
    $enabled,
    $liveCredentialsConfigured
  );
  $panel[] = '<p class="description">' . E::ts("The official button above opens the HelloAsso connection. If you need to adjust client credentials or check the link status, use the settings link first.") . '</p>';
  $panel[] = '<p class="description"><a href="' . htmlspecialchars($settingsUrl, ENT_QUOTES, 'UTF-8') . '">' . E::ts('Refresh this HelloAsso settings page') . '</a></p>';
  $panel[] = '</fieldset></div>';
  $panelHtml = implode('', $panel);

  Civi::resources()->addScript("
    CRM.\$(function(\$) {
      var panelHtml = " . json_encode($panelHtml) . ";
      var \$panel = \$('#helloasso-mire-settings-panel');
      if (\$panel.length) {
        \$panel.remove();
      }
      var \$submitButtons = \$('.crm-settings-form-block .crm-submit-buttons').first();
      if (\$submitButtons.length) {
        \$submitButtons.before(panelHtml);
        return;
      }
      var \$targetFieldset = \$('.crm-settings-form-block fieldset').first();
      if (\$targetFieldset.length) {
        \$targetFieldset.before(panelHtml);
        return;
      }
      var \$container = \$('.crm-settings-form-block').first();
      if (\$container.length) {
        \$container.prepend(panelHtml);
      }
    });
  ");
}

/**
 * Implements hook_civicrm_postProcess().
 */
function helloasso_payment_processor_civicrm_postProcess(string $formName, mixed &$form): void {
  if ($formName === 'CRM_Mjwshared_Form_PaymentRefund') {
    helloasso_payment_processor_show_refund_success_message();
    return;
  }

  if ($formName !== 'CRM_Admin_Form_PaymentProcessor') {
    return;
  }

  $paymentProcessorType = $form->getVar('_paymentProcessorDAO');
  if (!$paymentProcessorType || $paymentProcessorType->class_name !== 'Payment_HelloAsso') {
    return;
  }

  $processorAuthConfig = new CRM_HelloassoPaymentProcessor_ProcessorAuthConfig();
  $liveProcessorId = (int) $form->getVar('_id');
  $submittedLiveMode = (string) ($_POST['helloasso_live_connection_mode'] ?? '');
  if ($liveProcessorId && in_array($submittedLiveMode, ['community', 'plugin_public'], TRUE)) {
    $liveProcessor = helloasso_payment_processor_get_payment_processor($liveProcessorId) ?? [];
    if (
      $submittedLiveMode === 'plugin_public'
      && (
        !$liveProcessor
        || $processorAuthConfig->hasClassicCredentials($liveProcessor)
        || !helloasso_payment_processor_has_partner_client_credentials(FALSE)
      )
    ) {
      $submittedLiveMode = 'community';
    }
    $processorAuthConfig->setConnectionMode($liveProcessorId, $submittedLiveMode);
    $processorAuthConfig->setWebhookOwnership(
      $liveProcessorId,
      !empty($_POST['helloasso_live_webhook_auto_manage']) ? 'managed_by_civicrm' : 'manual'
    );
  }

  $sandboxProcessor = helloasso_payment_processor_get_editable_sandbox_processor($form);
  if (!$sandboxProcessor) {
    return;
  }

  $submittedMode = (string) ($_POST['helloasso_test_connection_mode'] ?? '');
  if (!in_array($submittedMode, ['community', 'plugin_public'], TRUE)) {
    return;
  }

  $processorAuthConfig->setConnectionMode((int) $sandboxProcessor['id'], $submittedMode);
  $processorAuthConfig->setWebhookOwnership(
    (int) $sandboxProcessor['id'],
    !empty($_POST['helloasso_test_webhook_auto_manage']) ? 'managed_by_civicrm' : 'manual'
  );
}

/**
 * Implements hook_civicrm_check().
 */
function helloasso_payment_processor_civicrm_check(mixed &$messages): void {
  try {
    $missingColumns = helloasso_payment_processor_get_missing_metadata_columns([
      'checkout_intent_id',
      'helloasso_payment_id',
      'event_type',
      'state',
      'payment_processor_id',
      'sync_origin_date',
      'sync_next_date',
      'sync_last_date',
      'sync_attempt_count',
      'sync_error_count',
      'long_sync_scheme',
      'long_sync_origin_date',
      'long_sync_next_date',
      'long_sync_last_date',
      'long_sync_attempt_count',
      'long_sync_error_count',
    ]);
    $missingAuthColumns = helloasso_payment_processor_get_missing_auth_columns([
      'refresh_issued_at',
      'refresh_status',
      'last_refresh_error',
      'last_refresh_error_date',
      'last_refresh_http_status',
    ]);

    $missingIndexes = helloasso_payment_processor_get_missing_metadata_indexes([
      'index_contribution_id',
      'index_checkout_intent_id',
      'index_helloasso_payment_id',
      'index_payment_processor_id_sync_next_date',
      'index_sync_next_date',
      'index_payment_processor_id_long_sync_next_date',
    ]);

    if ($missingColumns || $missingAuthColumns || $missingIndexes) {
      $details = [];
      if ($missingColumns) {
        $details[] = E::ts('Missing metadata columns: %1', [1 => implode(', ', $missingColumns)]);
      }
      if ($missingAuthColumns) {
        $details[] = E::ts('Missing authorization columns: %1', [1 => implode(', ', $missingAuthColumns)]);
      }
      if ($missingIndexes) {
        $details[] = E::ts('Missing indexes: %1', [1 => implode(', ', $missingIndexes)]);
      }

      $messages[] = new CRM_Utils_Check_Message(
        'helloasso_payment_processor_schema_incomplete',
        E::ts(
          'The HelloAsso v2 schema upgrade is incomplete. %1 Run <code>cv updb</code> and then <code>cv flush</code> before relying on webhook queueing, follow-up sync, or legacy repair.',
          [1 => implode(' ', $details)]
        ),
        E::ts('HelloAsso: Incomplete V2 Upgrade'),
        \Psr\Log\LogLevel::WARNING,
        'fa-exclamation-triangle'
      );
    }
  }
  catch (Exception $e) {
    $messages[] = new CRM_Utils_Check_Message(
      'helloasso_payment_processor_schema_check_failed',
      E::ts('The HelloAsso schema check could not be completed: %1', [1 => $e->getMessage()]),
      E::ts('HelloAsso: Schema Check Failed'),
      \Psr\Log\LogLevel::WARNING,
      'fa-exclamation-triangle'
    );
  }

  try {
    $processorAuthConfig = new CRM_HelloassoPaymentProcessor_ProcessorAuthConfig();
    $linkedProcessors = $processorAuthConfig->getLinkedProcessors();
    $now = time();
    $expiringSoon = [];
    $expired = [];
    $missingWebhook = [];
    $refreshFailed = [];
    $reconnectRequired = [];
    $organizationBlocked = [];

    $domainMismatches = [];
    $webhookMismatches = [];

    foreach ($linkedProcessors as $paymentProcessorId => $link) {
      $refreshExpiresAt = (int) ($link['refresh_expires_at'] ?? 0);
      $label = helloasso_payment_processor_describe_payment_processor($paymentProcessorId, $link);
      $partnerUrl = htmlspecialchars(helloasso_payment_processor_get_partner_page_url('reset=1&processor_id=' . (int) $paymentProcessorId), ENT_QUOTES, 'UTF-8');
      $linkedLabel = '<a href="' . $partnerUrl . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
      $refreshStatus = (string) ($link['refresh_status'] ?? '');
      if ($refreshStatus === 'reconnect_required') {
        $status = !empty($link['last_refresh_http_status']) ? 'HTTP ' . (int) $link['last_refresh_http_status'] : E::ts('refresh refused');
        $date = helloasso_payment_processor_format_datetime($link['last_refresh_error_date'] ?? NULL);
        $reconnectRequired[] = sprintf('%s (%s%s)', $linkedLabel, $status, $date !== '' ? ', ' . htmlspecialchars($date, ENT_QUOTES, 'UTF-8') : '');
      }
      elseif ($refreshStatus === 'organization_blocked') {
        $status = !empty($link['last_refresh_http_status']) ? 'HTTP ' . (int) $link['last_refresh_http_status'] : E::ts('payment refused');
        $date = helloasso_payment_processor_format_datetime($link['last_refresh_error_date'] ?? NULL);
        $organizationBlocked[] = sprintf('%s (%s%s)', $linkedLabel, $status, $date !== '' ? ', ' . htmlspecialchars($date, ENT_QUOTES, 'UTF-8') : '');
      }
      elseif ($refreshStatus === 'refresh_failed') {
        $refreshFailed[] = $linkedLabel;
      }
      if ($processorAuthConfig->isWebhookAutoRegistrationEnabled($paymentProcessorId) && !$processorAuthConfig->getWebhookRegistration($paymentProcessorId)) {
        $missingWebhook[] = $label;
      }

      // Check for domain mismatch
      if (!empty($link['redirect_uri'])) {
        $authorizedHost = parse_url($link['redirect_uri'], PHP_URL_HOST);
        $currentHost = parse_url(CRM_Utils_System::url(), PHP_URL_HOST);
        if ($authorizedHost && $currentHost && strcasecmp($authorizedHost, $currentHost) !== 0) {
          $domainMismatches[] = sprintf('%s (%s %s %s)', $linkedLabel, htmlspecialchars($currentHost, ENT_QUOTES, 'UTF-8'), E::ts('instead of'), htmlspecialchars($authorizedHost, ENT_QUOTES, 'UTF-8'));
        }
      }

      // Check for webhook URL domain mismatch
      $webhookRegistration = $processorAuthConfig->getWebhookRegistration($paymentProcessorId);
      if (!empty($webhookRegistration['url'])) {
        $webhookHost = parse_url($webhookRegistration['url'], PHP_URL_HOST);
        $currentHost = parse_url(CRM_Utils_System::url(), PHP_URL_HOST);
        if ($webhookHost && $currentHost && strcasecmp($webhookHost, $currentHost) !== 0) {
          $webhookMismatches[] = sprintf('%s (%s)', $linkedLabel, htmlspecialchars((string) $webhookRegistration['url'], ENT_QUOTES, 'UTF-8'));
        }
      }

      if (!$refreshExpiresAt) {
        continue;
      }

      if ($refreshExpiresAt <= $now) {
        $expired[] = $label;
        continue;
      }

      if ($refreshExpiresAt <= ($now + 7 * 24 * 60 * 60)) {
        $expiringSoon[] = sprintf('%s (%s)', $label, date('Y-m-d H:i:s', $refreshExpiresAt));
      }
    }

    if ($domainMismatches) {
      $messages[] = new CRM_Utils_Check_Message(
        'helloasso_payment_processor_domain_mismatch',
        E::ts('Domain mismatch detected: this CiviCRM instance runs on a different host than the one authorized for the HelloAsso link: %1. OAuth callbacks will not function correctly on this staging/dev instance.', [1 => implode('; ', $domainMismatches)]),
        E::ts('HelloAsso: Domain Mismatch (Staging/Dev Instance)'),
        \Psr\Log\LogLevel::WARNING,
        'fa-globe'
      );
    }

    if ($webhookMismatches) {
      $messages[] = new CRM_Utils_Check_Message(
        'helloasso_payment_processor_webhook_domain_mismatch',
        E::ts('The HelloAsso webhook registered for %1 points to a different domain. Inbound payments will not be processed on this CiviCRM instance: %2.', [1 => implode('; ', $webhookMismatches), 2 => htmlspecialchars(parse_url(CRM_Utils_System::url(), PHP_URL_HOST), ENT_QUOTES, 'UTF-8')]),
        E::ts('HelloAsso: Webhook Domain Mismatch'),
        \Psr\Log\LogLevel::WARNING,
        'fa-plug'
      );
    }

    if ($expired) {
      $messages[] = new CRM_Utils_Check_Message(
        'helloasso_payment_processor_oauth_expired',
        E::ts('One or more HelloAsso authorization links have expired and must be reconnected: %1', [1 => implode('; ', $expired)]),
        E::ts('HelloAsso: Authorization Expired'),
        \Psr\Log\LogLevel::WARNING,
        'fa-unlink'
      );
    }

    if ($reconnectRequired) {
      $messages[] = new CRM_Utils_Check_Message(
        'helloasso_payment_processor_oauth_reconnect_required',
        E::ts('One or more HelloAsso authorization links can no longer be renewed. Reconnect them before accepting new payments through the authorization screen: %1', [1 => implode('; ', $reconnectRequired)]),
        E::ts('HelloAsso: Reconnection Required'),
        \Psr\Log\LogLevel::ERROR,
        'fa-unlink'
      );
    }

    if ($organizationBlocked) {
      $messages[] = new CRM_Utils_Check_Message(
        'helloasso_payment_processor_organization_blocked',
        E::ts('One or more HelloAsso organizations linked on this instance are not currently allowed by HelloAsso to receive online payments. Check their administrative status before accepting new payments through the authorization screen: %1', [1 => implode('; ', $organizationBlocked)]),
        E::ts('HelloAsso: Organization Cannot Receive Payments'),
        \Psr\Log\LogLevel::ERROR,
        'fa-ban'
      );
    }

    if ($refreshFailed) {
      $messages[] = new CRM_Utils_Check_Message(
        'helloasso_payment_processor_oauth_refresh_failed',
        E::ts('The last HelloAsso authorization-link renewal failed for: %1. The maintenance job will retry; reconnect if the failure continues.', [1 => implode('; ', $refreshFailed)]),
        E::ts('HelloAsso: Authorization Renewal Failed'),
        \Psr\Log\LogLevel::WARNING,
        'fa-exclamation-triangle'
      );
    }

    if ($expiringSoon) {
      $messages[] = new CRM_Utils_Check_Message(
        'helloasso_payment_processor_oauth_expiring_soon',
        E::ts('One or more HelloAsso authorization links will expire soon: %1', [1 => implode('; ', $expiringSoon)]),
        E::ts('HelloAsso: Authorization Expiring Soon'),
        \Psr\Log\LogLevel::NOTICE,
        'fa-clock-o'
      );
    }

    if ($missingWebhook) {
      $messages[] = new CRM_Utils_Check_Message(
        'helloasso_payment_processor_webhook_missing',
        E::ts('One or more HelloAsso links expect automatic webhook registration, but no webhook is stored yet: %1. Reconnect the authorization screen to push the webhook, or disable automatic webhook registration on that processor if another CiviCRM instance must keep manual control of the HelloAsso webhook URL.', [1 => implode('; ', $missingWebhook)]),
        E::ts('HelloAsso: Webhook Not Configured'),
        \Psr\Log\LogLevel::NOTICE,
        'fa-plug'
      );
    }
  }
  catch (Exception $e) {
    $messages[] = new CRM_Utils_Check_Message(
      'helloasso_payment_processor_oauth_check_failed',
      E::ts('The HelloAsso authorization check could not be completed: %1', [1 => $e->getMessage()]),
      E::ts('HelloAsso: Authorization Check Failed'),
      \Psr\Log\LogLevel::WARNING,
      'fa-exclamation-triangle'
    );
  }

  try {
    $processors = civicrm_api3('PaymentProcessor', 'get', [
      'class_name' => 'Payment_HelloAsso',
      'is_active' => 1,
      'options' => ['limit' => 0],
    ]);
    $processorAuthConfig = new CRM_HelloassoPaymentProcessor_ProcessorAuthConfig();
    $invalidApiKeys = [];
    $missingPaymentInstruments = [];
    $unexpectedPaymentInstruments = [];

    foreach ($processors['values'] ?? [] as $processor) {
      $processorId = (int) ($processor['id'] ?? 0);
      if (!$processorId) {
        continue;
      }

      if (empty($processor['payment_instrument_id'])) {
        $missingPaymentInstruments[] = helloasso_payment_processor_describe_payment_processor($processorId, []);
      }
      elseif (CRM_Core_PseudoConstant::getName('CRM_Contribute_BAO_Contribution', 'payment_instrument_id', (int) $processor['payment_instrument_id']) === 'Check') {
        $unexpectedPaymentInstruments[] = helloasso_payment_processor_describe_payment_processor($processorId, []);
      }

      if ($processorAuthConfig->shouldUsePluginPublic($processorId, $processor)) {
        continue;
      }

      if (trim((string) ($processor['user_name'] ?? '')) === '' || trim((string) ($processor['password'] ?? '')) === '') {
        continue;
      }

      $status = helloasso_payment_processor_get_api_key_health($processor);
      if (!$status['ok']) {
        $invalidApiKeys[] = sprintf(
          '%s (%s)',
          helloasso_payment_processor_get_payment_processor_admin_link($processorId),
          $status['message']
        );
      }
    }

    if ($missingPaymentInstruments) {
      $messages[] = new CRM_Utils_Check_Message(
        'helloasso_payment_processor_payment_instrument_missing',
        E::ts('One or more HelloAsso payment processors have no payment method configured: %1. Configure a payment method on the processor; new processors use the dedicated HelloAsso payment instrument by default.', [1 => implode('; ', $missingPaymentInstruments)]),
        E::ts('HelloAsso: Payment Method Missing'),
        \Psr\Log\LogLevel::WARNING,
        'fa-credit-card'
      );
    }

    if ($unexpectedPaymentInstruments) {
      $messages[] = new CRM_Utils_Check_Message(
        'helloasso_payment_processor_payment_instrument_check',
        E::ts('One or more HelloAsso payment processors use Check as their payment method: %1. Review the processor configuration and select the dedicated HelloAsso payment instrument or your existing HelloAsso online payment method.', [1 => implode('; ', $unexpectedPaymentInstruments)]),
        E::ts('HelloAsso: Payment Method Requires Review'),
        \Psr\Log\LogLevel::WARNING,
        'fa-credit-card'
      );
    }

    if ($invalidApiKeys) {
      $messages[] = new CRM_Utils_Check_Message(
        'helloasso_payment_processor_api_key_invalid',
        E::ts('One or more HelloAsso payment processors still rely on API keys that could not authenticate: %1', [1 => implode('; ', $invalidApiKeys)]),
        E::ts('HelloAsso: API Key Authentication Failed'),
        \Psr\Log\LogLevel::WARNING,
        'fa-key'
      );
    }
  }
  catch (Exception $e) {
    $messages[] = new CRM_Utils_Check_Message(
      'helloasso_payment_processor_api_key_check_failed',
      E::ts('The HelloAsso API key health check could not be completed: %1', [1 => $e->getMessage()]),
      E::ts('HelloAsso: API Key Check Failed'),
      \Psr\Log\LogLevel::WARNING,
      'fa-exclamation-triangle'
    );
  }
}

function helloasso_payment_processor_describe_payment_processor(int $paymentProcessorId, array $link = []): string {
  try {
    $processor = civicrm_api3('PaymentProcessor', 'getsingle', ['id' => $paymentProcessorId]);
    $title = trim((string) ($processor['title'] ?? $processor['name'] ?? ''));
    if ($title !== '') {
      $mode = !empty($processor['is_test']) ? E::ts('sandbox') : E::ts('production');
      return sprintf('%s (#%d, %s)', $title, $paymentProcessorId, $mode);
    }
  }
  catch (Exception $e) {
    // Fall back to the organization slug if the processor has gone missing.
  }

  $organizationSlug = trim((string) ($link['organization_slug'] ?? ''));
  if ($organizationSlug !== '') {
    return sprintf('%s (#%d)', $organizationSlug, $paymentProcessorId);
  }

  return sprintf('Processor #%d', $paymentProcessorId);
}

function helloasso_payment_processor_get_payment_processor_admin_link(int $paymentProcessorId): string {
  $label = helloasso_payment_processor_describe_payment_processor($paymentProcessorId, []);
  $adminProcessorId = helloasso_payment_processor_get_admin_edit_payment_processor_id($paymentProcessorId);
  $url = CRM_Utils_System::url(
    'civicrm/admin/paymentProcessor/edit',
    'action=update&id=' . $adminProcessorId . '&reset=1',
    FALSE,
    NULL,
    FALSE
  );

  return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
    . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
    . '</a>';
}

function helloasso_payment_processor_get_admin_edit_payment_processor_id(int $paymentProcessorId): int {
  try {
    $processor = civicrm_api3('PaymentProcessor', 'getsingle', ['id' => $paymentProcessorId]);
    if (empty($processor['is_test'])) {
      return $paymentProcessorId;
    }

    $lookups = [];
    if (!empty($processor['name'])) {
      $lookups[] = ['name' => $processor['name'], 'is_test' => 0];
    }
    $lookups[] = ['id' => $paymentProcessorId + 1, 'is_test' => 0];

    foreach ($lookups as $params) {
      try {
        $liveProcessor = civicrm_api3('PaymentProcessor', 'getsingle', $params);
        if (
          !empty($liveProcessor['id'])
          && (empty($processor['payment_processor_type_id'])
            || empty($liveProcessor['payment_processor_type_id'])
            || (int) $liveProcessor['payment_processor_type_id'] === (int) $processor['payment_processor_type_id'])
        ) {
          return (int) $liveProcessor['id'];
        }
      }
      catch (Exception $e) {
        // Try the next safe lookup before falling back to the original id.
      }
    }
  }
  catch (Exception $e) {
    return $paymentProcessorId;
  }
  return $paymentProcessorId;
}

function helloasso_payment_processor_get_api_key_health(array $paymentProcessor): array {
  $processorId = (int) ($paymentProcessor['id'] ?? 0);
  $cacheKey = 'helloasso-api-key-health-' . $processorId;
  $cached = Civi::cache('long')->get($cacheKey);
  if (is_array($cached) && !empty($cached['checked_at']) && ((time() - (int) $cached['checked_at']) < 3600)) {
    return $cached;
  }

  $result = [
    'ok' => FALSE,
    'message' => E::ts('Unknown authentication failure'),
    'checked_at' => time(),
  ];

  try {
    $isTest = !empty($paymentProcessor['is_test']);
    $baseUrl = rtrim((string) ($paymentProcessor['url_site'] ?? ''), '/');
    if ($baseUrl === '') {
      $result['message'] = E::ts('Missing site URL');
    }
    else {
      $oauthUrl = $baseUrl . '/oauth2/token';
      CRM_HelloassoPaymentProcessor_HelloAssoClient::getInstance()->invalidateToken($isTest, $paymentProcessor);
      CRM_HelloassoPaymentProcessor_HelloAssoClient::getInstance()->getToken(
        $isTest,
        $paymentProcessor,
        $oauthUrl,
        (string) ($paymentProcessor['user_name'] ?? ''),
        (string) ($paymentProcessor['password'] ?? '')
      );
      $result['ok'] = TRUE;
      $result['message'] = 'OK';
    }
  }
  catch (Exception $e) {
    $result['message'] = $e->getMessage();
  }

  Civi::cache('long')->set($cacheKey, $result, DateInterval::createFromDateString('1 hour'));
  return $result;
}

function helloasso_payment_processor_format_datetime(mixed $value): string {
  if ($value === NULL || $value === '') {
    return '';
  }

  try {
    if (is_numeric($value)) {
      $date = new DateTimeImmutable('@' . (int) $value);
      $date = $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
      $formattedStr = $date->format('Y-m-d H:i:s');
    }
    else {
      $date = new DateTimeImmutable((string) $value);
      $formattedStr = $date->format('Y-m-d H:i:s');
    }

    if (class_exists('CRM_Utils_Date')) {
      return CRM_Utils_Date::customFormat($formattedStr);
    }
    return $formattedStr;
  }
  catch (Exception $e) {
    return (string) $value;
  }
}

function helloasso_payment_processor_protect_test_processor_edit(CRM_Core_Form $form): void {
  $currentId = (int) $form->getVar('_id');
  if (!$currentId) {
    return;
  }

  try {
    $currentProcessor = civicrm_api3('PaymentProcessor', 'getsingle', ['id' => $currentId]);
  }
  catch (Exception $e) {
    return;
  }

  if (empty($currentProcessor['is_test']) || empty($currentProcessor['name'])) {
    return;
  }

  try {
    $liveProcessor = civicrm_api3('PaymentProcessor', 'getsingle', [
      'name' => $currentProcessor['name'],
      'is_test' => 0,
    ]);
  }
  catch (Exception $e) {
    return;
  }

  if (empty($liveProcessor['id'])) {
    return;
  }

  CRM_Core_Session::setStatus(
    E::ts("You opened the HelloAsso test processor directly. CiviCRM stores the live and test values together on this screen. To avoid overwriting production, you will be redirected to the associated live processor record."),
    E::ts('HelloAsso'),
    'warning'
  );

  CRM_Utils_System::redirect(CRM_Utils_System::url(
    'civicrm/admin/paymentProcessor/edit',
    'action=update&id=' . (int) $liveProcessor['id'] . '&reset=1'
  ));
}

function helloasso_payment_processor_get_editable_sandbox_processor(CRM_Core_Form $form): ?array {
  $testProcessorId = (int) $form->getVar('_testID');
  if (!$testProcessorId) {
    $liveProcessorId = (int) $form->getVar('_id');
    if ($liveProcessorId) {
      try {
        $liveProcessor = civicrm_api3('PaymentProcessor', 'getsingle', ['id' => $liveProcessorId]);
        if (!empty($liveProcessor['is_test'])) {
          $testProcessorId = $liveProcessorId;
        }
        elseif (!empty($liveProcessor['name'])) {
          $sandboxProcessor = civicrm_api3('PaymentProcessor', 'getsingle', [
            'name' => $liveProcessor['name'],
            'is_test' => 1,
          ]);
          $testProcessorId = (int) ($sandboxProcessor['id'] ?? 0);
        }
      }
      catch (Exception $e) {
        return NULL;
      }
    }
  }

  if (!$testProcessorId) {
    return NULL;
  }

  try {
    return civicrm_api3('PaymentProcessor', 'getsingle', ['id' => $testProcessorId]);
  }
  catch (Exception $e) {
    return NULL;
  }
}

function helloasso_payment_processor_has_partner_client_credentials(?bool $isTest = NULL): bool {
  $credentials = new CRM_HelloassoPaymentProcessor_PartnerCredentials();
  if ($isTest === TRUE) {
    return $credentials->hasCredentials(TRUE);
  }
  if ($isTest === FALSE) {
    return $credentials->hasCredentials(FALSE);
  }
  return FALSE;
}

/**
 * Implements hook_civicrm_cron().
 */
function helloasso_payment_processor_civicrm_cron(mixed $job = NULL): void {
  try {
    $processors = civicrm_api3('PaymentProcessor', 'get', [
      'class_name' => 'Payment_HelloAsso',
      'is_active' => 1,
      'options' => ['limit' => 0],
    ]);
  }
  catch (Exception $e) {
    Civi::log()->error('HelloAsso cron failed to list payment processors: ' . $e->getMessage());
    return;
  }

  foreach ($processors['values'] ?? [] as $processor) {
    try {
      $mode = !empty($processor['is_test']) ? 'test' : 'live';
      $paymentProcessor = new CRM_Core_Payment_HelloAsso($mode, $processor);
      $result = $paymentProcessor->processScheduledSynchronization([
        'only_scheduled' => TRUE,
        'due_before' => date('Y-m-d H:i:s'),
        'limit' => max(1, (int) (Civi::settings()->get('helloasso_v2_cron_limit') ?? 15)),
      ]);

      if (!empty($result['checked'])) {
        CRM_HelloassoPaymentProcessor_Logger::debug(sprintf(
          'HelloAsso short cron sync checked %d contribution(s), updated %d, errors %d.',
          $result['checked'],
          $result['updated'],
          count($result['errors'])
        ));
      }

      foreach ($result['errors'] as $error) {
        Civi::log()->error('HelloAsso short cron sync error: ' . $error);
      }

      $longResult = $paymentProcessor->processLongScheduledSynchronization([
        'due_before' => date('Y-m-d H:i:s'),
        'limit' => max(1, (int) (Civi::settings()->get('helloasso_v2_cron_limit') ?? 15)),
      ]);

      if (!empty($longResult['checked'])) {
        CRM_HelloassoPaymentProcessor_Logger::debug(sprintf(
          'HelloAsso long cron sync checked %d contribution(s), updated %d, errors %d.',
          $longResult['checked'],
          $longResult['updated'],
          count($longResult['errors'])
        ));
      }

      foreach ($longResult['errors'] as $error) {
        Civi::log()->error('HelloAsso long cron sync error: ' . $error);
      }
    }
    catch (Exception $e) {
      Civi::log()->error('HelloAsso cron sync failed: ' . $e->getMessage());
    }
  }
}

function helloasso_payment_processor_show_refund_success_message(): void {
  $session = CRM_Core_Session::singleton();
  $refund = $session->get('last_refund', 'helloasso_payment_processor');
  $session->set('last_refund', NULL, 'helloasso_payment_processor');
  if (empty($refund['payment_id']) || empty($refund['refund_operation_id'])) {
    return;
  }

  CRM_Core_Session::setStatus(
    E::ts('HelloAsso has accepted the refund request for payment %1. Refund operation %2 has been recorded in CiviCRM; the final HelloAsso refund state will be confirmed later by webhook or scheduled synchronization.', [
      1 => (string) $refund['payment_id'],
      2 => (string) $refund['refund_operation_id'],
    ]),
    E::ts('HelloAsso refund requested'),
    'success'
  );
}

/**
 * Implements hook_civicrm_validateForm().
 */
function helloasso_payment_processor_civicrm_validateForm(string $formName, mixed &$fields, mixed &$files, mixed &$form, mixed &$errors): void {
  // Check if this is a form that might use a payment processor
  $is_payment_form = in_array($formName, [
    'CRM_Contribute_Form_Contribution_Main',
    'CRM_Event_Form_Registration_Register',
  ]);

  if (!$is_payment_form) {
    return;
  }

  // Check if HelloAsso is the selected payment processor
  $processor_id = $form->get('paymentProcessor');
  $is_helloasso = false;

  if (is_object($processor_id)) {
      if (is_a($processor_id, 'CRM_Core_Payment_HelloAsso')) {
          $is_helloasso = true;
      } else {
          return; // It's another payment processor
      }
  } else {
      if (!$processor_id && !empty($fields['payment_processor_id'])) {
          $processor_id = $fields['payment_processor_id'];
      }

      if (is_array($processor_id) && isset($processor_id['id'])) {
          $processor_id = $processor_id['id'];
      }

      if ($processor_id && is_numeric($processor_id)) {
        try {
          $processor = civicrm_api3('PaymentProcessor', 'getsingle', ['id' => $processor_id]);
          if ($processor['class_name'] === 'Payment_HelloAsso') {
            $is_helloasso = true;
          } else {
            return;
          }
        } catch (Exception $e) {
          return; // Processor not found or error
        }
      } else {
        // Cannot determine processor, maybe it's a free event or offline. Skip validation.
        return;
      }
  }

  if (!$is_helloasso) {
      return;
  }

  if (
    $formName === 'CRM_Contribute_Form_Contribution_Main'
    && array_key_exists('helloasso_installments', $fields)
  ) {
    try {
      $fields = CRM_HelloassoPaymentProcessor_QuickFormInstallments::apply($fields);
    }
    catch (InvalidArgumentException $e) {
      $errors['helloasso_installments'] = E::ts('HelloAsso requires between 2 and 12 installments.');
    }
  }

  if (
    $formName === 'CRM_Contribute_Form_Contribution_Main'
    && !empty($fields['is_recur'])
  ) {
    $installments = filter_var(
      $fields['installments'] ?? NULL,
      FILTER_VALIDATE_INT
    );
    if ($installments === FALSE || $installments < 2 || $installments > 12) {
      $errors['installments'] = E::ts('HelloAsso requires between 2 and 12 installments.');
    }

    if (($fields['frequency_unit'] ?? '') !== 'month') {
      $errors['frequency_unit'] = E::ts('HelloAsso installments must be monthly.');
    }

    $frequencyInterval = filter_var(
      $fields['frequency_interval'] ?? NULL,
      FILTER_VALIDATE_INT
    );
    if ($frequencyInterval !== 1) {
      $errors['frequency_interval'] = E::ts('HelloAsso installments must be collected every month.');
    }
  }

  // Find the names in the fields (could be first_name/last_name or billing_first_name/billing_last_name)
  $first_name_key = isset($fields['billing_first_name']) ? 'billing_first_name' : (isset($fields['first_name']) ? 'first_name' : null);
  $last_name_key = isset($fields['billing_last_name']) ? 'billing_last_name' : (isset($fields['last_name']) ? 'last_name' : null);

  $first_name = $first_name_key ? ($fields[$first_name_key] ?? '') : '';
  $last_name = $last_name_key ? ($fields[$last_name_key] ?? '') : '';

  $forbidden_values = ['firstname', 'lastname', 'unknown', 'first name', 'user', 'admin', 'name', 'nom', 'prénom', 'test', 'last name', 'anonyme'];

  $validate_name = function($value, $field_key) use (&$errors, $forbidden_values) {
    if (empty($value)) return;

    $val_lower = mb_strtolower(trim($value), 'UTF-8');
    $val_no_accents = mb_strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $value));

    if (mb_strlen($value, 'UTF-8') < 3) {
      $errors[$field_key] = ts('First/last name must contain at least 3 characters (HelloAsso rule).');
    } elseif (preg_match('/(.)\1\1/u', $val_lower)) {
      $errors[$field_key] = ts('First/last name must not contain 3 repeated characters (HelloAsso rule).');
    } elseif (preg_match('/[0-9]/', $value)) {
      $errors[$field_key] = ts('First/last name must not contain numbers (HelloAsso rule).');
    } elseif (!preg_match('/[aeiouyAEIOUYéèêëàâäùûüîïôö]/u', $value)) {
      $errors[$field_key] = ts('First/last name must contain at least one vowel (HelloAsso rule).');
    } elseif (in_array($val_lower, $forbidden_values) || in_array($val_no_accents, $forbidden_values) || in_array(str_replace('_', ' ', $val_lower), $forbidden_values)) {
      $errors[$field_key] = ts('This value is not allowed by HelloAsso.');
    } elseif (!preg_match('/^[\p{Latin}\s\'\-]+$/u', $value)) {
      $errors[$field_key] = ts('First/last name contains unauthorized special characters (HelloAsso rule).');
    }
  };

  if ($first_name) {
    $validate_name($first_name, $first_name_key);
  }
  if ($last_name) {
    $validate_name($last_name, $last_name_key);
  }

  if ($first_name && $last_name && mb_strtolower(trim($first_name), 'UTF-8') === mb_strtolower(trim($last_name), 'UTF-8')) {
    $errors[$last_name_key] = ts('First name and last name must not be identical (HelloAsso rule).');
  }
}

function helloasso_payment_processor_get_missing_metadata_columns(array $expectedColumns): array {
  $existingColumns = [];
  $dao = CRM_Core_DAO::executeQuery('SHOW COLUMNS FROM civicrm_hello_asso_metadata');
  while ($dao->fetch()) {
    $existingColumns[] = $dao->Field;
  }

  return array_values(array_diff($expectedColumns, $existingColumns));
}

function helloasso_payment_processor_get_missing_auth_columns(array $expectedColumns): array {
  $existingColumns = [];
  try {
    $dao = CRM_Core_DAO::executeQuery('SHOW COLUMNS FROM civicrm_hello_asso_processor_auth');
    while ($dao->fetch()) {
      $existingColumns[] = $dao->Field;
    }
  }
  catch (Exception $e) {
    return $expectedColumns;
  }

  return array_values(array_diff($expectedColumns, $existingColumns));
}

function helloasso_payment_processor_get_missing_metadata_indexes(array $expectedIndexes): array {
  $existingIndexes = [];
  $dao = CRM_Core_DAO::executeQuery('SHOW INDEX FROM civicrm_hello_asso_metadata');
  while ($dao->fetch()) {
    $existingIndexes[] = $dao->Key_name;
  }

  return array_values(array_diff($expectedIndexes, array_unique($existingIndexes)));
}
