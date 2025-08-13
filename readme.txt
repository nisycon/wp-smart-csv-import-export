=== Smart CSV Import & Export ===
Contributors: qoox
Donate link: https://www.paypal.com/ncp/payment/JKL3WTQLH5NXA
Tags: csv, import, export, custom-post-types, custom-fields
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Universal CSV import/export plugin supporting all post types, custom fields, taxonomies with round-trip capability.

== Description ==

WP Smart CSV Import/Export is a powerful CSV import/export plugin for efficiently managing WordPress site data.

= Key Features =

* **Universal Support**: Works with all post types (posts, pages, custom post types)
* **Complete Coverage**: Custom fields (including ACF), taxonomies, featured images
* **Round-trip Support**: Import CSV files exported by the plugin seamlessly
* **ID-based Detection**: Reliable update/create detection using post IDs
* **Auto Post Type Detection**: Automatic detection via post_type column (no manual selection needed)
* **Batch Processing**: Safe processing of large datasets with progress display
* **Full Internationalization**: UTF-8 (with BOM) support for all languages

= Supported Fields =

* **Basic Fields**: ID, post_type, title, content, excerpt, status, published date, modified date, author, slug, parent post, menu order
* **Custom Fields**: All custom fields (ACF, Meta Box, Toolset, etc.)
* **Taxonomies**: Categories, tags, custom taxonomies (with hierarchy support)
* **Featured Images**: Image URLs, attachment IDs

= How to Use =

1. **Export**: Select post type (or "All") and export to CSV
2. **Edit**: Modify the downloaded CSV file
3. **Import**: Upload the edited CSV file and import

= Technical Specifications =

* **ID Detection System**: Reliable update detection using WordPress post IDs
* **Post Type Switching**: Change post types of existing posts via CSV
* **Safe Processing**: Page-leave warnings, backup recommendations
* **Progress Display**: Real-time progress bar showing processing status

= Perfect for =

* **Data Migration**: Moving content between WordPress sites
* **Bulk Editing**: Mass updates to posts, pages, and custom post types
* **Content Management**: Offline editing of large datasets
* **Site Maintenance**: Backup and restore post data
* **Development**: Testing with sample data

== Installation ==

1. Download the plugin and upload to `/wp-content/plugins/` directory
2. Activate the plugin through WordPress admin
3. Find "CSV IMP/EXP" menu in your admin dashboard

== Frequently Asked Questions ==

= Which post types are supported? =

All post types are supported, including standard posts/pages and custom post types.

= Are custom fields supported? =

Yes, all custom fields including Advanced Custom Fields (ACF) are fully supported.

= Can it handle large datasets safely? =

Yes, the batch processing feature safely handles large datasets with real-time progress tracking.

= Can I import the same CSV I exported? =

Yes, the plugin supports round-trip functionality - you can edit exported CSV files and import them back.

= Can I change post types? =

Yes, you can change existing post types by editing the post_type column in the CSV.

= What about data with commas or line breaks? =

The plugin automatically handles special characters. For manual editing, enclose data containing commas or line breaks in double quotes.

== Screenshots ==

1. Export interface - Select post types and fields for export
2. Import interface - Upload CSV files for bulk import
3. Progress display - Real-time progress bar during processing
4. Help section - Detailed technical specifications and usage guide

== Changelog ==

= 1.0.0 =
* Initial release
* Universal CSV import/export for all post types
* Automatic post type detection via post_type column
* ID-based reliable update/create detection
* Batch processing with real-time progress display
* Complete custom fields and taxonomies support
* Full internationalization (UTF-8 with BOM)
* Page-leave warning functionality
* Round-trip CSV editing support

== Upgrade Notice ==

= 1.0.0 =
Initial release. Efficient CSV management for WordPress data.

== Support ==

For support, please use the WordPress.org support forums or visit our website.

== Development ==

This plugin is actively developed. If you'd like to support feature improvements and new development, please consider making a donation.

== Privacy Policy ==

This plugin does not collect or store any user data. All CSV processing is done locally on your server.

== Third-party Services ==

This plugin does not connect to any third-party services. All processing is done locally.