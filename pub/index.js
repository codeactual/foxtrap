'use strict';

var Y = new YUI();

YUI().use('autocomplete', 'jsonp', 'array-extras', 'event-delegate', function(Y) {
  var api = {
    // Refresh left-panel list of click-generating queries
    refreshHistory: function (response) {
      var template =
          '<a class="past-query" href="#">#{query}</a>';
      if (response.length) {
        response = Y.Array.map(response, function (element) {
          return Y.Lang.sub(template, {
            query: element.query
          });
        });
        var histNode = Y.Node.one('#query-history');
        histNode.empty(true);
        for (var n = 0; n < response.length; n++) {
          histNode.append(response[n]);
        }
      }
    },

    // Format each bookmark result based on JSONP results
    ftFormatter : function(query, results) {
      var template =
        '<div class="result-template">' +
          '<div class="hd">' +
              '{title}' +
          '</div>' +
          '<div class="bd">' +
            '{domain} <span class="tags">{tags}</span>' +
          '</div>' +
          '<div class="ft">{excerpt}</div>' +
        '</div>';
      return Y.Array.map(results, function (result) {
        return Y.Lang.sub(template, {
          title: result.raw.title,
          excerpt: result.raw.excerpt,
          domain: result.raw.domain,
          tags: result.raw.tags ? '(' + result.raw.tags + ')' : result.raw.tags
        });
      });
    },

    // After clicks to the links in the list generated by refreshHistory()
    onHistoryClick: function(e) {
      var pastQuery = e.target.get('innerHTML').replace(/#/, '');
      var qNode = Y.Node.one('#q');
      qNode.set('value', pastQuery);
      qNode.ac.sendRequest(pastQuery);
    },
  };

  Y.on('domready', function() {
    var qNode = Y.Node.one('#q');

    qNode.focus();

    qNode.plug(Y.Plugin.AutoComplete, {
      align: {
        node: '#results-ac-output',
        points: ['tl', 'tl']
      },
      resultFormatter: api.ftFormatter,
      source: '/q.php?q={query}&callback={callback}',
      alwaysShowList: true,
      queryDelay: 5
    });

    // Open up the bookmarked URI after clicking a result
    qNode.ac.on('select', function (e) {
      e.preventDefault();
      window.open(e.result.raw.uri);

      var currentQuery = Y.Node.one('#q').get('value');
      var url =
        '/add_history.php?callback={callback}&q='
        + encodeURIComponent(currentQuery);
      Y.jsonp(url, function(response) {
        api.refreshHistory(response);
      });
    });

    // If URI argument 'q' was set and prepopulated the input box,
    // so automatically fire off a request for results
    var initialQuery = qNode.get('value');
    if (initialQuery) {
      qNode.ac.sendRequest(initialQuery);
    }

    // Use delegation in the history links container so event handling applies
    // even after successive refreshHistory() calls
    Y.delegate("click", api.onHistoryClick, "#query-history", ".past-query");

    // Grab the query history
    Y.jsonp('/get_history.php?callback={callback}', function(response) {
      api.refreshHistory(response);
    });
  });
});
