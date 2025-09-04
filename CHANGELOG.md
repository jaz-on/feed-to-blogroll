# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased] (towards 1.0.0)

### Changed
- Complete refactorization to remove ACF Pro dependency
- Migrated to native WordPress meta fields and meta boxes
- Updated plugin description to reflect no external dependencies

### Added
- Native WordPress meta field registration with validation
- Custom meta boxes using WordPress core functionality
- Comprehensive refactorization documentation
- Development configuration with Taskmaster integration

### Removed
- ACF Pro dependency requirement
- External plugin dependencies
- Legacy ACF field definitions

### Added
- `.distignore` file for optimized plugin packaging
- Development file exclusion during distribution

### Added
- Complete change documentation in CHANGELOG.md
- Detailed feature and improvement history

### Added
- Complete integration with Feedbin API for automatic blogroll synchronization
- Custom 'blogroll' post type with native WordPress meta fields
- Comprehensive admin interface with dashboard and settings
- `[blogroll]` and `[blogroll_grid]` shortcodes for display
- Native WordPress block with customizable attributes
- OPML export for backup and sharing
- Category and tag support for organization
- REST API for programmatic data access
- Automatic synchronization via WordPress cron
- Responsive interface with adaptive grid

### Security
- Nonce verification for all AJAX actions
- User capability checks
- Data sanitization and validation
- wp-config.php constant support for credentials

### Performance
- Optimized caching system for queries
- Conditional asset loading
- Optimized database queries
- OPML cache support

### Accessibility
- WCAG 2.1 AA compliance
- Screen reader support
- Keyboard navigation
- Appropriate ARIA attributes
- Semantic HTML structure

### Technical
- Modular architecture with separation of concerns
- PHP 8.2+ support
- WordPress coding standards
- Complete documentation
- Automated tests and validation

### Added
- Initial plugin version
- Basic structure and architecture
- Essential functionality support