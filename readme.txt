=== wp-photo2content ===
Contributors: cadeyrn
Tags:
Requires at least: 3.0
Tested up to: 4.5.3
Stable tag: 0.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Automatically fill post content, post tags and post geo location from the featured image.

== Description ==

In case the post content is empty, the plugin tries to fill it in on post save/publish from the `caption` data of the featured image, if any.
Existing or non-empty content is not overwritten.

If the featured image has any keywords, the plugin adds them as post_tags to the parent post.

If the featured image has location info, the plugin adds geo_latitude and geo_longitude accordingly.

== Installation ==

1. Upload contents of `wp-photo2content.zip` to the `/wp-content/plugins/` directory
2. Activate the plugin through the `Plugins` menu in WordPress

== Frequently Asked Questions ==

== Changelog ==

Version numbering logic:

* every A. indicates BIG changes.
* every .B version indicates new features.
* every ..C indicates bugfixes for A.B version.

= 0.1 =
*2016-07-22*

* initial public release
