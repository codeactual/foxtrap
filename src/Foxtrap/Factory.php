<?php
/**
 * Factory class.
 *
 * @package Foxtrap
 */

namespace Foxtrap;

use \CurlyQueue;
use \HTMLPurifier;
use \HTMLPurifier_Config;

require_once __DIR__ . '/../Foxtrap.php';
require_once __DIR__ . '/../../vendor/curlyqueue/src/CurlyQueue.php';
require_once __DIR__ . '/../../vendor/htmlpurifier/library/HTMLPurifier.auto.php';

/**
 * Configure Foxtrap dependencies based on configuration array.
 */
class Factory
{
  /**
   * Convert config array to an instance with injected dependencies.
   *
   * @param array $config From config.php
   * @return Foxtrap New instance based on configuration.
   */
  public function createInstance(array $config)
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

    return new Foxtrap($queue, $db, $purifier, $log);
  }

  /**
   * Apply the test-related options in $config to createInstance().
   *
   * @param array $config From config.php
   * @return Foxtrap New instance based on configuration.
   */
  public function createTestInstance(array $config)
  {
    $config['db']['opts'] = $config['db']['testOpts'];
    return $this->createInstance($config);
  }
}
