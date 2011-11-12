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

  /**
   * @group cleansUp
   * @test
   */
  public function cleansUp()
  {
    // Should flag as non-downloadable
    $target1 = TestData\registerRandomMark(self::$foxtrap, array('tags' => 'tag1,nosave,tag2'));
    self::$db->saveSuccess('html', 'plaintext', 1);

    // Should prune as mark no longer in FF
    $target2 = TestData\registerRandomMark(self::$foxtrap);

    $ignored = TestData\registerRandomMark(self::$foxtrap);

    // Verify initial state
    $actual = self::$db->getMarkById(1);
    $this->assertSame($target1['uri'], $actual['uri']);
    $actual = self::$db->getMarkById(2);
    $this->assertSame($target2['uri'], $actual['uri']);
    $actual = self::$db->getMarkById(3);
    $this->assertSame($ignored['uri'], $actual['uri']);

    // Remove $target2
    $affected = self::$foxtrap->cleanup(array(2));

    // Verify basic results
    $this->assertSame(1, $affected['pruned']);
    $this->assertSame(1, $affected['flagged']);

    // Verify flagging result
    $actual = self::$db->getMarkById(1);
    $this->assertSame('', $actual['body']);
    $this->assertSame('', $actual['body_clean']);
    $this->assertSame('nosave', $actual['last_err']);
    $this->assertSame(0, $actual['downloaded']);

    // Verify pruning result
    $this->assertSame(null, self::$db->getMarkById(2));
    $actual = self::$db->getMarkById(3);

    // Verify unintended target
    $this->assertSame($ignored['uri'], $actual['uri']);
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
   * @group generatesMarkHash
   * @test
   */
  public function generatesMarkHash()
  {
    $this->assertSame(
      '968d9da0d79159049c5c44e8f85e8304',
      self::$foxtrap->generateMarkHash(
        'https://twitter.com/',
        'Twitter',
        'social',
        1316495070
      )
    );
  }

  /**
   * @group markLastModTimeConsidersTagModTime
   * @test
   */
  public function markLastModTimeConsidersTagModTime()
  {
    $json = file_get_contents(__DIR__ . '/../fixture/bookmarks.json');
    $arr = self::$foxtrap->jsonToArray($json);

    $found = false;
    foreach ($arr['marks'] as $mark) {
      if ('https://twitter.com/' == $mark['uri']) {
        $this->assertSame(1316495070343835, $mark['lastModified']);
        $found = true;
      }
    }

    $this->assertTrue($found);
  }

  /**
   * FF allows them, so importing is simpler if we do too.
   *
   * @group registerMarksAllowsDupes
   * @test
   */
  public function registerMarksAllowsDupes()
  {
    $json = file_get_contents(__DIR__ . '/../fixture/google-with-fragments.json');
    self::$foxtrap->registerMarks(self::$foxtrap->jsonToArray($json));
    $toDownload = self::$db->getMarksToDownload();

    $this->assertSame(3, count($toDownload));
    $mark = self::$db->getMarkById(1);
    $this->assertSame('http://www.google.com/search?q=php', $mark['uri']);
    $mark = self::$db->getMarkById(2);
    $this->assertSame('http://www.google.com/search?q=php#frag1', $mark['uri']);
    $mark = self::$db->getMarkById(3);
    $this->assertSame('http://www.google.com/search?q=php#frag2', $mark['uri']);
  }

  /**
   * @group registerMarksDetectsUriRemoval
   * @test
   */
  public function registerMarksDetectsUriRemoval()
  {
    $json = file_get_contents(__DIR__ . '/../fixture/entities-to-encode.json');
    $pruneIds = self::$foxtrap->registerMarks(self::$foxtrap->jsonToArray($json));
    $this->assertCount(0, $pruneIds);

    $json = file_get_contents(__DIR__ . '/../fixture/entities-to-encode-2-removed.json');
    $pruneIds = self::$foxtrap->registerMarks(self::$foxtrap->jsonToArray($json));
    $this->assertCount(2, $pruneIds);

    $mark = self::$db->getMarkById(1);
    $this->assertSame('http://www.w3schools.com/tags/ref_symbols.asp', $mark['uri']);
    $mark = self::$db->getMarkById(2);
    $this->assertSame('http://redis.io/topics/pubsub', $mark['uri']);
    $mark = self::$db->getMarkById(3);
    $this->assertSame('http://www.slideshare.net/AmazonWebServices/predicting-costs-on-aws', $mark['uri']);
  }

  /**
   * @group registerMarksDetectsTagRemoval
   * @test
   */
  public function registerMarksDetectsTagRemoval()
  {
    $json = file_get_contents(__DIR__ . '/../fixture/entities-to-encode.json');
    self::$foxtrap->registerMarks(self::$foxtrap->jsonToArray($json));
    $mark = self::$db->getMarkById(5);
    $this->assertSame('tag 1,tag2', $mark['tags']);

    $json = file_get_contents(__DIR__ . '/../fixture/entities-to-encode-1-tag-removed.json');
    self::$foxtrap->registerMarks(self::$foxtrap->jsonToArray($json));
    $mark = self::$db->getMarkById(7);
    $this->assertSame('tag 1', $mark['tags']);
  }

  /**
   * @group registerMarksDetectsNonDownloadable
   * @test
   */
  public function registerMarksDetectsNonDownloadable()
  {
    // 3 downloadable marks, 1 non-downloadable
    $json = file_get_contents(__DIR__ . '/../fixture/amazon-addtag-nosave.json');
    self::$foxtrap->registerMarks(self::$foxtrap->jsonToArray($json));

    $toDownload = self::$db->getMarksToDownload();
    $this->assertSame(3, count($toDownload));

    $mark = self::$db->getMarkById(3);
    $this->assertSame('http://www.amazon.com/', $mark['uri']);
    $this->assertSame('nosave', $mark['last_err']);
  }

  /**
   * @group registerMarksStoresMiscFields
   * @test
   */
  public function registerMarksStoresMiscFields()
  {
    $json = file_get_contents(__DIR__ . '/../fixture/bookmarks.json');
    self::$foxtrap->registerMarks(self::$foxtrap->jsonToArray($json));

    $mark = self::$db->getMarkById(1);
    $this->assertSame('https://twitter.com/', $mark['uri']);
    $this->assertSame('Twitter', $mark['title']);
    $this->assertSame(
      self::$foxtrap->generateMarkHash(
        $mark['uri'],
        $mark['title'],
        $mark['tags'],
        $mark['added']
      ),
      $mark['hash']
    );
    $this->assertSame('social', $mark['tags']);
    $this->assertSame(1316494982, $mark['added']);
    $this->assertSame(1316495070, $mark['modified']);
    $this->assertSame(0, $mark['downloaded']);
  }

  /**
   * @group responseErrorHandled
   * @test
   */
  public function responseErrorHandled()
  {
    $host = 'b6e90e661b6f10e2d3763c4e8c450c88adcd20d8';
    $markData = array(
      'marks' => array(
        array(
          'uri' => "http://{$host}/",
          'lastModified' => time(),
          'title' => 'does not exist'
        )
      ),
      'pageTags' => array()
    );
    self::$foxtrap->registerMarks($markData);
    self::$foxtrap->download();
    $mark = self::$db->getMarkById(1);
    $this->assertContains("Couldn't resolve host '{$host}'", $mark['last_err']);
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
    $this->assertContains('Yahoo! Inc', $actual['body']);
    $this->assertContains('<html', $actual['body']);
    $this->assertContains('Yahoo! Inc', $actual['body_clean']);
    $this->assertNotContains('<html', $actual['body_clean']);
  }

  /**
   * @group verifiesLastRunHash
   * @test
   */
  public function verifiesLastRunHash()
  {
    $file = self::$foxtrap->getLastRunHashFile();
    if (file_exists($file)) {
      unlink($file);
    }

    $json = file_get_contents(__DIR__ . '/../fixture/bookmarks.json');
    self::$foxtrap->writeLastRunHash($json);
    $this->assertSame(
      md5($json),
      file_get_contents(self::$foxtrap->getLastRunHashFile())
    );

    $this->assertTrue(self::$foxtrap->isLastRunInputSame($json));
    $this->assertFalse(self::$foxtrap->isLastRunInputSame($json . 't'));
  }
}
