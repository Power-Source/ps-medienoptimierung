<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PS_Smush_Video_Admin {
	const LOG_CLEARED = 'ps-smush-video-log-cleared';

	private $video;

	public function __construct( PS_Smush_Video $video ) {
		$this->video = $video;
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'ps_smush_before_save_settings', array( $this, 'save' ) );
		add_action( 'wp_ajax_ps_smush_video_list', array( $this, 'listing' ) );
		add_action( 'wp_ajax_ps_smush_video_process', array( $this, 'process' ) );
		add_action( 'wp_ajax_ps_smush_video_stats', array( $this, 'statistics' ) );
		add_action( 'wp_ajax_ps_smush_video_clear_log', array( $this, 'clear_log' ) );
		add_filter( 'attachment_fields_to_edit', array( $this, 'attachment_status' ), 10, 2 );
		add_action( 'ps_smush_after_basic_settings', array( $this, 'settings_fields' ) );
		add_action( 'smush_settings_ui_bottom', array( $this, 'render' ) );
	}

	public function enqueue( $hook ) {
		if ( ! in_array( $hook, array( 'media_page_ps-smush-bulk', 'settings_page_ps-smush', 'settings_page_ps-smush-network' ), true ) ) {
			return;
		}
		wp_enqueue_script( 'ps-smush-video', PS_SMUSH_URL . 'assets/js/video.js', array( 'jquery' ), PS_SMUSH_VERSION, true );
		wp_localize_script( 'ps-smush-video', 'psSmushVideo', array(
			'url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'ps_smush_video' ),
			'working' => __( 'Video wird konvertiert und vollständig geprüft …', 'ps-medienoptimierung' ),
			'complete' => __( 'Sammelverarbeitung abgeschlossen.', 'ps-medienoptimierung' ),
			'stopped' => __( 'Angehalten. Das laufende Video wurde noch fertig verarbeitet.', 'ps-medienoptimierung' ),
			'networkError' => __( 'Serverantwort fehlt oder ist ungültig. Die Verarbeitung wurde angehalten. Bitte vor einem Neustart den Videostatus prüfen; der Server könnte noch arbeiten.', 'ps-medienoptimierung' ),
			'progress' => __( 'Verarbeitet: %1$d · Erfolgreich: %2$d · Fehler: %3$d', 'ps-medienoptimierung' ),
			'larger' => __( 'größer', 'ps-medienoptimierung' ),
			'smaller' => __( 'kleiner', 'ps-medienoptimierung' ),
			'unchanged' => __( 'unverändert', 'ps-medienoptimierung' ),
			'checking' => __( 'Video-Codecs und Optimierungsbedarf werden geprüft …', 'ps-medienoptimierung' ),
			'checked' => __( 'Videoprüfung abgeschlossen.', 'ps-medienoptimierung' ),
			'pending' => __( 'Noch zu optimierende Videos: %d', 'ps-medienoptimierung' ),
			'done' => __( 'Alle unterstützten Videos sind mit den aktuellen Einstellungen geprüft und optimiert.', 'ps-medienoptimierung' ),
			'statsError' => __( 'Die Videostatistik konnte nicht aktualisiert werden.', 'ps-medienoptimierung' ),
			'clearing' => __( 'Protokoll wird geleert …', 'ps-medienoptimierung' ),
			'clearError' => __( 'Das Videoprotokoll konnte nicht geleert werden.', 'ps-medienoptimierung' ),
		) );
	}

	public function save() {
		if ( ! isset( $_POST['ps-smush-video-settings-present'] ) ) {
			return;
		}
		check_ajax_referer( 'ps_smush_video_settings', 'ps_smush_video_nonce' );
		$network = is_multisite() && isset( $_POST['setting-type'] ) && 'network' === $_POST['setting-type'];
		if ( ! current_user_can( $network ? 'manage_network_options' : 'manage_options' ) ||
			( ! $network && is_multisite() && get_site_option( 'ps-smush-networkwide', false ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Die Videoeinstellungen dürfen nur in der zuständigen Medienoptimierung geändert werden. Bei zentraler Steuerung ist das die Netzwerkverwaltung.', 'ps-medienoptimierung' ) ), 403 );
		}
		$quality = isset( $_POST['ps-smush-video-quality'] ) && is_scalar( $_POST['ps-smush-video-quality'] ) ? (int) $_POST['ps-smush-video-quality'] : 0;
		if ( $quality < 18 || $quality > 30 ) {
			wp_send_json_error( array( 'message' => __( 'Die Videoqualität muss zwischen 18 und 30 liegen.', 'ps-medienoptimierung' ) ), 400 );
		}
		$settings = array( 'auto' => isset( $_POST['ps-smush-video-auto'] ) ? 1 : 0, 'backup' => isset( $_POST['ps-smush-video-backup'] ) ? 1 : 0, 'quality' => $quality );
		$settings['compress'] = isset( $_POST['ps-smush-video-compress'] ) ? 1 : 0;
		$settings['resize'] = isset( $_POST['ps-smush-video-resize'] ) ? 1 : 0;
		foreach ( array( 'width' => 1280, 'height' => 720 ) as $dimension => $default ) {
			$key = 'ps-smush-video-' . $dimension;
			$value = isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? (int) $_POST[ $key ] : ( $settings['resize'] ? 0 : $default );
			if ( $value < 16 || $value > 7680 || 0 !== $value % 2 ) {
				wp_send_json_error( array( 'message' => __( 'Videobreite und -höhe müssen gerade Pixelwerte zwischen 16 und 7680 sein.', 'ps-medienoptimierung' ) ), 400 );
			}
			$settings[ $dimension ] = $value;
		}
		$saved = $network ? update_site_option( PS_Smush_Video::SETTINGS, $settings ) : update_option( PS_Smush_Video::SETTINGS, $settings, false );
		$current = $network ? get_site_option( PS_Smush_Video::SETTINGS ) : get_option( PS_Smush_Video::SETTINGS );
		if ( ! $saved && $current !== $settings ) {
			wp_send_json_error( array( 'message' => __( 'Die Videoeinstellungen konnten nicht gespeichert werden.', 'ps-medienoptimierung' ) ), 500 );
		}
	}

	private function authorize() {
		check_ajax_referer( 'ps_smush_video', 'nonce' );
		if ( ! current_user_can( 'edit_others_posts' ) || ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung zur Videooptimierung.', 'ps-medienoptimierung' ) ), 403 );
		}
	}

	public function listing() {
		$this->authorize();
		global $wpdb;
		$cursor = isset( $_POST['cursor'] ) ? absint( $_POST['cursor'] ) : 0;
		$ceiling = isset( $_POST['ceiling'] ) ? absint( $_POST['ceiling'] ) : 0;
		if ( ! $ceiling ) {
			$ceiling = (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'video/%' AND post_status = 'inherit'" );
		}
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'video/%%' AND post_status = 'inherit' AND ID > %d AND ID <= %d ORDER BY ID ASC LIMIT 25",
			$cursor, $ceiling
		) );
		if ( $wpdb->last_error ) {
			wp_send_json_error( array( 'message' => __( 'Die Videoliste konnte nicht aus der Datenbank gelesen werden.', 'ps-medienoptimierung' ) ), 500 );
		}
		$items = array();
		foreach ( $ids as $id ) {
			$check = isset( $_POST['mode'] ) && 'check' === $_POST['mode'];
			if ( current_user_can( 'edit_post', (int) $id ) && ( $check || $this->video->needs_optimization( (int) $id ) ) ) {
				$items[] = array( 'id' => (int) $id, 'title' => get_the_title( $id ) );
			}
		}
		wp_send_json_success( array( 'items' => $items, 'cursor' => $ids ? (int) end( $ids ) : $cursor, 'ceiling' => $ceiling, 'done' => count( $ids ) < 25 ) );
	}

	public function process() {
		$this->authorize();
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id || ! current_user_can( 'edit_post', $id ) || 'attachment' !== get_post_type( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung für dieses Video.', 'ps-medienoptimierung' ) ), 403 );
		}
		$mode = isset( $_POST['mode'] ) ? $_POST['mode'] : 'optimize';
		if ( ! in_array( $mode, array( 'check', 'optimize' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unbekannter Videoauftrag.', 'ps-medienoptimierung' ) ), 400 );
		}
		$result = 'check' === $mode ? $this->video->check( $id ) : $this->video->optimize( $id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message(), 'code' => $result->get_error_code() ) );
		}
		$result['before_human'] = size_format( $result['before'], 2 );
		$result['after_human'] = size_format( $result['after'], 2 );
		$result['change_human'] = size_format( abs( $result['saved'] ), 2 );
		wp_send_json_success( $result );
	}

	public function statistics() {
		$this->authorize();
		$stats = $this->video->statistics();
		if ( is_wp_error( $stats ) ) {
			wp_send_json_error( array( 'message' => $stats->get_error_message() ), 500 );
		}
		wp_send_json_success( $stats );
	}

	public function clear_log() {
		$this->authorize();
		$time = sprintf( '%.6F', microtime( true ) );
		if ( ! update_option( self::LOG_CLEARED, $time, false ) && get_option( self::LOG_CLEARED ) !== $time ) {
			wp_send_json_error( array( 'message' => __( 'Das Videoprotokoll konnte nicht geleert werden.', 'ps-medienoptimierung' ) ), 500 );
		}
		wp_send_json_success( array( 'message' => __( 'Protokoll geleert. Optimierungsstände, Einsparungen und offene Fehler bleiben erhalten.', 'ps-medienoptimierung' ) ) );
	}

	private function recent_results() {
		$cleared = get_option( self::LOG_CLEARED, '0' );
		$args = array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'video', 'posts_per_page' => 20, 'order' => 'DESC' );
		$recent = get_posts( array_merge( $args, array(
			'meta_key' => PS_Smush_Video::LOG_TIME, 'orderby' => 'meta_value_num',
			'meta_query' => array( array( 'key' => PS_Smush_Video::LOG_TIME, 'value' => $cleared, 'compare' => '>', 'type' => 'DECIMAL(20,6)' ) ),
		) ) );
		if ( ! $cleared && count( $recent ) < 20 ) {
			$legacy = get_posts( array_merge( $args, array(
				'posts_per_page' => 20 - count( $recent ), 'meta_key' => PS_Smush_Video::META, 'orderby' => 'ID',
				'meta_query' => array( array( 'key' => PS_Smush_Video::LOG_TIME, 'compare' => 'NOT EXISTS' ) ),
			) ) );
			$recent = array_merge( $recent, $legacy );
		}
		return $recent;
	}

	public function attachment_status( $fields, $post ) {
		if ( 0 !== strpos( (string) $post->post_mime_type, 'video/' ) ) {
			return $fields;
		}
		$status = get_post_meta( $post->ID, PS_Smush_Video::META, true );
		$message = ! empty( $status['message'] ) ? $status['message'] : __( 'Noch nicht für Mobilgeräte optimiert.', 'ps-medienoptimierung' );
		if ( isset( $status['before'], $status['after'] ) ) {
			$message .= ' ' . size_format( $status['before'], 2 ) . ' → ' . size_format( $status['after'], 2 );
		}
		$fields['ps_video_status'] = array( 'label' => __( 'Videooptimierung', 'ps-medienoptimierung' ), 'input' => 'html', 'html' => esc_html( $message ) );
		return $fields;
	}

	public function settings_fields() {
		if ( ! current_user_can( is_network_admin() ? 'manage_network_options' : 'manage_options' ) ) {
			return;
		}
		$settings = $this->video->settings();
		$backend = $this->video->backend();
		$options = array(
			'auto' => array(
				'title' => __( 'Automatische Videooptimierung', 'ps-medienoptimierung' ),
				'label' => __( 'Videos beim Hochladen automatisch für Mobilgeräte konvertieren', 'ps-medienoptimierung' ),
				'description' => __( 'Standardmäßig deaktiviert. Die Konvertierung läuft nach dem Upload über WP-Cron. Ohne regelmäßige Seitenaufrufe bitte einen System-Cron einrichten. Fehler erscheinen in der Medienoptimierung und am Medium.', 'ps-medienoptimierung' ),
			),
			'compress' => array(
				'title' => __( 'Videokomprimierung', 'ps-medienoptimierung' ),
				'label' => __( 'Auch die Dateigröße bereits kompatibler Videos optimieren', 'ps-medienoptimierung' ),
				'description' => __( 'Kleinere Dateien sparen Speicher und Ladezeit. Ist ein Video bereits handykompatibel und wird nicht skaliert, bleibt das Original erhalten, falls die Komprimierung keine kleinere Datei ergibt.', 'ps-medienoptimierung' ),
			),
			'resize' => array(
				'title' => __( 'Videogröße begrenzen', 'ps-medienoptimierung' ),
				'label' => __( 'Große Videos proportional verkleinern', 'ps-medienoptimierung' ),
				'description' => __( 'Begrenzt die Auflösung ohne Hochskalieren oder Verzerren. Standardmäßig deaktiviert. Kleinere Abmessungen können die Dateigröße zusätzlich reduzieren.', 'ps-medienoptimierung' ),
			),
			'backup' => array(
				'title' => __( 'Originalvideos', 'ps-medienoptimierung' ),
				'label' => __( 'Kopie der Originalvideos erstellen', 'ps-medienoptimierung' ),
				'description' => __( 'Standardmäßig deaktiviert, um Speicherplatz zu sparen. Ohne Sicherung ist eine Wiederherstellung nach erfolgreichem Ersetzen nicht möglich. Vorhandene Sicherungen bleiben beim Abschalten erhalten.', 'ps-medienoptimierung' ),
			),
		);
		?>
		<div id="ps-smush-video-settings">
			<h3><?php esc_html_e( 'Videooptimierung', 'ps-medienoptimierung' ); ?></h3>
			<input type="hidden" name="ps-smush-video-settings-present" value="1">
			<?php wp_nonce_field( 'ps_smush_video_settings', 'ps_smush_video_nonce' ); ?>
			<p><?php esc_html_e( 'Lokale Codec-Anpassung für Mobilgeräte: MP4 mit H.264, 8 Bit, 4:2:0 und AAC-Ton. Dateigröße und maximale Auflösung lassen sich unabhängig davon optimieren.', 'ps-medienoptimierung' ); ?></p>
			<p class="ps-smush-notice"><?php echo esc_html( is_wp_error( $backend ) ? $backend->get_error_message() : sprintf( __( 'Verfügbarer lokaler Encoder: %s', 'ps-medienoptimierung' ), $backend['name'] ) ); ?></p>
			<?php foreach ( $options as $key => $option ) : ?>
				<div class="ps-smush-setting-row ps-smush-basic">
					<div class="column column-left"><span class="ps-smush-setting-label"><?php echo esc_html( $option['title'] ); ?></span></div>
					<div class="column column-right">
						<span class="toggle float-l">
							<input type="checkbox" class="toggle-checkbox" id="ps-smush-video-<?php echo esc_attr( $key ); ?>" name="ps-smush-video-<?php echo esc_attr( $key ); ?>" value="1" <?php checked( $settings[ $key ] ); ?>>
							<label class="toggle-label" for="ps-smush-video-<?php echo esc_attr( $key ); ?>" aria-hidden="true"></label>
						</span>
						<div class="column-right-content">
							<label for="ps-smush-video-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $option['label'] ); ?></label><br>
							<small class="smush-setting-description"><?php echo esc_html( $option['description'] ); ?></small>
						</div>
						<?php if ( 'resize' === $key ) : ?>
							<div class="column-right-content ps-video-dimensions">
								<label for="ps-video-width"><?php esc_html_e( 'Breite', 'ps-medienoptimierung' ); ?></label>
								<input type="number" id="ps-video-width" name="ps-smush-video-width" min="16" max="7680" step="2" value="<?php echo esc_attr( $settings['width'] ); ?>" class="small-text">
								<label for="ps-video-height"><?php esc_html_e( 'Höhe', 'ps-medienoptimierung' ); ?></label>
								<input type="number" id="ps-video-height" name="ps-smush-video-height" min="16" max="7680" step="2" value="<?php echo esc_attr( $settings['height'] ); ?>" class="small-text">
								<small class="smush-setting-description"><?php esc_html_e( 'Pixel. Gilt nur mit aktivierter Größenbegrenzung. Das Seitenverhältnis bleibt erhalten.', 'ps-medienoptimierung' ); ?></small>
							</div>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
			<div class="ps-smush-setting-row ps-smush-basic">
				<div class="column column-left"><label for="ps-video-quality" class="ps-smush-setting-label"><?php esc_html_e( 'Videoqualität', 'ps-medienoptimierung' ); ?></label></div>
				<div class="column column-right">
					<input type="number" id="ps-video-quality" name="ps-smush-video-quality" min="18" max="30" value="<?php echo esc_attr( $settings['quality'] ); ?>" class="small-text" required><br>
					<small class="smush-setting-description"><?php esc_html_e( 'Qualitätswert 18–30, Standard 23. Kleinere Werte bedeuten höhere Qualität und größere Dateien. Codec-Kompatibilität bleibt immer aktiviert.', 'ps-medienoptimierung' ); ?></small>
				</div>
			</div>
		</div>
		<?php
	}

	public function render() {
		if ( is_network_admin() || ! current_user_can( 'edit_others_posts' ) || ! current_user_can( 'upload_files' ) ) {
			return;
		}
		$backend = $this->video->backend();
		$stats = $this->video->statistics();
		if ( is_wp_error( $stats ) ) {
			echo '<p class="ps-smush-notice">' . esc_html( $stats->get_error_message() ) . '</p>';
			return;
		}
		$recent = $this->recent_results();
		?>
		<div class="row ps-video-overview" id="ps-smush-video-bulk" data-total="<?php echo esc_attr( $stats['total'] ); ?>" data-pending="<?php echo esc_attr( $stats['pending'] ); ?>" data-available="<?php echo is_wp_error( $backend ) ? '0' : '1'; ?>">
			<?php if ( is_multisite() && get_site_option( 'ps-smush-networkwide', false ) ) : ?>
				<p class="ps-smush-notice"><?php esc_html_e( 'Bild- und Videoeinstellungen werden zentral unter Medienoptimierung in der Netzwerkverwaltung gesteuert. Die Sammelverarbeitung gilt für die Videos dieser Website.', 'ps-medienoptimierung' ); ?></p>
			<?php endif; ?>
			<?php if ( is_wp_error( $backend ) ) : ?>
				<p class="ps-smush-notice"><?php echo esc_html( $backend->get_error_message() ); ?></p>
			<?php endif; ?>
			<div class="col-half float-l">
			<section class="dev-box ps-smush-container ps-video-bulk">
				<div class="box-title"><h3><?php esc_html_e( 'Video-Bulk-Optimierung', 'ps-medienoptimierung' ); ?></h3></div>
				<div class="box-container">
					<div class="ps-smush-notice" id="ps-video-pending"><?php echo esc_html( sprintf( __( 'Noch zu optimierende Videos: %d', 'ps-medienoptimierung' ), $stats['pending'] ) ); ?></div>
					<p><?php esc_html_e( 'Lokale MP4-Videos für Mobilgeräte anpassen und auf Wunsch komprimieren oder verkleinern. Bestehende URLs bleiben erhalten.', 'ps-medienoptimierung' ); ?></p>
					<p class="tr"><button type="button" class="button button-cta" id="ps-video-start" <?php disabled( is_wp_error( $backend ) || ! $stats['pending'] ); ?>><?php esc_html_e( 'BULK-OPTIMIERUNG', 'ps-medienoptimierung' ); ?></button></p>
					<div id="ps-video-progress-wrap" hidden>
						<div class="ps-smush-progress-wrap">
							<i class="icon-fi-loader" aria-hidden="true"></i>
							<div class="ps-smush-progress-bar-wrap" role="progressbar" id="ps-video-bar" aria-label="<?php esc_attr_e( 'Video-Fortschritt', 'ps-medienoptimierung' ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
								<div class="ps-video-progress-inner"></div>
							</div>
						</div>
						<p class="ps-smush-count" id="ps-video-progress" role="status" aria-live="polite"></p>
						<p class="tc"><button type="button" class="button button-grey" id="ps-video-stop" disabled><?php esc_html_e( 'Nach aktuellem Video anhalten', 'ps-medienoptimierung' ); ?></button></p>
					</div>
					<p class="smush-setting-description"><?php esc_html_e( 'Originale werden erst nach erfolgreicher Prüfung ersetzt. Für die Stapelverarbeitung diese Seite geöffnet lassen.', 'ps-medienoptimierung' ); ?></p>
					<details class="ps-video-log"><summary><?php esc_html_e( 'Ergebnisse und Fehler', 'ps-medienoptimierung' ); ?></summary>
					<ul id="ps-video-results" aria-live="polite">
			<?php
			foreach ( $recent as $post ) {
				$status = get_post_meta( $post->ID, PS_Smush_Video::META, true );
				$message = isset( $status['message'] ) ? $status['message'] : '';
				if ( isset( $status['before'], $status['after'] ) ) {
					$message .= ' ' . size_format( $status['before'], 2 ) . ' → ' . size_format( $status['after'], 2 );
				}
				echo '<li><a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a>: ' . esc_html( $message ) . '</li>';
			}
			?>
					</ul>
					<p><button type="button" class="button button-grey button-small" id="ps-video-clear-log" <?php disabled( ! $recent ); ?>><?php esc_html_e( 'PROTOKOLL LEEREN', 'ps-medienoptimierung' ); ?></button></p>
					<p class="smush-setting-description"><?php esc_html_e( 'Leert nur diese Ergebnisliste. Optimierungsstände, Einsparungen und offene Fehler bleiben erhalten.', 'ps-medienoptimierung' ); ?></p>
					</details>
					<p id="ps-video-log-status" role="status" aria-live="polite"></p>
				</div>
			</section>
			</div>
			<div class="col-half float-l">
			<section class="dev-box ps-smush-container ps-video-stats">
				<div class="box-title">
					<h3><?php esc_html_e( 'Video-Statistiken', 'ps-medienoptimierung' ); ?></h3>
					<button type="button" class="button button-grey button-small" id="ps-video-recheck" <?php disabled( is_wp_error( $backend ) || ! $stats['total'] ); ?>><?php esc_html_e( 'VIDEOS ERNEUT PRÜFEN', 'ps-medienoptimierung' ); ?></button>
				</div>
				<div class="box-content">
					<div class="row ps-video-counts">
						<div class="ps-video-count-primary"><span data-video-stat="correct"><?php echo esc_html( $stats['correct'] ); ?></span><span class="total-stats-label"><?php esc_html_e( 'Handykompatible Videos', 'ps-medienoptimierung' ); ?></span></div>
						<div class="ps-video-count-secondary">
							<p><strong data-video-stat="optimized"><?php echo esc_html( $stats['optimized'] ); ?></strong> <?php esc_html_e( 'Videos optimiert', 'ps-medienoptimierung' ); ?></p>
							<p><strong data-video-stat="resized"><?php echo esc_html( $stats['resized'] ); ?></strong> <?php esc_html_e( 'Videos verkleinert', 'ps-medienoptimierung' ); ?></p>
						</div>
					</div>
					<?php foreach ( array( 'total' => __( 'Videos gesamt', 'ps-medienoptimierung' ), 'pending' => __( 'Noch zu optimieren', 'ps-medienoptimierung' ), 'unchecked' => __( 'Noch ungeprüft oder verändert', 'ps-medienoptimierung' ), 'errors' => __( 'Fehler', 'ps-medienoptimierung' ), 'unsupported' => __( 'Nicht unterstützte Videos', 'ps-medienoptimierung' ) ) as $key => $label ) : ?>
						<hr><div class="row ps-smush-savings"><span class="float-l ps-smush-stats-label"><?php echo esc_html( $label ); ?></span><span class="float-r ps-smush-stats" data-video-stat="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $stats[ $key ] ); ?></span></div>
					<?php endforeach; ?>
					<hr><div class="row ps-smush-savings"><span class="float-l ps-smush-stats-label"><?php esc_html_e( 'Gesamteinsparung / Mehrbedarf (+)', 'ps-medienoptimierung' ); ?></span><span class="float-r ps-smush-stats"><span data-video-stat="saved_human"><?php echo esc_html( $stats['saved_human'] ); ?></span> / <span data-video-stat="percent"><?php echo esc_html( $stats['percent'] ); ?></span>%</span></div>
					<hr><div class="row ps-smush-savings"><span class="float-l ps-smush-stats-label"><?php esc_html_e( 'Dateigröße vorher / nachher', 'ps-medienoptimierung' ); ?></span><span class="float-r ps-smush-stats"><span data-video-stat="before_human"><?php echo esc_html( $stats['before_human'] ); ?></span> / <span data-video-stat="after_human"><?php echo esc_html( $stats['after_human'] ); ?></span></span></div>
					<hr><div class="row ps-smush-savings"><span class="float-l ps-smush-stats-label"><?php esc_html_e( 'Einsparung bei verkleinerten Videos', 'ps-medienoptimierung' ); ?></span><span class="float-r ps-smush-stats" data-video-stat="resize_human"><?php echo esc_html( $stats['resize_human'] ); ?></span></div>
					<p class="smush-setting-description"><?php esc_html_e( 'Bereits passende Codecs zählen als handykompatibel, auch wenn die Dateigröße noch optimiert werden kann. Erneut prüfen aktualisiert den Bedarf ohne Dateien zu verändern.', 'ps-medienoptimierung' ); ?></p>
				</div>
			</section>
			</div>
		</div>
		<?php
	}
}
