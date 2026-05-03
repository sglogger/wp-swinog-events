<?php
/**
 * Meta boxes for events, presentations and sponsors.
 *
 * All meta keys match v0.x exactly so existing data is editable without
 * migration. Save handlers run through proper nonce/capability checks and
 * use type-aware sanitisation.
 *
 * @package Stgl\SwinogEvents
 */

declare( strict_types = 1 );

namespace Stgl\SwinogEvents;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Meta_Boxes {

	private const NONCE_FIELD = 'stgl_meta_nonce';
	private const NONCE_ACTION = 'stgl_save_meta';

	public function register(): void {
		add_action( 'add_meta_boxes', [ $this, 'add' ] );
		add_action( 'save_post', [ $this, 'save' ], 10, 2 );

		// Make the post-edit form upload-capable (was an inline filter before).
		add_action( 'post_edit_form_tag', [ $this, 'enable_multipart_form' ] );

		// Custom title placeholders.
		add_filter( 'enter_title_here', [ $this, 'title_placeholder' ], 10, 2 );

		// Re-label the featured image meta-box on sponsors/presentations.
		add_action( 'admin_head', [ $this, 'tweak_featured_image_label' ] );
	}

	/* ------------------------------------------------------------------ */

	public function add(): void {
		add_meta_box(
			'stgl_presentation_details',
			__( 'SwiNOG Presentation Details', 'stgl' ),
			[ $this, 'render_presentation' ],
			Post_Types::CPT_PRESENTATION,
			'normal',
			'high'
		);

		add_meta_box(
			'stgl_presentation_attachment',
			__( 'Presentation File (Slides / PDF)', 'stgl' ),
			[ $this, 'render_attachment' ],
			Post_Types::CPT_PRESENTATION,
			'side',
			'high'
		);

		add_meta_box(
			'stgl_sponsor_details',
			__( 'SwiNOG Sponsor Details', 'stgl' ),
			[ $this, 'render_sponsor' ],
			Post_Types::CPT_SPONSOR,
			'normal',
			'high'
		);
	}

	public function enable_multipart_form( $post ): void {
		$post_type = $post instanceof \WP_Post ? $post->post_type : '';
		if ( Post_Types::CPT_PRESENTATION === $post_type ) {
			echo ' enctype="multipart/form-data"';
		}
	}

	public function title_placeholder( string $title, $post = null ): string {
		if ( ! $post instanceof \WP_Post ) {
			return $title;
		}
		switch ( $post->post_type ) {
			case Post_Types::CPT_SPONSOR:
				return __( 'Enter company name', 'stgl' );
			case Post_Types::CPT_PRESENTATION:
				return __( 'Enter presentation / talk title', 'stgl' );
		}
		return $title;
	}

	public function tweak_featured_image_label(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}
		if ( Post_Types::CPT_SPONSOR === $screen->post_type ) {
			remove_meta_box( 'postimagediv', Post_Types::CPT_SPONSOR, 'side' );
			add_meta_box( 'postimagediv', __( 'Company Logo', 'stgl' ), 'post_thumbnail_meta_box', Post_Types::CPT_SPONSOR, 'side', 'high' );
		} elseif ( Post_Types::CPT_PRESENTATION === $screen->post_type ) {
			remove_meta_box( 'postimagediv', Post_Types::CPT_PRESENTATION, 'side' );
			add_meta_box( 'postimagediv', __( 'Presenter Portrait', 'stgl' ), 'post_thumbnail_meta_box', Post_Types::CPT_PRESENTATION, 'side', 'low' );
		}
	}

	/* ------------------------------------------------------------------ */
	/*  Render – Presentation                                             */
	/* ------------------------------------------------------------------ */

	public function render_presentation( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$m = self::meta( $post->ID );
		?>
		<table class="form-table stgl-meta-table">
			<tbody>
			<tr>
				<th><label for="stgl_presenter_name"><?php esc_html_e( 'Presenter name(s)', 'stgl' ); ?></label></th>
				<td><input type="text" id="stgl_presenter_name" name="stgl_presenter_name" value="<?php echo esc_attr( $m['stgl_presenter_name'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="stgl_presenter_company"><?php esc_html_e( 'Company', 'stgl' ); ?></label></th>
				<td><input type="text" id="stgl_presenter_company" name="stgl_presenter_company" value="<?php echo esc_attr( $m['stgl_presenter_company'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="stgl_presenter_email"><?php esc_html_e( 'Presenter email', 'stgl' ); ?></label></th>
				<td>
					<input type="email" id="stgl_presenter_email" name="stgl_presenter_email" value="<?php echo esc_attr( $m['stgl_presenter_email'] ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Internal use only – will not be published.', 'stgl' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Publish presentation?', 'stgl' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="stgl_presenter_publish" value="1" <?php checked( ! empty( $m['stgl_presenter_publish'] ) ); ?> />
						<?php esc_html_e( 'OK to publish slides on the website', 'stgl' ); ?>
					</label>
					<input type="hidden" name="stgl_presenter_publish_present" value="1" />
				</td>
			</tr>
			<tr>
				<th><label for="stgl_presenter_videourl"><?php esc_html_e( 'Video URL', 'stgl' ); ?></label></th>
				<td>
					<input type="url" id="stgl_presenter_videourl" name="stgl_presenter_videourl" value="<?php echo esc_attr( $m['stgl_presenter_videourl'] ); ?>" class="regular-text" placeholder="https://www.youtube.com/watch?v=..." />
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Publish video?', 'stgl' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="stgl_presenter_publish_video" value="1" <?php checked( ! empty( $m['stgl_presenter_publish_video'] ) ); ?> />
						<?php esc_html_e( 'OK to publish the video link', 'stgl' ); ?>
					</label>
					<input type="hidden" name="stgl_presenter_publish_video_present" value="1" />
				</td>
			</tr>
			<tr>
				<th><label for="stgl_presenter_time"><?php esc_html_e( 'Schedule', 'stgl' ); ?></label></th>
				<td><input type="time" id="stgl_presenter_time" name="stgl_presenter_time" value="<?php echo esc_attr( $m['stgl_presenter_time'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="stgl_presenter_lenght"><?php esc_html_e( 'Length (minutes)', 'stgl' ); ?></label></th>
				<td>
					<input type="number" min="0" step="1" id="stgl_presenter_lenght" name="stgl_presenter_lenght" value="<?php echo esc_attr( $m['stgl_presenter_lenght'] ); ?>" class="small-text" />
				</td>
			</tr>
			<tr>
				<th><label for="stgl_presenter_bio"><?php esc_html_e( 'Speaker bio', 'stgl' ); ?></label></th>
				<td><textarea id="stgl_presenter_bio" name="stgl_presenter_bio" rows="3" class="large-text"><?php echo esc_textarea( $m['stgl_presenter_bio'] ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="stgl_presenter_twitter"><?php esc_html_e( 'Twitter/X handle', 'stgl' ); ?></label></th>
				<td><input type="text" id="stgl_presenter_twitter" name="stgl_presenter_twitter" value="<?php echo esc_attr( $m['stgl_presenter_twitter'] ); ?>" class="regular-text" placeholder="@handle" /></td>
			</tr>
			<tr>
				<th><label for="stgl_presenter_linkedin"><?php esc_html_e( 'LinkedIn URL', 'stgl' ); ?></label></th>
				<td><input type="url" id="stgl_presenter_linkedin" name="stgl_presenter_linkedin" value="<?php echo esc_attr( $m['stgl_presenter_linkedin'] ); ?>" class="regular-text" /></td>
			</tr>
			</tbody>
		</table>
		<?php
	}

	public function render_attachment( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$attachment_id = (int) get_post_meta( $post->ID, '_stgl_presentation_attachment_id', true );
		$legacy        = get_post_meta( $post->ID, 'wp_custom_attachment', true );

		// Prefer modern Media-Library attachment, fall back to legacy.
		$file_url = '';
		$filename = '';
		if ( $attachment_id ) {
			$file_url = wp_get_attachment_url( $attachment_id );
			$filename = $file_url ? basename( $file_url ) : '';
		} elseif ( is_array( $legacy ) && ! empty( $legacy['url'] ) ) {
			$file_url = $legacy['url'];
			$filename = basename( $file_url );
		}
		?>
		<div class="stgl-attachment">
			<input type="hidden" id="stgl_presentation_attachment_id" name="stgl_presentation_attachment_id" value="<?php echo esc_attr( (string) $attachment_id ); ?>" />

			<p>
				<button type="button" class="button" id="stgl-pick-attachment">
					<?php $attachment_id ? esc_html_e( 'Replace file…', 'stgl' ) : esc_html_e( 'Select / upload file…', 'stgl' ); ?>
				</button>
			</p>

			<p class="stgl-attachment-current" <?php echo $file_url ? '' : 'style="display:none"'; ?>>
				<strong><?php esc_html_e( 'Current file:', 'stgl' ); ?></strong><br />
				<a href="<?php echo esc_url( $file_url ); ?>" target="_blank" rel="noopener" id="stgl-attachment-link"><?php echo esc_html( $filename ); ?></a><br />
				<button type="button" class="button-link delete" id="stgl-attachment-remove"><?php esc_html_e( 'Remove file', 'stgl' ); ?></button>
			</p>

			<p class="description">
				<?php esc_html_e( 'Allowed: PDF, PPT/PPTX, DOC/DOCX, ZIP, MP4, TXT.', 'stgl' ); ?>
			</p>

			<details>
				<summary style="cursor:pointer"><?php esc_html_e( 'Or upload directly', 'stgl' ); ?></summary>
				<p style="margin-top:.5em">
					<input type="file" name="wp_custom_attachment" />
				</p>
			</details>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/*  Render – Sponsor                                                  */
	/* ------------------------------------------------------------------ */

	public function render_sponsor( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$levels = get_option( Installer::OPTION_SPONSOR_LEVELS, Installer::default_sponsor_levels() );
		$m      = self::meta( $post->ID );
		?>
		<table class="form-table stgl-meta-table">
			<tbody>
			<tr>
				<th><label for="stgl_sponsor_level"><?php esc_html_e( 'Level', 'stgl' ); ?></label></th>
				<td>
					<select id="stgl_sponsor_level" name="stgl_sponsor_level">
						<?php foreach ( $levels as $weight => $label ) : ?>
							<option value="<?php echo esc_attr( (string) $weight ); ?>" <?php selected( (string) $m['stgl_sponsor_level'], (string) $weight ); ?>>
								<?php echo esc_html( $label !== '' ? $label : __( '— none —', 'stgl' ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="stgl_sponsor_url"><?php esc_html_e( 'Website URL', 'stgl' ); ?></label></th>
				<td><input type="url" id="stgl_sponsor_url" name="stgl_sponsor_url" value="<?php echo esc_attr( $m['stgl_sponsor_url'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="stgl_sponsor_notes"><?php esc_html_e( 'Internal notes', 'stgl' ); ?></label></th>
				<td><textarea id="stgl_sponsor_notes" name="stgl_sponsor_notes" rows="3" class="large-text"><?php echo esc_textarea( $m['stgl_sponsor_notes'] ); ?></textarea></td>
			</tr>
			</tbody>
		</table>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/*  Save                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Single save handler – runs proper checks once and dispatches.
	 */
	public function save( int $post_id, \WP_Post $post ): void {

		// Ignore autosaves & revisions.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Only act on our post types.
		$ours = [ Post_Types::CPT_PRESENTATION, Post_Types::CPT_SPONSOR ];
		if ( ! in_array( $post->post_type, $ours, true ) ) {
			return;
		}

		// Nonce check.
		$nonce = isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		// Capability check.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		switch ( $post->post_type ) {
			case Post_Types::CPT_PRESENTATION:
				$this->save_presentation( $post_id );
				break;
			case Post_Types::CPT_SPONSOR:
				$this->save_sponsor( $post_id );
				break;
		}
	}

	private function save_presentation( int $id ): void {
		$text_keys = [
			'stgl_presenter_name',
			'stgl_presenter_company',
			'stgl_presenter_email',
			'stgl_presenter_time',
			'stgl_presenter_twitter',
		];
		foreach ( $text_keys as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
			}
		}

		if ( isset( $_POST['stgl_presenter_videourl'] ) ) {
			update_post_meta( $id, 'stgl_presenter_videourl', esc_url_raw( wp_unslash( $_POST['stgl_presenter_videourl'] ) ) );
		}
		if ( isset( $_POST['stgl_presenter_linkedin'] ) ) {
			update_post_meta( $id, 'stgl_presenter_linkedin', esc_url_raw( wp_unslash( $_POST['stgl_presenter_linkedin'] ) ) );
		}
		if ( isset( $_POST['stgl_presenter_bio'] ) ) {
			update_post_meta( $id, 'stgl_presenter_bio', wp_kses_post( wp_unslash( $_POST['stgl_presenter_bio'] ) ) );
		}
		if ( isset( $_POST['stgl_presenter_lenght'] ) ) {
			update_post_meta( $id, 'stgl_presenter_lenght', max( 0, (int) $_POST['stgl_presenter_lenght'] ) );
		}

		// Checkboxes – use *_present hidden field to detect that the box was on screen.
		if ( isset( $_POST['stgl_presenter_publish_present'] ) ) {
			update_post_meta( $id, 'stgl_presenter_publish', empty( $_POST['stgl_presenter_publish'] ) ? '' : '1' );
		}
		if ( isset( $_POST['stgl_presenter_publish_video_present'] ) ) {
			update_post_meta( $id, 'stgl_presenter_publish_video', empty( $_POST['stgl_presenter_publish_video'] ) ? '' : '1' );
		}

		// Modern attachment id (set by the media-library JS picker).
		if ( isset( $_POST['stgl_presentation_attachment_id'] ) ) {
			$att_id = (int) $_POST['stgl_presentation_attachment_id'];
			if ( $att_id > 0 ) {
				update_post_meta( $id, '_stgl_presentation_attachment_id', $att_id );

				// Mirror to legacy meta for backward compat with old shortcodes/templates.
				$url  = wp_get_attachment_url( $att_id );
				$path = get_attached_file( $att_id );
				if ( $url ) {
					update_post_meta( $id, 'wp_custom_attachment', [
						'url'  => $url,
						'file' => $path ?: '',
						'type' => get_post_mime_type( $att_id ) ?: '',
					] );
				}
			} else {
				delete_post_meta( $id, '_stgl_presentation_attachment_id' );
				delete_post_meta( $id, 'wp_custom_attachment' );
			}
		}

		// Legacy direct upload (kept as a fallback for users who don't use the media picker).
		if ( ! empty( $_FILES['wp_custom_attachment']['name'] ) ) {
			$this->handle_legacy_upload( $id );
		}
	}

	/**
	 * Handle legacy direct upload via media_handle_upload (much safer than wp_upload_bits).
	 */
	private function handle_legacy_upload( int $id ): void {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$allowed = [
			'pdf'  => 'application/pdf',
			'ppt'  => 'application/vnd.ms-powerpoint',
			'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			'pps'  => 'application/vnd.ms-powerpoint',
			'ppsx' => 'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
			'doc'  => 'application/msword',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'zip'  => 'application/zip',
			'mp4'  => 'video/mp4',
			'txt'  => 'text/plain',
		];

		$check = wp_check_filetype_and_ext(
			$_FILES['wp_custom_attachment']['tmp_name'],
			$_FILES['wp_custom_attachment']['name'],
			$allowed
		);
		if ( empty( $check['type'] ) ) {
			return;
		}

		$attachment_id = media_handle_upload( 'wp_custom_attachment', $id );
		if ( is_wp_error( $attachment_id ) ) {
			return;
		}

		update_post_meta( $id, '_stgl_presentation_attachment_id', (int) $attachment_id );
		$url  = wp_get_attachment_url( (int) $attachment_id );
		$path = get_attached_file( (int) $attachment_id );
		update_post_meta( $id, 'wp_custom_attachment', [
			'url'  => $url ?: '',
			'file' => $path ?: '',
			'type' => get_post_mime_type( (int) $attachment_id ) ?: '',
		] );
	}

	private function save_sponsor( int $id ): void {
		if ( isset( $_POST['stgl_sponsor_url'] ) ) {
			update_post_meta( $id, 'stgl_sponsor_url', esc_url_raw( wp_unslash( $_POST['stgl_sponsor_url'] ) ) );
		}
		if ( isset( $_POST['stgl_sponsor_notes'] ) ) {
			update_post_meta( $id, 'stgl_sponsor_notes', wp_kses_post( wp_unslash( $_POST['stgl_sponsor_notes'] ) ) );
		}
		if ( isset( $_POST['stgl_sponsor_level'] ) ) {
			update_post_meta( $id, 'stgl_sponsor_level', (int) $_POST['stgl_sponsor_level'] );
		}
	}

	/* ------------------------------------------------------------------ */
	/*  Helpers                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Read a fixed set of meta keys for a post and always return strings,
	 * so the templates above don't have to do a million isset() checks.
	 *
	 * @return array<string, string>
	 */
	private static function meta( int $post_id ): array {
		$keys = [
			// Presentation
			'stgl_presenter_name', 'stgl_presenter_company', 'stgl_presenter_email',
			'stgl_presenter_videourl', 'stgl_presenter_publish', 'stgl_presenter_publish_video',
			'stgl_presenter_time', 'stgl_presenter_lenght',
			'stgl_presenter_bio', 'stgl_presenter_twitter', 'stgl_presenter_linkedin',
			// Sponsor
			'stgl_sponsor_url', 'stgl_sponsor_notes', 'stgl_sponsor_level',
		];
		$out = [];
		foreach ( $keys as $k ) {
			$v = get_post_meta( $post_id, $k, true );
			$out[ $k ] = is_scalar( $v ) ? (string) $v : '';
		}
		return $out;
	}
}
