<?php

$title = empty($_GET['title']) ? '' : $_GET['title'];
$uri = empty($_GET['uri']) ? '' : $_GET['uri'];
$tags = empty($_GET['tags']) ? '' : $_GET['tags'];

if ($title && $uri) {
  require __DIR__ . '/../src/LoadClasses.php';
  $factory = new \Foxtrap\Factory();
  $now = time();
  $mark = [
    'title' => $title,
    'uri' => $uri,
    'tags' => $tags
  ];
  $mark = array_map('stripslashes', $mark);
  $mark['hash'] = md5($mark['uri']);
  $mark['modified'] = $now;
  $mark['added'] = $now;
  $mark['last_err'] = '';
  $lastInsertId = $factory->createInstance()->getDb()->register($mark);
}

$foxtrap->jsonpHeader();
echo $foxtrap->jsonpCallback(json_encode($lastInsertId), $_GET['callback']);
