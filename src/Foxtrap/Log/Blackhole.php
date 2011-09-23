<?php
/**
 * Blackhole class.
 *
 * @package Foxtrap
 */

namespace Foxtrap\Log;

use \Foxtrap\Log\Api;

/**
 *  Default logging API implementation.
 */
class Blackhole implements Api
{
  /**
   * {@inheritdoc}
   */
  public function onDownloadEnqueue(array $event)
  {
  }

  /**
   * {@inheritdoc}
   */
  public function onDownloadResponse(array $event)
  {
  }

  /**
   * {@inheritdoc}
   */
  public function onDownloadError(array $event)
  {
  }
}
