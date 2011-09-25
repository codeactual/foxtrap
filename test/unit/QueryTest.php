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
  }

  public function setUp()
  {
    parent::setUp();
    self::$foxtrap->getDb()->resetTestDb();
  }

  /**
   * @group convertsDbRowToObj
   * @test
   */
  public function convertsDbRowToObj()
  {
    $row = array(
      'id' => uniqid(),
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

  /**
   * @group findsAny
   * @test
   */
  public function findsAny()
  {
    $json = file_get_contents(__DIR__ . '/../fixture/bookmarks.json');
    self::$foxtrap->registerMarks(self::$foxtrap->jsonToArray($json));

    $q = 'search yahoo';
    $results = self::$query->run($q, SPH_MATCH_ANY, SPH_SORT_RELEVANCE);

    $expected = array('www.yahoo.com', 'www.google.com', 'www.amazon.com');
    foreach ($results as $res) {
      $actual[] = $res->domain;
    }
    $this->assertSame($expected, $actual);
  }

  /**
   * @group findsAll
   * @test
   */
  public function findsAll()
  {
    $json = file_get_contents(__DIR__ . '/../fixture/bookmarks.json');
    self::$foxtrap->registerMarks(self::$foxtrap->jsonToArray($json));

    $q = 'search yahoo';
    $results = self::$query->run($q, SPH_MATCH_ALL, SPH_SORT_RELEVANCE);

    $expected = array('www.yahoo.com');
    foreach ($results as $res) {
      $actual[] = $res->domain;
    }
    $this->assertSame($expected, $actual);
  }

  /**
   * @group sortsByRelevance
   * @test
   */
  public function sortsByRelevance()
  {
    $json = file_get_contents(__DIR__ . '/../fixture/bookmarks.json');
    self::$foxtrap->registerMarks(self::$foxtrap->jsonToArray($json));

    $q = 'web';
    $results = self::$query->run($q, SPH_MATCH_ANY, SPH_SORT_RELEVANCE);

    $expected = array('www.yahoo.com', 'www.amazon.com');
    foreach ($results as $res) {
      $actual[] = $res->domain;
    }
    $this->assertSame($expected, $actual);
  }

  /**
   * @group sortsByModifiedAscending
   * @test
   */
  public function sortsByModifiedAscending()
  {
    $json = file_get_contents(__DIR__ . '/../fixture/bookmarks.json');
    self::$foxtrap->registerMarks(self::$foxtrap->jsonToArray($json));

    $q = 'web';
    $results = self::$query->run($q, SPH_MATCH_ANY, SPH_SORT_ATTR_ASC, 'modified');

    $expected = array('www.yahoo.com', 'www.amazon.com');
    foreach ($results as $res) {
      $actual[] = $res->domain;
    }
    $this->assertSame($expected, $actual);
  }

  /**
   * @group sortsByModifiedDescending
   * @test
   */
  public function sortsByModifiedDescending()
  {
    $json = file_get_contents(__DIR__ . '/../fixture/bookmarks.json');
    self::$foxtrap->registerMarks(self::$foxtrap->jsonToArray($json));

    $q = 'web';
    $results = self::$query->run($q, SPH_MATCH_ANY, SPH_SORT_ATTR_DESC, 'modified');

    $expected = array('www.amazon.com', 'www.yahoo.com');
    foreach ($results as $res) {
      $actual[] = $res->domain;
    }
    $this->assertSame($expected, $actual);
  }
}
