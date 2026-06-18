<?php

if (PHP_SAPI !== 'cli') {
  fwrite(STDERR, "This script can only be run from command line.\n");
  exit(1);
}

$baseDir = dirname(__DIR__);

// Try to resolve paths dynamically
$coreBase = null;
$packagesBase = null;

// 1. Try to resolve via 'cv' command
$cvPaths = [];
$ancestor = $baseDir;
for ($i = 0; $i < 12; $i++) {
  if (file_exists($ancestor . '/vendor/bin/cv')) {
    $cvPaths[] = $ancestor . '/vendor/bin/cv';
  }
  if (file_exists($ancestor . '/web/vendor/bin/cv')) {
    $cvPaths[] = $ancestor . '/web/vendor/bin/cv';
  }
  if (file_exists($ancestor . '/bin/cv')) {
    $cvPaths[] = $ancestor . '/bin/cv';
  }
  $parent = dirname($ancestor);
  if ($parent === $ancestor) {
    break;
  }
  $ancestor = $parent;
}
$cvPaths[] = 'cv'; // Global fallback

foreach ($cvPaths as $path) {
  $test = @shell_exec(escapeshellcmd($path) . ' path -d "[civicrm.root]" 2>/dev/null');
  if ($test && ($resolved = trim($test)) && is_dir($resolved)) {
    $coreBase = $resolved;
    break;
  }
}

// 2. If 'cv' didn't work, traverse up the tree to look for vendor folders
if (!$coreBase) {
  $ancestor = $baseDir;
  for ($i = 0; $i < 12; $i++) {
    $candidates = [
      $ancestor . '/vendor/civicrm/civicrm-core',
      $ancestor . '/web/vendor/civicrm/civicrm-core',
      $ancestor . '/docroot/vendor/civicrm/civicrm-core',
      $ancestor . '/civicrm-core',
    ];
    foreach ($candidates as $cand) {
      if (is_dir($cand)) {
        $coreBase = $cand;
        break 2;
      }
    }
    $parent = dirname($ancestor);
    if ($parent === $ancestor) {
      break;
    }
    $ancestor = $parent;
  }
}

// 3. Fallbacks if not found
if (!$coreBase) {
  $coreBase = '/home/crm/public_html/vendor/civicrm/civicrm-core';
  if (!is_dir($coreBase)) {
    fwrite(STDERR, "Could not locate CiviCRM core root dynamically.\n");
    fwrite(STDERR, "Please install CiviCRM core via Composer, ensure 'cv' is available, or set CIVICRM_CORE path.\n");
    exit(1);
  }
}

// Try to find packages folder
$packagesBase = null;
if (is_dir($coreBase . '/packages')) {
  $packagesBase = $coreBase . '/packages';
}
$ancestor = dirname($coreBase);
if (is_dir($ancestor . '/civicrm-packages')) {
  $packagesBase = $ancestor . '/civicrm-packages';
}

$includes = [$coreBase];
if ($packagesBase) {
  $includes[] = $packagesBase;
}
if (is_dir($coreBase . '/packages')) {
  $includes[] = $coreBase . '/packages';
}
$includes[] = dirname($coreBase) . '/civicrm-packages';

ini_set('include_path', implode(PATH_SEPARATOR, array_unique($includes)));
date_default_timezone_set('UTC');

define('CIVICRM_UF', 'Drupal');
define('CIVICRM_UF_BASEURL', '/');
define('CIVICRM_L10N_BASEDIR', getenv('CIVICRM_L10N_BASEDIR') ?: $coreBase . '/l10n');
$GLOBALS['civicrm_paths']['cms.root']['url'] = 'http://gencode.example.com/do-not-use';

require_once 'CRM/Core/ClassLoader.php';
CRM_Core_ClassLoader::singleton()->register();

makeDAOs($baseDir, $baseDir . '/xml/schema/CRM/HelloassoPaymentProcessor/*.xml');

function makeDAOs(string $baseDir, string $xmlSchemasGlob): void {
  $specification = new CRM_Core_CodeGen_Specification();
  $specification->buildVersion = CRM_Utils_System::majorVersion();
  $config = new stdClass();
  $config->phpCodePath = $baseDir . '/';
  $config->sqlCodePath = $baseDir . '/sql/';
  $config->database = [
    'name' => '',
    'attributes' => '',
    'tableAttributes_modern' => 'ENGINE=InnoDB',
    'tableAttributes_simple' => 'ENGINE=InnoDB',
    'comment' => '',
  ];
  $config->tables = [];

  foreach (glob($xmlSchemasGlob) as $xmlSchema) {
    $dom = new DOMDocument();
    $dom->loadXML(file_get_contents($xmlSchema));
    $xml = simplexml_import_dom($dom);
    if (!$xml) {
      throw new RuntimeException("There is an error in the XML for $xmlSchema");
    }
    $specification->getTable($xml, $config->database, $config->tables);
    $name = (string) $xml->name;
    $config->tables[$name]['name'] = $name;
    $config->tables[$name]['sourceFile'] = CRM_Utils_File::relativize($xmlSchema, $baseDir);
  }

  foreach ($config->tables as $table) {
    $dao = new CRM_Core_CodeGen_DAO($config, (string) $table['name'], 'ts');
    ob_start();
    $dao->run();
    ob_end_clean();
    echo 'Write ' . $dao->getAbsFileName() . PHP_EOL;
  }
}
