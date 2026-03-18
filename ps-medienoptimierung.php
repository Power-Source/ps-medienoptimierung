<?php
/*
Plugin Name: PS Medienoptimierung
Plugin URI: https://nerdservice.de/
Description: Local image optimization for WordPress media without external API dependencies.
Author: NerdService
Version: 2.7.8
Author URI: https://nerdservice.de/
Text Domain: ps-medienoptimierung
WDP ID: 912164
*/

/*
  Copyright 2009-2017 Incsub (http://incsub.com)
  Author - Aaron Edwards, Sam Najian, Umesh Kumar
  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
  the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

/**
 * Constants
 */
$prefix  = 'WP_SMUSH_';
$version = '2.7.8';

// Local fork: do not force-disable other Smush variants.

/**
 * Set the default timeout for API request and AJAX timeout
 */
$timeout = apply_filters( 'WP_SMUSH_API_TIMEOUT', 90 );

// To support smushing on staging sites like SiteGround staging where
// staging site urls are different but redirects to main site url.
// Remove the protocols and www, and get the domain name.
$site_url = str_replace( array( 'http://', 'https://', 'www.' ), '', site_url() );
// If current site's url is different from site_url, disable Async.
if ( ! empty( $_SERVER['SERVER_NAME'] ) && ( 0 !== strpos( $site_url, $_SERVER['SERVER_NAME'] ) ) && ! defined( $prefix . 'ASYNC' ) ) {
	define( $prefix . 'ASYNC', false );
}

$smush_constants = array(
	'VERSION'           => $version,
	'BASENAME'          => plugin_basename( __FILE__ ),
	'API'               => '',
	'UA'                => 'PS Medienoptimierung/' . $version . '; ' . network_home_url(),
	'DIR'               => plugin_dir_path( __FILE__ ),
	'URL'               => plugin_dir_url( __FILE__ ),
	'MAX_BYTES'         => 1000000,
	'PREMIUM_MAX_BYTES' => 32000000,
	'PREFIX'            => 'wp-smush-',
	'TIMEOUT'           => $timeout,
	//If Set to false, WP Smush switch backs to the Old Sync Optimisation
	'ASYNC'             => true
);

foreach ( $smush_constants as $const_name => $constant_val ) {
	if ( ! defined( $prefix . $const_name ) ) {
		define( $prefix . $const_name, $constant_val );
	}
}

//Include main class
require_once WP_SMUSH_DIR . 'lib/class-wp-smush.php';

/**
 * Filters the rating message, include stats if greater than 1Mb
 *
 * @param $message
 *
 * @return string
 */
if ( ! function_exists( 'wp_smush_rating_message' ) ) {
	function wp_smush_rating_message( $message ) {
		global $wpsmushit_admin, $wpsmush_db;
		if ( empty( $wpsmushit_admin->stats ) ) {
			$wpsmushit_admin->setup_global_stats();
		}
		$savings    = $wpsmushit_admin->stats;
		$show_stats = false;

		//If there is any saving, greater than 1Mb, show stats
		if ( ! empty( $savings ) && ! empty( $savings['bytes'] ) && $savings['bytes'] > 1048576 ) {
			$show_stats = true;
		}

		$message = "Hey %s, you've been using %s for a while now, and we hope you're happy with it.";

		//Conditionally Show stats in rating message
		if ( $show_stats ) {
			$message .= sprintf( " You've smushed <strong>%s</strong> from %d images already, improving the speed and SEO ranking of this site!", $savings['human'], $savings['total_images'] );
		}
		$message .= " We've spent countless hours developing this free plugin for you, and we would really appreciate it if you dropped us a quick rating!";

		return $message;
	}
}

/**
 * NewsLetter
 *
 * @param $message
 *
 * @return string
 */
if ( ! function_exists( 'wp_smush_email_message' ) ) {
	function wp_smush_email_message( $message ) {
		$message = "You're awesome for installing %s! Site speed isn't all image optimization though, so we've collected all the best speed resources we know in a single email - just for users of WP Smush!";

		return $message;
	}
}
if ( ! function_exists( 'get_plugin_dir' ) ) {
	/**
	 * Returns the dir path for the plugin
	 *
	 * @return string
	 */
	function get_plugin_dir() {
		$dir_path = plugin_dir_path( __FILE__ );

		return $dir_path;
	}
}

// Local fork: no cross-plugin deactivation notice.

if ( ! function_exists( 'smush_activated' ) ) {
	/**
	 * Check if a existing install or new
	 */
	function smush_activated() {
		global $wpsmush_settings;

		$version  = get_site_option( WP_SMUSH_PREFIX . 'version' );
		$settings = ! empty( $wpsmush_settings->settings ) ? $wpsmush_settings->settings : $wpsmush_settings->init_settings();

		//If the version is not saved or if the version is not same as the current version,
		if ( ! $version || WP_SMUSH_VERSION != $version ) {
			global $wpdb;
			//Check if there are any existing smush stats
			$query   = "SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_key=%s LIMIT 1";
			$results = $wpdb->get_var( $wpdb->prepare( $query, 'wp-smpro-smush-data' ) );

			if ( $results ) {
				update_site_option( 'wp-smush-install-type', 'existing' );
			} else {
				//Check for existing settings
				if ( false !== $settings['auto'] ) {
					update_site_option( 'wp-smush-install-type', 'existing' );
				}
			}

			//Store the plugin version in db
			update_site_option( WP_SMUSH_PREFIX . 'version', WP_SMUSH_VERSION );
		}

	}
}


if ( ! function_exists( 'smush_sanitize_hex_color' ) ) {
	/**
	 * Sanitizes a hex color.
	 *
	 * @param $color
	 *
	 * @return string Returns either '', a 3 or 6 digit hex color (with #), or nothing
	 */
	function smush_sanitize_hex_color( $color ) {
		if ( '' === $color ) {
			return '';
		}

		// 3 or 6 hex digits, or the empty string.
		if ( preg_match( '|^#([A-Fa-f0-9]{3}){1,2}$|', $color ) ) {
			return $color;
		}
	}
}

if ( ! function_exists( 'smush_sanitize_hex_color_no_hash' ) ) {
	/**
	 * Sanitizes a hex color without hash
	 *
	 * @param $color
	 *
	 * @return string Returns either '', a 3 or 6 digit hex color (with #), or nothing
	 */
	function smush_sanitize_hex_color_no_hash( $color ) {
		$color = ltrim( $color, '#' );

		if ( '' === $color ) {
			return '';
		}

		return smush_sanitize_hex_color( '#' . $color ) ? $color : null;
	}
}
//Load Translation files
add_action( 'plugins_loaded', 'smush_i18n' );
if ( ! function_exists( 'smush_i18n' ) ) {
	function smush_i18n() {
		$path = path_join( dirname( plugin_basename( __FILE__ ) ), 'languages/' );
		load_plugin_textdomain( 'ps-medienoptimierung', false, $path );
	}
}

register_activation_hook( __FILE__, 'smush_activated' );