<?php

$config = array(
  'db' => array(
    // \Foxtrap\Db\Mysqli
    'class' => 'Mysqli',
    // Passed to mysqli_connect()
    'opts' => array('localhost', 'user', 'pass', 'db'),
    // Pairs specific to the DB class
    'table' => 'foxtrap'
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
  )
);
