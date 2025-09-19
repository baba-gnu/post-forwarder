=== Post Forwarder ===
Contributors: sylwesterulatowski
Tags: post, forward, sync, multisite, rest-api
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Forward posts to multiple WordPress sites via REST API with taxonomy mapping, featured image support, and duplicate prevention.

== Description ==

Post Forwarder is a powerful WordPress plugin that allows you to automatically forward posts to multiple WordPress sites using the REST API. Perfect for content syndication, multi-site networks, or distributing content across related websites.

**Key Features:**

* **Multi-Portal Support**: Configure multiple destination WordPress sites
* **Taxonomy Intelligence**: Automatically maps custom taxonomies or falls back to regular tags
* **Featured Image Transfer**: Uploads and sets featured images on destination sites
* **Custom Post Type Support**: Works with any public post type
* **Duplicate Prevention**: Smart locking mechanism prevents duplicate posts
* **ACF Integration**: Transfers Advanced Custom Fields data
* **Flexible Configuration**: Easy-to-use interface or advanced JSON configuration
* **Selective Forwarding**: Choose which portals to forward each post to
* **Draft or Publish**: Configure whether forwarded posts are published or saved as drafts

**How It Works:**

1. Configure your destination WordPress sites (portals) with their REST API credentials
2. When editing a post, select which portals to forward the post to
3. Upon saving, the plugin automatically forwards the post with all its content, taxonomies, meta fields, and featured image
4. Smart taxonomy mapping tries to preserve custom taxonomies, falling back to regular tags if needed

**Perfect For:**

* News networks with multiple websites
* Content syndication between related sites
* Multi-brand companies sharing content
* Blog networks and content distribution
* Development/staging to production workflows

**Technical Requirements:**

* WordPress REST API enabled on destination sites
* Application passwords configured for API access
* PHP 7.4 or higher

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/post-forwarder` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Settings → Post Forwarding to configure your destination portals
4. Set up Application Passwords on your destination WordPress sites for API access
5. Start forwarding posts by selecting portals in the post editor

== Frequently Asked Questions ==

= How do I set up API access for destination sites? =

1. On each destination WordPress site, go to Users → Profile
2. Scroll down to "Application Passwords"
3. Create a new application password
4. Use the user ID and generated password in the plugin configuration

= What happens if a custom taxonomy doesn't exist on the destination site? =

The plugin uses intelligent taxonomy mapping. It first tries to send taxonomies as they are. If that fails, it converts all taxonomy terms to regular tags, ensuring no content is lost.

= Can I forward custom post types? =

Yes! The plugin works with any public post type. It automatically detects the post type and uses the appropriate REST API endpoint.

= Will featured images be transferred? =

Yes, the plugin automatically downloads featured images from the source site and uploads them to the destination site, maintaining the featured image relationship.

= How does duplicate prevention work? =

The plugin uses a sophisticated transient-based locking system that prevents the same post from being forwarded multiple times, even if the save action is triggered multiple times.

= Can I forward to multiple sites at once? =

Absolutely! You can configure multiple portals and select which ones to forward each post to using checkboxes in the post editor.

== Screenshots ==

1. **Portal Configuration** - Easy-to-use interface for configuring destination WordPress sites
2. **Post Editor Integration** - Simple checkboxes to select which portals to forward posts to
3. **Advanced JSON Configuration** - For power users who prefer direct JSON editing
4. **Settings Page** - Global plugin settings and portal management

== Changelog ==

= 2.1.0 =
* Added multi-portal support with selective forwarding
* Implemented intelligent taxonomy mapping with fallback to tags
* Added featured image transfer functionality
* Introduced duplicate prevention system
* Added ACF (Advanced Custom Fields) integration
* Improved error handling and logging
* Added user-friendly portal configuration interface
* Enhanced custom post type support
* Added internationalization support
* Improved security with proper input sanitization

= 2.0.0 =
* Complete rewrite with REST API support
* Added taxonomy and meta field forwarding
* Improved reliability and error handling

= 1.0.0 =
* Initial release
* Basic post forwarding functionality

== Upgrade Notice ==

= 2.1.0 =
Major update with multi-portal support, taxonomy mapping, featured image transfer, and many other improvements. Please review your configuration after updating.

== Technical Notes ==

**REST API Endpoints Used:**
* `/wp-json/wp/v2/posts` - For standard posts
* `/wp-json/wp/v2/{post_type}` - For custom post types
* `/wp-json/wp/v2/media` - For featured image uploads

**Security:**
* All data is properly sanitized and validated
* Uses WordPress nonces for form security
* Application passwords for secure API authentication
* No data is stored insecurely

**Performance:**
* Minimal impact on site performance
* Efficient transient-based duplicate prevention
* Optimized API calls with proper error handling
* Smart taxonomy processing to reduce API calls

== Support ==

For support, feature requests, or bug reports, please visit the plugin's support forum or GitHub repository.

**Minimum Requirements:**
* WordPress 5.0+
* PHP 7.4+
* REST API enabled on destination sites
* Application passwords configured for API access