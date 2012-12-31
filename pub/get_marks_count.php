<?php

require __DIR__ . '/../src/LoadClasses.php';
$factory = new \Foxtrap\Factory();
$foxtrap = $factory->createInstance();
$data = $foxtrap->getDb()->getMarksCount();

$foxtrap->jsonpHeader();
echo $foxtrap->jsonpCallback(json_encode($data), $_GET['callback']);
