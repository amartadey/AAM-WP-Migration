<?php
/**
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

// Include plugin bootstrap to have all classes available
require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'aam-wp-migration.php';

if ( defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	global $wpdb, $wp_filesystem;

	// Clear base plugin cron jobs
	if ( Ai1wm_Cron::exists( 'ai1wm_storage_cleanup' ) ) {
		Ai1wm_Cron::clear( 'ai1wm_storage_cleanup' );
	}

	if ( Ai1wm_Cron::exists( 'ai1wm_cleanup_cron' ) ) {
		Ai1wm_Cron::clear( 'ai1wm_cleanup_cron' );
	}

	// Delete base plugin options
	delete_option( AI1WM_STATUS );
	delete_option( AI1WM_SECRET_KEY );
	delete_option( AI1WM_AUTH_USER );
	delete_option( AI1WM_AUTH_PASSWORD );
	delete_option( AI1WM_AUTH_HEADER );
	delete_option( AI1WM_BACKUPS_PATH_OPTION );

	// Delete unlimited extension options
	delete_option( 'ai1wmue_backups' );
	delete_option( 'ai1wmue_total' );
	delete_option( 'ai1wmue_days' );

	// Remove remote uploader progress file
	$progress_file = WP_CONTENT_DIR . '/rfu-ai1wm-progress.json';
	if ( file_exists( $progress_file ) ) {
		@unlink( $progress_file );
	}
}
