<?php
/**
 * UI and settings routing: wp --skip-plugins --skip-themes eval-file tests/video-settings.php
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

require_once dirname( __DIR__ ) . '/ps-medienoptimierung.php';

class PS_Video_Settings_UI extends WpSmushBulkUi {
	public function __construct() {}
	public function smush_page_header() {}
	public function smush_page_footer() {}
	public function bulk_smush_container() {}
	public function smush_stats_container() {}
	public function quick_setup() {}
}

class PS_Video_Settings_Response extends RuntimeException {}

$networkwide = false;
$network_values = array( 'auto' => 1, 'backup' => 1, 'quality' => 21 );
$local_values = array( get_current_blog_id() => array( 'auto' => 0, 'backup' => 0, 'quality' => 27 ) );
$user_id = get_current_user_id();
$screen = get_current_screen();
$image_settings = $wpsmush_settings->settings;
$post = $_POST;
$request = $_REQUEST;
$checks = 0;
$protected_options = array();
$assert = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	$checks++;
	echo "PASS: $message\n";
};
$network_flag = static function () use ( &$networkwide ) {
	return $networkwide ? 1 : 0;
};
$read_local = static function () use ( &$local_values ) {
	return isset( $local_values[ get_current_blog_id() ] ) ? $local_values[ get_current_blog_id() ] : array( 'auto' => 0, 'backup' => 0, 'quality' => 28 );
};
$read_network = static function () use ( &$network_values ) {
	return $network_values;
};
$write_local = static function ( $value, $old ) use ( &$local_values ) {
	$local_values[ get_current_blog_id() ] = $value;
	return $old;
};
$write_network = static function ( $value, $old ) use ( &$network_values ) {
	$network_values = $value;
	return $old;
};
$die_handler = static function () {
	return static function ( $message ) {
		throw new PS_Video_Settings_Response( (string) $message );
	};
};
$keep_image_option = static function ( $value, $old ) {
	return $old;
};
$last_image_settings = static function () use ( $image_settings ) {
	return $image_settings;
};
add_filter( 'pre_site_option_ps-smush-networkwide', $network_flag );
add_filter( 'pre_option_' . PS_Smush_Video::SETTINGS, $read_local );
add_filter( 'pre_site_option_' . PS_Smush_Video::SETTINGS, $read_network );
add_filter( 'pre_update_option_' . PS_Smush_Video::SETTINGS, $write_local, 10, 2 );
add_filter( 'pre_update_site_option_' . PS_Smush_Video::SETTINGS, $write_network, 10, 2 );
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', $die_handler, 999 );
$render = static function ( $ui ) {
	ob_start();
	try {
		$ui->ui();
		return ob_get_contents();
	} finally {
		ob_end_clean();
	}
};

try {
	if ( is_multisite() ) {
		$admins = get_super_admins();
		$admin = $admins ? get_user_by( 'login', $admins[0] ) : false;
	} else {
		$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
		$admin = $admins ? $admins[0] : false;
	}
	if ( ! $admin ) {
		throw new RuntimeException( 'A local administrator is required.' );
	}
	wp_set_current_user( $admin->ID );
	$wpsmushit_admin->init_settings();
	$protected_options = array_merge( array_keys( $wpsmushit_admin->settings ), array( 'last_settings', 'image_sizes', 'resize_sizes', 'settings_updated', 'hide_smush_welcome', 'run_recheck' ) );
	foreach ( $protected_options as $option ) {
		add_filter( 'pre_update_option_ps-smush-' . $option, $keep_image_option, 10, 2 );
		add_filter( 'pre_update_site_option_ps-smush-' . $option, $keep_image_option, 10, 2 );
	}
	add_filter( 'pre_option_ps-smush-last_settings', $last_image_settings );
	add_filter( 'pre_site_option_ps-smush-last_settings', $last_image_settings );
	$wpsmush_settings->settings['s3'] = 0;
	$ui = new PS_Video_Settings_UI();
	set_current_screen( 'media_page_ps-smush-bulk' );
	$wpsmush_settings->settings['networkwide'] = 0;
	$html = $render( $ui );
	$start = strpos( $html, '<form id="ps-smush-settings-form"' );
	$end = strpos( $html, '</form>', $start );
	$video = strpos( $html, 'name="ps-smush-video-auto"' );
	$assert( false !== $start && $video > $start && $video < $end && 1 === substr_count( $html, '<form' ), 'Image and video controls share exactly one settings form' );
	$assert( false !== strpos( $html, 'id="ps-smush-video-bulk"' ), 'Video batch processing stays on the existing media optimization page' );
	$assert( false !== strpos( $html, 'id="ps-video-recheck"' ) && false !== strpos( $html, 'data-video-stat="correct"' ) && false !== strpos( $html, 'data-video-stat="saved_human"' ), 'Video overview exposes recheck, compatible counts and real savings in the shared UI' );
	$assert( 1 === substr_count( $html, 'name="ps-smush-video-width"' ) && 1 === substr_count( $html, 'name="ps-smush-video-height"' ), 'Video size controls appear once inside the common settings form' );
	$assert( false === has_action( 'admin_menu', array( $ps_smush_video_admin, 'menu' ) ) && false === strpos( $html, 'page=ps-smush-video' ), 'No additional video menu or separate settings page' );
	$assert( 27 === $ps_smush_video->settings()['quality'], 'Independent website reads its own video configuration' );

	$_POST = array(
		'ps-smush-video-settings-present' => '1',
		'ps_smush_video_nonce' => wp_create_nonce( 'ps_smush_video_settings' ),
		'ps-smush-video-quality' => '25',
		'ps-smush-video-auto' => '1',
		'ps-smush-video-compress' => '1',
		'ps-smush-video-resize' => '1',
		'ps-smush-video-width' => '640',
		'ps-smush-video-height' => '360',
	);
	$_REQUEST = $_POST;
	$wpsmush_settings->process_options();
	$assert( 25 === $local_values[ get_current_blog_id() ]['quality'] && 21 === $network_values['quality'], 'Shared save hook updates only local video settings when central control is off' );
	$assert( 1 === $local_values[ get_current_blog_id() ]['compress'] && 1 === $local_values[ get_current_blog_id() ]['resize'] && 640 === $local_values[ get_current_blog_id() ]['width'] && 360 === $local_values[ get_current_blog_id() ]['height'], 'Shared save persists independent video compression and size bounds' );
	$_POST['ps-smush-video-width'] = '641';
	$_REQUEST = $_POST;
	ob_start();
	try {
		$wpsmush_settings->process_options();
		throw new RuntimeException( 'Odd video dimensions should have been rejected.' );
	} catch ( PS_Video_Settings_Response $response ) {
		$data = json_decode( ob_get_contents(), true );
		$rejected = isset( $data['success'] ) && ! $data['success'];
	} finally {
		ob_end_clean();
	}
	$assert( $rejected && 640 === $local_values[ get_current_blog_id() ]['width'], 'Invalid video dimensions produce a visible save error without overwriting settings' );
	$_POST['ps-smush-video-width'] = '640';
	$_REQUEST = $_POST;

	if ( is_multisite() ) {
		set_current_screen( 'settings_page_ps-smush-network' );
		$networkwide = true;
		$wpsmush_settings->settings['networkwide'] = 1;
		$html = $render( $ui );
		$start = strpos( $html, '<form id="ps-smush-settings-form"' );
		$end = strpos( $html, '</form>', $start );
		$video = strpos( $html, 'name="ps-smush-video-auto"' );
		$assert( is_network_admin() && $video > $start && $video < $end && 1 === substr_count( $html, '<form' ), 'Network media optimization includes video controls in the existing form' );
		$assert( false === strpos( $html, 'id="ps-smush-video-bulk"' ), 'Network settings do not accidentally process main-site videos' );

		set_current_screen( 'media_page_ps-smush-bulk' );
		$html = $render( $ui );
		$assert( false === strpos( $html, 'name="ps-smush-video-auto"' ) && false !== strpos( $html, 'id="ps-smush-video-bulk"' ), 'Centrally controlled subsites have no competing settings form but retain batch processing' );
		$assert( 21 === $ps_smush_video->settings()['quality'] && 1 === $ps_smush_video->settings()['auto'], 'Website upload jobs inherit central video settings' );
		$blogs = get_sites( array( 'number' => 2, 'fields' => 'ids' ) );
		foreach ( $blogs as $blog_id ) {
			switch_to_blog( $blog_id );
			try {
				$assert( 21 === $ps_smush_video->settings()['quality'], 'Network configuration applies consistently after switching website' );
			} finally {
				restore_current_blog();
			}
		}

		ob_start();
		try {
			$ps_smush_video_admin->save();
			throw new RuntimeException( 'Local override should have been rejected.' );
		} catch ( PS_Video_Settings_Response $response ) {
			$data = json_decode( ob_get_contents(), true );
			$assertion = isset( $data['success'] ) && ! $data['success'];
		} finally {
			ob_end_clean();
		}
		$assert( $assertion && 21 === $network_values['quality'], 'Subsite save cannot override inherited network video options' );

		// Network AJAX runs without a network screen; scope comes from the shared form.
		$_POST['setting-type'] = 'network';
		$_POST['action'] = 'save_settings';
		$_POST['ps-smush-networkwide'] = '1';
		$_POST['ps-smush-video-quality'] = '22';
		$_REQUEST = $_POST;
		$wpsmush_settings->settings['s3'] = 0;
		$wpsmush_settings->process_options();
		$assert( 22 === $network_values['quality'] && 25 === $local_values[ get_current_blog_id() ]['quality'], 'Network AJAX saves network video values without altering local preferences' );
		$networkwide = false;
		$assert( 25 === $ps_smush_video->settings()['quality'], 'Disabling central control restores the website configuration' );
	}
	echo "All $checks settings checks passed.\n";
} finally {
	remove_filter( 'pre_site_option_ps-smush-networkwide', $network_flag );
	remove_filter( 'pre_option_' . PS_Smush_Video::SETTINGS, $read_local );
	remove_filter( 'pre_site_option_' . PS_Smush_Video::SETTINGS, $read_network );
	remove_filter( 'pre_update_option_' . PS_Smush_Video::SETTINGS, $write_local );
	remove_filter( 'pre_update_site_option_' . PS_Smush_Video::SETTINGS, $write_network );
	remove_filter( 'wp_doing_ajax', '__return_true' );
	remove_filter( 'wp_die_ajax_handler', $die_handler, 999 );
	foreach ( $protected_options as $option ) {
		remove_filter( 'pre_update_option_ps-smush-' . $option, $keep_image_option );
		remove_filter( 'pre_update_site_option_ps-smush-' . $option, $keep_image_option );
	}
	remove_filter( 'pre_option_ps-smush-last_settings', $last_image_settings );
	remove_filter( 'pre_site_option_ps-smush-last_settings', $last_image_settings );
	$wpsmush_settings->settings = $image_settings;
	$_POST = $post;
	$_REQUEST = $request;
	$GLOBALS['current_screen'] = $screen;
	wp_set_current_user( $user_id );
}
