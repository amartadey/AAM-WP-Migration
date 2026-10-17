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
	delete_option( 'ai1wmue_plugin_key' );
	delete_option( 'ai1wmve_plugin_key' );

	// Delete scheduled backups and their cron events
	delete_option( 'ai1wmve_schedule_events' );
	wp_unschedule_hook( 'ai1wmve_schedule_event' );

	// Delete GitHub updater cache and options left by older versions
	delete_transient( 'ai1wm_github_release' );
	delete_option( 'ai1wm_updater' );
	delete_option( 'ai1wm_last_check_for_updates' );

	// Delete remote uploader progress (per-user transients)
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_rfu_ai1wm_progress_' ) . '%', $wpdb->esc_like( '_transient_timeout_rfu_ai1wm_progress_' ) . '%' ) );

	// Remove progress file written by older remote uploader versions
	$progress_file = WP_CONTENT_DIR . '/rfu-ai1wm-progress.json';
	if ( file_exists( $progress_file ) ) {
		@unlink( $progress_file );
	}
}
