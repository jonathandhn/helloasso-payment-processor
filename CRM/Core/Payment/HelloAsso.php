<?php

use Civi\Payment\Exception\PaymentProcessorException;
use CRM_HelloassoPaymentProcessor_ExtensionUtil as E;

class CRM_Core_Payment_HelloAsso extends CRM_Core_Payment
{
    private const SHORT_FOLLOWUP_MAX_ATTEMPTS = 3;
    private const LONG_FOLLOWUP_MAX_ATTEMPTS = 4;
    private const TECHNICAL_ERROR_MAX_ATTEMPTS = 5;
    private const TECHNICAL_ERROR_BACKOFF_MINUTES = [5, 15, 30, 60, 120];
    private const LONG_FOLLOWUP_CARD_DAYS = [14, 45, 90];
    private const LONG_FOLLOWUP_SEPA_DAYS = [30, 90, 180];
    private const INSTALLMENT_FOLLOWUP_CARD_DAYS = [1, 7, 30];
    private const INSTALLMENT_FOLLOWUP_SEPA_DAYS = [9, 15, 30];
    private const INSTALLMENT_RECOVERY_DAYS = [1, 7, 15, 30];

    /**
     * @var GuzzleHttp\Client
     */
    protected $guzzleClient;

    /**
     * mode of operation: live or test
     *
     * @var object
     * @static
     */
    protected $_mode = null;

    /**
     * is this the testing processor?
     */
    protected bool $_is_test = false;

    /**
     * Constructor
     *
     * @param string $mode the mode of operation: live or test
     *
     * @return void
     */
    function __construct(string $mode, mixed &$paymentProcessor)
    {
        $this->_mode = $mode;
        $this->_paymentProcessor = $paymentProcessor;
        $this->_is_test = ($this->_mode === 'test');
    }

    /**
     * @return \GuzzleHttp\Client
     */
    public function getGuzzleClient(): \GuzzleHttp\Client
    {
        return $this->guzzleClient ?? new \GuzzleHttp\Client();
    }

    /**
     * @param \GuzzleHttp\Client $guzzleClient
     */
    public function setGuzzleClient(\GuzzleHttp\Client $guzzleClient): void
    {
        $this->guzzleClient = $guzzleClient;
    }

    /**
     * This function checks to see if we have the right config values
     *
     * @return string the error message if any
     * @public
     */
    function checkConfig()
    {
        $error = array();
        $processorAuthConfig = new CRM_HelloassoPaymentProcessor_ProcessorAuthConfig();
        $paymentProcessorId = $this->getPaymentProcessorId();
        $usesPluginPublic = $paymentProcessorId
            && $processorAuthConfig->shouldUsePluginPublic($paymentProcessorId, $this->getPaymentProcessorConfig());

        // CiviCRM stores live and sandbox rails together. Allow an intentionally
        // unused rail to remain blank while saving the processor pair, but keep
        // it invalid everywhere it could actually be offered for payment.
        if (!$usesPluginPublic && $this->isBlankRailDuringProcessorSave()) {
            return NULL;
        }

        if (!$usesPluginPublic && empty($this->_paymentProcessor['user_name'])) {
            $error[] = E::ts('Client Id is not set in the Administer CiviCRM &raquo; System Settings &raquo; Payment Processors.');
        }
        if (!$usesPluginPublic && empty($this->_paymentProcessor['password'])) {
            $error[] = E::ts('Client Secret Id is not set in the Administer CiviCRM &raquo; System Settings &raquo; Payment Processors.');
        }
        if (empty($this->_paymentProcessor['subject']) && !$usesPluginPublic) {
            $error[] = E::ts('HelloAsso Organization Name is not set in the Administer CiviCRM &raquo; System Settings &raquo; Payment Processors.');
        }
        if ($usesPluginPublic && !$processorAuthConfig->getLinkedOrganization($paymentProcessorId)) {
            $error[] = E::ts('HelloAsso authorization screen is selected for this processor, but no linked HelloAsso organization is stored yet.');
        }

        if (!empty($error)) {
            $processorReference = $this->getProcessorReference($paymentProcessorId);
            return E::ts(
                'Invalid HelloAsso processor configuration for %1:',
                [1 => htmlspecialchars($processorReference, ENT_QUOTES, 'UTF-8')]
            ) . '<p>' . implode('<p>', $error);
        } else {
            return NULL;
        }
    }

    protected function supportsCancelRecurring()
    {
        $paymentProcessorId = $this->getPaymentProcessorId();
        return $paymentProcessorId
            && (new CRM_HelloassoPaymentProcessor_ProcessorAuthConfig())->shouldUsePluginPublic(
                $paymentProcessorId,
                $this->getPaymentProcessorConfig()
            );
    }

    protected function supportsCancelRecurringNotifyOptional()
    {
        return FALSE;
    }

    public function doCancelRecurring(\Civi\Payment\PropertyBag $propertyBag)
    {
        if (!$this->supportsCancelRecurring()) {
            throw new PaymentProcessorException(E::ts('HelloAsso installment cancellation is available only for processors connected through the authorization screen.'));
        }
        if (!$propertyBag->has('recurProcessorID')) {
            throw new PaymentProcessorException(E::ts('The HelloAsso order ID is missing from this recurring contribution.'));
        }

        $orderId = filter_var($propertyBag->getRecurProcessorID(), FILTER_VALIDATE_INT);
        if ($orderId === FALSE || $orderId < 1) {
            throw new PaymentProcessorException(E::ts('The HelloAsso order ID stored on this recurring contribution is invalid.'));
        }

        CRM_HelloassoPaymentProcessor_HelloAssoClient::getInstance()->cancelOrder(
            $this->getPaymentProcessorConfig(),
            $this->_is_test,
            $orderId
        );

        $contributionRecurId = $propertyBag->has('contributionRecurID')
            ? (int) $propertyBag->getContributionRecurID()
            : NULL;
        try {
            (new CRM_HelloassoPaymentProcessor_InstallmentCancellation())->synchronize(
                (int) $this->getPaymentProcessorId(),
                $orderId,
                $contributionRecurId ?: NULL
            );
        }
        catch (Throwable $e) {
            Civi::log()->error(sprintf(
                'HelloAsso order %d was cancelled remotely, but local recurring contribution synchronization failed: %s',
                $orderId,
                $e->getMessage()
            ));
            throw new PaymentProcessorException(E::ts(
                'The HelloAsso plan was cancelled, but its local CiviCRM records could not be synchronized: %1',
                [1 => $e->getMessage()]
            ));
        }

        return [
            'message' => E::ts('Future HelloAsso installments were cancelled successfully. Payments already collected were not refunded.'),
        ];
    }

    private function getProcessorReference(?int $paymentProcessorId): string
    {
        $processorTitle = trim((string) ($this->_paymentProcessor['title'] ?? $this->_paymentProcessor['name'] ?? 'HelloAsso'));
        $processorTitle = $processorTitle !== '' ? $processorTitle : 'HelloAsso';
        if (!$paymentProcessorId) {
            return $processorTitle;
        }

        $mode = $this->_is_test ? E::ts('sandbox') : E::ts('production');
        $pairedProcessorId = $this->getPairedProcessorId($paymentProcessorId);
        if ($pairedProcessorId && $this->_is_test) {
            return E::ts('%1 (#%2, sandbox linked to live processor #%3)', [
                1 => $processorTitle,
                2 => $paymentProcessorId,
                3 => $pairedProcessorId,
            ]);
        }

        return E::ts('%1 (#%2, %3)', [
            1 => $processorTitle,
            2 => $paymentProcessorId,
            3 => $mode,
        ]);
    }

    private function getPairedProcessorId(int $paymentProcessorId): ?int
    {
        $processorName = trim((string) ($this->_paymentProcessor['name'] ?? ''));
        if ($processorName === '') {
            return NULL;
        }

        try {
            // Remplacement de l'APIv3 getsingle par l'APIv4 get() pour retrouver 
            // le processeur de manière silencieuse sans générer d'exceptions/logs.
            $pairedProcessors = \Civi\Api4\PaymentProcessor::get(FALSE)
                ->addWhere('name', '=', $processorName)
                ->addWhere('is_test', '=', $this->_is_test ? 0 : 1)
                ->addSelect('id')
                ->setLimit(1)
                ->execute();
                
            if (count($pairedProcessors) > 0) {
                $pairedProcessorId = (int) $pairedProcessors[0]['id'];
                return $pairedProcessorId && $pairedProcessorId !== $paymentProcessorId ? $pairedProcessorId : NULL;
            }
        }
        catch (Exception $e) {
            // Ignoré silencieusement
        }
        return NULL;
    }

    private function isBlankRailDuringProcessorSave(): bool
    {
        if (
            !empty($this->_paymentProcessor['user_name'])
            || !empty($this->_paymentProcessor['password'])
            || !empty($this->_paymentProcessor['subject'])
        ) {
            return FALSE;
        }

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            return FALSE;
        }

        $currentPath = (string) CRM_Utils_System::currentPath();
        return strpos($currentPath, 'civicrm/admin/paymentProcessor') === 0;
    }

    /**
     * Process payment - this function wraps around both doTransferCheckout and doDirectPayment.
     * Any processor that still implements the deprecated doTransferCheckout() or doDirectPayment() should be updated to use doPayment().
     *
     * This function adds some historical defaults ie. the assumption that if a 'doDirectPayment' processors comes back it completed
     *   the transaction & in fact doTransferCheckout would not traditionally come back.
     * Payment processors should throw exceptions and not return Error objects as they may have done with the old functions.
     *
     * Payment processors should set payment_status_id (which is really contribution_status_id) in the returned array. The default is assumed to be Pending.
     *   In some cases the IPN will set the payment to "Completed" some time later.
     *
     * @fixme Creating a contribution record is inconsistent! We should always create a contribution BEFORE calling doPayment...
     *  For the current status see: https://lab.civicrm.org/dev/financial/issues/53
     * If we DO have a contribution ID, then the payment processor can (and should) update parameters on the contribution record as necessary.
     *
     * @param array|\Civi\Payment\PropertyBag $params
     *
     * @param string $component
     *
     * @return array
     *   Result array (containing at least the key payment_status_id)
     *
     * @throws \Civi\Payment\Exception\PaymentProcessorException
     */
    public function doPayment(&$params, $component = 'contribute')
    {
        if (is_array($params)) {
            $params = $this->chooseBestContactDetails($params);
        }

        $propertyBag = \Civi\Payment\PropertyBag::cast($params);
        $this->_component = $component;

        $result = $this->setStatusPaymentPending([]);

        // If we have a $0 amount, skip call to processor and set payment_status to Completed.
        // Conceivably a processor might override this - perhaps for setting up a token - but we don't
        // have an example of that at the moment.
        if ($propertyBag->getAmount() == 0) {
            return $this->setStatusPaymentCompleted($result);
        }

        $backUrl = $params['cancel_url']
            ?? (isset($this->cancelUrl)
                ? $this->getCancelUrl($params['qfKey'] ?? null)
                : $this->getGoBackUrl($params['qfKey'] ?? null));
        $errorUrl = $params['cancel_url'] ?? $this->getCancelUrl($params['qfKey'] ?? null);
        $returnUrl = $params['return_url'] ?? $this->getReturnSuccessUrl($params['qfKey'] ?? null);

        if ($this->isSafeAbortUrlsEnabled() && $this->shouldUseSafeAbortUrl($backUrl, $errorUrl)) {
            $safeAbortUrl = $this->getSafeAbortUrl($params);
            $backUrl = $safeAbortUrl;
            $errorUrl = $safeAbortUrl;
        }

        $response = $this->createCheckoutIntentAndStore(
            $propertyBag,
            [
                'item_name' => $this->getPaymentDescription($params, 250),
                'back_url' => $backUrl,
                'error_url' => $errorUrl,
                'return_url' => $returnUrl,
            ]
        );

        // Check if it's a Drupal AJAX request (Webform)
        if ($this->isDrupalAjaxRequest()) {
            $ajaxResponse = [
                [
                    'command' => 'helloassoRedirect',
                    'url' => $response['redirectUrl']
                ]
            ];
            header('Content-Type: application/json');
            echo json_encode($ajaxResponse);
            CRM_Utils_System::civiExit();
        }

        // then redirect to HelloAsso (standard CiviCRM way)
        CRM_Core_Config::singleton()->userSystem->prePostRedirect();
        CRM_Utils_System::redirect($response['redirectUrl']);

        // exit called before

        return $result;
    }

    public function startHostedCheckoutForContribution(int $contributionId, array $urls = []): string
    {
        $propertyBag = $this->buildPropertyBagFromContribution($contributionId);
        $landingUrl = (string) ($urls['landing_url'] ?? '');
        $backUrl = (string) ($urls['cancel_url'] ?? ($landingUrl !== '' ? $landingUrl : CRM_Utils_System::baseCMSURL()));
        $errorUrl = (string) ($urls['error_url'] ?? $backUrl);
        $returnUrl = (string) ($urls['return_url'] ?? ($landingUrl !== '' ? $landingUrl : $backUrl));

        $response = $this->createCheckoutIntentAndStore(
            $propertyBag,
            [
                'contribution_id' => $contributionId,
                'item_name' => $this->buildContributionCheckoutLabel($contributionId),
                'back_url' => $backUrl,
                'error_url' => $errorUrl,
                'return_url' => $returnUrl,
            ]
        );

        return (string) $response['redirectUrl'];
    }

    public function cancelHostedCheckoutFollowUps(int $contributionId): void
    {
        // The browser explicitly left this checkout; a later payment from an
        // already-open gateway tab must be reconciled by its webhook.
        $this->stopContributionFollowUps($contributionId);
    }

    public function synchronizeContributionForHostedCheckout(int $contributionId): array
    {
        $this->processScheduledSynchronization([
            'contribution_id' => $contributionId,
            'limit' => 1,
        ]);

        $contribution = $this->loadContributionById($contributionId);
        if (!$contribution) {
            throw new PaymentProcessorException(E::ts('Unable to reload contribution %1.', [1 => $contributionId]));
        }

        $metadata = $this->loadMetadataForContribution($contributionId);
        $statusName = CRM_Core_PseudoConstant::getName(
            'CRM_Contribute_BAO_Contribution',
            'contribution_status_id',
            (int) $contribution->contribution_status_id
        );

        $gatewayState = (string) ($metadata->state ?? '');
        $outcome = CRM_HelloassoPaymentProcessor_PaymentState::outcome($gatewayState);
        $checkoutStatus = 'pending';
        if ((string) $statusName === 'Completed' || $outcome === CRM_HelloassoPaymentProcessor_PaymentState::SUCCESS) {
            $checkoutStatus = 'success';
        }
        elseif (
            (string) $statusName === 'Cancelled'
            || in_array($gatewayState, ['Canceled', 'Abandoned'], TRUE)
        ) {
            $checkoutStatus = 'cancel';
        }
        elseif (
            in_array((string) $statusName, ['Failed', 'Refunded', 'Chargeback'], TRUE)
            || in_array($outcome, [
                CRM_HelloassoPaymentProcessor_PaymentState::FAILED,
                CRM_HelloassoPaymentProcessor_PaymentState::REFUNDED,
                CRM_HelloassoPaymentProcessor_PaymentState::CONTESTED,
            ], TRUE)
        ) {
            $checkoutStatus = 'fail';
        }

        return [
            'checkout_status' => $checkoutStatus,
            'contribution_status_name' => (string) $statusName,
            'gateway_state' => $gatewayState,
        ];
    }

    public function supportsRefund(): bool
    {
        if (!(bool) Civi::settings()->get('helloasso_enable_refunds')) {
            return FALSE;
        }

        $paymentProcessorId = $this->getPaymentProcessorId();
        return $paymentProcessorId
            && (new CRM_HelloassoPaymentProcessor_ProcessorAuthConfig())
                ->shouldUsePluginPublic($paymentProcessorId, $this->getPaymentProcessorConfig());
    }

    public function doRefund(&$params): array
    {
        if (!$this->supportsRefund()) {
            throw new PaymentProcessorException(E::ts('HelloAsso refunds are disabled by this extension.'));
        }

        $helloAssoPaymentId = (int) ($params['trxn_id'] ?? 0);
        $refundAmount = (float) ($params['amount'] ?? 0);
        if (!$helloAssoPaymentId || $refundAmount <= 0) {
            throw new PaymentProcessorException(E::ts('HelloAsso refund cannot be requested without a payment ID and a positive amount.'));
        }
        $this->assertFullRefundAmount($helloAssoPaymentId, $refundAmount);

        $refundOperation = CRM_HelloassoPaymentProcessor_HelloAssoClient::getInstance()->refundPayment(
            $this->getPaymentProcessorConfig(),
            $this->_is_test,
            $helloAssoPaymentId
        );

        if (empty($refundOperation['id'])) {
            throw new PaymentProcessorException(E::ts('HelloAsso accepted the refund request but did not return a refund operation ID.'));
        }

        Civi::log()->info(sprintf(
            'HelloAsso refund requested: payment_id=%d refund_operation_id=%s processor_id=%s mode=%s amount=%s',
            $helloAssoPaymentId,
            (string) $refundOperation['id'],
            (string) $this->getPaymentProcessorId(),
            $this->_is_test ? 'test' : 'live',
            (string) $refundAmount
        ));
        CRM_Core_Session::singleton()->set('last_refund', [
            'payment_id' => $helloAssoPaymentId,
            'refund_operation_id' => (string) $refundOperation['id'],
        ], 'helloasso_payment_processor');

        return [
            'refund_trxn_id' => (string) $refundOperation['id'],
            'refund_status' => 'Completed',
            'fee_amount' => 0,
        ];
    }

    private function assertFullRefundAmount(int $helloAssoPaymentId, float $refundAmount): void
    {
        $paymentProcessorId = (int) $this->getPaymentProcessorId();
        $financialTrxn = civicrm_api3('FinancialTrxn', 'get', [
            'sequential' => 1,
            'trxn_id' => (string) $helloAssoPaymentId,
            'payment_processor_id' => $paymentProcessorId,
            'options' => ['limit' => 1],
        ]);

        if (empty($financialTrxn['values'][0]['total_amount'])) {
            throw new PaymentProcessorException(E::ts('HelloAsso refund cannot be requested because the original CiviCRM payment could not be found.'));
        }

        $originalAmount = (float) $financialTrxn['values'][0]['total_amount'];
        if (abs($originalAmount - $refundAmount) > 0.0001) {
            throw new PaymentProcessorException(E::ts('HelloAsso only supports full refunds through this integration. Partial refunds must be handled manually in HelloAsso.'));
        }
    }

    private function createCheckoutIntentAndStore(\Civi\Payment\PropertyBag $propertyBag, array $options): array
    {
        $contributionId = !empty($options['contribution_id']) ? (int) $options['contribution_id'] : (int) $propertyBag->getContributionID();
        if (!$contributionId) {
            $contribution = new CRM_Contribute_BAO_Contribution();
            $contribution->invoice_id = $propertyBag->getInvoiceID();
            if ($contribution->find(TRUE)) {
                $contributionId = (int) $contribution->id;
            }
        }

        if (!$contributionId) {
            throw new PaymentProcessorException(E::ts("Unable to find the contribution to update for the HelloAsso checkout."));
        }

        $payer = $this->buildPayerFromPropertyBag($propertyBag);
        $companyName = CRM_HelloassoPaymentProcessor_PayerCompany::resolveForContribution($contributionId);
        if ($companyName !== '') {
            $payer['companyName'] = $companyName;
        }
        $key = hash('sha256', random_bytes(64));
        $metadata = [
            'invoiceID' => $propertyBag->getInvoiceID(),
            'sig' => hash_hmac('sha256', $propertyBag->getInvoiceID(), $key),
        ];
        if ($propertyBag->getIsRecur() && $propertyBag->has('contributionRecurID')) {
            $metadata['contributionRecurID'] = $propertyBag->getContributionRecurID();
        }

        $request = $this->buildCheckoutAmountFields($propertyBag, $options)
            + CRM_HelloassoPaymentProcessor_SepaOptions::build(
                (bool) Civi::settings()->get('helloasso_enable_sepa')
            )
            + [
            'itemName' => (string) ($options['item_name'] ?? E::ts('Online contribution')),
            'backUrl' => (string) ($options['back_url'] ?? ''),
            'errorUrl' => (string) ($options['error_url'] ?? ''),
            'returnUrl' => (string) ($options['return_url'] ?? ''),
            'containsDonation' => FALSE,
            'payer' => $payer,
            'metadata' => $metadata,
        ];

        $response = CRM_HelloassoPaymentProcessor_HelloAssoClient::getInstance()
            ->createCheckoutIntent($this->getPaymentProcessorConfig(), $this->_is_test, $request);

        if (empty($response['redirectUrl']) || empty($response['id'])) {
            throw new PaymentProcessorException(E::ts("Unknown error while preparing the HelloAsso redirect."));
        }

        $contribution = $this->loadContributionById($contributionId);
        if (!$contribution) {
            throw new PaymentProcessorException(E::ts("Unable to update contribution %1.", [1 => $contributionId]));
        }

        if (empty($contribution->invoice_id)) {
            $contribution->invoice_id = $propertyBag->getInvoiceID();
        }
        $contribution->trxn_id = $response['id'];
        $contribution->save();

        $helloAssoMetadata = $this->loadMetadataForContribution($contribution->id);
        $helloAssoMetadata->contribution_id = $contribution->id;
        $helloAssoMetadata->signing_key = $key;
        $helloAssoMetadata->checkout_intent_id = $response['id'];
        if ($this->hasHelloAssoMetadataColumn('payment_processor_id')) {
            $helloAssoMetadata->payment_processor_id = $this->getPaymentProcessorId();
        }
        $helloAssoMetadata->save();
        $this->armContributionFollowUp($contribution->id, $helloAssoMetadata);
        $this->armLongFollowUp($contribution->id, $helloAssoMetadata);

        return $response;
    }

    private function buildCheckoutAmountFields(\Civi\Payment\PropertyBag $propertyBag, array $options = []): array
    {
        $installmentAmount = (int) round(((float) $propertyBag->getAmount()) * 100);
        if (!$propertyBag->getIsRecur()) {
            return [
                'totalAmount' => $installmentAmount,
                'initialAmount' => $installmentAmount,
            ];
        }

        if (!(bool) Civi::settings()->get('helloasso_enable_installments')) {
            throw new PaymentProcessorException(E::ts('HelloAsso installment payments are disabled.'));
        }

        try {
            if (!empty($options['schedule_total_amount'])) {
                return CRM_HelloassoPaymentProcessor_InstallmentSchedule::buildMonthly(
                    (int) round(((float) $options['schedule_total_amount']) * 100),
                    $propertyBag->has('recurInstallments') ? (int) $propertyBag->getRecurInstallments() : 0,
                    new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get())),
                    min((int) date('j'), 27)
                );
            }

            return CRM_HelloassoPaymentProcessor_InstallmentPlan::buildMonthly(
                $installmentAmount,
                $propertyBag->has('recurInstallments') ? (int) $propertyBag->getRecurInstallments() : 0,
                $propertyBag->has('recurFrequencyInterval') ? (int) $propertyBag->getRecurFrequencyInterval() : 0,
                $propertyBag->has('recurFrequencyUnit') ? (string) $propertyBag->getRecurFrequencyUnit() : '',
                new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get()))
            );
        }
        catch (InvalidArgumentException $e) {
            throw new PaymentProcessorException(E::ts('Invalid HelloAsso installment schedule: %1', [
                1 => $e->getMessage(),
            ]));
        }
    }

    private function buildPayerFromPropertyBag(\Civi\Payment\PropertyBag $propertyBag): array
    {
        $payer = [
            'email' => $propertyBag->getEmail(),
        ];
        if ($propertyBag->has('firstName')) {
            $payer['firstName'] = $propertyBag->getFirstName();
        }
        if ($propertyBag->has('lastName')) {
            $payer['lastName'] = $propertyBag->getLastName();
        }
        if ($propertyBag->has('billingStreetAddress')) {
            $payer['address'] = $propertyBag->getBillingStreetAddress();
        }
        if ($propertyBag->has('billingCity')) {
            $payer['city'] = $propertyBag->getBillingCity();
        }
        if ($propertyBag->has('billingPostalCode')) {
            $payer['zipCode'] = $propertyBag->getBillingPostalCode();
        }
        if (
            $propertyBag->has('billingCountry') &&
            CRM_HelloassoPaymentProcessor_IsoCountryAlpha3::getInstance()->support($propertyBag->getBillingCountry())
        ) {
            $payer['country'] = CRM_HelloassoPaymentProcessor_IsoCountryAlpha3::getInstance()->get($propertyBag->getBillingCountry());
        }

        $this->validateHelloAssoPayerNames(
            $propertyBag->has('firstName') ? (string) $propertyBag->getFirstName() : '',
            $propertyBag->has('lastName') ? (string) $propertyBag->getLastName() : ''
        );

        return $payer;
    }

    private function validateHelloAssoPayerNames(string $firstName, string $lastName): void
    {
        $throwError = function ($msg) {
            CRM_Core_Session::setStatus($msg, E::ts('HelloAsso error'), 'error', [
                'helloasso_checkout_validation' => TRUE,
            ]);
            throw new PaymentProcessorException($msg);
        };

        $forbiddenValues = ['firstname', 'lastname', 'unknown', 'first name', 'user', 'admin', 'name', 'nom', 'prénom', 'test', 'last name', 'anonyme'];
        $validateName = function ($value, $fieldLabel) use ($forbiddenValues, $throwError) {
            if (empty($value)) {
                return;
            }
            $valLower = mb_strtolower(trim($value), 'UTF-8');
            $valNoAccents = @iconv('UTF-8', 'ASCII//TRANSLIT', $value);
            if ($valNoAccents !== false) {
                $valNoAccents = mb_strtolower($valNoAccents);
            }
            else {
                $valNoAccents = $valLower;
            }

            if (mb_strlen($value, 'UTF-8') < 3) {
                $throwError(E::ts('The %1 must contain at least 3 characters (HelloAsso rule).', [1 => $fieldLabel]));
            }
            elseif (preg_match('/(.)\1\1/u', $valLower)) {
                $throwError(E::ts('The %1 must not contain 3 repeated characters (HelloAsso rule).', [1 => $fieldLabel]));
            }
            elseif (preg_match('/[0-9]/', $value)) {
                $throwError(E::ts('The %1 must not contain numbers (HelloAsso rule).', [1 => $fieldLabel]));
            }
            elseif (!preg_match('/[aeiouyAEIOUYéèêëàâäùûüîïôö]/u', $value)) {
                $throwError(E::ts('The %1 must contain at least one vowel (HelloAsso rule).', [1 => $fieldLabel]));
            }
            elseif (in_array($valLower, $forbiddenValues, TRUE) || in_array($valNoAccents, $forbiddenValues, TRUE) || in_array(str_replace('_', ' ', $valLower), $forbiddenValues, TRUE)) {
                $throwError(E::ts('The value of %1 is not allowed by HelloAsso.', [1 => $fieldLabel]));
            }
            elseif (!preg_match('/^[\p{Latin}\s\'\-]+$/u', $value)) {
                $throwError(E::ts('The %1 contains unauthorized characters (HelloAsso rule).', [1 => $fieldLabel]));
            }
        };

        $validateName($firstName, E::ts('first name'));
        $validateName($lastName, E::ts('last name'));
        if ($firstName && $lastName && mb_strtolower(trim($firstName), 'UTF-8') === mb_strtolower(trim($lastName), 'UTF-8')) {
            $throwError(E::ts('First name and last name must not be identical (HelloAsso rule).'));
        }
    }

    private function buildPropertyBagFromContribution(int $contributionId): \Civi\Payment\PropertyBag
    {
        $contribution = \Civi\Api4\Contribution::get(FALSE)
            ->addWhere('id', '=', $contributionId)
            ->addSelect(
                'id',
                'contact_id',
                'total_amount',
                'currency',
                'invoice_id',
                'source',
                'contribution_recur_id',
                'contribution_recur_id.amount',
                'contribution_recur_id.frequency_interval',
                'contribution_recur_id.frequency_unit',
                'contribution_recur_id.installments'
            )
            ->execute()
            ->single();

        $email = '';
        try {
            $email = (string) civicrm_api3('Email', 'getvalue', [
                'contact_id' => $contribution['contact_id'],
                'is_primary' => 1,
                'return' => 'email',
            ]);
        }
        catch (Exception $e) {
        }

        if ($email === '') {
            throw new PaymentProcessorException(E::ts("No primary email address is available to start the HelloAsso checkout for contribution %1.", [1 => $contributionId]));
        }

        $contact = civicrm_api3('Contact', 'getsingle', [
            'id' => $contribution['contact_id'],
            'return' => ['first_name', 'last_name'],
        ]);

        $address = [];
        try {
            $address = civicrm_api3('Address', 'getsingle', [
                'contact_id' => $contribution['contact_id'],
                'is_primary' => 1,
                'return' => ['street_address', 'city', 'postal_code', 'country_id'],
            ]);
        }
        catch (Exception $e) {
        }

        $invoiceId = (string) ($contribution['invoice_id'] ?? '');
        if ($invoiceId === '') {
            $invoiceId = hash('sha256', random_bytes(32));
            \Civi\Api4\Contribution::update(FALSE)
                ->addWhere('id', '=', $contributionId)
                ->addValue('invoice_id', $invoiceId)
                ->execute();
        }

        $params = [
            'amount' => (float) $contribution['total_amount'],
            'currency' => (string) $contribution['currency'],
            'invoiceID' => $invoiceId,
            'contributionID' => $contributionId,
            'contactID' => (int) $contribution['contact_id'],
            'email' => $email,
            'firstName' => (string) ($contact['first_name'] ?? ''),
            'lastName' => (string) ($contact['last_name'] ?? ''),
        ];

        if (!empty($contribution['contribution_recur_id'])) {
            $params['amount'] = (float) $contribution['contribution_recur_id.amount'];
            $params['is_recur'] = TRUE;
            $params['contributionRecurID'] = (int) $contribution['contribution_recur_id'];
            $params['frequency_interval'] = (int) ($contribution['contribution_recur_id.frequency_interval'] ?? 0);
            $params['frequency_unit'] = (string) ($contribution['contribution_recur_id.frequency_unit'] ?? '');
            $params['installments'] = (int) ($contribution['contribution_recur_id.installments'] ?? 0);
        }

        if (!empty($address['street_address'])) {
            $params['billingStreetAddress'] = (string) $address['street_address'];
        }
        if (!empty($address['city'])) {
            $params['billingCity'] = (string) $address['city'];
        }
        if (!empty($address['postal_code'])) {
            $params['billingPostalCode'] = (string) $address['postal_code'];
        }
        if (!empty($address['country_id'])) {
            $params['billingCountry'] = (int) $address['country_id'];
        }

        return \Civi\Payment\PropertyBag::cast($params);
    }

    private function buildContributionCheckoutLabel(int $contributionId): string
    {
        $contribution = \Civi\Api4\Contribution::get(FALSE)
            ->addWhere('id', '=', $contributionId)
            ->addSelect('source', 'invoice_id')
            ->execute()
            ->single();

        $source = trim((string) ($contribution['source'] ?? ''));
        if ($source !== '') {
            return mb_substr($source, 0, 250);
        }

        $invoiceId = trim((string) ($contribution['invoice_id'] ?? ''));
        if ($invoiceId !== '') {
            return mb_substr(E::ts('Online contribution: %1', [1 => $invoiceId]), 0, 250);
        }

        return E::ts('Online contribution');
    }


    /*
     * Address, phone, email parameters provided by profiles have names like:
     *
     * - email-5 (e.g. 5 is the LocationType ID)
     * - email-Primary (Primary email was selected)
     *
     * We try to pick the billing location types if possible, after that we look
     * for Primary, after that we go with any given.
     */
    public function chooseBestContactDetails(array $params): array
    {

        // We remove all addresses, emails from the $params array, and then re-insert the one we want to use.
        $addresses = [];
        $emails = [];
        $phones = [];
        foreach ($params as $inputKey => $value) {
            if (preg_match('/^(phone|email|street_address|supplemental_address_1|supplemental_address_2|city|postal_code|country|state_province)-(\d+|\w+)$/', $inputKey, $matches)) {

                [, $fieldname, $locType] = $matches;
                if ($fieldname === 'email') {
                    $emails[$locType] = $value;
                } elseif ($fieldname === 'phone') {
                    $phones[$locType] = $value;
                } else {
                    // Index addresses by location type, field.
                    $addresses[$locType][$fieldname] = $value;
                }
                unset($params[$inputKey]);
            }
        }

        $selectedAddress = [];
        // First preference is dedicated billing location types
        $billingLocTypeID = CRM_Core_BAO_LocationType::getBilling();
        if ($billingLocTypeID && isset($addresses[$billingLocTypeID])) {
            // Use this one.
            $selectedAddress = $addresses[$billingLocTypeID];
        } elseif (isset($addresses['Primary'])) {
            $selectedAddress = $addresses['Primary'];
        } elseif ($addresses) {
            $selectedAddress = reset($addresses);
        }

        foreach (["street_address" => 'billingStreetAddress', "supplemental_address_1" => 'billingSupplementalAddress1', "supplemental_address_2" => 'billingSupplementalAddress2', "supplemental_address_3" => 'billingSupplementalAddress3', "city" => 'billingCity', "postal_code" => 'billingPostalCode', "country" => 'billingCountry', "state_province" => 'billingStateProvince',] as $arrayKey => $propertyBagProp) {
            if (!empty($selectedAddress[$arrayKey])) {
                $params[$propertyBagProp] = $selectedAddress[$arrayKey];
            }
        }

        if ($billingLocTypeID && !empty($emails[$billingLocTypeID])) {
            $params['email'] = $emails[$billingLocTypeID];
        } elseif (!empty($emails['Primary'])) {
            $params['email'] = $emails['Primary'];
        } elseif ($emails) {
            $params['email'] = reset($emails);
        }

        if ($billingLocTypeID && !empty($phones[$billingLocTypeID])) {
            $params['phone'] = $phones[$billingLocTypeID];
        } elseif (!empty($phones['Primary'])) {
            $params['phone'] = $phones['Primary'];
        } elseif ($phones) {
            $params['phone'] = reset($phones);
        }

        return $params;
    }

    /**
     * Add lightweight frontend handling on top of the existing redirect flow.
     *
     * We keep the PHP redirect/overlay as the source of truth, but use CRM.payment
     * to reduce double-submits and cope better with AJAX reloads/processor switching.
     *
     * @param \CRM_Core_Form $form
     */
    public function buildForm(&$form): void
    {
        if (!$this->isStandardFrontendBridgeEnabled()) {
            return;
        }

        $jsVars = [
            'id' => $form->_paymentProcessor['id'],
            'name' => 'helloasso',
            'v2' => [
                'standardFrontendBridge' => $this->isStandardFrontendBridgeEnabled(),
                'safeAbortUrls' => $this->isSafeAbortUrlsEnabled(),
            ],
        ];

        \Civi::resources()->addVars('helloassoPayment', $jsVars);
        $form->assign('helloassoJSVars', $jsVars);

        CRM_Core_Region::instance('billing-block')->add([
            'scriptUrl' => \Civi::resources()->getUrl(E::LONG_NAME, 'js/civicrmHelloAsso.js'),
            'weight' => 110,
        ]);

        if (in_array(CRM_Core_Config::singleton()->userFramework, ['Drupal', 'Drupal8'])) {
            CRM_Core_Region::instance('billing-block')->add([
                'template' => E::path('templates/CRM/Core/Payment/HelloAsso/JSVars.tpl'),
                'weight' => -1,
            ]);
        }
    }


    public function handlePaymentNotification(): void
    {
        http_response_code(200);
        if (!CRM_HelloassoPaymentProcessor_Webhook::acceptsJsonPayload($_SERVER['REQUEST_METHOD'] ?? NULL)) {
            CRM_HelloassoPaymentProcessor_Logger::debug(
                'HelloAsso non-POST payment endpoint request ignored.',
                [
                    'payment_processor_id' => $this->getPaymentProcessorId(),
                    'request_method' => $_SERVER['REQUEST_METHOD'] ?? NULL,
                ]
            );
            CRM_Utils_System::civiExit();
        }

        $rawData = file_get_contents('php://input');
        $params = json_decode($rawData, true);

        if (!is_array($params)) {
            Civi::log()->warning('HelloAsso webhook ignored: invalid JSON payload.');
            http_response_code(400);
            CRM_Utils_System::civiExit();
        }

        $eventType = $params['eventType'] ?? NULL;
        if ($eventType !== 'Payment' || empty($params['data']) || !is_array($params['data'])) {
            CRM_HelloassoPaymentProcessor_Logger::debug('HelloAsso webhook ignored: unsupported event type or missing data.');
            CRM_Utils_System::civiExit();
        }

        try {
            $partnerSignatureTrusted = $this->validatePartnerWebhookSignature((string) $rawData);

            $invoiceId = $params['metadata']['invoiceID'] ?? NULL;
            $sig = $params['metadata']['sig'] ?? NULL;
            $contribution = $this->findContributionFromWebhookPayload($params);

            if (!$contribution) {
                CRM_HelloassoPaymentProcessor_Logger::debug('HelloAsso webhook not matched to a contribution. Ignored.');
                CRM_Utils_System::civiExit();
            }

            if ($this->isWebhookQueueEnabled()) {
                $this->queueWebhookPayload($params, (string) $rawData);
                CRM_Utils_System::civiExit();
            }

            $legacySignatureTrusted = $invoiceId
                ? $this->validateNotificationSignature($contribution->id, $invoiceId, $sig)
                : FALSE;

            $trustedPayload = $partnerSignatureTrusted || $legacySignatureTrusted;
            $authoritativePayload = $trustedPayload
                ? [$params['data'], $params['data']['order'] ?? []]
                : $this->fetchAuthoritativeWebhookPaymentState($params, $contribution);
            if (!$authoritativePayload) {
                Civi::log()->warning('HelloAsso webhook ignored because it has no verifiable signature and no locally expected HelloAsso object identifier.');
                CRM_Utils_System::civiExit();
            }

            $this->applyHelloAssoPaymentState($contribution, $authoritativePayload[0], $authoritativePayload[1], $eventType);
        }
        catch (PaymentProcessorException $e) {
            Civi::log()->error('HelloAsso webhook validation failed: ' . $e->getMessage());
            http_response_code(400);
        }
        catch (Throwable $e) {
            Civi::log()->error('HelloAsso webhook processing failed: ' . $e->getMessage());
            http_response_code(500);
        }

        CRM_Utils_System::civiExit();
    }

    public function processWebhookEvent(array $webhookEvent): bool
    {
        try {
            $duplicates = \Civi\Api4\PaymentprocessorWebhook::get(FALSE)
                ->selectRowCount()
                ->addWhere('event_id', '=', $webhookEvent['event_id'])
                ->addWhere('id', '<', $webhookEvent['id'])
                ->execute()
                ->count();

            if ($duplicates) {
                \Civi\Api4\PaymentprocessorWebhook::update(FALSE)
                    ->addWhere('id', '=', $webhookEvent['id'])
                    ->addValue('status', 'error')
                    ->addValue('message', E::ts("Duplicate webhook ignored."))
                    ->addValue('processed_date', 'now')
                    ->execute();
                return FALSE;
            }

            $payload = json_decode((string) $webhookEvent['data'], TRUE);
            if (!is_array($payload)) {
                throw new PaymentProcessorException(E::ts('Invalid HelloAsso webhook payload in the queue.'));
            }

            $invoiceId = $payload['metadata']['invoiceID'] ?? NULL;
            $sig = $payload['metadata']['sig'] ?? NULL;
            $contribution = $this->findContributionFromWebhookPayload($payload);

            if (!$contribution) {
                \Civi\Api4\PaymentprocessorWebhook::update(FALSE)
                    ->addWhere('id', '=', $webhookEvent['id'])
                    ->addValue('status', 'success')
                    ->addValue('message', E::ts("No matching contribution. Webhook ignored."))
                    ->addValue('processed_date', 'now')
                    ->execute();
                return TRUE;
            }

            $legacySignatureTrusted = $invoiceId
                ? $this->validateNotificationSignature($contribution->id, $invoiceId, $sig)
                : FALSE;

            $authoritativePayload = $legacySignatureTrusted
                ? [$payload['data'], $payload['data']['order'] ?? []]
                : $this->fetchAuthoritativeWebhookPaymentState($payload, $contribution);
            if (!$authoritativePayload) {
                \Civi\Api4\PaymentprocessorWebhook::update(FALSE)
                    ->addWhere('id', '=', $webhookEvent['id'])
                    ->addValue('status', 'success')
                    ->addValue('message', E::ts('Untrusted webhook ignored; no locally expected HelloAsso object identifier.'))
                    ->addValue('processed_date', 'now')
                    ->execute();
                return TRUE;
            }

            $this->applyHelloAssoPaymentState($contribution, $authoritativePayload[0], $authoritativePayload[1], $payload['eventType'] ?? NULL);

            \Civi\Api4\PaymentprocessorWebhook::update(FALSE)
                ->addWhere('id', '=', $webhookEvent['id'])
                ->addValue('status', 'success')
                ->addValue('message', E::ts('OK'))
                ->addValue('processed_date', 'now')
                ->execute();
            return TRUE;
        }
        catch (Throwable $e) {
            \Civi\Api4\PaymentprocessorWebhook::update(FALSE)
                ->addWhere('id', '=', $webhookEvent['id'])
                ->addValue('status', 'error')
                ->addValue('message', preg_replace('/^(.{250}).*/su', '$1 ...', $e->getMessage()))
                ->addValue('processed_date', 'now')
                ->execute();
            Civi::log()->error('HelloAsso queued webhook processing failed: ' . $e->getMessage());
            return FALSE;
        }
    }

    public function synchronizePendingContributions(int $limit = 30, array $filters = []): array
    {
        $results = [
            'checked' => 0,
            'updated' => 0,
            'errors' => [],
        ];

        $refundedStatusId = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Refunded');
        $where = [
            '(
                c.contribution_status_id <> %1
                OR m.helloasso_payment_id IS NOT NULL
                OR m.checkout_intent_id IS NOT NULL
            )',
        ];
        $params = [
            1 => [$refundedStatusId, 'Integer'],
        ];
        $paramIndex = 2;

        if ($this->hasHelloAssoMetadataColumn('payment_processor_id')) {
            $where[] = "(m.payment_processor_id = %{$paramIndex} OR m.payment_processor_id IS NULL)";
            $params[$paramIndex++] = [$this->getPaymentProcessorId(), 'Integer'];
        }

        if (!empty($filters['contribution_id'])) {
            $where[] = "c.id = %{$paramIndex}";
            $params[$paramIndex++] = [(int) $filters['contribution_id'], 'Integer'];
        }

        if (!empty($filters['status_names']) && is_array($filters['status_names'])) {
            $statusIds = [];
            foreach ($filters['status_names'] as $statusName) {
                $statusId = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', $statusName);
                if ($statusId) {
                    $statusIds[] = (int) $statusId;
                }
            }
            if ($statusIds) {
                $statusPlaceholders = [];
                foreach ($statusIds as $statusId) {
                    $statusPlaceholders[] = "%{$paramIndex}";
                    $params[$paramIndex++] = [$statusId, 'Integer'];
                }
                $where[] = 'c.contribution_status_id IN (' . implode(', ', $statusPlaceholders) . ')';
            }
        }

        if (!empty($filters['receive_date_from'])) {
            $where[] = "c.receive_date >= %{$paramIndex}";
            $params[$paramIndex++] = [$this->normalizeNullableTimestamp($filters['receive_date_from']), 'Timestamp'];
        }

        if (!empty($filters['receive_date_to'])) {
            $where[] = "c.receive_date <= %{$paramIndex}";
            $params[$paramIndex++] = [$this->normalizeNullableTimestamp($filters['receive_date_to']), 'Timestamp'];
        }

        if (!empty($filters['only_scheduled'])) {
            $where[] = 'm.sync_next_date IS NOT NULL';
        }

        if (!empty($filters['due_before'])) {
            $where[] = "m.sync_next_date IS NOT NULL AND m.sync_next_date <= %{$paramIndex}";
            $where[] = 'COALESCE(m.sync_attempt_count, 0) < ' . self::SHORT_FOLLOWUP_MAX_ATTEMPTS;
            $params[$paramIndex++] = [$this->normalizeNullableTimestamp($filters['due_before']), 'Timestamp'];
        }

        $sql = "
            SELECT c.id AS contribution_id,
                   c.trxn_id,
                   c.contribution_status_id,
                   m.checkout_intent_id,
                   m.helloasso_payment_id,
                   m.state
            FROM civicrm_contribution c
            INNER JOIN civicrm_hello_asso_metadata m ON m.contribution_id = c.id
            WHERE " . implode("\n              AND ", $where) . "
            ORDER BY COALESCE(m.sync_next_date, c.receive_date) ASC, c.id DESC
            LIMIT {$limit}
        ";
        $dao = CRM_Core_DAO::executeQuery($sql, $params);

        while ($dao->fetch()) {
            $results['checked']++;

            try {
                $updated = FALSE;
                $syncAttempted = FALSE;
                if (!empty($dao->helloasso_payment_id)) {
                    $syncAttempted = TRUE;
                    $payment = CRM_HelloassoPaymentProcessor_HelloAssoClient::getInstance()->getPayment(
                        $this->getPaymentProcessorConfig(),
                        $this->_is_test,
                        (int) $dao->helloasso_payment_id,
                        ['withFailedRefundOperation' => 'true']
                    );
                    $contribution = $this->loadContributionById((int) $dao->contribution_id);
                    if ($contribution) {
                        $updated = $this->applyHelloAssoPaymentState($contribution, $payment, $payment['order'] ?? [], 'CronSyncPayment') || $updated;
                    }
                }
                elseif (!empty($dao->checkout_intent_id) || ctype_digit((string) $dao->trxn_id)) {
                    $syncAttempted = TRUE;
                    $checkoutIntentId = !empty($dao->checkout_intent_id) ? (int) $dao->checkout_intent_id : (int) $dao->trxn_id;
                    $checkoutIntent = CRM_HelloassoPaymentProcessor_HelloAssoClient::getInstance()->getCheckoutIntent(
                        $this->getPaymentProcessorConfig(),
                        $this->_is_test,
                        $checkoutIntentId,
                        ['withFailedRefundOperation' => 'true']
                    );
                    $contribution = $this->loadContributionById((int) $dao->contribution_id);
                    $hasPayments = $contribution && !empty($checkoutIntent['order']['payments']);
                    if ($hasPayments) {
                        foreach ($checkoutIntent['order']['payments'] as $payment) {
                            $updated = $this->applyHelloAssoPaymentState($contribution, $payment, $checkoutIntent['order'], 'CronSyncCheckoutIntent') || $updated;
                        }
                    }
                    elseif (
                        $contribution
                        && CRM_HelloassoPaymentProcessor_CheckoutAbandonment::isExpired(
                            $this->getContributionFollowUpOriginDate((int) $dao->contribution_id),
                            $this->nowForMetadata(),
                            FALSE
                        )
                    ) {
                        $abandonment = new CRM_HelloassoPaymentProcessor_CheckoutAbandonment();
                        $contributionRecurId = (int) ($contribution->contribution_recur_id ?? 0);
                        $updated = (
                            $contributionRecurId
                                ? $abandonment->expire(
                                    (int) $dao->contribution_id,
                                    (int) $this->getPaymentProcessorId()
                                )
                                : $abandonment->markClassicContribution(
                                    (int) $dao->contribution_id,
                                    (int) $this->getPaymentProcessorId()
                                )
                        ) || $updated;
                    }
                }

                if ($syncAttempted) {
                    $this->advanceContributionFollowUp((int) $dao->contribution_id);
                }

                if ($updated) {
                    $results['updated']++;
                }
            }
            catch (Exception $e) {
                if ($this->isHelloAssoNotFoundException($e)) {
                    $this->stopContributionFollowUps((int) $dao->contribution_id);
                    $results['errors'][] = 'Contribution ' . $dao->contribution_id . ': ' . E::ts("HelloAsso object not found (404). Short and long follow-up checks have been disabled to avoid repeated calls.");
                    CRM_HelloassoPaymentProcessor_Logger::debug(
                        'HelloAsso short cron sync stopped after a 404 response.',
                        [
                            'contribution_id' => (int) $dao->contribution_id,
                            'error' => $e->getMessage(),
                        ]
                    );
                    continue;
                }

                $this->deferTechnicalFollowUpError((int) $dao->contribution_id, 'short', $e);
                $results['errors'][] = 'Contribution ' . $dao->contribution_id . ': ' . $e->getMessage();
                CRM_HelloassoPaymentProcessor_Logger::debug(
                    'HelloAsso short cron sync attempt failed.',
                    [
                        'contribution_id' => (int) $dao->contribution_id,
                        'error' => $e->getMessage(),
                    ]
                );
            }
        }

        if (!empty($filters['allow_recent_scan']) && $results['checked'] < $limit && empty($filters['contribution_id']) && empty($filters['only_scheduled']) && empty($filters['due_before'])) {
            $results = $this->mergeSyncResults($results, $this->synchronizeRecentOrganizationPayments());
        }

        return $results;
    }

    private function synchronizeRecentOrganizationPayments(): array
    {
        $results = [
            'checked' => 0,
            'updated' => 0,
            'errors' => [],
        ];

        $from = (new DateTimeImmutable('-7 days'))->format(DateTimeInterface::ATOM);
        $payments = CRM_HelloassoPaymentProcessor_HelloAssoClient::getInstance()->listOrganizationPayments(
            $this->getPaymentProcessorConfig(),
            $this->_is_test,
            [
                'from' => $from,
                'pageSize' => 100,
                'sortField' => 'UpdateDate',
                'sortOrder' => 'Desc',
            ]
        );

        foreach ($payments['data'] ?? [] as $payment) {
            $results['checked']++;
            $checkoutIntentId = $payment['order']['checkoutIntentId'] ?? NULL;
            if (!$checkoutIntentId) {
                continue;
            }

            $contribution = $this->loadContributionByCheckoutIntentId((int) $checkoutIntentId);
            if (!$contribution) {
                continue;
            }

            try {
                if ($this->applyHelloAssoPaymentState($contribution, $payment, $payment['order'] ?? [], 'CronSyncOrganizationPayments')) {
                    $results['updated']++;
                }
            }
            catch (Exception $e) {
                $results['errors'][] = 'Contribution ' . $contribution->id . ': ' . $e->getMessage();
                CRM_HelloassoPaymentProcessor_Logger::debug(
                    'HelloAsso organization payment sync attempt failed.',
                    [
                        'contribution_id' => (int) $contribution->id,
                        'error' => $e->getMessage(),
                    ]
                );
            }
        }

        return $results;
    }

    public function processScheduledSynchronization(array $options = []): array
    {
        $filters = [
            'contribution_id' => !empty($options['contribution_id']) ? (int) $options['contribution_id'] : NULL,
            'status_names' => !empty($options['status_names']) ? (array) $options['status_names'] : [],
            'receive_date_from' => $options['receive_date_from'] ?? NULL,
            'receive_date_to' => $options['receive_date_to'] ?? NULL,
            'only_scheduled' => !empty($options['only_scheduled']),
            'due_before' => $options['due_before'] ?? NULL,
            'allow_recent_scan' => !empty($options['allow_recent_scan']),
        ];

        if ($filters['only_scheduled'] && empty($filters['due_before'])) {
            $filters['due_before'] = $this->formatMetadataTimestamp($this->nowForMetadata());
        }
        elseif (!empty($filters['due_before']) && strtolower((string) $filters['due_before']) === 'now') {
            $filters['due_before'] = $this->formatMetadataTimestamp($this->nowForMetadata());
        }

        $limit = !empty($options['limit']) ? (int) $options['limit'] : $this->getFollowUpCronLimit();

        return $this->synchronizePendingContributions($limit, $filters);
    }

    public function synchronizeLongPendingContributions(int $limit = 30, array $filters = []): array
    {
        $results = [
            'checked' => 0,
            'updated' => 0,
            'errors' => [],
        ];

        if (!$this->hasHelloAssoMetadataColumn('long_sync_next_date')) {
            return $results;
        }

        $where = ['m.long_sync_next_date IS NOT NULL'];
        $where[] = 'COALESCE(m.long_sync_attempt_count, 0) < ' . self::LONG_FOLLOWUP_MAX_ATTEMPTS;
        $params = [];
        $paramIndex = 1;

        if ($this->hasHelloAssoMetadataColumn('payment_processor_id')) {
            $where[] = "(m.payment_processor_id = %{$paramIndex} OR m.payment_processor_id IS NULL)";
            $params[$paramIndex++] = [$this->getPaymentProcessorId(), 'Integer'];
        }

        if (!empty($filters['contribution_id'])) {
            $where[] = "c.id = %{$paramIndex}";
            $params[$paramIndex++] = [(int) $filters['contribution_id'], 'Integer'];
        }

        if (!empty($filters['status_names']) && is_array($filters['status_names'])) {
            $statusIds = [];
            foreach ($filters['status_names'] as $statusName) {
                $statusId = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', $statusName);
                if ($statusId) {
                    $statusIds[] = (int) $statusId;
                }
            }
            if ($statusIds) {
                $statusPlaceholders = [];
                foreach ($statusIds as $statusId) {
                    $statusPlaceholders[] = "%{$paramIndex}";
                    $params[$paramIndex++] = [$statusId, 'Integer'];
                }
                $where[] = 'c.contribution_status_id IN (' . implode(', ', $statusPlaceholders) . ')';
            }
        }

        if (!empty($filters['receive_date_from'])) {
            $where[] = "c.receive_date >= %{$paramIndex}";
            $params[$paramIndex++] = [$this->normalizeNullableTimestamp($filters['receive_date_from']), 'Timestamp'];
        }

        if (!empty($filters['receive_date_to'])) {
            $where[] = "c.receive_date <= %{$paramIndex}";
            $params[$paramIndex++] = [$this->normalizeNullableTimestamp($filters['receive_date_to']), 'Timestamp'];
        }

        $dueBefore = $filters['due_before'] ?? $this->formatMetadataTimestamp($this->nowForMetadata());
        $where[] = "m.long_sync_next_date <= %{$paramIndex}";
        $params[$paramIndex++] = [$this->normalizeNullableTimestamp($dueBefore), 'Timestamp'];

        $sql = "
            SELECT c.id AS contribution_id,
                   c.trxn_id,
                   m.checkout_intent_id,
                   m.helloasso_payment_id,
                   m.long_sync_scheme
            FROM civicrm_contribution c
            INNER JOIN civicrm_hello_asso_metadata m ON m.contribution_id = c.id
            WHERE " . implode("\n              AND ", $where) . "
            ORDER BY m.long_sync_next_date ASC, c.id ASC
            LIMIT {$limit}
        ";
        $dao = CRM_Core_DAO::executeQuery($sql, $params);

        while ($dao->fetch()) {
            $results['checked']++;

            try {
                $updated = FALSE;
                $syncAttempted = FALSE;
                $followUpScheme = !empty($dao->long_sync_scheme) ? (string) $dao->long_sync_scheme : 'card';

                if (!empty($dao->helloasso_payment_id)) {
                    $syncAttempted = TRUE;
                    $payment = CRM_HelloassoPaymentProcessor_HelloAssoClient::getInstance()->getPayment(
                        $this->getPaymentProcessorConfig(),
                        $this->_is_test,
                        (int) $dao->helloasso_payment_id,
                        ['withFailedRefundOperation' => 'true']
                    );
                    $contribution = $this->loadContributionById((int) $dao->contribution_id);
                    if ($contribution) {
                        $followUpScheme = $this->detectLongFollowUpScheme($payment, NULL, $followUpScheme);
                        $updated = $this->applyHelloAssoPaymentState($contribution, $payment, $payment['order'] ?? [], 'LongCronSyncPayment') || $updated;
                    }
                }
                elseif (!empty($dao->checkout_intent_id) || ctype_digit((string) $dao->trxn_id)) {
                    $syncAttempted = TRUE;
                    $checkoutIntentId = !empty($dao->checkout_intent_id) ? (int) $dao->checkout_intent_id : (int) $dao->trxn_id;
                    $checkoutIntent = CRM_HelloassoPaymentProcessor_HelloAssoClient::getInstance()->getCheckoutIntent(
                        $this->getPaymentProcessorConfig(),
                        $this->_is_test,
                        $checkoutIntentId,
                        ['withFailedRefundOperation' => 'true']
                    );
                    $contribution = $this->loadContributionById((int) $dao->contribution_id);
                    if ($contribution && !empty($checkoutIntent['order']['payments'])) {
                        foreach ($checkoutIntent['order']['payments'] as $payment) {
                            $followUpScheme = $this->detectLongFollowUpScheme($payment, NULL, $followUpScheme);
                            $updated = $this->applyHelloAssoPaymentState($contribution, $payment, $checkoutIntent['order'], 'LongCronSyncCheckoutIntent') || $updated;
                        }
                    }
                }

                if ($syncAttempted) {
                    $this->advanceLongFollowUp((int) $dao->contribution_id, $followUpScheme);
                }

                if ($updated) {
                    $results['updated']++;
                }
            }
            catch (Exception $e) {
                if ($this->isHelloAssoNotFoundException($e)) {
                    $this->stopContributionFollowUps((int) $dao->contribution_id);
                    $results['errors'][] = 'Contribution ' . $dao->contribution_id . ': ' . E::ts("HelloAsso object not found (404). Short and long follow-up checks have been disabled to avoid repeated calls.");
                    CRM_HelloassoPaymentProcessor_Logger::debug(
                        'HelloAsso long cron sync stopped after a 404 response.',
                        [
                            'contribution_id' => (int) $dao->contribution_id,
                            'error' => $e->getMessage(),
                        ]
                    );
                    continue;
                }

                $this->deferTechnicalFollowUpError((int) $dao->contribution_id, 'long', $e);
                $results['errors'][] = 'Contribution ' . $dao->contribution_id . ': ' . $e->getMessage();
                CRM_HelloassoPaymentProcessor_Logger::debug(
                    'HelloAsso long cron sync attempt failed.',
                    [
                        'contribution_id' => (int) $dao->contribution_id,
                        'error' => $e->getMessage(),
                    ]
                );
            }
        }

        return $results;
    }

    public function processLongScheduledSynchronization(array $options = []): array
    {
        $filters = [
            'contribution_id' => !empty($options['contribution_id']) ? (int) $options['contribution_id'] : NULL,
            'status_names' => !empty($options['status_names']) ? (array) $options['status_names'] : [],
            'receive_date_from' => $options['receive_date_from'] ?? NULL,
            'receive_date_to' => $options['receive_date_to'] ?? NULL,
            'due_before' => $options['due_before'] ?? NULL,
        ];

        if (empty($filters['due_before']) || strtolower((string) $filters['due_before']) === 'now') {
            $filters['due_before'] = $this->formatMetadataTimestamp($this->nowForMetadata());
        }

        $limit = !empty($options['limit']) ? (int) $options['limit'] : $this->getFollowUpCronLimit();

        return $this->synchronizeLongPendingContributions($limit, $filters);
    }

    private function applyHelloAssoPaymentState(CRM_Contribute_BAO_Contribution $contribution, array $paymentData, array $orderData = [], ?string $eventType = NULL): bool
    {
        $state = $paymentData['state'] ?? NULL;
        if (!$state) {
            return FALSE;
        }

        $contribution = $this->resolveInstallmentContribution($contribution, $paymentData, $orderData);
        $resolved = FALSE;
        $contributionChanged = FALSE;

        $metadata = $this->loadMetadataForContribution($contribution->id);
        $metadata->contribution_id = $contribution->id;
        if (empty($metadata->signing_key)) {
            $metadata->signing_key = hash('sha256', random_bytes(64));
        }
        if (!empty($orderData['id'])) {
            $metadata->helloasso_ref_cmd_id = $orderData['id'];
        }
        if (!empty($orderData['checkoutIntentId'])) {
            $metadata->checkout_intent_id = $orderData['checkoutIntentId'];
        }
        if (!empty($paymentData['id'])) {
            $metadata->helloasso_payment_id = $paymentData['id'];
        }
        if ($this->hasHelloAssoMetadataColumn('payment_processor_id') && empty($metadata->payment_processor_id)) {
            $metadata->payment_processor_id = $this->getPaymentProcessorId();
        }
        if ($eventType) {
            $metadata->event_type = $eventType;
        }
        $metadata->state = $state;
        $metadata->save();
        $this->ensureLongFollowUpSchedule($contribution->id, $metadata, $paymentData);
        if (CRM_HelloassoPaymentProcessor_InstallmentFollowUp::isFuturePending(
            $paymentData,
            $this->nowForMetadata()
        )) {
            $this->stopContributionFollowUps((int) $contribution->id, TRUE, FALSE);
        }

        if (!empty($paymentData['id']) && $contribution->trxn_id !== (string) $paymentData['id']) {
            $contribution->trxn_id = $paymentData['id'];
            $contribution->save();
            $contributionChanged = TRUE;
        }

        switch (CRM_HelloassoPaymentProcessor_PaymentState::outcome($state)) {
            case CRM_HelloassoPaymentProcessor_PaymentState::SUCCESS:
                $contributionChanged = $this->markContributionCompleted($contribution, $paymentData) || $contributionChanged;
                $this->completeInstallmentRecovery($paymentData, $metadata);
                $resolved = TRUE;
                break;

            case CRM_HelloassoPaymentProcessor_PaymentState::FAILED:
                $contributionChanged = $this->updateContributionStatus($contribution->id, 'Failed') || $contributionChanged;
                $resolved = !$this->armInstallmentRecovery($contribution, $paymentData, $metadata);
                break;

            case CRM_HelloassoPaymentProcessor_PaymentState::REFUNDING:
                // CiviCRM does not allow a direct transition Completed -> Pending refund.
                // We therefore keep the contribution status unchanged and rely on metadata.state
                // until HelloAsso confirms the final Refunded state.
                break;

            case CRM_HelloassoPaymentProcessor_PaymentState::REFUNDED:
                $contributionChanged = $this->markContributionRefunded($contribution, $paymentData) || $contributionChanged;
                $resolved = TRUE;
                break;

            case CRM_HelloassoPaymentProcessor_PaymentState::CONTESTED:
                $contributionChanged = $this->updateContributionStatus($contribution->id, 'Chargeback') || $contributionChanged;
                $resolved = TRUE;
                break;

            case CRM_HelloassoPaymentProcessor_PaymentState::PENDING:
                // SEPA validation and withdrawal can take several business days.
                // Keep the contribution Pending and continue scheduled reconciliation.
                break;
        }

        $contributionRecurId = (int) ($contribution->contribution_recur_id ?? 0);
        if ($contributionRecurId) {
            (new CRM_HelloassoPaymentProcessor_InstallmentLifecycle())
                ->synchronize($contributionRecurId);
        }

        if ($resolved) {
            $this->stopContributionFollowUps(
                (int) $contribution->id,
                TRUE,
                $this->isLongFollowUpTerminalState($state)
            );
        }

        return $contributionChanged;
    }

    private function resolveInstallmentContribution(
        CRM_Contribute_BAO_Contribution $anchorContribution,
        array $paymentData,
        array $orderData
    ): CRM_Contribute_BAO_Contribution {
        $contributionRecurId = (int) ($anchorContribution->contribution_recur_id ?? 0);
        if (!$contributionRecurId) {
            return $anchorContribution;
        }

        $identity = CRM_HelloassoPaymentProcessor_InstallmentIdentity::fromPayment($paymentData, $orderData);
        if (!$identity) {
            throw new PaymentProcessorException(E::ts('A recurring HelloAsso payment is missing its order or installment identity.'));
        }

        $identity['payment_date'] = $this->formatCiviBusinessTimestamp($identity['payment_date']);
        $store = new CRM_HelloassoPaymentProcessor_InstallmentStore();
        if (!$store->tableExists()) {
            throw new PaymentProcessorException(E::ts('The HelloAsso installment mapping table is missing. Apply the extension database upgrades before processing installments.'));
        }

        $transaction = new CRM_Core_Transaction();
        try {
            $claim = $store->claim(
                (int) $this->getPaymentProcessorId(),
                $contributionRecurId,
                $identity
            );

            if ($claim['contribution_id']) {
                $contribution = $this->loadContributionById($claim['contribution_id']);
                if (!$contribution) {
                    throw new PaymentProcessorException(E::ts('The contribution mapped to this HelloAsso installment no longer exists.'));
                }
                $transaction->commit();
                return $contribution;
            }

            if ($identity['installment_number'] === 1) {
                $contribution = $anchorContribution;
            }
            else {
                $contribution = $this->createRepeatedInstallmentContribution(
                    $anchorContribution,
                    $contributionRecurId,
                    $identity
                );
            }

            $store->attachContribution($claim['id'], (int) $contribution->id);
            $this->storeHelloAssoOrderOnContributionRecur($contributionRecurId, $identity['order_id']);
            $transaction->commit();

            return $contribution;
        }
        catch (Throwable $e) {
            $transaction->rollback();
            throw $e;
        }
    }

    private function createRepeatedInstallmentContribution(
        CRM_Contribute_BAO_Contribution $anchorContribution,
        int $contributionRecurId,
        array $identity
    ): CRM_Contribute_BAO_Contribution {
        $amount = $identity['amount'] !== NULL
            ? number_format(((int) $identity['amount']) / 100, 2, '.', '')
            : (string) $anchorContribution->total_amount;

        $result = civicrm_api3('Contribution', 'repeattransaction', [
            'contribution_recur_id' => $contributionRecurId,
            'original_contribution_id' => (int) $anchorContribution->id,
            'contribution_status_id' => 'Pending',
            'payment_processor_id' => $this->getPaymentProcessorId(),
            'trxn_id' => (string) $identity['payment_id'],
            'receive_date' => $identity['payment_date'] ?: $this->formatCiviBusinessTimestamp('now'),
            'total_amount' => $amount,
            'is_email_receipt' => FALSE,
        ]);

        $createdValues = $result['values'] ?? [];
        $created = reset($createdValues);
        $contributionId = (int) ($created['id'] ?? 0);
        $contribution = $contributionId ? $this->loadContributionById($contributionId) : NULL;
        if (!$contribution) {
            throw new PaymentProcessorException(E::ts('CiviCRM could not create the contribution for HelloAsso installment %1.', [
                1 => $identity['installment_number'],
            ]));
        }

        return $contribution;
    }

    private function storeHelloAssoOrderOnContributionRecur(int $contributionRecurId, int $orderId): void
    {
        CRM_Core_DAO::executeQuery(
            'UPDATE civicrm_contribution_recur
             SET processor_id = %1,
                 trxn_id = %1,
                 modified_date = NOW()
             WHERE id = %2',
            [
                1 => [(string) $orderId, 'String'],
                2 => [$contributionRecurId, 'Integer'],
            ]
        );
    }

    private function markContributionCompleted(CRM_Contribute_BAO_Contribution $contribution, array $paymentData): bool
    {
        $completedStatusId = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed');
        if ((int) $contribution->contribution_status_id === (int) $completedStatusId) {
            return FALSE;
        }

        if ($this->isContributionStatusLockedByDonRec($contribution, $completedStatusId)) {
            $this->logDonRecStatusMismatch($contribution->id, (string) ($paymentData['state'] ?? 'Authorized'));
            return FALSE;
        }

        $paymentDate = $this->formatCiviBusinessTimestamp($paymentData['date'] ?? NULL);
        $paymentParams = [
            'trxn_id' => $paymentData['id'],
            'payment_processor_id' => $this->getPaymentProcessorId(),
            'contribution_id' => $contribution->id,
            'total_amount' => $contribution->total_amount,
        ];
        if ($paymentDate) {
            $paymentParams['trxn_date'] = $paymentDate;
        }

        try {
            civicrm_api3('Payment', 'create', $paymentParams);
        }
        catch (Throwable $e) {
            // Some legacy/Afform contributions have no line items or price set.
            // CiviCRM may persist the payment before failing while rebuilding
            // the order. Treat that partial core failure as success only when
            // the expected local payment can be verified.
            if (!$this->findLocalPaymentId(
                (int) $contribution->id,
                (string) $paymentData['id']
            )) {
                throw $e;
            }
            Civi::log()->warning(sprintf(
                'HelloAsso payment %s was recorded for contribution %d, but CiviCRM reported a post-save order error: %s',
                (string) $paymentData['id'],
                (int) $contribution->id,
                $e->getMessage()
            ));
        }

        if ($paymentDate) {
            CRM_Core_DAO::executeQuery(
                'UPDATE civicrm_contribution SET receive_date = %1 WHERE id = %2',
                [
                    1 => [$paymentDate, 'Timestamp'],
                    2 => [(int) $contribution->id, 'Integer'],
                ]
            );
        }

        return TRUE;
    }

    private function markContributionRefunded(CRM_Contribute_BAO_Contribution $contribution, array $paymentData): bool
    {
        $changed = FALSE;
        $refundOperation = $this->extractSuccessfulRefundOperation($paymentData);
        if ($refundOperation && !$this->hasLocalRefundPayment($contribution->id, (string) $refundOperation['id'])) {
            $this->recordHelloAssoRefundPayment($contribution, $paymentData, $refundOperation);
            $changed = TRUE;
        }

        return $this->updateContributionStatusDirectly($contribution->id, 'Refunded') || $changed;
    }

    private function extractSuccessfulRefundOperation(array $paymentData): ?array
    {
        foreach ($paymentData['refundOperations'] ?? [] as $refundOperation) {
            if (empty($refundOperation['id'])) {
                continue;
            }
            $state = (string) ($refundOperation['state'] ?? '');
            if ($state === '' || in_array($state, ['Processed', 'Refunded', 'Succeeded', 'Success', 'Init'], TRUE)) {
                return $refundOperation;
            }
        }

        return NULL;
    }

    private function hasLocalRefundPayment(int $contributionId, string $refundTrxnId = ''): bool
    {
        $where = [
            'eft.entity_table = "civicrm_contribution"',
            'eft.entity_id = %1',
            'ft.total_amount < 0',
        ];
        $params = [1 => [$contributionId, 'Integer']];
        if ($refundTrxnId !== '') {
            $where[] = 'ft.trxn_id = %2';
            $params[2] = [$refundTrxnId, 'String'];
        }

        return (bool) CRM_Core_DAO::singleValueQuery(
            'SELECT ft.id
             FROM civicrm_financial_trxn ft
             INNER JOIN civicrm_entity_financial_trxn eft ON eft.financial_trxn_id = ft.id
             WHERE ' . implode(' AND ', $where) . '
             LIMIT 1',
            $params
        );
    }

    private function recordHelloAssoRefundPayment(CRM_Contribute_BAO_Contribution $contribution, array $paymentData, array $refundOperation): void
    {
        $originalPaymentId = $this->findLocalPaymentId((int) $contribution->id, (string) ($paymentData['id'] ?? ''));
        $refundAmount = !empty($refundOperation['amount'])
            ? ((float) $refundOperation['amount'] / 100)
            : (float) $contribution->total_amount;

        $params = [
            'contribution_id' => (int) $contribution->id,
            'trxn_id' => (string) $refundOperation['id'],
            'total_amount' => 0 - abs($refundAmount),
            'fee_amount' => 0,
            'payment_processor_id' => $this->getPaymentProcessorId(),
        ];

        if ($originalPaymentId) {
            $params['cancelled_payment_id'] = $originalPaymentId;
        }

        if (!empty($refundOperation['creationDate'])) {
            $params['trxn_date'] = $this->formatCiviBusinessTimestamp($refundOperation['creationDate']);
        }

        civicrm_api3('Payment', 'create', $params);
    }

    private function findLocalPaymentId(int $contributionId, string $helloAssoPaymentId): ?int
    {
        if ($helloAssoPaymentId === '') {
            return NULL;
        }

        $payment = civicrm_api3('Payment', 'get', [
            'sequential' => 1,
            'contribution_id' => $contributionId,
            'trxn_id' => $helloAssoPaymentId,
            'options' => ['limit' => 1],
        ]);

        return !empty($payment['values'][0]['id']) ? (int) $payment['values'][0]['id'] : NULL;
    }

    private function updateContributionStatusDirectly(int $contributionId, string $statusName): bool
    {
        $statusId = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', $statusName);
        $contribution = $this->loadContributionById($contributionId);
        if ($contribution && (int) $contribution->contribution_status_id === (int) $statusId) {
            return FALSE;
        }

        if ($contribution && $this->isContributionStatusLockedByDonRec($contribution, (int) $statusId)) {
            $this->logDonRecStatusMismatch($contributionId, $statusName);
            return FALSE;
        }

        CRM_Core_DAO::executeQuery(
            'UPDATE civicrm_contribution SET contribution_status_id = %1, cancel_date = %2 WHERE id = %3',
            [
                1 => [$statusId, 'Integer'],
                2 => [$this->formatCiviBusinessTimestamp('now'), 'Timestamp'],
                3 => [$contributionId, 'Integer'],
            ]
        );

        return TRUE;
    }

    private function updateContributionStatus(int $contributionId, string $statusName): bool
    {
        $statusId = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', $statusName);
        $contribution = $this->loadContributionById($contributionId);
        if ($contribution && (int) $contribution->contribution_status_id === (int) $statusId) {
            return FALSE;
        }

        if ($contribution && $this->isContributionStatusLockedByDonRec($contribution, (int) $statusId)) {
            $this->logDonRecStatusMismatch($contributionId, $statusName);
            return FALSE;
        }

        $update = \Civi\Api4\Contribution::update(FALSE)
            ->addValue('contribution_status_id:name', $statusName)
            ->addWhere('id', '=', $contributionId);

        if (in_array($statusName, ['Failed', 'Pending refund', 'Refunded'], TRUE)) {
            $update->addValue('cancel_date', $this->formatCiviBusinessTimestamp('now'));
        }

        $update->execute();

        return TRUE;
    }

    private function isContributionStatusLockedByDonRec(CRM_Contribute_BAO_Contribution $contribution, int $newStatusId): bool
    {
        if (!class_exists('CRM_Donrec_Logic_Settings')) {
            return FALSE;
        }

        try {
            $errors = CRM_Donrec_Logic_Settings::validateContribution(
                $contribution->id,
                ['contribution_status_id' => (int) $contribution->contribution_status_id],
                ['contribution_status_id' => $newStatusId],
                FALSE
            );

            return !empty($errors['contribution_status_id']);
        }
        catch (Throwable $e) {
            Civi::log()->warning('HelloAsso DonRec lock check failed for contribution ' . $contribution->id . ': ' . $e->getMessage());
            return FALSE;
        }
    }

    private function logDonRecStatusMismatch(int $contributionId, string $gatewayStatus): void
    {
        Civi::log()->warning(E::ts(
            "Contribution %1 is locked by DonRec. The status used for the tax receipt differs from the current HelloAsso payment gateway status (%2). Metadata has been synchronized, but the contribution status has not been changed.",
            [
                1 => $contributionId,
                2 => $gatewayStatus,
            ]
        ));
    }

    private function validateNotificationSignature(int $contributionId, string $invoiceId, ?string $signature): bool
    {
        $strictSignature = $this->isWebhookSignatureRequired();
        if (empty($signature)) {
            if ($strictSignature) {
                throw new PaymentProcessorException(E::ts('HelloAsso notification signature is required but missing.'));
            }
            return FALSE;
        }

        $metadata = $this->loadMetadataForContribution($contributionId);
        if (empty($metadata->signing_key)) {
            if ($strictSignature) {
                throw new PaymentProcessorException(E::ts('HelloAsso notification signature cannot be verified because the local signing key is missing.'));
            }
            return FALSE;
        }

        $expectedSignature = hash_hmac('sha256', $invoiceId, $metadata->signing_key);
        if (!hash_equals($expectedSignature, $signature)) {
            throw new PaymentProcessorException(E::ts('HelloAsso notification signature mismatch.'));
        }

        return TRUE;
    }

    private function validatePartnerWebhookSignature(string $rawData): bool
    {
        $signature = $this->getPartnerWebhookSignatureHeader();
        $signatureKey = $this->getPartnerWebhookSignatureKey();
        $strictSignature = $this->isPartnerWebhookSignatureRequired()
            && $this->isPartnerWebhookSignatureEnforcedForProcessor();

        if ($signature === NULL || $signature === '') {
            if ($strictSignature && $signatureKey) {
                throw new PaymentProcessorException(E::ts('HelloAsso partner webhook signature is required but missing.'));
            }
            return FALSE;
        }

        if (!$signatureKey) {
            if ($strictSignature) {
                throw new PaymentProcessorException(E::ts('HelloAsso partner webhook signature cannot be verified because the local signature key is missing.'));
            }

            CRM_HelloassoPaymentProcessor_Logger::debug(
                'HelloAsso partner webhook signature received without a configured local signature key.',
                ['payment_processor_id' => $this->getPaymentProcessorId()]
            );
            return FALSE;
        }

        $expectedSignature = hash_hmac('sha256', $rawData, $signatureKey);
        if (!hash_equals($expectedSignature, $signature)) {
            throw new PaymentProcessorException(E::ts('HelloAsso partner webhook signature mismatch.'));
        }

        return TRUE;
    }

    private function getPartnerWebhookSignatureHeader(): ?string
    {
        $candidates = [
            $_SERVER['HTTP_X_HA_SIGNATURE'] ?? NULL,
            $_SERVER['REDIRECT_HTTP_X_HA_SIGNATURE'] ?? NULL,
            $_SERVER['X_HA_SIGNATURE'] ?? NULL,
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return NULL;
    }

    private function getPartnerWebhookSignatureKey(): ?string
    {
        $registration = (new CRM_HelloassoPaymentProcessor_ProcessorAuthConfig())
            ->getWebhookRegistration($this->getPaymentProcessorId());

        $signatureKey = trim((string) ($registration['signatureKey'] ?? ''));
        return $signatureKey !== '' ? $signatureKey : NULL;
    }

    private function loadMetadataForContribution(int $contributionId): CRM_HelloassoPaymentProcessor_BAO_HelloAssoMetadata
    {
        $metadata = new CRM_HelloassoPaymentProcessor_BAO_HelloAssoMetadata();
        $metadata->contribution_id = $contributionId;
        $metadata->find(TRUE);
        return $metadata;
    }

    private function loadContributionById(int $contributionId): ?CRM_Contribute_BAO_Contribution
    {
        $contribution = new CRM_Contribute_BAO_Contribution();
        $contribution->id = $contributionId;
        return $contribution->find(TRUE) ? $contribution : NULL;
    }

    private function loadContributionByCheckoutIntentId(int $checkoutIntentId): ?CRM_Contribute_BAO_Contribution
    {
        $sql = "
            SELECT c.id
            FROM civicrm_contribution c
            LEFT JOIN civicrm_hello_asso_metadata m ON m.contribution_id = c.id
            WHERE (m.payment_processor_id = %1 OR m.payment_processor_id IS NULL)
              AND (
                m.checkout_intent_id = %2
                OR c.trxn_id = %3
              )
            ORDER BY c.id DESC
            LIMIT 1
        ";
        $dao = CRM_Core_DAO::executeQuery($sql, [
            1 => [$this->getPaymentProcessorId(), 'Integer'],
            2 => [$checkoutIntentId, 'Integer'],
            3 => [(string) $checkoutIntentId, 'String'],
        ]);

        if ($dao->fetch()) {
            return $this->loadContributionById((int) $dao->id);
        }

        return NULL;
    }

    private function findContributionFromWebhookPayload(array $params): ?CRM_Contribute_BAO_Contribution
    {
        $invoiceId = $params['metadata']['invoiceID'] ?? NULL;
        if ($invoiceId) {
            $contribution = new CRM_Contribute_BAO_Contribution();
            $contribution->invoice_id = $invoiceId;
            if ($contribution->find(TRUE)) {
                return $contribution;
            }
        }

        $paymentId = $params['data']['id'] ?? NULL;
        $identity = CRM_HelloassoPaymentProcessor_InstallmentIdentity::fromPayment(
            (array) ($params['data'] ?? []),
            (array) ($params['data']['order'] ?? [])
        );
        if ($identity) {
            $store = new CRM_HelloassoPaymentProcessor_InstallmentStore();
            if ($store->tableExists()) {
                $contributionId = $store->findContributionId(
                    (int) $this->getPaymentProcessorId(),
                    $identity['payment_id'],
                    $identity['order_id'],
                    $identity['installment_number']
                );
                if ($contributionId) {
                    return $this->loadContributionById($contributionId);
                }
            }
        }

        if ($paymentId) {
            $sql = "
                SELECT contribution_id
                FROM civicrm_hello_asso_metadata
                WHERE helloasso_payment_id = %1
                ORDER BY contribution_id DESC
                LIMIT 1
            ";
            $dao = CRM_Core_DAO::executeQuery($sql, [
                1 => [(int) $paymentId, 'Integer'],
            ]);
            if ($dao->fetch()) {
                return $this->loadContributionById((int) $dao->contribution_id);
            }
        }

        $checkoutIntentId = $params['data']['order']['checkoutIntentId'] ?? NULL;
        if ($checkoutIntentId) {
            return $this->loadContributionByCheckoutIntentId((int) $checkoutIntentId);
        }

        return NULL;
    }

    private function fetchAuthoritativeWebhookPaymentState(array $params, CRM_Contribute_BAO_Contribution $contribution): ?array
    {
        $metadata = $this->loadMetadataForContribution((int) $contribution->id);

        $paymentId = $params['data']['id'] ?? NULL;
        if ($paymentId) {
            if (empty($metadata->helloasso_payment_id) || (string) $metadata->helloasso_payment_id !== (string) $paymentId) {
                return NULL;
            }

            CRM_HelloassoPaymentProcessor_Logger::debug('HelloAsso webhook has no locally verifiable signature. Confirming known payment state with the HelloAsso API before applying it.');
            $payment = CRM_HelloassoPaymentProcessor_HelloAssoClient::getInstance()->getPayment(
                $this->getPaymentProcessorConfig(),
                $this->_is_test,
                (int) $paymentId,
                ['withFailedRefundOperation' => 'true']
            );

            if ((string) ($payment['id'] ?? '') !== (string) $paymentId) {
                throw new PaymentProcessorException(E::ts('HelloAsso webhook payment ID could not be confirmed by the API.'));
            }

            return [$payment, $payment['order'] ?? []];
        }

        $checkoutIntentId = $params['data']['order']['checkoutIntentId'] ?? NULL;
        if ($checkoutIntentId) {
            if (empty($metadata->checkout_intent_id) || (string) $metadata->checkout_intent_id !== (string) $checkoutIntentId) {
                return NULL;
            }

            CRM_HelloassoPaymentProcessor_Logger::debug('HelloAsso webhook has no locally verifiable signature. Confirming known checkout intent state with the HelloAsso API before applying it.');
            $checkoutIntent = CRM_HelloassoPaymentProcessor_HelloAssoClient::getInstance()->getCheckoutIntent(
                $this->getPaymentProcessorConfig(),
                $this->_is_test,
                (int) $checkoutIntentId,
                ['withFailedRefundOperation' => 'true']
            );

            foreach ($checkoutIntent['order']['payments'] ?? [] as $payment) {
                return [$payment, $checkoutIntent['order'] ?? []];
            }

            throw new PaymentProcessorException(E::ts('HelloAsso webhook checkout intent could not be confirmed with a payment by the API.'));
        }

        return NULL;
    }

    private function queueWebhookPayload(array $params, string $rawData = ''): void
    {
        $eventId = $this->buildWebhookEventId($params);
        $identifier = $this->buildWebhookIdentifier($params);

        $existing = \Civi\Api4\PaymentprocessorWebhook::get(FALSE)
            ->addWhere('payment_processor_id', '=', $this->getPaymentProcessorId())
            ->addWhere('event_id', '=', $eventId)
            ->addWhere('processed_date', 'IS NULL')
            ->execute()
            ->count();

        if ($existing) {
            CRM_HelloassoPaymentProcessor_Logger::debug('HelloAsso webhook already queued: ' . $eventId);
            return;
        }

        $storedPayload = $rawData !== ''
            ? $rawData
            : json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($storedPayload)) {
            throw new PaymentProcessorException(E::ts('HelloAsso webhook payload could not be stored in the queue.'));
        }

        \Civi\Api4\PaymentprocessorWebhook::create(FALSE)
            ->addValue('payment_processor_id', $this->getPaymentProcessorId())
            ->addValue('event_id', $eventId)
            ->addValue('trigger', (string) ($params['eventType'] ?? 'payment'))
            ->addValue('identifier', $identifier)
            ->addValue('created_date', 'now')
            ->addValue('data', $storedPayload)
            ->addValue('status', 'new')
            ->execute();
    }

    private function buildWebhookEventId(array $params): string
    {
        $parts = [
            (string) ($params['eventType'] ?? 'payment'),
            (string) ($params['data']['id'] ?? ''),
            (string) ($params['data']['order']['id'] ?? ''),
            (string) ($params['data']['order']['checkoutIntentId'] ?? ''),
            (string) ($params['data']['state'] ?? ''),
        ];

        return implode(':', $parts);
    }

    private function buildWebhookIdentifier(array $params): string
    {
        $parts = array_filter([
            $params['data']['id'] ?? NULL,
            $params['data']['order']['checkoutIntentId'] ?? NULL,
            $params['metadata']['invoiceID'] ?? NULL,
        ], static function ($value) {
            return $value !== NULL && $value !== '';
        });

        if ($parts) {
            return implode(':', $parts);
        }

        return sha1(json_encode($params));
    }

    private function hasHelloAssoMetadataColumn(string $columnName): bool
    {
        static $columns = [];

        if (!array_key_exists($columnName, $columns)) {
            $dao = CRM_Core_DAO::executeQuery(
                "SHOW COLUMNS FROM civicrm_hello_asso_metadata LIKE %1",
                [1 => [$columnName, 'String']]
            );
            $columns[$columnName] = (bool) $dao->fetch();
        }

        return $columns[$columnName];
    }

    private function isFollowUpEnabled(): bool
    {
        return (bool) Civi::settings()->get('helloasso_v2_followup_enabled');
    }

    private function getFollowUpCronLimit(): int
    {
        $limit = (int) (Civi::settings()->get('helloasso_v2_cron_limit') ?? 15);

        return $limit > 0 ? $limit : 15;
    }

    private function getShortFollowUpMinutes(): array
    {
        return [5, 15, CRM_HelloassoPaymentProcessor_CheckoutAbandonment::EXPIRATION_MINUTES];
    }

    private function getContributionFollowUpOriginDate(int $contributionId): ?string
    {
        $origin = CRM_Core_DAO::singleValueQuery(
            'SELECT sync_origin_date
             FROM civicrm_hello_asso_metadata
             WHERE contribution_id = %1
             LIMIT 1',
            [1 => [$contributionId, 'Integer']]
        );

        return $origin ? (string) $origin : NULL;
    }

    private function getLongFollowUpDays(string $scheme): array
    {
        switch ($scheme) {
            case 'sepa':
                return self::LONG_FOLLOWUP_SEPA_DAYS;

            case 'installment-sepa':
                return self::INSTALLMENT_FOLLOWUP_SEPA_DAYS;

            case 'installment-card':
                return self::INSTALLMENT_FOLLOWUP_CARD_DAYS;

            case 'installment-recovery':
                return self::INSTALLMENT_RECOVERY_DAYS;

            default:
                return self::LONG_FOLLOWUP_CARD_DAYS;
        }
    }

    private function detectLongFollowUpScheme(?array $paymentData = NULL, ?CRM_HelloassoPaymentProcessor_BAO_HelloAssoMetadata $metadata = NULL, string $default = 'card'): string
    {
        $isInstallment = (int) ($paymentData['installmentNumber'] ?? 0) > 1
            || strpos($default, 'installment-') === 0;
        $paymentMeans = strtolower((string) ($paymentData['paymentMeans'] ?? ''));
        if (strpos($paymentMeans, 'sepa') !== FALSE) {
            return $isInstallment ? 'installment-sepa' : 'sepa';
        }

        if (
            !empty($metadata->state)
            && in_array((string) $metadata->state, ['WaitingBankValidation', 'WaitingBankWithdraw'], TRUE)
        ) {
            return $isInstallment ? 'installment-sepa' : 'sepa';
        }

        if (in_array($default, ['card', 'sepa', 'installment-card', 'installment-sepa', 'installment-recovery'], TRUE)) {
            return $default;
        }

        return $isInstallment ? 'installment-card' : 'card';
    }

    private function armContributionFollowUp(int $contributionId, ?CRM_HelloassoPaymentProcessor_BAO_HelloAssoMetadata $metadata = NULL): void
    {
        if (!$this->isFollowUpEnabled()) {
            return;
        }

        $metadata = $metadata ?: $this->loadMetadataForContribution($contributionId);
        $origin = $this->nowForMetadata();
        $minutes = $this->getShortFollowUpMinutes();

        $metadata->contribution_id = $contributionId;
        $metadata->sync_origin_date = $this->formatMetadataTimestamp($origin);
        $metadata->sync_next_date = $this->formatMetadataTimestamp($origin->modify('+' . $minutes[0] . ' minutes'));
        $metadata->sync_last_date = 'null';
        $metadata->sync_attempt_count = 0;
        if ($this->hasHelloAssoMetadataColumn('sync_error_count')) {
            $metadata->sync_error_count = 0;
        }
        $metadata->save();
    }

    private function armLongFollowUp(int $contributionId, ?CRM_HelloassoPaymentProcessor_BAO_HelloAssoMetadata $metadata = NULL, ?array $paymentData = NULL): void
    {
        if (!$this->hasHelloAssoMetadataColumn('long_sync_next_date')) {
            return;
        }

        $metadata = $metadata ?: $this->loadMetadataForContribution($contributionId);
        $scheme = $this->detectLongFollowUpScheme($paymentData, $metadata);
        $origin = $this->resolveLongFollowUpOriginDate($paymentData);
        $scheduleDays = $this->getLongFollowUpDays($scheme);

        $metadata->contribution_id = $contributionId;
        $metadata->long_sync_scheme = $scheme;
        $metadata->long_sync_origin_date = $this->formatMetadataTimestamp($origin);
        $metadata->long_sync_next_date = $this->formatMetadataTimestamp($origin->modify('+' . $scheduleDays[0] . ' days'));
        $metadata->long_sync_last_date = 'null';
        $metadata->long_sync_attempt_count = 0;
        if ($this->hasHelloAssoMetadataColumn('long_sync_error_count')) {
            $metadata->long_sync_error_count = 0;
        }
        $metadata->save();
    }

    private function ensureLongFollowUpSchedule(int $contributionId, CRM_HelloassoPaymentProcessor_BAO_HelloAssoMetadata $metadata, ?array $paymentData = NULL): void
    {
        if (!$this->hasHelloAssoMetadataColumn('long_sync_next_date')) {
            return;
        }

        $contribution = $this->loadContributionById($contributionId);
        if (!$contribution) {
            return;
        }

        if ($this->isLongFollowUpResolved($contribution, $metadata)) {
            $this->stopContributionFollowUps($contributionId, FALSE, TRUE);
            return;
        }

        $futurePending = $paymentData
            && CRM_HelloassoPaymentProcessor_InstallmentFollowUp::isFuturePending(
                $paymentData,
                $this->nowForMetadata()
            );
        if (
            $futurePending
            && empty($metadata->long_sync_last_date)
            && (int) ($metadata->long_sync_attempt_count ?? 0) === 0
        ) {
            $defaultScheme = (string) ($metadata->long_sync_scheme ?? 'installment-card');
            if (strpos($defaultScheme, 'installment-') !== 0) {
                $defaultScheme = $defaultScheme === 'sepa' ? 'installment-sepa' : 'installment-card';
            }
            $scheme = $this->detectLongFollowUpScheme(
                $paymentData,
                $metadata,
                $defaultScheme
            );
            $origin = $this->resolveLongFollowUpOriginDate($paymentData);
            $scheduleDays = $this->getLongFollowUpDays($scheme);
            $metadata->long_sync_scheme = $scheme;
            $metadata->long_sync_origin_date = $this->formatMetadataTimestamp($origin);
            $metadata->long_sync_next_date = $this->formatMetadataTimestamp(
                $origin->modify('+' . $scheduleDays[0] . ' days')
            );
            $metadata->save();
            return;
        }

        if (empty($metadata->long_sync_origin_date)) {
            $this->armLongFollowUp($contributionId, $metadata, $paymentData);
            return;
        }

        $scheme = $this->detectLongFollowUpScheme($paymentData, $metadata, (string) ($metadata->long_sync_scheme ?? 'card'));
        if ((string) $metadata->long_sync_scheme !== $scheme) {
            $metadata->long_sync_scheme = $scheme;
            if (empty($metadata->long_sync_last_date) && ((int) ($metadata->long_sync_attempt_count ?? 0) === 0)) {
                $origin = $this->metadataDateTime((string) $metadata->long_sync_origin_date);
                $scheduleDays = $this->getLongFollowUpDays($scheme);
                $metadata->long_sync_next_date = $this->formatMetadataTimestamp($origin->modify('+' . $scheduleDays[0] . ' days'));
            }
            $metadata->save();
        }
    }

    private function advanceContributionFollowUp(int $contributionId): void
    {
        $metadata = $this->loadMetadataForContribution($contributionId);
        if (empty($metadata->contribution_id)) {
            return;
        }

        $contribution = $this->loadContributionById($contributionId);
        if (!$contribution) {
            return;
        }

        $metadata->sync_last_date = $this->formatMetadataTimestamp($this->nowForMetadata());
        $metadata->sync_attempt_count = (int) ($metadata->sync_attempt_count ?? 0) + 1;
        if ($this->hasHelloAssoMetadataColumn('sync_error_count')) {
            $metadata->sync_error_count = 0;
        }

        if (!$this->isFollowUpEnabled() || $this->isContributionFollowUpResolved($contribution, $metadata)) {
            $metadata->save();
            $this->stopContributionFollowUps($contributionId, TRUE, FALSE);
            return;
        }

        $minutes = $this->getShortFollowUpMinutes();
        $attemptIndex = (int) $metadata->sync_attempt_count;
        if (!isset($minutes[$attemptIndex])) {
            $metadata->save();
            $this->stopContributionFollowUps($contributionId, TRUE, FALSE);
            return;
        }
        $origin = !empty($metadata->sync_origin_date) ? $this->metadataDateTime($metadata->sync_origin_date) : $this->nowForMetadata();
        $metadata->sync_next_date = $this->formatMetadataTimestamp($origin->modify('+' . $minutes[$attemptIndex] . ' minutes'));
        $this->logFollowUpMetadataSnapshot('short', $contributionId, $metadata);
        $metadata->save();
    }

    private function advanceLongFollowUp(int $contributionId, ?string $scheme = NULL): void
    {
        $metadata = $this->loadMetadataForContribution($contributionId);
        if (empty($metadata->contribution_id) || !$this->hasHelloAssoMetadataColumn('long_sync_next_date')) {
            return;
        }

        $contribution = $this->loadContributionById($contributionId);
        if (!$contribution) {
            return;
        }

        $metadata->long_sync_last_date = $this->formatMetadataTimestamp($this->nowForMetadata());
        $metadata->long_sync_attempt_count = (int) ($metadata->long_sync_attempt_count ?? 0) + 1;
        if ($this->hasHelloAssoMetadataColumn('long_sync_error_count')) {
            $metadata->long_sync_error_count = 0;
        }

        if ($this->isLongFollowUpResolved($contribution, $metadata)) {
            $metadata->save();
            $this->stopContributionFollowUps($contributionId, FALSE, TRUE);
            return;
        }

        $scheme = $this->detectLongFollowUpScheme(
            NULL,
            $metadata,
            (string) ($metadata->long_sync_scheme ?? '') ?: ($scheme ?: 'card')
        );
        $metadata->long_sync_scheme = $scheme;
        $origin = !empty($metadata->long_sync_origin_date) ? $this->metadataDateTime((string) $metadata->long_sync_origin_date) : $this->nowForMetadata();
        $nextDate = $this->computeNextLongFollowUpDate(
            $origin,
            $scheme,
            (int) $metadata->long_sync_attempt_count,
            $this->nowForMetadata()
        );
        if (!$nextDate) {
            $metadata->save();
            if ($scheme === 'installment-recovery') {
                $this->expireInstallmentRecovery($contribution);
            }
            $this->stopContributionFollowUps($contributionId, FALSE, TRUE);
            return;
        }

        $metadata->long_sync_next_date = $this->formatMetadataTimestamp($nextDate);
        $this->logFollowUpMetadataSnapshot('long', $contributionId, $metadata);
        $metadata->save();
    }

    private function expireInstallmentRecovery(CRM_Contribute_BAO_Contribution $contribution): void
    {
        $contributionRecurId = (int) ($contribution->contribution_recur_id ?? 0);
        if (!$contributionRecurId) {
            return;
        }

        CRM_Core_DAO::executeQuery(
            "UPDATE civicrm_hello_asso_installment
             SET state = 'RecoveryExpired',
                 updated_at = NOW()
             WHERE contribution_id = %1
               AND state = 'Refused'",
            [1 => [(int) $contribution->id, 'Integer']]
        );
        $metadata = $this->loadMetadataForContribution((int) $contribution->id);
        $metadata->state = 'RecoveryExpired';
        $metadata->save();
        (new CRM_HelloassoPaymentProcessor_InstallmentLifecycle())
            ->synchronize($contributionRecurId);
    }

    private function logFollowUpMetadataSnapshot(string $rail, int $contributionId, CRM_HelloassoPaymentProcessor_BAO_HelloAssoMetadata $metadata): void
    {
        CRM_HelloassoPaymentProcessor_Logger::debug('HelloAsso follow-up metadata before save', [
            'rail' => $rail,
            'contribution_id' => $contributionId,
            'metadata_id' => $metadata->id ?? NULL,
            'sync_origin_date' => $metadata->sync_origin_date ?? NULL,
            'sync_origin_date_type' => gettype($metadata->sync_origin_date ?? NULL),
            'sync_last_date' => $metadata->sync_last_date ?? NULL,
            'sync_last_date_type' => gettype($metadata->sync_last_date ?? NULL),
            'sync_next_date' => $metadata->sync_next_date ?? NULL,
            'sync_next_date_type' => gettype($metadata->sync_next_date ?? NULL),
            'long_sync_origin_date' => $metadata->long_sync_origin_date ?? NULL,
            'long_sync_origin_date_type' => gettype($metadata->long_sync_origin_date ?? NULL),
            'long_sync_last_date' => $metadata->long_sync_last_date ?? NULL,
            'long_sync_last_date_type' => gettype($metadata->long_sync_last_date ?? NULL),
            'long_sync_next_date' => $metadata->long_sync_next_date ?? NULL,
            'long_sync_next_date_type' => gettype($metadata->long_sync_next_date ?? NULL),
            'sync_attempt_count' => $metadata->sync_attempt_count ?? NULL,
            'long_sync_attempt_count' => $metadata->long_sync_attempt_count ?? NULL,
            'state' => $metadata->state ?? NULL,
        ]);
    }

    private function isContributionFollowUpResolved(CRM_Contribute_BAO_Contribution $contribution, CRM_HelloassoPaymentProcessor_BAO_HelloAssoMetadata $metadata): bool
    {
        $statusName = CRM_Core_PseudoConstant::getName(
            'CRM_Contribute_BAO_Contribution',
            'contribution_status_id',
            (int) $contribution->contribution_status_id
        );
        $resolvedStatuses = ['Completed', 'Failed', 'Refunded', 'Chargeback'];
        if (in_array((string) $statusName, $resolvedStatuses, TRUE)) {
            return TRUE;
        }

        if (
            !empty($metadata->state)
            && CRM_HelloassoPaymentProcessor_PaymentState::isShortFollowUpTerminal((string) $metadata->state)
        ) {
            return TRUE;
        }

        return FALSE;
    }

    private function isLongFollowUpResolved(CRM_Contribute_BAO_Contribution $contribution, CRM_HelloassoPaymentProcessor_BAO_HelloAssoMetadata $metadata): bool
    {
        if (
            (string) ($metadata->state ?? '') === 'Refused'
            && (string) ($metadata->long_sync_scheme ?? '') === 'installment-recovery'
        ) {
            return FALSE;
        }

        $statusName = CRM_Core_PseudoConstant::getName(
            'CRM_Contribute_BAO_Contribution',
            'contribution_status_id',
            (int) $contribution->contribution_status_id
        );
        $resolvedStatuses = ['Failed', 'Refunded', 'Chargeback'];
        if (in_array((string) $statusName, $resolvedStatuses, TRUE)) {
            return TRUE;
        }

        if (
            !empty($metadata->state)
            && CRM_HelloassoPaymentProcessor_PaymentState::isLongFollowUpTerminal((string) $metadata->state)
        ) {
            return TRUE;
        }

        return FALSE;
    }

    private function isLongFollowUpTerminalState(string $state): bool
    {
        if ($state === 'Refused') {
            return FALSE;
        }
        return CRM_HelloassoPaymentProcessor_PaymentState::isLongFollowUpTerminal($state);
    }

    private function armInstallmentRecovery(
        CRM_Contribute_BAO_Contribution $contribution,
        array $paymentData,
        CRM_HelloassoPaymentProcessor_BAO_HelloAssoMetadata $metadata
    ): bool {
        if (
            (string) ($paymentData['state'] ?? '') !== 'Refused'
            || (int) ($paymentData['installmentNumber'] ?? 0) < 2
        ) {
            return FALSE;
        }
        if (
            (string) ($metadata->long_sync_scheme ?? '') === 'installment-recovery'
            && !empty($metadata->long_sync_origin_date)
        ) {
            return TRUE;
        }

        $origin = CRM_HelloassoPaymentProcessor_InstallmentFollowUp::originDate(
            [
                'date' => $paymentData['meta']['updatedAt']
                    ?? $paymentData['date']
                    ?? NULL,
            ],
            $this->nowForMetadata()
        );
        $metadata->long_sync_scheme = 'installment-recovery';
        $metadata->long_sync_origin_date = $this->formatMetadataTimestamp($origin);
        $metadata->long_sync_next_date = $this->formatMetadataTimestamp(
            $origin->modify('+' . self::INSTALLMENT_RECOVERY_DAYS[0] . ' days')
        );
        $metadata->long_sync_last_date = 'null';
        $metadata->long_sync_attempt_count = 0;
        if ($this->hasHelloAssoMetadataColumn('long_sync_error_count')) {
            $metadata->long_sync_error_count = 0;
        }
        $metadata->save();

        return TRUE;
    }

    private function completeInstallmentRecovery(
        array $paymentData,
        CRM_HelloassoPaymentProcessor_BAO_HelloAssoMetadata $metadata
    ): void {
        if ((string) ($metadata->long_sync_scheme ?? '') !== 'installment-recovery') {
            return;
        }

        $scheme = $this->detectLongFollowUpScheme(
            $paymentData,
            $metadata,
            'installment-card'
        );
        if ($scheme === 'installment-recovery') {
            $scheme = 'installment-card';
        }
        $origin = $this->resolveLongFollowUpOriginDate($paymentData);
        $days = $this->getLongFollowUpDays($scheme);
        $metadata->long_sync_scheme = $scheme;
        $metadata->long_sync_origin_date = $this->formatMetadataTimestamp($origin);
        $metadata->long_sync_next_date = $this->formatMetadataTimestamp(
            $origin->modify('+' . $days[0] . ' days')
        );
        $metadata->long_sync_last_date = 'null';
        $metadata->long_sync_attempt_count = 0;
        $metadata->save();
    }

    private function computeNextLongFollowUpDate(DateTimeImmutable $origin, string $scheme, int $attemptCount, DateTimeImmutable $reference): ?DateTimeImmutable
    {
        $scheduleDays = $this->getLongFollowUpDays($scheme);
        for ($index = max(0, $attemptCount); $index < count($scheduleDays); $index++) {
            $candidate = $origin->modify('+' . $scheduleDays[$index] . ' days');
            if ($candidate > $reference) {
                return $candidate;
            }
        }

        return NULL;
    }

    private function isHelloAssoNotFoundException(Exception $e): bool
    {
        return strpos($e->getMessage(), 'Erreur API HelloAsso (404)') !== FALSE;
    }

    private function stopContributionFollowUps(int $contributionId, bool $stopShort = TRUE, bool $stopLong = TRUE): void
    {
        $updates = [];
        if ($stopShort) {
            $updates[] = 'sync_next_date = NULL';
        }
        if ($stopLong && $this->hasHelloAssoMetadataColumn('long_sync_next_date')) {
            $updates[] = 'long_sync_next_date = NULL';
        }
        if (!$updates) {
            return;
        }

        CRM_Core_DAO::executeQuery(
            'UPDATE civicrm_hello_asso_metadata SET ' . implode(', ', $updates) . ' WHERE contribution_id = %1',
            [1 => [$contributionId, 'Integer']]
        );
    }

    private function deferTechnicalFollowUpError(int $contributionId, string $rail, Exception $exception): void
    {
        $isLong = $rail === 'long';
        $dateColumn = $isLong ? 'long_sync_next_date' : 'sync_next_date';
        $lastDateColumn = $isLong ? 'long_sync_last_date' : 'sync_last_date';
        $errorColumn = $isLong ? 'long_sync_error_count' : 'sync_error_count';
        $stopShort = !$isLong;
        $stopLong = $isLong;

        if (!$this->hasHelloAssoMetadataColumn($errorColumn)) {
            $retryDate = $this->formatMetadataTimestamp($this->nowForMetadata()->modify('+15 minutes'), 'YmdHis');
            CRM_Core_DAO::executeQuery(
                "UPDATE civicrm_hello_asso_metadata SET {$dateColumn} = %1, {$lastDateColumn} = %2 WHERE contribution_id = %3",
                [
                    1 => [$retryDate, 'Timestamp'],
                    2 => [$this->formatMetadataTimestamp($this->nowForMetadata(), 'YmdHis'), 'Timestamp'],
                    3 => [$contributionId, 'Integer'],
                ]
            );
            return;
        }

        CRM_Core_DAO::executeQuery(
            "UPDATE civicrm_hello_asso_metadata SET {$errorColumn} = COALESCE({$errorColumn}, 0) + 1, {$lastDateColumn} = %1 WHERE contribution_id = %2",
            [
                1 => [$this->formatMetadataTimestamp($this->nowForMetadata(), 'YmdHis'), 'Timestamp'],
                2 => [$contributionId, 'Integer'],
            ]
        );
        $errorCount = (int) CRM_Core_DAO::singleValueQuery(
            "SELECT {$errorColumn} FROM civicrm_hello_asso_metadata WHERE contribution_id = %1",
            [1 => [$contributionId, 'Integer']]
        );

        if ($errorCount >= self::TECHNICAL_ERROR_MAX_ATTEMPTS) {
            $this->stopContributionFollowUps($contributionId, $stopShort, $stopLong);
            Civi::log()->warning(sprintf(
                'HelloAsso %s follow-up disabled for contribution %d after %d technical errors. Last error: %s',
                $rail,
                $contributionId,
                $errorCount,
                $exception->getMessage()
            ));
            return;
        }

        $backoffIndex = min($errorCount - 1, count(self::TECHNICAL_ERROR_BACKOFF_MINUTES) - 1);
        $retryDate = $this->formatMetadataTimestamp(
            $this->nowForMetadata()->modify('+' . self::TECHNICAL_ERROR_BACKOFF_MINUTES[$backoffIndex] . ' minutes'),
            'YmdHis'
        );
        CRM_Core_DAO::executeQuery(
            "UPDATE civicrm_hello_asso_metadata SET {$dateColumn} = %1 WHERE contribution_id = %2",
            [
                1 => [$retryDate, 'Timestamp'],
                2 => [$contributionId, 'Integer'],
            ]
        );
    }

    private function resolveLongFollowUpOriginDate(?array $paymentData = NULL): DateTimeImmutable
    {
        return CRM_HelloassoPaymentProcessor_InstallmentFollowUp::originDate(
            $paymentData ?? [],
            $this->nowForMetadata()
        );
    }

    private function nowForMetadata(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    private function metadataDateTime(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }

    private function formatMetadataTimestamp(DateTimeInterface $dateTime, string $format = 'Y-m-d H:i:s'): string
    {
        return DateTimeImmutable::createFromInterface($dateTime)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format($format);
    }

    private function getPaymentProcessorConfig(): array
    {
        return (array) $this->_paymentProcessor;
    }

    private function getPaymentProcessorId(): ?int
    {
        if (is_array($this->_paymentProcessor)) {
            return isset($this->_paymentProcessor['id']) ? (int) $this->_paymentProcessor['id'] : NULL;
        }

        return isset($this->_paymentProcessor->id) ? (int) $this->_paymentProcessor->id : NULL;
    }

    private function mergeSyncResults(array $left, array $right): array
    {
        return [
            'checked' => $left['checked'] + $right['checked'],
            'updated' => $left['updated'] + $right['updated'],
            'errors' => array_merge($left['errors'], $right['errors']),
        ];
    }

    private function shouldUseSafeAbortUrl(?string $backUrl, ?string $errorUrl): bool
    {
        if ($this->isDrupalAjaxRequest()) {
            return TRUE;
        }

        if ($this->isUnsafeAbortUrl($backUrl) || $this->isUnsafeAbortUrl($errorUrl)) {
            return TRUE;
        }

        return FALSE;
    }

    private function getSafeAbortUrl(array $params): string
    {
        $candidates = [
            $_SERVER['HTTP_REFERER'] ?? NULL,
            $params['source_url'] ?? NULL,
            $params['cancel_url'] ?? NULL,
        ];

        foreach ($candidates as $candidate) {
            if (!$this->isUnsafeAbortUrl($candidate)) {
                return (string) $this->sanitizeAbortUrl($candidate);
            }
        }

        return CRM_Utils_System::baseCMSURL();
    }

    private function isUnsafeAbortUrl(?string $url): bool
    {
        if (empty($url)) {
            return TRUE;
        }

        $url = $this->sanitizeAbortUrl($url);

        $unsafePatterns = [
            'ajax',
            '_wrapper_format=drupal_ajax',
            'civicrm/contact/view/contribution',
            'civicrm/contact/view/participant',
            'civicrm/contact/view/membership',
            'civicrm/contribute/transact',
            'civicrm/payment',
            'wp-admin/admin-ajax.php',
            'wc-ajax=',
            'wp-json/',
            'rest_route=',
            'option=com_ajax',
            'format=raw',
            'tmpl=component',
            'task=ajax',
        ];

        foreach ($unsafePatterns as $pattern) {
            if (stripos($url, $pattern) !== FALSE) {
                return TRUE;
            }
        }

        return FALSE;
    }

    private function sanitizeAbortUrl(?string $url): ?string
    {
        if (empty($url)) {
            return $url;
        }

        $parts = parse_url($url);
        if ($parts === FALSE) {
            return $url;
        }

        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            foreach ([
                '_wrapper_format',
                '_drupal_ajax',
                'ajax_form',
                'snippet',
                'format',
                'tmpl',
                'task',
                'rest_route',
            ] as $key) {
                unset($query[$key]);
            }
        }

        $rebuilt = '';
        if (!empty($parts['scheme'])) {
            $rebuilt .= $parts['scheme'] . '://';
        }
        if (!empty($parts['user'])) {
            $rebuilt .= $parts['user'];
            if (!empty($parts['pass'])) {
                $rebuilt .= ':' . $parts['pass'];
            }
            $rebuilt .= '@';
        }
        if (!empty($parts['host'])) {
            $rebuilt .= $parts['host'];
        }
        if (!empty($parts['port'])) {
            $rebuilt .= ':' . $parts['port'];
        }
        $rebuilt .= $parts['path'] ?? '';
        if ($query) {
            $rebuilt .= '?' . http_build_query($query);
        }
        if (!empty($parts['fragment'])) {
            $rebuilt .= '#' . $parts['fragment'];
        }

        return $rebuilt ?: $url;
    }

    private function isDrupalAjaxRequest(): bool
    {
        return !empty($_REQUEST['ajax_form']) || ((string) ($_REQUEST['_wrapper_format'] ?? '') === 'drupal_ajax');
    }

    private function isStandardFrontendBridgeEnabled(): bool
    {
        return (bool) Civi::settings()->get('helloasso_v2_standard_frontend_bridge');
    }

    private function isSafeAbortUrlsEnabled(): bool
    {
        return (bool) Civi::settings()->get('helloasso_v2_safe_abort_urls');
    }

    private function isWebhookQueueEnabled(): bool
    {
        return (bool) Civi::settings()->get('helloasso_v2_queue_webhooks');
    }

    private function isWebhookSignatureRequired(): bool
    {
        return (bool) Civi::settings()->get('helloasso_v2_require_webhook_signature');
    }

    private function isPartnerWebhookSignatureRequired(): bool
    {
        return (bool) Civi::settings()->get('helloasso_v2_require_partner_webhook_signature');
    }

    private function isPartnerWebhookSignatureEnforcedForProcessor(): bool
    {
        $paymentProcessorId = $this->getPaymentProcessorId();
        return $paymentProcessorId
            && (new CRM_HelloassoPaymentProcessor_ProcessorAuthConfig())
                ->shouldUsePluginPublic($paymentProcessorId, $this->getPaymentProcessorConfig());
    }

    private function normalizeNullableTimestamp($value): ?string
    {
        if ($value === NULL || $value === '') {
            return NULL;
        }

        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            $digits = (string) $value;
            if (strlen($digits) === 14 || strlen($digits) === 8) {
                return $digits;
            }
            if (strlen($digits) === 10) {
                return gmdate('YmdHis', (int) $digits);
            }
        }

        try {
            return (new DateTimeImmutable((string) $value))->format('YmdHis');
        }
        catch (Exception $e) {
            return (string) $value;
        }
    }

    private function formatCiviBusinessTimestamp($value): ?string
    {
        if ($value === NULL || $value === '') {
            return NULL;
        }

        try {
            $dateTime = is_int($value) || (is_string($value) && ctype_digit($value) && strlen((string) $value) === 10)
                ? (new DateTimeImmutable('@' . (int) $value))
                : new DateTimeImmutable((string) $value);

            return $dateTime
                ->setTimezone(new DateTimeZone(date_default_timezone_get()))
                ->format('YmdHis');
        }
        catch (Exception $e) {
            return (string) $value;
        }
    }

    public function getWebhookPath(): string
    {
        return CRM_HelloassoPaymentProcessor_Webhook::getWebhookPath($this->getPaymentProcessorId());
    }
}
