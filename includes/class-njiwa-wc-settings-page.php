<?php
/**
 * WooCommerce, Settings, Njiwa.
 *
 * It lives inside WooCommerce's own settings rather than in a menu of its own,
 * because that is where somebody configuring a shop is already standing.
 *
 * Every field carries its own description. A setting whose meaning has to be
 * looked up somewhere else is a setting people get wrong.
 */

defined( 'ABSPATH' ) || exit;

class Njiwa_WC_Settings_Page extends WC_Settings_Page {

	public function __construct() {
		$this->id    = 'njiwa';
		$this->label = __( 'Njiwa', 'njiwa-for-woocommerce' );

		parent::__construct();

		add_action( 'woocommerce_admin_field_njiwa_check', array( $this, 'render_check' ) );

		// The two wp_ajax_ handlers are NOT registered here. This constructor
		// runs only when WooCommerce builds its list of settings pages, which
		// it does on the settings screen and not during an admin-ajax request,
		// so a handler added here would never exist at the moment the button
		// is pressed: admin-ajax would answer "0" and the notice would read
		// undefined. They are registered in njiwa_wc_start() instead, which
		// runs on plugins_loaded for every admin request including that one.
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	protected function get_settings_for_default_section() {
		$settings = array(
			array(
				'type' => 'title',
				'id'   => 'njiwa_wc_connection',
				'name' => __( 'Connection', 'njiwa-for-woocommerce' ),
				'desc' => __( 'Njiwa sends the WhatsApp messages. Your shop tells it when.', 'njiwa-for-woocommerce' ),
			),
			array(
				'id'      => 'njiwa_wc_enabled',
				'name'    => __( 'Send WhatsApp messages', 'njiwa-for-woocommerce' ),
				'desc'    => __( 'The master switch. Turn it off and this plugin stops sending anything at all, without losing your key, your numbers or your templates. Orders carry on exactly as before.', 'njiwa-for-woocommerce' ),
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'id'      => 'njiwa_wc_api_key',
				'name'    => __( 'API key', 'njiwa-for-woocommerce' ),
				'desc_tip' => __( 'Create one in the Njiwa console under API keys, then paste it here.', 'njiwa-for-woocommerce' ),
				'desc'    => __( 'A key beginning <code>sk_test_</code> checks and stores every message and delivers nothing, which is what you want while you set this up. A key beginning <code>sk_live_</code> sends to real phones. The console shows a key once and keeps only its fingerprint, so a lost key is replaced rather than recovered.', 'njiwa-for-woocommerce' ),
				'type'    => 'password',
				'default' => '',
				'css'     => 'min-width:340px;',
			),
			array(
				'id'      => 'njiwa_wc_base_url',
				'name'    => __( 'Njiwa address', 'njiwa-for-woocommerce' ),
				'desc'    => __( 'Leave this exactly as it is. It exists for shops that have been given their own Njiwa address, and changing it otherwise stops messages reaching anybody.', 'njiwa-for-woocommerce' ),
				'type'    => 'text',
				'default' => Njiwa_WC_Client::DEFAULT_BASE_URL,
				'css'     => 'min-width:340px;',
			),
			array(
				'id'      => 'njiwa_wc_from',
				'name'    => __( 'Send from', 'njiwa-for-woocommerce' ),
				'desc'    => __( 'Which of your linked WhatsApp numbers these messages come from. Digits only, in full international form, such as 254712345678. Leave it empty to use the number marked default in the console, which is the right answer if you have one number.', 'njiwa-for-woocommerce' ),
				'type'    => 'text',
				'default' => '',
				'placeholder' => '254712345678',
			),
			array(
				'type' => 'njiwa_check',
				'id'   => 'njiwa_wc_check',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'njiwa_wc_connection',
			),

			array(
				'type' => 'title',
				'id'   => 'njiwa_wc_customer',
				'name' => __( 'Messages to your customers', 'njiwa-for-woocommerce' ),
				'desc' => $this->placeholder_help(),
			),
		);

		foreach ( Njiwa_WC_Notifier::customer_events() as $status => $label ) {
			$settings[] = array(
				'id'      => 'njiwa_wc_event_' . $status,
				'name'    => $label,
				'desc'    => $this->event_help( $status ),
				'type'    => 'checkbox',
				'default' => 'no',
			);
			$settings[] = array(
				'id'      => 'njiwa_wc_template_' . $status,
				'name'    => '',
				/* translators: %s: the name of the order status this message is for */
				'desc'    => sprintf( __( 'The message sent when an order reaches %s. Leave it empty and nothing is sent, whatever the tick box says.', 'njiwa-for-woocommerce' ), '<strong>' . esc_html( $label ) . '</strong>' ),
				'type'    => 'textarea',
				'default' => Njiwa_WC_Templates::default_for( $status ),
				'css'     => 'width:100%;height:90px;',
			);
		}

		$settings[] = array(
			'type' => 'sectionend',
			'id'   => 'njiwa_wc_customer',
		);

		$settings[] = array(
			'type' => 'title',
			'id'   => 'njiwa_wc_admin',
			'name' => __( 'The message to you', 'njiwa-for-woocommerce' ),
			'desc' => __( 'One message when an order becomes real. It is sent on the first status that means money is on the way, not the moment somebody reaches the payment page, so an abandoned checkout never wakes you up.', 'njiwa-for-woocommerce' ),
		);
		$settings[] = array(
			'id'      => 'njiwa_wc_event_admin',
			'name'    => __( 'Tell me about new orders', 'njiwa-for-woocommerce' ),
			'desc'    => __( 'Send me a WhatsApp message when an order comes in.', 'njiwa-for-woocommerce' ),
			'type'    => 'checkbox',
			'default' => 'no',
		);
		$settings[] = array(
			'id'          => 'njiwa_wc_admin_numbers',
			'name'        => __( 'Your WhatsApp numbers', 'njiwa-for-woocommerce' ),
			'desc'        => __( 'Where that message goes. Digits only, in full international form, separated by commas if there are several. Everybody listed gets their own copy.', 'njiwa-for-woocommerce' ),
			'type'        => 'text',
			'default'     => '',
			'placeholder' => '254712345678, 254733000111',
			'css'         => 'min-width:340px;',
		);
		$settings[] = array(
			'id'      => 'njiwa_wc_template_admin',
			'name'    => '',
			'desc'    => __( 'What that message says. <code>{admin_url}</code> is worth having here: it opens the order straight from your phone.', 'njiwa-for-woocommerce' ),
			'type'    => 'textarea',
			'default' => Njiwa_WC_Templates::default_for( 'admin' ),
			'css'     => 'width:100%;height:90px;',
		);
		$settings[] = array(
			'type' => 'sectionend',
			'id'   => 'njiwa_wc_admin',
		);

		return $settings;
	}

	/**
	 * The placeholder list, built from the code that does the replacing, so
	 * the two cannot drift apart.
	 */
	protected function placeholder_help() {
		$rows = array();
		foreach ( Njiwa_WC_Templates::placeholders() as $token => $meaning ) {
			$rows[] = '<code>' . esc_html( $token ) . '</code> &mdash; ' . esc_html( $meaning );
		}

		return __( 'Each message is plain text. Anything in braces is filled in from the order:', 'njiwa-for-woocommerce' )
			. '<br>' . implode( '<br>', $rows );
	}

	protected function event_help( $status ) {
		$help = array(
			'on-hold'    => __( 'For bank transfer, cash on delivery and anything else where the order is placed before the money arrives. Tell them you have it and that you are waiting.', 'njiwa-for-woocommerce' ),
			'processing' => __( 'The one most shops want. Payment has landed and you are getting the order ready.', 'njiwa-for-woocommerce' ),
			'completed'  => __( 'Sent, delivered, or done. WooCommerce sends its own email at the same moment; this arrives where people actually look.', 'njiwa-for-woocommerce' ),
			'cancelled'  => __( 'Worth sending. A cancellation nobody explained is what turns into a phone call.', 'njiwa-for-woocommerce' ),
			'refunded'   => __( 'Money is on its way back. Saying so stops the "where is my refund" message before it is sent.', 'njiwa-for-woocommerce' ),
		);

		return isset( $help[ $status ] ) ? $help[ $status ] : '';
	}

	/**
	 * The two buttons under the connection settings.
	 */
	public function render_check() {
		$nonce = wp_create_nonce( 'njiwa-wc-check' );
		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php esc_html_e( 'Check it works', 'njiwa-for-woocommerce' ); ?></th>
			<td class="forminp">
				<button type="button" class="button" id="njiwa-wc-test"><?php esc_html_e( 'Test connection', 'njiwa-for-woocommerce' ); ?></button>
				<button type="button" class="button" id="njiwa-wc-send"><?php esc_html_e( 'Send me a test message', 'njiwa-for-woocommerce' ); ?></button>
				<p class="description">
					<?php esc_html_e( 'Both use the settings as they are saved, not as they are on screen. Save first, then check.', 'njiwa-for-woocommerce' ); ?>
				</p>
				<div id="njiwa-wc-result" style="margin-top:8px"></div>
				<script>
				jQuery(function ($) {
					function run(action, button) {
						var out = $('#njiwa-wc-result');
						button.prop('disabled', true);
						out.html('<em><?php echo esc_js( __( 'Asking Njiwa...', 'njiwa-for-woocommerce' ) ); ?></em>');
						$.post(ajaxurl, { action: action, _wpnonce: '<?php echo esc_js( $nonce ); ?>' })
							.done(function (response) {
								out.html('<div class="notice notice-' + (response.success ? 'success' : 'error') +
									' inline"><p>' + response.data.message + '</p></div>');
							})
							.fail(function () {
								out.html('<div class="notice notice-error inline"><p><?php echo esc_js( __( 'WordPress could not reach itself to run the check.', 'njiwa-for-woocommerce' ) ); ?></p></div>');
							})
							.always(function () { button.prop('disabled', false); });
					}
					$('#njiwa-wc-test').on('click', function () { run('njiwa_wc_test', $(this)); });
					$('#njiwa-wc-send').on('click', function () { run('njiwa_wc_send_test', $(this)); });
				});
				</script>
			</td>
		</tr>
		<?php
	}

	/** Who this key belongs to, and what it can send from. */
	public static function ajax_test() {
		self::guard();

		try {
			$numbers = Njiwa_WC_Client::numbers();
		} catch ( Njiwa_WC_Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html( $e->getMessage() ) ) );
		}

		$lines = array();

		if ( Njiwa_WC_Client::is_test_key() ) {
			$lines[] = '<strong>' . esc_html__( 'This is a test key.', 'njiwa-for-woocommerce' ) . '</strong> '
				. esc_html__( 'Every message is checked and stored, and nothing reaches WhatsApp. Swap it for a key beginning sk_live_ when you are ready.', 'njiwa-for-woocommerce' );
		}

		if ( empty( $numbers ) ) {
			$lines[] = esc_html__( 'The key works, but this account has no numbers yet. Add one in the Njiwa console under Numbers and link it.', 'njiwa-for-woocommerce' );
		} else {
			$listed = array();
			foreach ( $numbers as $number ) {
				$listed[] = sprintf(
					'%s &mdash; %s (%s)',
					esc_html( isset( $number['label'] ) ? $number['label'] : '' ),
					esc_html( ! empty( $number['msisdn'] ) ? '+' . $number['msisdn'] : __( 'not linked yet', 'njiwa-for-woocommerce' ) ),
					esc_html( isset( $number['status'] ) ? $number['status'] : '' )
				);
			}
			$lines[] = esc_html__( 'Connected. This key can send from:', 'njiwa-for-woocommerce' ) . '<br>' . implode( '<br>', $listed );
		}

		$from = trim( (string) get_option( 'njiwa_wc_from', '' ) );
		if ( '' !== $from ) {
			$known = array();
			foreach ( $numbers as $number ) {
				if ( ! empty( $number['msisdn'] ) ) {
					$known[] = $number['msisdn'];
				}
			}
			if ( ! in_array( preg_replace( '/\D/', '', $from ), $known, true ) ) {
				$lines[] = '<strong>' . esc_html__( 'Send from does not match any number on this account, so every message will be refused.', 'njiwa-for-woocommerce' )
					. '</strong> ' . esc_html__( 'Correct it, or clear it to use the default number.', 'njiwa-for-woocommerce' );
			}
		}

		wp_send_json_success( array( 'message' => implode( '<br><br>', $lines ) ) );
	}

	/** A real message, to the shop's own number, using the real template. */
	public static function ajax_send_test() {
		self::guard();

		$numbers = Njiwa_WC_Numbers::parse_list( get_option( 'njiwa_wc_admin_numbers', '' ) );
		if ( empty( $numbers ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Put your own WhatsApp number in "Your WhatsApp numbers" below and save, then try again. The test goes there, never to a customer.', 'njiwa-for-woocommerce' ),
				)
			);
		}

		try {
			$answer = Njiwa_WC_Client::send_text(
				$numbers[0],
				sprintf(
					/* translators: %s: the shop name */
					__( 'Test message from %s. If you can read this, WooCommerce can reach your customers on WhatsApp.', 'njiwa-for-woocommerce' ),
					wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
				)
			);
		} catch ( Njiwa_WC_Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html( $e->getMessage() ) ) );
		}

		$message = sprintf(
			/* translators: 1: the number it went to, 2: Njiwa's message id */
			esc_html__( 'Sent to +%1$s (%2$s).', 'njiwa-for-woocommerce' ),
			esc_html( $numbers[0] ),
			esc_html( isset( $answer['id'] ) ? $answer['id'] : '?' )
		);

		if ( Njiwa_WC_Client::is_test_key() ) {
			$message .= ' <strong>' . esc_html__( 'This is a test key, so nothing actually reached the phone.', 'njiwa-for-woocommerce' ) . '</strong>';
		}

		wp_send_json_success( array( 'message' => $message ) );
	}

	protected static function guard() {
		check_ajax_referer( 'njiwa-wc-check' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'You are not allowed to change WooCommerce settings.', 'njiwa-for-woocommerce' ) ),
				403
			);
		}
	}
}
