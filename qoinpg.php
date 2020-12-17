<?php
/*
 * Plugin Name: Qoin Payment Gateway
 * Plugin URI: https://wordpress.org/plugins/qoin-payment-gateway
 * Description: Qoin adalah aplikasi untuk yang dapat digunakan untuk berbagai macam transaksi finansial.
 * Author: Qoin
 * Author URI: https://my.qoin.id/
 * Version: 1.0.0
 * Requires at least: 5.3
 * Requires PHP: 7.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'Q_PLUGIN_FILE' ) ) {
	define( 'Q_PLUGIN_FILE', __FILE__ );
}

// require __DIR__.'/vendor/autoload.php';
require __DIR__ . '/Autoloader.php';

if ( ! \Qoin\Autoloader::init() ) {
	return;
}

// Include Qoin Snap class.
if ( ! class_exists( 'Snap', false ) ) {
	include_once dirname( Q_PLUGIN_FILE ) . '/vendor/qoin/qoin-php/src/Snap.php';
}

 /*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'qoin_add_gateway_class' );
function qoin_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Qoin_Gateway'; //class name is here
	return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'qoin_init_gateway_class' );
function qoin_init_gateway_class() {
 
	class WC_Qoin_Gateway extends WC_Payment_Gateway {
 
 		/**
 		 * Class constructor
 		 */
 		public function __construct() {
 
            $this->id = 'qoin'; 
            $this->icon = plugins_url( 'assets/images/logo.png', __FILE__ ); 
            $this->has_fields = true; 
            $this->method_title = 'Qoin Payment Gateway';
            $this->method_description = 'Qoin payment gateway give customer a simplicity to enter their payment information';
         
            $this->supports = array(
                'products'
            );
         
            $this->init_form_fields();
         
            $this->init_settings();
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->order_id_prefix = $this->get_option( 'order_id_prefix' );
            $this->merchant_id = $this->get_option( 'merchant_id' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->sbmode = $this->get_option( 'sbmode' )==1 ? 'sandbox' : 'production';
            $this->private_key = $this->sbmode ? $this->get_option( 'sb_private_key' ) : $this->get_option( 'private_key' );
            $this->secret_key = $this->sbmode ? $this->get_option( 'sb_secret_key' ) : $this->get_option( 'secret_key' );
            
            // This action hook saves the settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
         
            // We need custom JavaScript to obtain a token
            add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );   
 		}
 
		/**
 		 * Plugin options
 		 */
 		public function init_form_fields(){
 
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable Qoin Payment Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'Qoin',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => "Pay via Qoin; you can pay with your credit card, bank transfer, e-money.",
                ),
                'order_id_prefix' => array(
                    'title'       => 'Order ID Prefix',
                    'type'        => 'text',
                    'desc_tip' => 'This controls the prefix of order id.',
                    'default'     => "wp_",
                ),
                'merchant_id' => array(
                    'title'       => 'Merchant ID',
                    'type'        => 'text',
                    'description' => 'This controls the merchant id which the user sends during checkout.',
                    'default'     => '1234567',
                    'desc_tip'    => true,
                ),
                'sbmode' => array(
                    'title'       => 'Sandbox mode',
                    'label'       => 'Enable Sandbox Mode',
                    'type'        => 'checkbox',
                    'description' => 'Place the payment gateway in sandbox mode using test API keys.',
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ),
                'sb_secret_key' => array(
                    'title'       => 'Sandbox Secret Key',
                    'type'        => 'text'
                ),
                'sb_private_key' => array(
                    'title'       => 'Sandbox RSA Private Key',
                    'type'        => 'textarea',
                ),
                'secret_key' => array(
                    'title'       => 'Production Secret Key',
                    'type'        => 'text'
                ),
                'private_key' => array(
                    'title'       => 'Production RSA Private Key',
                    'type'        => 'textarea'
                ),
     
               
            );
 
         }
         
	
		// public function payment_fields() {}
 
		/*
		 * Custom CSS and JS
		 */
	 	public function payment_scripts() {

            // we need JavaScript to process a token only on cart/checkout pages, right?
            if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
                return;
            }
        
            // if our payment gateway is disabled, we do not have to enqueue JS too
            if ( 'no' === $this->enabled ) {
                return;
            }
        
            // no reason to enqueue JavaScript if API keys are not set
            if ( empty( $this->private_key ) || empty( $this->secret_key ) ) {
                return;
            }
        
            // do not work with card detailes without SSL unless your website is in a test mode
            // if ( ! $this->sbmode && ! is_ssl() ) {
            //     return;
            // }
           
            wp_enqueue_script( 'qoin_js', 'https://dev-embed.qoin.id/qoin.js' );
          
            wp_deregister_script('wc-checkout');
            wp_register_script('wc-checkout', plugins_url( 'assets/js/checkout.js', __FILE__ ), 
            array( 'jquery', 'woocommerce', 'wc-country-select', 'wc-address-i18n' ), null, TRUE);
           
            wp_localize_script( 'wc-checkout', 'qoin_params', array(
                'checkout_url'=> wc_get_checkout_url(),
            ) );
            wp_enqueue_script('wc-checkout');
 
	 	}
 
		/*
		 * We're processing the payments here
		 */
		public function process_payment( $order_id ) {
 
            global $woocommerce;
 
            //order details
            $order = wc_get_order( $order_id );

             $products = [];
             $i = 0;
             
             // Iterating through each "line" items in the order
            foreach ($order->get_items() as $item_id => $item ) {
                $i++;
                $total = $item->get_data()['total']+$item->get_data()['total_tax'];
                $products[] = ['Item'=>$i,'Desc'=>$item->get_data()['name'],'Amount'=>$total];                
            }

            $products[] = ['Item'=>count($products)+1,'Desc'=>'Shipping','Amount'=>$order->get_shipping_total()];

            $pay = (new \Qoin\Snap)
            ->setEnvironment($this->sbmode) // sandbox || production
            ->setPrivateKey($this->private_key)
            ->setSecretKey($this->secret_key)
            ->createOrder([
                'merchantCode' => $this->merchant_id,
                'linkPayment' => '12345',
                'referenceNo' => $this->order_id_prefix.$order_id,
                'expiredDate' => '',
                'requestTime' => date('Y-m-d H:i:s'),
                'currency' => $order->data['currency'],
                'paymentMethod' => '',
                'paymentChannel' => '',
                'customerName' => $order->data['billing']['first_name'].' '.$order->data['billing']['last_name'],
                'customerPhone' => $order->data['billing']['phone'],
                'customerEmail' => $order->data['billing']['email'],
                'product' => $products, 
                'totalPrice' => $order->data['total']
            ]);
          
            if($pay->status==200){
                return array(
                    "result" => "success",
                    'redirect' => $this->get_return_url( $order ),
                    "payload"=> $pay,
                );
            }else{
                wc_add_notice( 'Transaction failed.', 'error' );
                return;
            }
	 	}
 
 	}
}