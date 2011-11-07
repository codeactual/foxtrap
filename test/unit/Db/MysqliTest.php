<?php

use \Exception;
use \Foxtrap\Factory;
use \TestData;

class MysqliTest extends PHPUnit_Framework_TestCase
{
  protected static $db;

  public static function setUpBeforeClass()
  {
    $factory = new Factory();
    $foxtrap = $factory->createTestInstance();
    self::$db = $foxtrap->getDb();
  }

  public function setUp()
  {
    parent::setUp();
    self::$db->resetTestDb();
  }

  /**
   * @group detectsConnectError
   * @test
   */
  public function detectsConnectError()
  {
    try {
      self::$db->createLink('badhost', 'baduser', 'badpw');
      $this->fail('did not detect connect error');
    } catch (Exception $e) {
      $this->assertContains('Name or service not known', $e->getMessage());
    }
  }

  /**
   * @group getsMarkMetaByUri
   * @test
   */
  public function getsMarkMetaByUri()
  {
    $expected = TestData\registerRandomMark(self::$db);
    $actual = self::$db->getMarkMetaByUri($expected['uri']);
    $this->assertSame($expected['title'], $actual['title']);
    $this->assertSame($expected['tags'], $actual['tags']);
  }

  /**
   * @group registersMark
   * @test
   */
  public function registersMark()
  {
    $expected = TestData\registerRandomMark(self::$db);
    $actual = self::$db->getMarkById(1);
    foreach ($expected as $key => $expectedValue) {
      $actualValue = $actual[$key];
      if ('modified' == $key) {
        $actualValue = strtotime($actualValue);
      }
      $this->assertSame($expectedValue, $actualValue, $key);
    }
  }

  /**
   * @group savesSuccess
   * @test
   */
  public function savesSuccess()
  {
    TestData\registerRandomMark(self::$db);
    $id = 1;
    $body = uniqid();
    $bodyClean = uniqid();
    self::$db->saveSuccess($body, $bodyClean, $id);
    $actual = self::$db->getMarkById($id);
    $this->assertSame($body, $actual['body']);
    $this->assertSame($bodyClean, $actual['body_clean']);
    $this->assertSame(1, $actual['saved']);
  }

  /**
   * @group savesError
   * @test
   */
  public function savesError()
  {
    TestData\registerRandomMark(self::$db);
    $id = 1;
    $lastErr = uniqid();
    $bodyClean = uniqid();
    self::$db->saveError($lastErr, $id);
    $actual = self::$db->getMarkById($id);
    $this->assertSame(0, $actual['saved']);
    $this->assertSame($lastErr, $actual['last_err']);
  }

  /**
   * @group flagsNonDownloadable
   * @test
   */
  public function flagsNonDownloadable()
  {
    TestData\registerRandomMark(self::$db, array('tags' => 'tag1 nosave tag2'));
    $id = 1;
    $body = uniqid();
    $bodyClean = uniqid();
    self::$db->saveSuccess($body, $bodyClean, $id);

    self::$db->flagNonDownloadable();
    $actual = self::$db->getMarkById(1);
    $this->assertSame('', $actual['body']);
    $this->assertSame('', $actual['body_clean']);
    $this->assertSame(0, $actual['saved']);
    $this->assertSame('nosave', $actual['last_err']);
  }

  /**
   * @group prunesRemovedMarks
   * @test
   */
  public function prunesRemovedMarks()
  {
    $id = 1;
    $oldVersion = 10;
    TestData\registerRandomMark(self::$db, array('version' => $oldVersion));
    $actual = self::$db->getMarkById(1);
    $this->assertSame($oldVersion, $actual['version']);

    // Expect no change
    $newVersion = $oldVersion;
    self::$db->pruneRemovedMarks($newVersion);
    $actual = self::$db->getMarkById(1);
    $this->assertSame($oldVersion, $actual['version']);

    // Expect pruning
    $newVersion = $oldVersion + 1;
    self::$db->pruneRemovedMarks($newVersion);
    $actual = self::$db->getMarkById(1);
    $this->assertSame(null, $actual);
  }

  /**
   * @group getsMarksToDownload
   * @test
   */
  public function getsMarksToDownload()
  {
    $mark1 = TestData\registerRandomMark(self::$db);
    $mark2 = TestData\registerRandomMark(self::$db);
    $expected = array($mark1['uri'], $mark2['uri']);

    $toDownload = self::$db->getMarksToDownload();
    $actual = array($toDownload[0]['uri'], $toDownload[1]['uri']);

    sort($expected);
    sort($actual);

    $this->assertSame($expected, $actual);

    $expectedSkippedId = 2;
    self::$db->saveError('SSL cert', $expectedSkippedId);
    $toDownload = self::$db->getMarksToDownload();
    $this->assertSame(1, count($toDownload));
    $this->assertSame($mark1['uri'], $toDownload[0]['uri']);
  }
}
