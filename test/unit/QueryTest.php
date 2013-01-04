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

  /**
   * @group convertsDbRowToObj
   * @test
   */
  public function convertsDbRowToObj()
  {
    $row = array(
      'id' => uniqid(),
      'downloaded' => false,
      'deleted' => false,
      'modified' => time(),
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
}
