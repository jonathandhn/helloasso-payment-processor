<?php

use Civi\Payment\Exception\PaymentProcessorException;
use CRM_HelloassoPaymentProcessor_ExtensionUtil as E;

class CRM_HelloassoPaymentProcessor_Page_PartnerAuth extends CRM_Core_Page {

  private const FORM_TOKEN_SESSION_KEY = 'helloasso_partner_auth_form_token';

  public function run() {
    $action = CRM_Utils_Request::retrieve('ha_action', 'String', $this, FALSE, 'status');
    $paymentProcessorId = (int) CRM_Utils_Request::retrieve('processor_id', 'Positive', $this, FALSE, 0);
    if ($action === 'status' && CRM_Utils_System::currentPath() === 'civicrm/helloasso/partner/callback') {
      $action = 'callback';
    }
    $partnerAuth = new CRM_HelloassoPaymentProcessor_PartnerAuth($paymentProcessorId ?: NULL);

    try {
      if ($this->isPostRequest()) {
        $this->handlePost($partnerAuth);
      }
      if ($action === 'connect') {
        CRM_Utils_System::redirect($partnerAuth->buildAuthorizationUrl());
      }
      elseif ($action === 'callback') {
        $this->handleCallback($partnerAuth);
      }
    }
    catch (Exception $e) {
      CRM_Core_Session::setStatus($e->getMessage(), E::ts('HelloAsso partner authorization'), 'error');
    }

    $this->renderStatus($partnerAuth);
    parent::run();
  }

  private function handleCallback(CRM_HelloassoPaymentProcessor_PartnerAuth $partnerAuth): void {
    $error = CRM_Utils_Request::retrieve('error', 'String', $this, FALSE);
    if ($error) {
      throw new PaymentProcessorException(E::ts('HelloAsso partner authorization failed: %1', [1 => $error]));
    }

    $code = CRM_Utils_Request::retrieve('code', 'String', $this, TRUE);
    $state = CRM_Utils_Request::retrieve('state', 'String', $this, TRUE);
    $link = $partnerAuth->completeCallback($code, $state);

    CRM_Core_Session::setStatus(
      E::ts('HelloAsso organization %1 is now linked.', [1 => $link['organization_slug']]),
      E::ts('HelloAsso'),
      'success'
    );

    if (!empty($link['payment_processor_id'])) {
      CRM_Utils_System::redirect($this->getPostCallbackReturnUrl((int) $link['payment_processor_id']));
    }
  }

  private function handlePost(CRM_HelloassoPaymentProcessor_PartnerAuth $partnerAuth): void {
    $this->assertValidFormToken();
    $submittedAction = (string) ($_POST['helloasso_partner_form_action'] ?? 'save');

    if ($submittedAction === 'unlink') {
      $partnerAuth->unlink();
      CRM_Core_Session::setStatus(
        E::ts('HelloAsso linked organization has been disconnected.'),
        E::ts('HelloAsso'),
        'success'
      );
      return;
    }

    if ($partnerAuth->isTestProcessor()) {
      Civi::settings()->set('helloasso_partner_client_id_test', trim((string) ($_POST['helloasso_partner_client_id'] ?? '')));
    }
    else {
      Civi::settings()->set('helloasso_partner_client_id_live', trim((string) ($_POST['helloasso_partner_client_id'] ?? '')));
    }
    $submittedSecret = trim((string) ($_POST['helloasso_partner_client_secret'] ?? ''));
    if ($submittedSecret !== '') {
      if ($partnerAuth->isTestProcessor()) {
        Civi::settings()->set('helloasso_partner_client_secret_test', $submittedSecret);
      }
      else {
        Civi::settings()->set('helloasso_partner_client_secret_live', $submittedSecret);
      }
    }
    Civi::settings()->set('helloasso_partner_authorize_url', trim((string) ($_POST['helloasso_partner_authorize_url'] ?? $partnerAuth->getEffectiveAuthorizeUrl())));
    Civi::settings()->set('helloasso_partner_token_url', trim((string) ($_POST['helloasso_partner_token_url'] ?? $partnerAuth->getEffectiveTokenUrl())));

    CRM_Core_Session::setStatus(
      E::ts('HelloAsso partner settings have been saved on this page.'),
      E::ts('HelloAsso'),
      'success'
    );
  }

  private function renderStatus(CRM_HelloassoPaymentProcessor_PartnerAuth $partnerAuth): void {
    $link = $partnerAuth->getLinkedOrganization();
    $paymentProcessorId = (int) CRM_Utils_Request::retrieve('processor_id', 'Positive', $this, FALSE, 0);
    $querySuffix = $paymentProcessorId ? '&processor_id=' . $paymentProcessorId : '';
    $connectUrl = $this->getPartnerPageUrl('reset=1&ha_action=connect' . $querySuffix);
    $formActionUrl = $this->getPartnerPageUrl('reset=1' . $querySuffix);
    $redirectUri = htmlspecialchars($partnerAuth->getRedirectUri(), ENT_QUOTES, 'UTF-8');
    $enabled = (bool) Civi::settings()->get('helloasso_partner_auth_enabled');
    $isTestProcessor = $partnerAuth->isTestProcessor();
    if ($isTestProcessor) {
      $storedClientId = trim((string) Civi::settings()->get('helloasso_partner_client_id_test'));
    }
    else {
      $storedClientId = trim((string) Civi::settings()->get('helloasso_partner_client_id_live'));
    }
    $clientId = htmlspecialchars($storedClientId, ENT_QUOTES, 'UTF-8');
    $authorizeUrl = htmlspecialchars($partnerAuth->getEffectiveAuthorizeUrl(), ENT_QUOTES, 'UTF-8');
    $tokenUrl = htmlspecialchars($partnerAuth->getEffectiveTokenUrl(), ENT_QUOTES, 'UTF-8');
    $formToken = htmlspecialchars($this->getFormToken(), ENT_QUOTES, 'UTF-8');
    $formActionUrlEscaped = htmlspecialchars($formActionUrl, ENT_QUOTES, 'UTF-8');
    $processorAuthConfig = new CRM_HelloassoPaymentProcessor_ProcessorAuthConfig();
    $webhookRegistration = $paymentProcessorId ? $processorAuthConfig->getWebhookRegistration($paymentProcessorId) : NULL;
    $webhookOwnership = $paymentProcessorId ? $processorAuthConfig->getWebhookOwnership($paymentProcessorId) : NULL;
    $screenLabel = $isTestProcessor ? E::ts('HelloAsso sandbox authorization screen') : E::ts('HelloAsso authorization screen');
    $sharedClientIdLabel = $isTestProcessor ? E::ts('Sandbox shared client ID') : E::ts('Shared client ID');
    $sharedClientSecretLabel = $isTestProcessor ? E::ts('Sandbox shared client secret') : E::ts('Shared client secret');
    $connectLabel = $isTestProcessor ? E::ts('Connect sandbox to HelloAsso') : E::ts('Connect to HelloAsso');
    $connectButtonHtml = helloasso_payment_processor_get_authorize_button_html($connectUrl, $connectLabel);
    $linkedAtLabel = helloasso_payment_processor_format_datetime($link['linked_at'] ?? NULL);
    $accessExpiresAtLabel = helloasso_payment_processor_format_datetime($link['expires_at'] ?? NULL);
    $refreshExpiresAtLabel = helloasso_payment_processor_format_datetime($link['refresh_expires_at'] ?? NULL);
    $lastRefreshErrorDateLabel = helloasso_payment_processor_format_datetime($link['last_refresh_error_date'] ?? NULL);
    $settingsUrl = htmlspecialchars(helloasso_payment_processor_get_helloasso_settings_url(), ENT_QUOTES, 'UTF-8');

    $html = '<div class="crm-block crm-form-block">';
    $html .= '<h3>' . $screenLabel . '</h3>';
    if ($paymentProcessorId) {
      $html .= '<p><strong>' . E::ts('Payment processor ID: %1', [1 => $paymentProcessorId]) . '</strong></p>';
    }
    $html .= '<p>' . helloasso_payment_processor_get_partner_required_notice() . '</p>';
    $html .= '<p class="description">' . E::ts("This page is used to enter the shared client, display the link status and start the HelloAsso connection. The general authorization-screen activation switch is configured in the HelloAsso settings.") . '</p>';
    $html .= '<p class="description"><a href="' . $settingsUrl . '">' . E::ts('Open HelloAsso settings') . '</a></p>';
    $html .= '<form method="post" action="' . $formActionUrlEscaped . '">';
    $html .= '<input type="hidden" name="helloasso_partner_form_token" value="' . $formToken . '">';
    $html .= '<input type="hidden" name="helloasso_partner_form_action" value="save">';
    $html .= '<table class="form-layout-compressed">';
    $html .= '<tr><td><label for="helloasso_partner_client_id">' . $sharedClientIdLabel . '</label></td>';
    $html .= '<td><input class="huge" type="text" id="helloasso_partner_client_id" name="helloasso_partner_client_id" value="' . $clientId . '"></td></tr>';
    $html .= '<tr><td><label for="helloasso_partner_client_secret">' . $sharedClientSecretLabel . '</label></td>';
    $html .= '<td><input class="huge" type="password" id="helloasso_partner_client_secret" name="helloasso_partner_client_secret" value="">';
    $html .= '<br><span class="description">' . E::ts('Leave blank to keep the current secret.') . '</span></td></tr>';
    $html .= '<tr><td><label for="helloasso_partner_authorize_url">' . E::ts("Authorization URL") . '</label></td>';
    $html .= '<td><input class="huge" type="text" id="helloasso_partner_authorize_url" name="helloasso_partner_authorize_url" value="' . $authorizeUrl . '"></td></tr>';
    $html .= '<tr><td><label for="helloasso_partner_token_url">' . E::ts('Token URL') . '</label></td>';
    $html .= '<td><input class="huge" type="text" id="helloasso_partner_token_url" name="helloasso_partner_token_url" value="' . $tokenUrl . '"></td></tr>';
    $html .= '</table>';
    $html .= '<p><button class="button crm-form-submit default" type="submit">' . E::ts('Save authorization-screen settings') . '</button></p>';
    $html .= '</form>';
    $html .= '<p><strong>' . E::ts('Callback URL to declare at HelloAsso:') . '</strong><br><code>' . $redirectUri . '</code></p>';
    if ($paymentProcessorId) {
      $html .= '<p class="description">' . E::ts('Webhook management: %1', [1 => htmlspecialchars(helloasso_payment_processor_describe_webhook_ownership($webhookOwnership), ENT_QUOTES, 'UTF-8')]) . '</p>';
      if (!empty($webhookRegistration['url'])) {
        $html .= '<p class="description">' . E::ts('Registered webhook URL: %1', [1 => htmlspecialchars((string) $webhookRegistration['url'], ENT_QUOTES, 'UTF-8')]) . '</p>';
      }
      if (!empty($webhookRegistration['signatureKey'])) {
        $html .= '<p class="description">' . E::ts('Stored webhook signature key: %1', [1 => htmlspecialchars((string) $webhookRegistration['signatureKey'], ENT_QUOTES, 'UTF-8')]) . '</p>';
      }
    }

    if (!$partnerAuth->isEnabled()) {
      $html .= '<p class="status warning">' . E::ts('The authorization screen is disabled globally. Enable it in HelloAsso settings, then return to this page to connect an organization.') . '</p>';
    }
    elseif ($link) {
      $html .= '<p class="status success">' . E::ts('Organization linked: %1', [1 => htmlspecialchars($link['organization_slug'] ?? '', ENT_QUOTES, 'UTF-8')]) . '</p>';
      if (($link['refresh_status'] ?? '') === 'reconnect_required') {
        $httpStatus = !empty($link['last_refresh_http_status']) ? ' HTTP ' . (int) $link['last_refresh_http_status'] : '';
        $failureDate = $lastRefreshErrorDateLabel !== '' ? ' (' . htmlspecialchars($lastRefreshErrorDateLabel, ENT_QUOTES, 'UTF-8') . ')' : '';
        $html .= '<p class="status warning">' . E::ts("The HelloAsso link can no longer be renewed%1%2. Reconnect the organization before accepting new payments through the authorization screen.", [1 => $httpStatus, 2 => $failureDate]) . '</p>';
      }
      elseif (($link['refresh_status'] ?? '') === 'refresh_failed') {
        $html .= '<p class="status warning">' . E::ts("The latest HelloAsso link renewal failed. Check the next maintenance job or reconnect the organization if the problem persists.") . '</p>';
      }
      if ($linkedAtLabel !== '') {
        $html .= '<p>' . E::ts('Linked on: %1', [1 => htmlspecialchars($linkedAtLabel, ENT_QUOTES, 'UTF-8')]) . '</p>';
      }
      if ($accessExpiresAtLabel !== '') {
        $html .= '<p>' . E::ts("Access token valid until: %1", [1 => htmlspecialchars($accessExpiresAtLabel, ENT_QUOTES, 'UTF-8')]) . '</p>';
      }
      if ($refreshExpiresAtLabel !== '') {
        $html .= '<p>' . E::ts("Authorization link valid until: %1", [1 => htmlspecialchars($refreshExpiresAtLabel, ENT_QUOTES, 'UTF-8')]) . '</p>';
      }
      $html .= '<p>' . $connectButtonHtml . '</p>';
      $html .= '<form method="post" action="' . $formActionUrlEscaped . '">';
      $html .= '<input type="hidden" name="helloasso_partner_form_token" value="' . $formToken . '">';
      $html .= '<input type="hidden" name="helloasso_partner_form_action" value="unlink">';
      $html .= '<p><button class="button" type="submit">' . E::ts("Disconnect the linked organization") . '</button></p>';
      $html .= '</form>';
    }
    else {
      $html .= '<p class="status">' . E::ts("No HelloAsso organization is linked yet.") . '</p>';
      $html .= '<p>' . $connectButtonHtml . '</p>';
    }

    $html .= '</div>';

    CRM_Utils_System::setTitle($screenLabel);
    $this->assign('helloassoPartnerAuthHtml', $html);
  }

  private function isPostRequest(): bool {
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
  }

  private function getFormToken(): string {
    $session = CRM_Core_Session::singleton();
    $token = (string) $session->get(self::FORM_TOKEN_SESSION_KEY);
    if ($token === '') {
      $token = bin2hex(random_bytes(24));
      $session->set(self::FORM_TOKEN_SESSION_KEY, $token);
    }

    return $token;
  }

  private function assertValidFormToken(): void {
    $submittedToken = (string) ($_POST['helloasso_partner_form_token'] ?? '');
    if ($submittedToken === '' || !hash_equals($this->getFormToken(), $submittedToken)) {
      throw new PaymentProcessorException(E::ts('The HelloAsso partner settings form token is invalid. Please reload the page and try again.'));
    }
  }

  private function getPartnerPageUrl(string $query): string {
    $query = ltrim($query, '?&');
    $config = CRM_Core_Config::singleton();
    if ($config->userFramework === 'WordPress') {
      return CRM_Utils_System::url('civicrm/helloasso/partner', $query, TRUE, NULL, FALSE, FALSE);
    }

    return CRM_Utils_System::url('civicrm/helloasso/partner', $query, TRUE, NULL, FALSE, TRUE);
  }

  private function getPostCallbackReturnUrl(int $paymentProcessorId): string {
    $config = CRM_Core_Config::singleton();
    if ($config->userFramework === 'WordPress') {
      return $this->getPartnerPageUrl('reset=1&processor_id=' . $paymentProcessorId);
    }

    return $this->getPaymentProcessorEditUrl($paymentProcessorId);
  }

  private function getPaymentProcessorEditUrl(int $paymentProcessorId): string {
    return CRM_Utils_System::url(
      'civicrm/admin/paymentProcessor/edit',
      'action=update&id=' . $paymentProcessorId . '&reset=1',
      TRUE,
      NULL,
      FALSE,
      TRUE
    );
  }

}
