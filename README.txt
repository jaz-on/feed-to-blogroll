=== Feed to Blogroll ===
Contributors: jasonrouet
Tags: blogroll, rss, feedbin, api, synchronization
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatic blogroll synchronization with Feedbin API, integrated with Distributed theme.

== Description ==

Feed to Blogroll is a powerful WordPress plugin that automatically synchronizes your blogroll with your Feedbin RSS reader subscriptions. It creates a beautiful, responsive grid layout of blogs with automatic updates and OPML export functionality.

> Requires Advanced Custom Fields Pro for the admin UI and field definitions.

= Features =

* **Automatic Synchronization**: Daily sync with Feedbin API
* **Custom Post Type**: Dedicated 'blogroll' post type with ACF fields
* **Responsive Grid Layout**: 4-column desktop, 2-column mobile design
* **OPML Export**: Export your blogroll as OPML file
* **Category Support**: Organize blogs by categories and tags
* **Shortcodes**: Easy integration with [blogroll] and [blogroll_grid] shortcodes
* **REST API**: Access blogroll data via WordPress REST API
* **Admin Dashboard**: Comprehensive management interface
* **Security**: Nonces, capability checks, and data sanitization
* **Performance**: Optimized caching and conditional loading
* **Accessibility**: WCAG 2.1 AA compliant with ARIA support

= Requirements =

* WordPress 6.0 or higher
* PHP 8.2 or higher
* Advanced Custom Fields Pro plugin
* Feedbin account with API access

= Installation =

1. Upload the plugin files to `/wp-content/plugins/feed-to-blogroll/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to 'Blogroll > Settings' to configure your Feedbin API credentials
4. Use the [blogroll] shortcode to display your blogroll on any page

= Security (optional) =
You can define `FEED_TO_BLOGROLL_FETCH_TAGS` in wp-config.php to disable fetching Feedbin tags (reduces API calls):
```
define( 'FEED_TO_BLOGROLL_FETCH_TAGS', false );
```
When defined, the plugin will skip per-feed tag requests and use only core subscription data.

You can also define credentials in wp-config.php to avoid storing them in the database:
```
define( 'FEED_TO_BLOGROLL_USERNAME', 'your-email@example.com' );
define( 'FEED_TO_BLOGROLL_PASSWORD', 'your-secure-password' );
```
When defined, the corresponding fields in Settings are locked (read-only).

= Configuration =

1. **Feedbin API Setup**:
   - Enter your Feedbin email address
   - Enter your Feedbin password
   - Test the connection

2. **Synchronization Settings**:
   - Enable/disable automatic synchronization
   - Set sync frequency (twice daily, daily, weekly)

3. **Display Options**:
   - Use shortcodes: [blogroll] or [blogroll_grid]
   - Customize columns and limits
   - Filter by categories

= Shortcodes =

= [blogroll] =
Main blogroll shortcode with full functionality.

`[blogroll category="tech" limit="12" columns="4" show_export="true"]`

**Parameters:**
* `category` - Filter by category slug
* `limit` - Maximum number of blogs to display (-1 for all)
* `columns` - Number of columns (1-6)
* `show_export` - Show OPML export button (true/false)

= [blogroll_grid] =
Grid-specific shortcode for focused display.

`[blogroll_grid category="design" limit="8" columns="3"]`

= Usage Examples =

= Basic Blogroll =
```
[blogroll]
```

= Category-Specific Grid =
```
[blogroll_grid category="technology" columns="3" limit="9"]
```

= Custom Layout =
```
[blogroll columns="2" limit="6" show_export="false"]
```

= REST API =

Access your blogroll data programmatically:

**Endpoint:** `/wp-json/feed-to-blogroll/v1/blogroll`

= Blocks =

Block: Blogroll (`feed-to-blogroll/blogroll`)

Attributes:
- `category` (string): Filter by category slug (default: all)
- `limit` (number): Number of blogs to display (-1 for all)
- `columns` (number): 1–6 (default: 4)
- `showExport` (boolean): Show OPML export button (default: true)

Insert the “Blogroll” block via the inserter and configure options in the sidebar. Assets (CSS/JS) are loaded automatically when the block/shortcode is present.

**Parameters:**
* `category` - Filter by category slug
* `limit` - Maximum number of blogs

**Example Response:**
```json
[
  {
    "id": 123,
    "title": "Example Blog",
    "description": "A great blog about technology",
    "site_url": "https://example.com",
    "rss_url": "https://example.com/feed/",
    "categories": ["Technology", "Web Development"]
  }
]
```

= Hooks and Filters =

= Actions =
* `feed_to_blogroll_sync_cron` - Fired during scheduled synchronization
* `feed_to_blogroll_manual_sync` - Fired during manual synchronization

= Filters =
* `feed_to_blogroll_sync_result` - Modify synchronization results
* `feed_to_blogroll_blog_data` - Modify individual blog data

= Customization =

= CSS Classes =
* `.feed-to-blogroll-container` - Main container
* `.blogroll-grid` - Grid layout container
* `.blog-card` - Individual blog card
* `.blog-title` - Blog title
* `.blog-description` - Blog description
* `.blog-actions` - Action buttons container

= Styling =
The plugin includes responsive CSS that works with most themes. You can override styles by adding custom CSS to your theme.

= Privacy & Data Handling =

= Data Collection =
This plugin collects and stores:
- Feedbin API credentials (stored in WordPress options)
- Blog metadata from RSS feeds
- Synchronization logs and timestamps

= Data Retention =
- API credentials are stored until removed in settings or plugin uninstallation
- Blog data is retained according to WordPress post lifecycle
- Sync logs are kept for 30 days by default

= Third-party Services =
- Feedbin API: Used for RSS feed synchronization
- No data is shared with other third-party services

= GDPR Compliance =
- Users can export their blogroll data via OPML
- Users can request data deletion through WordPress admin
- No personal data is collected beyond what's necessary for functionality

= Uninstall =

When deleting the plugin from WordPress Admin, all plugin data is removed:
- Options: `feed_to_blogroll_options`
- Custom post type entries (`blogroll`) and related taxonomies
- Scheduled events and transients

= Accessibility =

= WCAG 2.1 AA Compliance =
- Semantic HTML structure with proper heading hierarchy
- ARIA labels and roles for interactive elements
- Keyboard navigation support for all features
- Screen reader compatibility with descriptive text
- High contrast support and focus indicators
- ARIA-compliant structure (list/listitem) and keyboard navigation for cards

= Screen Reader Support =
- Descriptive alt text for all images
- ARIA live regions for dynamic content updates
- Proper form labeling and error messaging
- Semantic markup for navigation and content structure

= Keyboard Navigation =
- Tab order follows logical content flow
- All interactive elements accessible via keyboard
- Skip links for main content areas
- Focus indicators visible on all focusable elements

= Troubleshooting =

= Common Issues =

1. **ACF Pro Required**: Make sure Advanced Custom Fields Pro is installed and activated
2. **API Connection Failed**: Verify your Feedbin credentials and check your internet connection
3. **No Blogs Displayed**: Check if blogs are published and have the correct post status
4. **Styling Issues**: Ensure your theme CSS doesn't conflict with plugin styles
5. **HTTP 429 (rate limited)**: Reduce sync frequency or set `FEED_TO_BLOGROLL_FETCH_TAGS` to `false` to lower API calls

= Diagnostics =
Use the built-in diagnostics page (Blogroll > Diagnostics) to:
- Check system compatibility
- Test API connections
- View database status
- Troubleshoot common issues

= Debug Mode =
Enable WordPress debug mode to see detailed error logs:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

= Support =

For support and feature requests, please visit:
* GitHub: https://github.com/jaz-on/feed-to-blogroll
* Issues: https://github.com/jaz-on/feed-to-blogroll/issues

= Changelog =

= 1.0.0 =
* Initial release
* Feedbin API integration
* Custom post type with ACF fields
* Automatic synchronization
* Responsive grid layout
* OPML export functionality
* Admin dashboard
* Shortcodes support
* REST API endpoints
* Enhanced security features
* Improved accessibility
* Performance optimizations

= Credits =

Developed by Jason Rouet
Built with WordPress best practices
Integrated with Distributed theme

For more information, visit: https://github.com/jaz-on/feed-to-blogroll

== Frequently Asked Questions ==

= How often does the plugin sync with Feedbin? =

By default, the plugin syncs daily. You can change this in the settings to twice daily or weekly.

= Can I manually sync my blogroll? =

Yes! Use the "Manual Sync" button in the admin dashboard to sync immediately.

= What happens if a blog is removed from Feedbin? =

The plugin marks removed blogs as "inactive" (draft status) instead of deleting them, preserving your content.

= Can I export my blogroll? =

Yes! The plugin provides OPML export functionality for easy backup and sharing.

= Is the plugin compatible with my theme? =

The plugin is designed to work with most WordPress themes, including the Distributed theme. It includes responsive CSS and follows WordPress coding standards.

= Can I customize the appearance? =

Yes! The plugin uses semantic CSS classes that you can override with custom CSS in your theme.

= Is the plugin accessible? =

Yes! The plugin follows WCAG 2.1 AA guidelines and includes comprehensive ARIA support for screen readers and keyboard navigation.

= What data does the plugin collect? =

The plugin only collects data necessary for functionality: Feedbin API credentials (encrypted), blog metadata from RSS feeds, and synchronization logs. No personal user data is collected.

== Screenshots ==

1. Admin dashboard with statistics and actions
2. Settings page with API configuration
3. Frontend blogroll display with responsive grid
4. Blog card with site and RSS links

== Upgrade Notice ==

= 1.0.0 =
Initial release with full Feedbin API integration, responsive design, and accessibility features.
