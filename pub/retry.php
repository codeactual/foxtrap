<?php

use \Foxtrap\Factory;

require __DIR__ . '/../src/LoadClasses.php';

$factory = new Factory();
$foxtrap = $factory->createInstance();

$markId = empty($_GET['markId']) ? '' : $_GET['markId'];
if (!$markId) {
  header("HTTP/1.0 404 Not Found");
  exit;
}

$result = ['removed' => $foxtrap->getDb()->removeError($markId)];
$foxtrap->jsonpHeader();
echo $foxtrap->jsonpCallback(json_encode($result), $_GET['callback']);
