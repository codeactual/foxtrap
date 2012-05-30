<?php
$q = empty($_GET['q']) ? '' : $_GET['q'];
header('Content-type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
  <title>Foxtrap</title>
  <meta charset="utf-8" />
  <link rel="stylesheet" type="text/css" href="yui-grids-reset.css" />
  <link type="text/css" href="index.css" rel="stylesheet" />
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon" />
</head>
<body>
<div class="yui3-g">
  <div class="yui3-u-1-8">
    <div id="logo">foxtrap</div>
    <div id="query-history" class="history-group float-left">&nbsp;</div>
  </div>
  <div class="yui3-u-7-8">
    <div class="query-group">
      <input id="q" type="text" value="<?php echo $q; ?>"/>
    </div>
    <div id="results" class="results-group float-left">
      <ul id="results-ac-output"></ul>
    </div>
  </div>
</div>
<script src="jquery.min.js" charset="utf-8"></script>
<script src="jquery-ui.min.js" charset="utf-8"></script>
<script src="index.js" charset="utf-8"></script>
</body>
</html>
