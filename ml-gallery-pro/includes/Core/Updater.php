<?php
/**
 * GitHub Updater - notifica o WordPress quando ha nova versao disponivel
 * no repositorio mlopesdesign/ml-gallery-pro.
 *
 * @package MLGalleryPro
 */

namespace MLGP\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Updater {

	private string $plugin_file;
	private string $plugin_slug;
	private string $github_repo;
	private string $current_version;
	private string $transient_key;

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

	public function hooks(): void {
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );
		add_filter( 'upgrader_post_install', [ $this, 'after_install' ], 10, 3 );
	}

	public function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();

		if ( ! $release ) {
			return $transient;
		}

		$remote_version = ltrim( $release['tag_name'] ?? '', 'v' );

		if ( version_compare( $this->current_version, $remote_version, '<' ) ) {
			$transient->response[ $this->plugin_file ] = (object) $this->build_update_payload( $release, $remote_version );
		} else {
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
				'tested'        => '',
				'requires_php'  => '',
				'compatibility' => new \stdClass(),
			];
		}

		return $transient;
	}

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

		$remote_version = ltrim( $release['tag_name'] ?? '', 'v' );

		return (object) [
			'name'           => 'ML Gallery Pro',
			'slug'           => $this->plugin_slug,
			'version'        => $remote_version,
			'author'         => '<a href="https://mlopesdesign.com">ML Lopes Design</a>',
			'homepage'       => 'https://github.com/' . $this->github_repo,
			'download_link'  => $this->find_zip_url( $release ),
			'trunk'          => $this->find_zip_url( $release ),
			'requires'       => '6.0',
			'requires_php'   => '8.0',
			'tested'         => '6.8',
			'last_updated'   => $release['published_at'] ?? '',
			'sections'       => [
				'description' => '<p>Premium WordPress gallery plugin by ML Lopes Design.</p>',
				'changelog'   => '<pre>' . esc_html( $release['body'] ?? '' ) . '</pre>',
			],
			'banners'        => [],
			'icons'          => [],
		];
	}

	public function after_install( $response, array $hook_extra, array $result ) {
		if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_file ) {
			return $response;
		}

		global $wp_filesystem;

		$install_dir = $result['destination'] ?? '';
		$target_dir  = WP_PLUGIN_DIR . '/' . $this->plugin_slug;

		if ( $install_dir && $install_dir !== $target_dir ) {
			$wp_filesystem->move( $install_dir, $target_dir, true );
			$result['destination'] = $target_dir;
		}

		activate_plugin( $this->plugin_file );

		return $result;
	}

	private function get_latest_release(): ?array {
		$cached = get_transient( $this->transient_key );

		if ( false !== $cached ) {
			return ! empty( $cached ) ? $cached : null;
		}

		$url      = 'https://api.github.com/repos/' . $this->github_repo . '/releases/latest';
		$response = wp_remote_get( $url, [
			'timeout'    => 10,
			'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
			'headers'    => [ 'Accept' => 'application/vnd.github.v3+json' ],
		] );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_transient( $this->transient_key, [], 15 * MINUTE_IN_SECONDS );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['tag_name'] ) ) {
			set_transient( $this->transient_key, [], 15 * MINUTE_IN_SECONDS );
			return null;
		}

		set_transient( $this->transient_key, $data, 6 * HOUR_IN_SECONDS );

		return $data;
	}

	private function find_zip_url( array $release ): string {
		foreach ( $release['assets'] ?? [] as $asset ) {
			if ( ! empty( $asset['browser_download_url'] ) &&
				str_ends_with( (string) $asset['name'], '.zip' ) ) {
				return (string) $asset['browser_download_url'];
			}
		}

		return 'https://github.com/' . $this->github_repo
			. '/archive/refs/tags/' . $release['tag_name'] . '.zip';
	}

	private function build_update_payload( array $release, string $remote_version ): array {
		return [
			'id'            => 'github.com/' . $this->github_repo,
			'slug'          => $this->plugin_slug,
			'plugin'        => $this->plugin_file,
			'new_version'   => $remote_version,
			'url'           => 'https://github.com/' . $this->github_repo,
			'package'       => $this->find_zip_url( $release ),
			'icons'         => [],
			'banners'       => [],
			'banners_rtl'   => [],
			'tested'        => '6.8',
			'requires_php'  => '8.0',
			'compatibility' => new \stdClass(),
		];
	}
}
