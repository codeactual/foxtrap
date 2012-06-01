<?php $q = empty($_GET['q']) ? '' : $_GET['q']; ?>
<!doctype html>
<html lang="en">
<head>
  <title>Foxtrap</title>
  <meta charset="utf-8" />
  <link rel="stylesheet" type="text/css" href="yui-grids-reset.css" />
  <link type="text/css" href="index.css" rel="stylesheet" />
  <link rel="shortcut icon" href="/favicon.png" type="image/png" />
</head>
<body>
<div class="yui3-g">
  <div id="sidebar" class="yui3-u-1-8">
    <div id="logo">foxtrap</div>
    <div><a class="layout-toggle" href="#">status</a></div>
    <div id="query-history" class="history-group search"></div>
  </div>
  <div class="yui3-u-7-8">
    <?php /* Search elements */ ?>
    <div class="query-group search">
      <input id="q" type="text" value="<?php echo $q; ?>"/>
    </div>
    <div id="results" class="results-group search">
      <ul id="results-ac-output"></ul>
    </div>
    <?php /* Status elements */ ?>
    <ul id="error-log" class="status"></ul>
  </div>
</div>
<script src="jquery.min.js" charset="utf-8"></script>
<script src="jquery-ui.min.js" charset="utf-8"></script>
<script src="index.js" charset="utf-8"></script>
</body>
</html>
