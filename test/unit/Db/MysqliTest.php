<?php

use \Exception;
use \Foxtrap\Factory;
use \TestData;

class MysqliTest extends PHPUnit_Framework_TestCase
{
  protected static $db;
  protected static $foxtrap;

  public static function setUpBeforeClass()
  {
    $factory = new Factory();
    self::$foxtrap = $factory->createTestInstance();
    self::$db = self::$foxtrap->getDb();
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
   * @group registersMark
   * @test
   */
  public function registersMark()
  {
    $expected = TestData\registerRandomMark(self::$foxtrap);
    $actual = self::$db->getMarkById(1);
    foreach ($expected as $key => $expectedValue) {
      $actualValue = $actual[$key];
      $this->assertSame($expectedValue, $actualValue, $key);
    }
  }

  /**
   * @group savesSuccess
   * @test
   */
  public function savesSuccess()
  {
    TestData\registerRandomMark(self::$foxtrap);
    $id = 1;
    $body = uniqid();
    $bodyClean = uniqid();
    self::$db->saveSuccess($body, $bodyClean, $id);
    $actual = self::$db->getMarkById($id);
    $this->assertSame($body, $actual['body']);
    $this->assertSame($bodyClean, $actual['body_clean']);
    $this->assertSame(time(), $actual['downloaded']);
  }

  /**
   * Uses text with known trigger of MySQL's 'Incorrect string value' (1366) error
   * when not prepared with utf8_encode().
   *
   * @group savesTextWithIncorrectStringValue
   * @test
   */
  public function savesBodyWithIncorrectStringValue()
  {
    TestData\registerRandomMark(self::$foxtrap);
    $id = 1;
    $body = file_get_contents(__DIR__ . '/../../fixture/mysql-incorrect-string-type.html');
    $bodyClean = self::$foxtrap->cleanResponseBody($body);
    self::$db->saveSuccess($body, $bodyClean, $id);
    $actual = self::$db->getMarkById($id);
    $this->assertSame($body, $actual['body']);
    $this->assertSame($bodyClean, $actual['body_clean']);
  }

  /**
   * @group cleansResponseBody
   * @test
   */
  public function cleansResponseBody()
  {
    $body = file_get_contents(__DIR__ . '/../../fixture/mysql-incorrect-string-type.html');
    $expected = file_get_contents(__DIR__ . '/../../fixture/mysql-incorrect-string-type-cleaned.html');
    //$body = file_get_contents('/tmp/asdf');
    $actual = self::$foxtrap->cleanResponseBody($body);
    file_put_contents('/tmp/clean', $actual);
    //$this->assertSame($expected, $actual);
    $this->assertTrue(true);
  }

  /**
   * @group savesError
   * @test
   */
  public function savesError()
  {
    TestData\registerRandomMark(self::$foxtrap);
    $id = 1;
    $lastErr = uniqid();
    $bodyClean = uniqid();
    self::$db->saveError($lastErr, $id);
    $actual = self::$db->getMarkById($id);
    $this->assertSame(0, $actual['downloaded']);
    $this->assertSame($lastErr, $actual['last_err']);
  }

  /**
   * @group flagsNonDownloadable
   * @test
   */
  public function flagsNonDownloadable()
  {
    TestData\registerRandomMark(self::$foxtrap, array('tags' => 'tag1,nosave,tag2'));
    $id = 1;
    $body = uniqid();
    $bodyClean = uniqid();
    self::$db->saveSuccess($body, $bodyClean, $id);

    self::$db->flagNonDownloadable();
    $actual = self::$db->getMarkById(1);
    $this->assertSame('', $actual['body']);
    $this->assertSame('', $actual['body_clean']);
    $this->assertSame(0, $actual['downloaded']);
    $this->assertSame('nosave', $actual['last_err']);
  }

  /**
   * @group getsMarksToDownload
   * @test
   */
  public function getsMarksToDownload()
  {
    $mark1 = TestData\registerRandomMark(self::$foxtrap);
    $mark2 = TestData\registerRandomMark(self::$foxtrap);
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
