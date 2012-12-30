<?php

$title = empty($_POST['title']) ? '' : $_POST['title'];
$uri = empty($_POST['uri']) ? '' : $_POST['uri'];
$tags = empty($_POST['tags']) ? '' : $_POST['tags'];

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

$foxtrap = $factory->createInstance();
$foxtrap->jsonpHeader();
echo $foxtrap->jsonpCallback(json_encode($lastInsertId), $_GET['callback']);
