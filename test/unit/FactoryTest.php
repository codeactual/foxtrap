<?php

use \Foxtrap\Factory;

class FactoryTest extends PHPUnit_Framework_TestCase
{
  protected static $baseConfig;
  protected static $factory;

  public static function setUpBeforeClass()
  {
    self::$baseConfig = array(
      'db' => array(
        'class' => 'Blackhole',
        'connect' => array('event', 'horizon'),
        'ftConnect' => array('localhost:9306'),
        'testFtConnect' => array('localhost:9307'),
        'table' => 'marks',
        'historyTable' => 'searches'
      ),
      'sphinx' => array(
        'connect' => array(
          'host' => 'localhost',
          'port' => 9312,
          'index' => 'foxtrap'
        ),
        'historyTable' => 'searches',
        'weights' => array(
          'tags' => 40,
          'title' => 30,
          'uri' => 20,
          'body_clean' => 1
        ),
        'excerpts' => array(
          'before_match' => '<span class="excerpt-word">',
          'after_match'	=> '</span>',
          'chunk_separator'	=> ' ... ',
          'limit'	=> 750,
          'around' => 10
        )
      ),
      'curl' => array(
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_MAXREDIRS => 3,
      ),
      'htmlpurifier' => array(
        'index' => array(
          'HTML.TidyLevel' => 'none',
          'HTML.Allowed' => '',
          'Cache.SerializerPath' => '/tmp'
        ),
        'viewer' => array(
          'HTML.TidyLevel' => 'none',
          'HTML.AllowedAttributes' => '',
          'Cache.SerializerPath' => '/tmp'
        )
      ),
      'log' => array(
        'class' => 'Stdout'
      )
    );

    self::$factory = new Factory();
  }

  /**
   * @group createsInstanceFromArray
   * @test
   */
  public function createsInstanceFromArray()
  {
    $config = self::$baseConfig;
    $foxtrap = self::$factory->createInstanceFromArray($config);

    $expectedConfig = $config;
    $this->assertSame($expectedConfig, $foxtrap->getDb()->config);

    $this->assertInstanceOf(
      "\\Foxtrap\\Db\\{$config['db']['class']}",
      $foxtrap->getDb()
    );

    $this->assertInstanceOf(
      "\\Foxtrap\\Log\\{$config['log']['class']}",
      $foxtrap->getLog()
    );

    $this->assertInstanceOf('\\Foxtrap\\Query', $foxtrap->getQuery());

    // Blackhole::createLink() just returns the options it's sent as an object
    $this->assertEquals((object) $config['db']['connect'], $foxtrap->getDb()->link);

    $purifierConfig = $foxtrap->getPurifier()->config->getAll();
    foreach ($config['htmlpurifier']['index'] as $key => $value) {
      list($ns, $key) = explode('.', $key);
      $this->assertSame($value, $purifierConfig[$ns][$key]);
    };

    $this->assertInstanceOf('\\CurlyQueue\\CurlyQueue', $foxtrap->getQueue());
  }

  /**
   * @group createsInstanceFromFileWithoutLogClass
   * @test
   */
  public function createsInstanceFromFileWithoutLogClass()
  {
    $config = self::$baseConfig;
    $config['log']['class'] = '';
    $foxtrap = self::$factory->createInstanceFromArray($config);

    $this->assertInstanceOf(
      "\\Foxtrap\\Log\\Blackhole",
      $foxtrap->getLog()
    );
  }

  /**
   * @group createsInstanceFromFile
   * @test
   */
  public function createsInstanceFromFile()
  {
    $expected = self::$factory->getConfigFromFile();
    $foxtrap = self::$factory->createInstance();
    $this->assertInstanceOf("\\Foxtrap\\Db\\{$expected['db']['class']}", $foxtrap->getDb());
  }
}
