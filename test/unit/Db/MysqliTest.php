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
   * @group savesError
   * @test
   */
  public function savesError()
  {
    TestData\registerRandomMark(self::$foxtrap);
    $id = 1;
    $lastErr = uniqid();
    self::$db->saveError($lastErr, $id);
    $actual = self::$db->getMarkById($id);
    $this->assertSame(0, $actual['downloaded']);
    $this->assertSame($lastErr, $actual['last_err']);
  }

  /**
   * @group removesError
   * @test
   */
  public function removesError()
  {
    TestData\registerRandomMark(self::$foxtrap);
    $id = 1;
    $lastErr = uniqid();

    self::$db->saveError($lastErr, $id);
    $actual = self::$db->getMarkById($id);
    $this->assertSame($lastErr, $actual['last_err']);

    $success = self::$db->removeError($id);
    $this->assertTrue($success);
    $success = self::$db->removeError($id);
    $this->assertFalse($success);
    $actual = self::$db->getMarkById($id);
    $this->assertSame('', $actual['last_err']);
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

  /**
   * @group getsErrorLogs
   * @test
   */
  public function getsErrorLogs()
  {
    $mark1 = TestData\registerRandomMark(self::$foxtrap);
    $mark2 = TestData\registerRandomMark(self::$foxtrap);
    $mark3 = TestData\registerRandomMark(self::$foxtrap);
    $mark4 = TestData\registerRandomMark(self::$foxtrap);
    $expected = array(
      array(
        'id' => $mark4['id'],
        'last_err' => 'mark4 error',
        'title' => $mark4['title'],
        'uri' => $mark4['uri']
      ),
      array(
        'id' => $mark1['id'],
        'last_err' => 'mark1 error',
        'title' => $mark1['title'],
        'uri' => $mark1['uri']
      )
    );

    self::$db->saveError('mark1 error', $mark1['id']); // Expected
    self::$db->saveError('', $mark2['id']);            // Unexpected
    self::$db->saveError('nosave', $mark3['id']);      // Unexpected
    self::$db->saveError('mark4 error', $mark4['id']); // Expected

    $log = self::$db->getErrorLog(2);
    $this->assertSame(2, count($log));
    $this->assertSame($expected[0], (array) $log[0]);   // $mark4
    $this->assertSame($expected[1], (array) $log[1]);   // $mark1
  }
}
