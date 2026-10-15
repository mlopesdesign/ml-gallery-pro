<?php
/**
 * Handles licensing operations.
 *
 * 0.27.0: a licença passa a ser emitida e conferida pelo site tools.mlopesdesign.com.br
 * (cliente ML_License_Client_V2). O servidor antigo (license.mlopesdesign.com.br) não é
 * mais chamado. Regras: 1 licença = 1 site; teste grátis de 30 dias por site; sem
 * teste/licença válidos, o painel do plugin trava (as galerias publicadas continuam
 * aparecendo no site — nenhum dado é apagado).
 *
 * A interface pública desta classe (validate_license, deactivate_license, build_payload,
 * is_full_license_active, get_state) foi mantida para não quebrar Admin e Ajax.
 *
 * @package MLGalleryPro
 */

namespace MLGP\License;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Manager {
	/**
	 * Endereço da API de licenças do site.
	 */
	private const API_BASE = 'https://tools.mlopesdesign.com.br/wp-json/mllh/v1';

	/**
	 * Chave pública Ed25519 do site (confere a assinatura das respostas).
	 */
	private const PUBLIC_KEY = 'VGZQp2X3RJMuDmQBlIEXS4fqsZD1CnFlIJgfVtY/pHo=';

	/**
	 * Slug do produto no site = slug do plugin.
	 */
	private const PRODUCT_ID = 'ml-gallery-pro';

	/**
	 * Option antiga (servidor antigo). Mantida intacta: nunca apagar dados do usuário.
	 */
	private const LEGACY_OPTION_KEY = 'mlgp_license_state';

	/**
	 * Cliente de licenças.
	 *
	 * @var \ML_License_Client_V2|null
	 */
	private $client = null;

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {
		// Instancia o cliente cedo para agendar a renovação diária do token.
		$this->client();
		add_action( 'admin_init', [ $this, 'maybe_handle_license_post' ] );
	}

	/**
	 * Cliente compartilhado.
	 *
	 * @return \ML_License_Client_V2
	 */
	public function client(): \ML_License_Client_V2 {
		if ( null === $this->client ) {
			$this->client = new \ML_License_Client_V2(
				[
					'api_base'   => self::API_BASE,
					'product'    => self::PRODUCT_ID,
					'public_key' => self::PUBLIC_KEY,
					'version'    => MLGP_VERSION,
				]
			);
		}

		return $this->client;
	}

	/**
	 * Returns the current license state (local, sem rede).
	 *
	 * @return array<string, mixed>
	 */
	public function get_state(): array {
		return $this->client()->state();
	}

	/**
	 * Ativa uma chave; sem chave, inicia (ou retoma) o teste grátis deste site.
	 *
	 * @param string $license_key Submitted serial.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function validate_license( string $license_key ) {
		$key = trim( $license_key );

		// O campo mostra a chave mascarada; reenviar a máscara só confere a licença atual.
		if ( false !== strpos( $key, '•' ) ) {
			$result = $this->client()->refresh();
		} elseif ( '' === $key ) {
			$result = $this->client()->start_trial();
		} else {
			$result = $this->client()->activate( $key );
		}

		return is_wp_error( $result ) ? $result : $this->build_payload();
	}

	/**
	 * Libera este site (a chave pode ser usada em outro).
	 *
	 * @return array<string, mixed>
	 */
	public function deactivate_license(): array {
		$this->client()->deactivate();
		return $this->build_payload();
	}

	/**
	 * Teste ou licença válidos neste site.
	 *
	 * @return bool
	 */
	public function is_full_license_active(): bool {
		return $this->client()->is_premium();
	}

	/**
	 * O painel do plugin fica travado (sem teste nem licença válidos).
	 *
	 * @return bool
	 */
	public function is_locked(): bool {
		return ! $this->is_full_license_active();
	}

	/**
	 * Returns a payload suitable for the admin UI.
	 *
	 * @return array<string, mixed>
	 */
	public function build_payload(): array {
		$state  = $this->get_state();
		$active = ! empty( $state['active'] );
		$mode   = (string) ( $state['mode'] ?? 'none' );
		$plan   = strtolower( (string) ( $state['plan'] ?? '' ) );
		$days   = isset( $state['days_left'] ) ? $state['days_left'] : null;

		$plan_labels = [
			'trial'    => __( 'Teste grátis', 'ml-gallery-pro' ),
			'monthly'  => __( 'Mensal', 'ml-gallery-pro' ),
			'annual'   => __( 'Anual', 'ml-gallery-pro' ),
			'yearly'   => __( 'Anual', 'ml-gallery-pro' ),
			'lifetime' => __( 'Vitalícia', 'ml-gallery-pro' ),
			'courtesy' => __( 'Cortesia', 'ml-gallery-pro' ),
			'manual'   => __( 'Cortesia', 'ml-gallery-pro' ),
		];
		$plan_label = $plan_labels[ $plan ] ?? ( '' !== $plan ? ucfirst( $plan ) : '' );

		if ( $active && 'trial' === $mode ) {
			$label   = __( 'Teste', 'ml-gallery-pro' );
			$tone    = 'trial';
			$message = null === $days
				? __( 'Teste grátis ativo neste site.', 'ml-gallery-pro' )
				/* translators: %d: days left. */
				: sprintf( _n( 'Teste grátis ativo: falta %d dia.', 'Teste grátis ativo: faltam %d dias.', (int) $days, 'ml-gallery-pro' ), (int) $days );
		} elseif ( $active ) {
			$label   = __( 'Ativa', 'ml-gallery-pro' );
			$tone    = 'full';
			$message = __( 'Licença ativa neste site.', 'ml-gallery-pro' );
		} else {
			$label = __( 'Sem licença', 'ml-gallery-pro' );
			$tone  = 'free';
			if ( 'trial_expired' === ( $state['status'] ?? '' ) ) {
				$message = __( 'O teste grátis terminou. Ative uma licença para continuar criando e editando galerias. As galerias publicadas continuam no ar.', 'ml-gallery-pro' );
			} elseif ( '' !== (string) ( $state['message'] ?? '' ) ) {
				$message = (string) $state['message'];
			} else {
				$message = __( 'Comece o teste grátis de 30 dias ou ative a sua licença.', 'ml-gallery-pro' );
			}
		}

		$expires_label = '';
		if ( ! empty( $state['expires_at'] ) ) {
			$ts = strtotime( (string) $state['expires_at'] );
			if ( $ts ) {
				$expires_label = wp_date( get_option( 'date_format', 'd/m/Y' ), $ts );
			}
		}

		return [
			'license_key'     => (string) ( $state['key_masked'] ?? '' ),
			'license_source'  => $mode,
			'message'         => $message,
			'plan'            => $plan_label,
			'expires_at'      => (string) ( $state['expires_at'] ?? '' ),
			'expires_label'   => $expires_label,
			'days_left'       => $days,
			'last_sync'       => 0,
			'status'          => (string) ( $state['status'] ?? '' ),
			'is_full_active'  => $active,
			'locked'          => ! $active,
			'mode'            => $mode,
			'can_start_trial' => ! $active && empty( $state['trial_used'] ) && 'license' !== $mode,
			'state_label'     => $label,
			'state_tone'      => $tone,
			'buy_url'         => 'https://tools.mlopesdesign.com.br/produtos/',
			'account_url'     => 'https://tools.mlopesdesign.com.br/minha-conta/',
		];
	}

	/**
	 * Handles POST submissions coming from the license panel (fallback sem JavaScript).
	 *
	 * @return void
	 */
	public function maybe_handle_license_post(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( empty( $_POST['mlgp_license_action'] ) ) {
			return;
		}

		if ( ! check_admin_referer( 'mlgp_license_action_nonce', 'mlgp_license_action_nonce' ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['mlgp_license_action'] ) );
		if ( 'validate_license' === $action ) {
			$key = isset( $_POST['mlgp_license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['mlgp_license_key'] ) ) : '';
			$this->validate_license( $key );
		} elseif ( 'deactivate_license' === $action ) {
			$this->deactivate_license();
		}
	}

	/**
	 * Nome da option antiga (para referência no diagnóstico; não é apagada).
	 *
	 * @return string
	 */
	public static function legacy_option_key(): string {
		return self::LEGACY_OPTION_KEY;
	}
}
