<?php
/**
 * iCal / ICS export.
 *
 * Hits any URL with ?stgl_ical=<event-slug> and the browser receives a clean
 * RFC-5545 .ics file with one VEVENT per session in the agenda.
 *
 * Example:  https://swinog.example/?stgl_ical=swinog-37
 *
 * @package Stgl\SwinogEvents
 */

declare( strict_types = 1 );

namespace Stgl\SwinogEvents;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ICal {

	public function register(): void {
		add_action( 'init', [ $this, 'maybe_serve' ], 99 );
	}

	public function maybe_serve(): void {
		if ( empty( $_GET['stgl_ical'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$slug = sanitize_title( wp_unslash( (string) $_GET['stgl_ical'] ) ); // phpcs:ignore
		if ( '' === $slug ) {
			return;
		}

		$query = new \WP_Query( [
			'post_type'      => Post_Types::CPT_EVENT,
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			'tax_query'      => [
				[ 'taxonomy' => Post_Types::TAX_EVENT, 'field' => 'slug', 'terms' => $slug ],
			],
		] );

		if ( ! $query->have_posts() ) {
			status_header( 404 );
			exit;
		}

		$event = $query->posts[0];
		$ics   = $this->build_ics( $event, $slug );

		nocache_headers();
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $slug . '.ics"' );
		echo $ics; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw ICS text
		exit;
	}

	private function build_ics( \WP_Post $event, string $slug ): string {
		$site = wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'localhost';

		$lines   = [];
		$lines[] = 'BEGIN:VCALENDAR';
		$lines[] = 'VERSION:2.0';
		$lines[] = 'PRODID:-//SwiNOG Events//' . $site . '//EN';
		$lines[] = 'CALSCALE:GREGORIAN';
		$lines[] = 'METHOD:PUBLISH';
		$lines[] = 'X-WR-CALNAME:' . self::escape( get_the_title( $event ) );
		$lines[] = 'X-WR-TIMEZONE:' . wp_timezone_string();

		// Main event VEVENT (full-day or window).
		$start = (string) get_post_meta( $event->ID, 'stgl_event_date', true );
		$end   = (string) get_post_meta( $event->ID, 'stgl_event_end_date', true );
		$loc   = (string) get_post_meta( $event->ID, 'stgl_event_location', true );
		$reg   = (string) get_post_meta( $event->ID, 'stgl_event_reg_url', true );

		$start_iso = Helpers\to_iso_date( $start );
		$end_iso   = $end ? Helpers\to_iso_date( $end ) : $start_iso;

		if ( '' !== $start_iso ) {
			$lines[] = 'BEGIN:VEVENT';
			$lines[] = 'UID:' . $slug . '@' . $site;
			$lines[] = 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' );
			$lines[] = 'DTSTART;VALUE=DATE:' . str_replace( '-', '', $start_iso );
			// All-day VEVENT DTEND is exclusive – add a day.
			$end_plus = (int) ( strtotime( $end_iso . ' + 1 day' ) ?: 0 );
			$lines[]  = 'DTEND;VALUE=DATE:' . gmdate( 'Ymd', $end_plus );
			$lines[] = 'SUMMARY:' . self::escape( get_the_title( $event ) );
			if ( $loc ) {
				$lines[] = 'LOCATION:' . self::escape( $loc );
			}
			$desc = wp_strip_all_tags( $event->post_content );
			if ( $reg ) {
				$desc = trim( $desc . "\n\n" . __( 'Register: ', 'stgl' ) . $reg );
				$lines[] = 'URL:' . self::escape( $reg );
			}
			if ( $desc ) {
				$lines[] = 'DESCRIPTION:' . self::escape( $desc );
			}
			$lines[] = 'END:VEVENT';
		}

		// Per-session VEVENTs (with concrete times).
		$presentations = new \WP_Query( [
			'post_type'      => Post_Types::CPT_PRESENTATION,
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'orderby'        => 'meta_value',
			'meta_key'       => 'stgl_presenter_time', // phpcs:ignore
			'order'          => 'ASC',
			'tax_query'      => [
				[ 'taxonomy' => Post_Types::TAX_EVENT, 'field' => 'slug', 'terms' => $slug ],
			],
		] );

		foreach ( $presentations->posts as $p ) {
			$time   = (string) get_post_meta( $p->ID, 'stgl_presenter_time', true );
			$length = (int) get_post_meta( $p->ID, 'stgl_presenter_lenght', true );
			if ( '' === $start_iso || '' === $time ) {
				continue;
			}
			$dt_start_local = $start_iso . ' ' . $time;
			$ts             = strtotime( $dt_start_local );
			if ( ! $ts ) {
				continue;
			}
			$dur     = $length > 0 ? $length : 30;
			$ts_end  = $ts + ( $dur * 60 );
			$tz_str  = wp_timezone_string();

			$lines[] = 'BEGIN:VEVENT';
			$lines[] = 'UID:talk-' . $p->ID . '@' . $site;
			$lines[] = 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' );
			$lines[] = 'DTSTART;TZID=' . $tz_str . ':' . wp_date( 'Ymd\THis', $ts );
			$lines[] = 'DTEND;TZID=' . $tz_str . ':' . wp_date( 'Ymd\THis', $ts_end );

			$presenter = (string) get_post_meta( $p->ID, 'stgl_presenter_name', true );
			$company   = (string) get_post_meta( $p->ID, 'stgl_presenter_company', true );
			$summary   = get_the_title( $p );
			if ( $presenter ) {
				$summary .= ' – ' . $presenter . ( $company ? ' (' . $company . ')' : '' );
			}
			$lines[] = 'SUMMARY:' . self::escape( $summary );
			if ( $loc ) {
				$lines[] = 'LOCATION:' . self::escape( $loc );
			}
			$desc = wp_strip_all_tags( $p->post_content );
			if ( $desc ) {
				$lines[] = 'DESCRIPTION:' . self::escape( $desc );
			}
			$lines[] = 'END:VEVENT';
		}

		$lines[] = 'END:VCALENDAR';
		// RFC 5545 line endings are CRLF.
		return implode( "\r\n", $lines ) . "\r\n";
	}

	/**
	 * Escape a value per RFC 5545 § 3.3.11.
	 */
	private static function escape( string $text ): string {
		$text = str_replace( [ "\\", "\r\n", "\n", ",", ";" ], [ "\\\\", "\\n", "\\n", "\\,", "\\;" ], $text );
		// Hard wrap at 73 chars, continuation lines start with a single space.
		$wrapped = '';
		$len     = strlen( $text );
		for ( $i = 0; $i < $len; $i += 73 ) {
			if ( $i > 0 ) {
				$wrapped .= "\r\n ";
			}
			$wrapped .= substr( $text, $i, 73 );
		}
		return $wrapped;
	}
}
