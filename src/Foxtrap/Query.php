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
   * @var array Non-connection fields under 'sphinx' from config.php
   */
  protected $config;

  /**
   * @var string
   */
  protected $index;

  public function __construct(SphinxClient $cl, DbApi $db, $index, array $config)
  {
    $this->cl = $cl;
    $this->db = $db;
    $this->config = $config;
    $this->index = $index;

    $this->cl->SetFieldWeights($this->config['weights']);
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
   * @param string $sortAttr (Optional, '') Sphinx sort-by attribute.
   * - Only optional for SPH_SORT_RELEVANCE
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
  public function run($q, $match, $sortMode, $sortAttr = '')
  {
    // In Sphinx sort order
    $docs = array();

    $this->cl->SetMatchMode($match);
    $this->cl->SetSortMode($sortMode, $sortAttr);

    $results = $this->cl->Query($q, $this->index);

    $lastError = $this->cl->GetLastError();
    if ($lastError) {
      error_log($lastError);
    }

    if (empty($results['matches'])) {
      return $docs;
    }

    // Use this index to keep getMarksForSearch() and BuildExcerpts()
    // results in Sphinx sort order.
    $rankToId = array();
    foreach ($results['matches'] as $id => $prop) {
      $rankToId[] = $id;
    }
    $idToRank = array_flip($rankToId);

    $marks = $this->db->getMarksForSearch($rankToId);
    foreach ($marks as $id => $mark) {
      $docs[$idToRank[$id]] = $this->dbRowToObj($mark);
    }

    // getMarksForSearch() may not return results in sort order.
    // Reindex here to prevent run() clients from using foreach()
    // and erronously relying on array insertion order.
    ksort($docs);

    // Collect excerpt raw material from 'indexed' property augmented
    // above in dbRowToObj(). Use $rankToId so that BuildExcerpts()
    // returns results in original sort order.
    $docBodies = array();
    foreach ($rankToId as $id) {
      $docBodies[] = $docs[$idToRank[$id]]->indexed;
      unset($docs[$idToRank[$id]]->indexed);
    }

    $res = $this->cl->BuildExcerpts(
      $docBodies,
      $this->index,
      $q,
      $this->config['excerpts']
    );
    if ($res) {
      foreach ($res as $rank => $r) {
        $docs[$rank]->excerpt = $r;
      }
    } else {
      error_log($this->cl->GetLastError());
    }

    return $docs;
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
