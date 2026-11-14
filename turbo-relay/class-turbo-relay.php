<?php
/**
 * Turbo Relay Upload — part of Aam WP Migration
 *
 * The browser sends a .wpress file to the relay server in parallel chunks, this site then pulls it
 * from the relay with the Remote Upload downloader, and the relay copy is deleted afterwards.
 * Separate from the default browser upload on the Import page. Nothing to configure.
 *
 * Copyright (C) 2014-2026 Amarta Dey
 * License: GPL v3 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

// Built-in relay. To use your own, define AAM_TURBO_RELAY_URL in wp-config.php.
if ( ! defined( 'AAM_TURBO_RELAY_URL' ) ) {
	define( 'AAM_TURBO_RELAY_URL', 'https://relay.webgraphicshub.com' );
}

class Aam_Turbo_Relay {

	const SLUG = 'aam-turbo-relay';

	public static function init() {
		// 20: the parent menu (ai1wm_export) must exist first, or WordPress builds a broken link for this page.
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'rfu_ai1wm_downloaded', array( __CLASS__, 'delete_from_relay' ) );
	}

	public static function add_admin_menu() {
		add_submenu_page(
			'ai1wm_export',
			__( 'Turbo Relay Upload', 'all-in-one-wp-migration' ),
			__( 'Turbo Relay Upload', 'all-in-one-wp-migration' ),
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	public static function enqueue( $hook ) {
		if ( strpos( $hook, self::SLUG ) === false ) {
			return;
		}

		// Same look as the Remote Upload page.
		wp_enqueue_style( 'aam-rfu-admin', RFU_AI1WM_PLUGIN_URL . 'assets/css/admin.css', array(), RFU_AI1WM_VERSION );
		wp_enqueue_script( 'aam-turbo-relay', AI1WM_URL . '/turbo-relay/turbo.js', array(), AI1WM_VERSION, true );
		wp_localize_script( 'aam-turbo-relay', 'aamTurbo', array(
			'relay'       => untrailingslashit( AAM_TURBO_RELAY_URL ),
			'ajax_url'    => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'rfu_ai1wm_nonce' ),
			'restore_url' => admin_url( 'admin.php?page=ai1wm_backups' ),
		) );
	}

	/**
	 * Runs when the Remote Upload downloader has the whole file. Only acts on the relay's own links.
	 * The random upload id in the link is what authorises the delete.
	 */
	public static function delete_from_relay( $source_url ) {
		$relay = untrailingslashit( AAM_TURBO_RELAY_URL );

		if ( ! preg_match( '#^' . preg_quote( $relay, '#' ) . '/f/([0-9a-f]{32})/#', $source_url, $m ) ) {
			return;
		}

		// A failed delete is not fatal: the relay wipes idle files on its own.
		wp_remote_request( $relay . '/u/' . $m[1], array( 'method' => 'DELETE', 'timeout' => 15 ) );
	}

	public static function render() {
		?>
		<div class="wrap rfu-ai1wm-wrap">
			<h1 style="display:none;"><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<div class="rfu-ai1wm-container">
				<div class="rfu-ai1wm-hero">
					<div class="rfu-ai1wm-hero-icon">🚀</div>
					<div class="rfu-ai1wm-hero-text">
						<h2><?php _e( 'Turbo Relay Upload', 'all-in-one-wp-migration' ); ?></h2>
						<p><?php _e( 'A faster way to get a backup onto this site: it travels through a relay server in parallel streams, lands here at datacenter speed, and the relay copy is wiped.', 'all-in-one-wp-migration' ); ?></p>
					</div>
				</div>

				<div class="rfu-ai1wm-card">
					<div class="rfu-ai1wm-card-header">
						<span class="header-icon">⬆️</span>
						<h2><?php _e( 'Upload a backup from this computer', 'all-in-one-wp-migration' ); ?></h2>
					</div>
					<div class="rfu-ai1wm-card-body">
						<p><input type="file" id="aam-turbo-file" accept=".wpress" /></p>

						<div class="rfu-ai1wm-progress-container" id="aam-turbo-progress" style="display:none;">
							<div class="rfu-ai1wm-progress-bar-wrapper">
								<div class="rfu-ai1wm-progress-bar">
									<div class="rfu-ai1wm-progress-bar-fill" id="aam-turbo-fill"></div>
								</div>
								<div class="rfu-ai1wm-progress-text">
									<span class="rfu-ai1wm-progress-percentage" id="aam-turbo-pct">0%</span>
									<span class="rfu-ai1wm-progress-size" id="aam-turbo-size"></span>
									<span class="rfu-ai1wm-progress-speed" id="aam-turbo-speed"></span>
								</div>
							</div>
							<p class="rfu-ai1wm-progress-status" id="aam-turbo-status"></p>
						</div>

						<div class="rfu-ai1wm-message" id="aam-turbo-message" style="display:none;"></div>

						<p class="submit" style="margin-top:16px; padding-bottom:0;">
							<button type="button" class="button button-primary" id="aam-turbo-start"><?php _e( 'Start Turbo Upload', 'all-in-one-wp-migration' ); ?></button>
						</p>
						<p class="description"><?php _e( 'Choose a .wpress file and press the button. Keep this tab open until step 1 finishes. The relay copy is deleted as soon as this site has the file.', 'all-in-one-wp-migration' ); ?></p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
