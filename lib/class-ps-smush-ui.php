<?php
/**
 * @package PS Smush
 * @subpackage Admin
 * @version 1.0
 *
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2016, Incsub (http://incsub.com)
 */
if ( ! class_exists( 'WpSmushBulkUi' ) ) {
	/**
	 * Show settings in Media settings and add column to media library
	 *
	 */

	/**
	 * Class WpSmushBulkUi
	 */
	class WpSmushBulkUi {

	    public $setting_group = array(
	      'resize',
	      'original',
	      'backup'
	    );

	    function __construct() {
	        add_action('smush_setting_column_right_end', array( $this, 'full_size_options' ), '', 2 );
	    }

		/**
		 * Prints the Header Section for a container as per the Shared UI
		 *
		 * @param string $classes Any additional classes that needs to be added to section
		 * @param string $heading Box Heading
		 * @param string $sub_heading Any additional text to be shown by the side of Heading
		 * @param bool $dismissible If the Box is dimissible
		 *
		 * @return string
		 */
		function container_header( $classes = '', $id = '', $heading = '', $sub_heading = '', $dismissible = false ) {
			if ( empty( $heading ) ) {
				return '';
			}
			echo '<section class="dev-box ' . $classes . ' ps-smush-container" id="' . $id . '">'; ?>
			<div class="ps-smush-container-header box-title" xmlns="http://www.w3.org/1999/html">
			<h3 tabindex="0"><?php echo $heading ?></h3><?php
			//Sub Heading
			if ( ! empty( $sub_heading ) ) { ?>
				<div class="smush-container-subheading roboto-medium"><?php echo $sub_heading ?></div><?php
			}
			//Dismissible
			if ( $dismissible ) { ?>
				<div class="float-r smush-dismiss-welcome">
				<a href="#" title="<?php esc_html_e( "Dismiss Welcome notice", "ps-medienoptimierung" ); ?>">
					<i class="icon-fi-close"></i>
				</a>
				</div><?php
			} ?>
			</div><?php
		}

		/**
		 *  Prints the content of WelCome Screen for New Installation
		 *  Dismissible by default
		 */
		function quick_setup() {
			global $WpSmush, $wpsmushit_admin, $wpsmush_settings;

			//Header Of the Box ?>
			<dialog id="smush-quick-setup" title="<?php esc_html_e( "QUICK SETUP", "ps-medienoptimierung" ); ?>" class="small">
				<p class="ps-smush-welcome-message end"><?php esc_html_e( 'Welcome to Smush - Winner of Torque Plugin Madness 2017! Let\'s quickly set up the basics for you, then you can fine tune each setting as you go - our recommendations are on by default.', "ps-medienoptimierung" ); ?></p>
				<div class="smush-quick-setup-settings">
					<form method="post">
					<input type="hidden" value="setupSmush" name="action"/><?php
					wp_nonce_field( 'setupSmush' );
					    $exclude = array(
					            'backup',
					            'png_to_jpg',
					            'nextgen',
					            's3'
					    );
					    //Settings for free and pro version
					    foreach( $wpsmushit_admin->settings as $name => $values ) {
					        //Skip networkwide settings, we already printed it
					        if( 'networkwide' == $name ) {
					            continue;
					        }
					        //Skip premium features if not a member
					        if( !in_array( $name, $wpsmushit_admin->basic_features ) && !$WpSmush->validate_install() ) {
					            continue;
					        }
					        //Do not output settings listed in exclude array list
					        if( in_array($name, $exclude ) ) {
					            continue;
					        }
					        $setting_m_key = PS_SMUSH_PREFIX . $name;
							$setting_val   = $WpSmush->validate_install() ? $wpsmush_settings->settings[$name] : false;
							//Set the default value 1 for auto smush
							if( 'auto' == $name && false === $setting_val ) {
							    $setting_val = 1;
							} ?>
							<div class='ps-smush-setting-row ps-smush-basic'>
								<label class="inline-label" for="<?php echo $setting_m_key . '-quick-setup'; ?>">
									<span class="ps-smush-setting-label"><?php echo $wpsmushit_admin->settings[ $name ]['label']; ?></span><br/>
									<small class="smush-setting-description">
		                                <?php echo $wpsmushit_admin->settings[ $name ]['desc']; ?>
		                            </small>
		                        </label>
		                        <span class="toggle float-r">
		                            <input type="checkbox" class="toggle-checkbox"
		                               id="<?php echo $setting_m_key . '-quick-setup'; ?>"
		                               name="smush_settings[]" <?php checked( $setting_val, 1, true ); ?> value="<?php echo $setting_m_key; ?>" tabindex="0">
		                            <label class="toggle-label" for="<?php echo $setting_m_key . '-quick-setup'; ?>" aria-hidden="true"></label>
		                        </span>
		                        <?php $this->resize_settings( $name, 'quick-setup-' ); ?>
							</div><?php
						}
						?>
						<div class="columns last">
							<div class="column is-3 tr submit-button-wrap">
								<button type="submit" class="button"><?php _e( "Get Started", "ps-medienoptimierung" ) ?></button>
							</div>
						</div>
					</form>
				</div>
			</dialog><?php
		}

		/**
		 * Bulk Smush UI and Progress bar
		 */
		function bulk_smush_container() {
			global $WpSmush;

			//Subheading content
			$smush_individual_msg = sprintf( esc_html__( "Smush individual images via your %sMedia Library%s", "ps-medienoptimierung" ), '<a href="' . esc_url( admin_url( 'upload.php' ) ) . '" title="' . esc_html__( 'Media Library', 'ps-medienoptimierung' ) . '">', '</a>' );

			$class = $WpSmush->validate_install() ? 'bulk-smush-wrapper ps-smush-pro-install' : 'bulk-smush-wrapper';

			//Contianer Header
			$this->container_header( $class, 'ps-smush-bulk-wrap-box', esc_html__( "BULK SMUSH", "ps-medienoptimierung" ), $smush_individual_msg ); ?>

			<div class="box-container"><?php
			$this->bulk_smush_content(); ?>
			</div><?php
			echo "</section>";
		}

		/**
		 * All the settings for Basic and Advanced Users
		 */
		function settings_ui() {
			global $WpSmush;
			$class = $WpSmush->validate_install() ? 'smush-settings-wrapper ps-smush-pro' : 'smush-settings-wrapper';
			$this->container_header( $class, 'ps-smush-settings-box', esc_html__( "SETTINGS", "ps-medienoptimierung" ), '' );
			// display the options
			$this->options_ui();
		}

		/**
        * Outputs the Smush stats for the site
        * @todo: Divide the function into parts, way too big
        *
        */
		function smush_stats_container() {
			global $WpSmush, $wpsmushit_admin, $wpsmush_db, $wpsmush_settings, $wpsmush_dir;

			$settings = $wpsmush_settings->settings;

			$button = '<span class="spinner"></span><button tooltip="' . esc_html__( "Lets you check if any images can be further optimized. Useful after changing settings.", "ps-medienoptimierung" ) . '" class="ps-smush-title button button-grey button-small ps-smush-scan">' . esc_html__( "RE-CHECK IMAGES", "ps-medienoptimierung" ) . '</button>';
			$this->container_header( 'smush-stats-wrapper', 'ps-smush-stats-box', esc_html__( "STATS", "ps-medienoptimierung" ), $button );

			$resize_count = $wpsmush_db->resize_savings( false, false, true );
			$resize_count = !$resize_count ? 0 : $resize_count;

			$compression_savings = 0;

			if( !empty( $wpsmushit_admin->stats ) && !empty( $wpsmushit_admin->stats['bytes'] ) ) {
    			$compression_savings = $wpsmushit_admin->stats['bytes'] - $wpsmushit_admin->stats['resize_savings'];
			}

			$tooltip = $wpsmushit_admin->stats['total_images'] > 0 ? 'tooltip="' . sprintf( esc_html__("You've smushed %d images in total", "ps-medienoptimierung"), $wpsmushit_admin->stats['total_images'] ) . '"' : ''; ?>
			<div class="box-content">
			<div class="row smush-total-savings smush-total-reduction-percent">

                <div class="ps-smush-current-progress" >
                    <!-- Total Images Smushed -->
                    <div class="ps-smush-count-total">
                        <div class="ps-smush-smush-stats-wrapper">
                            <span class="ps-smush-total-optimised"><?php echo $wpsmushit_admin->stats['total_images']; ?></span>
                        </div>
                        <span class="total-stats-label"><?php esc_html_e( "Images smushed", "ps-medienoptimierung" ); ?></span>
                    </div>
                    <!-- Attachments And Resized Images -->
                    <div class="ps-smush-stats-other">
                        <!-- Attachment count -->
                        <div class="ps-smush-count-attachment-total">
                            <div class="ps-smush-smush-stats-wrapper">
                                <span class="ps-smush-total-optimised"><?php echo $wpsmushit_admin->smushed_count; ?></span>
                            </div>
                            <span class="total-stats-label"><?php esc_html_e( "Attachments smushed", "ps-medienoptimierung" ); ?></span>
                        </div>
                        <!-- Resized Image count -->
                        <div class="ps-smush-count-resize-total">
                            <div class="ps-smush-smush-stats-wrapper">
                                <span class="ps-smush-total-optimised"><?php echo $resize_count; ?></span>
                            </div>
                            <span class="total-stats-label"><?php esc_html_e( "Images resized", "ps-medienoptimierung" ); ?></span>
                        </div>
                    </div>

                </div>
			</div>
			<hr />
			<div class="row ps-smush-savings">
				<span class="float-l ps-smush-stats-label"><?php esc_html_e("Total savings", "ps-medienoptimierung");?></span>
				<span class="float-r ps-smush-stats">
					<span class="ps-smush-stats-human">
						<?php echo $wpsmushit_admin->stats['human'] > 0 ? $wpsmushit_admin->stats['human'] : "0MB"; ?>
					</span>
					<span class="ps-smush-stats-sep">/</span>
					<span class="ps-smush-stats-percent"><?php echo $wpsmushit_admin->stats['percent'] > 0 ? number_format_i18n( $wpsmushit_admin->stats['percent'], 1, '.', '' ) : 0; ?></span>%
				</span>
			</div><?php
			/**
			 * Allows to hide the Super Smush stats as it might be heavy for some users
			 */
			if ( $WpSmush->validate_install() && apply_filters( 'ps_smush_show_lossy_stats', true ) ) {
				$wpsmushit_admin->super_smushed = $wpsmush_db->super_smushed_count();?>
				<hr />
				<div class="row super-smush-attachments">
				<span class="float-l ps-smush-stats-label"><strong><?php esc_html_e( "Super-smushed savings", "ps-medienoptimierung" ); ?></strong></span>
				<span class="ps-smush-stats<?php echo $WpSmush->lossy_enabled ? ' float-r' : ' float-l ps-smush-lossy-disabled-wrap' ?>"><?php
					if ( $WpSmush->lossy_enabled ) {
						echo '<span class="smushed-savings">' . size_format( $compression_savings, 1 ) . '</span>';
					} else {
					    //Output a button/link to enable respective setting
					    if( !is_multisite() || !$settings['networkwide'] ) {
                            printf( esc_html__( "Compress images up to 2x more than regular smush with almost no visible drop in quality. %sEnable Super-smush%s", "ps-medienoptimierung" ), '<a class="ps-smush-lossy-enable" href="#">', '</a>' );
						}else {
					        $settings_link = $wpsmushit_admin->settings_link( array(), true );
                            printf( esc_html__( "Compress images up to 2x more than regular smush with almost no visible drop in quality. %sEnable Super-smush%s", "ps-medienoptimierung" ), '<a class="ps-smush-lossy-enable-network" href="'. $settings_link .'">', '</a>' );
						}
					} ?>
				</span>
				</div><?php
			} ?>
			<hr /><?php
			    if( !$settings['resize'] && empty( $wpsmushit_admin->stats['resize_savings'] ) ) {
			        $class = ' settings-desc float-l';
			    }elseif ( empty( $wpsmushit_admin->stats['resize_savings'] ) ) {
			        $class = ' settings-desc float-r';
			    }else{
			        $class = ' float-r';
			    }
			  ?>
			<div class="row smush-resize-savings">
				<span class="float-l ps-smush-stats-label"><strong><?php esc_html_e( "Resize savings", "ps-medienoptimierung" ); ?></strong></span>
				<span class="ps-smush-stats<?php echo $class; ?>"><?php
					if( !empty( $wpsmushit_admin->stats['resize_savings'] ) && $wpsmushit_admin->stats['resize_savings'] > 0 ) {
						echo size_format( $wpsmushit_admin->stats['resize_savings'], 1 );
					}else{
						if( !$settings['resize'] ) {
							//Output a button/link to enable respective setting
							if( !is_multisite() || !$settings['networkwide'] ) {
							    printf( esc_html__( "Save storage space by resizing your full sized uploads down to a maximum size. %sEnable image resizing%s", "ps-medienoptimierung" ), '<a class="ps-smush-resize-enable" href="#">', '</a>' );
                            }else {
                                $settings_link = $wpsmushit_admin->settings_link( array(), true );
                                printf( esc_html__( "Save storage space by resizing your full sized uploads down to a maximum size. %sEnable image resizing%s", "ps-medienoptimierung" ), '<a href="' . $settings_link .'" class="ps-smush-resize-enable">', '</a>' );
                            }
						}else{
							printf( esc_html__( "No resize savings available", "ps-medienoptimierung" ), '<span class="total-stats-label"><strong>', '</strong></span>' );
						}
					} ?>
				</span>
			</div>
			<?php
			if( $WpSmush->validate_install() && !empty( $wpsmushit_admin->stats['conversion_savings'] ) && $wpsmushit_admin->stats['conversion_savings'] > 0 ) { ?>
				<hr />
				<div class="row smush-conversion-savings">
					<span class="float-l ps-smush-stats-label"><strong><?php esc_html_e( "PNG to JPEG savings", "ps-medienoptimierung" ); ?></strong></span>
					<span class="float-r ps-smush-stats"><?php echo $wpsmushit_admin->stats['conversion_savings'] > 0 ? size_format( $wpsmushit_admin->stats['conversion_savings'], 1 ) : "0MB"; ?></span>
				</div><?php
			}
			/**
			* Allows to output Directory Smush stats
            */
			do_action('stats_ui_after_resize_savings');
			/**
			 * Allows you to output any content within the stats box at the end
			 */
			do_action( 'ps_smush_after_stats' );
			echo "</div>";
			//Pro Savings Expected: For free Version
			if ( ! $WpSmush->validate_install() ) {
			    //Initialize pro savings if not set already
			    if( empty( $wpsmushit_admin->stats) || empty( $wpsmushit_admin->stats['pro_savings'] ) ) {
			        $wpsmushit_admin->set_pro_savings();
			    }
			    $pro_savings = $wpsmushit_admin->stats['pro_savings'];
			    $show_pro_savings = $pro_savings['savings'] > 0 ? true : false;
				$pro_only = ''; ?>
				<!-- Make a hidden div if not stats found -->
				<div class="row" id="smush-avg-pro-savings" <?php echo $show_pro_savings ? '' : 'style="display: none;"'; ?>>
					<div class="row smush-avg-pro-savings">
						<span class="float-l ps-smush-stats-label"><span tooltip="<?php esc_html_e("Basiert auf durchschnittlichen Einsparungswerten", "ps-medienoptimierung"); ?>"><strong><?php esc_html_e( "Einsparungs-Schaetzung", "ps-medienoptimierung" ); ?></strong></span><span class="ps-smush-stats-try-pro roboto-regular"><?php echo $pro_only; ?></span></span>
						<span class="float-r ps-smush-stats">
							<span class="ps-smush-stats-human">
								<?php echo $show_pro_savings ? $pro_savings['savings']: '0.0 B'; ?>
							</span>
							<span class="ps-smush-stats-sep">/</span>
							<span class="ps-smush-stats-percent"><?php echo $show_pro_savings ? $pro_savings['percent'] : 0;  ?></span>%
						</span>
					</div>
				</div><?php
			}
			echo "</section>";
		}

		/**
		 * Outputs the advanced settings for Pro users, Disabled for basic users by default
		 */
		function advanced_settings( $configure_screen = false ) {
			global $WpSmush, $wpsmushit_admin, $wpsmush_settings;

			//Content for the End of box container
			$div_end = $this->save_button( $configure_screen );

			//Available advanced settings
			$pro_settings = array(
				'lossy',
				'original',
				'backup',
				'png_to_jpg'
			);

			//For Basic User, Show advanced settings in a separate box
			if ( ! $WpSmush->validate_install() ) {
				echo $div_end;
				do_action('ps_smush_before_advanced_settings');
				//Network settings wrapper
				if( is_multisite() && is_network_admin() ) {
					$class = $wpsmush_settings->settings['networkwide'] ? '' : ' hidden'; ?>
					<div class="network-settings-wrapper<?php echo $class; ?>"><?php
				}
				$pro_only = '';

				$this->container_header( 'ps-smush-premium', 'ps-smush-pro-settings-box', esc_html__( "ADVANCED SETTINGS", "ps-medienoptimierung" ), $pro_only, false ); ?>
				<div class="box-content"><?php

				$pro_settings = apply_filters( 'ps_smush_pro_settings', $pro_settings );
				//Iterate Over all the available settings, and print a row for each of them
                foreach ( $pro_settings as $setting_key ) {
                    //Output the Full size setting option only once
					if( in_array( $setting_key, $this->setting_group ) ) {
					    if( ( 'original' != $setting_key ) ) {
					        continue;
					    }
					}

                    if ( isset( $wpsmushit_admin->settings[ $setting_key ] ) ) {
                        $setting_m_key = PS_SMUSH_PREFIX . $setting_key;
                        $label = !empty( $wpsmushit_admin->settings[ $setting_key ]['short_label'] ) ? $wpsmushit_admin->settings[ $setting_key ]['short_label'] : $wpsmushit_admin->settings[ $setting_key ]['label'];
                        $setting_val   = $WpSmush->validate_install() ? $wpsmush_settings->get_setting( $setting_m_key, false ) : 0;?>
                        <div class='ps-smush-setting-row ps-smush-advanced'>
                            <div class="column column-left">
                                <label class="inline-label" for="<?php echo $setting_m_key; ?>" aria-hidden="true">
                                    <span class="ps-smush-setting-label"><?php echo $label; ?></span>
                                    <br/>
                                    <small class="smush-setting-description"><?php
                                        if( 'original' != $setting_key ) {
                                            echo $wpsmushit_admin->settings[ $setting_key ]['desc'];
                                        }else{
                                            esc_html_e("By default, Smush only compresses your cropped image sizes, not your original full-size images.", "ps-medienoptimierung");
                                        }
                                    ?>
                                    </small>
                                </label>
                            </div>
                            <div class="column column-right"><?php
						    //Do not print for Resize, Smush Original, Backup
						    if( !in_array( $setting_key, $this->setting_group ) ) { ?>
                                <span class="toggle float-l">
                                    <input type="checkbox" class="toggle-checkbox"
                                           id="<?php echo $setting_m_key; ?>" <?php checked( $setting_val, 1, true ); ?>
                                           value="1"
                                           name="<?php echo $setting_m_key; ?>" tabindex= "0">
                                    <label class="toggle-label <?php echo $setting_m_key . '-label'; ?>" for="<?php echo $setting_m_key; ?>" aria-hidden="true"></label>
                                </span>
                                <div class="column-right-content">
                                    <label class="inline-label" for="<?php echo $setting_m_key; ?>" tabindex="0">
                                        <span class="ps-smush-setting-label"><?php echo $wpsmushit_admin->settings[ $setting_key ]['label']; ?></span><br/><?php
                                        $this->settings_desc( $setting_key );
                                        do_action('smush_setting_label_end', $setting_key);
                                        ?>
                                    </label>
                                </div><?php
                            }
                            do_action( 'smush_setting_column_right_end', $setting_key, 'advanced' );
                            ?>

                            </div>
                            <?php
	                             /**
	                             * Perform a action after setting row content
	                             */
	                            do_action('smush_setting_row_end', $setting_key );?>
                        </div><?php
                    }
                }
			}
			//Output Form end and Submit button for pro version
			echo $div_end;
			//Close wrapper div
			if( is_multisite() && is_network_admin() && !$WpSmush->validate_install() ) {
				echo "</div>";
			}
		}

		/**
		 * Process and display the Settings
		 * Since Free and Pro version have different sequence of settings, we've a separate method advanced_settings(),
		 * Which prints out pro settings fro free version, otherwise all settings are printed via the current function
		 *
		 * To print Full size smush, resize and backup in group, we hook at `smush_setting_column_right_end`
		 *
		 */
		function options_ui( $configure_screen = false ) {
			global $WpSmush, $wpsmushit_admin, $wpsmush_settings;

			$settings = !empty( $wpsmush_settings->settings ) ? $wpsmush_settings->settings : $wpsmush_settings->init_settings();

			echo '<div class="box-container">
				<form id="ps-smush-settings-form" method="post">';

			//Use settings networkwide,@uses get_site_option() and not get_option
			$opt_networkwide = PS_SMUSH_PREFIX . 'networkwide';
			$opt_networkwide_val = $wpsmush_settings->settings['networkwide'];

			//Option to enable/disable networkwide settings
			if( is_multisite() && is_network_admin() ) {
				$class = $wpsmush_settings->settings['networkwide'] ? '' : ' hidden'; ?>
				<!-- A tab index of 0 keeps the element in tab flow with other elements with an unspecified tab index which are still tabbable.) -->
				<div class='ps-smush-setting-row ps-smush-basic'>
				    <div class="column column-left"">
                        <label class="inline-label" for="<?php echo $opt_networkwide; ?>" aria-hidden="true">
                            <span class="ps-smush-setting-label">
                                <?php echo $wpsmushit_admin->settings['networkwide']['short_label']; ?>
                            </span><br/>
                            <small class="smush-setting-description">
                                <?php echo $wpsmushit_admin->settings['networkwide']['desc']; ?>
                            </small>
                        </label>
					</div>
					<div class="column column-right">
                        <span class="toggle float-l">
                            <input type="checkbox" class="toggle-checkbox"
                               id="<?php echo $opt_networkwide; ?>"
                               name="<?php echo $opt_networkwide; ?>" <?php checked( $opt_networkwide_val, 1, true ); ?> value="1">
                            <label class="toggle-label" for="<?php echo $opt_networkwide; ?>" aria-hidden="true"></label>
                        </span>
                        <div class="column-right-content">
                            <label class="inline-label" for="<?php echo $opt_networkwide; ?>">
                                <span class="ps-smush-setting-label"><?php echo $wpsmushit_admin->settings['networkwide']['label']; ?></span><br/>
                            </label>
                        </div>
					</div>
				</div>
				<input type="hidden" name="setting-type" value="network">
				<div class="network-settings-wrapper<?php echo $class; ?>"><?php
			}

			//Do not print settings in network page if networkwide settings are disabled
			if( ! is_multisite() || ( ! $wpsmush_settings->settings['networkwide'] && !is_network_admin() ) || is_network_admin() ) {
				foreach( $wpsmushit_admin->settings as $name => $values ) {

					//Skip networkwide settings, we already printed it
					if( 'networkwide' == $name ) {
						continue;
					}

			        //Skip premium features if not a member
			        if( !in_array( $name, $wpsmushit_admin->basic_features ) && !$WpSmush->validate_install() ) {
			            continue;
			        }

			        $setting_m_key = PS_SMUSH_PREFIX . $name;
					$setting_val   = !empty( $settings[$name] ) ? $settings[$name] : 0;

					//Set the default value 1 for auto smush
					if( 'auto' == $name && ( false === $setting_val || !isset( $setting_val ) ) ) {
					    $setting_val = 1;
					}

					//Group Original, Resize and Backup for pro users
					if( in_array( $name, $this->setting_group ) ) {
					    if( ( 'original' != $name && $WpSmush->validate_install() ) || ( !$WpSmush->validate_install() && 'resize' != $name ) ) {
					        continue;
					    }
					}

					$label = !empty( $wpsmushit_admin->settings[ $name ]['short_label'] ) ? $wpsmushit_admin->settings[ $name ]['short_label'] : $wpsmushit_admin->settings[ $name ]['label']; ?>
					<div class='ps-smush-setting-row ps-smush-basic'>
						<div class="column column-left">
							<label class="inline-label" for="<?php echo 'column-' . $setting_m_key; ?>" aria-hidden="true">
	                            <span class="ps-smush-setting-label"><?php echo $label; ?></span><br/>
	                            <small class="smush-setting-description"><?php
	                                //For pro settings, print a different description for group setting
	                                if( 'original' != $name && 'resize' != $name ) {
	                                    echo $wpsmushit_admin->settings[ $name ]['desc'];
	                                }else{
	                                    esc_html_e("Save a ton of space by not storing over-sized images on your server.", "ps-medienoptimierung");
	                                }?>
	                            </small>
	                        </label>
                        </div>
						<div class="column column-right" id="column-<?php echo $setting_m_key; ?>"><?php
						    //Do not print for Resize, Smush Original, Backup
						    if( !in_array( $name, $this->setting_group ) ) { ?>
                                <span class="toggle float-l">
                                    <input type="checkbox" class="toggle-checkbox" aria-describedby="<?php echo $setting_m_key . '-desc'?>"
                                       id="<?php echo $setting_m_key; ?>"
                                       name="<?php echo $setting_m_key; ?>" <?php checked( $setting_val, 1, true ); ?> value="1">
                                    <label class="toggle-label <?php echo $setting_m_key . '-label'; ?>" for="<?php echo $setting_m_key; ?>" aria-hidden="true"></label>
                                </span>
                                <div class="column-right-content">
                                    <label class="inline-label" for="<?php echo $setting_m_key; ?>">
                                        <span class="ps-smush-setting-label"><?php echo $wpsmushit_admin->settings[ $name ]['label']; ?></span><br/>
                                    </label><?php
                                    $this->settings_desc( $name );
                                    $this->image_sizes( $name ); ?>
                                </div><?php
                            }
                            /**
                            * Print/Perform action in right setting column, Used to group Pro settings
                            */
                            do_action('smush_setting_column_right_end', $name); ?>
                        </div>
				    </div>
				    <?php
				}
				do_action( 'ps_smush_after_basic_settings' );
				$this->advanced_settings( $configure_screen );
			} else{
				echo "<hr />";
				echo $this->save_button( $configure_screen );
				echo "</div><!-- Box Content -->
				</section><!-- Main Section -->";
			}
		}

		/**
		 * Display the Whole page ui, Call all the other functions under this
		 */
		function ui() {

			global $WpSmush, $wpsmushit_admin, $wpsmush_settings, $wpsmush_dir;

			if( !$WpSmush->validate_install() ) {
				//Reset Transient
				$wpsmushit_admin->check_bulk_limit( true );
			}

			$this->smush_page_header();
			$is_network = is_network_admin();

			if( !$is_network ) {
				//Show Configure screen for only a new installation and for only network admins
				if ( ( 1 != get_site_option( 'skip-smush-setup' ) && 1 != get_site_option( 'ps-smush-hide_smush_welcome' ) && 1 != get_option( 'ps-smush-hide_smush_welcome' ) ) && 1 != get_option( 'hide_smush_features' ) && is_super_admin() ) {
					echo '<div class="block float-l">';
					$this->quick_setup();
					echo '</div>';
				}
				?>

				<!-- Bulk Smush Progress Bar -->
				<div class="ps-smushit-container-left col-half float-l"><?php
					//Bulk Smush Container
					$this->bulk_smush_container();
    				if( $WpSmush->validate_install() ) { ?>
	    			    <!-- Stats Share Widget -->
                        <div class="col-half share-widget-wrapper"><?php
                            global $wpsmush_share;
                            $wpsmush_share->share_widget(); ?>
                        </div><?php
    				} ?>
				</div>

				<!-- Stats -->
				<div class="ps-smushit-container-right col-half float-l"><?php
					//Stats
					$this->smush_stats_container();
					if ( ! $WpSmush->validate_install() ) {
						/**
						 * Allows to Hook in Additional Containers after Stats Box for free version
						 * Pro Version has a full width settings box, so we don't want to do it there
						 */
						do_action( 'ps_smush_after_stats_box' );
					} ?>
				</div><!-- End Of Smushit Container right --><?php
                    if( !$WpSmush->validate_install() ) {?>
                        <!-- Stats Share Widget -->
                        <div class="row"><?php
                            global $wpsmush_share;
                            $wpsmush_share->share_widget(); ?>
                        </div><?php
                    }
			//End of "!is_network()' check
			}?>

			<!-- Settings -->
			<div class="row"><?php
				wp_nonce_field( 'save_ps_smush_options', 'ps_smush_options_nonce', '', true );
				//Check if a network site and networkwide settings is enabled
				if( ! is_multisite() || ( is_multisite() && ! $wpsmush_settings->settings['networkwide'] ) || ( is_multisite() && is_network_admin() ) ) {
					$this->settings_ui();
				}

				do_action('smush_settings_ui_bottom');

				?>
			</div><?php
			$this->smush_page_footer();
		}



		/**
		 * Outputs the Content for Bulk Smush Div
		 */
		function bulk_smush_content() {

			global $WpSmush, $wpsmushit_admin, $wpsmush_settings;

			$all_done = ( $wpsmushit_admin->smushed_count == $wpsmushit_admin->total_count ) && 0 == count( $wpsmushit_admin->resmush_ids );

			echo $this->bulk_resmush_content();
			//Check whether to show pagespeed recommendation or not
			$hide_pagespeed = get_site_option(PS_SMUSH_PREFIX . 'hide_pagespeed_suggestion');

			//If there are no images in Media Library
			if ( 0 >= $wpsmushit_admin->total_count ) { ?>
				<span class="ps-smush-no-image tc">
					<img src="<?php echo PS_SMUSH_URL . 'assets/images/smush-no-media.png'; ?>"
					     alt="<?php esc_html_e( "No attachments found - Upload some images", "ps-medienoptimierung" ); ?>">
		        </span>
				<p class="ps-smush-no-images-content tc roboto-regular"><?php printf( esc_html__( "We haven’t found any images in your %smedia library%s yet so there’s no smushing to be done! Once you upload images, reload this page and start playing!", "ps-medienoptimierung" ), '<a href="' . esc_url( admin_url( 'upload.php' ) ) . '">', '</a>' ); ?></p>
				<span class="ps-smush-upload-images tc">
				<a class="button button-cta"
				   href="<?php echo esc_url( admin_url( 'media-new.php' ) ); ?>"><?php esc_html_e( "UPLOAD IMAGES", "ps-medienoptimierung" ); ?></a>
				</span><?php
			} else { ?>
				<!-- Hide All done div if there are images pending -->
				<div class="ps-smush-notice ps-smush-all-done<?php echo $all_done ? '' : ' hidden' ?>" tabindex="0">
					<i class="icon-fi-check-tick"></i><?php esc_html_e( "All images are smushed and up to date. Awesome!", "ps-medienoptimierung" ); ?>
				</div><?php
				if( !$hide_pagespeed ) {?>
                    <div class="ps-smush-pagespeed-recommendation<?php echo $all_done ? '' : ' hidden' ?>">
						<span class="smush-recommendation-title roboto-medium"><?php esc_html_e("PageSpeed macht noch Probleme? Probier mal das hier...", "ps-medienoptimierung"); ?></span>
                        <ol class="smush-recommendation-list"><?php
                         if( !$WpSmush->validate_install() ) { ?>
							 <li class="smush-recommendation-lossy"><?php esc_html_e("Aktiviere erweiterte verlustbehaftete Komprimierung, um noch mehr rauszuholen.", "ps-medienoptimierung" ); ?></li><?php
                         }elseif ( !$wpsmush_settings->settings['lossy'] ) {?>
                             <li class="smush-recommendation-lossy"><?php printf( esc_html__("Enable %sSuper-smush%s for advanced lossy compression to optimise images further with almost no visible drop in quality.", "ps-medienoptimierung"), '<a href="#" class="ps-smush-lossy-enable">', "</a>" ); ?></li><?php
                         }?>
                         <li class="smush-recommendation-resize"><?php printf( esc_html__("Make sure your images are the right size for your theme. %sLearn more%s.", "ps-medienoptimierung"), '<a href="'. esc_url("https://goo.gl/kCqWxS") .'" target="_blank">', '</a>' ); ?></li><?php
                         if( !$wpsmush_settings->settings['resize'] ) {
                             //Check if resize original is disabled ?>
                             <li class="smush-recommendation-resize-original"><?php printf( esc_html__("Enable %sResize Original Images%s to scale big images down to a reasonable size and save a ton of space.", "ps-medienoptimierung"), '<a href="#" class="ps-smush-resize-enable">', "</a>"); ?></li><?php
                         }
                         ?>
                        </ol>
                        <span class="dismiss-recommendation"><i class="icon-fi-cross-close"></i><?php esc_html_e("DISMISS", "ps-medienoptimierung"); ?></span>
                    </div><?php
				} ?>
				<div class="ps-smush-bulk-wrapper <?php echo $all_done ? ' hidden' : ''; ?>"><?php
				//If all the images in media library are smushed
				//Button Text
				$button_content = esc_html__( "BULK SMUSH", "ps-medienoptimierung" );

				//Show the notice only if there are remaining images and if we aren't showing a notice for resmush
				if ( $wpsmushit_admin->remaining_count > 0 ) {
					$class = count( $wpsmushit_admin->resmush_ids ) > 0 ? ' hidden' : '';
					?>
					<div class="ps-smush-notice ps-smush-remaining<?php echo $class; ?>" tabindex="0">
					    <i class="icon-fi-warning-alert"></i>
						<span class="ps-smush-notice-text"><?php
							printf( _n( "%s, you have %s%s%d%s attachment%s that needs smushing!", "%s, you have %s%s%d%s attachments%s that need smushing!", $wpsmushit_admin->remaining_count, "ps-medienoptimierung" ), $wpsmushit_admin->get_user_name(), '<strong>', '<span class="ps-smush-remaining-count">', $wpsmushit_admin->remaining_count, '</span>', '</strong>' );
							?>
						</span>
					</div><?php
				} ?>
				<button type="button" class="ps-smush-all ps-smush-button" title="<?php esc_html_e('Click to start Bulk Smushing images in Media Library', 'ps-medienoptimierung'); ?>"><?php echo $button_content; ?></button>
				</div><?php
				$this->progress_bar( $wpsmushit_admin );
				//Enable Super Smush
				if ( $WpSmush->validate_install() && ! $WpSmush->lossy_enabled ) { ?>
					<p class="ps-smush-enable-lossy hidden"><?php esc_html_e( "Tip: Enable Super-smush in the Settings area to get even more savings with almost no visible drop in quality.", "ps-medienoptimierung" ); ?></p><?php
				}
				
			}
		}

		/**
		 * Content for showing Progress Bar
		 */
		function progress_bar( $count ) {

			//If we have resmush list, smushed_count = totalcount - resmush count, else smushed_count
//			$smushed_count = ( $resmush_count = count( $count->resmush_ids ) ) > 0 ? ( $count->total_count - ( $count->remaining_count + $resmush_count ) ) : $count->smushed_count;
			// calculate %ages, avoid divide by zero error with no attachments

			if ( $count->total_count > 0 && $count->smushed_count > 0 ) {
				$smushed_pc = $count->smushed_count / $count->total_count * 100;
			} else {
				$smushed_pc = 0;
			}
			?>
			<div class="ps-smush-bulk-progress-bar-wrapper hidden">
			<p class="ps-smush-bulk-active roboto-medium"><?php printf( esc_html__( "%sBulk smush is currently running.%s You need to keep this page open for the process to complete.", "ps-medienoptimierung" ), '<strong>', '</strong>' ); ?></p>
			<div class="ps-smush-progress-wrap">
			    <i class="icon-fi-loader"></i>
				<div class="ps-smush-progress-bar-wrap">
					<div class="ps-smush-progress-bar">
						<div class="ps-smush-progress-inner" style="width: <?php echo $smushed_pc; ?>%;">
						</div>
					</div>
				</div>
			</div>
			<div class="ps-smush-count tc">
                <?php printf( esc_html__( "%s%d%s of your media attachments have been smushed." ), '<span class="ps-smush-images-percent">', $smushed_pc, '</span>%' ); ?>
            </div>
            <div class="smush-cancel-button-wrapper">
                <button type="button"
                        class="button button-grey ps-smush-cancel-bulk" tooltip="<?php esc_html_e( "Stop current bulk smush process.", "ps-medienoptimierung"); ?>"><?php esc_html_e( "CANCEL", "ps-medienoptimierung" ); ?></button>
            </div>
			</div>
			<div class="smush-final-log notice notice-warning inline hidden"></div><?php
		}

		/**
		 * Shows a option to ignore the Image ids which can be resmushed while bulk smushing
		 *
		 * @param int $count Resmush + Unsmushed Image count
		 */
		function bulk_resmush_content( $count = false, $show = false ) {

			global $wpsmushit_admin;

			//If we already have count, don't fetch it
			if ( false === $count ) {
				//If we have the resmush ids list, Show Resmush notice and button
				if ( $resmush_ids = get_option( "ps-smush-resmush-list" ) ) {

					$count = count( $resmush_ids );

					//Whether to show the remaining re-smush notice
					$show = $count > 0 ? true : false;

					//Get the Actual remainaing count
					if ( ! isset( $wpsmushit_admin->remaining_count ) ) {
						$wpsmushit_admin->setup_global_stats();
					}

					$count = $wpsmushit_admin->remaining_count;
				}
			}
			//Show only if we have any images to ber resmushed
			if ( $show ) {
				return '<div class="ps-smush-notice ps-smush-resmush-notice ps-smush-remaining" tabindex="0">
						<i class="icon-fi-warning-alert"></i>
						<span class="ps-smush-notice-text">' . sprintf( _n( "%s, you have %s%s%d%s attachment%s that needs re-compressing!", "%s, you have %s%s%d%s attachments%s that need re-compressing!", $count, "ps-medienoptimierung" ), $wpsmushit_admin->get_user_name(), '<strong>', '<span class="ps-smush-remaining-count">', $count, '</span>', '</strong>' ) . '</span>
						<button class="button button-grey button-small ps-smush-skip-resmush" title="' . esc_html__("Skip re-smushing the images", "ps-medienoptimierung") . '">' . esc_html__( "Skip", "ps-medienoptimierung" ) . '</button>
	                </div>';
			}
		}

		/**
		 * Displays a admin notice for settings update
		 */
		function settings_updated() {
			global $wpsmushit_admin, $wpsmush_settings;

			//Check if Networkwide settings are enabled, Do not show settings updated message
			if( is_multisite() && $wpsmush_settings->settings['networkwide'] && !is_network_admin() ) {
				return;
			}

			//Show Setttings Saved message
			if ( 1 == $wpsmush_settings->get_setting( 'ps-smush-settings_updated', false ) ) {

				//Default message
				$message = esc_html__( "Your settings have been updated!", "ps-medienoptimierung" );

				//Additonal message if we got work to do!
				$resmush_count = is_array( $wpsmushit_admin->resmush_ids ) && count( $wpsmushit_admin->resmush_ids ) > 0;
				$smush_count   = is_array( $wpsmushit_admin->remaining_count ) && $wpsmushit_admin->remaining_count > 0;

				if ( $smush_count || $resmush_count ) {
					$message .= ' ' . sprintf( esc_html__( "You have images that need smushing. %sBulk smush now!%s", "ps-medienoptimierung" ), '<a href="#" class="ps-smush-trigger-bulk">', '</a>' );
				}
				echo '<div class="ps-smush-notice ps-smush-settings-updated"><i class="icon-fi-check-tick"></i> ' . $message . '
				<i class="icon-fi-close"></i>
				</div>';

				//Remove the option
				$wpsmush_settings->delete_setting( 'ps-smush-settings_updated' );
			}
		}

		/**
		 * Prints out the page header for Bulk Smush Page
		 */
		function smush_page_header() {
			global $WpSmush, $wpsmushit_admin, $wpsmush_s3;

			//Include Shared UI
			require_once PS_SMUSH_DIR . 'assets/shared-ui/plugin-ui.php';

			if( $wpsmushit_admin->remaining_count == 0 || $wpsmushit_admin->smushed_count == 0 ) {
				//Initialize global Stats
				$wpsmushit_admin->setup_global_stats();
			}

			//Page Heading for Free and Pro Version
			$page_heading = esc_html__( 'PS Medienoptimierung', 'ps-medienoptimierung' );

			$auto_smush_message = $WpSmush->is_auto_smush_enabled() ? sprintf( esc_html__( "Automatic smushing is %senabled%s. Newly uploaded images will be automagically compressed.", "ps-medienoptimierung" ), '<span class="ps-smush-auto-enabled">', '</span>' ) : sprintf( esc_html__( "Automatic smushing is %sdisabled%s. Newly uploaded images will need to be manually smushed.", "ps-medienoptimierung" ), '<span class="ps-smush-auto-disabled">', '</span>' );

			//User API check, and display a message if not valid
			$user_validation = $this->get_user_validation_message();

			//Re-Check images notice
			$recheck_notice = $this->get_recheck_message();

			echo '<div class="smush-page-wrap">
				<section id="header">
					<div class="ps-smush-page-header">
						<h1 class="ps-smush-page-heading">' . $page_heading . '</h1>
						<div class="ps-smush-auto-message roboto-regular">' . $auto_smush_message . '</div>
					</div>' .
					$user_validation .
					$recheck_notice .
				'</section>';

			//Check for any stored API message and show it
			$this->show_api_message();

			//Check if settings were updated and shoe a notice
			$this->settings_updated();

			//Show S3 integration message, if user hasn't enabled it
			if( is_object( $wpsmush_s3 ) && method_exists( $wpsmush_s3, 's3_support_required_notice') ) {
			    $wpsmush_s3->s3_support_required_notice();
			}

			echo '<div class="row ps-smushit-container-wrap">';
		}





		/**
		 * Prints Out the page Footer
		 */
		function smush_page_footer() {
			echo '</div><!-- End of Container wrap -->
			</div> <!-- End of div wrap -->';
		}

		/**
		* Returns a Warning message if API key is not validated
		*
		* @return string Warning Message to be displayed on Bulk Smush Page
		*
		*/
		function get_user_validation_message( $notice = false ) {
			$notice_class = $notice ? ' notice' : '';
			$attr_message = esc_html__( 'Pruefe gerade, ob der lokale Optimierer verfuegbar ist..', 'ps-medienoptimierung' );
			$message      = esc_html__( 'PS Medienoptimierung laeuft ohne externe Mitgliedschafts-Pruefung.', 'ps-medienoptimierung' );
			$content = sprintf( '<div id="ps-smush-invalid-member" data-message="%s" class="hidden' . $notice_class . '"><div class="message">%s</div></div>', $attr_message, $message );
			return $content;
		}

		/**
		*
		* @param $configure_screen
		*
		* @return string
		*
		*/
		function save_button( $configure_screen = false ) {
			$div_end = '';
			//Close wrapper div
			if( is_multisite() && is_network_admin() ) {
				$div_end .= "</div>";
			}

			$div_end .=
			'<span class="ps-smush-submit-wrap">
				<input type="submit" id="ps-smush-save-settings" class="button button-grey"
				       value="' . esc_html__( 'UPDATE SETTINGS', 'ps-medienoptimierung' ) . '">
		        <span class="spinner"></span>
		        <span class="smush-submit-note">' . esc_html__( "Smush will automatically check for any images that need re-smushing.", "ps-medienoptimierung") . '</span>
		        </span>
			</form>';

			//For Configuration screen we need to show the advanced settings in single box
			if ( ! $configure_screen ) {
				$div_end .= '</div><!-- Box Content -->
					</section><!-- Main Section -->';
			}
			return $div_end;
		}

		function get_recheck_message() {
			global $wpsmush_settings;
			//Return if not multisite, or on network settings page, Netowrkwide settings is disabled
			if( ! is_multisite() || is_network_admin() || ! $wpsmush_settings->settings['networkwide'] ) {
				return;
			}

			//Check the last settings stored in db
			$run_recheck = get_site_option( PS_SMUSH_PREFIX . 'run_recheck', false );

			//If not same, Display notice
			if( !$run_recheck ) {
				return;
			}
			$message = '<div class="ps-smush-notice ps-smush-re-check-message">' . esc_html__( "Smush settings were updated, performing a quick scan to check if any of the images need to be Smushed again.", "ps-medienoptimierung") . '<i class="icon-fi-close"></i></div>';

			return $message;
		}

		/**
        * Prints all the registererd image sizes, to be selected/unselected for smushing
        *
        * @param string $name
        */
		function image_sizes( $name = '' ) {
            if( empty( $name ) || 'auto' != $name ) {
                return;
            }
            global $wpsmushit_admin, $wpsmush_settings;
            //Additional Image sizes
            $image_sizes = $wpsmush_settings->get_setting( PS_SMUSH_PREFIX . 'image_sizes', false );
            $sizes = $wpsmushit_admin->image_dimensions();
            if( !empty( $sizes ) ) { ?>
                <!-- List of image sizes recognised by PS Smush -->
                <div class="ps-smush-image-size-list">
                    <span id="ps-smush-auto-desc"><?php printf( esc_html__("Every time you upload an image to your site, WordPress generates a resized version of that image for every default and/or custom image size that your theme has registered. This means there are multiple versions of your images in your media library.%sChoose the images size/s below that you would like optimized:%s", "ps-medienoptimierung"), "<br /> <br />", "<br />"); ?></span><?php
                    foreach ( $sizes as $size_k => $size ) {
                        //If image sizes array isn't set, mark all checked ( Default Values )
                        if ( false === $image_sizes ) {
                            $checked = true;
                        }else{
                            $checked = is_array( $image_sizes ) ? in_array( $size_k, $image_sizes ) : false;
                        } ?>
                        <label>
                            <input type="checkbox" id="ps-smush-size-<?php echo $size_k; ?>" <?php checked( $checked, true ); ?> name="ps-smush-image_sizes[]" value="<?php echo $size_k; ?>"><?php
                            if( isset( $size['width'], $size['height'] ) ) {
                                echo $size_k . " (" . $size['width'] . "x" . $size['height'] . ") ";
                            } ?>
                        </label><?php
                    } ?>
                </div><?php
            }

        }

        /**
        * Prints Dimensions required for Resizing
        *
        * @param string $name
        * @param string $class_prefix, To avoid element id repetition on settings page
        *
        * @param int $setting_status
         */
        function resize_settings( $name = '', $class_prefix = '' ) {
            if( empty( $name ) || 'resize' != $name ) {
                return;
            }
            global $wpsmush_settings, $wpsmushit_admin;
            //Dimensions
            $resize_sizes = $wpsmush_settings->get_setting( PS_SMUSH_PREFIX . 'resize_sizes', array( 'width' => '', 'height' => '' ) );
            //Get max. dimesnions
            $max_sizes = $wpsmushit_admin->get_max_image_dimensions();

            $setting_status = !empty( $wpsmush_settings->settings['resize'] ) ? $wpsmush_settings->settings['resize'] : 0;

            $prefix = !empty( $class_prefix ) ? $class_prefix : PS_SMUSH_PREFIX;

            //Placeholder width and Height
            $p_width = $p_height = 2048; ?>
            <div class="ps-smush-resize-settings-wrap<?php echo $setting_status ? '' : ' hidden'?>">
                <label class="resize-width-label" aria-labelledby="<?php echo $prefix; ?>label-max-width" for="<?php echo $prefix . $name . '_width'; ?>"><span class = "label-text" id="<?php echo $prefix; ?>label-max-width"><?php esc_html_e("Max width", "ps-medienoptimierung"); ?></span>
                    <input aria-required="true" type="text" aria-describedby="<?php echo $prefix; ?>ps-smush-resize-note" id="<?php echo $prefix . $name . '_width'; ?>" class="ps-smush-resize-input" value="<?php echo isset( $resize_sizes['width'] ) && '' != $resize_sizes['width'] ? $resize_sizes['width'] : $p_width; ?>" name="<?php echo PS_SMUSH_PREFIX . $name . '_width'; ?>" tabindex="0" width=100 /> px
                </label>
                <label class="resize-height-label" aria-labelledby="<?php echo $prefix; ?>label-max-height" for = "<?php echo $prefix . $name . '_height'; ?>"><span class = "label-text" id="<?php echo $prefix; ?>label-max-height"><?php esc_html_e("Max height", "ps-medienoptimierung"); ?></span>
                    <input aria-required="true" type="text" aria-describedby="<?php echo $prefix; ?>ps-smush-resize-note" id="<?php echo $prefix . $name . '_height'; ?>" class="ps-smush-resize-input" value="<?php echo isset( $resize_sizes['height'] ) && '' != $resize_sizes['height'] ? $resize_sizes['height'] : $p_height; ?>" name="<?php echo PS_SMUSH_PREFIX . $name . '_height'; ?>" tabindex="0" width=100 /> px
                </label>
                <div class="ps-smush-resize-note" id="<?php echo $prefix; ?>ps-smush-resize-note"><?php printf( esc_html__("Currently, your largest image size is set at %s%dpx wide %s %dpx high%s.", "ps-medienoptimierung"), '<strong>', $max_sizes['width'], '&times;', $max_sizes['height'], '</strong>' ); ?></div>
                <div class="ps-smush-settings-info ps-smush-size-info ps-smush-update-width hidden" tabindex="0"><?php esc_html_e( "Just to let you know, the width you've entered is less than your largest image and may result in pixelation.", "ps-medienoptimierung" ); ?></div>
                <div class="ps-smush-settings-info ps-smush-size-info ps-smush-update-height hidden" tabindex="0"><?php esc_html_e( "Just to let you know, the height you’ve entered is less than your largest image and may result in pixelation.", "ps-medienoptimierung" ); ?></div>
            </div><?php
        }

        /**
        * Prints Resize, Smush Original, and Backup Settings
        *
        * @param string $name Name of the current setting being processed
        */
        function full_size_options( $name = '', $section = '' ) {
		    if( 'original' != $name && 'resize' != $name ) {
		        return;
		    }
		    global $WpSmush, $wpsmushit_admin, $wpsmush_settings;
		    foreach( $this->setting_group as $name ) {
		        //Do not print Smush Original, Backup for free users
		        if( !$WpSmush->validate_install() ) {
		            if( 'resize' == $name && !empty( $section ) ) {
		             continue;
		            }elseif( empty( $section ) && 'resize' != $name ) {
		                continue;
		            }
		        }
		        $setting_val = $wpsmush_settings->settings[$name];
		        //Turn off settings for free users
                if( !in_array( $name, $wpsmushit_admin->basic_features ) && !$WpSmush->validate_install() ) {
                    $setting_val = 0;
                }
		        ?>
		        <div class="smush-sub-setting-wrapper">
                     <span class="toggle float-l">
                        <input type="checkbox" class="toggle-checkbox"
                               id="<?php echo PS_SMUSH_PREFIX . $name ; ?>" <?php checked( $setting_val, 1, true ); ?>
                               value="1"
                               name="<?php echo PS_SMUSH_PREFIX . $name; ?>" aria-describedby="<?php echo PS_SMUSH_PREFIX . $name . "-desc" ;?>">
                        <label class="toggle-label <?php echo PS_SMUSH_PREFIX . $name ; ?>-label" for="<?php echo PS_SMUSH_PREFIX . $name; ?>" aria-hidden="true"></label>
                    </span>
                    <div class="column-right-content">
                        <label class="inline-label" for="<?php echo PS_SMUSH_PREFIX . $name; ?>">
                            <span class="ps-smush-setting-label"><?php echo $wpsmushit_admin->settings[ $name ]['label']; ?></span><br/>
                        </label>
                        <span class="ps-smush-setting-desc" id="<?php echo PS_SMUSH_PREFIX . $name . "-desc" ;?>"><?php echo $wpsmushit_admin->settings[ $name ]['desc']; ?></span><br/><?php
                        $this->resize_settings( $name );?>
                    </div>
                </div><?php
		    }
        }

        /**
        *
        * @param string $setting_key
        */
        function settings_desc( $setting_key = '' ) {
            if( empty( $setting_key ) || !in_array( $setting_key, array( 'keep_exif', 'png_to_jpg', 's3')) ) {
                return;
            } ?>
            <div class="column-right-content-description" id="<?php echo PS_SMUSH_PREFIX . $setting_key . "-desc"; ?>"><?php
                switch ( $setting_key ) {

                    case 'keep_exif':
                        esc_html_e("Note: This data, called EXIF, adds to the size of the image. While this information might be important to photographers, it’s unnecessary for most users and safe to remove.", "ps-medienoptimierung");
                        break;
                    case 'png_to_jpg':
                        esc_html_e("Note: Any PNGs with transparency will be ignored. Smush will only convert PNGs if it results in a smaller file size. The resulting file will have a new filename and extension (JPEG), and any hard-coded URLs on your site that contain the original PNG filename will need to be updated.", "ps-medienoptimierung");
                        break;
                    case 's3':
                        esc_html_e("Note: For this process to happen automatically you need automatic smushing enabled.", "ps-medienoptimierung");
                        break;
                    case 'default':
                        break;
                } ?>
            </div><?php
        }



        /**
        * Display a stored API message
        * @return null
        */
        function show_api_message() {

            //Do not show message for any other users
            if( !is_network_admin() && !is_super_admin() ) {
                return null;
            }

            $message_icon_class = '';
            $api_message = get_site_option( PS_SMUSH_PREFIX . 'api_message', array() );
            $api_message = current( $api_message );

            //Return if the API message is not set or user dismissed it earlier
            if( empty( $api_message ) || !is_array( $api_message ) || $api_message['status'] != 'show' ) {
                return null;
            }

            $message = !empty( $api_message['message'] ) ? $api_message['message'] : '';
            $message_type = is_array( $api_message ) && !empty( $api_message['type'] ) ? $api_message['type'] : 'info';

            if( 'warning' == $message_type ) {
                $message_icon_class = "icon-fi-warning-alert";
            }else if( 'info' == $message_type ) {
                $message_icon_class = "icon-fi-info";
            }
            echo '<div class="ps-smush-notice ps-smush-api-message '. $message_type .'"><i class="'. $message_icon_class .'"></i>' . $message . '<i class="icon-fi-close"></i></div>';
        }
    }
    global $wpsmush_bulkui;
	$wpsmush_bulkui = new WpSmushBulkUi();
}