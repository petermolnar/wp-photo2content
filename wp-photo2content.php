<?php
/*
Plugin Name: wp-photo2content
Plugin URI: https://github.com/petermolnar/wp-photo2content
Description: Autofill content, tags and geo from featured image EXIF & IPTC
Version: 0.1
Author: Peter Molnar <hello@petermolnar.net>
Author URI: http://petermolnar.net/
License: GPLv3
*/

/*  Copyright 2016 Peter Molnar ( hello@petermolnar.net )

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 3, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

namespace WP_PHOTO2CONTENT;

\add_action( 'transition_post_status', 'WP_PHOTO2CONTENT\on_transition', 99, 3 );

/**
 *
 */
function on_transition ( $new_status, $old_status, $post ) {

	$post = fix_post( $post );

	if ( false === $post )
		return false;

	$thid = get_post_thumbnail_id( $post->ID );

	if ( empty($thid) )
		return false;

	$enabled = apply_filters( 'wp_photo2content_enabled',
		true, $new_status, $old_status, $post );

	if ( true !== $enabled )
		return false;

	tags_from_photo ( $post, $thid );
	content_from_photo ( $post, $thid );
	geo_from_photo ( $post, $thid );


	// \do_action( 'wp_photo2content',  )

}


/**
 *
 */
function content_from_photo ( &$post, $thid ) {
	$meta = wp_get_attachment_metadata( $thid );

	if ( empty ( $post->post_content ) &&
		! empty( $meta['image_meta']['caption'] )
	){
		replace_content ( $post, $meta['image_meta']['caption'] );
	}

}

/**
 *
 */
function tags_from_photo ( &$post, $thid ) {
	$keywords = array();

	$meta = wp_get_attachment_metadata( $thid );

	// add keywords as tags
	if ( isset( $meta['image_meta'] ) &&
		isset ( $meta['image_meta']['keywords'] ) &&
		! empty( $meta['image_meta']['keywords'] ) &&
		is_array ( $meta['image_meta']['keywords'] )
	) {
		$keywords = array_merge( $keywords, $meta['image_meta']['keywords'] );
	}

	if ( isset ( $meta['image_meta']['camera'] ) &&
		! empty ( $meta['image_meta']['camera'] ) ) {

		// add camera
		array_push( $keywords, $meta['image_meta']['camera'] );

		// add camera manufacturer
		if ( strstr( $meta['image_meta']['camera'], ' ' ) ) {
			$manufacturer =
				ucfirst (
					strtolower (
						substr ( $meta['image_meta']['camera'], 0,
							strpos( $meta['image_meta']['camera'], ' ')
						)
					)
				);
			array_push ( $keywords, $manufacturer );
		}
	}

	if ( ! empty ( $keywords ) )
		add_tags ( $post, $keywords );
}

/**
 *
 */
function geo_from_photo ( &$post, $thid ) {
	$meta = wp_get_attachment_metadata( $thid );

	$try = array ( 'geo_latitude', 'geo_longitude', 'geo_altitude' );
	foreach ( $try as $kw ) {
		$curr = get_post_meta ( $post->ID, $kw, true );

		if ( isset ( $meta['image_meta'][ $kw ] ) &&
			!empty( $meta['image_meta'][ $kw ] ) ) {
			if ( empty ( $curr ) ) {
				debug("Adding {$kw} to {$post->ID} from exif", 5);
				add_post_meta( $post->ID, $kw, $meta['image_meta'][ $kw ], true );
			}
			elseif ( $curr != $meta['image_meta'][ $kw ] ) {
				debug("Updating {$kw} to {$post->ID} from exif", 5);
				update_post_meta( $post->ID, $kw, $meta['image_meta'][ $kw ], $curr );
			}
		}
	}
}

/**
 *
 */
function add_tags ( &$post, &$keywords, $taxonomy = 'post_tag' ) {

	$keywords = array_unique($keywords);
	foreach ( $keywords as $tag ) {
		$tag = trim( $tag );

		if ( empty( $tag ) )
			continue;

		if ( !term_exists( $tag, $taxonomy ))
			wp_insert_term ( $tag, $taxonomy );

		if ( !has_term( $tag, $taxonomy, $post ) ) {
			debug ( "appending post #{$post->ID} {$taxonomy} taxonomy with:".
				" {$tag}", 5 );
			wp_set_post_terms( $post->ID, $tag, $taxonomy, true );
		}

	}

}

/**
 *
 */
function replace_content ( &$post, &$content ) {

	$post = fix_post ( $post );

	if ( false === $post )
		return false;

	global $wpdb;
	$dbname = "{$wpdb->prefix}posts";
	$req = false;

	debug("Updating post content for #{$post->ID}", 5);

	$q = $wpdb->prepare( "UPDATE `{$dbname}` SET `post_content`='%s' "
		."WHERE `ID`='{$post->ID}'", $content );

	try {
		$req = $wpdb->query( $q );
	}
	catch (Exception $e) {
		debug('Something went wrong: ' . $e->getMessage(), 4);
	}
}


/**
 * do everything to get the Post object
 */
function fix_post ( &$post = null ) {
	if ($post === null || !is_post($post))
		global $post;

	if (is_post($post))
		return $post;

	return false;
}

/**
 * test if an object is actually a post
 */
function is_post ( &$post ) {
	if ( ! empty( $post ) &&
			 is_object( $post ) &&
			 isset( $post->ID ) &&
			 ! empty( $post->ID ) )
		return true;

	return false;
}

/**
 *
 * debug messages; will only work if WP_DEBUG is on
 * or if the level is LOG_ERR, but that will kill the process
 *
 * @param string $message
 * @param int $level
 *
 * @output log to syslog | wp_die on high level
 * @return false on not taking action, true on log sent
 */
function debug( $message, $level = LOG_NOTICE ) {
	if ( empty( $message ) )
		return false;

	if ( @is_array( $message ) || @is_object ( $message ) )
		$message = json_encode($message);

	$levels = array (
		LOG_EMERG => 0, // system is unusable
		LOG_ALERT => 1, // Alert 	action must be taken immediately
		LOG_CRIT => 2, // Critical 	critical conditions
		LOG_ERR => 3, // Error 	error conditions
		LOG_WARNING => 4, // Warning 	warning conditions
		LOG_NOTICE => 5, // Notice 	normal but significant condition
		LOG_INFO => 6, // Informational 	informational messages
		LOG_DEBUG => 7, // Debug 	debug-level messages
	);

	// number for number based comparison
	// should work with the defines only, this is just a make-it-sure step
	$level_ = $levels [ $level ];

	// in case WordPress debug log has a minimum level
	if ( defined ( '\WP_DEBUG_LEVEL' ) ) {
		$wp_level = $levels [ \WP_DEBUG_LEVEL ];
		if ( $level_ > $wp_level ) {
			return false;
		}
	}

	// ERR, CRIT, ALERT and EMERG
	if ( 3 >= $level_ ) {
		\wp_die( '<h1>Error:</h1>' . '<p>' . $message . '</p>' );
		exit;
	}

	$trace = debug_backtrace();
	$caller = $trace[1];
	$parent = $caller['function'];

	if (isset($caller['class']))
		$parent = $caller['class'] . '::' . $parent;

	if (isset($caller['namespace']))
		$parent = $caller['namespace'] . '::' . $parent;

	return error_log( "{$parent}: {$message}" );
}
