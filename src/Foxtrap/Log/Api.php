<?php

namespace Foxtrap\Log;

/**
 * Api interface.
 *
 * @package Foxtrap
 */

/**
 * Contract for concretes like Log\Stdout to optionally relay events.
 */
interface Api
{
  /**
   * Handle queue additions.
   *
   * @param array $event
   * - string 'uri'
   * @return void
   */
  public function onDownloadEnqueue(array $event);

  /**
   * Handle download responses (200, 3XX, 4XX, etc.)
   *
   * @param array $event
   * - string 'uri'
   * - mixed 'id' Row ID
   * - int 'uriDownloaded'
   * - int 'uriTotal'
   * - int 'errno' curl_errno()
   * - string 'error' From cURL, DB, or JSON encoded curl_getinfo() on non-200
   * @return void
   */
  public function onDownloadResponse(array $event);

  /**
   * Handle download errors.
   *
   * @param array $event
   * - string 'uri'
   * - mixed 'id' Row ID
   * - int 'uriDownloaded'
   * - int 'uriTotal'
   * - string 'curlErrno' curl_errno()
   * - string 'curlError' curl_error()
   * - string 'curlInfo' curl_getinfo()
   * @return void
   */
  public function onDownloadError(array $event);
}
