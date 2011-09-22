<?php
/**
 * Mysqli class.
 *
 * @package Foxtrap
 */

namespace Foxtrap\Db;

use \Exception;
use \Foxtrap\Db\Api;
use \mysqli as DbLink;

require_once __DIR__ . '/Api.php';

/**
 *  MySQLi DB API implementation.
 */
class Mysqli implements Api
{
  /**
   * @var mysqli Connection instance.
   */
  protected $link;

  /**
   * @var string
   */
  protected $table;

  /**
   * @var string
   */
  protected $testDbName;

  /**
   * @param mysqli $link
   * @param array $config
   */
  public function __construct(DbLink $link, array $config)
  {
    $this->link = $link;
    $this->table = $config['db']['table'];
    $this->testDbName = $config['db']['testOpts'][3] ?: '';
  }

  /**
   * Read access to $this->table.
   *
   * @return string
   */
  public function getTable()
  {
    return $this->table;
  }

  /**
   * {@inheritdoc}
   */
  public static function createLink()
  {
    $link = @call_user_func_array('mysqli_connect', func_get_args());
    if (($error = mysqli_connect_error())) {
      $errno = mysqli_connect_errno();
      throw new Exception("{$error} ({$errno})");
    }
    return $link;
  }

  /**
   * {@inheritdoc}
   */
  public function register(array $mark)
  {
    // Add the URI for the first time OR update its tags/title
    // (ex. 'nosave' tag added).
    $sql = "
      INSERT INTO `{$this->table}`
      (
        `title`,
        `uri`,
        `uri_hash`,
        `tags`,
        `body`,
        `body_clean`,
        `last_err`,
        `modified`,
        `version`
      )
      VALUES
      (
        ?,
        ?,
        ?,
        ?,
        '',
        '',
        ?,
        FROM_UNIXTIME(?),
        ?
      )
      ON DUPLICATE KEY UPDATE
        `version` = VALUES(`version`),
        `tags` = VALUES(`tags`),
        `title` = VALUES(`title`)";

    $stmt = $this->link->prepare($sql);
    $stmt->bind_param(
      'sssssdd',
      $mark['title'],
      $mark['uri'],
      $mark['uri_hash'],
      $mark['tags'],
      $mark['last_err'],
      $mark['modified'],
      $mark['version']
    );
    $stmt->execute();
    if ($stmt->error) {
      throw new Exception("{$mark['uri']}: {$stmt->error} ({$stmt->errno})");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function saveSuccess($body, $bodyClean, $id)
  {
    $sql = "
      UPDATE `{$this->table}`
      SET
        `body` = ?,
        `body_clean` = ?,
        `saved` = 1,
        `last_err` = ''
      WHERE `id` = ?";

    $stmt = $this->link->prepare($sql);
    $stmt->bind_param('ssd', $body, $bodyClean, $id);
    $stmt->execute();
    if ($stmt->error) {
      throw new Exception("id {$id}: {$stmt->error} ({$stmt->errno})");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function saveError($lastErr, $id)
  {
    $sql = "UPDATE `{$this->table}` SET `last_err` = ? WHERE `id` = ?";
    $stmt = $this->link->prepare($sql);
    $stmt->bind_param('sd', $lastErr, $id);
    $stmt->execute();
    if ($stmt->error) {
      throw new Exception("id {$id}: {$stmt->error} ({$stmt->errno})");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function flagNonDownloadable()
  {
    $sql = "
      UPDATE `{$this->table}`
      SET
        `body` = '',
        `body_clean` = '',
        `saved` = 0,
        `last_err` = 'nosave'
      WHERE
        `tags` LIKE '%nosave%'
        AND `body` != ''";

    $stmt = $this->link->prepare($sql);
    $stmt->execute();
    if ($stmt->error) {
      throw new Exception("{$stmt->error} ({$stmt->errno})");
    }

    return $stmt->affected_rows;
  }

  /**
   * {@inheritdoc}
   */
  public function pruneRemovedMarks($version)
  {
    $sql = "
      DELETE FROM `{$this->table}`
      WHERE `version` < ?";

    $stmt = $this->link->prepare($sql);
    $stmt->bind_param('d', $version);
    $stmt->execute();
    if ($stmt->error) {
      throw new Exception("{$stmt->error} ({$stmt->errno})");
    }

    return $stmt->affected_rows;
  }

  /**
   * {@inheritdoc}
   */
  public function getMarksToDownload()
  {
    $sql = "
      SELECT
        `id`, `uri`
      FROM `{$this->table}`
      WHERE
        `saved` = 0
        AND `last_err` = ''";

    $stmt = $this->link->prepare($sql);
    $stmt->execute();
    if ($stmt->error) {
      throw new Exception("{$stmt->error} ({$stmt->errno})");
    }

    $result = $stmt->get_result();
    $queue = array();
    while (($row = $result->fetch_array(MYSQLI_ASSOC))) {
      $queue[] = $row;
    }
    return $queue;
  }

  /**
   * {@inheritdoc}
   */
  public function getMarkById($id)
  {
    $sql = "
      SELECT *
      FROM `{$this->table}`
      WHERE `id` = ?";

    $stmt = $this->link->prepare($sql);
    $stmt->bind_param('d', $id);
    $stmt->execute();
    if ($stmt->error) {
      throw new Exception("{$stmt->error} ({$stmt->errno})");
    }

    $result = $stmt->get_result();
    return $result->fetch_array(MYSQLI_ASSOC);
  }

  /**
   * {@inheritdoc}
   */
  public function resetTestDb()
  {
    if ($this->testDbName) {
      $this->link->query("TRUNCATE `{$this->testDbName}`.`{$this->table}`");
    }
  }
}
