<?php
/**
 * Blackhole class.
 *
 * @package Foxtrap
 */

namespace Foxtrap\Log;

use \Foxtrap\Log\Api;

require_once __DIR__ . '/Api.php';

/**
 *  Default logging API implementation.
 */
class Blackhole implements Api
{
  public function onDownloadEnqueue(array $event)
  {
  }

  public function onDownloadResponse(array $event)
  {
  }

  public function onDownloadError(array $event)
  {
  }
}
