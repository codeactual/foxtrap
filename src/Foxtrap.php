<?php
/**
 * Foxtrap functions used by back- and front-end, or located here for unit tests.
 *
 * @package Foxtrap
 */

namespace Foxtrap;

use \CurlyQueue;
use \Foxtrap\Db\Api;
use \HTMLPurifier;

require_once __DIR__ . '/Foxtrap/Db/Api.php';
require_once __DIR__ . '/../vendor/curlyqueue/src/CurlyQueue.php';
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

      $foxtrap->uriDownloaded++;

      if (0 === $errno) {
        $info = curl_getinfo($ch);
        if (200 == $info['http_code'] && strlen($content)) {
          if (!mb_check_encoding($content, 'UTF-8')) {
            $content = utf8_encode($content);
          }
          $content_clean = preg_replace('/\s{2,}/', ' ', trim($content));
          $content_clean = $foxtrap->purifier->purify($content_clean);
          $foxtrap->db->saveSuccess($content, $content_clean, $requestObj['id']);
        } else {
          $error = json_encode($info);
        }
      } else {
        $error = curl_error($ch);
      }

      if ($error) {
        $foxtrap->db->saveError($error_stmt, $requestObj['id']);
        $error = $error ? "({$error})" : '';
      }

      $mem = memory_get_usage(true) / 1024;
      $symbol = $error ? '!' : '$';
      error_log("{$symbol} {$requestObj['uri']} {$foxtrap->uriDownloaded}/{$foxtrap->uriTotal} mem {$mem}K id {$requestObj['id']} {$error}");
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
      $foxtrap->uriDownloaded++;

      $mem = memory_get_usage(true) / 1024;
      $error = sprintf(
        'error callback: %s %s',
        curl_error($ch), json_encode(curl_getinfo($ch))
      );
      error_log("! {$requestObj['uri']} {$foxtrap->uriDownloaded}/{$foxtrap->uriTotal} mem {$mem}K id {$requestObj['id']} {$error}");
      $db->saveError($error, $requestObj['id']);
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
      error_log('bmsave: DONE');
    };
  }

  /**
   * Convert a database row (as assoc. array) to an object (e.g. for JSONP).
   *
   * @param array $row
   * @return object
   * - mixed 'id'
   * - string 'bodyClean' HTMLPurifier filtered page content
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

    $bodyClean =
      $row['title']
      . " {$row['uri']}"
      . " {$row['tags']}"
      . $row['body_clean'];

    return (object) array(
      'id' => $row['id'],
      'bodyClean' => $bodyClean,
      'title' => $row['title'],
      'domain' => $domain,
      'tags' => $row['tags'],
      'uri' => $row['uri']
    );
  }

  /**
   * Convert a Firefox JSON backup file to an array.
   *
   * @param string $filename
   * @return array
   * - array 'marks' Values are arrays w/ keys 'uri', 'title', 'lastmodified'
   * - array 'pageTags' Keys are URIs, values are arrays of tag titles
   */
  public function jsonFileToArray($filename)
  {
    $json = file_get_contents($filename);
    $assoc = json_decode($json, true);

    if (!array_key_exists('children', $assoc)) {
      exit("\nNo bookmark folders.\n");
    }

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

    return array('marks' => $unsorted['children'], 'pageTags' => $pageTags);
  }

  /**
   * Use output from jsonFileToArray() to add/update each URI's DB record.
   *
   * @param array $marks See jsonFileToArray().
   * @param array $pageTags See jsonFileToArray().
   * @return void
   */
  public function registerMarks(array $marks, array $pageTags)
  {
    foreach ($marks as $mark) {
      $time = (int) substr($mark['lastmodified'], 0, 10);
      $uriHash = md5($mark['uri']);
      if (isset($pageTags[$uriHash])) {
        $pageTagsStr = implode(' ', $pageTags[$uriHash]);
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
        $mark['title'],
        $mark['uri'],
        $uriHashWithoutFrag,
        $pageTagsStr,
        $lastErr,
        $time
      );
    }
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
}
