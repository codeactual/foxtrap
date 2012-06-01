<?php

use \Foxtrap\Factory;
use \Foxtrap\Query;

class QueryTest extends PHPUnit_Framework_TestCase
{
  protected static $foxtrap;
  protected static $query;

  public static function setUpBeforeClass()
  {
    $factory = new Factory();
    $config = $factory->getConfigFromFile();
    self::$foxtrap = $factory->createTestInstance();
    self::$query = self::$foxtrap->getQuery();

    $db = self::$foxtrap->getDb();
    $db->resetTestDb();
    $json = file_get_contents(__DIR__ . '/../fixture/bookmarks.json');
    self::$foxtrap->registerMarks(self::$foxtrap->jsonToArray($json));

    $body = file_get_contents(__DIR__ . '/../fixture/twitter.html');
    $db->saveSuccess($body, self::$foxtrap->cleanResponseBody($body), 1);
    $body = file_get_contents(__DIR__ . '/../fixture/yahoo.html');
    $db->saveSuccess($body, self::$foxtrap->cleanResponseBody($body), 2);
    $body = file_get_contents(__DIR__ . '/../fixture/amazon.html');
    $db->saveSuccess($body, self::$foxtrap->cleanResponseBody($body), 3);
    $body = file_get_contents(__DIR__ . '/../fixture/google.html');
    $db->saveSuccess($body, self::$foxtrap->cleanResponseBody($body), 4);

    exec(__DIR__ . '/../../bin/foxtrap-test-indexer');
  }

  /**
   * @group convertsDbRowToObj
   * @test
   */
  public function convertsDbRowToObj()
  {
    $row = array(
      'id' => uniqid(),
      'downloaded' => false,
      'title' => uniqid(),
      'tags' => uniqid(),
      'body_clean' => uniqid(),
      'uri' => 'https://twitter.com/#!/user/status/1234'
    );
    $obj = self::$query->dbRowToObj($row);
    $this->assertSame($row['id'], $obj->id);
    $this->assertSame(
      $row['title']
      . " {$row['uri']}"
      . " {$row['tags']}"
      . $row['body_clean'],
      $obj->indexed
    );
    $this->assertSame($row['title'], $obj->title);
    $this->assertSame('twitter.com', $obj->domain);
    $this->assertSame($row['tags'], $obj->tags);
    $this->assertSame($row['uri'], $obj->uri);
  }

  /**
   * @group convertsSphinxModeNameToValue
   * @test
   */
  public function convertsSphinxModeNameToValue()
  {
    $this->assertSame(
      SPH_MATCH_BOOLEAN,
      self::$query->sphinxModeNameToValue('SPH_MATCH_BOOLEAN')
    );
    $this->assertSame(
      SPH_MATCH_ANY,
      self::$query->sphinxModeNameToValue('SPH_MATCH_ANY')
    );
    $this->assertSame(
      0,
      self::$query->sphinxModeNameToValue('SPH_DOES_NOT_EXIST')
    );
  }

  public function providesRunData()
  {
    return array(
      array(
        'search yahoo',
        SPH_MATCH_ANY,
        SPH_SORT_RELEVANCE,
        '',
        array('www.yahoo.com', 'www.google.com', 'www.amazon.com'),
      ),
      array(
        'search yahoo',
        SPH_MATCH_ALL,
        SPH_SORT_RELEVANCE,
        '',
        array('www.yahoo.com'),
      ),
      array(
        'web',
        SPH_MATCH_ANY,
        SPH_SORT_RELEVANCE,
        '',
        array('www.yahoo.com', 'www.amazon.com', 'www.google.com'),
      ),
      array(
        'web',
        SPH_MATCH_ANY,
        SPH_SORT_ATTR_ASC,
        'modified',
        array('www.google.com', 'www.yahoo.com', 'www.amazon.com'),
      ),
      array(
        'web',
        SPH_MATCH_ANY,
        SPH_SORT_ATTR_DESC,
        'modified',
        array('www.amazon.com', 'www.yahoo.com', 'www.google.com'),
      )
    );
  }

  /**
   * @group runsQuery
   * @test
   * @dataProvider providesRunData
   */
  public function runsQuery($q, $matchMode, $sortMode, $sortAttr, $expected)
  {
    $results = self::$query->run($q, $matchMode, $sortMode, $sortAttr);
    $actual = array();
    foreach ($results as $res) {
      $actual[] = $res->domain;
    }
    $this->assertSame($expected, $actual);
  }
}
