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

  public function __construct(CurlyQueue $queue, DbApi $db, HTMLPurifier $purifier, LogApi $log, Query $query)
  {
    $this->db = $db;
    $this->purifier = $purifier;
    $this->queue = $queue;
    $this->queue->setResponseCallback(array($this, 'onDownloadResponse'));
    $this->queue->setErrorCallback(array($this, 'onDownloadError'));
    $this->log = $log;
    $this->query = $query;
  }

  /**
   * Sync-related maintenance.
   *
   * @param int $latestVer Latest import version ID (timestamp)
   * @return array Amounts of marks affected
   * - int 'pruned' Removed from the database due to removal in Firefox
   * - int 'flagged' Content fields erased, flagged as 'nosave'
   */
  public function cleanup($latestVer)
  {
    return array(
      'pruned' => $this->db->pruneRemovedMarks($latestVer),
      'flagged' => $this->db->flagNonDownloadable()
    );
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
        if (!mb_check_encoding($content, 'UTF-8')) {
          $content = utf8_encode($content);
        }
        $contentClean = preg_replace('/\s{2,}/', ' ', trim($content));
        $contentClean = $this->purifier->purify($contentClean);
        $this->db->saveSuccess($content, $contentClean, $context['id']);
      } else {
        $error = json_encode($info);
      }
    } else {
      $error = curl_error($ch);
    }

    if ($error) {
      $this->db->saveError($error_stmt, $context['id']);
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
   * Convert JSON from a Firefox backup file to a lighter array structured
   * for use by consumers like registerMarks().
   *
   * @param string $json
   * @return array
   * - array 'marks' Values are arrays w/ keys 'uri', 'title', 'lastmodified'
   * - array 'pageTags' Keys are URIs, values are arrays of tag titles
   */
  public function jsonToArray($json)
  {
    $assoc = json_decode($json, true);

    $unsorted = null;
    $tags = null;

    foreach ($assoc['children'] as $folder) {
      switch ($folder['title']) {
      case 'Unsorted Bookmarks':
        $unsorted = $folder;
        break;
      case 'Tags':
        $tags = $folder;
        break;
      default:
        continue;
      }
    }

    // COLLECT ALL TAGS FOR EACH PAGE
    // (keys = URI hashes, values = array of tag strings)
    $pageTags = array();
    foreach ($tags['children'] as $tag) {
      $tagTitle = $tag['title'];
      foreach ($tag['children'] as $page) {
        $uriHash = md5($page['uri']);
        if (!isset($pageTags[$uriHash])) {
          $pageTags[$uriHash] = array();
        }
        $pageTags[$uriHash][] = $tagTitle;
      }
    }

    // Strip unused elements
    $kept = array_flip(array('uri', 'title', 'lastModified'));
    foreach ($unsorted['children'] as $pos => $child) {
      foreach ($child as $key => $val) {
        if (!array_key_exists($key, $kept)) {
          unset($unsorted['children'][$pos][$key]);
        }
      }
    }

    return array('marks' => $unsorted['children'], 'pageTags' => $pageTags);
  }

  /**
   * Use output from jsonToArray() to add/update each URI's DB record.
   *
   * @param array $fileData See jsonToArray().
   * @return int Latest version ID (timestamp).
   */
  public function registerMarks(array $fileData)
  {
    $version = time();

    foreach ($fileData['marks'] as $mark) {
      $lastModified = (int) substr($mark['lastModified'], 0, 10);
      $uriHash = md5($mark['uri']);
      if (isset($fileData['pageTags'][$uriHash])) {
        $pageTagsStr = implode(' ', $fileData['pageTags'][$uriHash]);
      } else {
        $pageTagsStr = '';
      }

      // For marks tagged as 'nosave', set an error state to prevent download
      if (false === strpos($pageTagsStr, 'nosave')) {
        $lastErr = '';
      } else {
        $lastErr = 'nosave';
      }

      // multiple bookmarks may point to the same base uri but different fragments,
      // so use the fragment-less URI to avoid duplicates
      $uriHashWithoutFrag = md5(
        preg_replace('/#[^!].*$/','', $mark['uri'])
      );

      $this->db->register(
        array(
          'title' => $mark['title'],
          'uri' => $mark['uri'],
          'uri_hash' => $uriHashWithoutFrag,
          'tags' => $pageTagsStr,
          'last_err' => $lastErr,
          'modified' => $lastModified,
          'version' => $version
        )
      );
    }

    return $version;
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
