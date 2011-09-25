# foxtrap

foxtrap imports s Firefox bookmark backup and downloads all pages for full-text indexing.

## Usage

1. `foxtrap --file bookmarks-YYYY-MM-DD.json`

## Requirements

* PHP 5.3+
* Sphinx Search 0.9.9+
* MySQL 5.1+

## Setup

1. Create a new database and import `config/foxtrap.sql`.
1. Customize the "dist" configuration files under `config/`.
1. [Export bookmarks as JSON.](http://support.mozilla.com/en-US/kb/Backing%20up%20and%20restoring%20bookmarks#w_manual-backup)
1. Run `foxtrap`.
1. Setup a document root at `pub/`, e.g. a local Apache VirtualHost named "foxtrap".
1. Open `http://foxtrap/`.

# Recommended

Set up a cron to run `foxtrap` on the latest JSON file periodically created by Firefox in `bookmarkbackups/` under your profile's directory.