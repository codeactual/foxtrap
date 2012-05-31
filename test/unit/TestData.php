<?php

namespace TestData;

use \Foxtrap\Foxtrap;

/**
 * @param array $override Key/value pairs to override random selection.
 * @return array Expected field names and values of the created row.
 */
function registerRandomMark(Foxtrap $foxtrap, array $overrides = array())
{
  $uri = 'http://' . uniqid() . '.com/';
  $expected = array(
    'title' => 'Title ¥£€$¢₡₢₣₤₥₦₧₨₩₪₫₭₮₯₹',
    'uri' => $uri,
    'tags' => '¥£€$,¢₡₢₣,₤₥₦₧,₨₩₪₫,₭₮₯₹',
    'last_err' => '',
    'added' => time() - mt_rand(1, 3600),
    'modified' => time()
  );
  $expected['hash'] = $foxtrap->generateMarkHash($expected['title'], $expected['uri'], $expected['tags'], $expected['added']);
  $expected = array_merge($expected, $overrides);
  $expected['id'] = $foxtrap->getDb()->register($expected);
  return $expected;
}
