<?php
/**
 * Stdout class.
 *
 * @package Foxtrap
 */

namespace Foxtrap\Log;

use \Foxtrap\Log\Api;

/**
 *  stdout logging API implementation.
 */
class Stdout implements Api
{
  /**
   * {@inheritdoc}
   */
  public function onDownloadEnqueue(array $event)
  {
    echo "+ {$event['uri']}\n";
  }

  /**
   * {@inheritdoc}
   */
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

  /**
   * {@inheritdoc}
   */
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
