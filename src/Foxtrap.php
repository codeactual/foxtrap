<?php
/**
 * Foxtrap functions used by back- and front-end, or located here for unit tests.
 *
 * @package Foxtrap
 */

namespace Foxtrap;

use \CurlyQueue;
use \Exception;
use \Flow\Flow;
use \Foxtrap\Db\Api;
use \HTMLPurifier;

require_once __DIR__ . '/Foxtrap/Db/Api.php';
require_once __DIR__ . '/../vendor/curlyqueue/src/CurlyQueue.php';
require_once __DIR__ . '/../vendor/curlyqueue/vendor/flow/src/Flow/Flow.php';
require_once __DIR__ . '/../vendor/htmlpurifier/library/HTMLPurifier.auto.php';

class Foxtrap
{
  /**
   * @var Api Implementation interface, e.g. Db\Mysqli.
   */
  protected $db;

  /**
   * @var HTMLPurifier
   */
  protected $purifier;

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

  public function __construct(CurlyQueue $queue, Api $db, HTMLPurifier $purifier)
  {
    $this->db = $db;
    $this->purifier = $purifier;
    $this->queue = $queue;
    $this->queue->setResponseCallback($this->onDownloadResponse());
    $this->queue->setErrorCallback($this->onDownloadError());
    $this->queue->setEndCallback($this->onDownloadEnd());
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
        echo "+ {$mark['uri']}\n";
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
   * @return Closure
   */
  public function onDownloadResponse()
  {
    $foxtrap = $this;
    return function ($ch, $content, $requestObj) use ($foxtrap) {
      $errno = curl_errno($ch);
      $error = '';

      $foxtrap->incrUriDownloaded();

      if (0 === $errno) {
        $info = curl_getinfo($ch);
        if (200 == $info['http_code'] && strlen($content)) {
          if (!mb_check_encoding($content, 'UTF-8')) {
            $content = utf8_encode($content);
          }
          $content_clean = preg_replace('/\s{2,}/', ' ', trim($content));
          $content_clean = $foxtrap->getPurifier()->purify($content_clean);
          $foxtrap->getDb()->saveSuccess($content, $content_clean, $requestObj['id']);
        } else {
          $error = json_encode($info);
        }
      } else {
        $error = curl_error($ch);
      }

      if ($error) {
        $foxtrap->getDb()->saveError($error_stmt, $requestObj['id']);
        $error = $error ? "({$error})" : '';
      }

      $mem = memory_get_usage(true) / 1024;
      $symbol = $error ? '!' : '$';
      echo "{$symbol} {$requestObj['uri']} {$foxtrap->getUriDownloaded()}/{$foxtrap->getUriTotal()} mem {$mem}K id {$requestObj['id']} {$error}\n";
    };
  }

  /**
   * Return a CurlyQueue handler for error events.
   *
   * @return Closure
   */
  public function onDownloadError()
  {
    $foxtrap = $this;
    return function ($ch, $requestObj) use ($foxtrap) {
      $foxtrap->incrUriDownloaded();

      $mem = memory_get_usage(true) / 1024;
      $error = sprintf(
        'error callback: %s %s',
        curl_error($ch), json_encode(curl_getinfo($ch))
      );
      echo "! {$requestObj['uri']} {$foxtrap->getUriDownloaded()}/{$foxtrap->getUriTotal()} mem {$mem}K id {$requestObj['id']} {$error}\n";
      $foxtrap->getDb()->saveError($error, $requestObj['id']);
    };
  }

  /**
   * Return a CurlyQueue handler for queue completion.
   *
   * @return Closure
   */
  public function onDownloadEnd()
  {
    return function () {
      echo "Done\n";
    };
  }

  /**
   * Convert a database row (as assoc. array) to an object (e.g. for JSONP).
   *
   * @param array $row
   * @return object
   * - mixed 'id'
   * - string 'indexed' <title> <uri> <tags> <purified html>
   * - string 'title'
   * - string 'domain'
   * - string 'tags' Space delimited tag list
   * - string 'uri'
   */
  public function dbRowToObj(array $row)
  {
    $matches = array();
    preg_match('/https?:\/\/([^\/]+)/', $row['uri'], $matches);
    $domain = isset($matches[1]) ? $matches[1] : '';

    return (object) array(
      'id' => $row['id'],
      'indexed' =>
        $row['title']
        . " {$row['uri']}"
        . " {$row['tags']}"
        . $row['body_clean'],
      'title' => $row['title'],
      'domain' => $domain,
      'tags' => $row['tags'],
      'uri' => $row['uri']
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
   * @param array $req HTTP arguments.
   * @return string
   */
  public function jsonpCallback($json, array $req)
  {
    $callback = empty($req['callback']) ? 'callback' : $req['callback'];
    return "{$callback}({$json});";
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
   * Read access to $this->uriTotal.
   *
   * @return int
   */
  public function getUriTotal()
  {
    return $this->uriTotal;
  }

  /**
   * Read access to $this->uriDownloaded.
   *
   * @return int
   */
  public function getUriDownloaded()
  {
    return $this->uriDownloaded;
  }

  /**
   * Increment $this->uriDownloaded.
   *
   * @return void
   */
  public function incrUriDownloaded()
  {
    $this->uriDownloaded++;
  }
}
