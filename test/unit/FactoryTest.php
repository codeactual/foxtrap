<?php

use \Foxtrap\Factory;

class FactoryTest extends PHPUnit_Framework_TestCase
{
  protected static $baseConfig;

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
  }

  /**
   * @group createsInstance
   * @test
   */
  public function createsInstance()
  {
    $config = self::$baseConfig;
    $foxtrap = Factory::createInstance($config);

    // createInstance() forces CURLOPT_RETURNTRANSFER
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

    $this->assertInstanceOf('\\CurlyQueue', $foxtrap->getQueue());
  }

  /**
   * @group createsInstanceWithoutLogClass
   * @test
   */
  public function createsInstanceWithoutLogClass()
  {
    $config = self::$baseConfig;
    $config['log']['class'] = '';
    $foxtrap = Factory::createInstance($config);

    $this->assertInstanceOf(
      "\\Foxtrap\\Log\\Blackhole",
      $foxtrap->getLog()
    );
  }
}
