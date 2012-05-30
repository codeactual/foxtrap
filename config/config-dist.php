<?php

$config = array(
  'db' => array(
    // \Foxtrap\Db\Mysqli
    'class' => 'Mysqli',
    // Passed to mysqli_connect()
    'connect' => array('localhost', 'user', 'pass', 'foxtrap'),
    'testConnect' => array('localhost', 'user', 'pass', 'foxtrap'),
    // Pairs specific to the DB class
    'table' => 'marks',
    'historyTable' => 'searches'
  ),
  'sphinx' => array(
    'connect' => array(
      'host' => 'localhost',
      'port' => 9312,
      'index' => 'foxtrap'
    ),
    'testConnect' => array(
      'host' => 'localhost',
      'port' => 9313,
      'index' => 'foxtrap_test'
    ),

    // SphinxClient::SetFieldWeights() options
    'weights' => array(
      'tags' => 40,
      'title' => 30,
      'uri' => 20,
      'body_clean' => 1
    ),

    // SphinxClient::BuildExcerpts() options
    'excerpts' => array(
      'before_match' => '<span class="excerpt-word">',
      'after_match'	=> '</span>',
      'chunk_separator'	=> ' ... ',
      'limit'	=> 750,
      'around' => 10
    ),

    // bin/foxtrap always ends by running bin/foxtrap-indexer
    'autoindex' => true
  ),
  'curl' => array(
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_FOLLOWLOCATION => 1
  ),
  'htmlpurifier' => array(
    'index' => array(
      'HTML.TidyLevel' => 'none',
      'HTML.Allowed' => '',
      'Cache.SerializerPath' => '/tmp'
    ),
    'viewer' => array(
      'HTML.TidyLevel' => 'none',
      'HTML.AllowedAttributes' => '',
      'Cache.SerializerPath' => '/tmp'
    )
  ),
  'log' => array(
    'class' => '' // ex. 'Stdout' for Log/Stdout.php
  )
);
