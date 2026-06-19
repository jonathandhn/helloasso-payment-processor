<?php

/**
 * @file
 * Bootstrap file for PHPStan: declares CiviCRM global API helper functions.
 *
 * These functions are defined at CMS runtime (api/v3/utils.php) and are never
 * autoloaded. We declare minimal signatures here so PHPStan can resolve them.
 * This file is referenced via bootstrapFiles in phpstan.neon.
 */

if (!function_exists('civicrm_api3_create_success')) {
  /**
   * @param mixed $values
   * @param array<string, mixed> $params
   * @param string $entity
   * @param string|null $action  The API action name (e.g. 'create', 'get'). NOT a DAO object.
   * @param object|null $dao
   * @param array<string, mixed> $extraReturnValues
   * @return array<string, mixed>
   */
  function civicrm_api3_create_success(
    mixed $values = 1,
    array $params = [],
    string $entity = '',
    string|null $action = null,
    ?object $dao = null,
    array $extraReturnValues = [],
  ): array {
    return [];
  }
}

if (!function_exists('civicrm_api3_create_error')) {
  /**
   * @param string $msg
   * @param array<string, mixed> $data
   * @return array<string, mixed>
   */
  function civicrm_api3_create_error(string $msg, array $data = []): array {
    return [];
  }
}
