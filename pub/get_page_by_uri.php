<?php

use \DOMDocument;
use \DOMXPath;

$uri = empty($_GET['uri']) ? '' : $_GET['uri'];

if ($uri) {
  require __DIR__ . '/../src/LoadClasses.php';
  $factory = new \Foxtrap\Factory();
  $foxtrap = $factory->createInstance();

  $mark = $foxtrap->getDb()->getMarkByHash(md5($uri));
  if (!$mark) {
    $mark = array();

    $ch = curl_init();
    $config = $factory->getConfigFromFile();
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

    // Uses:
    // - utf8 encoding
    // - Ex. <title> contains a real quotation mark defined as an HTML entity.
    $content =  mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8');

    @$doc->loadHTML($content);
    $xpath = new DOMXPath($doc);
    $mark['title'] = $xpath->query('//title');
    if ($mark['title']) {
      $mark['title'] = trim($mark['title']->item(0)->nodeValue);
    } else {
      $mark['title'] = 'untitled page';
    }
  }

  $foxtrap->jsonpHeader();
  echo $foxtrap->jsonpCallback(json_encode($mark), $_GET['callback']);
}
