<?php

use \Foxtrap\Factory;
use \Foxtrap\Query;

require __DIR__ . '/../src/LoadClasses.php';

$q = empty($_GET['q']) ? '' : $_GET['q'];

$factory = new Factory();
$foxtrap = $factory->createInstance();
$query = $foxtrap->getQuery();
$results = $query->run($q);

$output = json_encode($results);
$foxtrap->jsonpHeader();
echo $foxtrap->jsonpCallback($output, $_GET['callback']);
