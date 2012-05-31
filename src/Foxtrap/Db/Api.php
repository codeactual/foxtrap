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
   * @return int Inserted/modified ID.
   * @throws Exception
   * - on write error
   */
  public function register(array $mark);

  /**
   * Update a URI's related fields, e.g. raw content and tags.
   *
   * @param string $raw Unsanitized HTML.
   * @param string $clean Ssanitized HTML.
   * @param int $id
   * @return void
   * @throws Exception
   * - on write error
   */
  public function saveSuccess($raw, $clean, $id);

  /**
   * Update a URI's error state.
   *
   * @param string $message
   * @param int $id
   * @return void
   * @throws Exception
   * - on write error
   */
  public function saveError($message, $id);

  /**
   * Remove a URI's error state.
   *
   * @param int $id
   * @return boolean True on success.
   * @throws Exception
   * - on write error
   */
  public function removeError($id);

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

  /**
   * Add a query to the search history. If exists, update counter.
   *
   * @param string $q
   * @return void
   * @throws Exception
   * - on write error
   */
  public function addHistory($q);

  /**
   * Read the most recent/popular N searches.
   *
   * @param int $limit
   * @return void
   * @throws Exception
   * - on read error
   */
  public function getHistory($limit);

  /**
   * Read the most recent N errors.
   *
   * @param int $limit
   * @return void
   * @throws Exception
   * - on read error
   */
  public function getErrorLog($limit);

  /**
   * Delete marks by ID.
   *
   * @return array IDs.
   * @throws Exception
   * - on write error
   */
  public function deleteMarksById(array $uri);
}
