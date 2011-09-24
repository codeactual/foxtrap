<?php
/**
 * Query class.
 *
 * @package Foxtrap
 */

namespace Foxtrap;

use \Foxtrap\Db\Api as DbApi;
use \SphinxClient;

/**
 * Full-text query execution and history logging.
 */
class Query
{
  /**
   * @var SphinxClient
   */
  protected $cl;

  /**
   * @var \Foxtrap\Db\Api
   */
  protected $db;

  /**
   * @var array 'sphinx' element from config.php
   */
  protected $config;

  public function __construct(SphinxClient $cl, DbApi $db, array $config)
  {
    $this->cl = $cl;
    $this->db = $db;
    $this->config = $config;
  }

  /**
   * Convert a SPH_ constant name into its integer value.
   *
   * @param string $name
   * @return int
   */
  public function sphinxModeNameToValue($name)
  {
    if (0 === strpos($name, 'SPH_') && defined($name)) {
      return constant($name);
    } else {
      return 0;
    }
  }

  /**
   * Perform a query.
   *
   * @param string $q
   * @param int $match Sphinx match mode.
   * @param int $sortMode Sphinx sort mode.
   * @param string $sortAttr Sphinx sort-by attribute.
   * @return array Search result arrays, each with:
   * - mixed 'id'
   * - string 'uri'
   * - string 'tags'
   * - string 'domain'
   * - string 'tags'
   * - string 'excerpt'
   * @see http://www.php.net/manual/en/sphinxclient.setmatchmode.php
   * @see http://www.php.net/manual/en/sphinxclient.setsortmode.php
   */
  public function run($q, $match, $sortMode, $sortAttr)
  {
    $results = array();

    $this->cl->SetFieldWeights($this->config['weights']);
    $this->cl->SetMatchMode($match);

    $results = $this->cl->Query($q, $this->config['index']);
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
      $res = $this->cl->BuildExcerpts(
        $docBodies,
        $this->config['index'],
        $q,
        $this->config['excerpts']
      );
      if ($res) {
        $pos = 0;
        foreach ($res as $r) {
          $docs[$docIds[$pos++]]->excerpt = $r;
        }

        // Reindex starting at 0
        $results = array_merge($docs);
      } else {
        error_log($this->cl->GetLastError());
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
}
