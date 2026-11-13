<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PS_Smush_Video {
	const SETTINGS = 'ps-smush-video-settings';
	const META = '_ps_smush_video';
	const LOG_TIME = '_ps_smush_video_log_time';
	const CRON = 'ps_smush_video_upload';

	private $backend;

	public function __construct() {
		add_action( 'add_attachment', array( $this, 'queue_upload' ) );
		add_action( self::CRON, array( $this, 'process_upload' ) );
		add_action( 'delete_attachment', array( $this, 'delete_attachment' ) );
	}

	public function settings() {
		$network = is_multisite() && ( is_network_admin() || get_site_option( 'ps-smush-networkwide', false ) );
		$settings = $network ? get_site_option( self::SETTINGS, array() ) : get_option( self::SETTINGS, array() );
		return array_merge( array( 'auto' => 0, 'backup' => 0, 'compress' => 1, 'quality' => 23, 'resize' => 0, 'width' => 1280, 'height' => 720 ), (array) $settings );
	}

	private function config_key() {
		$settings = $this->settings();
		unset( $settings['auto'], $settings['backup'] );
		return md5( wp_json_encode( $settings ) );
	}

	private function compatible( array $info ) {
		return $info['mp4'] && $info['h264'] && $info['yuv420'] && ( ! $info['audio'] || $info['aac'] );
	}

	private function needs_resize( array $info ) {
		$settings = $this->settings();
		$width = $info['width'] * $info['pixel_aspect'];
		$height = $info['height'];
		return ! empty( $settings['resize'] ) && ( $info['rotated'] ? $height > $settings['width'] || $width > $settings['height'] : $width > $settings['width'] || $height > $settings['height'] );
	}

	protected function binary( $name ) {
		$directories = array_merge( explode( PATH_SEPARATOR, (string) getenv( 'PATH' ) ), array( '/usr/bin', '/usr/local/bin', '/opt/homebrew/bin' ) );
		foreach ( array_unique( $directories ) as $directory ) {
			// Never search the current working directory.
			if ( '' === $directory || ! preg_match( '~^(?:/|[A-Za-z]:[\\\\/])~', $directory ) ) {
				continue;
			}
			$path = rtrim( $directory, '/\\' ) . DIRECTORY_SEPARATOR . $name . ( '\\' === DIRECTORY_SEPARATOR ? '.exe' : '' );
			if ( is_file( $path ) && is_executable( $path ) ) {
				return $path;
			}
		}
		return false;
	}

	protected function run( array $arguments, $timeout = 900 ) {
		if ( ! function_exists( 'proc_open' ) || ! function_exists( 'proc_terminate' ) ) {
			return new WP_Error( 'process_disabled', __( 'Die lokale Videooptimierung benötigt die PHP-Funktionen proc_open und proc_terminate. Bitte den Hoster um Freigabe bitten.', 'ps-medienoptimierung' ) );
		}
		$environment = getenv();
		$environment['LC_ALL'] = 'C';
		$process = proc_open( $arguments, array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes, null, $environment );
		if ( ! is_resource( $process ) ) {
			return new WP_Error( 'process_start', __( 'Das Videoprogramm konnte nicht gestartet werden.', 'ps-medienoptimierung' ) );
		}
		register_shutdown_function( static function () use ( $process ) {
			if ( is_resource( $process ) ) {
				proc_terminate( $process );
			}
		} );
		fclose( $pipes[0] );
		stream_set_blocking( $pipes[1], false );
		stream_set_blocking( $pipes[2], false );
		$output = '';
		$start = microtime( true );
		$expired = false;
		do {
			$output = substr( $output . stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] ), -262144 );
			$status = proc_get_status( $process );
			if ( ! $status['running'] ) {
				break;
			}
			if ( microtime( true ) - $start > $timeout ) {
				$expired = true;
				proc_terminate( $process );
				usleep( 200000 );
				if ( proc_get_status( $process )['running'] ) {
					proc_terminate( $process, 9 );
				}
				break;
			}
			usleep( 50000 );
		} while ( true );
		$output = substr( $output . stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] ), -262144 );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$exit = proc_close( $process );
		if ( $expired ) {
			return new WP_Error( 'video_timeout', __( 'Zeitlimit der Videoverarbeitung erreicht. Das Original wurde nicht ersetzt.', 'ps-medienoptimierung' ) );
		}
		if ( 0 !== $status['exitcode'] && 0 !== $exit ) {
			return new WP_Error( 'video_process', __( 'Das Videoprogramm hat einen Fehler gemeldet:', 'ps-medienoptimierung' ) . ' ' . sanitize_text_field( substr( $output, -1500 ) ) );
		}
		return $output;
	}

	public function backend() {
		if ( null !== $this->backend ) {
			return $this->backend;
		}
		$ffmpeg = $this->binary( 'ffmpeg' );
		$ffprobe = $this->binary( 'ffprobe' );
		if ( $ffmpeg && $ffprobe ) {
			$encoders = $this->run( array( $ffmpeg, '-hide_banner', '-encoders' ), 15 );
			$probe = $this->run( array( $ffprobe, '-version' ), 15 );
			if ( ! is_wp_error( $encoders ) && ! is_wp_error( $probe ) && preg_match( '/\blibx264\b/', $encoders ) && preg_match( '/\baac\b/', $encoders ) ) {
				$this->backend = array( 'name' => 'FFmpeg', 'encode' => $ffmpeg, 'probe' => $ffprobe );
				return $this->backend;
			}
		}
		$launch = $this->binary( 'gst-launch-1.0' );
		$inspect = $this->binary( 'gst-inspect-1.0' );
		$discover = $this->binary( 'gst-discoverer-1.0' );
		if ( $launch && $inspect && $discover ) {
			$available = true;
			foreach ( array( 'uridecodebin', 'videoconvert', 'videoflip', 'videoscale', 'videorate', 'x264enc', 'h264parse', 'mp4mux', 'audioconvert', 'audioresample', 'aacparse', 'fakesink' ) as $element ) {
				if ( is_wp_error( $this->run( array( $inspect, $element ), 15 ) ) ) {
					$available = false;
					break;
				}
			}
			if ( $available ) {
				foreach ( array( 'avenc_aac', 'voaacenc', 'fdkaacenc' ) as $aac ) {
					if ( ! is_wp_error( $this->run( array( $inspect, $aac ), 15 ) ) && ! is_wp_error( $this->run( array( $discover, '--help' ), 15 ) ) ) {
						$this->backend = array( 'name' => 'GStreamer', 'encode' => $launch, 'probe' => $discover, 'aac' => $aac );
						return $this->backend;
					}
				}
			}
		}
		$this->backend = new WP_Error( 'no_video_encoder', __( 'Kein nutzbarer lokaler Videoencoder verfügbar. Benötigt werden FFmpeg und ffprobe mit libx264/AAC oder GStreamer mit gst-launch, gst-inspect, gst-discoverer, x264enc, AAC-Encoder und MP4-Muxer. PHP muss lokale Prozesse erlauben. Bitte den Hoster um Einrichtung bitten. Videos bleiben unverändert.', 'ps-medienoptimierung' ) );
		return $this->backend;
	}

	private function uri( $path ) {
		return 'file://' . str_replace( '%2F', '/', rawurlencode( str_replace( '\\', '/', $path ) ) );
	}

	protected function probe( $file, array $backend ) {
		if ( 'FFmpeg' === $backend['name'] ) {
			$output = $this->run( array( $backend['probe'], '-v', 'error', '-show_streams', '-show_format', '-of', 'json', $file ), 30 );
			if ( is_wp_error( $output ) ) {
				return $output;
			}
			$data = json_decode( $output, true );
			$video = null;
			$audio = null;
			foreach ( isset( $data['streams'] ) ? $data['streams'] : array() as $stream ) {
				if ( 'video' === $stream['codec_type'] && empty( $stream['disposition']['attached_pic'] ) && null === $video ) {
					$video = $stream;
				} elseif ( 'audio' === $stream['codec_type'] && null === $audio ) {
					$audio = $stream;
				}
			}
			if ( ! $video || empty( $data['format']['duration'] ) ) {
				return new WP_Error( 'video_probe', __( 'Videospur oder Laufzeit konnte nicht zuverlässig gelesen werden.', 'ps-medienoptimierung' ) );
			}
			$rotated = false;
			foreach ( isset( $video['side_data_list'] ) ? $video['side_data_list'] : array() as $side_data ) {
				if ( isset( $side_data['rotation'] ) ) {
					$rotated = 90 === abs( (int) round( $side_data['rotation'] ) ) % 180;
				}
			}
			$fps = explode( '/', isset( $video['avg_frame_rate'] ) ? $video['avg_frame_rate'] : '0/1' );
			$aspect = explode( ':', isset( $video['sample_aspect_ratio'] ) ? $video['sample_aspect_ratio'] : '1:1' );
			return array(
				'width' => (int) $video['width'], 'height' => (int) $video['height'],
				'rotated' => $rotated,
				'pixel_aspect' => count( $aspect ) === 2 && (float) $aspect[0] > 0 && (float) $aspect[1] > 0 ? (float) $aspect[0] / (float) $aspect[1] : 1,
				'duration' => (float) $data['format']['duration'],
				'mp4' => isset( $data['format']['tags']['major_brand'] ) && (bool) preg_match( '/^(?:iso[m2-9]|mp4[12]|avc1|M4V|MSNV)$/', trim( $data['format']['tags']['major_brand'] ) ),
				'h264' => isset( $video['codec_name'] ) && 'h264' === $video['codec_name'],
				'yuv420' => isset( $video['pix_fmt'] ) && 'yuv420p' === $video['pix_fmt'],
				'mobile_profile' => isset( $video['profile'], $video['level'] ) && in_array( $video['profile'], array( 'Baseline', 'Constrained Baseline' ), true ) && $video['level'] > 0,
				'fps' => count( $fps ) === 2 && (float) $fps[1] > 0 ? (float) $fps[0] / (float) $fps[1] : 0,
				'audio' => null !== $audio,
				'audio_rate' => $audio && isset( $audio['sample_rate'] ) ? (int) $audio['sample_rate'] : 0,
				'aac' => $audio && isset( $audio['codec_name'], $audio['profile'], $audio['channels'], $audio['sample_rate'] ) && 'aac' === $audio['codec_name'] && 'LC' === $audio['profile'] && $audio['channels'] >= 1 && $audio['channels'] <= 2 && (int) $audio['sample_rate'] > 0 && (int) $audio['sample_rate'] <= 48000,
			);
		}
		$output = $this->run( array( $backend['probe'], '-v', '-t', '25', $this->uri( $file ) ), 30 );
		if ( is_wp_error( $output ) ) {
			return $output;
		}
		if ( preg_match( '/^\s*(Missing plugins|An error|Error|Could not|No URI|This file contains no)/mi', $output ) ||
			! preg_match( '/Duration:\s*(\d+):(\d+):([\d.]+)/', $output, $duration ) ||
			! preg_match( '/Width:\s*(\d+)/', $output, $width ) || ! preg_match( '/Height:\s*(\d+)/', $output, $height ) ) {
			return new WP_Error( 'video_probe', __( 'GStreamer konnte das Video nicht vollständig untersuchen. Möglicherweise fehlt ein Decoder.', 'ps-medienoptimierung' ) );
		}
		preg_match( '/Frame rate:\s*(\d+)\/(\d+)/', $output, $fps );
		preg_match( '/Pixel aspect ratio:\s*(\d+)\/(\d+)/', $output, $aspect );
		preg_match( '/video #\d+:[^\r\n]+/', $output, $video_caps );
		$caps = isset( $video_caps[0] ) ? $video_caps[0] : '';
		preg_match( '/level=\(string\)([\d.]+)/', $caps, $level );
		preg_match( '/audio #\d+:[^\r\n]+/', $output, $audio_caps );
		$audio = isset( $audio_caps[0] ) ? $audio_caps[0] : '';
		preg_match( '/rate=\(int\)(\d+)/', $audio, $rate );
		return array(
			'width' => (int) $width[1], 'height' => (int) $height[1],
			'rotated' => (bool) preg_match( '/image orientation:\s*(?:flip-)?rotate-(90|270)\b/', $output ),
			'pixel_aspect' => isset( $aspect[2] ) && (int) $aspect[1] > 0 && (int) $aspect[2] > 0 ? (int) $aspect[1] / (int) $aspect[2] : 1,
			'duration' => $duration[1] * 3600 + $duration[2] * 60 + (float) $duration[3],
			'mp4' => false !== strpos( $output, 'video/quicktime, variant=(string)iso' ),
			'h264' => (bool) preg_match( '/video #\d+: video\/x-h264/', $output ),
			'yuv420' => false !== strpos( $output, 'chroma-format=(string)4:2:0' ) && false !== strpos( $output, 'bit-depth-luma=(uint)8' ) && false !== strpos( $output, 'bit-depth-chroma=(uint)8' ),
			'mobile_profile' => (bool) preg_match( '/profile=\(string\)(constrained-baseline|baseline)(?:,|\s|$)/', $caps ) && isset( $level[1] ) && (float) $level[1] > 0,
			'fps' => isset( $fps[2] ) && (int) $fps[2] > 0 ? (int) $fps[1] / (int) $fps[2] : 0,
			'audio' => (bool) preg_match( '/audio #\d+:/', $output ),
			'audio_rate' => isset( $rate[1] ) ? (int) $rate[1] : 0,
			'aac' => false !== strpos( $audio, 'audio/mpeg' ) && false !== strpos( $audio, 'mpegversion=(int)4' ) && false !== strpos( $audio, 'profile=(string)lc' ) &&
				isset( $rate[1] ) && (int) $rate[1] > 0 && (int) $rate[1] <= 48000 && (bool) preg_match( '/channels=\(int\)[12](?:,|\s|$)/', $audio ),
		);
	}

	protected function encode( $source, $target, array $info, array $backend, $quality ) {
		$display_width = $info['width'] * $info['pixel_aspect'];
		$source_width = $info['rotated'] ? $info['height'] : $display_width;
		$source_height = $info['rotated'] ? $display_width : $info['height'];
		$settings = $this->settings();
		$ratio = empty( $settings['resize'] ) ? 1 : min( 1, $settings['width'] / $source_width, $settings['height'] / $source_height );
		$width = max( 2, (int) floor( $source_width * $ratio / 2 ) * 2 );
		$height = max( 2, (int) floor( $source_height * $ratio / 2 ) * 2 );
		if ( 'FFmpeg' === $backend['name'] ) {
			$args = array( $backend['encode'], '-nostdin', '-hide_banner', '-loglevel', 'error', '-xerror', '-y', '-i', $source, '-map', '0:V:0', '-map', '0:a:0?', '-map_metadata', '-1', '-sn', '-dn',
				'-vf', "scale=$width:$height,setsar=1,fps=30,format=yuv420p",
				'-c:v', 'libx264', '-threads', '2', '-preset', 'medium', '-crf', (string) $quality, '-profile:v', 'baseline',
				'-c:a', 'aac', '-profile:a', 'aac_low', '-b:a', '128k', '-ac', '2', '-ar', '48000', '-movflags', '+faststart', '-f', 'mp4', $target );
		} else {
			$args = array( $backend['encode'], '-q', '-e', 'uridecodebin', 'uri=' . $this->uri( $source ), 'name=source',
				'mp4mux', 'name=mux', 'faststart=true', '!', 'filesink', 'location=' . $this->gst_string( $target ),
				'source.', '!', 'queue', '!', 'video/x-raw', '!', 'videoflip', 'method=automatic', '!', 'videoconvert', '!', 'videoscale', '!', 'videorate',
				'!', "video/x-raw,format=I420,width=$width,height=$height,pixel-aspect-ratio=1/1,framerate=30/1", '!', 'x264enc',
				'threads=2', 'speed-preset=medium', 'pass=qual', 'quantizer=' . $quality, '!', 'video/x-h264,profile=constrained-baseline',
				'!', 'h264parse', '!', 'queue', '!', 'mux.' );
			if ( $info['audio'] ) {
				$args = array_merge( $args, array( 'source.', '!', 'queue', '!', 'audio/x-raw', '!', 'audioconvert', '!', 'audioresample',
					'!', 'audio/x-raw,channels=2,rate=48000', '!', $backend['aac'], 'bitrate=128000', '!', 'aacparse', '!', 'queue', '!', 'mux.' ) );
			}
		}
		return $this->run( $args );
	}

	private function gst_string( $value ) {
		// GStreamer parses property values itself, even without a shell.
		return '"' . str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), $value ) . '"';
	}

	protected function validate( $file, array $source, array $backend ) {
		$info = $this->probe( $file, $backend );
		if ( is_wp_error( $info ) ) {
			return $info;
		}
		if ( ! $this->compatible( $info ) || ! $info['mobile_profile'] || $info['fps'] <= 0 || $info['fps'] > 30.01 || $this->needs_resize( $info ) ||
			$info['duration'] <= 0 || abs( $source['duration'] - $info['duration'] ) > max( 0.15, min( 0.5, $source['duration'] * 0.001 ) ) ||
			( $source['audio'] && ( ! $info['audio'] || ! $info['aac'] || 48000 !== $info['audio_rate'] ) ) ) {
			return new WP_Error( 'video_validation', __( 'Die Ausgabe erfüllt die Prüfung auf MP4, H.264, 8-Bit-4:2:0, Mobilauflösung, Laufzeit oder vorhandenen AAC-Ton nicht. Das Original bleibt erhalten.', 'ps-medienoptimierung' ) );
		}
		if ( 'FFmpeg' === $backend['name'] ) {
			$args = array( $backend['encode'], '-nostdin', '-v', 'error', '-xerror', '-i', $file, '-map', '0:v:0', '-map', '0:a:0?', '-f', 'null', '-' );
		} else {
			$args = array( $backend['encode'], '-q', 'uridecodebin', 'uri=' . $this->uri( $file ), 'name=check',
				'check.', '!', 'queue', '!', 'video/x-raw', '!', 'fakesink', 'sync=false' );
			if ( $source['audio'] ) {
				$args = array_merge( $args, array( 'check.', '!', 'queue', '!', 'audio/x-raw', '!', 'fakesink', 'sync=false' ) );
			}
		}
		$result = $this->run( $args );
		return is_wp_error( $result ) ? $result : $info;
	}

	private function local_file( $id ) {
		$uploads = wp_upload_dir();
		$file = get_attached_file( $id, true );
		$root = realpath( $uploads['basedir'] );
		$path = $file ? realpath( $file ) : false;
		if ( ! $root || ! $path || is_link( $file ) || 0 !== strpos( wp_normalize_path( $path ), trailingslashit( wp_normalize_path( $root ) ) ) || ! is_file( $path ) ) {
			return new WP_Error( 'video_not_local', __( 'Nur vorhandene lokale Videos im Upload-Verzeichnis können verarbeitet werden. Ausgelagerte Medien bleiben unverändert.', 'ps-medienoptimierung' ) );
		}
		return $path;
	}

	public function optimize( $id ) {
		$result = $this->optimize_file( $id );
		if ( is_wp_error( $result ) && 'video_busy' !== $result->get_error_code() ) {
			$this->record_error( $id, $result );
		}
		return $result;
	}

	private function record_error( $id, WP_Error $error ) {
		$previous = get_post_meta( $id, self::META, true );
		$status = in_array( $error->get_error_code(), array( 'video_extension', 'video_offloaded' ), true ) ? 'unsupported' : 'error';
		$this->save_status( $id, array_merge( is_array( $previous ) ? $previous : array(), array( 'status' => $status, 'message' => $error->get_error_message(), 'time' => time() ) ) );
	}

	private function save_status( $id, array $status ) {
		if ( false === update_post_meta( $id, self::META, $status ) && get_post_meta( $id, self::META, true ) !== $status ) {
			error_log( sprintf( 'Medienoptimierung: Videostatus für Anhang %d konnte nicht gespeichert werden.', $id ) );
			return false;
		}
		$time = sprintf( '%.6F', microtime( true ) );
		if ( false === update_post_meta( $id, self::LOG_TIME, $time ) && get_post_meta( $id, self::LOG_TIME, true ) !== $time ) {
			error_log( sprintf( 'Medienoptimierung: Video-Protokollzeit für Anhang %d konnte nicht gespeichert werden.', $id ) );
		}
		return true;
	}

	private function source_file( $id ) {
		if ( 'attachment' !== get_post_type( $id ) || 0 !== strpos( (string) get_post_mime_type( $id ), 'video/' ) ) {
			return new WP_Error( 'not_video', __( 'Dieser Anhang ist kein Video.', 'ps-medienoptimierung' ) );
		}
		global $as3cf;
		if ( is_object( $as3cf ) && method_exists( $as3cf, 'is_attachment_served_by_s3' ) && $as3cf->is_attachment_served_by_s3( $id ) ) {
			return new WP_Error( 'video_offloaded', __( 'Dieses Video wird von S3 ausgeliefert. Eine lokale Kopie wird nicht ersetzt, da die veröffentlichte Videodatei dadurch nicht aktualisiert würde.', 'ps-medienoptimierung' ) );
		}
		$file = $this->local_file( $id );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		if ( 'mp4' !== strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) ) {
			return new WP_Error( 'video_extension', __( 'Unverändert: Für die sichere Beibehaltung bestehender URLs werden nur Dateien mit .mp4-Endung ersetzt. Andere Endungen benötigen serverseitige Weiterleitungen auf eine neue MP4-Datei.', 'ps-medienoptimierung' ) );
		}
		return $file;
	}

	public function check( $id ) {
		$lock = $this->processing_lock();
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}
		try {
			return $this->check_file( $id );
		} finally {
			flock( $lock, LOCK_UN );
			fclose( $lock );
		}
	}

	private function check_file( $id ) {
		$file = $this->source_file( $id );
		$backend = $this->backend();
		$info = is_wp_error( $file ) ? $file : ( is_wp_error( $backend ) ? $backend : $this->probe( $file, $backend ) );
		if ( is_wp_error( $info ) ) {
			$this->record_error( $id, $info );
			return $info;
		}
		$hash = hash_file( 'sha256', $file );
		if ( ! $hash || $info['width'] < 2 || $info['height'] < 2 || $info['duration'] <= 0 ) {
			$error = new WP_Error( 'video_check', __( 'Das Video konnte nicht zuverlässig geprüft werden.', 'ps-medienoptimierung' ) );
			$this->record_error( $id, $error );
			return $error;
		}
		clearstatcache( true, $file );
		$previous = get_post_meta( $id, self::META, true );
		$previous = isset( $previous['hash'] ) && $hash === $previous['hash'] ? $previous : array();
		$settings = $this->settings();
		$processed = ! empty( $previous['processed'] ) && isset( $previous['config'] ) && $previous['config'] === $this->config_key();
		$compatible = $this->compatible( $info );
		$pending = ! $compatible || $this->needs_resize( $info ) || ( ! empty( $settings['compress'] ) && ! $processed );
		$result = array_merge( $previous, array(
			'status' => $pending ? 'pending' : 'compatible', 'compatible' => $compatible, 'processed' => ! $pending,
			'hash' => $hash, 'config' => $this->config_key(), 'info' => $info, 'size' => filesize( $file ), 'mtime' => filemtime( $file ), 'time' => time(),
			'before' => filesize( $file ), 'after' => filesize( $file ), 'saved' => 0,
			'message' => $compatible ? ( $pending ? __( 'Codec bereits handykompatibel; Dateigröße oder Auflösung kann noch optimiert werden.', 'ps-medienoptimierung' ) : __( 'Bereits handykompatibel. Keine weitere Optimierung erforderlich.', 'ps-medienoptimierung' ) ) : __( 'Codec-Anpassung für Mobilgeräte erforderlich.', 'ps-medienoptimierung' ),
		) );
		if ( ! $this->save_status( $id, $result ) ) {
			return new WP_Error( 'video_check_save', __( 'Das Prüfergebnis konnte nicht gespeichert werden.', 'ps-medienoptimierung' ) );
		}
		return $result;
	}

	private function state( array $record, $file ) {
		if ( isset( $record['status'] ) && 'unsupported' === $record['status'] ) {
			return array( 'fresh' => false, 'correct' => false, 'pending' => false );
		}
		$fresh = $file && ! wp_is_stream( $file ) && is_file( $file ) && isset( $record['size'], $record['mtime'], $record['info'] ) && is_array( $record['info'] ) &&
			(int) $record['size'] === filesize( $file ) && (int) $record['mtime'] === filemtime( $file );
		$correct = $fresh && ! empty( $record['compatible'] );
		$settings = $this->settings();
		$processed = $fresh && ! empty( $record['processed'] ) && isset( $record['config'] ) && $record['config'] === $this->config_key();
		$pending = ! $fresh || ! $correct || $this->needs_resize( $record['info'] ) || ( ! empty( $settings['compress'] ) && ! $processed ) || ( isset( $record['status'] ) && 'error' === $record['status'] );
		return array( 'fresh' => $fresh, 'correct' => $correct, 'pending' => $pending );
	}

	public function needs_optimization( $id ) {
		$record = get_post_meta( $id, self::META, true );
		$state = $this->state( is_array( $record ) ? $record : array(), get_attached_file( $id, true ) );
		return $state['pending'];
	}

	public function statistics() {
		global $wpdb;
		$stats = array( 'total' => 0, 'correct' => 0, 'pending' => 0, 'unchecked' => 0, 'optimized' => 0, 'resized' => 0, 'errors' => 0, 'unsupported' => 0, 'before' => 0, 'after' => 0, 'resize_saved' => 0 );
		$cursor = 0;
		$uploads = wp_upload_dir();
		do {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT p.ID, v.meta_value AS result, f.meta_value AS file FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} v ON p.ID = v.post_id AND v.meta_key = %s
				LEFT JOIN {$wpdb->postmeta} f ON p.ID = f.post_id AND f.meta_key = '_wp_attached_file'
				WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND p.post_mime_type LIKE 'video/%%' AND p.ID > %d ORDER BY p.ID ASC LIMIT 500",
				self::META, $cursor
			), ARRAY_A );
			if ( $wpdb->last_error ) {
				return new WP_Error( 'video_stats', __( 'Die Videostatistik konnte nicht gelesen werden.', 'ps-medienoptimierung' ) );
			}
			foreach ( $rows as $row ) {
				$cursor = (int) $row['ID'];
				$record = maybe_unserialize( $row['result'] );
				$record = is_array( $record ) ? $record : array();
				$file = $row['file'] ? ( path_is_absolute( $row['file'] ) || wp_is_stream( $row['file'] ) ? $row['file'] : trailingslashit( $uploads['basedir'] ) . $row['file'] ) : '';
				$state = $this->state( $record, $file );
				$stats['total']++;
				$stats['correct'] += (int) $state['correct'];
				$stats['pending'] += (int) $state['pending'];
				$stats['unchecked'] += (int) ( ! $state['fresh'] && ( ! isset( $record['status'] ) || 'unsupported' !== $record['status'] ) );
				$stats['errors'] += (int) ( isset( $record['status'] ) && 'error' === $record['status'] );
				$stats['unsupported'] += (int) ( isset( $record['status'] ) && 'unsupported' === $record['status'] );
				if ( $state['fresh'] && ! empty( $record['optimized'] ) && isset( $record['initial_bytes'] ) ) {
					$stats['optimized']++;
					$stats['before'] += $record['initial_bytes'];
					$stats['after'] += $record['size'];
					if ( ! empty( $record['resized'] ) ) {
						$stats['resized']++;
						$stats['resize_saved'] += $record['initial_bytes'] - $record['size'];
					}
				}
			}
		} while ( count( $rows ) === 500 );
		$stats['saved'] = $stats['before'] - $stats['after'];
		$stats['percent'] = $stats['before'] > 0 ? round( 100 * $stats['saved'] / $stats['before'], 1 ) : 0;
		$stats['before_human'] = size_format( $stats['before'], 2 );
		$stats['after_human'] = size_format( $stats['after'], 2 );
		$stats['saved_human'] = ( $stats['saved'] < 0 ? '+' : '' ) . size_format( abs( $stats['saved'] ), 2 );
		$stats['resize_human'] = ( $stats['resize_saved'] < 0 ? '+' : '' ) . size_format( abs( $stats['resize_saved'] ), 2 );
		return $stats;
	}

	private function keep_original( $id, $file, array $source, array $previous, $message ) {
		$result = array_merge( $previous, array(
			'status' => 'compatible', 'compatible' => true, 'processed' => true, 'config' => $this->config_key(),
			'info' => $source, 'size' => filesize( $file ), 'mtime' => filemtime( $file ), 'hash' => hash_file( 'sha256', $file ),
			'before' => filesize( $file ), 'after' => filesize( $file ), 'saved' => 0, 'time' => time(), 'message' => $message,
		) );
		return $this->save_status( $id, $result ) ? $result : new WP_Error( 'video_status_save', __( 'Das Video blieb unverändert, aber sein Prüfergebnis konnte nicht gespeichert werden.', 'ps-medienoptimierung' ) );
	}

	private function processing_lock() {
		$uploads = wp_upload_dir();
		$lock = fopen( trailingslashit( $uploads['basedir'] ) . '.ps-video.lock', 'c' );
		if ( ! $lock ) {
			return new WP_Error( 'video_lock', __( 'Die Verarbeitungssperre konnte nicht erstellt werden.', 'ps-medienoptimierung' ) );
		}
		if ( ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
			fclose( $lock );
			return new WP_Error( 'video_busy', __( 'Eine Videooptimierung läuft bereits. Bitte später erneut versuchen.', 'ps-medienoptimierung' ) );
		}
		return $lock;
	}

	private function optimize_file( $id ) {
		$file = $this->source_file( $id );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		$backend = $this->backend();
		if ( is_wp_error( $backend ) ) {
			return $backend;
		}
		if ( ! is_writable( $file ) || ! is_writable( dirname( $file ) ) ) {
			return new WP_Error( 'video_readonly', __( 'Videodatei oder Upload-Verzeichnis ist nicht beschreibbar.', 'ps-medienoptimierung' ) );
		}
		$lock = $this->processing_lock();
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}
		$temp = false;
		try {
			if ( function_exists( 'set_time_limit' ) ) {
				set_time_limit( 1900 );
			}
			$settings = $this->settings();
			$quality = max( 18, min( 30, (int) $settings['quality'] ) );
			$hash = hash_file( 'sha256', $file );
			$previous = get_post_meta( $id, self::META, true );
			if ( ! $hash ) {
				return new WP_Error( 'video_read', __( 'Das Original konnte nicht vollständig gelesen werden.', 'ps-medienoptimierung' ) );
			}
			if ( isset( $previous['hash'], $previous['config'], $previous['status'] ) && ! empty( $previous['processed'] ) && 'error' !== $previous['status'] && $hash === $previous['hash'] && $this->config_key() === $previous['config'] ) {
				$previous['message'] = __( 'Bereits mit diesen Videoeinstellungen optimiert.', 'ps-medienoptimierung' );
				return $previous;
			}
			$previous = isset( $previous['hash'] ) && $hash === $previous['hash'] ? $previous : array();
			$source = $this->probe( $file, $backend );
			if ( is_wp_error( $source ) ) {
				return $source;
			}
			if ( $source['width'] < 2 || $source['height'] < 2 || $source['duration'] <= 0 ) {
				return new WP_Error( 'video_dimensions', __( 'Ungültige Videomaße oder Laufzeit.', 'ps-medienoptimierung' ) );
			}
			$before = filesize( $file );
			$compatible = $this->compatible( $source );
			$resize = $this->needs_resize( $source );
			if ( $compatible && ! $resize && empty( $settings['compress'] ) ) {
				return $this->keep_original( $id, $file, $source, $previous, __( 'Bereits handykompatibel. Komprimierung und Skalierung sind nicht erforderlich; Original unverändert.', 'ps-medienoptimierung' ) );
			}
			$free = disk_free_space( dirname( $file ) );
			if ( false !== $free && $free < $before * ( empty( $settings['backup'] ) ? 2 : 3 ) ) {
				return new WP_Error( 'video_space', __( 'Nicht genügend freier Speicher für eine sichere temporäre Konvertierung.', 'ps-medienoptimierung' ) );
			}
			$temp = tempnam( dirname( $file ), '.ps-video-' );
			if ( ! $temp || dirname( $temp ) !== dirname( $file ) ) {
				return new WP_Error( 'video_temp', __( 'Temporäre Videodatei konnte nicht im Originalverzeichnis angelegt werden.', 'ps-medienoptimierung' ) );
			}
			register_shutdown_function( static function () use ( $temp ) {
				if ( is_file( $temp ) ) {
					unlink( $temp );
				}
			} );
			$encoded = $this->encode( $file, $temp, $source, $backend, $quality );
			if ( is_wp_error( $encoded ) ) {
				return $encoded;
			}
			clearstatcache( true, $temp );
			if ( ! filesize( $temp ) ) {
				return new WP_Error( 'video_empty', __( 'Der Encoder hat keine Videodaten ausgegeben. Das Original bleibt erhalten.', 'ps-medienoptimierung' ) );
			}
			$verified = $this->validate( $temp, $source, $backend );
			if ( is_wp_error( $verified ) ) {
				return $verified;
			}
			require_once ABSPATH . 'wp-admin/includes/media.php';
			$metadata = wp_read_video_metadata( $temp );
			if ( ! is_array( $metadata ) || empty( $metadata['width'] ) || empty( $metadata['height'] ) ) {
				return new WP_Error( 'video_metadata', __( 'WordPress konnte die geprüfte Ausgabe nicht als Video lesen. Das Original bleibt erhalten.', 'ps-medienoptimierung' ) );
			}
			$after = filesize( $temp );
			$metadata['filesize'] = $after;
			$old_metadata = wp_get_attachment_metadata( $id );
			$metadata = array_merge( is_array( $old_metadata ) ? $old_metadata : array(), $metadata );
			if ( $hash !== hash_file( 'sha256', $file ) ) {
				return new WP_Error( 'video_changed', __( 'Das Original wurde zwischenzeitlich verändert. Es wird nicht ersetzt.', 'ps-medienoptimierung' ) );
			}
			if ( $compatible && ! $resize && $after >= $before ) {
				return $this->keep_original( $id, $file, $source, $previous, __( 'Bereits handykompatibel. Die Komprimierung brachte keine kleinere Datei; das Original bleibt unverändert.', 'ps-medienoptimierung' ) );
			}
			$backup = '';
			if ( ! empty( $settings['backup'] ) ) {
				$backup = get_post_meta( $id, '_ps_smush_video_backup', true );
				if ( ! $backup ) {
					$backup = $file . '.ps-original';
					if ( file_exists( $backup ) ) {
						return new WP_Error( 'video_backup_exists', __( 'Am Sicherungspfad liegt bereits eine Datei. Sie wird nicht überschrieben; bitte zuerst die Originalsicherung prüfen.', 'ps-medienoptimierung' ) );
					}
					if ( ! copy( $file, $backup ) || $hash !== hash_file( 'sha256', $backup ) ) {
						if ( is_file( $backup ) ) {
							wp_delete_file( $backup );
						}
						return new WP_Error( 'video_backup', __( 'Die Originalsicherung konnte nicht sicher angelegt werden. Das Original wird nicht ersetzt.', 'ps-medienoptimierung' ) );
					}
					if ( ! update_post_meta( $id, '_ps_smush_video_backup', $backup ) ) {
						wp_delete_file( $backup );
						return new WP_Error( 'video_backup_meta', __( 'Die Originalsicherung konnte nicht registriert werden. Das Original wird nicht ersetzt.', 'ps-medienoptimierung' ) );
					}
				} elseif ( ! is_file( $backup ) ) {
					return new WP_Error( 'video_backup_missing', __( 'Die gespeicherte Originalsicherung fehlt. Bitte zuerst die Sicherung prüfen.', 'ps-medienoptimierung' ) );
				}
			}
			if ( ! chmod( $temp, fileperms( $file ) & 0777 ) || ! rename( $temp, $file ) ) {
				return new WP_Error( 'video_replace', __( 'Die geprüfte Ausgabe konnte nicht atomar eingesetzt werden. Das Original bleibt erhalten.', 'ps-medienoptimierung' ) );
			}
			$temp = false;
			clearstatcache( true, $file );
			$saved = wp_update_attachment_metadata( $id, $metadata );
			if ( ! $saved && get_post_meta( $id, '_wp_attachment_metadata', true ) !== $metadata ) {
				return new WP_Error( 'video_metadata_save', __( 'Das Video wurde erfolgreich konvertiert, aber seine WordPress-Metadaten konnten nicht gespeichert werden.', 'ps-medienoptimierung' ) );
			}
			$result = array(
				'status' => 'success', 'before' => $before, 'after' => $after, 'saved' => $before - $after,
				'initial_bytes' => isset( $previous['initial_bytes'] ) ? $previous['initial_bytes'] : $before,
				'optimized' => true, 'compatible' => true, 'processed' => true, 'resized' => $resize || ! empty( $previous['resized'] ),
				'config' => $this->config_key(), 'info' => $verified, 'size' => $after, 'mtime' => filemtime( $file ),
				'dimensions_before' => $source['width'] . ' × ' . $source['height'], 'dimensions_after' => $verified['width'] . ' × ' . $verified['height'],
				'quality' => $quality, 'hash' => hash_file( 'sha256', $file ), 'time' => time(), 'backend' => $backend['name'],
				'message' => __( 'Geprüft: MP4, H.264, 8-Bit-4:2:0 und bei vorhandenem Ton AAC. URL unverändert.', 'ps-medienoptimierung' ),
			);
			if ( ! $this->save_status( $id, $result ) ) {
				return new WP_Error( 'video_status_save', __( 'Das Video wurde erfolgreich konvertiert, aber das Ergebnis konnte nicht gespeichert werden. Bitte die Datenbankverbindung prüfen.', 'ps-medienoptimierung' ) );
			}
			clean_post_cache( $id );
			return $result;
		} finally {
			if ( $temp && is_file( $temp ) ) {
				unlink( $temp );
			}
			flock( $lock, LOCK_UN );
			fclose( $lock );
		}
	}

	public function queue_upload( $id ) {
		$settings = $this->settings();
		if ( empty( $settings['auto'] ) || 0 !== strpos( (string) get_post_mime_type( $id ), 'video/' ) || ! $this->needs_optimization( $id ) ) {
			return;
		}
		$scheduled = wp_schedule_single_event( time() + 10, self::CRON, array( (int) $id ), true );
		$failed = is_wp_error( $scheduled ) || ! $scheduled;
		$previous = get_post_meta( $id, self::META, true );
		$this->save_status( $id, array_merge( is_array( $previous ) ? $previous : array(), array(
			'status' => $failed ? 'error' : 'queued',
			'message' => $failed ? ( is_wp_error( $scheduled ) ? $scheduled->get_error_message() : __( 'Der Videoauftrag konnte nicht für WP-Cron gespeichert werden.', 'ps-medienoptimierung' ) ) : __( 'Videooptimierung wartet auf WP-Cron.', 'ps-medienoptimierung' ),
			'time' => time(),
		) ) );
	}

	public function process_upload( $id ) {
		$settings = $this->settings();
		if ( empty( $settings['auto'] ) ) {
			$previous = get_post_meta( $id, self::META, true );
			$this->save_status( $id, array_merge( is_array( $previous ) ? $previous : array(), array( 'status' => 'paused', 'message' => __( 'Automatische Videooptimierung wurde deaktiviert.', 'ps-medienoptimierung' ) ) ) );
			return;
		}
		$result = $this->optimize( $id );
		if ( is_wp_error( $result ) && 'video_busy' === $result->get_error_code() ) {
			$this->queue_upload( $id );
		}
	}

	public function delete_attachment( $id ) {
		wp_clear_scheduled_hook( self::CRON, array( (int) $id ) );
		$file = $this->local_file( $id );
		$backup = get_post_meta( $id, '_ps_smush_video_backup', true );
		if ( ! is_wp_error( $file ) && $backup === $file . '.ps-original' && is_file( $backup ) ) {
			wp_delete_file( $backup );
		}
	}
}
