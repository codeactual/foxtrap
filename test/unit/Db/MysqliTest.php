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
    $this->assertTrue(self::$dbLink->query('TRUNCATE `' . self::$db->getTable() . '`'));
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
    $expected = array(
      'title' => uniqid(),
      'uri' => uniqid(),
      'uriHashWithoutFrag' => uniqid(),
      'pageTagsStr' => uniqid(),
      'lastErr' => '',
      'lastModified' => time() - mt_rand(1, 3600),
      'version' => time()
    );
    self::$db->register($expected);
    $actual = self::$db->getMarkById(1);
    $this->assertSame($expected['title'], $actual['title']);
    $this->assertSame($expected['uri'], $actual['uri']);
    $this->assertSame($expected['uriHashWithoutFrag'], $actual['uri_hash']);
    $this->assertSame($expected['pageTagsStr'], $actual['tags']);
    $this->assertSame($expected['lastErr'], $actual['last_err']);
    $this->assertSame($expected['lastModified'], strtotime($actual['modified']));
    $this->assertSame($expected['version'], $actual['version']);
  }

  /**
   * @group savesSuccess
   * @test
   */
  public function savesSuccess()
  {
    $this->markTestIncomplete();
  }

  /**
   * @group savesError
   * @test
   */
  public function savesError()
  {
    $this->markTestIncomplete();
  }

  /**
   * @group flagsNonDownload
   * @test
   */
  public function flagsNonDownload()
  {
    $this->markTestIncomplete();
  }

  /**
   * @group prunesRemovedMarks
   * @test
   */
  public function prunesRemovedMarks()
  {
    $this->markTestIncomplete();
  }

  /**
   * @group getsMarksToDownload
   * @test
   */
  public function getsMarksToDownload()
  {
    $this->markTestIncomplete();
  }
}
