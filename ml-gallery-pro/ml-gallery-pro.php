<?php
/**
 * Plugin Name: ML Gallery Pro
 * Plugin URI: https://mlopesdesign.com/
 * Description: Galerias e álbuns profissionais para WordPress com painel dedicado, AJAX e estrutura escalável.
 * Version: 0.26.9
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

define( 'MLGP_VERSION', '0.26.9' );
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
require_once MLGP_DIR . 'includes/Core/Updater.php';
require_once MLGP_DIR . 'includes/Core/Plugin.php';

add_action(
	'plugins_loaded',
	function () {
		\MLGP\Core\Plugin::instance()->boot();
	},
	0
);

register_activation_hook(
	__FILE__,
	function () {
		\MLGP\Database\Installer::maybe_upgrade();
		\MLGP\Core\Plugin::instance()->boot();
	}
);

if ( MLGP_VERSION !== get_option( 'mlgp_version' ) ) {
	update_option( 'mlgp_version', MLGP_VERSION );
}
