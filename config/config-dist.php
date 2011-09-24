<?php

$config = array(
  'db' => array(
    // \Foxtrap\Db\Mysqli
    'class' => 'Mysqli',
    // Passed to mysqli_connect()
    'opts' => array('localhost', 'user', 'pass', 'foxtrap'),
    'testOpts' => array('localhost', 'user', 'pass', 'foxtrap'),
    // Pairs specific to the DB class
    'table' => 'marks'
  ),
  'sphinx' => array(
    'host' => 'localhost',
    'port' => 9312,
    'index' => 'foxtrap',

    // bin/foxtrap always ends by running bin/foxtrap-indexer
    'autoindex' => true
  ),
  'curl' => array(
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_FOLLOWLOCATION => 1
  ),
  'htmlpurifier' => array(
    'HTML.TidyLevel' => 'none',
    'HTML.Allowed' => '',
    'Cache.SerializerPath' => '/tmp',
  ),
  'log' => array(
    'class' => '' // ex. 'Stdout' for Log/Stdout.php
  )
);
