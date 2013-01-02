<?php

use \Foxtrap\Factory;
use \Foxtrap\Query;

require __DIR__ . '/../src/LoadClasses.php';

$q = empty($_GET['q']) ? '' : $_GET['q'];
$match = empty($_GET['match']) ? 'SPH_MATCH_EXTENDED' : $_GET['match'];
$sortMode = empty($_GET['sortMode']) ? '' : $_GET['sortMode'];
$sortAttr = empty($_GET['sortAttr']) ? '' : $_GET['sortAttr'];

$factory = new Factory();
$foxtrap = $factory->createInstance();
$query = $foxtrap->getQuery();
$match = $query->sphinxModeNameToValue($match);
$sortMode = $query->sphinxModeNameToValue($sortMode);
$results = $query->run($q, $match, $sortMode, $sortAttr);

$output = json_encode($results);
$foxtrap->jsonpHeader();
echo $foxtrap->jsonpCallback($output, $_GET['callback']);
