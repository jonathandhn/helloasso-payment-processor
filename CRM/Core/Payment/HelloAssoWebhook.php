<?php

// The webhook processor class is declared alongside the main processor class.
// Keep this PSR-0-compatible entry point so PHP can autoload it while
// restoring serialized payment processor instances from a CiviCRM session.
require_once __DIR__ . '/HelloAsso.php';
