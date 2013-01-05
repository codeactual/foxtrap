<?php $q = empty($_GET['q']) ? '' : $_GET['q']; ?>
<!doctype html>
<html lang="en">
<head>
  <title>Foxtrap</title>
  <meta charset="utf-8" />
  <link rel="stylesheet" type="text/css" href="yui-grids-reset.css" />
  <link type="text/css" href="bootstrap.css" rel="stylesheet" />
  <link type="text/css" href="index.css" rel="stylesheet" />
  <link rel="shortcut icon" href="/favicon.png" type="image/png" />
</head>
<body>
<div class="yui3-g">
  <div id="sidebar" class="yui3-u-1-8">
    <div id="logo"><a href="/">foxtrap</a></div>
    <div><a class="layout-toggle" href="#">status</a></div>
    <div id="marks-count"></div>
    <div id="last-download-age"></div>
    <div><a class="compose-mark-open btn btn-primary" href="#">+ Add</a></div>
    <div class="sidebar-head">Tags</div>
    <div id="taglist" class="taglist-group search"></div>
    <div class="sidebar-head">History</div>
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
<div id="compose-mark-modal" class="modal hide" tabindex="-1">
  <div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
    <h3></h3>
  </div>
  <form class="form-horizontal compose-mark-form">
    <input type="hidden" name="markId" />
    <div class="modal-body">
      <div class="control-group">
        <div class="controls">
          <input type="text" name="uri" placeholder="URI" />
        </div>
      </div>
      <div class="control-group">
        <div class="controls">
          <input type="text" name="title" placeholder="Title" />
        </div>
      </div>
      <div class="control-group">
        <div class="controls">
          <input type="text" name="tags" placeholder="Tags" />
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="submit" class="btn btn-primary compose-mark-submit">Add</button>
    </div>
  </form>
</div>
<script src="jquery.min.js" charset="utf-8"></script>
<script src="jquery-ui.min.js" charset="utf-8"></script>
<script src="bootstrap.js" charset="utf-8"></script>
<script src="moment.min.js" charset="utf-8"></script>
<script src="index.js" charset="utf-8"></script>
</body>
</html>
