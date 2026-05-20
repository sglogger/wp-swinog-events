<?php
/**
 * Self-contained GitHub release updater.
 *
 * Lets WordPress update the plugin straight from GitHub Releases — no helper
 * plugin (Git Updater etc.) required. It hooks the three core extension points:
 *
 *   - `pre_set_site_transient_update_plugins`  → advertise a newer version.
 *   - `plugins_api`                            → feed the "View details" modal.
 *   - `upgrader_source_selection`              → rename the extracted GitHub
 *                                                folder to our plugin slug.
 *
 * The GitHub API response is cached in a transient to stay well under the
 * unauthenticated rate limit (60 req/h). For a private repo, supply a token
 * via the `STGL_SWINOG_GITHUB_TOKEN` constant or the
 * `stgl_swinog_github_token` filter.
 *
 * Requirements on the release side: cut GitHub Releases whose tag is the
 * semver version matching the plugin header `Version:` (a leading "v" is fine).
 *
 * @package Stgl\SwinogEvents
 */

declare( strict_types = 1 );

namespace Stgl\SwinogEvents;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Updater {

	/** GitHub repository in "owner/name" form. */
	private const REPO = 'sglogger/wp-swinog-events';

	/** How long to cache the GitHub release lookup, in seconds. */
	private const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	/** Plugin basename, e.g. "wp-swinog-events/swinog-events.php". */
	private string $basename;

	/** Plugin directory slug, e.g. "wp-swinog-events". */
	private string $slug;

	/** Currently installed version. */
	private string $version;

	/** Transient key for the cached GitHub release payload. */
	private string $cache_key;

	public function __construct() {
		$this->basename  = STGL_SWINOG_BASENAME;
		$this->slug      = dirname( $this->basename );
		$this->version   = STGL_SWINOG_VERSION;
		$this->cache_key = 'stgl_swinog_gh_release';
	}

	public function register(): void {
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'inject_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 10, 3 );
		add_filter( 'upgrader_source_selection', [ $this, 'rename_source' ], 10, 4 );
		add_action( 'upgrader_process_complete', [ $this, 'flush_cache' ], 10, 0 );
	}

	// -------------------------------------------------------------------------
	// Core hooks
	// -------------------------------------------------------------------------

	/**
	 * Add our plugin to the list of available updates when GitHub is ahead.
	 *
	 * @param object $transient The `update_plugins` site transient.
	 * @return object
	 */
	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$release = $this->get_release();
		if ( null === $release ) {
			return $transient;
		}

		$item = $this->build_response( $release );

		if ( version_compare( $release['version'], $this->version, '>' ) ) {
			$transient->response[ $this->basename ] = $item;
		} else {
			// Reported so WordPress shows "up to date" rather than nothing.
			$transient->no_update[ $this->basename ] = $item;
		}

		return $transient;
	}

	/**
	 * Supply the data shown in the "View version X.Y.Z details" modal.
	 *
	 * @param false|object|array $result The result object/array, or false.
	 * @param string             $action The API action being performed.
	 * @param object             $args   Arguments including the requested slug.
	 * @return false|object|array
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}
		if ( ! isset( $args->slug ) || $args->slug !== $this->slug ) {
			return $result;
		}

		$release = $this->get_release();
		if ( null === $release ) {
			return $result;
		}

		$info = [
			'name'           => 'SwiNOG Events',
			'slug'           => $this->slug,
			'version'        => $release['version'],
			'author'         => '<a href="https://www.glogger.ch">Steven Glogger</a>',
			'homepage'       => 'https://github.com/' . self::REPO,
			'download_link'  => $release['package'],
			'requires'       => $release['requires'],
			'requires_php'   => $release['requires_php'],
			'tested'         => $release['tested'],
			'last_updated'   => $release['published_at'],
			'sections'       => [
				'description' => esc_html__( 'Manage SwiNOG presentations and sponsors.', 'stgl' ),
				'changelog'   => $release['changelog'],
			],
		];

		return (object) $info;
	}

	/**
	 * GitHub zips extract to a versioned/hashed folder; rename it to our slug
	 * so the upgraded files land in the correct plugin directory.
	 *
	 * @param string $source        Path to the extracted source folder.
	 * @param string $remote_source Path to the parent of the extracted folder.
	 * @param object $upgrader      The WP_Upgrader instance.
	 * @param array  $hook_extra    Extra args, includes the target plugin file.
	 * @return string|\WP_Error
	 */
	public function rename_source( $source, $remote_source, $upgrader, $hook_extra = [] ) {
		global $wp_filesystem;

		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) {
			return $source;
		}

		$desired = trailingslashit( $remote_source ) . $this->slug . '/';
		if ( trailingslashit( $source ) === $desired ) {
			return $source;
		}

		if ( $wp_filesystem && $wp_filesystem->move( $source, $desired, true ) ) {
			return $desired;
		}

		return new \WP_Error(
			'stgl_swinog_rename_failed',
			esc_html__( 'Could not rename the downloaded update folder.', 'stgl' )
		);
	}

	/**
	 * Drop the cached release so the next check re-queries GitHub.
	 */
	public function flush_cache(): void {
		delete_transient( $this->cache_key );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Shape the update object WordPress expects in the transient.
	 *
	 * @param array<string, mixed> $release Normalised release data.
	 * @return object
	 */
	private function build_response( array $release ): object {
		return (object) [
			'slug'        => $this->slug,
			'plugin'      => $this->basename,
			'new_version' => $release['version'],
			'url'         => 'https://github.com/' . self::REPO,
			'package'     => $release['package'],
			'tested'      => $release['tested'],
			'requires'    => $release['requires'],
			'requires_php' => $release['requires_php'],
		];
	}

	/**
	 * Fetch (and cache) the latest GitHub release, normalised to our shape.
	 *
	 * @return array<string, mixed>|null Null when the lookup fails.
	 */
	private function get_release(): ?array {
		$cached = get_transient( $this->cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::REPO . '/releases/latest',
			[
				'timeout' => 10,
				'headers' => $this->api_headers(),
			]
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			// Cache a short negative result to avoid hammering the API.
			set_transient( $this->cache_key, [], 30 * MINUTE_IN_SECONDS );
			return null;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			set_transient( $this->cache_key, [], 30 * MINUTE_IN_SECONDS );
			return null;
		}

		$release = $this->normalise( $body );
		set_transient( $this->cache_key, $release, self::CACHE_TTL );

		return $release;
	}

	/**
	 * Turn GitHub's release JSON into the fields we actually use.
	 *
	 * @param array<string, mixed> $body Decoded GitHub release object.
	 * @return array<string, mixed>
	 */
	private function normalise( array $body ): array {
		$version = ltrim( (string) $body['tag_name'], 'vV' );

		// Prefer an uploaded .zip asset; fall back to the source zipball.
		$package = (string) ( $body['zipball_url'] ?? '' );
		if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
			foreach ( $body['assets'] as $asset ) {
				if ( isset( $asset['browser_download_url'], $asset['name'] )
					&& '.zip' === strtolower( substr( (string) $asset['name'], -4 ) ) ) {
					$package = (string) $asset['browser_download_url'];
					break;
				}
			}
		}

		return [
			'version'      => $version,
			'package'      => $package,
			'changelog'    => $this->format_changelog( (string) ( $body['body'] ?? '' ) ),
			'published_at' => isset( $body['published_at'] )
				? gmdate( 'Y-m-d', strtotime( (string) $body['published_at'] ) )
				: '',
			// These mirror the plugin headers; bump here if the headers change.
			'requires'     => '6.0',
			'requires_php' => '7.4',
			'tested'       => get_bloginfo( 'version' ),
		];
	}

	/**
	 * Render the release notes (Markdown) as the simple HTML the modal expects.
	 *
	 * @param string $markdown Raw release body.
	 * @return string
	 */
	private function format_changelog( string $markdown ): string {
		if ( '' === trim( $markdown ) ) {
			return esc_html__( 'See the GitHub release notes for details.', 'stgl' );
		}
		// Minimal, safe conversion: escape, then turn list/heading markers and
		// line breaks into HTML. Good enough for the details modal.
		$lines = preg_split( '/\r\n|\r|\n/', $markdown );
		$html  = '';
		$in_list = false;
		foreach ( (array) $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}
			if ( preg_match( '/^[-*]\s+(.*)$/', $line, $m ) ) {
				$html   .= ( $in_list ? '' : '<ul>' ) . '<li>' . esc_html( $m[1] ) . '</li>';
				$in_list = true;
				continue;
			}
			if ( $in_list ) {
				$html  .= '</ul>';
				$in_list = false;
			}
			if ( preg_match( '/^#{1,6}\s+(.*)$/', $line, $m ) ) {
				$html .= '<h4>' . esc_html( $m[1] ) . '</h4>';
			} else {
				$html .= '<p>' . esc_html( $line ) . '</p>';
			}
		}
		if ( $in_list ) {
			$html .= '</ul>';
		}
		return $html;
	}

	/**
	 * Request headers for the GitHub API, including auth when a token exists.
	 *
	 * @return array<string, string>
	 */
	private function api_headers(): array {
		$headers = [
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => 'wp-swinog-events',
		];

		$token = '';
		if ( defined( 'STGL_SWINOG_GITHUB_TOKEN' ) && STGL_SWINOG_GITHUB_TOKEN ) {
			$token = (string) STGL_SWINOG_GITHUB_TOKEN;
		}
		/** Allow a token to be supplied at runtime (e.g. for a private repo). */
		$token = (string) apply_filters( 'stgl_swinog_github_token', $token );

		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		return $headers;
	}
}
