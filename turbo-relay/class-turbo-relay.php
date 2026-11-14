<?php
/**
 * Turbo Relay Upload — part of Aam WP Migration
 *
 * The browser sends a .wpress file to a relay server in parallel chunks, this site then pulls it
 * from the relay with the Remote Upload downloader, and the relay copy is deleted afterwards.
 * Separate from the default browser upload on the Import page.
 *
 * Copyright (C) 2014-2026 Amarta Dey
 * License: GPL v3 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

class Aam_Turbo_Relay {

	const OPTION = 'aam_turbo_relay';
	const SLUG   = 'aam-turbo-relay';

	public static function init() {
		// 20: the parent menu (ai1wm_export) must exist first, or WordPress builds a broken link for this page.
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_aam_turbo_ticket', array( __CLASS__, 'ajax_ticket' ) );
		add_action( 'rfu_ai1wm_downloaded', array( __CLASS__, 'delete_from_relay' ) );
	}

	private static function settings() {
		return wp_parse_args( get_option( self::OPTION, array() ), array( 'url' => '', 'token' => '' ) );
	}

	private static function configured() {
		$s = self::settings();
		return $s['url'] !== '' && $s['token'] !== '';
	}

	/**
	 * Short-lived proof that this site allowed one upload id. The relay holds the same token and
	 * checks the signature, so the token itself never has to be sent to a browser.
	 */
	public static function ticket( $id, $token, $expiry ) {
		return $expiry . '.' . hash_hmac( 'sha256', $id . '.' . $expiry, $token );
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
			'ajax_url'    => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'rfu_ai1wm_nonce' ),
			'restore_url' => admin_url( 'admin.php?page=ai1wm_backups' ),
		) );
	}

	public static function ajax_ticket() {
		check_ajax_referer( 'rfu_ai1wm_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'all-in-one-wp-migration' ) ) );
		}

		if ( ! self::configured() ) {
			wp_send_json_error( array( 'message' => __( 'Save the relay address and token first.', 'all-in-one-wp-migration' ) ) );
		}

		$s  = self::settings();
		$id = bin2hex( random_bytes( 16 ) );

		wp_send_json_success( array(
			'relay'  => $s['url'],
			'id'     => $id,
			'ticket' => self::ticket( $id, $s['token'], time() + DAY_IN_SECONDS ),
		) );
	}

	/**
	 * Runs when the Remote Upload downloader has the whole file. Only acts on our own relay's links.
	 */
	public static function delete_from_relay( $source_url ) {
		$s = self::settings();

		if ( ! self::configured() || ! preg_match( '#^' . preg_quote( $s['url'], '#' ) . '/f/([0-9a-f]{32})/#', $source_url, $m ) ) {
			return;
		}

		// A failed delete is not fatal: the relay wipes anything older than 24 hours on its own.
		wp_remote_request( $s['url'] . '/u/' . $m[1], array(
			'method'  => 'DELETE',
			'timeout' => 15,
			'headers' => array( 'X-Relay-Ticket' => self::ticket( $m[1], $s['token'], time() + 300 ) ),
		) );
	}

	private static function save_settings() {
		if ( ! isset( $_POST['aam_turbo_save'] ) || ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		check_admin_referer( 'aam_turbo_save' );

		$s   = self::settings();
		$url = untrailingslashit( esc_url_raw( trim( wp_unslash( $_POST['relay_url'] ) ), array( 'https' ) ) );

		if ( $url === '' ) {
			return __( 'The relay address must start with https://', 'all-in-one-wp-migration' );
		}

		// Blank token field keeps the saved one, so the page never has to print it back.
		$token = trim( wp_unslash( $_POST['relay_token'] ) );
		update_option( self::OPTION, array( 'url' => $url, 'token' => $token !== '' ? $token : $s['token'] ), false );

		return '';
	}

	public static function render() {
		$error = self::save_settings();
		$s     = self::settings();
		?>
		<div class="wrap rfu-ai1wm-wrap">
			<h1 style="display:none;"><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php if ( $error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>

			<div class="rfu-ai1wm-container">
				<div class="rfu-ai1wm-hero">
					<div class="rfu-ai1wm-hero-icon">🚀</div>
					<div class="rfu-ai1wm-hero-text">
						<h2><?php _e( 'Turbo Relay Upload', 'all-in-one-wp-migration' ); ?></h2>
						<p><?php _e( 'Sends your backup to your own relay server in parallel streams, pulls it onto this site at datacenter speed, then wipes the relay copy.', 'all-in-one-wp-migration' ); ?></p>
					</div>
				</div>

				<?php if ( self::configured() ) : ?>
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
						<p class="description"><?php _e( 'Keep this tab open until step 1 finishes. The relay copy is deleted as soon as this site has the file.', 'all-in-one-wp-migration' ); ?></p>
					</div>
				</div>
				<?php endif; ?>

				<div class="rfu-ai1wm-card">
					<div class="rfu-ai1wm-card-header">
						<span class="header-icon">⚙️</span>
						<h2><?php _e( 'Relay server', 'all-in-one-wp-migration' ); ?></h2>
					</div>
					<div class="rfu-ai1wm-card-body">
						<form method="post">
							<?php wp_nonce_field( 'aam_turbo_save' ); ?>
							<table class="form-table" style="margin-top:0;">
								<tr>
									<th scope="row"><label for="relay_url"><?php _e( 'Relay address', 'all-in-one-wp-migration' ); ?></label></th>
									<td><input type="url" name="relay_url" id="relay_url" class="regular-text" value="<?php echo esc_attr( $s['url'] ); ?>" placeholder="https://relay.example.com" required /></td>
								</tr>
								<tr>
									<th scope="row"><label for="relay_token"><?php _e( 'Relay token', 'all-in-one-wp-migration' ); ?></label></th>
									<td>
										<input type="password" name="relay_token" id="relay_token" class="regular-text" autocomplete="new-password" placeholder="<?php echo $s['token'] !== '' ? esc_attr__( 'Saved. Leave blank to keep it.', 'all-in-one-wp-migration' ) : ''; ?>" />
										<p class="description"><?php _e( 'Stays on this server. It is never sent to the browser.', 'all-in-one-wp-migration' ); ?></p>
									</td>
								</tr>
							</table>
							<p class="submit" style="padding-bottom:0;"><button type="submit" name="aam_turbo_save" value="1" class="button"><?php _e( 'Save relay settings', 'all-in-one-wp-migration' ); ?></button></p>
						</form>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
