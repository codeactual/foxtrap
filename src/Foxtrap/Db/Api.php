<?php
/**
 * Api interface.
 *
 * @package Foxtrap
 */

namespace Foxtrap\Db;

/**
 * Contract for concretes like Db\Mysqli which further describes requirements.
 */
interface Api
{
  /**
   * Connection link factory.
   *
   * @return mixed Ex. mysqli instance.
   */
  public static function createLink();

  /**
   * Add or refresh URIs and related fields based on bookmarks in source
   * source JSON from Firefox.
   *
   * @param array $mark
   * - string 'title'
   * - string 'uri'
   * - string 'uri_hash'
   * - string 'tags'
   * - string 'last_err'
   * - string 'modified'
   * - int 'version'
   * @return void
   * @throws Exception
   * - on write error
   */
  public function register(array $mark);

  /**
   * Update a URI's related fields, e.g. raw content and tags.
   *
   * @return void
   * @throws Exception
   * - on write error
   */
  public function saveSuccess($raw, $clean, $id);

  /**
   * Update a URI's error state.
   *
   * @return void
   * @throws Exception
   * - on write error
   */
  public function saveError($message, $id);

  /**
   * Clear a URI's content fields and prevent future downloads, e.g. for
   * bookmarks where only titles and tags should be indexed.
   *
   * @return int Marks flagged.
   * @throws Exception
   * - on write error
   */
  public function flagNonDownloadable();

  /**
   * Remove URIs and related fields based on bookmarks which are no longer
   * in the source JSON from Firefox.
   *
   * @param int $version Latest import version ID (timestamp).
   * @return int Marks removed.
   * @throws Exception
   * - on non-positive version number
   * - on write error
   */
  public function pruneRemovedMarks($version);

  /**
   * Get all bookmarks awaiting download.
   *
   * @return array An array for each mark with:
   * - mixed 'id'
   * - string 'uri'
   * @throws Exception
   * - on read error
   */
  public function getMarksToDownload();

  /**
   * Get URIs and related fields of bookmarks awaiting download.
   *
   * @return array An array for each mark with:
   * - mixed 'id'
   * - string 'uri'
   * - string 'uri_hash'
   * - string 'tags'
   * - string 'body'
   * - string 'body_clean'
   * - int 'modified'
   * - int 'saved'
   * - string 'last_err'
   * - int 'version'
   * @throws Exception
   * - on read error
   */
  public function getMarkById($id);

  /**
   * Enable tests to work with predictable ID numbers and generally avoid
   * cross-test states.
   *
   * @return void
   */
  public function resetTestDb();

  /**
   * Return fields of identified documents search results/excerpts.
   *
   * @param array $ids Row IDs.
   * @return array Result arrays with:
   * - mixed 'id'
   * - string 'title'
   * - string 'body_clean'
   * - string 'uri'
   * - string 'tags'
   * - int 'modified'
   */
  public function getMarksForSearch(array $ids);
}
