<?php
/**
 * Foxtrap functions used by back- and front-end, or located here for unit tests.
 *
 * @package Foxtrap
 */

namespace Foxtrap;

use \CurlyQueue\CurlyQueue;
use \Exception;
use \Flow\Flow;
use \Foxtrap\Db\Api as DbApi;
use \Foxtrap\Log\Api as LogApi;
use \Foxtrap\FtIndex;
use \Foxtrap\Query;
use \HTMLPurifier;
use \DOMDocument;
use \DOMXPath;

class Foxtrap
{
  /**
   * @var DbApi Implementation interface, e.g. Db\Mysqli.
   */
  protected $db;

  /**
   * @var FtIndex instance.
   */
  protected $ftIndex;

  /**
   * @var LogApi Implementation interface, e.g. Log\Stdout.
   */
  protected $log;

  /**
   * @var HTMLPurifier
   */
  protected $purifier;

  /**
   * @var Query
   */
  protected $query;

  /**
   * @var CurlyQueue
   */
  protected $queue;

  /**
   * @var int $uriTotal Amount queued for download.
   */
  protected $uriTotal;

  /**
   * @var int $uriDownloaded Amount downloaded.
   */
  protected $uriDownloaded;

  public function __construct(CurlyQueue $queue, DbApi $db, HTMLPurifier $purifier, LogApi $log, Query $query, FtIndex $ftIndex)
  {
    $this->db = $db;
    $this->purifier = $purifier;
    $this->queue = $queue;
    $this->queue->setResponseCallback(array($this, 'onDownloadResponse'));
    $this->queue->setErrorCallback(array($this, 'onDownloadError'));
    $this->log = $log;
    $this->query = $query;
    $this->ftIndex = $ftIndex;
  }

  /**
   * Download HTML of any new or retry-eligible marks.
   *
   * @return array Downloaded marks. Each element is an array w/ 'id' and 'uri'.
   */
  public function download()
  {
    $marks = $this->db->getMarksToDownload();

    if ($marks) {
      $this->uriTotal = count($marks);

      foreach ($marks as $mark) {
        $this->log->onDownloadEnqueue(array('uri' => $mark['uri']));
        $this->queue->add($mark['uri'], $mark);
      }

      Flow::setMaxRuntime($this->queue, 2 * $this->uriTotal);

      $this->queue->exec();

      return $marks;
    }

    return $marks;
  }

  /**
   * Return body text with redundant whitespace and markup removed.
   *
   * @param string $text
   * @return string
   */
  public function cleanResponseBody($text)
  {
    // Ensure text in adjacent tags are spaced out after purify().
    $text = preg_replace('/(<\/[a-z]+>)/', '  \1 ', $text);
    $text = preg_replace('/(<[a-z]+ ?\/>)/', '  \1', $text);

    $text = $this->purifier->purify($text);

    // Normalize non-unicode and unicode whitespace.
    $text = preg_replace('/\s|\p{Z}/u', ' ', $text);

    // Remove redundant whitespace.
    return trim(preg_replace('/\s\s+/', ' ', $text));
  }

  /**
   * Return a CurlyQueue handler for successful response events.
   *
   * @return void
   */
  public function onDownloadResponse($ch, $content, $context)
  {
    $errno = curl_errno($ch);
    $error = '';

    $this->uriDownloaded++;

    if (0 === $errno) {
      $info = curl_getinfo($ch);
      if (200 == $info['http_code'] && strlen($content)) {
        // Extract HTML title to avoid having to manually input from UI.
        $doc = new DOMDocument();
        @$doc->loadHTML($content);
        $xpath = new DOMXPath($doc);
        $title = $xpath->query('//title');
        if ($title) {
          $title = $title->item(0)->nodeValue;
        } else {
          $title = 'untitled page';
        }

        $this->db->saveSuccess(
          $content,
          $this->cleanResponseBody($content),
          $context['id'],
          $title
        );
      } else {
        $error = json_encode($info);
      }
    } else {
      $error = curl_error($ch);
    }

    if ($error) {
      $this->db->saveError($error, $context['id']);
      $error = $error ? "({$error})" : '';
    }

    $this->log->onDownloadResponse(
      array(
        'uri' => $context['uri'],
        'id' => $context['id'],
        'uriDownloaded' => $this->uriDownloaded,
        'uriTotal' => $this->uriTotal,
        'errno' => $errno,
        'error' => $error
      )
    );
  }

  /**
   * Return a CurlyQueue handler for error events.
   *
   * @return Closure
   */
  public function onDownloadError($ch, $context)
  {
    $this->uriDownloaded++;

    $curlError = curl_error($ch);
    $curlInfo = curl_getinfo($ch);

    $this->db->saveError(
      $curlError . ': ' . json_encode($curlInfo),
      $context['id']
    );

    $this->log->onDownloadError(
      array(
        'uri' => $context['uri'],
        'id' => $context['id'],
        'uriDownloaded' => $this->uriDownloaded,
        'uriTotal' => $this->uriTotal,
        'curlErrno' => curl_errno($ch),
        'curlError' => $curlError,
        'curlInfo' => $curlInfo
      )
    );
  }

  /**
   * Access to $this->db.
   *
   * @return \Foxtrap\Db\Api
   */
  public function getDb()
  {
    return $this->db;
  }

  /**
   * Access to $this->ftIndex.
   *
   * @return \Foxtrap\FtIndex
   */
  public function getFtIndex()
  {
    return $this->ftIndex;
  }

  /**
   * Access to $this->log.
   *
   * @return \Foxtrap\Log\Api
   */
  public function getLog()
  {
    return $this->log;
  }

  /**
   * Access to $this->queue.
   *
   * @return CurlyQueue
   */
  public function getQueue()
  {
    return $this->queue;
  }

  /**
   * Access to $this->purifier.
   *
   * @return HTMLPurifier
   */
  public function getPurifier()
  {
    return $this->purifier;
  }

  /**
   * Access to $this->query.
   *
   * @return Query
   */
  public function getQuery()
  {
    return $this->query;
  }

  /**
   * Set the HTTP header for JSONP responses.
   *
   * @return void
   */
  public function jsonpHeader()
  {
    header('Content-Type: application/javascript; charset=utf-8');
  }

  /**
   * Return a JSON response body.
   *
   * @param string $json
   * @param string $callback
   * @return string
   */
  public function jsonpCallback($json, $callback)
  {
    return "{$callback}({$json});";
  }
}
