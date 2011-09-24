<?php

$q = empty($_GET['q']) ? '' : $_GET['q'];

if ($q) {
  require __DIR__ . '/../src/LoadClasses.php';
  $factory = new \Foxtrap\Factory();
  $factory->createInstance()->getDb()->addHistory(stripslashes($q));
}

require_once 'get_history.php';
