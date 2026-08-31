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

class Ai1wm_Updater {

	/**
	 * Retrieve plugin installer pages from WordPress Plugins API.
	 *
	 * @param  mixed        $result
	 * @param  string       $action
	 * @param  array|object $args
	 * @return mixed
	 */
	public static function plugins_api( $result, $action = null, $args = null ) {
		if ( empty( $args->slug ) ) {
			return $result;
		}

		// Get extensions
		$extensions = Ai1wm_Extensions::get();

		// View details page
		if ( isset( $extensions[ $args->slug ] ) && $action === 'plugin_information' ) {
			$updater = get_option( AI1WM_UPDATER, array() );

			// Plugin details
			if ( isset( $updater[ $args->slug ] ) ) {
				return (object) $updater[ $args->slug ];
			}
		}

		return $result;
	}

	/**
	 * Update WordPress plugin list page.
	 *
	 * @param  object $transient
	 * @return object
	 */
	public static function update_plugins( $transient ) {
		global $wp_version;

		// Creating default object from empty value
		if ( ! is_object( $transient ) ) {
			$transient = (object) array();
		}

		// Get extensions
		$extensions = Ai1wm_Extensions::get();

		// Get current updates
		$updater = get_option( AI1WM_UPDATER, array() );

		// Get extension updates
		foreach ( $updater as $slug => $update ) {
			if ( isset( $extensions[ $slug ], $update['version'], $update['homepage'], $update['download_link'], $update['icons'] ) ) {
				if ( ( $purchase_id = get_option( $extensions[ $slug ]['key'] ) ) ) {

					// Get download URL
					if ( $slug === 'all-in-one-wp-migration-file-extension' ) {
						$download_url = add_query_arg( array( 'siteurl' => get_site_url() ), sprintf( '%s', $update['download_link'] ) );
					} else {
						$download_url = add_query_arg( array( 'siteurl' => get_site_url() ), sprintf( '%s/%s', $update['download_link'], $purchase_id ) );
					}

					// Set plugin details
					$plugin_details = (object) array(
						'slug'        => $slug,
						'new_version' => $update['version'],
						'url'         => $update['homepage'],
						'plugin'      => $extensions[ $slug ]['basename'],
						'package'     => $download_url,
						'tested'      => $wp_version,
						'icons'       => $update['icons'],
					);

					// Enable auto updates
					if ( version_compare( $extensions[ $slug ]['version'], $update['version'], '<' ) ) {
						$transient->response[ $extensions[ $slug ]['basename'] ] = $plugin_details;
					} else {
						$transient->no_update[ $extensions[ $slug ]['basename'] ] = $plugin_details;
					}
				}
			}
		}

		return $transient;
	}

	/**
	 * Check for extension updates — disabled, no remote update server.
	 *
	 * @return boolean
	 */
	public static function check_for_updates() {
		return true;
	}

	/**
	 * Add "Check for updates" link
	 *
	 * @param  array  $plugin_meta An array of the plugin's metadata, including the version, author, author URI, and plugin URI
	 * @param  string $plugin_file Path to the plugin file relative to the plugins directory
	 * @return array
	 */
	public static function plugin_row_meta( $plugin_meta, $plugin_file ) {
		$modal_index = 0;

		// Get current updates
		$updater = get_option( AI1WM_UPDATER, array() );

		// Add link for each extension
		foreach ( Ai1wm_Extensions::get() as $slug => $extension ) {
			$modal_index++;

			// Get plugin details
			if ( $plugin_file === $extension['basename'] ) {

				// Get updater URL
				$updater_url = add_query_arg( array( 'ai1wm_check_for_updates' => 1, 'ai1wm_nonce' => wp_create_nonce( 'ai1wm_check_for_updates' ) ), network_admin_url( 'plugins.php' ) );

				// Check purchase ID
				if ( get_option( $extension['key'] ) ) {
					$plugin_meta[] = Ai1wm_Template::get_content( 'updater/check', array( 'url' => $updater_url ) );
				} else {
					$plugin_meta[] = Ai1wm_Template::get_content( 'updater/modal', array( 'url' => $updater_url, 'modal' => $modal_index ) );
				}
			}
		}

		return $plugin_meta;
	}
}
