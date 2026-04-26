# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Changed

- Standardized plugin identifiers on **feed-blogroll** across plugin bootstrap, text domain, REST namespace, WP-Cron/AJAX hooks, Composer metadata, front-end CSS classes, block name, and `FEED_BLOGROLL_*` wp-config constants.

## [1.2.0] - 2026-04-20

### Added

- Custom weekly cron schedule and `Feed_Blogroll_Plugin::reschedule_sync_cron()` so **Sync frequency** (twice daily, daily, weekly) and **Automatic synchronization** match the real WP-Cron event; `plugins_loaded` version option migrates existing installs.
- `last_sync_stats` stored under `feed_blogroll_options` for dashboard “last sync run” counts; per-post `last_sync` / `sync_status` updated on create, update, and Feedbin removal (draft + inactive).
- Shared `Feed_Blogroll_OPML` helper; public REST `GET /wp-json/feed-blogroll/v1/opml` (filterable via `feed_blogroll_rest_opml_permission`); front-end export uses `fetch()` to this endpoint.
- `feed_blogroll_merge_saved_options()` and PHPUnit scaffold (`phpunit.xml.dist`, `tests/`) for option merge behavior.
- Blogs and Export admin tabs with real content; plugin DB version option `feed_blogroll_plugin_version`.

### Changed

- Settings sanitize callback **merges** into existing `feed_blogroll_options` so `last_sync`, `sync_status`, and stats are not wiped on save; checkbox `auto_sync` uses a hidden `0` value when unchecked.
- Feedbin API status persistence: `feed_blogroll_api_connected` and `feed_blogroll_api_last_error` updated when the hourly connection test runs.
- REST `GET .../blogroll` permission is filterable via `feed_blogroll_rest_blogroll_permission` (default public).
- Post meta registration uses `object_subtype` `blogroll` for `rss_url`, `site_url`, `feed_id`, `sync_status`, `last_sync`.
- CPT capabilities are added on activation / upgrade only (removed per-request `init` cap grants).
- Template class always loaded so REST routes register in admin contexts; `wp_cache_flush_group` called only when available; OPML transient busted on blogroll cache bust.
- **Requires WordPress 6.1+** (plugin header, README, `plugin.json`); removed unused `feedbin_api_key` default; Composer autoload PSR-4 entries removed (classes use manual requires).
- Uninstall deletes extended options/transients and no longer relies on `current_user_can()` during uninstall.

## [1.1.0] - 2026-04-18

### Added

- Plugin headers `GitHub Plugin URI` and `Primary Branch` for [Git Updater](https://github.com/git-updater/git-updater) (track updates from GitHub; `dev` uses the `dev` branch, `main` uses `main` after release).
- [GitHub Actions](https://github.com/jaz-on/feed-blogroll/actions) workflow running `composer phpcs` on pushes and pull requests to `main` and `dev`.
- [`.gitattributes`](.gitattributes) `export-ignore` rules so `git archive` ZIPs omit Composer, PHPCS, and other development-only paths (aligned with [`.distignore`](.distignore)).

### Changed

- README: branch strategy (`main` stable vs `dev` integration), Git Updater installation, manual verification of updates, and fixed REST API / block documentation formatting.
- Aligned plugin version to **1.1.0** across the main plugin file, `FEED_BLOGROLL_VERSION`, `block.json`, `plugin.json`, and the POT header.
- PHPCS: increased `absoluteLineLimit` for long admin HTML/translatable strings; PHPCBF fixes in CPT, template, and main plugin class.

## [1.0.0] - Initial release

- Feedbin API synchronization, `blogroll` custom post type with native meta fields (no ACF dependency).
- Admin UI, shortcodes `[blogroll]` / `[blogroll_grid]`, block `feed-blogroll/blogroll`, REST API, OPML export, categories/tags, cron sync, security hardening, caching, and accessibility-oriented markup.
