<?php

use \Foxtrap\Factory;
use \TestData;

class FoxtrapTest extends PHPUnit_Framework_TestCase
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

  public function providesAdjacentTagsWithInnerText()
  {
    return array(
      array('<div>a</div><div>b</div>', 'a b'),
      array('<span>a</span><br /><span>b</span>', 'a b'),
      array(
        'a <br /> <br />b <span>c</span> d',
        'a b c d'
      )
    );
  }

  /**
   * @group addsWhitespaceBetweenAdjacentTagInnerText
   * @test
   * @dataProvider providesAdjacentTagsWithInnerText
   */
  public function addsWhitespaceBetweenAdjacentTagInnerText($body, $expected)
  {
    $actual = self::$foxtrap->cleanResponseBody($body);
    $this->assertSame($expected, $actual);
  }

  /**
   * @group removesRedundantWhitespace
   * @test
   */
  public function removesRedundantWhitespace()
  {
    $uniSpace = "\xc2\xa0";
    $body = "  a{$uniSpace}{$uniSpace}b c       d  {$uniSpace} ";
    $actual = self::$foxtrap->cleanResponseBody($body);
    $this->assertSame('a b c d', $actual);
  }

  /**
   * @group cleansResponseBody
   * @test
   */
  public function cleansResponseBody()
  {
    $body = file_get_contents(__DIR__ . '/../fixture/mysql-incorrect-string-type.html');
    $expected = file_get_contents(__DIR__ . '/../fixture/mysql-incorrect-string-type-cleaned.html');
    $actual = self::$foxtrap->cleanResponseBody($body);
    $this->assertSame($expected, $actual);
  }

  /**
   * @group downloads
   * @test
   */
  public function downloads()
  {
    $google = TestData\registerRandomMark(
      self::$foxtrap, array('uri' => 'http://www.facebook.com/')
    );
    $yahoo = TestData\registerRandomMark(
      self::$foxtrap, array('uri' => 'http://www.yahoo.com/')
    );

    self::$foxtrap->download();

    $actual = self::$db->getMarkById(1);
    $this->assertContains('Create a Page', $actual['body']);
    $this->assertContains('<html', $actual['body']);
    $this->assertContains('Create a Page', $actual['body_clean']);
    $this->assertNotContains('<html', $actual['body_clean']);

    $actual = self::$db->getMarkById(2);
    $this->assertContains('Yahoo!', $actual['body']);
    $this->assertContains('<html', $actual['body']);
    $this->assertContains('Yahoo!', $actual['body_clean']);
    $this->assertNotContains('<html', $actual['body_clean']);
  }
}
