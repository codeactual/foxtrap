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
   * @param array $pruneIds IDs of marks removed in Firefox.
   * @return array Amounts of marks affected.
   * - int 'pruned' Removed from the database due to removal in Firefox
   * - int 'flagged' Content fields erased, flagged as 'nosave'
   */
  public function cleanup(array $pruneIds)
  {
    $results = array();
    $results['flagged'] = $this->db->flagNonDownloadable();
    if ($pruneIds) {
      $results['pruned'] = $this->db->deleteMarksById($pruneIds);
    }
    return $results;
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

    // Collect tag modification times to override page modification times
    // in case the former is more recent.
    // URI hashes => timestamps
    $tagsModified = array();

    // Collect page tags.
    // URI hashes => array of tag names
    $pageTags = array();
    foreach ($tags['children'] as $tag) {
      $tagTitle = $tag['title'];
      foreach ($tag['children'] as $page) {
        $uriHash = md5($page['uri']);
        if (!isset($pageTags[$uriHash])) {
          $pageTags[$uriHash] = array();
        }
        $pageTags[$uriHash][] = $tagTitle;
        $tagsModified[$page['uri']] = $page['lastModified'];
      }
    }

    // Page fields to keep.
    $kept = array_flip(array('uri', 'title', 'lastModified', 'dateAdded'));

    foreach ($unsorted['children'] as $pos => $child) {
      // Strip unused elements.
      foreach ($child as $key => $val) {
        if (!array_key_exists($key, $kept)) {
          unset($unsorted['children'][$pos][$key]);
        }
      }

      // Resolve last known modification time.
      if (array_key_exists($child['uri'], $tagsModified)
        && $tagsModified[$child['uri']] > $unsorted['children'][$pos]['lastModified']) {
        $unsorted['children'][$pos]['lastModified'] = $tagsModified[$child['uri']];
      }
    }

    return array('marks' => $unsorted['children'], 'pageTags' => $pageTags);
  }

  /**
   * Generate a bookmark's hash.
   *
   * @param string $title
   * @param string $uri
   * @param string $tags Comma separated.
   * @param int $added UNIX timestamp.
   * @return string
   */
  public function generateMarkHash($uri, $title, $tags, $added)
  {
    // Encode for predictability (to match other `marks` text columns).
    return md5(utf8_encode($uri . $title . $tags . strval($added)));
  }

  /**
   * Use output from jsonToArray() to add/update each URI's DB record.
   *
   * @param array $fileData See jsonToArray().
   * @return array IDs to prune due to removal/update of mark in FF.
   */
  public function registerMarks(array $fileData)
  {
    $pruneIds = array();

    $prevHashes = $this->db->getMarkHashes();

    foreach ($fileData['marks'] as $mark) {
      $mark['lastModified'] = (int) substr($mark['lastModified'], 0, 10);
      $mark['dateAdded'] = (int) substr($mark['dateAdded'], 0, 10);
      $uriHash = md5($mark['uri']);

      if (empty($fileData['pageTags'][$uriHash])) {
        $tags = '';
      } else {
        $tags = implode(',', $fileData['pageTags'][$uriHash]);
      }

      $curHash = $this->generateMarkHash($mark['uri'], $mark['title'], $tags, $mark['dateAdded']);

      if (isset($prevHashes[$curHash])) {
        unset($prevHashes[$curHash]);
        continue;
      }

      // For marks tagged as 'nosave', set an error state to prevent download.
      if (false === strpos($tags, 'nosave')) {
        $lastErr = '';
      } else {
        $lastErr = 'nosave';
      }

      $this->db->register(
        array(
          'title' => $mark['title'],
          'uri' => $mark['uri'],
          'hash' => $curHash,
          'tags' => $tags,
          'last_err' => $lastErr,
          'modified' => $mark['lastModified'],
          'added' => $mark['dateAdded']
        )
      );

      unset($prevHashes[$curHash]);
    }

    foreach ($prevHashes as $hash => $id) {
      $pruneIds[] = $id;
    }

    return $pruneIds;
  }

  /**
   * Return the filename of the most recent input checksum.
   *
   * @return string
   */
  public function getLastRunHashFile()
  {
    return __DIR__ . '/../../config/lastrun';
  }

  /**
   * Write the checksum of the most recent input JSON.
   *
   * @param string $json
   * @return void
   * @throws Exception
   * - on write error
   */
  public function writeLastRunHash($json)
  {
    $file = $this->getLastRunHashFile();
    $hash = md5($json);
    // @codeCoverageIgnoreStart
    if (false === file_put_contents($file, $hash)) {
      throw new Exception("could not write '{$md5}' to {$file}");
    }
    // @codeCoverageIgnoreEnd
  }

  /**
   * Determine if the input JSON matches the last run's.
   *
   * @param string $json
   * @return boolean True if input matches.
   */
  public function isLastRunInputSame($json)
  {
    $file = $this->getLastRunHashFile();
    if (file_exists($file)) {
      return md5($json) === file_get_contents($file);
    }
    return false;
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
