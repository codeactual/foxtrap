# foxtrap

foxtrap imports Firefox bookmark backup files and downloads all pages for full-text indexing of titles, URIs, tags, and body text.

* "Instant" results as you type.
* Keyword history ranked by queries that led to a clicked link.
* Sanitized copy of the archived page viewable under each search result.

![screenshot: saved copy viewer](http://codeactual.github.com/foxtrap/images/saved-copy.png)

## Usage

`foxtrap --file bookmarks-YYYY-MM-DD.json`

## Requirements

* PHP 5.3+
* Sphinx Search 0.9.9+
* MySQL 5.1+

## Setup

1. git submodule update --init
1. Create a new database and import `config/foxtrap.sql`.
1. Copy `config/config-dist.php` to `config/config.php` and customize `db` and `sphinx` sections.
1. Copy `config/sphinx-dist.conf` to `config/sphinx.conf` and replace the placeholders.
1. [Export bookmarks as JSON.](http://support.mozilla.com/en-US/kb/Backing%20up%20and%20restoring%20bookmarks#w_manual-backup)
1. `foxtrap --file bookmarks.json`
1. Start Sphinx:`bin/foxtrap-searchd-start`
1. Setup a document root at `pub/`.
1. (Recommended) Set up a cron to run `foxtrap` on the latest JSON file periodically created by Firefox in `bookmarkbackups/` under your profile's directory.

## Running Tests

1. Create a new test database and import `config/foxtrap.sql`.
1. Customize `testConnect` values in `config/config.php`.
1. Copy `config/sphinx-test-dist.conf` to `config/sphinx-test.conf` and replace the placeholders.
1. Start Sphinx (w/ test config):`bin/foxtrap-test-searchd-start`
1. `phpunit -c test/unit/phpunit.xml test/unit`

## Altering the real-time index

1. Remove old index files.
1. Stop `searchd`.
1. Update sphinx configuration files, ex. new `rt_attr_timestamp` attribute.
1. Start `searchd`.
1. `bin/foxtrap-seed-rtindex`
