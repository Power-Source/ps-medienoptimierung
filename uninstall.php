<?php
/**
 * Remove plugin settings data
 *
 * @since 1.7
 *
 */

//if uninstall not called from WordPress exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

//Check if someone want to keep the stats and settings
if( defined('PS_SMUSH_PRESERVE_STATS') &&  PS_SMUSH_PRESERVE_STATS ) {
	return;
}

global $wpdb;

$smushit_keys = array(
	'auto',
	'original',
	'lossy',
	'backup',
	'resize',
	'png_to_jpg',
	'resize-sizes',
	'nextgen',
	'keep_exif',
	'resmush-list',
	'resize_sizes',
	'transparent_png',
	'image_sizes',
	'skip-redirect',
	'nextgen-resmush-list',
	'super_smushed',
	'super_smushed_nextgen',
	'settings_updated',
	'skip-redirect',
	'hide_smush_welcome',
	'hide_upgrade_notice',
	'hide_update_info',
	'install-type',
	'lossy-updated',
	'version',
	'networkwide',
	'dir_path',
	'scan',
	'last_settings'
);

//Cache Keys
$cache_keys = array(
	'smush_global_stats',
);

$cache_smush_group   = array(
	'exceeding_items',
	'ps-smush-resize_savings',
	'pngjpg_savings'
);
$cache_nextgen_group = array(
	'ps_smush_images',
	'ps_smush_images_smushed',
	'ps_smush_images_unsmushed',
	'ps_smush_stats_nextgen',

);

if ( ! is_multisite() ) {
	//Delete Options
	foreach ( $smushit_keys as $key ) {
		$key = 'ps-smush-' . $key;
		delete_option( $key );
		delete_site_option( $key );
	}
	//Delete Cache data
	foreach ( $cache_keys as $key ) {
		wp_cache_delete( $key );
	}

	foreach ( $cache_smush_group as $s_key ) {
		wp_cache_delete( $s_key, 'smush' );
	}

	foreach ( $cache_nextgen_group as $n_key ) {
		wp_cache_delete( $n_key, 'nextgen' );
	}

}

//Delete Directory Smush stats
delete_option( 'dir_smush_stats' );
delete_option( 'ps_smush_scan' );
delete_option( 'ps_smush_api_auth' );
delete_option( 'ps_smush_dir_path' );
delete_site_option( 'ps_smush_api_auth' );

//Delete Post meta
$meta_type  = 'post';
$meta_key   = 'wp-smpro-smush-data';
$meta_value = '';
$delete_all = true;

if ( is_multisite() ) {
	$offset = 0;
	$limit  = 100;
	while ( $blogs = $wpdb->get_results( "SELECT blog_id FROM {$wpdb->blogs} LIMIT $offset, $limit", ARRAY_A ) ) {
		if ( $blogs ) {
			foreach ( $blogs as $blog ) {
				switch_to_blog( $blog['blog_id'] );
				delete_metadata( $meta_type, null, $meta_key, $meta_value, $delete_all );
				delete_metadata( $meta_type, null, 'ps-smush-lossy', '', $delete_all );
				delete_metadata( $meta_type, null, 'ps-smush-resize_savings', '', $delete_all );
				delete_metadata( $meta_type, null, 'ps-smush-original_file', '', $delete_all );
				delete_metadata( $meta_type, null, 'ps-smush-pngjpg_savings', '', $delete_all );
				foreach ( $smushit_keys as $key ) {
					$key = 'ps-smush-' . $key;
					delete_option( $key );
					delete_site_option( $key );
				}
				//Delete Cache data
				foreach ( $cache_keys as $key ) {
					wp_cache_delete( $key );
				}

				foreach ( $cache_smush_group as $s_key ) {
					wp_cache_delete( $s_key, 'smush' );
				}

				foreach ( $cache_nextgen_group as $n_key ) {
					wp_cache_delete( $n_key, 'nextgen' );
				}
			}
			restore_current_blog();
		}
		$offset += $limit;
	}
} else {
	delete_metadata( $meta_type, null, $meta_key, $meta_value, $delete_all );
	delete_metadata( $meta_type, null, 'ps-smush-lossy', '', $delete_all );
	delete_metadata( $meta_type, null, 'ps-smush-resize_savings', '', $delete_all );
	delete_metadata( $meta_type, null, 'ps-smush-original_file', '', $delete_all );
	delete_metadata( $meta_type, null, 'ps-smush-pngjpg_savings', '', $delete_all );
}
//Delete Directory smush table
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}smush_dir_images" );

//@todo: Add procedure to delete backup files
//@todo: Update NextGen Metadata to remove Smush stats on plugin deletion