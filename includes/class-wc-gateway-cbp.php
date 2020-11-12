<?php

/**
 * @link       https://mildai.com
 * @since      1.0.0
 *
 * @package    CECA_Bank_Payments
 * @subpackage CECA_Bank_Payments/includes
 */

/**
 * The core plugin class.
 *
 * @since      1.0.0
 * @package    CECA_Bank_Payments
 * @subpackage CECA_Bank_Payments/includes
 * @author     Mildai Beauty Solutions <info@mildai.com>
 */
class WC_Gateway_CBP extends WC_Payment_Gateway {
	public $notify_url;
	public $environment;
	public $default_locale;
	public $default_currency;
	public $logger;
	public $debug;
	public $order_status;

	/**
	 * Currency ISO to Currency Code conversion
	 * @var array
	 */
	private $currencies = array(
		'EUR' => '978',
		'USD' => '840',
		'GBP' => '826',
		'AUD' => '036',
		'CHF' => '756',
		'JPY' => '392',
		'DKK' => '208',
		'SEK' => '752',
		'NOK' => '578'
	);

	/**
	 * Language name Language to CECA code conversion
	 * @var array
	 */
	private $languages = array(
		'6'  => 'English',
		'7'  => 'French',
		'8'  => 'German',
		'10' => 'Italian',
		'15' => 'Norwegian',
		'9'  => 'Portuguese',
		'14' => 'Russian',
		'1'  => 'Spanish',
	);

	/**
	 * Language ISO code to CECA code conversion
	 * @var string
	 */
	private $language_codes = array(
		'en_AU' => '6',
		'en_IN' => '6',
		'en_GB' => '6',
		'en_US' => '6',
		'en_CA' => '6',
		'en_NZ' => '6',
		'fr_CA' => '7',
		'fr_FR' => '7',
		'fr_XC' => '7',
		'de_DE' => '8',
		'it_IT' => '10',
		'no_NO' => '15',
		'pt_BR' => '9',
		'pt_PT' => '9',
		'ru_RU' => '14',
		'ru_UA' => '14',
		'es_ES' => '1',
		'es_XC' => '1',
	);

	public function __construct() {
		$this->id                 = 'cpb_gateway';
		$this->icon               = site_url() . '/wp-content/plugins/ceca-bank-payments/assets/img/MCV-logo.png';
		$this->method_title       = __( 'Payment by Card (ABANCA)', 'ceca-bank-payments' );
		$this->method_description = __( 'CECA Bank Payment Gateway', 'ceca-bank-payments' );
		$this->notify_url         = add_query_arg( 'wc-api', 'WC_Gateway_CBP', home_url( '/' ) );

		$this->has_fields = false;

		$this->supports = array(
			'products'
		);

		// Load the settings
		$this->init_settings();
		$this->init_form_fields();

		$this->title = __('Credit Card / Debit Card', 'ceca-bank-payments' );
		$this->description = __('Credit Card, Debit Card and EURO 6000 Card. You will be transferred to the secure server to make a payment. Your bank may ask you for the secure code to confirm the payment.', 'ceca-bank-payments' );

		// Get settings
		$this->environment            = $this->get_option( 'environment' );
		$this->acquirer_bin           = $this->get_option( 'acquirer_bin' );
		$this->merchant_id            = $this->get_option( 'merchant_id' );
		$this->terminal_id            = $this->get_option( 'terminal_id' );
		$this->encryption_key         = $this->get_option( 'encryption_key' );
		$this->acquirer_bin_sandbox   = $this->get_option( 'acquirer_bin_sandbox' );
		$this->merchant_id_sandbox    = $this->get_option( 'merchant_id_sandbox' );
		$this->terminal_id_sandbox    = $this->get_option( 'terminal_id_sandbox' );
		$this->encryption_key_sandbox = $this->get_option( 'encryption_key_sandbox' );
		$this->multi_currency         = 'yes' === $this->get_option( 'multi_currency' );
		$this->default_currency       = $this->get_option( 'default_currency' );
		$this->default_language       = $this->get_option( 'default_language' );
		$this->debug                  = 'yes' === $this->get_option( 'debug' );
		$this->enabled                = $this->get_option( 'enabled' );
		$this->order_status           = $this->get_option( 'order_status' );

		if ( $this->debug ) {
			$this->logger = new WC_Logger();
		}

		// Actions
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'cbp_receipt_page' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// Assets
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		// Payment listener/API hook
		add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'cbp_webhook' ) );
	}

	public function init_form_fields() {
		$currencies_by_code = [];
		$languages_array = [];

		foreach ( $this->currencies as $currency_name => $code ) {
			$currencies_by_code[$code] = __($currency_name, 'woocommerce');
		}

		foreach ( $this->languages as $language_code => $language_name ) {
			$languages_array[$language_code] = __($language_name, 'woocommerce');
		}

		$order_statuses = array(
			'processing' => __( 'Processing', 'woocommerce' ),
			'completed'  => __( 'Completed', 'woocommerce' ),
		);

		$this->form_fields = array(
			'enabled' => array(
				'title'       => __( 'Enable/Disable', 'woocommerce' ),
				'label'       => __( 'Enable Card Payment (CECA Bank)', 'ceca-bank-payments' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			),
			'multi_currency' => array(
				'title'       => __( 'Enable/Disable', 'woocommerce' ),
				'label'       => __( 'Multi Currency Support', 'ceca-bank-payments' ),
				'type'        => 'checkbox',
				'description' => __('Multi-currency support in TPV Dashboard must be enabled.', 'ceca-bank-payments'),
				'default'     => 'no'
			),
			'default_currency' => array(
				'title'       => __( 'Default Currency', 'ceca-bank-payments' ),
				'type'        => 'select',
				'description' => 'Default currency must be the same, as the shop default currency.',
				'default'     => '978',
				'desc_tip'    => false,
				'options'     => $currencies_by_code,
			),
			'default_language' => array(
				'title'       => __( 'Default Language', 'ceca-bank-payments' ),
				'type'        => 'select',
				'description' => 'Default language for the payment form (if the language is not supported by the payment gateway).',
				'default'     => '6',
				'desc_tip'    => false,
				'options'     => $languages_array,
			),
			'debug' => array(
				'title'       => __( 'Debug', 'woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Write debug information to the log.', 'ceca-bank-payments' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'order_status' => array(
				'title'       => __( 'Order Status', 'ceca-bank-payments' ),
				'type'        => 'select',
				'description' => __( 'Order status after the successful payment.', 'ceca-bank-payments' ),
				'default'     => 'no',
				'desc_tip'    => true,
				'options'     => $order_statuses,
			),
			'notify_url' => array(
				'title'       => __( 'Notify URL', 'ceca-bank-payments' ),
				'type'        => 'url',
				'description' => __( 'Enter this URL in TPV Dashboard.', 'ceca-bank-payments' ),
				'default'     => $this->notify_url,
				'desc_tip'    => false,
				'input-class' => 'cpb-disabled',
			),
			'environment' => array(
				'title'       => __( 'Environment', 'ceca-bank-payments' ),
				'type'        => 'select',
				'description' => '',
				'default'     => 'sandbox',
				'desc_tip'    => false,
				'options'     => array(
					'sandbox' => __( 'Sandbox', 'ceca-bank-payments' ),
					'production' => __( 'Production', 'ceca-bank-payments' ),
				)
			),

			'sandbox_credentials' => array(
				'title'       => __( 'Sandbox Credentials', 'ceca-bank-payments' ),
				'type'        => 'title',
				'description' => '',
			),
			'acquirer_bin_sandbox' => array(
				'title'       => __( 'Acquirer BIN', 'ceca-bank-payments' ),
				'type'        => 'text',
				'description' => '',
				'default'     => '',
				'desc_tip'    => false,
			),
			'merchant_id_sandbox' => array(
				'title'       => __( 'Merchant ID', 'ceca-bank-payments' ),
				'type'        => 'text',
				'description' => '',
				'default'     => '',
				'desc_tip'    => false,
			),
			'terminal_id_sandbox' => array(
				'title'       => __( 'Terminal ID', 'ceca-bank-payments' ),
				'type'        => 'text',
				'description' => '',
				'default'     => '00000003',
				'desc_tip'    => false,
			),
			'encryption_key_sandbox' => array(
				'title'       => __( 'Encryption Key', 'ceca-bank-payments' ),
				'type'        => 'text',
				'description' => '',
				'default'     => '',
				'desc_tip'    => false,
			),

			'production_credentials' => array(
				'title'       => __( 'Production Credentials', 'ceca-bank-payments' ),
				'type'        => 'title',
				'description' => '',
			),
			'acquirer_bin' => array(
				'title'       => __( 'Acquirer BIN', 'ceca-bank-payments' ),
				'type'        => 'text',
				'description' => '',
				'default'     => '',
				'desc_tip'    => false,
			),
			'merchant_id' => array(
				'title'       => __( 'Merchant ID', 'ceca-bank-payments' ),
				'type'        => 'text',
				'description' => '',
				'default'     => '',
				'desc_tip'    => false,
			),
			'terminal_id' => array(
				'title'       => __( 'Terminal ID', 'ceca-bank-payments' ),
				'type'        => 'text',
				'description' => '',
				'default'     => '00000003',
				'desc_tip'    => false,
			),
			'encryption_key' => array(
				'title'       => __( 'Encryption Key', 'ceca-bank-payments' ),
				'type'        => 'text',
				'description' => '',
				'default'     => '',
				'desc_tip'    => false,
			),
		);
	}

	public function enqueue_styles() {
		wp_enqueue_style( PLUGIN_NAME, site_url() . '/wp-content/plugins/ceca-bank-payments/assets/css/cbp.css', array(), CECA_BANK_PAYMENTS_VERSION, 'all' );
	}

	/**
	 * Outputs payment page
	 *
	 * @param $order int Order ID
	 */
	public function cbp_receipt_page( $order ) {
		if ( $this->debug ) {
			$this->logger->log( 'debug', 'Receipt page started. Order ID = ' . $order );
		}

		echo '<div class="alert alert-info">'.__('Thank you for your order, please click the button to pay by card.', 'ceca-bank-payments').'</div>';

		if ( $this->environment == 'sandbox' ) {
			echo '<div class="alert alert-warning"><i class="fas fa-info-circle"></i> ' . __( 'Warning: The payment gateway is in Sandbox Mode. Your account will not be charged and your order will not be fulfilled.', 'ceca-bank-payments' ) . '</div>';
		}

		echo $this->generate_payment_form( $order );
	}

	/**
	 * Redirects to payments page
	 *
	 * @param int $order_id
	 *
	 * @return array
	 */
	function process_payment( $order_id ) {
		global $woocommerce;

		$order = new WC_Order($order_id);

		// Return receipt_page redirect
		return array(
			'result' 	=> 'success',
			'redirect'	=> $order->get_checkout_payment_url( true )
		);
	}

	/**
	 * CECA Bank payment webhook
	 */
	public function cbp_webhook() {
		global $woocommerce;

		$success_code = '$*$OKY$*$';
		$error_code = '$*$NOK$*$';

		if ( $this->debug ) {
			$this->logger->log( 'debug', __( 'CECA Bank Webhook Received', 'ceca-bank-payments' ) );
			foreach ( $_POST as $key => $value ) {
				$this->logger->log( 'debug', $key . ' => ' . $value );
			}
		}

		try {
			$this->check_transaction($_POST);
		} catch (Exception $e) {
			$error_logger = new WC_Logger();

			$error_logger->log('error', __( 'CECA Payment Gateway Error (webhook).', 'ceca-bank-payments' ) );
			$error_logger->log('error', $e->getMessage() );

			if ( !empty( $_POST['Num_operacion'] ) ) {
				$order = wc_get_order( $_POST['Num_operacion'] );
				$error_logger->log('error', 'Order# ' . $_POST['Num_operacion'] . ' ' . __('cancelled', 'ceca-bank-payments') );
				$order->update_status('failed', __('Webhook error', 'ceca-bank-payments') );
			}

			die($error_code);
		}

		$order = wc_get_order( $_POST['Num_operacion'] );

		if ( $order->has_status( 'completed' ) ) {
			die();
		}

		// Payment completed
		$order->add_order_note( __('Payment completed with reference:' . ' ' . $_POST['Referencia'], 'ceca-bank-payments') );
		$order->payment_complete( $_POST['Referencia'] );

		$order->update_status( $this->order_status );

		die($success_code);
	}

	/*
	 * HELPERS
	 */

	/**
	 * Check transaction for webhook
	 *
	 * @param $post $_POST
	 *
	 * @return string
	 * @throws Exception
	 */
	private function check_transaction( $post ) {
		if ( empty( $post ) || empty( $post['Firma'] ) ) {
			throw new Exception( __('POST data is empty', 'ceca-bank-payments') );
		}

		$fields = array( 'MerchantID', 'AcquirerBIN', 'TerminalID', 'Num_operacion', 'Importe', 'TipoMoneda', 'Exponente', 'Referencia' );
		$key = $this->get_option( 'encryption_key_' . $this->environment );

		foreach ($fields as $field) {
			if ( empty( $post[$field] ) ) {
				throw new Exception( sprintf( 'Field <strong>%s</strong> is empty and is required to verify transaction', $field ) );
			}

			$key .= $post[$field];
		}

		$key = str_replace('&amp;', '&', $key);

		$signature = hash( 'sha256', $key );

		if ( $signature != $post['Firma'] ) {
			throw new Exception( sprintf( 'Signature not valid (%s != %s)', $signature, $post['Firma'] ) );
		}

		return $post['Firma'];
	}

	/**
	 * Generates payment form
	 *
	 * @param $order_id
	 *
	 * @return string payment form
	 */
	private function generate_payment_form( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( $this->environment == 'sandbox' ) {
			$action = 'https://tpv.ceca.es/tpvweb/tpv/compra.action';
			$testmode = 'yes';
		} else {
			$action = 'https://pgw.ceca.es/tpvweb/tpv/compra.action';
			$testmode = 'no';
		}

		if ( $this->debug ) {
			$this->logger->log( 'debug', 'Generate payment form. Environment: ' . $this->environment );
		}

		$acquirer_bin = $this->get_option( 'acquirer_bin_' . $this->environment );
		$merchant_id = $this->get_option( 'merchant_id_' . $this->environment );
		$terminal_id = $this->get_option( 'terminal_id_' . $this->environment );
		$encryption_key = $this->get_option( 'encryption_key_' . $this->environment );

		$locale = get_locale();
		$idioma = isset( $this->language_codes[$locale] ) ? $this->language_codes[$locale] : $this->default_locale;

		$currency = get_woocommerce_currency();
		$tipo_moneda = isset( $this->currencies[$currency] ) ? $this->currencies[$currency] : $this->default_currency;

		$num_operacion = $order_id;
		
		$exponente = '2';
		$url_ok = (string) $this->get_return_url( $order );
		$url_nok = (string) $order->get_cancel_order_url();
		$cifrado = 'SHA2';
		$pago_soportado = 'SSL';
		$descripcion = '';
		$pago_elegido = '';

		$url_ok = str_replace('&amp;', '&', $url_ok);
		$url_nok = str_replace('&amp;', '&', $url_nok);

		$order_total = number_format( (float) ( $order->get_total() ), 2, '.', '' );
		$importe = str_replace('.','', $order_total );

		$signature = $encryption_key . $merchant_id . $acquirer_bin . $terminal_id . $num_operacion . $importe . $tipo_moneda . $exponente . $cifrado . $url_ok . $url_nok;

		$firma = hash('sha256', $signature);
		
		// Payment form
		$payment_form =
		$payment_form = '<form action="' . $action . '" method="post" id="cbp_payment_form">';

		$payment_form .= '<input name="MerchantID" type="hidden" value="' . $merchant_id . '"/>';
		$payment_form .= '<input name="AcquirerBIN" type="hidden" value="' . $acquirer_bin . '"/>';
		$payment_form .= '<input name="TerminalID" type="hidden" value="' . $terminal_id . '"/>';
		$payment_form .= '<input name="Num_operacion" type="hidden" value="' . $num_operacion . '"/>';
		$payment_form .= '<input name="Importe" type="hidden" value="' . $importe . '"/>';
		$payment_form .= '<input name="TipoMoneda" type="hidden" value="' . $tipo_moneda . '"/>';
		$payment_form .= '<input name="Exponente" type="hidden" value="' . $exponente . '"/>';
		$payment_form .= '<input name="URL_OK" type="hidden" value="' . $url_ok . '"/>';
		$payment_form .= '<input name="URL_NOK" type="hidden" value="' . $url_nok . '"/>';
		$payment_form .= '<input name="Cifrado" type="hidden" value="' . $cifrado . '"/>';
		$payment_form .= '<input name="Idioma" type="hidden" value="' . $idioma . '"/>';
		$payment_form .= '<input name="Pago_soportado" type="hidden" value="' . $pago_soportado . '"/>';
		$payment_form .= '<input name="Descripcion" type="hidden" value="' . $descripcion . '"/>';
		$payment_form .= '<input name="Pago_elegido" type="hidden" value="' . $pago_elegido . '"/>';
		$payment_form .= '<input name="Firma" type="hidden" value="' . $firma . '"/>';

		$payment_form .= '<input name="mode" type="hidden" value="' . $testmode . '"/>';

		$payment_form .= '<div class="mt-2">';
		$payment_form .= '<input type="submit" class="button-alt mr-1" id="submit_cbp_payment_form" value="'.__('Pay', 'ceca-bank-payments').'" />';
		$payment_form .= '<a class="button cancel" href="' . $order->get_cancel_order_url() . '">' .__('Cancel', 'ceca-bank-payments') . '</a>';
		$payment_form .= '</div>';
        $payment_form .= '</form>';

		return $payment_form;
	}
}
