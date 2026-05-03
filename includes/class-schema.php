<?php
/**
 * JSON-LD Schema.org output for single event pages.
 *
 * Helps Google Search show event rich-results (date, location, register link).
 *
 * @package Stgl\SwinogEvents
 */

declare( strict_types = 1 );

namespace Stgl\SwinogEvents;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Schema {

	public function register(): void {
		add_action( 'wp_head', [ $this, 'output' ], 20 );
	}

	public function output(): void {
		if ( ! is_singular( Post_Types::CPT_EVENT ) ) {
			return;
		}
		$id = (int) get_queried_object_id();
		if ( ! $id ) {
			return;
		}

		$start = (string) get_post_meta( $id, 'stgl_event_date', true );
		if ( '' === $start ) {
			return;
		}

		$end       = (string) get_post_meta( $id, 'stgl_event_end_date', true );
		$location  = (string) get_post_meta( $id, 'stgl_event_location', true );
		$reg_url   = (string) get_post_meta( $id, 'stgl_event_reg_url', true );
		$lat       = (string) get_post_meta( $id, 'stgl_event_venue_lat', true );
		$lng       = (string) get_post_meta( $id, 'stgl_event_venue_lng', true );
		$max_seats = (int) get_post_meta( $id, 'stgl_event_max_seats', true );

		$start_iso = Helpers\to_iso_date( $start );
		$end_iso   = $end ? Helpers\to_iso_date( $end ) : $start_iso;

		$data = [
			'@context'    => 'https://schema.org',
			'@type'       => 'Event',
			'name'        => get_the_title( $id ),
			'startDate'   => $start_iso,
			'endDate'     => $end_iso,
			'eventStatus' => 'https://schema.org/EventScheduled',
			'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
			'description' => wp_strip_all_tags( get_post_field( 'post_content', $id ) ),
			'url'         => get_permalink( $id ),
		];

		if ( $location ) {
			$place = [
				'@type'   => 'Place',
				'name'    => $location,
				'address' => $location,
			];
			if ( $lat !== '' && $lng !== '' ) {
				$place['geo'] = [
					'@type'     => 'GeoCoordinates',
					'latitude'  => $lat,
					'longitude' => $lng,
				];
			}
			$data['location'] = $place;
		}

		if ( $reg_url ) {
			$data['offers'] = [
				'@type'         => 'Offer',
				'url'           => $reg_url,
				'availability'  => $max_seats > 0 ? 'https://schema.org/LimitedAvailability' : 'https://schema.org/InStock',
				'price'         => '0',
				'priceCurrency' => 'CHF',
				'validFrom'     => current_time( 'c' ),
			];
		}

		// Featured image / thumbnail.
		$thumb = (string) get_the_post_thumbnail_url( $id, 'large' );
		if ( $thumb ) {
			$data['image'] = $thumb;
		}

		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}
}
