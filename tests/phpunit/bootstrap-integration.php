<?php

/**
 * Bootstrap pour les tests d'intégration CiviCRM (PHPUnit 11+).
 *
 * PHPUnit 11 a supprimé les TestListeners — CiviTestListener n'est plus appelé.
 * On boot CiviCRM directement ici avec --level=full + CIVICRM_UF=UnitTests.
 *
 * Lancement :
 *   php vendor/bin/phpunit -c phpunit-integration.xml --no-coverage
 */

ini_set('memory_limit', '2G');

// Enregistre les autoloaders de l'extension en premier.
$loader = new \Composer\Autoload\ClassLoader();
$loader->add('CRM_', [__DIR__ . '/../..', __DIR__]);
$loader->addPsr4('Civi\\', [__DIR__ . '/../../Civi', __DIR__ . '/Civi']);
$loader->add('api_', [__DIR__ . '/../..', __DIR__]);
$loader->addPsr4('api\\', [__DIR__ . '/../../api', __DIR__ . '/api']);
$loader->register();

// Boot CiviCRM complet avec le mode par défaut (Drupal) pour utiliser la vraie base de données.
// Les tests utiliseront CRM_Core_Transaction pour rollback.
// phpcs:disable
eval(cv('php:boot --level=full', 'phpcode'));
// phpcs:enable


/**
 * Call the "cv" command (copie identique à bootstrap.php pour le CiviTestListener).
 *
 * @param string $cmd
 * @param string $decode 'json', 'phpcode', or 'raw'
 * @return mixed
 * @throws \RuntimeException
 */
function cv(string $cmd, string $decode = 'json') {
  $cmd = 'cv ' . $cmd;
  $descriptorSpec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => STDERR];
  $oldOutput = getenv('CV_OUTPUT');
  putenv('CV_OUTPUT=json');

  $cmd = sprintf('cd %s; %s', escapeshellarg(getenv('PWD')), $cmd);

  $process = proc_open($cmd, $descriptorSpec, $pipes, __DIR__);
  putenv("CV_OUTPUT=$oldOutput");
  fclose($pipes[0]);
  $result = stream_get_contents($pipes[1]);
  fclose($pipes[1]);
  if (proc_close($process) !== 0) {
    throw new RuntimeException("Command failed ($cmd):\n$result");
  }
  switch ($decode) {
    case 'raw':
      return $result;

    case 'phpcode':
      if (substr(trim($result), 0, 12) !== '/*BEGINPHP*/' || substr(trim($result), -10) !== '/*ENDPHP*/') {
        throw new \RuntimeException("Command failed ($cmd):\n$result");
      }
      return $result;

    case 'json':
      return json_decode($result, 1);

    default:
      throw new RuntimeException("Bad decoder format ($decode)");
  }
}
