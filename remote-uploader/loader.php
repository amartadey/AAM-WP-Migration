<?php
/**
 * Remote Uploader — embedded loader for Aam WP Migration
 *
 * Copyright (C) 2014-2026 Amarta Dey
 * License: GPL v3 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

// Constants adapted for embedded use inside aam-wp-migration
if ( ! defined( 'RFU_AI1WM_VERSION' ) ) {
	define( 'RFU_AI1WM_VERSION', '1.0.2' );
}

if ( ! defined( 'RFU_AI1WM_PLUGIN_DIR' ) ) {
	define( 'RFU_AI1WM_PLUGIN_DIR', AI1WM_PATH . DIRECTORY_SEPARATOR . 'remote-uploader' . DIRECTORY_SEPARATOR );
}

if ( ! defined( 'RFU_AI1WM_PLUGIN_URL' ) ) {
	define( 'RFU_AI1WM_PLUGIN_URL', AI1WM_URL . '/remote-uploader/' );
}

// Load the main class (skip the standalone plugin header file)
require_once RFU_AI1WM_PLUGIN_DIR . 'class-remote-file-uploader.php';

// Boot
add_action( 'plugins_loaded', function() {
	Remote_File_Uploader_AI1WM::get_instance();
} );
