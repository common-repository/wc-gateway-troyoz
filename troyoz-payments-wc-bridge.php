<?php
/**
 * Plugin Name: Troy Oz Payments bridge for WooCommerce
 * Plugin URI: https://www.troyozpayments.com
 * Description: Allows for alternative payment methods, including crypto-currencies/tokens.
 * Author: Troy Oz Information Services, LLC
 * Author URI: https://www.troyozpayments.com/
 * Version: 1.0.3
 * Text Domain: wc-gateway-troyoz
 *
 * Copyright: (c) 2015-2018 Troy Oz Information Services, LLC. (support@troyozpayments.com)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-Gateway-TroyOz
 * @author    Troy Oz Information Services, LLC
 * @category  Admin
 * @copyright Copyright: (c) 2015-2018 Troy Oz Information Services, LLC.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 */
 
defined( 'ABSPATH' ) or exit;


// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

// Make sure Troy Oz Payments is active
if ( ! in_array( 'troyoz-payments/troyoz-payments.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

define('TOZ_WOOCOMMERCE_BRIDGE', 'INSTALLED');

/**
 * Add the gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + troyoz gateway
 */
function wc_troyoz_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_TroyOz_Gateway';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_troyoz_add_to_gateways' );


/**
 * Adds plugin page links
 * 
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_troyoz_gateway_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=troyoz_gateway' ) . '">' . __( 'Configure', 'wc-gateway-troyoz' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_troyoz_gateway_plugin_links' );


/**
 * Troy Oz Payment Gateway
 *
 * Provides the Troy Oz Payment Gateway
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		WC_TroyOz_Gateway
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		Troy Oz Information Services, LLC
 */
add_action( 'plugins_loaded', 'wc_troyoz_gateway_init', 11 );

function wc_troyoz_gateway_init() {

	class WC_TroyOz_Gateway extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
	  
			$this->id                 = 'troyoz_gateway';
			//$this->icon               = apply_filters('woocommerce_troyoz_icon', '//media.troyoz.net/img/powered-by-troyoz-75.png');
			$this->has_fields         = false;
			$this->method_title       = __( 'Troy Oz Payments', 'wc-gateway-troyoz' );
			$this->method_description = __( 'Allows alternative payment methods, including crypto-curriencis/tokens.', 'wc-gateway-troyoz' );
		  
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
		  
			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions', $this->description );
		  
			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		  
			// Customer Emails
			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		}
	
	
		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {
	  
			$this->form_fields = apply_filters( 'wc_troyoz_form_fields', array(
		  
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'wc-gateway-troyoz' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Troy Oz Payments', 'wc-gateway-troyoz' ),
					'default' => 'yes'
				),
				
				'title' => array(
					'title'       => __( 'Title', 'wc-gateway-troyoz' ),
					'type'        => 'text',
					'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'wc-gateway-troyoz' ),
					'default'     => __( 'Troy Oz Payments', 'wc-gateway-troyoz' ),
					'desc_tip'    => true,
				),
				
				'description' => array(
					'title'       => __( 'Description', 'wc-gateway-troyoz' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-gateway-troyoz' ),
					'default'     => __( 'Other payment options, including crypto-currencies/tokens.', 'wc-gateway-troyoz' ),
					'desc_tip'    => true,
				),
				
				'instructions' => array(
					'title'       => __( 'Instructions', 'wc-gateway-troyoz' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank you page and emails.', 'wc-gateway-troyoz' ),
					'default'     => '',
					'desc_tip'    => true,
				),
			) );
		}
	
	
		/**
		 * Output for the order received page.
		 */
		public function thankyou_page() {
			if ( $this->instructions ) {
				echo wpautop( wptexturize( $this->instructions ) );
			}
		}
	
	
		/**
		 * Add content to the WC emails.
		 *
		 * @access public
		 * @param WC_Order $order
		 * @param bool $sent_to_admin
		 * @param bool $plain_text
		 */
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		
			if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
		}
	
	
		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) {
            
            $order = wc_get_order( $order_id );
            if (!empty($order)) {
                $tozWcStat = tozInjectWCPaymentOptions($order->get_data(), $this->get_return_url( $order ));
                if ($tozWcStat['status'] == 'success') {
			         WC()->cart->empty_cart(); // Clear shopping cart
                }
            }
            else $tozWcStat = array('status' => 'fail', 'redirect' => '');
            
            return array(
				'result' 	=> $tozWcStat['status'],
				'redirect'	=> $tozWcStat['redirect']
			);
	
		}
	
  } // end \WC_TroyOz_Gateway class
}