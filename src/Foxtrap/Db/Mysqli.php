<?php
/**
 * Storage interface.
 *
 * @package Foxtrap
 */

namespace \Foxtrap\Db;

use \Foxtrap\Db\DbInterface;
use \Mysqli as Db;

/**
 * Contract for concretes like Db\Mysqli.
 */
class Mysqli implements DbInterface
{
  /**
   * @var Db Connection instance.
   */
  protected $link;

  public function __construct(Db $link = null)
  {
    if ($link) {
      $this->link = $link;
    } else {
      $this->link = $this->createLink($config);
    }
  }

  public static function createLink()
  {
    //$link = mysqli_connec('localhost', 'rw', 'rw', 'stash');
    $link = call_user_func('mysqli_connect', func_get_args());(
    if ($link->connect_errno) {
      throw new Exception(
        'mysqli_connect() error %d: %s',
        $mysqli->connect_errno,
        $mysqli->connect_error
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
    $sql = 'INSERT INTO `stash`.`bmsave` (`title`, `uri`, `uri_hash`, `tags`, `body`, `body_clean`, `last_err`, `modified`) VALUES (?, ?, ?, ?, "", "", ?, FROM_UNIXTIME(?)) ON DUPLICATE KEY UPDATE `tags` = VALUES(`tags`), `title` = VALUES(`title`)';
    $stmt = $this->link->prepare($sql);

    $stmt->bind_param(
      'sssssd',
      $fields['title'], $fields['uri'], $fields['uri_hash_without_frag'], $fields['page_tags_str'], $fields['last_err'], $fields['time']
    );
    $stmt->execute();

    if (1 == $stmt->affected_rows) {
      error_log("bmprepare: new {$fields['uri']}");
    } else if ($stmt->error) {
      error_log("bmprepare: err ({$stmt->error}) {$fields['uri']}");
    }

    //
    $this->stopContentSave();
  }

  /**
   * {@inheritdoc}
   */
  public function saveSuccess($raw, $clean, $id)
  {
$sql = 'UPDATE `stash`.`bmsave` SET `body` = ?, `body_clean` = ?,`saved` = 1, `last_err` = "" WHERE `id` = ?';
$success_stmt = $mysqli->prepare($sql);
          if (1 != $success_stmt->affected_rows) {
            $error = sprintf(
              '%d: %s',
              $success_stmt->errno, $success_stmt->error
            );
          }
  }

  /**
   * {@inheritdoc}
   */
  public function saveError($message, $id)
  {
$sql = 'UPDATE `stash`.`bmsave` SET `last_err` = ? WHERE `id` = ?';
$error_stmt = $mysqli->prepare($sql);
          if (1 != $success_stmt->affected_rows) {
            $error = sprintf(
              '%d: %s',
              $success_stmt->errno, $success_stmt->error
            );
          }
  }

  /**
   * {@inheritdoc}
   */
  protected function stopContentSave() // bmclean
  {
    $sql = '
      UPDATE `bmsave`
      SET
        `body` = "",
        `body_clean` = "",
        `saved` = 0,
        `last_err` = "nosave"
      WHERE
        (`last_err` = "nosave" OR `tags` LIKE "%nosave%")
        AND `body` != ""';
    $this->link->prepare($sql)->execute();
    error_log("bmclean: {$stmt->affected_rows} affected"); // TODO REMOVE
  }

  /**
   * {@inheritdoc}
   */
  public function unregister($version)
  {
    // new process: during each 'register', a version number is incremented
    // we get the current version number using the ID of the last URI prepared
    // we delete all rows that do now have the same version number

    error_log("bmprepare: pruned {$stmt->affected_rows}"); // TODO REMOVE
  }

  /**
   * {@inheritdoc}
   */
  public function getDownloadQueue()
  {
    $sql = '
      SELECT
        `id`, `uri`
      FROM `stash`.`bmsave`
      WHERE
        `saved` = 0
        AND `last_err` = ""';
    return $this->link->prepare($sql)->execute()->get_result();
  }
}
