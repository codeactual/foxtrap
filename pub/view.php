<?php

header('Content-type: text/html; charset=utf-8');

use \Foxtrap\Factory;

require __DIR__ . '/../src/LoadClasses.php';

$factory = new Factory();
$foxtrap = $factory->createInstance();
$markId = empty($_GET['markId']) ? '' : $_GET['markId'];

if (!$markId) {
  header("HTTP/1.0 404 Not Found");
  exit;
}

$mark = $foxtrap->getDb()->getMarkById($markId);
if (!$mark) {
  header("HTTP/1.0 404 Not Found");
  exit;
}

$config = $factory->getConfigFromFile();
$purifier = $factory->createPurifier($config['htmlpurifier']['viewer']);
echo $purifier->purify($mark['body']);
