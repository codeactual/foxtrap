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
use \Foxtrap\Query;
use \HTMLPurifier;

class Foxtrap
{
  /**
   * @var DbApi Implementation interface, e.g. Db\Mysqli.
   */
  protected $db;

  /**
   * @var DbApi Implementation interface, e.g. Db\Mysqli.
   */
  protected $ftDb;

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

  public function __construct(CurlyQueue $queue, DbApi $db, HTMLPurifier $purifier, LogApi $log, Query $query, DbApi $ftDb)
  {
    $this->db = $db;
    $this->purifier = $purifier;
    $this->queue = $queue;
    $this->queue->setResponseCallback(array($this, 'onDownloadResponse'));
    $this->queue->setErrorCallback(array($this, 'onDownloadError'));
    $this->log = $log;
    $this->query = $query;
    $this->ftDb = $ftDb;
  }

  /**
   * Download HTML of any new or retry-eligible marks.
   *
   * @return int Download count.
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

      return $this->uriDownloaded;
    }

    return 0;
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
        $this->db->saveSuccess(
          $content,
          $this->cleanResponseBody($content),
          $context['id']
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
   * Access to $this->ftDb.
   *
   * @return \Foxtrap\Db\Api
   */
  public function getFtDb()
  {
    return $this->ftDb;
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

  /**
   * {@inheritdoc}
   */
  public function seedFtIndex($table, $index)
  {
    $selectSql = "
      SELECT `id`, `title`, `uri`, `tags`, `body_clean`
      FROM `{$table}`";

    $result = $this->db->query($selectSql);
    if ($result) {
      while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
        $replaceSql = sprintf("
          REPLACE INTO `%s`
          (`id`, `title`, `uri`, `tags`, `body_clean`)
          VALUES
          ('%s', '%s', '%s', '%s', '%s')",
          $index,
          $this->db->escape($row['id']),
          $this->db->escape($row['title']),
          $this->db->escape($row['uri']),
          $this->db->escape($row['tags']),
          $this->db->escape($row['body_clean'])
        );

        if (!$this->ftDb->query($replaceSql)) {
          throw new Exception($this->link->sqlstate);
        }
      }
    }
  }
}
