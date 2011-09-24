<?php
/**
 * Factory class.
 *
 * @package Foxtrap
 */

namespace Foxtrap;

use \CurlyQueue\CurlyQueue;
use \Foxtrap\Foxtrap;
use \Foxtrap\Query;
use \HTMLPurifier;
use \HTMLPurifier_Config;
use \SphinxClient;

/**
 * Configure Foxtrap dependencies based on configuration array.
 */
class Factory
{
  /**
   * Return $config defined in config/config.php
   *
   * @return Foxtrap
   */
  public function getConfigFromFile()
  {
    $configFile = __DIR__ . '/../../config/config.php';
    require $configFile;
    return $config;
  }

  /**
   * Use createInstanceFromArray() to build an instance based on config.php.
   *
   * @return Foxtrap
   */
  public function createInstance()
  {
    $config = $this->getConfigFromFile();
    return $this->createInstanceFromArray($config);
  }

  /**
   * Use createInstanceFromArray() to build a test-only instance.
   *
   * @return Foxtrap
   */
  public function createTestInstance()
  {
    $config = $this->getConfigFromFile();
    $config['db']['opts'] = $config['db']['testOpts'];
    return $this->createInstanceFromArray($config);
  }

  /**
   * Convert config array to an instance with injected dependencies.
   *
   * @param array $config From config.php
   * @return Foxtrap New instance based on configuration.
   */
  public function createInstanceFromArray(array $config)
  {
    $config['curl'][CURLOPT_RETURNTRANSFER] = 1;
    $queue = new CurlyQueue($config['curl']);

    require_once __DIR__ . "/Db/{$config['db']['class']}.php";
    $dbClass = "\\Foxtrap\\Db\\{$config['db']['class']}";
    $dbLink = call_user_func_array(
      array($dbClass, 'createLink'),
      $config['db']['opts']
    );
    $db = new $dbClass($dbLink, $config);

    $purifierConfig = HTMLPurifier_Config::createDefault();
    foreach ($config['htmlpurifier'] as $key => $value) {
      $purifierConfig->set($key, $value);
    }
    $purifier = new HTMLPurifier($purifierConfig);

    if (!$config['log']['class']) {
      $config['log']['class'] = 'Blackhole';
    }
    require_once __DIR__ . "/Log/{$config['log']['class']}.php";
    $logClass = "\\Foxtrap\\Log\\{$config['log']['class']}";
    $log = new $logClass();

    $cl = new SphinxClient();
    $cl->SetServer($config['sphinx']['host'], $config['sphinx']['port']);
    $query = new Query($cl, $db, $config['sphinx']);

    return new Foxtrap($queue, $db, $purifier, $log, $query);
  }
}
