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
  protected $historyTable;

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
    $this->testDbName = $config['db']['testConnect'][3] ?: '';
    $this->historyTable = $config['db']['historyTable'];
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
  public function query($sql)
  {
    return $this->link->query($sql);
  }

  /**
   * {@inheritdoc}
   */
  public function escape($str)
  {
    return $this->link->real_escape_string($str);
  }

  /**
   * {@inheritdoc}
   */
  public function getError()
  {
    return "{$this->link->error} ({$this->link->errno})";
  }

  /**
   * {@inheritdoc}
   */
  public function start()
  {
    if (!$this->link->query('START TRANSACTION')) {
      throw new Exception('Could not start transaction');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function register(array $mark)
  {
    $this->start();

    // Add the URI for the first time OR update its tags/title
    // (ex. 'nosave' tag added).
    $sql = "
      INSERT INTO `{$this->table}`
      (
        `title`,
        `uri`,
        `hash`,
        `tags`,
        `body`,
        `body_clean`,
        `last_err`,
        `modified`,
        `added`,
        `deleted`
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
        ?,
        ?,
        0
      )
      ON DUPLICATE KEY UPDATE
        `tags` = VALUES(`tags`),
        `title` = VALUES(`title`)";

    $stmt = $this->link->prepare($sql);

    $mark['title'] = utf8_encode($mark['title']);
    $mark['uri'] = utf8_encode($mark['uri']);
    $mark['tags'] = utf8_encode($mark['tags']);

    $stmt->bind_param(
      'sssssdd',
      $mark['title'],
      $mark['uri'],
      $mark['hash'],
      $mark['tags'],
      $mark['last_err'],
      $mark['modified'],
      $mark['added']
    );
    $stmt->execute();
    if ($stmt->error) {
      $this->link->rollback();
      throw new Exception("{$mark['uri']}: {$stmt->error} ({$stmt->errno})");
    }

    $insertId = $stmt->insert_id;

    if ($insertId) {
      $tags = array_diff(explode(' ', $mark['tags']), ['']);
      if ($tags) {
        $sql = "
          INSERT INTO `tags`
          (`name`, `uses`)
          VALUES ('" . implode("', 1),('", $tags) . "', 1)
          ON DUPLICATE KEY UPDATE `uses` = `uses` + 1";
        $stmt = $this->link->prepare($sql);
        $stmt->execute();
        if ($stmt->error) {
          $this->link->rollback();
          throw new Exception("{$q}: {$stmt->error} ({$stmt->errno})");
        }
      }
    }

    $this->link->commit();

    return $insertId;
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
        `downloaded` = UNIX_TIMESTAMP(),
        `last_err` = ''
      WHERE `id` = ?";

    $stmt = $this->link->prepare($sql);
    $body = utf8_encode($body);
    $bodyClean = utf8_encode($bodyClean);
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
    $lastErr = utf8_encode($lastErr);
    $stmt->bind_param('sd', $lastErr, $id);
    $stmt->execute();
    if ($stmt->error) {
      throw new Exception("id {$id}: {$stmt->error} ({$stmt->errno})");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function removeError($id)
  {
    $sql = "UPDATE `{$this->table}` SET `last_err` = '' WHERE `id` = ?";
    $stmt = $this->link->prepare($sql);
    $stmt->bind_param('d', $id);
    $stmt->execute();
    if ($stmt->error) {
      throw new Exception("id {$id}: {$stmt->error} ({$stmt->errno})");
    }
    return $stmt->affected_rows == 1;
  }

  /**
   * {@inheritdoc}
   */
  public function flagForReDownload($id)
  {
    $sql = "UPDATE `{$this->table}` SET `downloaded` = 0, `last_err` = '' WHERE `id` = ?";
    $stmt = $this->link->prepare($sql);
    $stmt->bind_param('d', $id);
    $stmt->execute();
    if ($stmt->error) {
      throw new Exception("id {$id}: {$stmt->error} ({$stmt->errno})");
    }
    return $stmt->affected_rows == 1;
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
        `downloaded` = 0,
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
  public function getMarksCount()
  {
    $sql = "
      SELECT COUNT(*) AS `count`
      FROM `{$this->table}`";

    $stmt = $this->link->prepare($sql);
    $stmt->execute();
    if ($stmt->error) {
      throw new Exception("{$stmt->error} ({$stmt->errno})");
    }

    $result = $stmt->get_result();
    return $result->fetch_array(MYSQLI_ASSOC)['count'];
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
        `downloaded` = 0
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
    $mark = $result->fetch_array(MYSQLI_ASSOC);
    if ($mark['id']) {
      $mark['uri'] = utf8_decode($mark['uri']);
      $mark['title'] = utf8_decode($mark['title']);
      $mark['tags'] = utf8_decode($mark['tags']);
      $mark['body'] = utf8_decode($mark['body']);
      $mark['body_clean'] = utf8_decode($mark['body_clean']);
    }
    return $mark;
  }

  /**
   * {@inheritdoc}
   */
  public function getMarkIdByHash($hash)
  {
    $sql = "
      SELECT `id`
      FROM `{$this->table}`
      WHERE `hash` = ?";

    $stmt = $this->link->prepare($sql);
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    if ($stmt->error) {
      throw new Exception("{$stmt->error} ({$stmt->errno})");
    }

    $result = $stmt->get_result();
    $mark = $result->fetch_array(MYSQLI_ASSOC);
    return $mark['id'];
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

  /**
   * {@inheritdoc}
   */
  public function getMarksForSearch(array $ids)
  {
    $marks = array();
    $sql = "
      SELECT `id`, `title`, `body_clean`, `uri`, `tags`, `modified`, `downloaded`, `deleted`
      FROM `{$this->table}`
      WHERE `id` IN(" . implode(',', $ids) . ')';

    $result = $this->link->query($sql);
    if ($result) {
      while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
        $row['title'] = utf8_decode($row['title']);
        $row['tags'] = utf8_decode($row['tags']);
        $row['body_clean'] = utf8_decode($row['body_clean']);
        $marks[$row['id']] = $row;
      }
    }

    return $marks;
  }

  /**
   * {@inheritdoc}
   */
  public function getMarkHashes()
  {
    $marks = array();
    $sql = "
      SELECT `id`, `hash`
      FROM `{$this->table}`";

    $result = $this->link->query($sql);
    if ($result) {
      while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
        $marks[$row['hash']] = $row['id'];
      }
    }

    return $marks;
  }

  /**
   * {@inheritdoc}
   */
  public function addHistory($q)
  {
    $q = utf8_encode($q);
    $sql = "
      INSERT INTO `{$this->historyTable}`
      (`query`, `query_hash`)
      VALUES (?, ?)
      ON DUPLICATE KEY UPDATE `uses` = `uses` + 1";
    $stmt = $this->link->prepare($sql);
    $hash = md5($q);
    $stmt->bind_param('ss', $q, $hash);
    $stmt->execute();
    if ($stmt->error) {
      throw new Exception("{$q}: {$stmt->error} ({$stmt->errno})");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getHistory($limit)
  {
    $sql = "
      SELECT `id`, `query`
      FROM `{$this->historyTable}`
      ORDER BY `modified` DESC, `uses` DESC
      LIMIT ?";
    $stmt = $this->link->prepare($sql);
    $stmt->bind_param('d', $limit);
    $stmt->execute();
    if ($stmt->error) {
      throw new Exception("{$q}: {$stmt->error} ({$stmt->errno})");
    }

    $result = $stmt->get_result();
    $data = array();
    if ($result) {
      while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
        $data[] = (object) array(
          'id' => $row['id'],
          'query' => utf8_decode($row['query'])
        );
      }
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getErrorLog($limit)
  {
    $sql = "
      SELECT `id`, `last_err`, `title`, `uri`, `tags`, `deleted`
      FROM `{$this->table}`
      WHERE `last_err` NOT IN ('', 'nosave')
      ORDER BY `id` DESC
      LIMIT ?";
    $stmt = $this->link->prepare($sql);
    $stmt->bind_param('d', $limit);
    $stmt->execute();
    if ($stmt->error) {
      throw new Exception("{$q}: {$stmt->error} ({$stmt->errno})");
    }

    $result = $stmt->get_result();
    $data = array();
    if ($result) {
      while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
        $data[] = (object) array(
          'id' => $row['id'],
          'last_err' => utf8_decode($row['last_err']),
          'title' => utf8_decode($row['title']),
          'uri' => utf8_decode($row['uri']),
          'tags' => utf8_decode($row['tags']),
          'deleted' => $row['deleted']
        );
      }
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMarksById(array $ids)
  {
    $sql = "
      DELETE FROM `{$this->table}`
      WHERE `id` IN(" . implode(',', $ids) . ')';

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
  public function toggleDeletionFlag($id)
  {
    $sql = "UPDATE `{$this->table}` SET `deleted` = `deleted` XOR 1 WHERE `id` = ?";
    $stmt = $this->link->prepare($sql);
    $stmt->bind_param('d', $id);
    $stmt->execute();
    if ($stmt->error) {
      throw new Exception("id {$id}: {$stmt->error} ({$stmt->errno})");
    }
    return $stmt->affected_rows == 1;
  }

  /**
   * {@inheritdoc}
   */
  public function getMarksFlaggedForDeletion()
  {
    $sql = "
      SELECT `id` FROM `{$this->table}`
      WHERE `deleted` = 1";

    $stmt = $this->link->prepare($sql);
    $stmt->execute();
    if ($stmt->error) {
      throw new Exception("{$stmt->error} ({$stmt->errno})");
    }

    $ids = array();
    $result = $stmt->get_result();
    if ($result) {
      while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
        $ids[] = $row['id'];
      }
    }

    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function getTags($limit)
  {
    $sql = "
      SELECT `id`, `name`
      FROM `tags`
      ORDER BY `modified` DESC, `uses` DESC
      LIMIT ?";
    $stmt = $this->link->prepare($sql);
    $stmt->bind_param('d', $limit);
    $stmt->execute();
    if ($stmt->error) {
      throw new Exception("{$q}: {$stmt->error} ({$stmt->errno})");
    }

    $result = $stmt->get_result();
    $data = array();
    if ($result) {
      while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
        $data[] = (object) array(
          'id' => $row['id'],
          'name' => utf8_decode($row['name'])
        );
      }
    }
    return $data;
  }
}
