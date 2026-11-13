<?php
/**
 * Translation integration checks: wp --skip-plugins --skip-themes eval-file tests/i18n.php
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

require_once dirname( __DIR__ ) . '/ps-medienoptimierung.php';
require_once ABSPATH . WPINC . '/pomo/po.php';
require_once dirname( __DIR__ ) . '/assets/shared-ui/plugin-ui.php';

class PS_I18n_Test_UI extends WpSmushBulkUi {
	public function __construct() {}
	public function smush_page_header() {}
	public function smush_page_footer() {}
	public function bulk_smush_container() {}
	public function smush_stats_container() {}
	public function quick_setup() {}
}

$checks = 0;
$user_id = get_current_user_id();
$screen = get_current_screen();
$image_settings = $wpsmush_settings->settings;
$admin_settings = $wpsmushit_admin->settings;
$scripts = isset( $GLOBALS['wp_scripts'] ) ? $GLOBALS['wp_scripts'] : null;
$submenus = isset( $GLOBALS['submenu'] ) ? $GLOBALS['submenu'] : array();
$menus = isset( $GLOBALS['menu'] ) ? $GLOBALS['menu'] : array();
$loaded_files = array();
$track_load = static function ( $domain, $file ) use ( &$loaded_files ) {
	if ( 'ps-medienoptimierung' === $domain ) {
		$loaded_files[] = basename( $file );
	}
};
$english_gb = static function ( $locale, $domain ) {
	return 'ps-medienoptimierung' === $domain ? 'en_GB' : $locale;
};
$assert = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	$checks++;
	echo "PASS: $message\n";
};
$capture = static function ( $callback ) {
	ob_start();
	try {
		call_user_func( $callback );
		return ob_get_contents();
	} finally {
		ob_end_clean();
	}
};
$tokens = static function ( $message ) {
	preg_match_all( '/%%|%(?:[1-9][0-9]*\$)?[-+0 \'#]*(?:[0-9]+|\*)?(?:\.(?:[0-9]+|\*))?[bcdeEfFgGosuxX]|\{\{[^}]+\}\}/', $message, $matches );
	$result = $matches[0];
	sort( $result );
	return $result;
};
add_filter( 'pre_site_option_ps-smush-networkwide', '__return_zero' );
add_action( 'load_textdomain', $track_load, 10, 2 );

try {
	$template = new PO();
	$assert( $template->import_from_file( dirname( __DIR__ ) . '/languages/ps-medienoptimierung.pot' ), 'Translation template can be read' );
	foreach ( array( 'en_US', 'en_GB' ) as $locale ) {
		$po = new PO();
		$mo = new MO();
		$base = dirname( __DIR__ ) . '/languages/ps-medienoptimierung-' . $locale;
		$assert( $po->import_from_file( $base . '.po' ) && $mo->import_from_file( $base . '.mo' ), $locale . ' source and compiled catalogs can be read' );
		$assert( isset( $po->headers['Language'] ) && $locale === $po->headers['Language'] && count( $template->entries ) === count( $mo->entries ), $locale . ' compiled catalog covers the entire template' );
		foreach ( $template->entries as $key => $entry ) {
			$translated = isset( $po->entries[ $key ] ) ? $po->entries[ $key ] : null;
			if ( ! $translated || in_array( 'fuzzy', $translated->flags, true ) || count( $translated->translations ) !== ( $entry->is_plural ? 2 : 1 ) ) {
				throw new RuntimeException( $locale . ' incomplete entry: ' . $entry->singular );
			}
			foreach ( $translated->translations as $index => $translation ) {
				$source = $index && $entry->is_plural ? $entry->plural : $entry->singular;
				if ( '' === $translation || $tokens( $source ) !== $tokens( $translation ) ) {
					throw new RuntimeException( $locale . ' missing translation or changed placeholders: ' . $source );
				}
			}
		}
		$assert( true, $locale . ' translations preserve placeholders and contain no empty or fuzzy entries' );
	}

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
	$ui = new PS_I18n_Test_UI();
	foreach ( array( 'de_DE', 'en_US' ) as $locale ) {
		$switched = switch_to_locale( $locale );
		try {
			$assert( $locale === determine_locale(), 'WordPress switches to ' . $locale . ' without changing saved preferences' );
			unload_textdomain( 'ps-medienoptimierung', true );
			smush_i18n();
			$english = 'en_US' === $locale;
			$assert( ( $english ? 'Settings' : 'Einstellungen' ) === __( 'Einstellungen', 'ps-medienoptimierung' ), $locale . ' loads the expected plugin language' );
			$assert( 0 === strcasecmp( $english ? 'Video compression' : 'Videokomprimierung', __( 'Videokomprimierung', 'ps-medienoptimierung' ) ), $locale . ' translates video settings' );
			$assert( ( $english ? 'Close' : 'Schließen' ) === __( 'Schließen', 'ps-medienoptimierung' ), $locale . ' translates shared and accessible controls' );

			set_current_screen( 'media_page_ps-smush-bulk' );
			$wpsmushit_admin->init_settings();
			$wpsmush_settings->settings['networkwide'] = 0;
			$html = $capture( array( $ui, 'ui' ) );
			$assert( false !== strpos( $html, esc_html__( 'Automatische Optimierung', 'ps-medienoptimierung' ) ) && false !== strpos( $html, esc_html__( 'Automatische Videooptimierung', 'ps-medienoptimierung' ) ), $locale . ' renders image and video settings in the same language' );
			$dialog = $capture( array( $wpsmush_dir, 'directory_list_dialog' ) );
			$assert( false !== strpos( $dialog, '>' . esc_html__( 'Schließen', 'ps-medienoptimierung' ) . '</span>' ), $locale . ' translates the folder dialog close control' );

			$GLOBALS['wp_scripts'] = new WP_Scripts();
			$ps_smush_video_admin->enqueue( 'media_page_ps-smush-bulk' );
			$data = wp_scripts()->get_data( 'ps-smush-video', 'data' );
			preg_match( '/var psSmushVideo = (\{.*\});/', $data, $matches );
			$messages = isset( $matches[1] ) ? json_decode( $matches[1], true ) : null;
			$assert( is_array( $messages ) && $messages['working'] === __( 'Video wird konvertiert und vollständig geprüft …', 'ps-medienoptimierung' ) && $messages['clearError'] === __( 'Das Videoprotokoll konnte nicht geleert werden.', 'ps-medienoptimierung' ), $locale . ' supplies translated processing and error text to JavaScript' );

			$wpsmushit_admin->screen();
			$entries = array_reverse( $GLOBALS['submenu']['upload.php'] );
			$label = null;
			foreach ( $entries as $item ) {
				if ( 'ps-smush-bulk' === $item[2] ) {
					$label = $item[0];
					break;
				}
			}
			$assert( 0 === strcasecmp( $english ? 'Media optimization' : 'Medienoptimierung', (string) $label ), $locale . ' translates the actual media menu entry' );
		} finally {
			if ( $switched ) {
				restore_previous_locale();
			}
		}
	}

	add_filter( 'plugin_locale', $english_gb, 10, 2 );
	unload_textdomain( 'ps-medienoptimierung', true );
	smush_i18n();
	$assert( 'Settings' === __( 'Einstellungen', 'ps-medienoptimierung' ) && in_array( 'ps-medienoptimierung-en_GB.mo', $loaded_files, true ), 'British English loads its own catalog through the standard plugin locale selection' );
	echo "All $checks localization checks passed.\n";
} finally {
	remove_filter( 'plugin_locale', $english_gb, 10 );
	remove_filter( 'pre_site_option_ps-smush-networkwide', '__return_zero' );
	remove_action( 'load_textdomain', $track_load, 10 );
	unload_textdomain( 'ps-medienoptimierung', true );
	smush_i18n();
	$GLOBALS['wp_scripts'] = $scripts;
	$GLOBALS['submenu'] = $submenus;
	$GLOBALS['menu'] = $menus;
	$wpsmush_settings->settings = $image_settings;
	$wpsmushit_admin->settings = $admin_settings;
	wp_set_current_user( $user_id );
	$GLOBALS['current_screen'] = $screen;
}
