<?php

require_once __DIR__ . '/../vendor/symfony/UniversalClassLoader.php';
$cl = new \Symfony\Component\ClassLoader\UniversalClassLoader();
$cl->registerNamespaces(
  array(
    'Foxtrap' => __DIR__,
    'CurlyQueue' => __DIR__ . '/../vendor/curlyqueue/src',
    'Flow' => __DIR__ . '/../vendor/curlyqueue/vendor/flow/src',
  )
);
$cl->register();
unset($cl);

require_once __DIR__ . '/../vendor/htmlpurifier/library/HTMLPurifier.auto.php';
require_once 'sphinx/sphinxapi.php';
