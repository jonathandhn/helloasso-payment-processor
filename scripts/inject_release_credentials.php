<?php

require_once __DIR__ . '/ReleaseCredentialInjector.php';

$target = $argv[1] ?? '';
if ($target === '') {
    fwrite(STDERR, "Usage: php scripts/inject_release_credentials.php <PartnerCredentials.php>\n");
    exit(2);
}

try {
    CRM_HelloassoPaymentProcessor_ReleaseCredentialInjector::inject($target, $_SERVER + $_ENV);
    fwrite(STDOUT, "HelloAsso release credentials injected and fingerprint-validated.\n");
}
catch (Throwable $e) {
    fwrite(STDERR, "Release credential validation failed: {$e->getMessage()}\n");
    exit(1);
}
