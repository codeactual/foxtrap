<?php

use \Foxtrap\Factory;
use \TestData;

class FoxtrapTest extends PHPUnit_Framework_TestCase
{
  protected static $db;
  protected static $foxtrap;

  public static function setUpBeforeClass()
  {
    require FOXTRAP_CONFIG_FILE; // from bootstrap.php
    $factory = new Factory();
    self::$foxtrap = $factory->createTestInstance($config);
    self::$db = self::$foxtrap->getDb();
  }

  public function setUp()
  {
    parent::setUp();
    self::$db->resetTestDb();
  }

  /**
   * @group cleansUp
   * @test
   */
  public function cleansUp()
  {
    // Should flag as non-downloadable
    $target1 = TestData\registerRandomMark(self::$db, array('tags' => 'tag1 nosave tag2'));
    self::$db->saveSuccess('html', 'plaintext', 1);

    // Should prune as mark no longer in FF
    $oldVersion = 10;
    $newVersion = $oldVersion + 1;
    $target2 = TestData\registerRandomMark(self::$db, array('version' => $oldVersion));

    $ignored = TestData\registerRandomMark(self::$db);

    // Verify initial state
    $actual = self::$db->getMarkById(1);
    $this->assertSame($target1['uri'], $actual['uri']);
    $actual = self::$db->getMarkById(2);
    $this->assertSame($target2['uri'], $actual['uri']);
    $actual = self::$db->getMarkById(3);
    $this->assertSame($ignored['uri'], $actual['uri']);

    $affected = self::$foxtrap->cleanup($newVersion);

    // Verify basic results
    $this->assertSame(1, $affected['pruned']);
    $this->assertSame(1, $affected['flagged']);

    // Verify flagging result
    $actual = self::$db->getMarkById(1);
    $this->assertSame('', $actual['body']);
    $this->assertSame('', $actual['body_clean']);
    $this->assertSame('nosave', $actual['last_err']);
    $this->assertSame(0, $actual['saved']);

    // Verify pruning result
    $this->assertSame(null, self::$db->getMarkById(2));
    $actual = self::$db->getMarkById(3);

    // Verify unintended target
    $this->assertSame($ignored['uri'], $actual['uri']);
  }

  /**
   * @group downloads
   * @test
   */
  public function downloads()
  {
    $google = TestData\registerRandomMark(
      self::$db, array('uri' => 'http://www.facebook.com/')
    );
    $yahoo = TestData\registerRandomMark(
      self::$db, array('uri' => 'http://www.yahoo.com/')
    );

    self::$foxtrap->download();

    $actual = self::$db->getMarkById(1);
    $this->assertContains('Create a Page', $actual['body']);
    $this->assertContains('<html', $actual['body']);
    $this->assertContains('Create a Page', $actual['body_clean']);
    $this->assertNotContains('<html', $actual['body_clean']);

    $actual = self::$db->getMarkById(2);
    $this->assertContains('Yahoo! Inc', $actual['body']);
    $this->assertContains('<html', $actual['body']);
    $this->assertContains('Yahoo! Inc', $actual['body_clean']);
    $this->assertNotContains('<html', $actual['body_clean']);
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
    $obj = self::$foxtrap->dbRowToObj($row);
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
   * @group convertsJsonToArray
   * @test
   */
  public function convertsJsonToArray()
  {
    $json = file_get_contents(__DIR__ . '/../fixture/bookmarks.json');
    $arr = self::$foxtrap->jsonToArray($json);

    $uris = array();
    foreach ($arr['marks'] as $mark) {
      $this->assertTrue(ctype_digit($mark['lastModified']));
      $this->assertNotEmpty($mark['uri']);
      $this->assertNotEmpty($mark['title']);
      $uris[$mark['uri']] = md5($mark['uri']);
    }

    $this->assertSame(
      array('social'),
      $arr['pageTags'][$uris['https://twitter.com/']]
    );
    $this->assertSame(
      array('shopping'),
      $arr['pageTags'][$uris['http://www.amazon.com/']]
    );
    $this->assertSame(
      array('search'),
      $arr['pageTags'][$uris['http://www.google.com/']]
    );
    $this->assertSame(
      array('search'),
      $arr['pageTags'][$uris['http://www.yahoo.com/']]
    );
  }

  /**
   * @group registersMarks
   * @test
   */
  public function registersMarks()
  {
    $this->markTestIncomplete();
  }

  /**
   * @group buildsJsonpCallback
   * @test
   */
  public function buildsJsonpCallback()
  {
    $this->markTestIncomplete();
  }
}
