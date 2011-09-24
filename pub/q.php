<?php

use \Foxtrap\Factory;
use \Foxtrap\Query;

require __DIR__ . '/../src/LoadClasses.php';

$q = empty($_GET['q']) ? '' : $_GET['q'];

$factory = new Factory();
$foxtrap = $factory->createInstance();
$config = $factory->getConfigFromFile();
$query = new Query($foxtrap->getDb(), $config['sphinx']);
$results = $query->run($q);

$output = json_encode($results);
$query->jsonpHeader();
echo $query->jsonpCallback($output, $_GET['callback']);
