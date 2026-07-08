<?php
/**
 * GitHub updater for ML Gallery Pro.
 *
 * Detects stable GitHub releases, exposes them to the native WordPress updater
 * and normalizes the extracted package when GitHub's source archive is used.
 *
 * @package MLGalleryPro
 */

namespace MLGP\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Updater {

	/** Positive release cache lifetime. */
	private const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

	/** Negative cache lifetime after a failed GitHub request. */
	private const ERROR_CACHE_TTL = 5 * MINUTE_IN_SECONDS;

	/** Path relative to the plugins directory. */
	private string $plugin_file;

	/** Technical plugin slug. */
	private string $plugin_slug;

	/** GitHub repository in owner/repository format. */
	private string $github_repo;

	/** Currently installed version. */
	private string $current_version;

	/** Site transient key used for the GitHub release cache. */
	private string $transient_key;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file     plugin_basename( MLGP_FILE ).
	 * @param string $plugin_slug     Technical plugin slug.
	 * @param string $github_repo     GitHub repository in owner/repository format.
	 * @param string $current_version Installed plugin version.
	 */
	public function __construct(
		string $plugin_file,
		string $plugin_slug,
		string $github_repo,
		string $current_version
	) {
		$this->plugin_file     = $plugin_file;
		$this->plugin_slug     = $plugin_slug;
		$this->github_repo     = $github_repo;
		$this->current_version = $current_version;
		$this->transient_key   = 'mlgp_updater_' . md5( $plugin_slug );
	}

	/**
	 * Registers updater hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );
		add_filter( 'upgrader_source_selection', [ $this, 'select_package_source' ], 10, 4 );
		add_filter( 'upgrader_post_install', [ $this, 'after_install' ], 10, 3 );

		// "Check again" in WordPress deletes this site transient. Clear our own
		// release cache at the same time so a newly published release is fetched.
		add_action( 'delete_site_transient_update_plugins', [ $this, 'clear_release_cache' ] );
	}

	/**
	 * Injects update data into WordPress' plugin update transient.
	 *
	 * @param mixed $transient WordPress update_plugins transient.
	 * @return mixed
	 */
	public function check_update( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();

		if ( ! $release ) {
			return $transient;
		}

		$remote_version = $this->normalize_version( (string) ( $release['tag_name'] ?? '' ) );

		if ( '' === $remote_version ) {
			return $transient;
		}

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = [];
		}

		if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
			$transient->no_update = [];
		}

		if ( version_compare( $this->current_version, $remote_version, '<' ) ) {
			$transient->response[ $this->plugin_file ] = (object) $this->build_update_payload( $release, $remote_version );
			unset( $transient->no_update[ $this->plugin_file ] );
		} else {
			unset( $transient->response[ $this->plugin_file ] );

			$transient->no_update[ $this->plugin_file ] = (object) [
				'id'            => 'github.com/' . $this->github_repo,
				'slug'          => $this->plugin_slug,
				'plugin'        => $this->plugin_file,
				'new_version'   => $this->current_version,
				'url'           => 'https://github.com/' . $this->github_repo,
				'package'       => '',
				'icons'         => [],
				'banners'       => [],
				'banners_rtl'   => [],
				'tested'        => '6.8',
				'requires'      => '6.0',
				'requires_php'  => '7.4',
				'compatibility' => new \stdClass(),
			];
		}

		return $transient;
	}

	/**
	 * Supplies data for WordPress' "View version details" modal.
	 *
	 * @param mixed  $result Current API result.
	 * @param string $action Requested API action.
	 * @param object $args   API arguments.
	 * @return mixed
	 */
	public function plugin_info( $result, string $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( empty( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $result;
		}

		$release = $this->get_latest_release();

		if ( ! $release ) {
			return $result;
		}

		$remote_version = $this->normalize_version( (string) ( $release['tag_name'] ?? '' ) );

		if ( '' === $remote_version ) {
			return $result;
		}

		$download_url = $this->find_zip_url( $release, $remote_version );

		return (object) [
			'name'           => 'ML Gallery Pro',
			'slug'           => $this->plugin_slug,
			'version'        => $remote_version,
			'author'         => '<a href="https://mlopesdesign.com">Mlopesdesign</a>',
			'author_profile' => 'https://mlopesdesign.com',
			'homepage'       => 'https://github.com/' . $this->github_repo,
			'download_link'  => $download_url,
			'trunk'          => $download_url,
			'requires'       => '6.0',
			'requires_php'   => '7.4',
			'tested'         => '6.8',
			'last_updated'   => $release['published_at'] ?? '',
			'sections'       => [
				'description' => '<p>Professional WordPress gallery plugin by Mlopesdesign.</p>',
				'changelog'   => '<pre>' . esc_html( (string) ( $release['body'] ?? '' ) ) . '</pre>',
			],
			'banners'        => [],
			'icons'          => [],
		];
	}

	/**
	 * Selects the real plugin folder when GitHub's automatic source archive is used.
	 *
	 * The official release asset already contains ml-gallery-pro/ at its root. The
	 * GitHub source archive, however, may contain that folder one level deeper.
	 *
	 * @param string|\WP_Error $source        Extracted package source.
	 * @param string           $remote_source Temporary extraction directory.
	 * @param \WP_Upgrader     $upgrader      Upgrader instance.
	 * @param array            $hook_extra    Upgrader hook data.
	 * @return string|\WP_Error
	 */
	public function select_package_source( $source, string $remote_source, $upgrader, array $hook_extra ) {
		unset( $remote_source, $upgrader );

		if ( is_wp_error( $source ) ) {
			return $source;
		}

		if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_file ) {
			return $source;
		}

		global $wp_filesystem;

		if ( ! is_object( $wp_filesystem ) ) {
			return $source;
		}

		$source_dir = trailingslashit( (string) $source );
		$main_file  = $source_dir . basename( $this->plugin_file );

		if ( $wp_filesystem->is_file( $main_file ) ) {
			return $source_dir;
		}

		$inner_dir  = trailingslashit( $source_dir . $this->plugin_slug );
		$inner_main = $inner_dir . basename( $this->plugin_file );

		if ( $wp_filesystem->is_dir( $inner_dir ) && $wp_filesystem->is_file( $inner_main ) ) {
			return $inner_dir;
		}

		return $source;
	}

	/**
	 * Finalizes this plugin's update without changing activation state.
	 *
	 * @param bool|\WP_Error $response   Installation response.
	 * @param array          $hook_extra Upgrader hook data.
	 * @param array          $result     Installation result.
	 * @return bool|\WP_Error
	 */
	public function after_install( $response, array $hook_extra, array $result ) {
		unset( $result );

		if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_file ) {
			return $response;
		}

		$this->clear_release_cache();

		return $response;
	}

	/**
	 * Clears both the current site cache and the legacy local transient.
	 *
	 * @param string $transient Deleted WordPress transient name, when called by hook.
	 * @return void
	 */
	public function clear_release_cache( string $transient = '' ): void {
		unset( $transient );

		delete_site_transient( $this->transient_key );
		delete_transient( $this->transient_key );
	}

	/**
	 * Retrieves the latest stable GitHub release.
	 *
	 * @return array|null Release data or null on failure.
	 */
	private function get_latest_release(): ?array {
		$cached = get_site_transient( $this->transient_key );

		if ( false !== $cached ) {
			return ! empty( $cached ) && is_array( $cached ) ? $cached : null;
		}

		$url = 'https://api.github.com/repos/' . $this->github_repo . '/releases/latest';

		$response = wp_remote_get(
			$url,
			[
				'timeout'    => 15,
				'user-agent' => 'ML-Gallery-Pro/' . $this->current_version . '; WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
				'headers'    => [
					'Accept'               => 'application/vnd.github+json',
					'X-GitHub-Api-Version' => '2022-11-28',
				],
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_site_transient( $this->transient_key, [], self::ERROR_CACHE_TTL );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || '' === $this->normalize_version( (string) ( $data['tag_name'] ?? '' ) ) ) {
			set_site_transient( $this->transient_key, [], self::ERROR_CACHE_TTL );
			return null;
		}

		set_site_transient( $this->transient_key, $data, self::CACHE_TTL );

		return $data;
	}

	/**
	 * Finds the release ZIP, prioritizing the official versioned asset.
	 *
	 * @param array  $release        GitHub release data.
	 * @param string $remote_version Normalized remote version.
	 * @return string Download URL.
	 */
	private function find_zip_url( array $release, string $remote_version ): string {
		$assets = [];

		foreach ( $release['assets'] ?? [] as $asset ) {
			$name = (string) ( $asset['name'] ?? '' );
			$url  = (string) ( $asset['browser_download_url'] ?? '' );

			if ( '' === $name || '' === $url || '.zip' !== strtolower( substr( $name, -4 ) ) ) {
				continue;
			}

			$assets[] = [
				'name'       => $name,
				'name_lower' => strtolower( $name ),
				'url'        => $url,
			];
		}

		$version_underscored = str_replace( '.', '_', $remote_version );
		$expected_names      = [
			strtolower( 'ML-Gallery-Pro-v' . $version_underscored . '.zip' ),
			strtolower( 'ml-gallery-pro-v' . $remote_version . '.zip' ),
			strtolower( 'ml-gallery-pro-' . $remote_version . '.zip' ),
		];

		foreach ( $expected_names as $expected_name ) {
			foreach ( $assets as $asset ) {
				if ( $asset['name_lower'] === $expected_name ) {
					return $asset['url'];
				}
			}
		}

		$version_tokens = [ strtolower( $remote_version ), strtolower( $version_underscored ) ];

		foreach ( $assets as $asset ) {
			if ( false === strpos( $asset['name_lower'], $this->plugin_slug ) ) {
				continue;
			}

			foreach ( $version_tokens as $version_token ) {
				if ( false !== strpos( $asset['name_lower'], $version_token ) ) {
					return $asset['url'];
				}
			}
		}

		if ( ! empty( $assets ) ) {
			return $assets[0]['url'];
		}

		$tag_name = rawurlencode( (string) ( $release['tag_name'] ?? ( 'v' . $remote_version ) ) );

		return 'https://github.com/' . $this->github_repo . '/archive/refs/tags/' . $tag_name . '.zip';
	}

	/**
	 * Builds WordPress' update payload.
	 *
	 * @param array  $release        GitHub release data.
	 * @param string $remote_version Normalized remote version.
	 * @return array
	 */
	private function build_update_payload( array $release, string $remote_version ): array {
		return [
			'id'            => 'github.com/' . $this->github_repo,
			'slug'          => $this->plugin_slug,
			'plugin'        => $this->plugin_file,
			'new_version'   => $remote_version,
			'url'           => 'https://github.com/' . $this->github_repo,
			'package'       => $this->find_zip_url( $release, $remote_version ),
			'icons'         => [],
			'banners'       => [],
			'banners_rtl'   => [],
			'tested'        => '6.8',
			'requires'      => '6.0',
			'requires_php'  => '7.4',
			'compatibility' => new \stdClass(),
		];
	}

	/**
	 * Normalizes tags such as v0.26.13 into 0.26.13.
	 *
	 * @param string $tag_name GitHub tag.
	 * @return string
	 */
	private function normalize_version( string $tag_name ): string {
		$version = preg_replace( '/^v/i', '', trim( $tag_name ) );

		if ( ! is_string( $version ) || ! preg_match( '/^\d+(?:\.\d+){1,3}(?:[-+][0-9A-Za-z.-]+)?$/', $version ) ) {
			return '';
		}

		return $version;
	}
}
