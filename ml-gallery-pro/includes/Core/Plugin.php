<?php
/**
 * Core plugin bootstrap.
 *
 * @package MLGalleryPro
 */

namespace MLGP\Core;

use MLGP\Admin\Admin;
use MLGP\Admin\Ajax;
use MLGP\Blocks\GalleryBlock;
use MLGP\Database\Installer;
use MLGP\Database\Repository;
use MLGP\Frontend\Shortcodes;
use MLGP\License\Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Shared repository instance.
	 *
	 * @var Repository
	 */
	private $repository;

	/**
	 * Shared license manager.
	 *
	 * @var Manager
	 */
	private $license_manager;

	/**
	 * Prevents duplicate bootstrapping.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->repository = new Repository();
		$this->license_manager = new Manager();
	}

	/**
	 * Gets singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Boots the plugin.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;
		Installer::maybe_upgrade();
		$this->license_manager->hooks();

		add_action( 'init', [ $this, 'load_textdomain' ] );

		$ajax = new Ajax( $this->repository, $this->license_manager );
		$ajax->hooks();

		$shortcodes = new Shortcodes( $this->repository );
		$shortcodes->hooks();

		$block = new GalleryBlock( $this->repository, $shortcodes );
		$block->hooks();

		if ( is_admin() ) {
			$admin = new Admin( $this->repository, $this->license_manager );
			$admin->hooks();
		}

		// WP-Cron handler: fires mlgp_after_items_stored in an isolated request.
		add_action( 'mlgp_fire_after_items_stored', [ $this, 'cron_fire_after_items_stored' ] );
	}

	/**
	 * Loads translations.
	 *
	 * @return void
	 */
	public function cron_fire_after_items_stored( string $transient_key ): void {
		$data = get_transient( $transient_key );
		if ( ! is_array( $data ) || empty( $data['uploads'] ) || ! isset( $data['gallery_id'] ) ) {
			return;
		}
		delete_transient( $transient_key );
		do_action( 'mlgp_after_items_stored', $data['uploads'], (int) $data['gallery_id'] );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'ml-gallery-pro', false, dirname( MLGP_BASENAME ) . '/languages' );
	}
}
