<?php
/**
 * GitHub updater for ML Gallery Pro.
 *
 * Detects stable GitHub releases, exposes them to the native WordPress updater
 * and normalizes the extracted package when GitHub's source archive is used.
 *
 * 0.27.1: hospedagem compartilhada divide o IP com muitos sites e a API do GitHub
 * (60 consultas/hora sem login) responde 403. Antes, nesse caso o plugin sumia do
 * transient do WordPress (sem coluna de atualizacao e sem deteccao de versao nova).
 * Agora: API -> pagina publica da release (github.com, fora do limite da API) ->
 * ultima release boa guardada; e o plugin sempre fica registrado em no_update.
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

	/** Option com a ultima release valida (usada quando o GitHub nao responde). */
	public const LAST_RELEASE_OPTION = 'mlgp_updater_last_release';

	/** Validade da ultima release guardada quando o GitHub segue fora do ar. */
	private const LAST_RELEASE_MAX_AGE = 7 * DAY_IN_SECONDS;

	/** Tempo maximo da consulta a API e de cada HEAD na pagina publica (soma baixa: hospedagem com limite de 30 s). */
	private const API_TIMEOUT  = 8;
	private const HEAD_TIMEOUT = 5;

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

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = [];
		}

		if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
			$transient->no_update = [];
		}

		$release        = $this->get_latest_release();
		$remote_version = $release ? $this->normalize_version( (string) ( $release['tag_name'] ?? '' ) ) : '';

		// Sem o ZIP oficial da versao na release, nao oferece atualizacao (registra no_update).
		if ( '' !== $remote_version && version_compare( $this->current_version, $remote_version, '<' ) && '' !== $this->find_zip_url( $release, $remote_version ) ) {
			$transient->response[ $this->plugin_file ] = (object) $this->build_update_payload( $release, $remote_version );
			unset( $transient->no_update[ $this->plugin_file ] );

			return $transient;
		}

		// GitHub fora do ar ou sem versao nova: o plugin continua registrado, para o
		// WordPress mostrar a coluna de atualizacao e voltar a detectar na proxima consulta.
		unset( $transient->response[ $this->plugin_file ] );

		$transient->no_update[ $this->plugin_file ] = (object) [
			'id'            => 'github.com/' . $this->github_repo,
			'slug'          => $this->plugin_slug,
			'plugin'        => $this->plugin_file,
			'new_version'   => $this->current_version,
			'url'           => 'https://github.com/' . $this->github_repo,
			'package'       => '',
			'icons'         => $this->icons(),
			'banners'       => [],
			'banners_rtl'   => [],
			'tested'        => '6.8',
			'requires'      => '6.0',
			'requires_php'  => '7.4',
			'compatibility' => new \stdClass(),
		];

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
		$changelog    = (string) ( $release['body'] ?? '' );

		if ( '' === $changelog ) {
			$changelog_html = '<p><a href="' . esc_url( 'https://github.com/' . $this->github_repo . '/releases' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Notas das versões no GitHub', 'ml-gallery-pro' ) . '</a></p>';
		} else {
			$changelog_html = '<pre>' . esc_html( $changelog ) . '</pre>';
		}

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
				'changelog'   => $changelog_html,
			],
			'banners'        => [],
			'icons'          => $this->icons(),
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
	 * Ordem: cache -> API do GitHub -> pagina publica da release -> ultima release boa.
	 *
	 * @return array|null Release data or null on failure.
	 */
	private function get_latest_release(): ?array {
		$cached = get_site_transient( $this->transient_key );

		if ( is_array( $cached ) ) {
			if ( ! empty( $cached ) ) {
				return $cached;
			}

			// Falha recente em cache: nao repete a consulta, usa a ultima release boa.
			return $this->last_good_release();
		}

		// Trava antes de consultar: requisicoes simultaneas, ou uma que estoure o tempo de
		// execucao, nao repetem a cadeia de consultas (sobrescrita abaixo se der certo).
		set_site_transient( $this->transient_key, [], self::ERROR_CACHE_TTL );

		$data = $this->fetch_release_from_api();

		if ( null === $data ) {
			$data = $this->fetch_release_from_web();
		}

		$version = $data ? $this->normalize_version( (string) ( $data['tag_name'] ?? '' ) ) : '';

		// So conta como release boa a que tem o ZIP oficial: release sem asset (ou ainda
		// subindo) nao apaga a ultima release boa guardada.
		if ( '' === $version || '' === $this->find_zip_url( $data, $version ) ) {
			$last = $this->last_good_release();
			return $last ? $last : ( '' !== $version ? $data : null );
		}

		$data['mlgp_saved_at'] = time();

		set_site_transient( $this->transient_key, $data, self::CACHE_TTL );
		update_option( self::LAST_RELEASE_OPTION, $data, false );

		return $data;
	}

	/**
	 * Consulta a API do GitHub (traz notas da versao e data).
	 *
	 * @return array|null
	 */
	private function fetch_release_from_api(): ?array {
		$response = wp_remote_get(
			'https://api.github.com/repos/' . $this->github_repo . '/releases/latest',
			[
				'timeout'    => self::API_TIMEOUT,
				'user-agent' => $this->user_agent(),
				'headers'    => [
					'Accept'               => 'application/vnd.github+json',
					'X-GitHub-Api-Version' => '2022-11-28',
				],
			]
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || '' === $this->normalize_version( (string) ( $data['tag_name'] ?? '' ) ) ) {
			return null;
		}

		return $data;
	}

	/**
	 * Le a ultima release pela pagina publica do GitHub (sem o limite da API).
	 *
	 * github.com/<repo>/releases/latest redireciona para /releases/tag/<tag>; o asset
	 * oficial so entra se o link de download dele existir (redirecionamento 302).
	 *
	 * @return array|null Mesmo formato da API (tag_name, assets, body, published_at).
	 */
	private function fetch_release_from_web(): ?array {
		$base     = 'https://github.com/' . $this->github_repo . '/releases';
		$response = wp_remote_head(
			$base . '/latest',
			[
				'timeout'     => self::HEAD_TIMEOUT,
				'redirection' => 0,
				'user-agent'  => $this->user_agent(),
			]
		);

		if ( is_wp_error( $response ) || ! in_array( (int) wp_remote_retrieve_response_code( $response ), [ 301, 302, 303, 307, 308 ], true ) ) {
			return null;
		}

		$location = wp_remote_retrieve_header( $response, 'location' );

		if ( is_array( $location ) ) {
			$location = end( $location );
		}

		if ( ! is_string( $location ) ) {
			$location = '';
		}

		if ( ! preg_match( '#/releases/tag/([^/?\#]+)/?$#', (string) $location, $match ) ) {
			return null;
		}

		$tag     = rawurldecode( $match[1] );
		$version = $this->normalize_version( $tag );

		if ( '' === $version ) {
			return null;
		}

		$assets = [];

		foreach ( $this->expected_asset_names( $version ) as $name ) {
			$url  = $base . '/download/' . rawurlencode( $tag ) . '/' . rawurlencode( $name );
			$head = wp_remote_head(
				$url,
				[
					'timeout'     => self::HEAD_TIMEOUT,
					'redirection' => 0,
					'user-agent'  => $this->user_agent(),
				]
			);

			// Erro de rede, 5xx ou 429 nao prova que o asset falta: aborta (vale a ultima release boa).
			if ( is_wp_error( $head ) ) {
				return null;
			}

			$code = (int) wp_remote_retrieve_response_code( $head );

			if ( $code >= 300 && $code < 400 ) {
				$assets[] = [
					'name'                 => $name,
					'browser_download_url' => $url,
				];
				break;
			}

			if ( 404 !== $code ) {
				return null;
			}
		}

		return [
			'tag_name'     => $tag,
			'assets'       => $assets,
			'body'         => '',
			'published_at' => '',
			'html_url'     => $base . '/tag/' . rawurlencode( $tag ),
		];
	}

	/**
	 * Ultima release valida guardada (null se nunca houve uma).
	 *
	 * @return array|null
	 */
	private function last_good_release(): ?array {
		$saved = get_option( self::LAST_RELEASE_OPTION );

		if ( ! is_array( $saved ) || '' === $this->normalize_version( (string) ( $saved['tag_name'] ?? '' ) ) ) {
			return null;
		}

		// Guardada ha mais de 7 dias sem conseguir conferir no GitHub: nao oferece mais nada.
		if ( time() - (int) ( $saved['mlgp_saved_at'] ?? 0 ) > self::LAST_RELEASE_MAX_AGE ) {
			return null;
		}

		return $saved;
	}

	/**
	 * User-Agent das consultas ao GitHub.
	 *
	 * @return string
	 */
	private function user_agent(): string {
		return 'ML-Gallery-Pro/' . $this->current_version . '; WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' );
	}

	/**
	 * Nomes aceitos para o ZIP oficial da versao, em ordem de prioridade.
	 *
	 * @param string $version Normalized version.
	 * @return string[]
	 */
	private function expected_asset_names( string $version ): array {
		return [
			'ML-Gallery-Pro-v' . str_replace( '.', '_', $version ) . '.zip',
			'ml-gallery-pro-v' . $version . '.zip',
			'ml-gallery-pro-' . $version . '.zip',
		];
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
		$prefix = 'https://github.com/' . $this->github_repo . '/releases/download/';

		foreach ( $release['assets'] ?? [] as $asset ) {
			$name = (string) ( $asset['name'] ?? '' );
			$url  = (string) ( $asset['browser_download_url'] ?? '' );

			if ( '' === $name || '' === $url || '.zip' !== strtolower( substr( $name, -4 ) ) ) {
				continue;
			}

			// Pacote so do repositorio de releases configurado.
			if ( 0 !== strpos( $url, $prefix ) ) {
				continue;
			}

			$assets[] = [
				'name_lower' => strtolower( $name ),
				'url'        => $url,
			];
		}

		foreach ( $this->expected_asset_names( $remote_version ) as $expected_name ) {
			$expected_name = strtolower( $expected_name );

			foreach ( $assets as $asset ) {
				if ( $asset['name_lower'] === $expected_name ) {
					return $asset['url'];
				}
			}
		}

		// Sem fallback: "qualquer ZIP" ou o zipball da tag (codigo-fonte) podiam instalar o arquivo errado.
		return '';
	}

	/**
	 * Icons shown on the WordPress updates screen.
	 *
	 * @return array<string,string>
	 */
	private function icons(): array {
		$base = plugin_dir_url( WP_PLUGIN_DIR . '/' . $this->plugin_file ) . 'assets/images/';

		return [
			'1x'      => $base . 'menu-icon-128.png',
			'2x'      => $base . 'menu-icon-256.png',
			'default' => $base . 'menu-icon-256.png',
		];
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
			'icons'         => $this->icons(),
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
