<?php

namespace TestData;

use \Foxtrap\Db\Api;

/**
 * @param array $override Key/value pairs to override random selection.
 * @return array Expected field names and values of the created row.
 */
function registerRandomMark(Api $db, array $overrides = array())
{
  $expected = array(
    'title' => uniqid(),
    'uri' => uniqid(),
    'uri_hash' => uniqid(),
    'tags' => uniqid(),
    'last_err' => '',
    'modified' => time() - mt_rand(1, 3600),
    'version' => time()
  );
  $expected = array_merge($expected, $overrides);
  $db->register($expected);
  return $expected;
}