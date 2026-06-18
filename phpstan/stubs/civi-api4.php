<?php

namespace Civi\Api4;

/**
 * Fluent query builder returned by Api4 entity factory methods.
 *
 * CiviCRM's Api4 entities (Contribution, ContributionRecur, etc.) are generated
 * at runtime and not available as static PHP files. This stub provides the
 * minimum interface needed for static analysis.
 */
class _ApiRequest {
  /** @return static */
  public function addWhere(string $fieldName, string $op, mixed $value = NULL): static {
  }

  /** @return static */
  public function addValue(string $fieldName, mixed $value): static {
  }

  /** @return static */
  public function addOrderBy(string $fieldName, string $direction = 'ASC'): static {
  }

  /** @return static */
  public function setLimit(int $limit): static {
  }

  /**
   * @param array<int, string> $select
   * @return static
   */
  public function setSelect(array $select): static {
  }

  /**
   * @return iterable<array<string, mixed>>
   */
  public function execute(): iterable {
  }

  /** @return array<string, mixed> */
  public function first(): array {
  }

  /** @return mixed */
  public function single() {
  }
}

class Contribution {
  public static function get(bool $checkPermissions = TRUE): _ApiRequest {
  }

  public static function create(bool $checkPermissions = TRUE): _ApiRequest {
  }

  public static function update(bool $checkPermissions = TRUE): _ApiRequest {
  }

  public static function delete(bool $checkPermissions = TRUE): _ApiRequest {
  }

  public static function save(bool $checkPermissions = TRUE): _ApiRequest {
  }
}

class ContributionRecur {
  public static function get(bool $checkPermissions = TRUE): _ApiRequest {
  }

  public static function create(bool $checkPermissions = TRUE): _ApiRequest {
  }

  public static function update(bool $checkPermissions = TRUE): _ApiRequest {
  }

  public static function delete(bool $checkPermissions = TRUE): _ApiRequest {
  }
}

class PaymentProcessor {
  public static function get(bool $checkPermissions = TRUE): _ApiRequest {
  }

  public static function create(bool $checkPermissions = TRUE): _ApiRequest {
  }

  public static function update(bool $checkPermissions = TRUE): _ApiRequest {
  }
}

class OptionValue {
  public static function get(bool $checkPermissions = TRUE): _ApiRequest {
  }

  public static function create(bool $checkPermissions = TRUE): _ApiRequest {
  }

  public static function update(bool $checkPermissions = TRUE): _ApiRequest {
  }
}
