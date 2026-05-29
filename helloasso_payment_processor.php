<?php

require_once 'helloasso_payment_processor.civix.php';

use CRM_HelloassoPaymentProcessor_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function helloasso_payment_processor_civicrm_config(&$config): void {
  _helloasso_payment_processor_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function helloasso_payment_processor_civicrm_install(): void {
  _helloasso_payment_processor_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function helloasso_payment_processor_civicrm_enable(): void {
  _helloasso_payment_processor_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_check().
 */
function helloasso_payment_processor_civicrm_check(&$messages): void {
  $helpText = E::ts("La version 2.0.0 de HelloAsso Payment Processor est en approche ! Cette mise à jour majeure apportera de nombreuses améliorations de robustesse et de nouvelles fonctionnalités :<br/>"
    . "- Intégration optimale avec Afform / Form Builder (Checkout Option)<br/>"
    . "- Traitement asynchrone des webhooks en arrière-plan via une file d'attente dédiée<br/>"
    . "- Validation stricte et sécurisée des signatures des webhooks partenaires<br/>"
    . "- Système de suivi automatisé (relances à T+5 et T+15 minutes pour fiabiliser les statuts)<br/>"
    . "- Suivi à long terme pour la détection et la réconciliation des remboursements ou rejets tardifs.<br/><br/>"
    . "<strong>Prérequis pour la V2 :</strong> Elle nécessitera l'utilisation de <strong>CiviCRM 6.14.0 ou version ultérieure</strong> ainsi que l'installation préalable de l'extension de dépendance <strong>mjwshared</strong>.<br/><br/>"
    . "Si vous avez des questions, vous pouvez nous contacter par e-mail ou rejoindre le canal francophone Mattermost : <a href=\"https://chat.civicrm.org/civicrm/channels/francophone\" target=\"_blank\">https://chat.civicrm.org/civicrm/channels/francophone</a>.");

  $messages[] = new CRM_Utils_Check_Message(
    'helloasso_v2_teasing_announcement',
    $helpText,
    E::ts('HelloAsso : Version 2.0.0 en approche'),
    \Psr\Log\LogLevel::INFO,
    'fa-info-circle'
  );
}

