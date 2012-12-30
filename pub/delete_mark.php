<?php

use \Foxtrap\Factory;

require __DIR__ . '/../src/LoadClasses.php';

$markId = empty($_POST['markId']) ? '' : $_POST['markId'];
if (!$markId) {
  header("HTTP/1.0 404 Not Found");
  exit;
}

$factory = new Factory();
$foxtrap = $factory->createInstance();
$foxtrap->getDb()->toggleDeletionFlag($markId);
$foxtrap->jsonpHeader();
echo $foxtrap->jsonpCallback(json_encode($markId), $_GET['callback']);
