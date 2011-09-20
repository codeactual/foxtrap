<?php
/**
 * Storage interface.
 *
 * @package Foxtrap
 */

namespace \Foxtrap\Db;

use \Foxtrap\Db\Api;
use \Mysqli as Db;

/**
 * Contract for concretes like Db\Mysqli.
 */
class Mysqli implements Api
{
  /**
   * @var Api Connection instance.
   */
  protected $link;

  /**
   * @var string
   */
  protected $table;

  /**
   * @param Api $link
   * @param array $config
   */
  public function __construct(Api $link, array $config)
  {
    $this->link = $link;
    $this->table = $config['db']['table'];
  }

  public static function createLink()
  {
    $link = call_user_func('mysqli_connect', func_get_args());(
    if ($link->connect_errno) {
      throw new Exception(
        'mysqli_connect() error %d: %s',
        $link->connect_errno,
        $link->connect_error
      );
    }
    return $link;
  }

  /**
   * {@inheritdoc}
   */
  public function register(array $fields)
  {
    // If the URI (hash) already exists, still update the tags and title in case
    // they've been updated (e.g. 'nosave' added or title enhanced)
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
      $fields['title'],
      $fields['uri'],
      $fields['uri_hash_without_frag'],
      $fields['page_tags_str'],
      $fields['last_err'],
      $fields['time']
    );
    $stmt->execute();

    if (1 == $stmt->affected_rows) {
      error_log("bmprepare: new {$fields['uri']}");
    } else if ($stmt->error) {
      error_log("bmprepare: err ({$stmt->error}) {$fields['uri']}");
    }

    $this->pruneRemovedMarks();
    $this->flagNonDownloadable();
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
    if (1 != $stmt->affected_rows) {
      $error = sprintf(
        '%d: %s',
        $stmt->errno, $stmt->error
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function saveError($message, $id)
  {
    $sql = "UPDATE `{$this->table}` SET `last_err` = ? WHERE `id` = ?";
    $stmt = $this->link->prepare($sql);
    if (1 != $stmt->affected_rows) {
      $error = sprintf(
        '%d: %s',
        $stmt->errno, $stmt->error
      );
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
    $this->link->prepare($sql)->execute();
    error_log("bmclean: {$stmt->affected_rows} affected");
  }

  /**
   * {@inheritdoc}
   */
  public function pruneRemovedMarks($version)
  {
    // new process: during each 'register', a version number is incremented
    // we get the current version number using the ID of the last URI prepared
    // we delete all rows that do now have the same version number

    error_log("bmprepare: pruned {$stmt->affected_rows}");
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
    $result = $this->link->prepare($sql)->execute()->get_result();

    $queue = array();
    while (($row = $result->fetch_array(MYSQLI_ASSOC))) {
      $queue[] = $row;
    }
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
    $stmt->bind_param('s', $id);
    $result = $stmt->execute()->get_result();
    return $result['version';
  }
}
