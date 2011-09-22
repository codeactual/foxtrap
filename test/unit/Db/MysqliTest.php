<?php

use \Exception;
use \Foxtrap\Factory;

class MysqliTest extends PHPUnit_Framework_TestCase
{
  protected static $db;
  protected static $dbClass;
  protected static $dbLink;

  public static function setUpBeforeClass()
  {
    require_once __DIR__ . "/../../../config.php";
    require_once __DIR__ . "/../../../src/Foxtrap/Db/{$config['db']['class']}.php";
    self::$dbClass = "\\Foxtrap\\Db\\{$config['db']['class']}";
    self::$dbLink = call_user_func_array(
      array(self::$dbClass, 'createLink'),
      $config['db']['testOpts']
    );
    self::$db = new self::$dbClass(self::$dbLink, $config);
  }

  public function setUp()
  {
    parent::setUp();

    // Allow simple tests to deal with predictable ID numbers
    // and generally avoid cross-test state passing.
    $this->assertTrue(self::$dbLink->query('TRUNCATE `' . self::$db->getTable() . '`'));
  }

  /**
   * @param array $override Key/value pairs to override random selection.
   * @return array Expected field names and values of the created row.
   */
  public function registerRandomMark(array $overrides = array())
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
    self::$db->register($expected);
    return $expected;
  }

  /**
   * @group detectsConnectError
   * @test
   */
  public function detectsConnectError()
  {
    try {
      self::$db->createLink('localhost', 'localhost', 'localhost');
      $this->fail('did not detect connect error');
    } catch (Exception $e) {
      $this->assertContains('Access denied for user', $e->getMessage());
    }
  }

  /**
   * @group registersMark
   * @test
   */
  public function registersMark()
  {
    $expected = $this->registerRandomMark();
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
    $this->registerRandomMark();
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
    $this->registerRandomMark();
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
    $this->registerRandomMark(array('tags' => 'tag1 nosave tag2'));
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
    $this->registerRandomMark(array('version' => $oldVersion));
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
    $this->assertEquals(false, $actual);
  }

  /**
   * @group getsMarksToDownload
   * @test
   */
  public function getsMarksToDownload()
  {
    $mark1 = $this->registerRandomMark();
    $mark2 = $this->registerRandomMark();
    $expected = array($mark1['uri'], $mark2['uri']);

    $toDownload = self::$db->getMarksToDownload();
    $actual = array($toDownload[0]['uri'], $toDownload[1]['uri']);

    sort($expected);
    sort($actual);

    $this->assertSame($expected, $actual);

    $expectedSkippedId = 2;
    self::$db->saveError('SSL cert', $expectedSkippedId);
    $toDownload = self::$db->getMarksToDownload();
    $this->assertEquals(1, count($toDownload));
    $this->assertSame($mark1['uri'], $toDownload[0]['uri']);
  }
}
