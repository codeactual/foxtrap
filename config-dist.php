<?php

$config = array(
  'db' => array(
    // \Foxtrap\Db\Mysqli
    'class' => 'Mysqli',
    // Passed to mysqli_connect()
    'opts' => array('localhost', 'user', 'pass', 'db'),
    // Pairs specific to the DB class
    'table' => 'foxtrap'
  )
);
