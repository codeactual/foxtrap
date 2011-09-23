<?php
/**
 * Stdout class.
 *
 * @package Foxtrap
 */

namespace Foxtrap\Log;

use \Foxtrap\Log\Api;

require_once __DIR__ . '/Api.php';

/**
 *  stdout logging API implementation.
 */
class Stdout implements Api
{
  public function onDownloadEnqueue(array $event)
  {
    echo "+ {$event['uri']}\n";
  }

  public function onDownloadResponse(array $event)
  {
    printf(
      "%s %d/%d %s id=%s mem=%dK %s\n",
      $event['error'] ? '!' : '$',
      $event['uriDownloaded'],
      $event['uriTotal'],
      $event['uri'],
      $event['id'],
      memory_get_usage(true) / 1024,
      $event['error']
    );
  }

  public function onDownloadError(array $event)
  {
    printf(
      "! %d/%d %s id=%s mem=%dK %s %s\n",
      $event['uriDownloaded'],
      $event['uriTotal'],
      $event['uri'],
      $event['id'],
      memory_get_usage(true) / 1024,
      $event['curlError'],
      json_encode($event['curlInfo'])
    );
  }
}
