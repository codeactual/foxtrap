<?php

namespace Foxtrap\Db;

/**
 * Api interface.
 *
 * @package Foxtrap
 */

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
   * - string 'uriHashWithoutFrag'
   * - string 'pageTagsStr'
   * - string 'lastErr'
   * - string 'time'
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
   * @return void
   * @throws Exception
   * - on write error
   */
  public function flagNonDownloadable();

  /**
   * Remove URIs and related fields based on bookmarks which are no longer
   * in the source JSON from Firefox.
   *
   * @param int $version Latest import version.
   * @return int Rows removed.
   * @throws Exception
   * - on non-positive version number
   * - on write error
   */
  public function pruneRemovedMarks($version);

  /**
   * Get URIs and related fields of bookmarks awaiting download.
   *
   * @return array An array for each mark with:
   * - mixed 'id'
   * - string 'uri'
   * @throws Exception
   * - on read error
   */
  public function getMarksToDownload();

  /**
   * Get the 'version' field of the identified mark.
   *
   * @param mixed $id
   * @return mixed
   * @throws Exception
   * - on read error
   */
  public function getMarkVersion($id);
}
