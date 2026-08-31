<?php
/**
 * Lightweight self-updater that pulls releases from GitHub instead of a
 * commercial update server. Feeds WordPress' native plugin-update pipeline.
 *
 * Copyright (C) 2014-2026 Amarta Dey
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

class Ai1wm_GitHub_Updater {

	const REPO       = 'amartadey/AAM-WP-Migration';
	const CACHE_KEY  = 'ai1wm_github_release';
	const CACHE_TTL  = 43200; // 12 hours

	public function __construct() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 4 );
		add_action( 'in_plugin_update_message-' . AI1WM_PLUGIN_BASENAME, array( $this, 'update_message' ) );
	}

	/**
	 * Fetch the latest GitHub release, cached in a transient.
	 *
	 * @return array|false { version, zip, url, body, published }
	 */
	private function get_release() {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::REPO . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'aam-wp-migration/' . AI1WM_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			set_transient( self::CACHE_KEY, array(), HOUR_IN_SECONDS ); // back off on failure
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['tag_name'] ) ) {
			return false;
		}

		// Prefer an uploaded .zip asset; fall back to GitHub's source zipball.
		$zip = isset( $data['zipball_url'] ) ? $data['zipball_url'] : '';
		if ( ! empty( $data['assets'] ) ) {
			foreach ( $data['assets'] as $asset ) {
				if ( isset( $asset['browser_download_url'] ) && substr( $asset['browser_download_url'], -4 ) === '.zip' ) {
					$zip = $asset['browser_download_url'];
					break;
				}
			}
		}

		$release = array(
			'version'   => ltrim( $data['tag_name'], 'vV' ),
			'zip'       => $zip,
			'url'       => isset( $data['html_url'] ) ? $data['html_url'] : 'https://github.com/' . self::REPO,
			'body'      => isset( $data['body'] ) ? $data['body'] : '',
			'published' => isset( $data['published_at'] ) ? $data['published_at'] : '',
		);

		set_transient( self::CACHE_KEY, $release, self::CACHE_TTL );

		return $release;
	}

	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = (object) array();
		}

		$release = $this->get_release();
		if ( ! $release || ! $release['zip'] ) {
			return $transient;
		}

		$plugin_data = array(
			'slug'        => dirname( AI1WM_PLUGIN_BASENAME ),
			'plugin'      => AI1WM_PLUGIN_BASENAME,
			'new_version' => $release['version'],
			'url'         => $release['url'],
			'package'     => $release['zip'],
		);

		if ( version_compare( $release['version'], AI1WM_VERSION, '>' ) ) {
			$transient->response[ AI1WM_PLUGIN_BASENAME ] = (object) $plugin_data;
		} else {
			$transient->no_update[ AI1WM_PLUGIN_BASENAME ] = (object) $plugin_data;
		}

		return $transient;
	}

	public function plugin_info( $result, $action, $args ) {
		if ( $action !== 'plugin_information' || empty( $args->slug ) || $args->slug !== dirname( AI1WM_PLUGIN_BASENAME ) ) {
			return $result;
		}

		$release = $this->get_release();
		if ( ! $release ) {
			return $result;
		}

		return (object) array(
			'name'          => 'Aam WP Migration',
			'slug'          => dirname( AI1WM_PLUGIN_BASENAME ),
			'version'       => $release['version'],
			'author'        => '<a href="https://amartadey.com/">Amarta Dey</a>',
			'homepage'      => $release['url'],
			'download_link' => $release['zip'],
			'last_updated'  => $release['published'],
			'sections'      => array(
				'changelog' => $release['body'] ? nl2br( esc_html( $release['body'] ) ) : __( 'See GitHub releases for details.', 'all-in-one-wp-migration' ),
			),
		);
	}

	/**
	 * GitHub zipballs extract to "<owner>-<repo>-<hash>/". Rename to the plugin slug
	 * so WordPress overwrites the right folder.
	 */
	public function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== AI1WM_PLUGIN_BASENAME ) {
			return $source;
		}

		global $wp_filesystem;
		$target = trailingslashit( $remote_source ) . dirname( AI1WM_PLUGIN_BASENAME );

		if ( $source === $target ) {
			return $source;
		}

		if ( $wp_filesystem->move( $source, $target, true ) ) {
			return trailingslashit( $target );
		}

		return new WP_Error( 'rename_failed', __( 'Could not rename the update package folder.', 'all-in-one-wp-migration' ) );
	}

	public function update_message() {
		echo ' <strong>' . esc_html__( 'Update served from GitHub.', 'all-in-one-wp-migration' ) . '</strong>';
	}
}

new Ai1wm_GitHub_Updater();
