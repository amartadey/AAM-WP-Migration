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

	public function is_ai1wm_active() {
		if ( defined( 'AI1WM_PLUGIN_BASENAME' ) || defined( 'AI1WM_BACKUPS_NAME' ) ) {
			return true;
		}
		foreach ( array( 'Ai1wm_Main', 'Ai1wm_Main_Controller', 'Ai1wm' ) as $class ) {
			if ( class_exists( $class, false ) ) {
				return true;
			}
		}
		return function_exists( 'ai1wm_setup' );
	}

	public function add_admin_menu() {
		if ( $this->is_ai1wm_active() ) {
			add_submenu_page(
				'ai1wm_export',
				__( 'Remote File Uploader', 'all-in-one-wp-migration' ),
				__( 'Remote Upload', 'all-in-one-wp-migration' ),
				'manage_options',
				'aam-remote-file-uploader',
				array( $this, 'render_admin_page' )
			);
		} else {
			add_management_page(
				__( 'Remote File Uploader', 'all-in-one-wp-migration' ),
				__( 'Remote File Uploader', 'all-in-one-wp-migration' ),
				'manage_options',
				'aam-remote-file-uploader',
				array( $this, 'render_admin_page' )
			);
		}
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

		$backup_dir   = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'ai1wm-backups';
		$free_bytes   = function_exists( 'disk_free_space' ) ? @disk_free_space( $backup_dir ) : false;
		$ai1wm_active = $this->is_ai1wm_active();

		wp_localize_script( 'aam-rfu-admin', 'rfuAi1wm', array(
			'ajax_url'          => admin_url( 'admin-ajax.php' ),
			'nonce'             => wp_create_nonce( 'rfu_ai1wm_nonce' ),
			'backup_dir'        => $backup_dir,
			'free_disk'         => $free_bytes !== false ? $free_bytes : null,
			'ai1wm_active'      => $ai1wm_active,
			'ai1wm_restore_url' => $ai1wm_active ? admin_url( 'admin.php?page=ai1wm_export' ) : '',
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

		$source_url = isset( $_POST['source_url'] ) ? esc_url_raw( $_POST['source_url'] ) : '';

		if ( empty( $source_url ) ) {
			wp_send_json_error( array( 'message' => __( 'Please provide a valid URL.', 'all-in-one-wp-migration' ) ) );
		}

		if ( ! filter_var( $source_url, FILTER_VALIDATE_URL ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid URL format.', 'all-in-one-wp-migration' ) ) );
		}

		if ( strpos( $source_url, 'http://' ) === 0 ) {
			$source_url = 'https://' . substr( $source_url, 7 );
		}

		$valid = $this->validate_source_url( $source_url );
		if ( is_wp_error( $valid ) ) {
			wp_send_json_error( array( 'message' => $valid->get_error_message() ) );
		}

		$backup_dir = $this->get_ai1wm_backup_dir();

		if ( ! $backup_dir ) {
			wp_send_json_error( array( 'message' => __( 'Backup directory not found. Please ensure Aam WP Migration is active.', 'all-in-one-wp-migration' ) ) );
		}

		$filename = basename( parse_url( $source_url, PHP_URL_PATH ) );
		$filename = sanitize_file_name( $filename );

		if ( empty( $filename ) ) {
			$filename = 'backup-' . time() . '.wpress';
		}

		$filename         = $this->get_unique_filename( $backup_dir, $filename );
		$destination_path = trailingslashit( $backup_dir ) . $filename;

		@ini_set( 'memory_limit', '512M' );
		@ini_set( 'max_execution_time', '0' );
		@set_time_limit( 0 );

		$result = $this->download_remote_file( $source_url, $destination_path, $filename );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'  => __( 'File uploaded successfully!', 'all-in-one-wp-migration' ),
			'filename' => $filename,
			'path'     => $destination_path,
			'size'     => filesize( $destination_path ),
		) );
	}

	/**
	 * Reject non-HTTP schemes, private/reserved hosts (SSRF), and non-archive extensions.
	 *
	 * ponytail: check-then-fetch re-resolves DNS, so a rebinding attacker could still
	 * slip past. Pin the resolved IP with CURLOPT_RESOLVE if that threat model matters.
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

		$ext     = strtolower( pathinfo( (string) parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		$allowed = apply_filters( 'rfu_ai1wm_allowed_extensions', array( 'wpress', 'zip' ) );
		if ( ! in_array( $ext, $allowed, true ) ) {
			return new WP_Error( 'rfu_ext', sprintf( __( 'The URL must point to a file ending in: %s', 'all-in-one-wp-migration' ), implode( ', ', $allowed ) ) );
		}

		return true;
	}

	private function get_unique_filename( $directory, $filename ) {
		$destination_path = trailingslashit( $directory ) . $filename;

		if ( ! file_exists( $destination_path ) ) {
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
		$backup_dir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'ai1wm-backups';

		if ( ! file_exists( $backup_dir ) ) {
			wp_mkdir_p( $backup_dir );
		}

		if ( ! is_writable( $backup_dir ) ) {
			return false;
		}

		return $backup_dir;
	}

	private function download_remote_file( $source_url, $destination_path, $filename = '' ) {
		@set_time_limit( 0 );
		@ini_set( 'max_execution_time', '0' );
		@ini_set( 'memory_limit', '512M' );

		$progress_file = WP_CONTENT_DIR . '/rfu-ai1wm-progress.json';
		$start_time    = microtime( true );
		$this->update_progress( $progress_file, 0, 0, 0, 'starting', $filename, '', $start_time );

		$fp = @fopen( $destination_path, 'w+' );

		if ( ! $fp ) {
			return new WP_Error( 'file_error', __( 'Could not open destination file for writing.', 'all-in-one-wp-migration' ) );
		}

		$ch = curl_init( $source_url );

		if ( ! $ch ) {
			fclose( $fp );
			return new WP_Error( 'curl_error', __( 'Could not initialize cURL.', 'all-in-one-wp-migration' ) );
		}

		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS );
		curl_setopt( $ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 0 );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 30 );
		curl_setopt( $ch, CURLOPT_NOPROGRESS, true );
		curl_setopt( $ch, CURLOPT_BUFFERSIZE, 524288 );
		curl_setopt( $ch, CURLOPT_ENCODING, '' );
		curl_setopt( $ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36' );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
			'Accept-Language: en-US,en;q=0.9',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
		) );

		$last_update_time = $start_time;
		$downloaded_bytes = 0;
		$self             = $this;
		// ponytail: default 20 GB ceiling stops a runaway download from filling the disk. Filter to raise.
		$max_bytes        = (int) apply_filters( 'rfu_ai1wm_max_bytes', 20 * 1024 * 1024 * 1024 );

		curl_setopt( $ch, CURLOPT_WRITEFUNCTION, function( $ch_handle, $chunk ) use ( $fp, $progress_file, $filename, $start_time, &$last_update_time, &$downloaded_bytes, $self, $max_bytes ) {
			$chunk_len         = strlen( $chunk );
			fwrite( $fp, $chunk );
			$downloaded_bytes += $chunk_len;

			if ( $downloaded_bytes > $max_bytes ) {
				return 0; // abort the transfer
			}

			$current_time = microtime( true );

			if ( $current_time - $last_update_time >= 0.5 ) {
				$elapsed_time = max( $current_time - $start_time, 0.001 );
				$speed        = $downloaded_bytes / $elapsed_time;
				$self->update_progress( $progress_file, 0, $downloaded_bytes, 0, 'downloading', $filename, '', $start_time, $speed, 0 );
				$last_update_time = $current_time;
			}

			return $chunk_len;
		} );

		$result    = curl_exec( $ch );
		$error     = curl_error( $ch );
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

		curl_close( $ch );
		fclose( $fp );

		if ( ! $result ) {
			@unlink( $destination_path );
			$this->update_progress( $progress_file, 0, 0, 0, 'error', $filename, sprintf( __( 'Download failed: %s', 'all-in-one-wp-migration' ), $error ) );
			return new WP_Error( 'download_error', sprintf( __( 'Download failed: %s', 'all-in-one-wp-migration' ), $error ) );
		}

		if ( $http_code !== 200 ) {
			@unlink( $destination_path );

			switch ( $http_code ) {
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
					$error_msg = sprintf( __( 'Remote server temporarily unavailable (%d). Please try again later.', 'all-in-one-wp-migration' ), $http_code );
					break;
				default:
					$error_msg = sprintf( __( 'Remote server returned HTTP %d. Please verify the URL is accessible.', 'all-in-one-wp-migration' ), $http_code );
			}

			$this->update_progress( $progress_file, 0, 0, 0, 'error', $filename, $error_msg );
			return new WP_Error( 'http_error', $error_msg );
		}

		$final_size = filesize( $destination_path );
		$this->update_progress( $progress_file, 100, $final_size, $final_size, 'complete', $filename, '', $start_time, 0, 0 );

		return true;
	}

	public function update_progress( $progress_file, $progress, $downloaded, $total, $status, $filename = '', $error_message = '', $start_time = 0, $speed = 0, $eta = 0 ) {
		$data = array(
			'progress'     => $progress,
			'downloaded'   => $downloaded,
			'total'        => $total,
			'status'       => $status,
			'filename'     => $filename,
			'timestamp'    => time(),
			'speed'        => $speed,
			'eta'          => $eta,
			'start_time'   => $start_time,
			'elapsed_time' => $start_time > 0 ? microtime( true ) - $start_time : 0,
		);

		if ( ! empty( $error_message ) ) {
			$data['error'] = $error_message;
		}

		file_put_contents( $progress_file, json_encode( $data ) );
	}

	public function format_bytes( $bytes, $decimals = 2 ) {
		if ( $bytes === 0 ) {
			return '0 Bytes';
		}
		$k     = 1024;
		$sizes = array( 'Bytes', 'KB', 'MB', 'GB', 'TB' );
		$i     = floor( log( $bytes ) / log( $k ) );
		return round( $bytes / pow( $k, $i ), $decimals ) . ' ' . $sizes[ $i ];
	}

	public function ajax_get_progress() {
		check_ajax_referer( 'rfu_ai1wm_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'all-in-one-wp-migration' ) ) );
		}

		$progress_file = WP_CONTENT_DIR . '/rfu-ai1wm-progress.json';

		if ( file_exists( $progress_file ) ) {
			$data = json_decode( file_get_contents( $progress_file ), true );
			wp_send_json_success( $data );
		} else {
			wp_send_json_success( array(
				'progress'   => 0,
				'downloaded' => 0,
				'total'      => 0,
				'status'     => 'idle',
			) );
		}
	}
}
