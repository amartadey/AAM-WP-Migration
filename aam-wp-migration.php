<?php
/**
 * Plugin Name: Aam WP Migration
 * Plugin URI: https://github.com/amartadey/AAM-WP-Migration
 * Description: Migration tool for all your blog data with unlimited file size support. Import or Export your blog content with a single click — no file size restrictions.
 * Author: Amarta Dey
 * Author URI: https://amartadey.com/
 * Version: 7.87
 * Update URI: https://github.com/amartadey/AAM-WP-Migration
 * Text Domain: all-in-one-wp-migration
 * Domain Path: /languages
 * Network: True
 *
 * Copyright (C) 2014-2026 Amarta Dey
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

// Same class names as the original All-in-One WP Migration: loading both is a fatal error
$ai1wm_original = 'all-in-one-wp-migration/all-in-one-wp-migration.php';
if ( defined( 'AI1WM_PLUGIN_NAME' )
	|| in_array( $ai1wm_original, (array) get_option( 'active_plugins', array() ), true )
	|| ( is_multisite() && array_key_exists( $ai1wm_original, (array) get_site_option( 'active_sitewide_plugins', array() ) ) ) ) {
	add_action( is_multisite() ? 'network_admin_notices' : 'admin_notices', function () {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Aam WP Migration is disabled: deactivate the original All-in-One WP Migration plugin first. Both cannot run at the same time.', 'all-in-one-wp-migration' ) . '</p></div>';
	} );
	return;
}
unset( $ai1wm_original );

// Check SSL Mode
if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && ( $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) ) {
	$_SERVER['HTTPS'] = 'on';
}

// =========================
// = Base Plugin Bootstrap =
// =========================

// Plugin Basename
define( 'AI1WM_PLUGIN_BASENAME', basename( dirname( __FILE__ ) ) . '/' . basename( __FILE__ ) );

// Plugin Path
define( 'AI1WM_PATH', dirname( __FILE__ ) );

// Plugin URL
define( 'AI1WM_URL', plugins_url( '', AI1WM_PLUGIN_BASENAME ) );

// Plugin Storage URL
define( 'AI1WM_STORAGE_URL', plugins_url( 'storage', AI1WM_PLUGIN_BASENAME ) );

// Include base plugin files
require_once AI1WM_PATH . DIRECTORY_SEPARATOR . 'constants.php';
require_once AI1WM_PATH . DIRECTORY_SEPARATOR . 'deprecated.php';
require_once AI1WM_PATH . DIRECTORY_SEPARATOR . 'functions.php';
require_once AI1WM_PATH . DIRECTORY_SEPARATOR . 'exceptions.php';
require_once AI1WM_PATH . DIRECTORY_SEPARATOR . 'loader.php';
require_once AI1WM_PATH . DIRECTORY_SEPARATOR . 'updater.php';

// =============================================
// = Unlimited Extension Bootstrap            =
// = (single-site only — use Multisite        =
// = Extension on WordPress Multisite setups) =
// =============================================
if ( ! is_multisite() ) {

	// Extension Basename (same plugin file as base)
	define( 'AI1WMUE_PLUGIN_BASENAME', AI1WM_PLUGIN_BASENAME );

	// Extension Path  (lives in the extension/ subfolder)
	define( 'AI1WMUE_PATH', AI1WM_PATH . DIRECTORY_SEPARATOR . 'extension' );

	// Extension URL
	define( 'AI1WMUE_URL', plugins_url( 'extension', AI1WM_PLUGIN_BASENAME ) );

	// Include extension files
	require_once AI1WMUE_PATH . DIRECTORY_SEPARATOR . 'constants.php';
	require_once AI1WMUE_PATH . DIRECTORY_SEPARATOR . 'functions.php';
	require_once AI1WMUE_PATH . DIRECTORY_SEPARATOR . 'loader.php';
}

// =====================================================================
// = Initialization                                                    =
// = Base controller handles core migration; extension controller adds =
// = unlimited uploads, retention, schedules, reset hub, and settings. =
// =====================================================================
new Ai1wm_Main_Controller();

if ( ! is_multisite() ) {
	new Ai1wmue_Main_Controller( 'AI1WMUE', 'file' );
}

// =======================================
// = Remote Uploader Integration        =
// = (single-site only, like the menus) =
// =======================================
if ( ! is_multisite() ) {
	require_once AI1WM_PATH . DIRECTORY_SEPARATOR . 'remote-uploader' . DIRECTORY_SEPARATOR . 'loader.php';
}
