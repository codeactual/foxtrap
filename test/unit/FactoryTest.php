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
        'opts' => array('event', 'horizon'),
      ),
      'curl' => array(
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_MAXREDIRS => 3,
      ),
      'htmlpurifier' => array(
        'HTML.TidyLevel' => 'heavy',
        'HTML.Allowed' => 'li',
        'Cache.SerializerPath' => '/tmp/custom',
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
    $expectedConfig['curl'][CURLOPT_RETURNTRANSFER] = 1;
    $this->assertSame($expectedConfig, $foxtrap->getDb()->config);

    $this->assertInstanceOf(
      "\\Foxtrap\\Db\\{$config['db']['class']}",
      $foxtrap->getDb()
    );

    $this->assertInstanceOf(
      "\\Foxtrap\\Log\\{$config['log']['class']}",
      $foxtrap->getLog()
    );

    // Blackhole::createLink() just returns the options it's sent as an object
    $this->assertEquals((object) $config['db']['opts'], $foxtrap->getDb()->link);

    $purifierConfig = $foxtrap->getPurifier()->config->getAll();
    foreach ($config['htmlpurifier'] as $key => $value) {
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
