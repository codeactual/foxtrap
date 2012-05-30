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
          $('#results-ac-output').empty();
          response(data);
        },
      });
    }
  })
  .data('autocomplete')._renderItem = function(ul, item) {
    item.tags = item.tags ? '(' + item.tags + ')' : '';
    var a = $('<a class="link-wrap"/>'),
        li = $('<li class="result-template"></li>');

    a.attr('href', item.uri);
    a.appendTo(li);
    a.data('item.autocomplete', item)
      .append('<div class="hd">' + item.title + '</div>')
      .append('<div class="bd">' + item.domain + ' <span class="tags">' + item.tags + '</span></div>')
      .append('<div class="ft">' + item.excerpt + '</div>')

    li.appendTo(acOutput);
  };

  $('#results-ac-output').delegate('a.link-wrap', 'click', function(e) {
    var a = $(this);

    $('#results-ac-output .opened-result').removeClass('opened-result');
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

  $('#query-history').on('click', '.past-query', function(event) {
    event.preventDefault();
    q.val($(this).text());
    q.autocomplete('search', $(this).text());
  });

  refreshHistory();
});
