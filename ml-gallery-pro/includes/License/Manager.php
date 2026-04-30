<?php
/**
 * Handles licensing operations.
 *
 * @package MLGalleryPro
 */

namespace MLGP\License;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Manager {
	/**
	 * Option key that stores the license state.
	 */
	private const OPTION_KEY = 'mlgp_license_state';

	/**
	 * URL of the official license server.
	 */
	private const LICENSE_SERVER_URL = 'https://license.mlopesdesign.com.br/api/license.php';

	/**
	 * Product identifier sent to the license server.
	 */
	private const PRODUCT_ID = 'ml-gallery-pro';

	/**
	 * Cached license state.
	 *
	 * @var array<string, mixed>|null
	 */
	private $state = null;

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'admin_init', [ $this, 'maybe_handle_license_post' ] );
	}

	/**
	 * Returns the current license payload.
	 *
	 * @return array<string, mixed>
	 */
	public function get_state(): array {
		if ( null === $this->state ) {
			$saved       = get_option( self::OPTION_KEY, [] );
			$this->state = array_merge( $this->get_default_state(), is_array( $saved ) ? $saved : [] );
		}

		return $this->state;
	}

	/**
	 * Validates a license key (or starts trial when empty).
	 *
	 * @param string $license_key Submitted serial.
	 * @return array<string, mixed>
	 */
	public function validate_license( string $license_key ): array {
		$normalized_key = trim( $license_key );
		$action         = $normalized_key === '' ? 'start_trial' : 'validate';
		$result         = $this->request_license_api( $normalized_key, $action );
		$state          = $this->build_state_from_response( $result, $normalized_key, $action );
		$this->save_state( $state );
		return $state;
	}

	/**
	 * Clears the stored license locally.
	 *
	 * @return array<string, mixed>
	 */
	public function deactivate_license(): array {
		$state = $this->get_default_state();
		$this->save_state( $state );
		return $state;
	}

	/**
	 * Indicates whether the site holds a full/license status.
	 *
	 * @return bool
	 */
	public function is_full_license_active(): bool {
		$state  = $this->get_state();
		$source = strtolower( (string) ( $state['license_source'] ?? '' ) );
		$status = strtolower( (string) ( $state['status'] ?? '' ) );
		$plan   = strtolower( (string) ( $state['plan'] ?? '' ) );

		if ( in_array( $status, [ 'trial_active', 'active', 'lifetime' ], true ) ) {
			return true;
		}

		if ( in_array( $plan, [ 'full', 'trial', 'lifetime', 'annual', 'monthly' ], true ) ) {
			return true;
		}

		return in_array( $source, [ 'license', 'full', 'server_license', 'license_hub' ], true ) && ! empty( $state['license_key'] );
	}

	/**
	 * Returns a payload suitable for the admin UI.
	 *
	 * @return array<string, mixed>
	 */
	public function build_payload(): array {
		$state        = $this->get_state();
		$payload_plan = (string) ( $state['plan'] ?? '' );
		$payload_plan = '' !== $payload_plan ? $payload_plan : ( $this->is_full_license_active() ? 'Full' : 'Free' );

		return [
			'license_key'    => (string) ( $state['license_key'] ?? '' ),
			'license_source' => (string) ( $state['license_source'] ?? 'free' ),
			'message'        => (string) ( $state['message'] ?? '' ),
			'plan'           => $payload_plan,
			'expires_at'     => (string) ( $state['expires_at'] ?? '' ),
			'last_sync'      => (int) ( $state['last_sync'] ?? 0 ),
			'status'         => (string) ( $state['status'] ?? 'unknown' ),
			'is_full_active' => $this->is_full_license_active(),
			'state_label'    => $this->resolve_plan_label( $state ),
			'state_tone'     => $this->resolve_plan_tone( $state ),
		];
	}

	/**
	 * Handles POST submissions coming from the license panel.
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
	 * Saves the license payload.
	 *
	 * @param array<string, mixed> $state License state.
	 * @return void
	 */
	private function save_state( array $state ): void {
		$merged              = array_merge( $this->get_default_state(), array_merge( $this->state ?? [], $state ) );
		$merged['last_sync'] = time();
		update_option( self::OPTION_KEY, $merged, false );
		$this->state = $merged;
	}

	/**
	 * Sends an API request to the licensing server.
	 *
	 * @param string $license_key License key.
	 * @param string $action Requested action.
	 * @return array<string, mixed>
	 */
	private function request_license_api( string $license_key, string $action ): array {
		$args = [
			'timeout' => 20,
			'body'    => [
				'action'      => $action,
				'license_key' => trim( $license_key ),
				'product_id'  => self::PRODUCT_ID,
				'domain'      => $this->get_current_domain_for_license(),
				'domain_hash' => $this->get_domain_hash(),
				'version'     => MLGP_VERSION,
			],
		];

		$response = wp_remote_post( self::LICENSE_SERVER_URL, $args );

		if ( is_wp_error( $response ) ) {
			return [
				'valid'   => false,
				'status'  => 'error',
				'message' => $response->get_error_message(),
			];
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return [
				'valid'   => false,
				'status'  => 'error',
				'message' => __( 'Resposta inválida da licença.', 'ml-gallery-pro' ),
			];
		}

		return $data;
	}

	/**
	 * Enforces the default license payload.
	 *
	 * @return array<string, mixed>
	 */
	private function get_default_state(): array {
		return [
			'license_key'    => '',
			'license_source' => 'free',
			'status'         => 'free',
			'message'        => '',
			'plan'           => 'Free',
			'expires_at'     => '',
			'last_sync'      => 0,
		];
	}

	/**
	 * Builds a normalized state from the license API response.
	 *
	 * @param array<string, mixed> $response Response from hub.
	 * @param string               $license_key License key.
	 * @param string               $action Action sent.
	 * @return array<string, mixed>
	 */
	private function build_state_from_response( array $response, string $license_key, string $action ): array {
		$state                = $this->get_default_state();
		$state['license_key'] = trim( $license_key );

		$status = '';
		foreach ( [ 'status', 'license_status', 'trial_status', 'state' ] as $status_key ) {
			if ( isset( $response[ $status_key ] ) && '' !== trim( (string) $response[ $status_key ] ) ) {
				$status = sanitize_key( (string) $response[ $status_key ] );
				break;
			}
		}

		$plan = '';
		foreach ( [ 'plan', 'license_type', 'type', 'tier' ] as $plan_key ) {
			if ( isset( $response[ $plan_key ] ) && '' !== trim( (string) $response[ $plan_key ] ) ) {
				$plan = (string) $response[ $plan_key ];
				break;
			}
		}

		if ( '' === $status ) {
			if ( ! empty( $response['valid'] ) ) {
				$status = 'start_trial' === $action ? 'trial_active' : 'active';
			} elseif ( 'start_trial' === $action ) {
				$status = 'trial_pending';
			} else {
				$status = '' === $license_key ? 'free' : 'inactive';
			}
		}

		if ( '' === $plan ) {
			if ( false !== strpos( $status, 'trial' ) || 'start_trial' === $action ) {
				$plan = 'Trial';
			} elseif ( in_array( $status, [ 'active', 'lifetime', 'annual', 'monthly' ], true ) ) {
				$plan = 'Full';
			} else {
				$plan = '' === $license_key ? 'Free' : 'Full';
			}
		}

		$message = '';
		foreach ( [ 'message', 'detail', 'description' ] as $message_key ) {
			if ( isset( $response[ $message_key ] ) && '' !== trim( (string) $response[ $message_key ] ) ) {
				$message = (string) $response[ $message_key ];
				break;
			}
		}

		if ( '' === $message ) {
			if ( false !== strpos( strtolower( $plan ), 'trial' ) ) {
				$message = __( 'Trial ativo nesta instalacao.', 'ml-gallery-pro' );
			} elseif ( 'free' === strtolower( $plan ) ) {
				$message = __( 'Versao Free ativa.', 'ml-gallery-pro' );
			} else {
				$message = __( 'Licenca ativa nesta instalacao.', 'ml-gallery-pro' );
			}
		}

		$license_source = isset( $response['license_source'] ) ? (string) $response['license_source'] : '';
		if ( '' === $license_source ) {
			if ( false !== strpos( strtolower( $plan ), 'trial' ) || false !== strpos( $status, 'trial' ) ) {
				$license_source = 'server_trial';
			} elseif ( 'free' === strtolower( $plan ) || '' === $license_key ) {
				$license_source = 'free';
			} else {
				$license_source = 'license';
			}
		}

		$state['status']         = $status;
		$state['message']        = $message;
		$state['license_source'] = $license_source;
		$state['plan']           = $this->normalize_plan_label( $plan, $status, $license_source );

		foreach ( [ 'expires_at', 'expires', 'expiration', 'expires_on' ] as $expires_key ) {
			if ( isset( $response[ $expires_key ] ) && '' !== trim( (string) $response[ $expires_key ] ) ) {
				$state['expires_at'] = (string) $response[ $expires_key ];
				break;
			}
		}

		if ( 'deactivate_license' === $action ) {
			$state['license_key']    = '';
			$state['license_source'] = 'free';
			$state['status']         = 'free';
			$state['plan']           = 'Free';
			$state['expires_at']     = '';
			$state['message']        = __( 'Versao Free ativa.', 'ml-gallery-pro' );
		}

		return $state;
	}

	/**
	 * Normalizes a plan label for UI usage.
	 *
	 * @param string $plan Raw plan label.
	 * @param string $status Normalized status.
	 * @param string $license_source License source.
	 * @return string
	 */
	private function normalize_plan_label( string $plan, string $status, string $license_source ): string {
		$normalized_plan = strtolower( trim( $plan ) );

		if ( '' === $normalized_plan ) {
			$normalized_plan = 'free';
		}

		if ( false !== strpos( $normalized_plan, 'trial' ) || false !== strpos( $status, 'trial' ) || 'server_trial' === $license_source ) {
			return 'Trial';
		}

		if ( in_array( $normalized_plan, [ 'lifetime', 'vitalicio', 'full', 'pro', 'premium', 'annual', 'monthly', 'active' ], true ) ) {
			return 'Full';
		}

		return 'Free';
	}

	/**
	 * Returns a human readable license state label.
	 *
	 * @param array<string, mixed> $state License state.
	 * @return string
	 */
	private function resolve_plan_label( array $state ): string {
		return $this->normalize_plan_label( (string) ( $state['plan'] ?? '' ), (string) ( $state['status'] ?? '' ), (string) ( $state['license_source'] ?? '' ) );
	}

	/**
	 * Returns a tone token for the current license state.
	 *
	 * @param array<string, mixed> $state License state.
	 * @return string
	 */
	private function resolve_plan_tone( array $state ): string {
		$label = strtolower( $this->resolve_plan_label( $state ) );

		if ( 'trial' === $label ) {
			return 'trial';
		}

		if ( 'full' === $label ) {
			return 'full';
		}

		return 'free';
	}

	/**
	 * Returns the domain identifier sent to the license server.
	 *
	 * @return string
	 */
	private function get_current_domain_for_license(): string {
		$domain = trim( wp_parse_url( home_url(), PHP_URL_HOST ) ?: '' );

		if ( '' === $domain && isset( $_SERVER['HTTP_HOST'] ) ) {
			$domain = trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) );
		}

		return trim( strtolower( $domain ) );
	}

	/**
	 * Builds a hashed fingerprint compatible with the current validation motor.
	 *
	 * @return string
	 */
	private function get_domain_hash(): string {
		return hash( 'sha256', $this->get_current_domain_for_license() . '|' . site_url() . '|' . home_url() );
	}
}
