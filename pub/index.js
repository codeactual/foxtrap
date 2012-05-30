'use strict';

$(document).ready(function() {
  var acOutput = $('#results-ac-output');
  var q = $('#q');
  var history = $('#query-history');

  var refreshHistory = function() {
    $.ajax({
      url: 'get_history.php',
      dataType: 'jsonp',
      success: function(data) {
        if (data.length) {
          history.empty();
          jQuery.each(data, function(pos, item) {
            history.append('<a class="past-query" href="#query-' + item.id + '">' + item.query + '</a>');
          });
        }
      }
    });
  };

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
        },
      });
    }
  })
  .data('autocomplete')._renderItem = function(ul, item) {
    item.tags = item.tags ? '(' + item.tags + ')' : '';
    var a = $('<a class="link-wrap"/>'),
        ft = $('<div class="ft" data-id="' + item.id + '"></div>'),
        li = $('<li class="result-template"></li>');

    ft.append('<div class="excerpt">' + item.excerpt + '</div>');
    ft.append('<div class="viewer-toggle"><span class="viewer-toggle-label">View Saved Copy</span></div>');

    a.attr('href', item.uri);
    a.appendTo(li);
    a.data('item.autocomplete', item)
      .append('<div class="hd">' + item.title + '</div>')
      .append('<div class="bd">' + item.domain + ' <span class="tags">' + item.tags + '</span></div>')
      .append(ft);

    li.appendTo(acOutput);
  };

  // Open a new tab with the marked URI.
  acOutput.delegate('.link-wrap', 'click', function(e) {
    var a = $(this);

    $('.opened-result', acOutput).removeClass('opened-result');
    a.addClass('opened-result');

    window.open(a.attr('href'));
    $.ajax({
      url: 'add_history.php',
      dataType: 'jsonp',
      data: { q: q.val() },
      success: function() {
        refreshHistory();
      }
    });
    e.preventDefault();
  });

  // Reveal 'View Saved Copy' links when hovering over a result.
  acOutput.delegate(
    'a',
    'hover',
    function(e) {
      $('.viewer-toggle-on', acOutput).removeClass('viewer-toggle-on');
      $('.viewer-toggle', this).addClass('viewer-toggle-on');
    }
  );

  // Display the saved copy content below the 'View Saved Copy' link.
  acOutput.delegate('.viewer-toggle-on', 'click', function(e) {
    e.preventDefault();
    e.stopImmediatePropagation();

    // Remove previously opened saved copy.
    $('iframe', acOutput).slideUp('fast', function() {
      $(this).remove();
    });

    // Add the current saved copy.
    var viewerToggle = $(this),
        a = viewerToggle.closest('.link-wrap'),
        ft = viewerToggle.closest('.ft'),
        markId = ft.data('id'),
        iframeId = 'viewer-' + markId,
        src = 'view.php?markId=' + markId;
    ft.append('<iframe id="' + iframeId + '" src="' + src +  '"/>');
    $('#' + iframeId).slideDown('fast');

    // Visually group the search result and the related saved copy.
    $('.opened-result', acOutput).removeClass('opened-result');
    a.addClass('opened-result');
  });


  // Populate and submit the search box with a prior query.
  $('#query-history').on('click', '.past-query', function(event) {
    var query = $(this);
    event.preventDefault();
    q.val(query.text());
    q.autocomplete('search', query.text());
  });

  refreshHistory();
});
