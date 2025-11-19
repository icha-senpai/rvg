<?php

namespace ElementPack;

/**
 * Notices class
 */
class Notices {

	private static $notices = [];

	private static $instance;

	public static function get_instance() {
		if (!isset(self::$instance)) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	public function __construct() {

		add_action('admin_notices', [$this, 'show_api_notices']);
		add_action('admin_notices', [$this, 'show_notices']);
		add_action('wp_ajax_element-pack-notices', [$this, 'dismiss']);

	}

	/**
	 * Fetch and display notices from API
	 */
	public function show_api_notices() {
		$notices = $this->get_api_notices_data();
		

		
		if (is_array($notices)) {
			foreach ($notices as $index => $notice) {
				// Check if notice is enabled and within date range
				if ($this->should_show_notice($notice)) {
					$notice_id = isset($notice->id) ? $notice->id : 'api-notice-' . $index;
					
					// Check if this notice should be shown (not dismissed)
					if (!$this->is_notice_dismissed($notice_id)) {
						self::add_notice([
							'id' => 'api-notice-' . $notice_id,
							'type' => isset($notice->type) ? $notice->type : 'info',
							'dismissible' => true,
							'dismissible-time' => isset($notice->visible_expired) ? $notice->visible_expired : HOUR_IN_SECONDS * 6,
							'html_message' => $this->render_api_notice($notice),
						]);
					}
				}
			}
		}
	}

	/**
	 * Get Remote Notices Data from API
	 *
	 * @return array|mixed
	 */
	private function get_api_notices_data() {
		// API endpoint for notices - you can change this to your actual endpoint
		$api_url = 'https://store.bdthemes.com/api/notices/api-data-by-product';

		$response = wp_remote_get($api_url, [
			'timeout' => 30,
			'headers' => [
				'Accept' => 'application/json',
			],
		]);

		if (is_wp_error($response)) {
			return [];
		}

		$response_code = wp_remote_retrieve_response_code($response);

		$response_body = wp_remote_retrieve_body($response);

		$notices = json_decode($response_body);
		
		if( isset($notices->api) && isset($notices->api->{'element-pack'}) ) {
			return $notices->api->{'element-pack'};
		}

		return [];
	}

	/**
	 * Check if a notice is dismissed
	 *
	 * @param string $notice_id
	 * @return bool
	 */
	private function is_notice_dismissed($notice_id) {
		$dismissed_notices = get_user_meta(get_current_user_id(), 'element_pack_dismissed_notices', true);
		
		if (!is_array($dismissed_notices)) {
			$dismissed_notices = [];
		}

		$is_dismissed = in_array($notice_id, $dismissed_notices);

		return $is_dismissed;
	}

	/**
	 * Check if a notice should be shown based on its enabled status and date range.
	 *
	 * @param object $notice The notice data from the API.
	 * @return bool True if the notice should be shown, false otherwise.
	 */
	private function should_show_notice($notice) {
		// Development override - set to true to bypass date checks for testing
		$development_mode = false; // Set to true to bypass date checks
		
		if ($development_mode) {
			return true;
		}
		
		// Check if the notice is enabled
		if (!isset($notice->is_enabled) || !$notice->is_enabled) {
			return false;
		}

		// Check if the notice has a start date and end date
		if (!isset($notice->start_date) || !isset($notice->end_date)) {
			return false;
		}

		// Get timezone from notice or default to UTC
		$timezone = isset($notice->timezone) ? $notice->timezone : 'UTC';
		
		// Create DateTime objects with proper timezone (using global namespace)
		$start_date = new \DateTime($notice->start_date, new \DateTimeZone($timezone));
		$end_date = new \DateTime($notice->end_date, new \DateTimeZone($timezone));
		$current_date = new \DateTime('now', new \DateTimeZone($timezone));

		// Convert to timestamps for comparison
		$start_timestamp = $start_date->getTimestamp();
		$end_timestamp = $end_date->getTimestamp();
		$current_timestamp = $current_date->getTimestamp();

		// Check if the current date is within the start and end dates
		if ($current_timestamp < $start_timestamp || $current_timestamp > $end_timestamp) {
			return false;
		}

		// Check if notice should be visible after a certain time
		if (isset($notice->visible_after) && $notice->visible_after > 0) {
			$visible_after_timestamp = $start_timestamp + $notice->visible_after;
			if ($current_timestamp < $visible_after_timestamp) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Render API notice HTML
	 *
	 * @param object $notice
	 * @return string
	 */
	private function render_api_notice($notice) {
		ob_start();
		
		// Add custom CSS if provided
		if (isset($notice->custom_css) && !empty($notice->custom_css)) {
			echo '<style>' . wp_kses_post($notice->custom_css) . '</style>';
		}
		
		// Prepare background styles
		$background_style = '';
		$wrapper_classes = 'bdt-notice-wrapper';
		
		if (isset($notice->background_color) && !empty($notice->background_color)) {
			$background_style .= 'background-color: ' . esc_attr($notice->background_color) . ';';
		}
		
		if (isset($notice->image) && !empty($notice->image)) {
			$background_style .= 'background-image: url(' . esc_url($notice->image) . ');';
			$wrapper_classes .= ' has-background-image';
		}
		
		?>
		<div class="<?php echo esc_attr($wrapper_classes); ?>" <?php echo $background_style ? 'style="' . $background_style . '"' : ''; ?>>
			
			
			<?php $title = (isset($notice->title) && !empty($notice->title)) ? $notice->title : ''; ?>

			<div class="bdt-plugin-logo-wrapper">
				<img height="auto" width="40" src="<?php echo esc_url(BDTEP_ASSETS_URL); ?>images/logo.svg" alt="Element Pack Logo">
			</div>

			<div class="bdt-notice-content">
				<?php if (isset($notice->logo) && !empty($notice->logo)) : ?>
					<div class="bdt-notice-logo-wrapper">
						<img width="100" src="<?php echo esc_url($notice->logo); ?>" alt="Logo">
					</div>
				<?php endif; ?>
				<div class="bdt-notice-title-description">
					<?php if (isset($title) && !empty($title)) : ?>
						<h2 class="bdt-notice-title"><?php echo wp_kses_post($title); ?></h2>
					<?php endif; ?>

					<?php if (isset($notice->content) && !empty($notice->content)) : ?>
						<div class="bdt-notice-html-content">
							<?php echo wp_kses_post($notice->content); ?>
						</div>
					<?php endif; ?>
				</div>

				<?php 
				// Only show countdown if it's enabled, has an end date, and the end date is in the future
				$show_countdown = isset($notice->show_countdown) && $notice->show_countdown && isset($notice->end_date);
				if ($show_countdown) {
					$end_timestamp = strtotime($notice->end_date);
					$current_timestamp = current_time('timestamp');
					$show_countdown = $end_timestamp > $current_timestamp;
				}
				?>
				<?php if ($show_countdown) : ?>
					<div class="bdt-notice-countdown" data-end-date="<?php echo esc_attr($notice->end_date); ?>" data-timezone="<?php echo esc_attr($notice->timezone ? $notice->timezone : 'UTC'); ?>">
						<div class="countdown-timer">Loading...</div>
					</div>
				<?php endif; ?>

				<?php if (isset($notice->link) && !empty($notice->link)) : ?>
					<div class="bdt-notice-btn">
						<a href="<?php echo esc_url($notice->link); ?>" target="_blank">
							<div class="nm-notice-btn">
							    <?php echo isset($notice->button_text) ? esc_html($notice->button_text) : 'Read More'; ?>
								<span class="dashicons dashicons-arrow-right-alt"></span>
								<div class="zolo-star zolo-star-1">✦</div><div class="zolo-star zolo-star-2">✦</div><div class="zolo-star zolo-star-3">✦</div><div class="zolo-star zolo-star-4">✦</div><div class="zolo-star zolo-star-5">✦</div><div class="zolo-star zolo-star-6">✦</div>
							</div>
						</a>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function add_notice($args = []) {
		if (is_array($args)) {
			self::$notices[] = $args;
		}
	}

	/**
	 * Dismiss Notice.
	 */
	public function dismiss() {
		$nonce = (isset($_POST['_wpnonce'])) ? sanitize_text_field($_POST['_wpnonce']) : '';
		$id   = (isset($_POST['id'])) ? esc_attr($_POST['id']) : '';
		$time = (isset($_POST['time'])) ? esc_attr($_POST['time']) : '';
		$meta = (isset($_POST['meta'])) ? esc_attr($_POST['meta']) : '';

		if ( ! wp_verify_nonce($nonce, 'element-pack') ) {
			wp_send_json_error();
		}

		if ( ! current_user_can('manage_options') ) {
			wp_send_json_error();
		}

		/**
		 * Valid inputs?
		 */
		if (!empty($id)) {
			// Handle API notices dismissal
			if (strpos($id, 'api-notice-') === 0) {
				$notice_id = str_replace('api-notice-', '', $id);
				$this->dismiss_api_notice($notice_id);
			} else {
				// Handle regular notices
				if ('user' === $meta) {
					update_user_meta(get_current_user_id(), $id, true);
				} else {
					set_transient($id, true, $time);
				}
			}

			wp_send_json_success();
		}

		wp_send_json_error();
	}

	/**
	 * Dismiss API notice
	 *
	 * @param string $notice_id
	 */
	private function dismiss_api_notice($notice_id) {
		$dismissed_notices = get_user_meta(get_current_user_id(), 'element_pack_dismissed_notices', true);
		
		if (!is_array($dismissed_notices)) {
			$dismissed_notices = [];
		}

		if (!in_array($notice_id, $dismissed_notices)) {
			$dismissed_notices[] = $notice_id;
			update_user_meta(get_current_user_id(), 'element_pack_dismissed_notices', $dismissed_notices);
		}
	}

	/**
	 * Notice Types
	 */
	public function show_notices() {

		$defaults = [
			'id'               => '',
			'type'             => 'info',
			'show_if'          => true,
			'title'            => '',
			'message'          => '',
			'class'            => 'element-pack-notice',
			'dismissible'      => false,
			'dismissible-meta' => 'transient',
			'dismissible-time' => WEEK_IN_SECONDS,
			'data'             => '',
			'action_link'      => '',
		];

		foreach (self::$notices as $key => $notice) {

			$notice = wp_parse_args($notice, $defaults);

			$classes = ['notice'];

			$classes[] = $notice['class'];
			if (isset($notice['type'])) {
				$classes[] = 'notice-' . $notice['type'];
			}

			// Is notice dismissible?
			if (true === $notice['dismissible']) {
				$classes[] = 'is-dismissible';

				// Dismissable time.
				$notice['data'] = ' dismissible-time=' . esc_attr($notice['dismissible-time']) . ' ';
			}

			// Notice ID.
			$notice_id    = 'element-pack-notice-id-' . $notice['id'];
			$notice['id'] = $notice_id;
			if (!isset($notice['id'])) {
				$notice_id    = 'element-pack-notice-id-' . $notice['id'];
				$notice['id'] = $notice_id;
			} else {
				$notice_id = $notice['id'];
			}

			$notice['classes'] = implode(' ', $classes);

			// User meta.
			$notice['data'] .= ' dismissible-meta=' . esc_attr($notice['dismissible-meta']) . ' ';
			if ('user' === $notice['dismissible-meta']) {
				$expired = get_user_meta(get_current_user_id(), $notice_id, true);
			} elseif ('transient' === $notice['dismissible-meta']) {
				$expired = get_transient($notice_id);
			}

			// Notices visible after transient expire.
			if (isset($notice['show_if'])) {

				if (true === $notice['show_if']) {

					// Is transient expired?
					if (false === $expired || empty($expired)) {
						self::notice_layout($notice);
					}
				}
			} else {

				// No transient notices.
				self::notice_layout($notice);
			}
		}
	}

	/**
	 * Notice layout
	 * @param  array $notice Notice notice_layout.
	 * @return void
	 */
	public static function __old__notice_layout($notice = []) {

?>
		<div id="<?php echo esc_attr($notice['id']); ?>" class="<?php echo esc_attr($notice['classes']); ?>" <?php echo esc_attr($notice['data']); ?>>
			<p>
				<?php echo wp_kses_post($notice['message']); ?>
			</p>
		</div>
	<?php
	}

	/**
	 * New Notice Layout
	 * @param  array $notice Notice notice_layout.
	 * @return void
	 * @since 6.11.3
	 */

	public static function notice_layout($notice = []) {

		if( isset($notice['html_message']) && ! empty($notice['html_message']) ) {
			self::new_notice_layout($notice);
			return;
		}

	?>
		<div id="<?php echo esc_attr($notice['id']); ?>" class="<?php echo esc_attr($notice['classes']); ?>" <?php echo esc_attr($notice['data']); ?>>
			<div class="bdt-notice-wrapper">
				<div class="bdt-notice-icon-wrapper">
					<img height="25" width="25" src="<?php echo esc_url (BDTEP_ASSETS_URL ); ?>images/logo.svg">
				</div>

				<div class="bdt-notice-content">
					<?php if (isset($notice['title']) && !empty($notice['title'])) : ?>
						<h2 class="bdt-notice-title"><?php echo wp_kses_post($notice['title']); ?></h2>
					<?php endif; ?>

					<p class="bdt-notice-text"><?php echo wp_kses_post($notice['message']); ?></p>

					<?php if (isset($notice['action_link']) && !empty($notice['action_link'])) : ?>
						<div class="bdt-notice-btn">
							<a href="#">Renew Now</a>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
<?php
	}

	public static function new_notice_layout( $notice = [] ) {

		?>
		<div id="<?php echo esc_attr( $notice['id'] ); ?>" class="<?php echo esc_attr( $notice['classes'] ); ?>" <?php echo esc_attr( $notice['data'] ); ?>>

				
			<?php 
				echo wp_kses_post( $notice['html_message'] );
			?>
			


		</div>
		<?php
	}
}

Notices::get_instance();
