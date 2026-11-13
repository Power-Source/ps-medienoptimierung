<?php
/**
 * Integration tests: wp --skip-plugins --skip-themes eval-file tests/video.php
 * Run only against a local test installation with GStreamer tools installed.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

require_once dirname( __DIR__ ) . '/lib/class-ps-smush-video.php';
require_once dirname( __DIR__ ) . '/lib/class-ps-smush-video-admin.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

class PS_Video_Test_Engine extends PS_Smush_Video {
	public function __construct() {}

	public function command( array $args, $timeout = 30 ) {
		return $this->run( $args, $timeout );
	}

	public function inspect( $file ) {
		return $this->probe( $file, $this->backend() );
	}
}

class PS_Video_Test_No_Encoder extends PS_Video_Test_Engine {
	protected function binary( $name ) {
		return false;
	}
}

class PS_Video_Test_Bad_Output extends PS_Video_Test_Engine {
	protected function encode( $source, $target, array $info, array $backend, $quality ) {
		file_put_contents( $target, 'not a video' );
		return '';
	}
}

class PS_Video_Test_No_Audio extends PS_Video_Test_Engine {
	protected function encode( $source, $target, array $info, array $backend, $quality ) {
		$info['audio'] = false;
		if ( 'FFmpeg' === $backend['name'] ) {
			// A genuine silent MP4, not a mocked validation result.
			return $this->run( array( $backend['encode'], '-nostdin', '-y', '-v', 'error', '-i', $source, '-an', '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-f', 'mp4', $target ) );
		}
		return parent::encode( $source, $target, $info, $backend, $quality );
	}
}

class PS_Video_Test_Changed_Source extends PS_Video_Test_Engine {
	protected function encode( $source, $target, array $info, array $backend, $quality ) {
		$result = parent::encode( $source, $target, $info, $backend, $quality );
		file_put_contents( $source, 'changed during conversion', FILE_APPEND );
		return $result;
	}
}

class PS_Video_Test_Response extends RuntimeException {}

class PS_Video_Test_Larger_Output extends PS_Video_Test_Engine {
	protected function encode( $source, $target, array $info, array $backend, $quality ) {
		$result = parent::encode( $source, $target, $info, $backend, $quality );
		if ( ! is_wp_error( $result ) ) {
			file_put_contents( $target, pack( 'N', 1048584 ) . 'free' . str_repeat( "\0", 1048576 ), FILE_APPEND );
		}
		return $result;
	}
}

$engine = new PS_Video_Test_Engine();
$settings = array( 'auto' => 0, 'backup' => 0, 'quality' => 23, 'resize' => 1 );
$filter = static function () use ( &$settings ) {
	return $settings;
};
add_filter( 'pre_option_' . PS_Smush_Video::SETTINGS, $filter );
add_filter( 'pre_site_option_' . PS_Smush_Video::SETTINGS, $filter );
$log_cutoff = '0';
$read_log_cutoff = static function () use ( &$log_cutoff ) {
	return $log_cutoff;
};
$write_log_cutoff = static function ( $value, $old ) use ( &$log_cutoff ) {
	$log_cutoff = $value;
	return $old;
};
add_filter( 'pre_option_' . PS_Smush_Video_Admin::LOG_CLEARED, $read_log_cutoff );
add_filter( 'pre_update_option_' . PS_Smush_Video_Admin::LOG_CLEARED, $write_log_cutoff, 10, 2 );
$uploads = wp_upload_dir();
$directory = trailingslashit( $uploads['basedir'] ) . 'ps-video-test-' . wp_generate_password( 12, false, false );
$ids = array();
$files = array();
$checks = 0;
$user_id = get_current_user_id();
$assert = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	$checks++;
	echo "PASS: $message\n";
};
$make = static function ( $name, $source ) use ( &$ids, &$files, $directory ) {
	$file = $directory . '/' . $name;
	copy( $source, $file );
	$files[] = $file;
	$id = wp_insert_attachment( array( 'post_title' => 'Video integration test', 'post_status' => 'inherit', 'post_mime_type' => 'video/mp4' ), $file, 0, true );
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( $id->get_error_message() );
	}
	$ids[] = $id;
	return array( $id, $file );
};
$ajax = static function ( $callback, array $post ) {
	$old_post = $_POST;
	$old_request = $_REQUEST;
	$_POST = $post;
	$_REQUEST = $post;
	$handler = static function () {
		return static function ( $message ) {
			throw new PS_Video_Test_Response( (string) $message );
		};
	};
	add_filter( 'wp_doing_ajax', '__return_true' );
	add_filter( 'wp_die_ajax_handler', $handler, 999 );
	ob_start();
	try {
		call_user_func( $callback );
		throw new RuntimeException( 'AJAX handler did not terminate with a response.' );
	} catch ( PS_Video_Test_Response $response ) {
		return array( 'data' => json_decode( ob_get_contents(), true ), 'die' => $response->getMessage() );
	} finally {
		ob_end_clean();
		$_POST = $old_post;
		$_REQUEST = $old_request;
		remove_filter( 'wp_doing_ajax', '__return_true' );
		remove_filter( 'wp_die_ajax_handler', $handler, 999 );
	}
};

try {
	if ( ! wp_mkdir_p( $directory ) ) {
		throw new RuntimeException( 'Cannot create isolated test directory.' );
	}
	$backend = $engine->backend();
	if ( is_wp_error( $backend ) ) {
		throw new RuntimeException( $backend->get_error_message() );
	}
	echo 'Testing backend: ' . $backend['name'] . "\n";
	$silent = $directory . '/source.mp4';
	$audio = $directory . '/source-audio.mp4';
	$files[] = $silent;
	$files[] = $audio;
	$result = $engine->command( array( 'gst-launch-1.0', '-q', '-e',
		'videotestsrc', 'num-buffers=60', '!', 'video/x-raw,format=Y444,width=321,height=241,framerate=60/1',
		'!', 'x264enc', 'threads=2', 'speed-preset=ultrafast', '!', 'h264parse', '!', 'mp4mux', '!', 'filesink', 'location=' . $silent ) );
	$assert( ! is_wp_error( $result ), 'Generate real 4:4:4, odd-dimension, 60fps source' );
	$result = $engine->command( array( 'gst-launch-1.0', '-q', '-e', 'mp4mux', 'name=mux', '!', 'filesink', 'location=' . $audio,
		'videotestsrc', 'num-buffers=60', '!', 'video/x-raw,format=Y444,width=320,height=240,framerate=30/1',
		'!', 'x264enc', 'threads=2', 'speed-preset=ultrafast', '!', 'h264parse', '!', 'queue', '!', 'mux.',
		'audiotestsrc', 'num-buffers=94', '!', 'audio/x-raw,rate=48000', '!', 'audioconvert',
		'!', 'lamemp3enc', '!', 'mpegaudioparse', '!', 'queue', '!', 'mux.' ) );
	$assert( ! is_wp_error( $result ), 'Generate real video with non-AAC audio' );
	$assert( ! $engine->inspect( $silent )['yuv420'], 'Fixture really is not 8-bit 4:2:0' );

	list( $id, $file ) = $make( 'spaces and \'quotes".mp4', $silent );
	$url = wp_get_attachment_url( $id );
	$before = filesize( $file );
	$result = $engine->optimize( $id );
	$assert( ! is_wp_error( $result ), 'Convert silent MP4 safely: ' . ( is_wp_error( $result ) ? $result->get_error_message() : 'OK' ) );
	$info = $engine->inspect( $file );
	$assert( $info['mp4'] && $info['h264'] && $info['yuv420'] && ! $info['audio'], 'Output is actual MP4/H.264/8-bit 4:2:0 without invented audio' );
	$assert( 320 === $info['width'] && 240 === $info['height'], 'Odd dimensions become even without upscaling' );
	$assert( $info['mobile_profile'] && abs( 30 - $info['fps'] ) < 0.01, 'Verify actual Baseline and 30fps output' );
	$assert( $url === wp_get_attachment_url( $id ) && get_attached_file( $id ) === $file, 'Existing URL and attached path stay unchanged' );
	clearstatcache( true, $file );
	$assert( $before === $result['before'] && filesize( $file ) === $result['after'] && $result['saved'] === $before - filesize( $file ), 'Report exact byte sizes and signed size change' );
	$assert( ! file_exists( $file . '.ps-original' ), 'Original backup is disabled by default' );
	$metadata = wp_get_attachment_metadata( $id );
	$assert( 320 === $metadata['width'] && filesize( $file ) === $metadata['filesize'], 'WordPress video metadata is refreshed' );
	$hash = hash_file( 'sha256', $file );
	$again = $engine->optimize( $id );
	$assert( ! is_wp_error( $again ) && $hash === hash_file( 'sha256', $file ), 'Repeated bulk run does not re-encode unchanged output' );
	$mobile_file = $file;

	foreach ( array( array( 'large', 1600, 900, 1280, 720 ), array( 'rotated', 320, 240, 240, 320 ), array( 'anamorphic', 320, 240, 640, 240 ) ) as $case ) {
		$fixture = $directory . '/' . $case[0] . '-source.mp4';
		$files[] = $fixture;
		$command = array( 'gst-launch-1.0', '-q', '-e', 'videotestsrc', 'num-buffers=30', '!', 'video/x-raw,format=Y444,width=' . $case[1] . ',height=' . $case[2] . ',framerate=30/1,pixel-aspect-ratio=' . ( 'anamorphic' === $case[0] ? '2/1' : '1/1' ),
			'!', 'x264enc', 'threads=2', 'speed-preset=ultrafast', '!', 'h264parse' );
		if ( 'rotated' === $case[0] ) {
			$command = array_merge( $command, array( '!', 'taginject', 'tags=image-orientation=rotate-90' ) );
		}
		$result = $engine->command( array_merge( $command, array( '!', 'mp4mux', '!', 'filesink', 'location=' . $fixture ) ) );
		$assert( ! is_wp_error( $result ), 'Generate ' . $case[0] . ' source' );
		list( $id, $file ) = $make( $case[0] . '.mp4', $fixture );
		$result = $engine->optimize( $id );
		$assert( ! is_wp_error( $result ), 'Convert ' . $case[0] . ' video: ' . ( is_wp_error( $result ) ? $result->get_error_message() : 'OK' ) );
		$info = $engine->inspect( $file );
		$assert( $case[3] === $info['width'] && $case[4] === $info['height'] && ! $info['rotated'], 'Correct ' . $case[0] . ' output dimensions and orientation' );
	}

	$stats_before = $engine->statistics();
	$settings['compress'] = 0;
	list( $id, $file ) = $make( 'already-correct.mp4', $mobile_file );
	$hash = hash_file( 'sha256', $file );
	$result = $engine->check( $id );
	$assert( ! is_wp_error( $result ) && $result['compatible'] && ! $engine->needs_optimization( $id ) && $hash === hash_file( 'sha256', $file ), 'Recheck recognizes correct codecs without changing video bytes' );
	$stats_after = $engine->statistics();
	$assert( $stats_after['correct'] === $stats_before['correct'] + 1, 'Statistics include already-compatible videos that were never converted' );
	$settings['compress'] = 1;
	$engine->check( $id );
	$assert( $engine->needs_optimization( $id ), 'Enabling compression makes compatible unchecked-for-size videos eligible' );
	$result = ( new PS_Video_Test_Larger_Output() )->optimize( $id );
	$assert( ! is_wp_error( $result ) && 0 === $result['saved'] && $hash === hash_file( 'sha256', $file ), 'Larger compression result never replaces an already-compatible original' );
	$assert( ! $engine->needs_optimization( $id ), 'A non-beneficial compression attempt is not retried on every bulk run' );

	list( $id, $file ) = $make( 'larger-but-correct-codec.mp4', $silent );
	$stats_before = $engine->statistics();
	$result = ( new PS_Video_Test_Larger_Output() )->optimize( $id );
	$assert( ! is_wp_error( $result ) && $result['saved'] < 0 && $engine->inspect( $file )['yuv420'], 'Required codec conversion may grow the file and reports negative savings' );
	$stats_after = $engine->statistics();
	$assert( $stats_after['saved'] - $stats_before['saved'] === $result['saved'], 'Aggregate savings preserve increases instead of hiding them' );

	$settings['resize'] = 0;
	list( $id, $file ) = $make( 'optional-resize.mp4', $directory . '/large-source.mp4' );
	$initial_bytes = filesize( $file );
	$stats_before = $engine->statistics();
	$result = $engine->optimize( $id );
	$info = $engine->inspect( $file );
	$assert( ! is_wp_error( $result ) && 1600 === $info['width'] && 900 === $info['height'], 'Disabled size limiting preserves original display resolution' );
	$settings['resize'] = 1;
	$settings['width'] = 640;
	$settings['height'] = 360;
	$hash = hash_file( 'sha256', $file );
	$result = $engine->check( $id );
	$assert( ! is_wp_error( $result ) && $engine->needs_optimization( $id ) && $hash === hash_file( 'sha256', $file ), 'Rechecking changed size settings queues resizing without modifying files' );
	$result = $engine->optimize( $id );
	$info = $engine->inspect( $file );
	$assert( ! is_wp_error( $result ) && 640 === $info['width'] && 360 === $info['height'] && $result['resized'], 'Configured bounds resize proportionally and record the result' );
	$stats_after = $engine->statistics();
	$assert( $stats_after['optimized'] === $stats_before['optimized'] + 1 && $stats_after['saved'] - $stats_before['saved'] === $initial_bytes - filesize( $file ), 'Repeated optimization does not double-count videos or cumulative savings' );
	$engine->check( $id );
	$assert( $engine->statistics()['saved'] === $stats_after['saved'], 'Rechecking a converted video preserves cumulative savings' );
	$settings = array( 'auto' => 0, 'backup' => 0, 'quality' => 23, 'resize' => 1 );

	list( $id, $file ) = $make( 'audio.mp4', $audio );
	$settings['backup'] = 1;
	$hash = hash_file( 'sha256', $file );
	$result = $engine->optimize( $id );
	$assert( ! is_wp_error( $result ), 'Convert video with MP3 audio: ' . ( is_wp_error( $result ) ? $result->get_error_message() : 'OK' ) );
	$files[] = $file . '.ps-original';
	$info = $engine->inspect( $file );
	$assert( $info['audio'] && $info['aac'] && $info['yuv420'], 'Audio survives and becomes AAC alongside mobile video' );
	$assert( $hash === hash_file( 'sha256', $file . '.ps-original' ), 'Optional backup is byte-identical to original' );
	$settings['quality'] = 24;
	$result = $engine->optimize( $id );
	$assert( ! is_wp_error( $result ) && $hash === hash_file( 'sha256', $file . '.ps-original' ), 'Changed quality reprocesses without overwriting first backup' );
	$settings = array( 'auto' => 0, 'backup' => 0, 'quality' => 23, 'resize' => 1 );

	foreach ( array( new PS_Video_Test_No_Encoder(), new PS_Video_Test_Bad_Output(), new PS_Video_Test_No_Audio(), new PS_Video_Test_Changed_Source() ) as $failing ) {
		list( $id, $file ) = $make( 'failure-' . $checks . '.mp4', $audio );
		$hash = hash_file( 'sha256', $file );
		$result = $failing->optimize( $id );
		$assert( is_wp_error( $result ), get_class( $failing ) . ' reports an explicit error' );
		$expected = $failing instanceof PS_Video_Test_Changed_Source ? hash( 'sha256', file_get_contents( $audio ) . 'changed during conversion' ) : $hash;
		$assert( $expected === hash_file( 'sha256', $file ), 'Failure does not replace source file' );
		$assert( 'error' === get_post_meta( $id, PS_Smush_Video::META, true )['status'], 'Failure is persisted on the attachment' );
	}
	list( $id, $file ) = $make( 'unchanged.mov', $silent );
	$stats_before = $engine->statistics();
	$hash = hash_file( 'sha256', $file );
	$result = $engine->optimize( $id );
	$assert( is_wp_error( $result ) && 'video_extension' === $result->get_error_code() && $hash === hash_file( 'sha256', $file ), 'Non-MP4 URLs are not silently broken or mislabeled' );
	$stats_after = $engine->statistics();
	$assert( ! $engine->needs_optimization( $id ) && $stats_after['unsupported'] === $stats_before['unsupported'] + 1 && $stats_after['unchecked'] === $stats_before['unchecked'] - 1, 'Known unsupported videos are not counted as unchecked or retried in bulk' );

	list( $id, $file ) = $make( 'offloaded-local-copy.mp4', $silent );
	$hash = hash_file( 'sha256', $file );
	$original_s3 = isset( $GLOBALS['as3cf'] ) ? $GLOBALS['as3cf'] : null;
	$GLOBALS['as3cf'] = new class {
		public function is_attachment_served_by_s3( $id ) {
			return true;
		}
	};
	try {
		$result = $engine->optimize( $id );
		$assert( is_wp_error( $result ) && 'video_offloaded' === $result->get_error_code() && $hash === hash_file( 'sha256', $file ), 'Offloaded videos are not reported as optimized just because a local copy exists' );
	} finally {
		$GLOBALS['as3cf'] = $original_s3;
	}

	list( $id, $file ) = $make( 'locked.mp4', $silent );
	$lock = fopen( trailingslashit( $uploads['basedir'] ) . '.ps-video.lock', 'c' );
	flock( $lock, LOCK_EX );
	$result = $engine->optimize( $id );
	$checked = $engine->check( $id );
	flock( $lock, LOCK_UN );
	fclose( $lock );
	$assert( is_wp_error( $result ) && 'video_busy' === $result->get_error_code(), 'Parallel conversion is prevented' );
	$assert( is_wp_error( $checked ) && 'video_busy' === $checked->get_error_code(), 'Codec scanning cannot race a conversion' );

	$result = $engine->command( array( PHP_BINARY, '-r', 'usleep(500000);' ), 0.05 );
	$assert( is_wp_error( $result ) && 'video_timeout' === $result->get_error_code(), 'Long-running subprocess is terminated on timeout' );
	$assert( array() === glob( $directory . '/.ps-video-*' ), 'Temporary outputs are cleaned on successes and failures' );

	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	if ( ! $admins ) {
		throw new RuntimeException( 'A local administrator account is required for AJAX tests.' );
	}
	wp_set_current_user( (int) $admins[0] );
	$admin = new PS_Smush_Video_Admin( $engine );
	$cursor = max( $ids );
	$batch = array();
	for ( $i = 0; $i < 26; $i++ ) {
		list( $id ) = $make( 'batch-' . $i . '.mp4', $silent );
		$batch[] = $id;
	}
	$ceiling = max( $batch );
	list( $extra ) = $make( 'after-snapshot.mp4', $silent );
	$nonce = wp_create_nonce( 'ps_smush_video' );
	$response = $ajax( array( $admin, 'listing' ), array( 'nonce' => $nonce, 'cursor' => $cursor, 'ceiling' => $ceiling ) )['data'];
	$assert( $response['success'] && 25 === count( $response['data']['items'] ) && ! $response['data']['done'], 'Bulk list returns a bounded first page' );
	$listed = array_column( $response['data']['items'], 'id' );
	$response = $ajax( array( $admin, 'listing' ), array( 'nonce' => $nonce, 'cursor' => $response['data']['cursor'], 'ceiling' => $ceiling ) )['data'];
	$listed = array_merge( $listed, array_column( $response['data']['items'], 'id' ) );
	$assert( $response['data']['done'] && array() === array_diff( $batch, $listed ) && ! in_array( $extra, $listed, true ) && count( $listed ) === count( array_unique( $listed ) ), 'Bulk pagination has no omissions or duplicates and respects snapshot ceiling' );
	$response = $ajax( array( $admin, 'process' ), array( 'nonce' => $nonce, 'id' => $extra ) )['data'];
	$assert( $response['success'] && isset( $response['data']['before_human'], $response['data']['after_human'], $response['data']['change_human'], $response['data']['saved'] ), 'Bulk processing returns real conversion result and size change fields' );
	$file = get_attached_file( $extra, true );
	$hash = hash_file( 'sha256', $file );
	$response = $ajax( array( $admin, 'process' ), array( 'nonce' => $nonce, 'id' => $extra, 'mode' => 'check' ) )['data'];
	$assert( $response['success'] && $response['data']['compatible'] && $hash === hash_file( 'sha256', $file ), 'Recheck endpoint returns compatibility without changing video bytes' );
	$response = $ajax( array( $admin, 'listing' ), array( 'nonce' => $nonce, 'cursor' => $ceiling, 'ceiling' => $extra ) )['data'];
	$assert( $response['success'] && array() === $response['data']['items'], 'Optimization listing excludes already processed videos' );
	$response = $ajax( array( $admin, 'statistics' ), array( 'nonce' => $nonce ) )['data'];
	$assert( $response['success'] && isset( $response['data']['total'], $response['data']['correct'], $response['data']['pending'], $response['data']['saved_human'] ) && is_int( $response['data']['total'] ), 'Statistics endpoint exposes typed counts and formatted savings' );
	$log_entry = esc_url( get_edit_post_link( $extra ) );
	ob_start();
	$admin->render();
	$html = ob_get_clean();
	$assert( false !== strpos( $html, $log_entry ) && false !== strpos( $html, 'id="ps-video-clear-log"' ), 'Persisted results include the latest processed video and a clear-log control' );
	$status_before = get_post_meta( $extra, PS_Smush_Video::META, true );
	$stats_before = $engine->statistics();
	$response = $ajax( array( new PS_Smush_Video_Admin( new PS_Video_Test_No_Encoder() ), 'clear_log' ), array( 'nonce' => $nonce ) )['data'];
	ob_start();
	$admin->render();
	$html = ob_get_clean();
	$assert( $response['success'] && false === strpos( $html, $log_entry ), 'Clearing the log persists after rerendering and needs no encoder' );
	$assert( $status_before === get_post_meta( $extra, PS_Smush_Video::META, true ) && $stats_before === $engine->statistics(), 'Clearing logs preserves optimization state, savings and unresolved errors' );
	$seconds = (string) ( time() + 3600 );
	$log_cutoff = $seconds . '.250000';
	update_post_meta( $extra, PS_Smush_Video::LOG_TIME, $seconds . '.500000' );
	ob_start();
	$admin->render();
	$html = ob_get_clean();
	$assert( false !== strpos( $html, $log_entry ), 'New log entries remain visible even within the same second as clearing' );
	$log_cutoff = '0';
	$response = $ajax( array( $admin, 'process' ), array( 'nonce' => 'invalid', 'id' => $extra ) );
	$assert( '-1' === $response['die'], 'Bulk requests reject invalid nonces' );
	wp_set_current_user( 0 );
	$response = $ajax( array( $admin, 'process' ), array( 'nonce' => wp_create_nonce( 'ps_smush_video' ), 'id' => $extra ) )['data'];
	$assert( ! $response['success'], 'Bulk requests reject unauthorized users' );
	$response = $ajax( array( $admin, 'clear_log' ), array( 'nonce' => wp_create_nonce( 'ps_smush_video' ) ) )['data'];
	$assert( ! $response['success'] && '0' === $log_cutoff, 'Unauthorized users cannot clear the website video log' );

	new PS_Smush_Video();
	list( $id, $file ) = $make( 'automatic-disabled.mp4', $silent );
	$engine->queue_upload( $id );
	$assert( ! wp_next_scheduled( PS_Smush_Video::CRON, array( $id ) ), 'Auto conversion is disabled by default' );
	$settings['auto'] = 1;
	list( $id, $file ) = $make( 'automatic.mp4', $silent );
	$assert( (bool) wp_next_scheduled( PS_Smush_Video::CRON, array( $id ) ), 'Actual attachment hook queues automatic conversion' );
	do_action( PS_Smush_Video::CRON, $id );
	$assert( 'success' === get_post_meta( $id, PS_Smush_Video::META, true )['status'], 'Upload job runs the same verified conversion' );
	$fields = ( new PS_Smush_Video_Admin( $engine ) )->attachment_status( array(), get_post( $id ) );
	$assert( isset( $fields['ps_video_status'] ) && false !== strpos( $fields['ps_video_status']['html'], '4:2:0' ), 'Media dialog shows conversion result' );
	wp_clear_scheduled_hook( PS_Smush_Video::CRON, array( $id ) );
	$engine->queue_upload( $id );
	$assert( ! wp_next_scheduled( PS_Smush_Video::CRON, array( $id ) ) && 'success' === get_post_meta( $id, PS_Smush_Video::META, true )['status'], 'Automatic retry does not requeue an already completed optimization' );
	$stats_before = $engine->statistics();
	$settings['auto'] = 0;
	$engine->process_upload( $id );
	$assert( 'paused' === get_post_meta( $id, PS_Smush_Video::META, true )['status'], 'Queued jobs respect disabled automation' );
	$stats_after = $engine->statistics();
	$assert( $stats_before['optimized'] === $stats_after['optimized'] && $stats_before['saved'] === $stats_after['saved'], 'Pausing an old upload job preserves optimization history and savings' );

	echo "All $checks checks passed.\n";
} finally {
	foreach ( $ids as $id ) {
		$engine->delete_attachment( $id );
		wp_delete_attachment( $id, true );
	}
	foreach ( $files as $file ) {
		if ( is_file( $file ) ) {
			unlink( $file );
		}
	}
	if ( is_dir( $directory ) ) {
		rmdir( $directory );
	}
	remove_filter( 'pre_option_' . PS_Smush_Video::SETTINGS, $filter );
	remove_filter( 'pre_site_option_' . PS_Smush_Video::SETTINGS, $filter );
	remove_filter( 'pre_option_' . PS_Smush_Video_Admin::LOG_CLEARED, $read_log_cutoff );
	remove_filter( 'pre_update_option_' . PS_Smush_Video_Admin::LOG_CLEARED, $write_log_cutoff );
	wp_set_current_user( $user_id );
}
