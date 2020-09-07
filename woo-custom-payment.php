<?php
/**
 * Plugin Name: woo custom payment
 * Plugin URI: http://www.mywebsite.com/my-first-plugin
 * Description: woocommerce plugi
 * Version: 1.0
 * Author: dedepere
 * Author URI: http://www.mywebsite.com
 */


if(!defined( 'ABSPATH' )) exit;

if (!function_exists('is_woocommerce_active')){
	function is_woocommerce_active(){
		$active_plugins = (array) get_option('active_plugins', array());
		if(is_multisite()){
		$active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
		}
		return in_array('woocommerce/woocommerce.php', $active_plugins) || array_key_exists('woocommerce/woocommerce.php', $active_plugins) || class_exists('WooCommerce');
	}
}

if(!is_woocommerce_active()) {
	return;
}

// define('WCCP_VERSION', '0.0.1');
// !defined('WCCP_BASE_NAME') && define('WCCP_BASE_NAME', plugin_basename( __FILE__ ));
// !defined('WCCP_PATH') && define('WCCP_PATH', plugin_dir_path( __FILE__ ));
// !defined('WCCP_URL') && define('WCCP_URL', plugins_url( '/', __FILE__ ));
// //!defined('WCCP_ASSETS_URL') && define('WCCP_ASSETS_URL', WCCP_URL .'assets/');

// require WCCP_PATH . 'classes/class-wccp.php';

function wc_wccp_gateway_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wccp_gateway' ) . '">' . __( 'Configure', 'wc-wccp-gateway' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_wccp_gateway_plugin_links' );



function wc_wccp_addUrlParameters($url, $add){
	$url_parts = parse_url($url);
	$params = [];

	if (isset($url_parts['query'])) {
		parse_str($url_parts['query'], $params);
	}

	$params = array_merge($params, $add);
	
	$url_parts['query'] = http_build_query($params);

	return $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'] . '?' . $url_parts['query'];
}
function wc_wccp_arg($name, $alt = ""){
	return (isset($_POST[$name]) ? $_POST[$name]: (isset($_GET[$name]) ? urldecode($_GET[$name]) : $alt));
}


add_action( 'plugins_loaded', 'wc_wccp_gateway_init', 11 );

function wc_wccp_gateway_init() {

	class WC_WCCP_Gateway extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
	
			$this->id                 = 'wccp_gateway';
			//$this->icon               = apply_filters('woocommerce_offline_icon', '');
			$this->has_fields         = false;
			$this->method_title       = __( 'Custom Payment', 'wc-wccp-gateway' );
			$this->method_description = __( 'Custom payments...', 'wc-wccp-gateway' );
		
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
		
			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions', $this->description );
		
			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			//add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		
			// Customer Emails
			//add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		
			add_action( 'woocommerce_api_wccppayhook', array( $this, 'webhook' ) );
		}
	
	
		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {
	
			$this->form_fields = apply_filters( 'wc_wccp_form_fields', array(
		
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'wc-wccp-gateway' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable WCCP Payment', 'wc-wccp-gateway' ),
					'default' => 'yes'
				),
				
				'title' => array(
					'title'       => __( 'Title', 'wc-wccp-gateway' ),
					'type'        => 'text',
					'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'wc-wccp-gateway' ),
					'default'     => __( 'WCCP Payment', 'wc-wccp-gateway' ),
					'desc_tip'    => true,
				),
				
				'description' => array(
					'title'       => __( 'Description', 'wc-wccp-gateway' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-wccp-gateway' ),
					'default'     => __( 'Please remit payment to Store Name upon pickup or delivery.', 'wc-wccp-gateway' ),
					'desc_tip'    => true,
				),
				
				'instructions' => array(
					'title'       => __( 'Instructions', 'wc-wccp-gateway' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank you page and emails.', 'wc-wccp-gateway' ),
					'default'     => '',
					'desc_tip'    => true,
				),


				'paymenturl' => array(
					'title'       => __( 'Gateway payment page URL', 'wc-wccp-gateway' ),
					'type'        => 'text',
					'description' => __( '...', 'wc-wccp-gateway' ),
					'default'     => __( 'http://payment', 'wc-wccp-gateway' ),
					'desc_tip'    => true,
				),

				'redirectok' => array(
					'title'       => __( 'OK redirect', 'wc-wccp-gateway' ),
					'type'        => 'text',
					'maxlength'   => 255,
					'description' => __( '...', 'wc-wccp-gateway' ),
					'default'     => __( 'http://ok', 'wc-wccp-gateway' ),
					'desc_tip'    => true,
				),

				'redirectfail' => array(
					'title'       => __( 'Fail redirect', 'wc-wccp-gateway' ),
					'type'        => 'text',
					'maxlength'   => 255,
					'description' => __( '...', 'wc-wccp-gateway' ),
					'default'     => __( 'http://fail', 'wc-wccp-gateway' ),
					'desc_tip'    => true,
				),

				'hookurl' => array(
					'title'       => __( 'API hook url', 'wc-wccp-gateway' ),
					'type'        => 'text',
					'maxlength'   => 255,
					'description' => __( '...', 'wc-wccp-gateway' ),
					'default'     => __( 'http://yourdomain.com/wc-api/wccppayhook', 'wc-wccp-gateway' ),
					'desc_tip'    => true,
				),

				'apiurl' => array(
					'title'       => __( 'Gateway API URL', 'wc-wccp-gateway' ),
					'type'        => 'text',
					'description' => __( '...', 'wc-wccp-gateway' ),
					'default'     => __( 'http://api-ajax', 'wc-wccp-gateway' ),
					'desc_tip'    => true,
				),

				'privatekey' => array(
					'title'       => __( 'Gateway private key', 'wc-wccp-gateway' ),
					'type'        => 'text',
					'maxlength'   => 50,
					'description' => __( '...', 'wc-wccp-gateway' ),
					'default'     => __( 'aajsdu92812*!#*!#21831839h1d', 'wc-wccp-gateway' ),
					'desc_tip'    => true,
				)
			));
		}
	
	
		/**
		 * Output for the order received page.
		 */
		// public function thankyou_page() {
		// 	if ( $this->instructions ) {
		// 		echo wpautop( wptexturize( $this->instructions ) );
		// 	}
		// }
	
	
		/**
		 * Add content to the WC emails.
		 *
		 * @access public
		 * @param WC_Order $order
		 * @param bool $sent_to_admin
		 * @param bool $plain_text
		 */
		// public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		
		// 	if ( $this->instructions && ! $sent_to_admin && $this->id === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
		// 		echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
		// 	}
		// }
	
	
		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) {
	
			$order = new WC_Order( $order_id );
			
			// Mark as on-hold (we're awaiting the payment)
			$order->update_status( 'on-hold', __( 'Awaiting wccp payment', 'wc-wccp-gateway' ) );
			
			// Reduce stock levels
			// $order->reduce_order_stock();
			
			// Remove cart
			WC()->cart->empty_cart();
			
			// Return thankyou redirect
			return array(
				'result' 	=> 'success',
				'redirect'	=> $this->get_return_url( $order )
			);
		}

		public function webhook(){
			$orderId = wc_wccp_arg('outId');
			$privateKey = wc_wccp_arg('privateKey');
			$state = wc_wccp_arg('state');

			if($privateKey !== $this->get_option('privatekey'))
				return;

			switch($state){
				case "ok":
					$order = new WC_Order($orderId);
					$order->payment_complete();
					$order->reduce_order_stock();
				break;
			} 
			//update_option('webhook_debug', $_GET);
		}

		public function get_return_url($order = NULL){
			$return_url;
			if ( $order ) {
				$url = wc_wccp_addUrlParameters(
					$this->get_option('apiurl'),
					[
						"action" => "API_main_call",

						"API_argument_space" => "order",
						"API_argument_method" => "addOrder",

						"API_argument_outId" => $order->get_id(),
						"API_argument_price" => $order->get_total(),
						"API_argument_redirectOK" => $this->get_option('redirectok'),
						"API_argument_redirectFail" => $this->get_option('redirectfail'),
						"API_argument_hook" => $this->get_option('hookurl'),
						"API_argument_privateKey" => $this->get_option('privatekey'),
						"API_argument_currency" => $order->get_currency()
					]
				);
				$api_ret = file_get_contents($url);
				//file_put_contents(plugin_dir_path(__FILE__) . "lol.txt", $api_ret);
				$api_ret = json_decode($api_ret, true);
				if($api_ret["ok"]){
					$return_url = wc_wccp_addUrlParameters(
						$this->get_option('paymenturl'),
						[
							"API_argument_orderId" => $api_ret["id"]
						]
					);
				}
			} else {
				$return_url = 'http://sorry';
			}
		
			return apply_filters( 'woocommerce_get_return_url', $return_url, $order );
		}
	
	} // end \WC_Gateway_Offline class
	function wc_wccp_add_to_gateways( $gateways ) {
		$gateways[] = 'WC_WCCP_Gateway';
		return $gateways;
	}
	add_filter( 'woocommerce_payment_gateways', 'wc_wccp_add_to_gateways' );
}