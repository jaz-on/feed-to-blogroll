# Feed to Blogroll

[![WordPress](https://img.shields.io/badge/WordPress-6.5+-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2+-green)](https://www.gnu.org/licenses/gpl-2.0.html)

Automatic blogroll synchronization with Feedbin API, integrated with Distributed theme.

## Features

- **Automatic Synchronization**: Daily sync with Feedbin API
- **Custom Post Type**: Dedicated 'blogroll' post type with ACF fields
- **Responsive Grid Layout**: 4-column desktop, 2-column mobile design
- **OPML Export**: Export your blogroll as OPML file
- **Category Support**: Organize blogs by categories
- **Shortcodes**: Easy integration with `[blogroll]` and `[blogroll_grid]` shortcodes
- **REST API**: Access blogroll data via WordPress REST API
- **Admin Dashboard**: Comprehensive management interface
- **Security**: Nonces, capability checks, and data sanitization

## Requirements

- WordPress 6.0 or higher
- PHP 8.2 or higher
- Advanced Custom Fields Pro plugin
- Feedbin account with API access

## Installation

1. Upload the plugin files to `/wp-content/plugins/feed-to-blogroll/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to 'Blogroll > Settings' to configure your Feedbin API credentials
4. Use the `[blogroll]` shortcode to display your blogroll on any page

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
```

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
