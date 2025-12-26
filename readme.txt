=== Tool SEO Hupuna ===
Contributors: maisydat
Tags: seo, links, scanner, external links, product manager, price manager, woocommerce, audit, content management
Requires at least: 5.8
Tested up to: 6.4
Stable tag: 2.1.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Comprehensive SEO tools including external link scanner, posts with links manager, and WooCommerce product price manager.

== Description ==

Tool SEO Hupuna is a powerful WordPress plugin that provides comprehensive SEO and content management tools. It includes three main features: external link scanner, posts with links manager, and WooCommerce product price manager.

= Key Features =

* **External Link Scanner**: Scans your entire WordPress website for external links across posts, pages, comments, and options
* **Posts with Links Manager**: Manage and edit internal links (products, categories, posts) in your content
* **WooCommerce Product Price Manager**: Bulk edit product prices and names directly from admin panel
* **Batch Processing**: Optimized for large databases with efficient batch processing
* **Smart Filtering**: Automatically excludes system domains (WordPress.org, WooCommerce, Gravatar, etc.)
* **WordPress Default UI**: Clean, native WordPress admin interface
* **Performance Optimized**: Handles large websites without performance issues

= External Link Scanner =

Scans your entire website for external links:
* All public post types (posts, pages, custom post types)
* Post content and excerpts
* Comments
* WordPress options (excluding transients and system options)

Results can be viewed grouped by URL or as all occurrences, with quick edit/view links.

= Posts with Links Manager =

Manage internal links in your content:
* Find posts containing specific URLs
* Edit and update links directly from admin panel
* Supports products, product categories, and posts
* Works with all post types

= WooCommerce Product Price Manager =

Bulk manage WooCommerce products:
* Edit product names inline
* Update regular and sale prices
* Support for simple and variable products
* Save all variants at once
* Search products by name

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Navigate to Plugins → Add New
3. Search for "Tool SEO Hupuna"
4. Click "Install Now" and then "Activate"

= Manual Installation =

1. Upload the `tool-seo-hupuna` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to Tool SEO in the admin menu to start using

== Frequently Asked Questions ==

= Does this plugin slow down my website? =

No. The plugin only runs when you manually trigger actions from the admin panel. It uses efficient batch processing to handle large databases without impacting site performance.

= What happens if I have a very large website? =

The plugin uses batch processing to handle websites of any size. It processes content in small batches to prevent server timeouts and ensure smooth operation.

= Can I customize which domains are excluded from scanning? =

Yes. You can use the `tool_seo_hupuna_whitelist` filter to customize the list of excluded domains.

= Does the plugin require WooCommerce? =

The External Link Scanner and Posts with Links Manager work without WooCommerce. The Product Price Manager requires WooCommerce to be installed and activated.

= Can I edit product prices in bulk? =

Yes. The Product Price Manager allows you to edit multiple product prices at once, including all variants of variable products.

== Screenshots ==

1. External Link Scanner interface
2. Posts with Links Manager
3. Product Price Manager

== Changelog ==

= 2.1.1 =
* Renamed plugin to Tool SEO Hupuna
* Added Posts with Links Manager feature
* Added WooCommerce Product Price Manager feature
* Improved UI with WordPress default styles
* Removed emoji icons
* Updated all text domains and constants

= 2.0.0 =
* Initial release
* External link scanner with batch processing
* Comprehensive scanning of posts, comments, and options
* Smart domain filtering

== Upgrade Notice ==

= 2.1.1 =
Major update: Added Posts with Links Manager and Product Price Manager features. Improved UI and renamed to Tool SEO Hupuna.

= 2.0.0 =
Initial release of Tool SEO Hupuna.

