<?php
/**
 * Remote File Uploader class — part of Aam WP Migration
 *
 * Copyright (C) 2014-2026 Amarta Dey
 * Author URI: https://amartadey.com/
 * Plugin URI: https://webgraphicshub.com/
 * License: GPL v3 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

class Remote_File_Uploader_AI1WM {

	const MAX_REDIRECTS = 5;

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->init_hooks();
	}

	private function init_hooks() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_rfu_ai1wm_upload_file', array( $this, 'ajax_upload_file' ) );
		add_action( 'wp_ajax_rfu_ai1wm_get_progress', array( $this, 'ajax_get_progress' ) );
	}

	public static function restore_url() {
		return admin_url( 'admin.php?page=ai1wm_backups' );
	}

	public function add_admin_menu() {
		add_submenu_page(
			'ai1wm_export',
			__( 'Remote File Uploader', 'all-in-one-wp-migration' ),
			__( 'Remote Upload', 'all-in-one-wp-migration' ),
			'manage_options',
			'aam-remote-file-uploader',
			array( $this, 'render_admin_page' )
		);
	}

	public function enqueue_admin_scripts( $hook ) {
		if ( strpos( $hook, 'aam-remote-file-uploader' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'aam-rfu-admin',
			RFU_AI1WM_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			RFU_AI1WM_VERSION
		);

		wp_enqueue_script(
			'aam-rfu-admin',
			RFU_AI1WM_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			RFU_AI1WM_VERSION,
			true
		);

		wp_localize_script( 'aam-rfu-admin', 'rfuAi1wm', array(
			'ajax_url'          => admin_url( 'admin-ajax.php' ),
			'nonce'             => wp_create_nonce( 'rfu_ai1wm_nonce' ),
			'ai1wm_restore_url' => self::restore_url(),
			'strings'           => array(
				'uploading' => __( 'Uploading...', 'all-in-one-wp-migration' ),
				'success'   => __( 'File uploaded successfully!', 'all-in-one-wp-migration' ),
				'error'     => __( 'An error occurred. Please try again.', 'all-in-one-wp-migration' ),
			),
		) );
	}

	public function render_admin_page() {
		include RFU_AI1WM_PLUGIN_DIR . 'includes/admin-page.php';
	}

	public function ajax_upload_file() {
		check_ajax_referer( 'rfu_ai1wm_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'all-in-one-wp-migration' ) ) );
		}

		// Drop the previous run's status so the poller can't report a stale "complete".
		delete_transient( self::progress_key() );

		if ( ! function_exists( 'curl_init' ) ) {
			wp_send_json_error( array( 'message' => __( 'cURL is not enabled on this server.', 'all-in-one-wp-migration' ) ) );
		}

		$source_url = isset( $_POST['source_url'] ) ? esc_url_raw( wp_unslash( $_POST['source_url'] ) ) : '';

		if ( empty( $source_url ) || ! filter_var( $source_url, FILTER_VALIDATE_URL ) ) {
			wp_send_json_error( array( 'message' => __( 'Please provide a valid URL.', 'all-in-one-wp-migration' ) ) );
		}

		$valid = $this->validate_source_url( $source_url );
		if ( is_wp_error( $valid ) ) {
			wp_send_json_error( array( 'message' => $valid->get_error_message() ) );
		}

		$valid = $this->validate_extension( $source_url );
		if ( is_wp_error( $valid ) ) {
			wp_send_json_error( array( 'message' => $valid->get_error_message() ) );
		}

		$backup_dir = $this->get_ai1wm_backup_dir();

		if ( ! $backup_dir ) {
			wp_send_json_error( array( 'message' => __( 'Backup directory is missing or not writable.', 'all-in-one-wp-migration' ) ) );
		}

		$filename = sanitize_file_name( basename( (string) parse_url( $source_url, PHP_URL_PATH ) ) );
		$filename = $this->get_unique_filename( $backup_dir, $filename );

		// Keep going if a proxy (Cloudflare, nginx) drops the browser connection; the UI polls progress instead.
		@ignore_user_abort( true );
		@set_time_limit( 0 );

		$destination_path = trailingslashit( $backup_dir ) . $filename;
		$result           = $this->download_remote_file( $source_url, $destination_path, $filename );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'  => __( 'File uploaded successfully!', 'all-in-one-wp-migration' ),
			'filename' => $filename,
			'size'     => filesize( $destination_path ),
		) );
	}

	/**
	 * Reject non-HTTP schemes and private/reserved hosts (SSRF). Runs on every redirect hop.
	 *
	 * @return string|WP_Error Resolved IP, pinned for the actual request so DNS can't be swapped in between.
	 */
	private function validate_source_url( $url ) {
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return new WP_Error( 'rfu_scheme', __( 'Only http/https URLs are allowed.', 'all-in-one-wp-migration' ) );
		}

		$host = parse_url( $url, PHP_URL_HOST );
		if ( empty( $host ) ) {
			return new WP_Error( 'rfu_host', __( 'Could not parse the host from the URL.', 'all-in-one-wp-migration' ) );
		}

		$ip = filter_var( $host, FILTER_VALIDATE_IP ) ? $host : gethostbyname( $host );
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) {
			return new WP_Error( 'rfu_private', __( 'The URL resolves to a private or reserved network address.', 'all-in-one-wp-migration' ) );
		}

		return $ip;
	}

	/**
	 * Only .wpress files show up in Backups, so nothing else is worth downloading.
	 */
	private function validate_extension( $url ) {
		$ext = strtolower( pathinfo( (string) parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		if ( $ext !== 'wpress' ) {
			return new WP_Error( 'rfu_ext', __( 'The URL must point to a .wpress file.', 'all-in-one-wp-migration' ) );
		}

		return true;
	}

	private function get_unique_filename( $directory, $filename ) {
		if ( ! file_exists( trailingslashit( $directory ) . $filename ) ) {
			return $filename;
		}

		$pathinfo  = pathinfo( $filename );
		$basename  = $pathinfo['filename'];
		$extension = isset( $pathinfo['extension'] ) ? '.' . $pathinfo['extension'] : '';

		$counter = 2;
		while ( file_exists( trailingslashit( $directory ) . $basename . '-' . $counter . $extension ) ) {
			$counter++;
		}

		return $basename . '-' . $counter . $extension;
	}

	private function get_ai1wm_backup_dir() {
		if ( ! file_exists( AI1WM_BACKUPS_PATH ) ) {
			wp_mkdir_p( AI1WM_BACKUPS_PATH );
		}

		if ( ! is_writable( AI1WM_BACKUPS_PATH ) ) {
			return false;
		}

		return AI1WM_BACKUPS_PATH;
	}

	private function download_remote_file( $source_url, $destination_path, $filename ) {
		$start_time = microtime( true );
		$this->update_progress( 0, 'starting', $filename, '', $start_time );

		$fp = @fopen( $destination_path, 'w+' );

		if ( ! $fp ) {
			return new WP_Error( 'file_error', __( 'Could not open destination file for writing.', 'all-in-one-wp-migration' ) );
		}

		// Follow redirects by hand so every hop goes through the SSRF check.
		$url = $source_url;
		for ( $hop = 0; $hop <= self::MAX_REDIRECTS; $hop++ ) {
			$ip = $this->validate_source_url( $url );
			if ( is_wp_error( $ip ) ) {
				return $this->fail( $fp, $destination_path, $filename, $ip->get_error_message() );
			}

			ftruncate( $fp, 0 );
			rewind( $fp );

			$result = $this->fetch( $url, $ip, $fp, $filename, $start_time );
			if ( is_wp_error( $result ) ) {
				return $this->fail( $fp, $destination_path, $filename, $result->get_error_message() );
			}

			if ( $result['code'] >= 300 && $result['code'] < 400 && $result['location'] ) {
				$url = $result['location'];
				continue;
			}

			break;
		}

		fclose( $fp );

		if ( $result['code'] !== 200 ) {
			@unlink( $destination_path );

			switch ( $result['code'] ) {
				case 301:
				case 302:
				case 303:
				case 307:
				case 308:
					$error_msg = __( 'Too many redirects.', 'all-in-one-wp-migration' );
					break;
				case 404:
					$error_msg = __( 'Remote file not found (404). Please verify the URL is correct and publicly accessible.', 'all-in-one-wp-migration' );
					break;
				case 403:
					$error_msg = __( 'Access denied (403). The server refused access to the file.', 'all-in-one-wp-migration' );
					break;
				case 401:
					$error_msg = __( 'Authentication required (401). The file requires login credentials.', 'all-in-one-wp-migration' );
					break;
				case 503:
				case 502:
					$error_msg = sprintf( __( 'Remote server temporarily unavailable (%d). Please try again later.', 'all-in-one-wp-migration' ), $result['code'] );
					break;
				default:
					$error_msg = sprintf( __( 'Remote server returned HTTP %d. Please verify the URL is accessible.', 'all-in-one-wp-migration' ), $result['code'] );
			}

			$this->update_progress( 0, 'error', $filename, $error_msg );
			return new WP_Error( 'http_error', $error_msg );
		}

		$this->update_progress( filesize( $destination_path ), 'complete', $filename, '', $start_time );

		return true;
	}

	/**
	 * One request, no automatic redirects, host pinned to the already-validated IP.
	 *
	 * @return array|WP_Error { code, location }
	 */
	private function fetch( $url, $ip, $fp, $filename, $start_time ) {
		$port = parse_url( $url, PHP_URL_PORT );
		if ( ! $port ) {
			$port = stripos( $url, 'https://' ) === 0 ? 443 : 80;
		}

		$ch = curl_init( $url );

		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, false );
		curl_setopt( $ch, CURLOPT_RESOLVE, array( parse_url( $url, PHP_URL_HOST ) . ':' . $port . ':' . $ip ) );
		curl_setopt( $ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 0 );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 30 );
		curl_setopt( $ch, CURLOPT_BUFFERSIZE, 524288 );
		curl_setopt( $ch, CURLOPT_ENCODING, '' );
		curl_setopt( $ch, CURLOPT_USERAGENT, 'aam-wp-migration/' . AI1WM_VERSION );

		$last_update_time = $start_time;
		$downloaded_bytes = 0;
		$write_failed     = false;
		$self             = $this;
		// ponytail: default 20 GB ceiling stops a runaway download from filling the disk. Filter to raise.
		$max_bytes = (int) apply_filters( 'rfu_ai1wm_max_bytes', 20 * 1024 * 1024 * 1024 );

		curl_setopt( $ch, CURLOPT_WRITEFUNCTION, function( $ch_handle, $chunk ) use ( $fp, $filename, $start_time, &$last_update_time, &$downloaded_bytes, &$write_failed, $self, $max_bytes ) {
			$chunk_len = strlen( $chunk );

			// Returning anything but $chunk_len aborts the transfer.
			if ( fwrite( $fp, $chunk ) !== $chunk_len ) {
				$write_failed = true;
				return 0;
			}

			$downloaded_bytes += $chunk_len;
			if ( $downloaded_bytes > $max_bytes ) {
				return 0;
			}

			$current_time = microtime( true );
			if ( $current_time - $last_update_time >= 1 ) {
				$self->update_progress( $downloaded_bytes, 'downloading', $filename, '', $start_time );
				$last_update_time = $current_time;
			}

			return $chunk_len;
		} );

		$result   = curl_exec( $ch );
		$error    = curl_error( $ch );
		$code     = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$location = (string) curl_getinfo( $ch, CURLINFO_REDIRECT_URL );

		curl_close( $ch );

		if ( $write_failed ) {
			return new WP_Error( 'write_error', __( 'Could not write to the backups folder. The disk may be full.', 'all-in-one-wp-migration' ) );
		}

		if ( $downloaded_bytes > $max_bytes ) {
			return new WP_Error( 'too_large', __( 'The file is larger than the allowed download limit.', 'all-in-one-wp-migration' ) );
		}

		if ( ! $result ) {
			return new WP_Error( 'download_error', sprintf( __( 'Download failed: %s', 'all-in-one-wp-migration' ), $error ) );
		}

		return array(
			'code'     => $code,
			'location' => $location,
		);
	}

	private function fail( $fp, $destination_path, $filename, $message ) {
		fclose( $fp );
		@unlink( $destination_path );
		$this->update_progress( 0, 'error', $filename, $message );

		return new WP_Error( 'download_error', $message );
	}

	/**
	 * Progress lives in a per-user transient, not a public file in wp-content.
	 */
	private static function progress_key() {
		return 'rfu_ai1wm_progress_' . get_current_user_id();
	}

	public function update_progress( $downloaded, $status, $filename, $error_message = '', $start_time = 0 ) {
		$elapsed = $start_time > 0 ? microtime( true ) - $start_time : 0;

		$data = array(
			'progress'     => $status === 'complete' ? 100 : 0,
			'downloaded'   => $downloaded,
			'total'        => $status === 'complete' ? $downloaded : 0,
			'status'       => $status,
			'filename'     => $filename,
			'speed'        => $elapsed > 0 ? $downloaded / $elapsed : 0,
			'elapsed_time' => $elapsed,
		);

		if ( $error_message !== '' ) {
			$data['error'] = $error_message;
		}

		set_transient( self::progress_key(), $data, DAY_IN_SECONDS );
	}

	public function ajax_get_progress() {
		check_ajax_referer( 'rfu_ai1wm_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'all-in-one-wp-migration' ) ) );
		}

		$data = get_transient( self::progress_key() );

		wp_send_json_success( is_array( $data ) ? $data : array(
			'progress'   => 0,
			'downloaded' => 0,
			'total'      => 0,
			'status'     => 'idle',
		) );
	}
}
