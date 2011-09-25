<?php
$q = empty($_GET['q']) ? '' : $_GET['q'];
header('Content-type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
  <title>Foxtrap</title>
  <meta charset="utf-8" />
  <link href="http://fonts.googleapis.com/css?family=Droid+Sans" rel="stylesheet" type="text/css">
  <link type="text/css" href="index.css" rel="stylesheet" />
  <script src="http://yui.yahooapis.com/3.4.0/build/yui/yui-min.js" charset="utf-8"></script>
  <script src="index.js" charset="utf-8"></script>
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon" />
</head>
<body>
<div class="query-group">
  <input id="q" type="text" value="<?php echo $q; ?>"/>
</div>
<div>
  <div id="query-history" class="history-group float-left">&nbsp;</div>
  <div class="results-group float-left">
    <div id="results-ac-output"></div>
  </div>
  <div class="float-clear"></div>
</div>
</body>
</html>
