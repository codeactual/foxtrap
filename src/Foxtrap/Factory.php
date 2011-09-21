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

require_once __DIR__ . '/../src/Foxtrap.php';
require_once __DIR__ . '/../vendor/curlyqueue/src/CurlyQueue.php';
require_once __DIR__ . '/../vendor/htmlpurifier/library/HTMLPurifier.auto.php';

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
    $markDownloader = new CurlyQueue($config['curl']);

    $dbClass = "\\Foxtrap\\Db\\{$config['db']['class']}";
    $dbLink = call_user_func_array(
      array($dbClass, 'createLink'),
      $config['db']['opts']
    );
    require_once __DIR__ . "/../src/Db/{$dbClass}.php";
    $db = new {$dbClass}($dbLink);

    $config = HTMLPurifier_Config::createDefault();
    foreach ($config['htmlpurifier'] as $key => $value) {
      $config->set($key, $value);
    }
    $purifier = HTMLPurifier($config);

    return new Foxtrap($markDownloader, $db, $purifier);
  }
}
