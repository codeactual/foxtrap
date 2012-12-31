'use strict';

$(document).ready(function() {
  var acOutput = $('#results-ac-output'),
      qHistory = $('#query-history'),
      q = $('#q'),
      body = $('body'),
      search = $('.search'),
      status = $('.status'),
      layoutToggle = $('.layout-toggle'),
      lastPushState = null,
      pushQueryState = function(query) {
        var state = {q: query};

        // Avoid duplicate states, ex. user clicks a history link and then
        // a search result associated with the same query.
        if (JSON.stringify(lastPushState) == JSON.stringify(state)) {
          return;
        }

        lastPushState = $.extend({}, state);
        history.pushState(lastPushState, '', '?q=' + encodeURIComponent(query));
      },
      reDownloadMsg = 'Will download in next cycle.';

  var openComposeMarkModal = function() {
    $('#compose-mark-modal').modal();

    // Appending ":first" to selector will select the title input for some reason.
    $('#compose-mark-modal input[type="text"]:enabled')[0].focus();
  };

  q.autocomplete({
    delay: 100,
    create: function(event, ui) {
      // Allow 'q?=keyword' URI to trigger initial search.
      var uriQuery = q.val();
      if (uriQuery) {
        // Avoid duplicate states, ex. user goes directory to 'q?=css' and then
        // a search result associated with the same query.
        lastPushState = {q: uriQuery};

        q.autocomplete('search', uriQuery);
      }
    },
    source: function(request, response) {
      pushQueryState(request.term);

      $.ajax({
        url: '/q.php',
        dataType: 'jsonp',
        data: {
          q: request.term
        },
        success: function(data) {
          acOutput.empty();
          response(data);
        }
      });
    }
  })
  .data('autocomplete')._renderItem = function(ul, item) {
    item.tags = item.tags ? item.tags : '';
    var a = $('<a class="link-wrap" target="_blank"/>'),
        ft = $('<div class="ft" data-id="' + item.id + '" data-deleted="' + item.deleted + '"></div>'),
        li = $('<li class="result-template"></li>'),
        dlBtnMsg = item.downloaded ? 'Download Again' : reDownloadMsg;

    ft.append('<div class="excerpt">' + item.excerpt + '</div>');

    var viewerToggle = $('<div class="viewer-toggle"/>');
    viewerToggle.append('<span class="viewer-edit mark-action-btn">Edit</span>')

    if (!/nosave/.test(item.tags)) {
      viewerToggle
        .append('<span class="viewer-view-copy mark-action-btn">View Saved Copy</span>')
        .append('<span class="viewer-dl-again mark-action-btn">' + dlBtnMsg + '</span>');
    }

    viewerToggle.append('<span class="viewer-delete mark-action-btn mark-delete-btn">' + (item.deleted ? 'Cancel Deletion' : 'Delete') + '</span>');

    ft.append(viewerToggle);

    a.attr('href', item.uri);
    a.appendTo(li);
    a.data('item.autocomplete', item)
      .append('<div class="hd title">' + item.title + '</div>')
      .append('<div class="bd uri">' + item.domain + ' <span class="tags">' + item.tags + '</span></div>')
      .append(ft);

    li.appendTo(acOutput);
  };

  // Open a new tab with the marked URI.
  acOutput.delegate('.link-wrap', 'click', function(e) {
    var a = $(this),
        openUri = function() {
          pushQueryState(q.val());
        };

    $.ajax({
      url: 'add_history.php',
      dataType: 'jsonp',
      data: { q: q.val() },
      success: openUri,
      error: openUri
    });
  });

  // Reveal 'View Saved Copy' links when hovering over a result.
  acOutput.on(
    'hover',
    'a',
    function(e) {
      $('.viewer-toggle-on', acOutput).removeClass('viewer-toggle-on');
      $('.viewer-toggle', this).addClass('viewer-toggle-on');
    }
  );

  // Display the saved copy content below the 'View Saved Copy' link.
  acOutput.on('click', '.viewer-view-copy', function(e) {
    e.preventDefault();
    e.stopImmediatePropagation();

    var viewerToggle = $(this),
        a = viewerToggle.closest('.link-wrap'),
        ft = viewerToggle.closest('.ft'),
        markId = ft.data('id'),
        iframeId = 'viewer-' + markId,
        iframe = $('#' + iframeId),
        scrollResultIntoView = function() {
          // Scroll to position the search result at the top.
          a.get(0).scrollIntoView(true);
        };

    // Hide/show the iframe if it's already in the DOM.
    if (iframe.length) {
      iframe.toggle();
      scrollResultIntoView();
      return;
    }

    var src = 'view.php?markId=' + markId;

    // Remove previously opened saved copy.
    $('iframe', acOutput).remove();

    // Add the new saved copy.
    ft.append('<iframe id="' + iframeId + '" src="' + src +  '"/>');

    scrollResultIntoView();
  });

  // Open Edit Mark modal.
  acOutput.on('click', '.viewer-edit', function(e) {
    event.preventDefault();
    e.stopImmediatePropagation();

    var editBtn = $(this);
    var linkWrap = editBtn.parents('.link-wrap');
    $('.compose-mark-form input[name="uri"]').val(linkWrap.attr('href')).attr('readonly', 'readonly');
    $('.compose-mark-form input[name="title"]').val($('div.title', linkWrap).text()).removeAttr('readonly');
    $('.compose-mark-form input[name="tags"]').val($('span.tags', linkWrap).text()).removeAttr('readonly');
    $('#compose-mark-modal h3').text('Edit Mark');
    $('#compose-mark-modal button[type="submit"]').text('Edit');
    openComposeMarkModal();
  });

  // Open Delete Mark modal.
  acOutput.on('click', '.viewer-delete', function(e) {
    event.preventDefault();
    e.stopImmediatePropagation();
    var delBtn = $(this);
    var linkWrap = delBtn.parents('.link-wrap');
    var ft = delBtn.closest('.ft');
    var markId = ft.data('id');
    $('.compose-mark-form input[name="markId"]').val(markId);;
    $('.compose-mark-form input[name="uri"]').val(linkWrap.attr('href'));
    $('.compose-mark-form input[name="title"]').val($('div.title', linkWrap).text());
    $('.compose-mark-form input[name="tags"]').val($('span.tags', linkWrap).text());
    $('.compose-mark-form input[type="text"]').attr('readonly', 'readonly');
    if (ft.data('deleted')) {
      $('#compose-mark-modal h3').text('Cancel Mark Deletion');
      $('#compose-mark-modal button[type="submit"]').text('Cancel');
    } else {
      $('#compose-mark-modal h3').text('Delete Mark');
      $('#compose-mark-modal button[type="submit"]').text('Delete');
    }
    openComposeMarkModal();
  });

  // Flag a mark for re-download.
  acOutput.on('click', '.viewer-dl-again', function(e) {
    e.preventDefault();
    e.stopImmediatePropagation();

    var reDownloadBtn = $(this),
        ft = reDownloadBtn.closest('.ft'),
        markId = ft.data('id');

    $.ajax({
      url: 'redownload.php',
      dataType: 'jsonp',
      data: {
        markId: markId
      },
      success: function(data) {
        if (data.scheduled) {
          reDownloadBtn.text(reDownloadMsg);
        } else {
          reDownloadBtn.text('Could not schedule download.');
        }
      },
      error: function() {
        reDownloadBtn.text('Could not contact server.');
      }
    });
  });

  // Populate and submit the search box with a prior query.
  $('#query-history').on('click', '.past-query', function(event) {
    event.preventDefault();

    var query = $(this),
        qText = query.text();
    q.val(qText);
    q.autocomplete('search', qText);
    pushQueryState(qText);
  });

  // Swap search/status elements.
  layoutToggle.on('click', function(event) {
    event.preventDefault();
    status.toggle();
    search.toggle();
  });

  var focusSearch = function() {
    q.focus();
  };

  // Swap search/status elements.
  body.on('click', '.compose-mark-open', function(event) {
    event.preventDefault();
    $('.compose-mark-form input[type="text"]').val('').removeAttr('readonly');
    $('#compose-mark-modal h3').text('Add Mark');
    $('#compose-mark-modal button[type="submit"]').text('Add');
    openComposeMarkModal();
  });

  body.on('submit', '.compose-mark-form', function(event) {
    event.preventDefault();

    var mode = $('.compose-mark-submit', $(this)).text();

    if ('Cancel' === mode || 'Delete' === mode) {
      $.ajax({
        url: '/delete_mark.php',
        type: 'POST',
        dataType: 'jsonp',
        data: $(this).serialize(),
        success: function() {
          $('#compose-mark-modal').modal('hide');
          focusSearch();
        }
      });
    } else {
      $.ajax({
        url: '/add_mark.php',
        type: 'POST',
        dataType: 'jsonp',
        data: $(this).serialize(),
        success: function() {
          $('#compose-mark-modal').modal('hide');
          focusSearch();
        }
      });
    };
  });

  body.on('keydown', function(e) {
    if (e.ctrlKey) {
      if (65 === e.keyCode) { // ctrl-a
        openAddMarkModal();
      } else if (83 === e.keyCode) { // ctrl-s
        focusSearch();
      }
    }
  });

  $('#compose-mark-modal').on('hidden', function() {
    focusSearch();
  });

  // Populate history.
  $.ajax({
    url: 'get_history.php',
    dataType: 'jsonp',
    success: function(data) {
      if (data.length) {
        $.each(data, function(pos, item) {
          qHistory.append('<a class="past-query" href="#query-' + item.id + '">' + item.query + '</a>');
        });
      }
    }
  });

  var populateErrorLog = function() {
    // Populate error log.
    $.ajax({
      url: 'get_error_log.php',
      dataType: 'jsonp',
      success: function(data) {
        var ul = $('#error-log');

        ul.empty();

        if (!data.length) {
          ul.append('<li>No errors.</li>');
          return;
        }

        $.each(data, function(pos, item) {
          var matches = item.last_err.match(/^([^\{]+)(.*)$/),
            message = matches[1].replace(/: $/, ''),
            detail = JSON.parse(matches[2]),
            li = $('<li id="error-log-' + item.id + '" class="error-log" />');

            message = message || 'HTTP code: ' + detail.http_code;

            li.append(
              $('<div class="title" data-id="' + item.id + '" data-title="' + item.title + '" data-uri="' + item.uri + '" data-tags="' + item.tags + '" data-deleted="' + item.deleted + '" />')
              .append('<a class="retry mark-action-btn" href="#">Retry</a>')
              .append('<a class="edit mark-action-btn" href="#">Edit</a>')
              .append('<a class="delete mark-action-btn mark-delete-btn" href="#">' + (item.deleted ? 'Cancel Deletion' : 'Delete') + '</a>')
              .append('<a class="uri" href="' + item.uri + '">' + item.title + '</a>')
              .append(' <span class="tags">' + item.tags + '</span>')
            );
            li.append('<div><a class="uri" href="' + item.uri + '">' + item.uri + '</a></div>')
            li.append('<div class="message">' + message + '</div>')
            ul.append(li);
        });
      }
    });
  };

  // Allow mark editing from the error log.
  $('#error-log').on('click', '.edit', function(event) {
    event.preventDefault();
    event.stopImmediatePropagation();

    var title = $(this).parents('.title');

    $('.compose-mark-form input[name="uri"]').val(title.data('uri')).attr('readonly', 'readonly');
    $('.compose-mark-form input[name="title"]').val(title.data('title')).removeAttr('readonly');
    $('.compose-mark-form input[name="tags"]').val(title.data('tags')).removeAttr('readonly');
    $('#compose-mark-modal h3').text('Edit Mark');
    $('#compose-mark-modal button[type="submit"]').text('Edit');
    openComposeMarkModal();
  });

  // Allow mark deleting from the error log.
  $('#error-log').on('click', '.delete', function(event) {
    event.preventDefault();
    event.stopImmediatePropagation();

    var title = $(this).parents('.title');

    $('.compose-mark-form input[name="markId"]').val(title.data('id'));
    $('.compose-mark-form input[name="uri"]').val(title.data('uri')).attr('readonly', 'readonly');
    $('.compose-mark-form input[name="title"]').val(title.data('title')).removeAttr('readonly');
    $('.compose-mark-form input[name="tags"]').val(title.data('tags')).removeAttr('readonly');
    if (title.data('deleted')) {
      $('#compose-mark-modal h3').text('Cancel Mark Deletion');
      $('#compose-mark-modal button[type="submit"]').text('Cancel');
    } else {
      $('#compose-mark-modal h3').text('Delete Mark');
      $('#compose-mark-modal button[type="submit"]').text('Delete');
    }
    openComposeMarkModal();
  });

  // Remove an mark's error state.
  $('#error-log').on('click', '.retry', function(event) {
    event.preventDefault();

    var markId = $(this).parents('.title').data('id'),
        logItem = $('#error-log-' + markId),
        logBtn = $('.mark-action-btn', logItem);

    $.ajax({
      url: '/retry.php',
      dataType: 'jsonp',
      data: {
        markId: markId
      },
      success: function(data) {
        if (data.removed) {
          logBtn.remove();
          logItem.addClass('invalid');
        } else {
          logBtn.text('Could not schedule retry.');
        }
      },
      error: function() {
        logBtn.text('Could not contact server.');
      }
    });
  });

  // Respond to back-navigation by applying the saved (search query) state.
  window.onpopstate = function(e) {
    if (e.state && e.state.q) {
      q.val(e.state.q);
      q.autocomplete('search', e.state.q);
    }
  };
;
  populateErrorLog();
  focusSearch();
});
