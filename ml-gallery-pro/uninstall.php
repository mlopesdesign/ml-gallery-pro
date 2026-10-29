<?php
/**
 * Desinstalação do ML Gallery Pro.
 *
 * Galerias, álbuns, imagens (wp-content/ml-gallery), configurações e licença são
 * PRESERVADOS de propósito: são dados do cliente e uma reinstalação deve encontrar tudo
 * como estava. Aqui só paramos as tarefas agendadas do plugin.
 *
 * @package MLGalleryPro
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

wp_clear_scheduled_hook( 'mllc_ml_gallery_pro_refresh' );

// 0.27.1: cache da última release do GitHub (não é dado do cliente).
delete_option( 'mlgp_updater_last_release' );
