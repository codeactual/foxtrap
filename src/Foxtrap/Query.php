<?php
/**
 * Query class.
 *
 * @package Foxtrap
 */

namespace Foxtrap;

use \Foxtrap\Db\Api;
use \SphinxClient;

require_once 'sphinx/sphinxapi.php';

/**
 * Full-text query execution and history logging.
 */
class Query
{
  /**
   * @var array 'sphinx' element from config.php
   */
  protected $config;

  /**
   * @var \Foxtrap\Db\Api
   */
  protected $db;

  public function __construct(Api $db, array $config)
  {
    $this->db = $db;
    $this->config = $config;
  }

  /**
   * Perform a query.
   *
   * @param string $q
   * @return array Search result arrays, each with:
   * - mixed 'id'
   * - string 'uri'
   * - string 'tags'
   * - string 'domain'
   * - string 'tags'
   * - string 'excerpt'
   */
  public function run($q)
  {
    $results = array();

    $cl = new SphinxClient();
    $cl->SetServer($this->config['host'], $this->config['port']);
    $cl->SetFieldWeights(
      array(
        'tags' => 40,
        'title' => 30,
        'uri' => 20,
        'body_clean' => 1
      )
    );
    // Detect match mode
    $mode = SPH_MATCH_ANY;
    $matches = array();
    if (preg_match('/^(ext|all|phrase|bool):/', $q, $matches)) {
      switch ($matches[1]) {
      case 'ext': $mode = SPH_MATCH_EXTENDED2; break;
      case 'all': $mode = SPH_MATCH_ALL; break;
      case 'phrase': $mode = SPH_MATCH_PHRASE; break;
      case 'bool': $mode = SPH_MATCH_BOOLEAN; break;
      }
      $q = str_replace($matches[1] . ':', '', $q);
    }
    $cl->SetMatchMode($mode);

    $results = $cl->Query($q, $this->config['index']);
    if (!empty($results['matches'])) {
      // Row properties from dbRowToObj() indexed by ID
      $docs = array();

      // Row IDs from matches
      $docIds = array();
      foreach ($results['matches'] as $id => $prop) {
        $docIds[] = $id;
      }

      $docs = $this->db->getMarksForSearch($docIds);
      foreach ($docs as $id => $doc) {
        $docs[$id] = $this->dbRowToObj($doc);
      }

      // Collect all doc bodies for building excerpts
      $docBodies = array();
      foreach ($docIds as $id) {
        $docBodies[] = $docs[$id]->indexed;
        unset($docs[$id]->indexed);
      }
      $res = $cl->BuildExcerpts(
        $docBodies, $this->config['index'], $q,
        array(
          'before_match' => '<span class="excerpt-word">',
          'after_match'	=> '</span>',
          'chunk_separator'	=> ' ... ',
          'limit'	=> 750,
          'around' => 10,
        )
      );
      if ($res) {
        $pos = 0;
        foreach ($res as $r) {
          $docs[$docIds[$pos++]]->excerpt = $r;
        }

        // Reindex starting at 0
        $results = array_merge($docs);
      } else {
        error_log($cl->GetLastError());
      }
    }

    return $results;
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
