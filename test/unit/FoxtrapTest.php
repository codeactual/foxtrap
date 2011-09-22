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
    $this->markTestIncomplete();
  }

  /**
   * @group convertsDbRowToObj
   * @test
   */
  public function convertsDbRowToObj()
  {
    $this->markTestIncomplete();
  }

  /**
   * @group convertsJsonFileToArray
   * @test
   */
  public function convertsJsonFileToArray()
  {
    $this->markTestIncomplete();
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
