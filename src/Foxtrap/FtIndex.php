<?php
/**
 * Sphinx real-time index interface.
 *
 * @package Foxtrap
 */

namespace Foxtrap;

use \Exception;
use \Foxtrap\Db\Api as DbApi;
use \Foxtrap\Log\Api as LogApi;

class FtIndex
{
  /**
   * @var DbApi Implementation interface, e.g. Db\Mysqli.
   */
  protected $db;

  /**
   * @var DbApi Implementation interface, e.g. Db\Mysqli.
   */
  protected $ftDb;

  /**
   * @var string DB table name.
   */
  protected $table;

  /**
   * @var string RT index name.
   */
  protected $index;

  public function __construct(DbApi $db, $table, DbApi $ftDb, $index)
  {
    $this->db = $db;
    $this->table = $table;

    $this->ftDb = $ftDb;
    $this->index = $index;
  }

  public function deleteById($id)
  {
    $replaceSql = sprintf('DELETE FROM `%s` WHERE id = %d', $this->index, intval($id));

    if (!$this->ftDb->query($replaceSql)) {
      throw new Exception("deleteById() failed: {$id}: " . $this->db->getError());
    }
  }

  public function updateById($id)
  {
    $mark = $this->db->getMarkById($id);

    $replaceSql = sprintf("
      REPLACE INTO `%s`
      (`id`, `title`, `uri`, `tags`, `body_clean`, `downloaded`)
      VALUES
      (%d, '%s', '%s', '%s', '%s', %d)",
      $this->index,
      $mark['id'],
      $this->db->escape($mark['title']),
      $this->db->escape($mark['uri']),
      $this->db->escape($mark['tags']),
      $this->db->escape($mark['body_clean']),
      $mark['downloaded']
    );

    if (!$this->ftDb->query($replaceSql)) {
      throw new Exception("updateById() failed: {$id}: " . $this->db->getError());
    }
  }

  public function updateFromMark($mark)
  {
    $replaceSql = sprintf("
      REPLACE INTO `%s`
      (`id`, `title`, `uri`, `tags`, `body_clean`, `downloaded`)
      VALUES
      (%d, '%s', '%s', '%s', '%s', %d)",
      $this->index,
      $mark['id'],
      $this->db->escape($mark['title']),
      $this->db->escape($mark['uri']),
      $this->db->escape($mark['tags']),
      $this->db->escape($mark['body_clean']),
      $mark['downloaded']
    );

    if (!$this->ftDb->query($replaceSql)) {
      throw new Exception('updateByDoc() failed');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function seed()
  {
    $selectSql = "
      SELECT `id`, `title`, `uri`, `tags`, `body_clean`, `downloaded`
      FROM `{$this->table}`";

    $result = $this->db->query($selectSql);
    if ($result) {
      while ($mark = $result->fetch_array(MYSQLI_ASSOC)) {
        $this->updateFromMark($mark);
      }
    }
  }
}
