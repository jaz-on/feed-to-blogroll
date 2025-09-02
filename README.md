# Feed to Blogroll

[![WordPress](https://img.shields.io/badge/WordPress-6.0+-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2+-green)](https://www.gnu.org/licenses/gpl-2.0.html)

Automatic blogroll synchronization with Feedbin API, integrated with Distributed theme.

> Requirement: Advanced Custom Fields Pro is required for the admin UI and field definitions.

## Features

- **Automatic Synchronization**: Daily sync with Feedbin API
- **Custom Post Type**: Dedicated 'blogroll' post type with ACF fields
- **Responsive Grid Layout**: 4-column desktop, 2-column mobile design
- **OPML Export**: Export your blogroll as OPML file
- **Category Support**: Organize blogs by categories and tags
- **Shortcodes**: Easy integration with `[blogroll]` and `[blogroll_grid]` shortcodes
- **REST API**: Access blogroll data via WordPress REST API
- **Admin Dashboard**: Comprehensive management interface
- **Security**: Nonces, capability checks, and data sanitization
- **Performance**: Optimized caching and conditional loading
- **Accessibility**: WCAG 2.1 AA compliant with ARIA support

## Requirements

- WordPress 6.0 or higher
- PHP 8.2 or higher (minimum requirement)
- Advanced Custom Fields Pro plugin (mandatory)
- Feedbin account with API access

## Installation

1. Upload the plugin files to `/wp-content/plugins/feed-to-blogroll/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to 'Blogroll > Settings' to configure your Feedbin API credentials
4. Use the `[blogroll]` shortcode to display your blogroll on any page

### Security (optional)
- You can define `FEED_TO_BLOGROLL_FETCH_TAGS` in wp-config.php to disable fetching Feedbin tags (reduces API calls):
```php
define( 'FEED_TO_BLOGROLL_FETCH_TAGS', false );
```
When defined, the plugin will skip per-feed tag requests and use only core subscription data.

- You can also define credentials in wp-config.php to avoid storing them in the database:
```php
define( 'FEED_TO_BLOGROLL_USERNAME', 'your-email@example.com' );
define( 'FEED_TO_BLOGROLL_PASSWORD', 'your-secure-password' );
```
When defined, the corresponding fields in Settings are locked (read-only).

## Usage

### Basic Blogroll
```
[blogroll]
```

### Category-Specific Grid
```
[blogroll_grid category="technology" columns="3" limit="9"]
```

### Custom Layout
```
[blogroll columns="2" limit="6" show_export="false"]
```

### Shortcode Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `category` | string | `""` | Filter by category slug |
| `limit` | integer | `-1` | Maximum number of blogs to display |
| `columns` | integer | `4` | Number of columns (1-6) |
| `show_export` | boolean | `true` | Show OPML export button |

### REST API

Access your blogroll data programmatically:

```bash
GET /wp-json/feed-to-blogroll/v1/blogroll
GET /wp-json/feed-to-blogroll/v1/blogroll?category=tech&limit=10
### Blocks

- Block: Blogroll (`feed-to-blogroll/blogroll`)
- Attributes:
  - `category` (string): filter by category slug (default: all)
  - `limit` (number): number of blogs to display (-1 for all)
  - `columns` (number): 1–6 (default: 4)
  - `showExport` (boolean): show OPML export button (default: true)

Insert the “Blogroll” block from the inserter and configure these options in the sidebar. Assets (CSS/JS) load automatically when the block/shortcode is present.

```

## Privacy & Data Handling

### Data Collection
This plugin collects and stores:
- Feedbin API credentials (stored in WordPress options)
- Blog metadata from RSS feeds
- Synchronization logs and timestamps

### Data Retention
- API credentials are stored until removed in settings or plugin uninstallation
- Blog data is retained according to WordPress post lifecycle
- Sync logs are kept for 30 days by default

### Third-party Services
- Feedbin API: Used for RSS feed synchronization
- No data is shared with other third-party services

### GDPR Compliance
- Users can export their blogroll data via OPML
- Users can request data deletion through WordPress admin
- No personal data is collected beyond what's necessary for functionality

### Uninstall

When deleting the plugin from WordPress Admin, all plugin data is removed:
- Options: `feed_to_blogroll_options`
- Custom post type entries (`blogroll`) and related taxonomies
- Scheduled events and transients

## Accessibility

### WCAG 2.1 AA Compliance
- Semantic HTML structure with proper heading hierarchy
- ARIA labels and roles for interactive elements
- Keyboard navigation support for all features
- Screen reader compatibility with descriptive text
- High contrast support and focus indicators
- ARIA-compliant structure for lists and items; keyboard navigation on cards

### Screen Reader Support
- Descriptive alt text for all images
- ARIA live regions for dynamic content updates
- Proper form labeling and error messaging
- Semantic markup for navigation and content structure

### Keyboard Navigation
- Tab order follows logical content flow
- All interactive elements accessible via keyboard
- Skip links for main content areas
- Focus indicators visible on all focusable elements

## Development

### Prerequisites

- PHP 8.2+
- Composer
- WordPress development environment

### Setup

1. Clone the repository:
```bash
git clone https://github.com/jaz-on/feed-to-blogroll.git
cd feed-to-blogroll
```

2. Install dependencies:
```bash
composer install
```

3. Run code quality checks:
```bash
composer phpcs
composer phpcbf
```

### Testing

The plugin follows WordPress coding standards and includes comprehensive error handling. Test thoroughly in a development environment before deploying to production.

## Contributing

We welcome contributions! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

- **Issues**: [GitHub Issues](https://github.com/jaz-on/feed-to-blogroll/issues)
- **Repository**: [GitHub Repository](https://github.com/jaz-on/feed-to-blogroll)

## License

This project is licensed under the GPL v2 or later - see the [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) file for details.

## Author

**Jason Rouet**

- Website: [jasonrouet.local](https://jasonrouet.local)
- GitHub: [@jaz-on](https://github.com/jaz-on)

Built with WordPress best practices and integrated with the Distributed theme.
