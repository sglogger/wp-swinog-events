<?php
/**
 * Thin HTTP client for the SwiNOG CFP tool API, plus accessors for the
 * "API Settings" stored on the Settings screen.
 *
 * @package Stgl\SwinogEvents
 */

declare( strict_types = 1 );

namespace Stgl\SwinogEvents;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Cfp_Client {

	public const DEFAULT_SERVER = 'https://cfp.swinog.ch';

	/**
	 * Slot types known to the CFP API (`SlotType` enum).
	 */
	public const SLOT_TYPES = [ 'presentation', 'break', 'social', 'housekeeping', 'custom' ];

	/**
	 * Event statuses queried on the public API when the admin route is not
	 * reachable. Without a status filter the API only returns open_cfp and
	 * published events.
	 */
	private const PUBLIC_EVENT_STATUSES = [ 'open_cfp', 'closed_cfp', 'published', 'archived' ];

	/* ------------------------------------------------------------------ */
	/*  Settings                                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * Suggested presentation type (slug) for each CFP slot type.
	 *
	 * @return array<string, string>
	 */
	public static function default_type_map(): array {
		return [
			'presentation' => 'talk',
			'break'        => 'break',
			'social'       => 'social',
			'housekeeping' => 'other',
			'custom'       => 'other',
		];
	}

	/**
	 * @return array{server: string, api_key: string, type_map: array<string, string>}
	 */
	public static function settings(): array {
		$raw = get_option( Installer::OPTION_CFP_API, [] );
		$raw = is_array( $raw ) ? $raw : [];

		$map = is_array( $raw['type_map'] ?? null ) ? $raw['type_map'] : [];
		$map = array_merge( self::default_type_map(), array_map( 'strval', $map ) );

		$server = (string) ( $raw['server'] ?? '' );

		return [
			'server'   => '' !== $server ? $server : self::DEFAULT_SERVER,
			'api_key'  => (string) ( $raw['api_key'] ?? '' ),
			'type_map' => $map,
		];
	}

	/**
	 * Accept "https://cfp.swinog.ch", ".../api" or ".../api/v1" and reduce it
	 * to the host root; the client appends /api/v1 itself.
	 */
	public static function normalize_server( string $url ): string {
		$url = untrailingslashit( esc_url_raw( trim( $url ) ) );
		return (string) preg_replace( '#/api(/v1)?$#i', '', $url );
	}

	/**
	 * Presentation type slug to store for a CFP slot type. Empty = default.
	 */
	public static function map_slot_type( string $slot_type ): string {
		$map   = self::settings()['type_map'];
		$types = Installer::presentation_types();
		$slug  = sanitize_key( $map[ $slot_type ] ?? '' );

		return isset( $types[ $slug ] ) ? $slug : '';
	}

	/* ------------------------------------------------------------------ */
	/*  Endpoints                                                         */
	/* ------------------------------------------------------------------ */

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public function version() {
		return $this->get( '/version', false );
	}

	/**
	 * All CFP events, newest first. Uses the admin route (every status) when
	 * the API key is accepted, otherwise merges the public status lists.
	 *
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	public function events() {
		$events = $this->get( '/admin/events', true );
		if ( ! is_wp_error( $events ) ) {
			return $events;
		}

		$by_id = [];
		foreach ( self::PUBLIC_EVENT_STATUSES as $status ) {
			$list = $this->get( '/events?status=' . rawurlencode( $status ), false );
			if ( is_wp_error( $list ) ) {
				return $list;
			}
			foreach ( $list as $event ) {
				if ( is_array( $event ) && ! empty( $event['id'] ) ) {
					$by_id[ (string) $event['id'] ] = $event;
				}
			}
		}

		$events = array_values( $by_id );
		usort( $events, static fn( $a, $b ) => strcmp( (string) ( $b['starts_on'] ?? '' ), (string) ( $a['starts_on'] ?? '' ) ) );
		return $events;
	}

	/**
	 * All slots of an event, including presenter contact data and consents.
	 * Needs an accepted API key.
	 *
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	public function slots( string $event_id ) {
		return $this->get( '/admin/events/' . rawurlencode( $event_id ) . '/slots', true );
	}

	/* ------------------------------------------------------------------ */
	/*  Transport                                                         */
	/* ------------------------------------------------------------------ */

	/**
	 * @return array<mixed>|\WP_Error Decoded JSON body.
	 */
	private function get( string $path, bool $auth ) {
		$settings = self::settings();
		$headers  = [ 'Accept' => 'application/json' ];

		if ( $auth ) {
			if ( '' === $settings['api_key'] ) {
				return new \WP_Error( 'stgl_cfp_no_key', __( 'No CFP API key configured (Settings → API Settings).', 'stgl' ) );
			}
			$headers['Authorization'] = 'Bearer ' . $settings['api_key'];
		}

		$url      = self::normalize_server( $settings['server'] ) . '/api/v1' . $path;
		$response = wp_remote_get( $url, [
			'timeout' => 15,
			'headers' => $headers,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$detail = is_array( $body ) && is_string( $body['detail'] ?? null ) ? $body['detail'] : '';
			if ( $auth && 401 === $code ) {
				$detail = trim( ( '' !== $detail ? $detail . ' – ' : '' ) . __( 'The API key is unknown, malformed or revoked. Enter a valid key under Settings → API Settings.', 'stgl' ) );
			} elseif ( $auth && 403 === $code ) {
				$detail = trim( ( '' !== $detail ? $detail . ' – ' : '' ) . __( 'The API key is valid but lacks the required scope. Create a token with the "read-internal" scope in the CFP tool (Settings → API tokens) and enter it under Settings → API Settings.', 'stgl' ) );
			}
			return new \WP_Error(
				'stgl_cfp_http',
				/* translators: 1: HTTP status code, 2: request URL, 3: error detail */
				sprintf( __( 'CFP API returned HTTP %1$d for %2$s. %3$s', 'stgl' ), $code, $url, $detail )
			);
		}

		if ( ! is_array( $body ) ) {
			/* translators: %s: request URL */
			return new \WP_Error( 'stgl_cfp_json', sprintf( __( 'CFP API returned invalid JSON for %s.', 'stgl' ), $url ) );
		}

		return $body;
	}
}
