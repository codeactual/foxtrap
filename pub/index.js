'use strict';

$(document).ready(function() {
  var acOutput = $('#results-ac-output'),
      history = $('#query-history'),
      q = $('#q'),
      search = $('.search'),
      status = $('.status'),
      layoutToggle = $('.layout-toggle');

  q.autocomplete({
    delay: 100,
    autofocus: true,
    create: function(event, ui) {
      // Allow q?=keyword URI to trigger initial search.
      var uriQuery = q.val();
      if (uriQuery) {
        q.autocomplete('search', uriQuery);
      }
    },
    source: function(request, response) {
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
    item.tags = item.tags ? '(' + item.tags + ')' : '';
    var a = $('<a class="link-wrap"/>'),
        ft = $('<div class="ft" data-id="' + item.id + '"></div>'),
        li = $('<li class="result-template"></li>');

    ft.append('<div class="excerpt">' + item.excerpt + '</div>');
    ft.append('<div class="viewer-toggle"><span class="viewer-toggle-label mark-action-btn">View Saved Copy</span></div>');

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
    e.preventDefault();
    e.stopImmediatePropagation();

    var a = $(this);

    $.ajax({
      url: 'add_history.php',
      dataType: 'jsonp',
      data: { q: q.val() },
      success: function() {
        window.location = a.attr('href');
      },
      error: function() {
        window.location = a.attr('href');
      }
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
  acOutput.on('click', '.viewer-toggle-on', function(e) {
    e.preventDefault();
    e.stopImmediatePropagation();

    var viewerToggle = $(this),
        a = viewerToggle.closest('.link-wrap'),
        ft = viewerToggle.closest('.ft'),
        markId = ft.data('id'),
        iframeId = 'viewer-' + markId,
        iframe = $('#' + iframeId);

    // Scroll to position the search result at the top.
    a.get(0).scrollIntoView(true);

    // Hide/show the iframe if it's already in the DOM.
    if (iframe.length) {
      iframe.toggle();
      return;
    }

    var src = 'view.php?markId=' + markId;

    // Remove previously opened saved copy.
    $('iframe', acOutput).remove();

    // Add the new saved copy.
    ft.append('<iframe id="' + iframeId + '" src="' + src +  '"/>');
  });

  // Populate and submit the search box with a prior query.
  $('#query-history').on('click', '.past-query', function(event) {
    event.preventDefault();

    var query = $(this);
    q.val(query.text());
    q.autocomplete('search', query.text());
  });

  // Swap search/status elements.
  layoutToggle.on('click', function(event) {
    event.preventDefault();
    status.toggle();
    search.toggle();
  });

  // Populate history.
  $.ajax({
    url: 'get_history.php',
    dataType: 'jsonp',
    success: function(data) {
      if (data.length) {
        $.each(data, function(pos, item) {
          history.append('<a class="past-query" href="#query-' + item.id + '">' + item.query + '</a>');
        });
      }
    }
  });

  // Populate error log.
  $.ajax({
    url: 'get_error_log.php',
    dataType: 'jsonp',
    success: function(data) {
      if (data.length) {
        var ul = $('#error-log');
        $.each(data, function(pos, item) {
          var matches = item.last_err.match(/^([^\{]+)(.*)$/),
              message = matches[1].replace(/: $/, ''),
              detail = JSON.parse(matches[2]),
              li = $('<li id="error-log-' + item.id + '" class="error-log" />');

          message = message || 'HTTP code: ' + detail.http_code;

          li.append(
            $('<div class="title"/>')
            .append('<a class="retry mark-action-btn" href="#" data-id="' + item.id + '">retry</a>')
            .append(item.title)
          );
          li.append('<div class="uri">' + item.uri + '</div>')
          li.append('<div class="message">' + message + '</div>')
          ul.append(li);
        });
      }
    }
  });

  // Remove an mark's error state.
  $('#error-log').on('click', '.retry', function(event) {
    event.preventDefault();

    var markId = $(this).data('id'),
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
});
