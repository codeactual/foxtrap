<?php

use \DOMDocument;
use \DOMXPath;

$uri = empty($_GET['uri']) ? '' : $_GET['uri'];

if ($uri) {
  require __DIR__ . '/../src/LoadClasses.php';
  $factory = new \Foxtrap\Factory();
  $config = $factory->getConfigFromFile();

  $ch = curl_init();
  $opts = $config['curl'];
  $opts[CURLOPT_URL] = $uri;
  $opts[CURLOPT_RETURNTRANSFER] = true;
  curl_setopt_array($ch, $opts);
  $content = curl_exec($ch);
  curl_close($ch);

  if (false === $content) {
    header("HTTP/1.0 404 Not Found");
    exit;
  }

  $doc = new DOMDocument();
  @$doc->loadHTML($content);
  $xpath = new DOMXPath($doc);
  $title = $xpath->query('//title');
  if ($title) {
    $title = $title->item(0)->nodeValue;
  } else {
    $title = 'untitled page';
  }

  $foxtrap = $factory->createInstance();
  $foxtrap->jsonpHeader();
  echo $foxtrap->jsonpCallback(json_encode($title), $_GET['callback']);
}
