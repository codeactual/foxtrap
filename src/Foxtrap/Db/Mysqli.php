<?php
/**
 * Mysqli class.
 *
 * @package Foxtrap
 */

namespace \Foxtrap\Db;

use \Foxtrap\Db\Api;
use \Mysqli as Db;

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
   * @param mysqli $link
   * @param array $config
   */
  public function __construct(mysqli $link, array $config)
  {
    $this->link = $link;
    $this->table = $config['db']['table'];
  }

  /**
   * {@inheritdoc}
   */
  public static function createLink()
  {
    $link = call_user_func('mysqli_connect', func_get_args());
    if ($link->connect_error) {
      throw new Exception("{$link->connect_error} ({$link->connect_errno})");
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
        `modified`
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
        FROM_UNIXTIME(?)
      )
      ON DUPLICATE KEY UPDATE
        `tags` = VALUES(`tags`),
        `title` = VALUES(`title`)";

    $stmt = $this->link->prepare($sql);
    $stmt->bind_param(
      'sssssd',
      $mark['title'],
      $mark['uri'],
      $mark['uriHashWithoutFrag'],
      $mark['pageTagsStr'],
      $mark['lastErr'],
      $mark['time']
    );
    $stmt->execute();
    if ($stmt->error) {
      throw new Exception("{$mark['uri']}: {$stmt->error} ({$stmt->errno})");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function saveSuccess($raw, $clean, $id)
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
    $stmt->execute();
    if ($stmt->error) {
      throw new Exception("id {$id}: {$stmt->error} ({$stmt->errno})");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function saveError($message, $id)
  {
    $sql = "UPDATE `{$this->table}` SET `last_err` = ? WHERE `id` = ?";
    $stmt = $this->link->prepare($sql);
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
        (`last_err` = 'nosave' OR `tags` LIKE '%nosave%')
        AND `body` != ''";

    $stmt = $this->link->prepare($sql);
    $stmt->execute();
    if ($stmt->error) {
      throw new Exception("{$stmt->error} ({$stmt->errno})");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function pruneRemovedMarks($version)
  {
    $sql = "
      UPDATE `{$this->table}`
      SET
        `body` = '',
        `body_clean` = '',
        `saved` = 0,
        `last_err` = 'nosave'
      WHERE
        (`last_err` = 'nosave' OR `tags` LIKE '%nosave%')
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
  public function getMarkVersion($id)
  {
    $sql = "
      SELECT `version`
      FROM `{$this->table}`
      WHERE `id` = ?";

    $stmt = $this->link->prepare($sql);
    $stmt->bind_param('d', $id);
    $stmt->execute();
    if ($stmt->error) {
      throw new Exception("id {$id}: {$stmt->error} ({$stmt->errno})");
    }

    $result = $stmt->get_result();
    return $result['version'];
  }
}
