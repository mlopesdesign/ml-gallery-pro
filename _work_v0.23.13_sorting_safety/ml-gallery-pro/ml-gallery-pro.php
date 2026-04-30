<?php
/**
 * Plugin Name: ML Gallery Pro
 * Plugin URI: https://mlopesdesign.com/
 * Description: Galerias e álbuns profissionais para WordPress com painel dedicado, AJAX e estrutura escalável.
 * Version: 0.23.13
 * Author: Mlopesdesign
 * Author URI: https://mlopesdesign.com/
 * Text Domain: ml-gallery-pro
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package MLGalleryPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MLGP_VERSION', '0.23.13' );
define( 'MLGP_FILE', __FILE__ );
define( 'MLGP_BASENAME', plugin_basename( __FILE__ ) );
define( 'MLGP_DIR', plugin_dir_path( __FILE__ ) );
define( 'MLGP_URL', plugin_dir_url( __FILE__ ) );

require_once MLGP_DIR . 'includes/Media/Storage.php';
require_once MLGP_DIR . 'includes/Database/Installer.php';
require_once MLGP_DIR . 'includes/Database/Repository.php';
require_once MLGP_DIR . 'includes/Admin/Admin.php';
require_once MLGP_DIR . 'includes/Admin/Ajax.php';
require_once MLGP_DIR . 'includes/License/Manager.php';
require_once MLGP_DIR . 'includes/Frontend/Shortcodes.php';
require_once MLGP_DIR . 'includes/Blocks/GalleryBlock.php';
require_once MLGP_DIR . 'includes/Core/Plugin.php';

register_activation_hook( __FILE__, [ 'MLGP\Database\Installer', 'activate' ] );

add_action(
	'plugins_loaded',
	static function () {
		\MLGP\Core\Plugin::instance()->boot();
	}
);


if ( ! function_exists( 'mlgp_get_gallery_url' ) ) {
	/**
	 * Returns the canonical frontend URL for one gallery.
	 *
	 * @param int $gallery_id Gallery ID.
	 * @return string
	 */
	function mlgp_get_gallery_url( $gallery_id ): string {
		$gallery_id = absint( $gallery_id );

		if ( $gallery_id <= 0 ) {
			return '';
		}

		$repository = new \MLGP\Database\Repository();

		return $repository->get_gallery_public_url( $gallery_id );
	}
}


if ( ! function_exists( 'mlgp_get_gallery_shortcode' ) ) {
	/**
	 * Returns the official shortcode for one gallery.
	 *
	 * @param int $gallery_id Gallery ID.
	 * @return string
	 */
	function mlgp_get_gallery_shortcode( $gallery_id ): string {
		$gallery_id = absint( $gallery_id );

		if ( $gallery_id <= 0 ) {
			return '';
		}

		$repository = new \MLGP\Database\Repository();

		return $repository->get_gallery_shortcode( $gallery_id );
	}
}


/**
 * Flushes gallery rewrite rules after plugin updates.
 *
 * @return void
 */
function mlgp_maybe_flush_rewrite_rules(): void {
	$installed_version = (string) get_option( 'mlgp_version', '' );

	if ( MLGP_VERSION === $installed_version ) {
		return;
	}

	update_option( 'mlgp_version', MLGP_VERSION );
	flush_rewrite_rules( false );
}

add_action( 'init', 'mlgp_maybe_flush_rewrite_rules', 99 );
