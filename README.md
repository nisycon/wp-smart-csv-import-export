# WP Smart CSV Import/Export

[![WordPress Plugin Version](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

Universal CSV import/export plugin for WordPress supporting all post types, custom fields, and taxonomies with round-trip capability.

## 🚀 Features

### ✨ Universal Support
- **All Post Types**: Posts, pages, custom post types
- **Custom Fields**: ACF, Meta Box, Toolset, and all others
- **Taxonomies**: Categories, tags, custom taxonomies with hierarchy
- **Featured Images**: URLs and attachment IDs

### 🔄 Round-trip Capability
- Export CSV files with perfect formatting
- Edit exported files in Excel or any editor
- Import edited files seamlessly back

### 🎯 Smart Detection
- **ID-based Updates**: Reliable post identification
- **Auto Post Type Detection**: No manual selection needed
- **Automatic Field Mapping**: Dynamic field discovery

### 📊 Batch Processing
- **Large Dataset Support**: Process thousands of posts safely
- **Real-time Progress**: Live progress bar with statistics
- **Memory Efficient**: Chunked processing prevents timeouts

### 🌍 International Ready
- **UTF-8 with BOM**: Perfect for all languages
- **Translatable**: Full i18n support
- **Excel Compatible**: Proper CSV formatting for Excel

## 🛠 Installation

### From WordPress Admin
1. Go to **Plugins > Add New**
2. Search for "WP Smart CSV Import Export"
3. Install and activate

### Manual Installation
1. Download the plugin
2. Upload to `/wp-content/plugins/wp-smart-csv-import-export/`
3. Activate through the WordPress admin

### From GitHub
```bash
cd wp-content/plugins/
git clone https://github.com/qoox/wp-smart-csv-import-export.git
```

## 📋 Usage

### Quick Start
1. Go to **CSV IMP/EXP** in your WordPress admin
2. **Export**: Select post type → Choose fields → Export CSV
3. **Edit**: Modify the CSV file as needed
4. **Import**: Upload CSV → Select import mode → Import

### Export Options
- **Post Types**: Select specific type or "All Post Types"
- **Fields**: Choose which fields to include
- **Filters**: Date range, post status, limits
- **Format**: Auto-generated UTF-8 with proper escaping

### Import Modes
- **Update + Create** (Recommended): Update existing posts by ID, create new ones
- **Create Only**: Always create new posts (ignore IDs)

## 📊 CSV Format

### Basic Structure
```csv
ID,post_type,post_title,post_content,post_status
1,post,"My Post","Content here",publish
,page,"New Page","Page content",draft
123,product,"Product Name","Description",publish
```

### Special Characters
```csv
ID,post_type,post_title,post_content
1,post,"Product A, B, C","Line 1
Line 2"
2,post,"15"" Monitor","Screen description"
```

### Post Type Switching
```csv
ID,post_type,post_title
123,product,"Changed from post to product"
456,page,"Changed from post to page"
```

## 🔧 Technical Specifications

### System Requirements
- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **Memory**: 128MB+ recommended for large datasets

### Supported Fields
- **Core Fields**: All WordPress post fields
- **Custom Fields**: All meta fields including ACF
- **Taxonomies**: Categories, tags, custom taxonomies
- **Media**: Featured images and attachments

### Security Features
- **Nonce Verification**: All AJAX requests secured
- **Capability Checks**: Admin-only access
- **Data Sanitization**: All inputs sanitized
- **File Validation**: CSV format validation

### Development Setup
```bash
# Clone repository
git clone https://github.com/qoox/wp-smart-csv-import-export.git
cd wp-smart-csv-import-export

# Install dependencies (if any)
npm install

# Make changes and test
```

### Reporting Issues
Please use the [GitHub Issues](https://github.com/qoox/wp-smart-csv-import-export/issues) page to report bugs or request features.

## 📝 Changelog

### 1.0.0 - Initial Release
- Universal CSV import/export for all post types
- Automatic post type detection via post_type column
- ID-based reliable update/create detection
- Batch processing with real-time progress display
- Complete custom fields and taxonomies support
- Full internationalization (UTF-8 with BOM)
- Page-leave warning functionality
- Round-trip CSV editing support

## 💝 Support Development

If this plugin has been helpful, please consider supporting development:

[![Donate via PayPal](https://img.shields.io/badge/Donate-PayPal-blue.svg)](https://www.paypal.com/ncp/payment/JKL3WTQLH5NXA)

Your support helps maintain and improve the plugin!

## 📄 License

This plugin is licensed under the [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

## 🔗 Links

- **WordPress Plugin Directory**: [Coming Soon]
- **Documentation**: [Wiki](https://github.com/qoox/wp-smart-csv-import-export/wiki)
- **Support**: [WordPress Forums](https://wordpress.org/support/plugin/wp-smart-csv-import-export/)
- **Website**: [https://qoox.co.jp](https://qoox.co.jp)

---


Made with ❤️ by [Qoox](https://qoox.co.jp)

