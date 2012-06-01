<?php
/**
 * Blackhole class.
 *
 * @package Foxtrap
 */

namespace Foxtrap\Db;

use \stdClass;

/**
 * Empty DB API implementation for unit tests.
 */
class Blackhole implements Api
{
  /**
   * @var stdClass $link Saved from constructor for test inspection.
   */
  public $link;

  /**
   * @var array $config Saved from constructor for test inspection.
   */
  public $config;

  /**
   * @param stdClass $link
   * @param array $config
   */
  public function __construct(stdClass $link, array $config)
  {
    $this->link = $link;
    $this->config = $config;
  }

  /**
   * {@inheritdoc}
   */
  public static function createLink()
  {
    return (object) func_get_args();
  }

  /**
   * {@inheritdoc}
   */
  public function register(array $mark)
  {
  }

  /**
   * {@inheritdoc}
   */
  public function saveSuccess($raw, $clean, $id)
  {
  }

  /**
   * {@inheritdoc}
   */
  public function saveError($message, $id)
  {
  }

  /**
   * {@inheritdoc}
   */
  public function removeError($id)
  {
  }

  /**
   * {@inheritdoc}
   */
  public function flagForReDownload($id)
  {
  }

  /**
   * {@inheritdoc}
   */
  public function flagNonDownloadable()
  {
  }

  /**
   * {@inheritdoc}
   */
  public function getMarksToDownload()
  {
  }

  /**
   * {@inheritdoc}
   */
  public function getMarkById($id)
  {
  }

  /**
   * {@inheritdoc}
   */
  public function resetTestDb()
  {
  }

  /**
   * {@inheritdoc}
   */
  public function getMarksForSearch(array $ids)
  {
  }

  /**
   * {@inheritdoc}
   */
  public function addHistory($q)
  {
  }

  /**
   * {@inheritdoc}
   */
  public function getHistory($limit)
  {
  }

  /**
   * {@inheritdoc}
   */
  public function getErrorLog($limit)
  {
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMarksById(array $uri)
  {
  }
}
