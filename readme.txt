=== Daniy Image Manager ===
Contributors: akudaniy
Donate link: http://www.murdanieko.com/donate
Tags: attachment, attachment data cache
Requires at least: 3.0
Tested up to: 3.5.2
Stable tag: 1.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Display default post thumbnail, display post attachments gallery and SAVE MORE MySQL queries

== Description ==

--------- WARNING! -----------
This plugin may cause conflict if you also use some plugins for Markdown. They might utilize the same table field with this plugin, causing the data to be overwritten each other. You must choose: this plugin or that markdown plugin gets activated.


Getting attachments with get_posts() function is one query, extracting its size and file location is another call to wp_postmeta where all data about an attachment is stored. So if you have 20 images in a post, you’ll end up with additional 21 queries. More queries means more load time for your server to response a request from browser.

Daniy Image Manager pull a post attachments from the wp_posts and wp_postmeta ONCE when the single page is requested, then the result is cached and stored in the vacant post_content_filtered field in wp_posts table. This means, on the next call to the same single page will also pull the attachments saved data in post_content_filtered field. All we have to do is unserialize it and output to browser. No more get_posts() or get_children() queries to database

== Installation ==

1. Upload `plugin-name.php` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. To show post thumbnails in archive pages, put `<?php imwp_view_thumbnail(); ?>` tag within the WordPress Loop of your archive.php/ category.php/ other archive template file. This will show your preferred post thumbnail. If not set, it will look for the last attachment attached to the post. If the post has no attachments, it will show a default image.

4. To show post attachment galleries, put `<?php imwp_view_thumbnail(); ?>` tag on your single.php template, preferably after `<?php the_content(); ?>` tag


== Changelog ==
= 1.3 =
* Change attachment data storage to `post_content_filtered` field. Previously in `post_excerpt` by applying another filter to `the_excerpt()`

== Frequently asked questions ==
= What if I activate another Markdown plugin along with this plugin?
It's not a good idea since the plugins will overwrite this plugin data in the database, whichh will result in a corrupt and unserializable data. Choose one plugin at a time.


== Screenshots ==
1. Example of post thumbnail in archive page, and default post thumbnail if no post thumbnail set, and the post has no attachments.
2. Example of post attachments gallery


== Upgrade notice ==
No upgrades are planned today, but if there's any bug found, we'll plan for that.