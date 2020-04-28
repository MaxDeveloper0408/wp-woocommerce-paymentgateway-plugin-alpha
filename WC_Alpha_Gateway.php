<?php


/*
 * Plugin Name: WooCommerce Alpha Payment Gateway
 * Plugin URI: https://Alpha.com/woocommerce/payment-gateway-plugin.html
 * Description: Payments on your store.
 * Author: Alpha
 * Author URI: http://Alpha.com
 * Version: 1.0.1
 */
if(!defined('ABSPATH'))
    exit();
define('WC_ALPHA_PAY_ID', 'alpha');
define('WC_ALPHA_DIR', rtrim(plugin_dir_path(__FILE__), '/'));
define('WC_ALPHA_URL', rtrim(plugin_dir_url(__FILE__), '/'));

add_filter( 'woocommerce_payment_gateways', 'Alpha_add_gateway_class' );

function Alpha_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Alpha_Gateway';
	return $gateways;
}

add_action( 'plugins_loaded', 'Alpha_init_gateway_class' );
function Alpha_init_gateway_class() {

	class WC_Alpha_Gateway extends WC_Payment_Gateway {

		private $SUCCESS_CALLBACK_URL = "Alpha_payment_success";
		private $FAILURE_CALLBACK_URL = "Alpha_payment_failure";
		private $SUCCESS_REDIRECT_URL = "/checkout/order-received/";
		private $FAILURE_REDIRECT_URL = "/checkout/order-received/";
		private $API_HOST = '';
		private $API_SESSION_CREATE_ENDPOINT = "";

 		public function __construct() {
 
			$this->id = WC_ALPHA_PAY_ID; 
			$this->icon ='';    
			$this->has_fields = true;
			$this->method_title = 'Alpha Payment Gateway Plugin';
			$this->method_description = 'Alpha Payment Gateway Plugin.';  
			$this->supports = array(
               'products'
	        );

			$this->init_form_fields();
			$this->init_settings();


			$this->title = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );

			$this->testmode = 'yes' === $this->get_option( 'testmode' );
			$this->merchant_id = $this->testmode ? $this->get_option( 'test_merchant_id' ) : $this->get_option( 'merchant_id' );
			$this->auth_token = $this->testmode ? $this->get_option( 'test_auth_token' ) : $this->get_option( 'auth_token' );

			$this->api_address = $this->get_option('api_address');
			$this->api_key = $this->get_option('api_key');
			
			if($this->api_key == '' || $this->api_key == 'undefined' || $this->api_key == 'null') {
				$this->update_option('enabled', 'no');
			}
			// Site URL
			$this->siteUrl = get_site_url(); 

			// This action hook saves the settings

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action('woocommerce_checkout_update_order_meta', array($this, 'custom_payment_update_order_meta'));
 		}

 		public function init_form_fields(){
			    $this->form_fields = array(
				     'enabled' => array(
				        'title'       => 'Enable/Disable',
				        'label'       => 'Enable Alpha Gateway',
				        'type'        => 'checkbox',
				        'description' => '',
				        'default'     => 'no'
				    ),
				    'title' => array(
				        'title'       => 'Title',
				        'type'        => 'text',
				        'description' => 'This controls the title which the user sees during checkout.',
				        'default'     => 'Alpha',
				        'desc_tip'    => true,
				    ),
				    'description' => array(
						'title'       => __( 'Description', 'woocommerce' ),
						'type'        => 'textarea',
						'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
						'default'     => __( 'Please send amount to olg payment', 'woocommerce' ),
						'desc_tip'    => true,
				    ),
				    'testmode' => array(
				        'title'       => 'Test mode',
				        'label'       => 'Enable Test Mode',
				        'type'        => 'checkbox',
				        'description' => 'Place the payment gateway in test mode using test API keys.',
				        'default'     => 'yes',
				        'desc_tip'    => true,
				    ),
				    'test_merchant_id' => array(
				        'title'       => 'Test MerchantID',
				        'type'        => 'text',
				        'placeholder' => 'Enter Test MerchantID'
				    ),
				    'test_auth_token' => array(
				        'title'       => 'Test Auth Token',
				        'type'        => 'text',
				        'placeholder' => 'Enter Test Auth Token'
				    ),
				    'merchant_id' => array(
				        'title'       => 'Live MerchantID',
				        'type'        => 'text',
				        'placeholder' => 'Enter Live MerchantID'
				    ),
				    'auth_token' => array(
				        'title'       => 'Live Auth Token',
				        'type'        => 'text',
				        'placeholder' => 'Enter Live Auth Token'
				    ),
				    'api_address' => array(
				        'title'       => 'API Address',
				        'type'        => 'text',
				        'placeholder' => 'Enter API Address'
				    ),
				    'api_key' => array(
				        'title'       => 'API KEY',
				        'type'        => 'text',
				        'placeholder' => 'Enter API KEY',
				        'default' => ''
				    )
			  	);
	 	}

		public function payment_fields() {
			if ( $this->description ) {
				// you can instructions for test mode, I mean test card numbers etc.
				if ( $this->testmode ) {
					$this->description .= ' TEST MODE ENABLED. In test mode, you can use theese ';
					$this->description  = trim( $this->description );
				}
				// display the description with <p> tags etc.
				echo wpautop( wp_kses_post( $this->description ) );
			}
		 
			// I will echo() the form, but you can close PHP tags and print it directly in HTML
			echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
		 
				// Add this action hook if you want your custom payment gateway to support it
				do_action( 'woocommerce_credit_card_form_start', $this->id );
			 
				// I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
					echo '
						<style>
							.wc_payment_methods .payment_box p {
								padding: 15px;
								margin: 0;
								text-align: center;
								font-family: NonBreakingSpaceOverride, "Hoefler Text", Garamond, "Times New Roman", serif;
							}
							.form-row {
								margin-bottom: 10px;
							}
							select {
								border-color: #dcd7ca;
								width: 100%!important;
							    background: #fff;
							    border-radius: 0;
							    border-style: solid;
							    border-width: 0.1rem;
							    box-shadow: none;
							    display: block;
							    font-size: 1.6rem;
							    letter-spacing: -0.015em;
							    margin: 0;
							    max-width: 100%;
							    padding: 1.5rem 1.8rem;
							    width: 100%;
							}
							.alpha-logos {
								display: flex;
								justify-content: space-between;
							}
							.alpha-logos img{
									height: 50px;
									width: 90%;
									opacity: 0.3;
							}
							.alpha-logos label {
								display: flex!important;
								align-items: center;
							}
							.alpha-logos input[type=radio] { 
							  position: absolute;
							  opacity: 0;
							  width: 0;
							  height: 0;
							}

							.alpha-logos input[type=radio] + img {
							  cursor: pointer;
							}
							.alpha-logos label:hover img {
							      opacity: 1;
							      outline: 2px solid #ffa100;
							      outline-offset: 1px;
							}
							.alpha-logos input[type=radio]:checked + img {
								  opacity: 1;
							      outline: 2px solid #ffa100;
							      outline-offset: 1px;
							}
						</style>
						<script>
							var Alphaexpdate_input = document.querySelectorAll(".Alpha-expdate")[0];
							var Alphaexpdate_input_dateInputMask = function Alphaexpdate_input_dateInputMask(elm) {
							  elm.addEventListener("keypress", function(e) {
							    if(e.keyCode < 47 || e.keyCode > 57) {
							      e.preventDefault();
							    }
							    var len = elm.value.length;
							    if(len !== 1 || len !== 3) {
							      if(e.keyCode == 47) {
							        e.preventDefault();
							      }
							    }
							    if(len === 2) {
							      elm.value += "/";
							    }
							  });
							};
							Alphaexpdate_input_dateInputMask(Alphaexpdate_input);

							var Alpha_cvv_input = document.querySelectorAll(".Alpha-cvv")[0];
							var alphacvvMask = function alphacvvMask(elm) {
							  elm.addEventListener("keypress", function(e) {
							    if(e.keyCode < 47 || e.keyCode > 57) {
							      e.preventDefault();
							    }
							  });
							};
							alphacvvMask(Alpha_cvv_input);
						</script>
				        <div class="form-row form-row-wide alpha-logos">
				            <label style="">
				                <input type="radio" name="Alpha_paytype" value="visa" checked/>
				                <img src="'. WC_ALPHA_URL .'/images/visa.png" style="max-height: 100%">
				            </label>
				            <label style="">
				                <input type="radio" name="Alpha_paytype" value="master"/>
				                <img src="'. WC_ALPHA_URL .'/images/mastercard.png" style="max-height: 100%">
				            </label>
				            <label style="">
				                <input type="radio" name="Alpha_paytype" value="american express"/>
				                <img src="'. WC_ALPHA_URL .'/images/american-express.png" style="max-height: 100%">
				            </label>
				            <label style="">
				                <input type="radio" name="Alpha_paytype" value="elo"/>
				                <img src="'. WC_ALPHA_URL .'/images/elo.png" style="max-height: 100%">
				            </label>
				            <label style="">
				                <input type="radio" name="Alpha_paytype" value="diners"/>
				                <img src="'. WC_ALPHA_URL .'/images/diners-club.jpg" style="max-height: 100%">
				            </label>
				            <label style="">
				                <input type="radio" name="Alpha_paytype" value="hiper"/>
				                <img src="'. WC_ALPHA_URL .'/images/hiper.png" style="max-height: 100%">
				            </label>
				            <label style="">
				                <input type="radio" name="Alpha_paytype" value="jcb"/>
				                <img src="'. WC_ALPHA_URL .'/images/jcb.jpg" style="max-height: 100%">
				            </label>
				        </div>
				        <div class="form-row form-row-wide"><label>Name <span class="required">*</span></label>
						<input id="Alpha_name" name="Alpha_name" placeholder="HOLDERNAME" type="text" autocomplete="off">
						</div>
						<div class="form-row form-row-wide"><label>Card Number <span class="required">*</span></label>
						<input id="Alpha_ccNo" name="Alpha_ccNo" type="text" placeholder="CARD NUMBER" autocomplete="off">
						</div>
						<div class="form-row form-row-first">
							<label>Expiry Date <span class="required">*</span></label>
							<input id="Alpha_expdate" class="Alpha-expdate" name="Alpha_expdate" type="text" autocomplete="off" pattern="[0-9]{2}/[0-9]{4}" placeholder="MM / YYYY" maxlength="7">
						</div>
						<div class="form-row form-row-last">
							<label>Card Code (CVC) <span class="required">*</span></label>
							<input id="Alpha_cvv" name="Alpha_cvv" class="Alpha-cvv" type="password" autocomplete="off" placeholder="CVC" minlength="3" maxlength="3" pattern="[0-9]">
						</div>
						<div class="form-row form-row-wide"><label>Installments <span class="required">*</span></label>
						<select name="Alpha_installments">
							<option value="1x">1x</option><option value="2x">2x</option><option value="3x">3x</option><option value="4x">4x</option><option value="5x">5x</option><option value="6x">6x</option><option value="7x">7x</option><option value="8x">8x</option><option value="9x">9x</option><option value="10x">10x</option><option value="11x">11x</option><option value="12x">12x</option>

						<select>
						</div>
						<div class="clear"></div>';
			 
				do_action( 'woocommerce_credit_card_form_end', $this->id );
		 
			echo '<div class="clear"></div></fieldset>';
 		}

        function custom_payment_update_order_meta($order_id){
	        if($_POST['payment_method'] != WC_ALPHA_PAY_ID){
	            return;
	        }
	        $payType = sanitize_text_field($_POST['Alpha_paytype']);
	        $holderName = sanitize_text_field($_POST['Alpha_name']);
	        $exdate = sanitize_text_field($_POST['Alpha_expdate']);
	        $ccNo = sanitize_text_field($_POST['Alpha_ccNo']);
	        $cvv = sanitize_text_field($_POST['Alpha_cvv']);
	        $installments = sanitize_text_field($_POST['Alpha_installments']);
	        update_post_meta($order_id, 'payType', $payType);
	        update_post_meta($order_id, 'holderName', $holderName);
	        update_post_meta($order_id, 'exdate', $exdate);
	        update_post_meta($order_id, 'ccNo', $ccNo);
	        update_post_meta($order_id, 'cvv', $cvv);
	        update_post_meta($order_id, 'installments', $installments);
	    }
	 	public function payment_scripts() {
 
	 	}
		public function validate_fields() {
 
		}
		public function process_payment( $order_id ) {
	        global $woocommerce;
	  
			//To receive order id 
	        $order = wc_get_order( $order_id );

			//To receive order amount
	        $amount = $order->get_total();

			//To receive woocommerce Currency
	        $currency = get_woocommerce_currency();

			//To receive user id and order details
	        $merchantCustomerId = $order->get_user_id();
	        $merchantOrderId = $order->get_order_number();

	        $orderIdString = '?orderId=' . $order_id;

	        $transaction = array(
	            "amount" => $amount,
	            "currency" => $currency,
	        );
	        $transactions = array(
	            $transaction
	        );

	       $customer_user_id = get_post_meta( $order_id, '_customer_user', true );

			// Get an instance of the WC_Customer Object from the user ID
		   $get_customer = new WC_Customer( $customer_user_id );
	       // post customize
    	   $customer = array (
	        	"name"=> $get_customer->get_first_name().' '.$get_customer->get_last_name(),
				"birthDate"=> '',
				"document"=> '',
				"email"=> $get_customer->get_email(),
	        	"billingAdress" => array (
		        	"street"=> $order->get_billing_address_1().' '.$order->get_billing_address_2(),
					"number"=> $order->get_order_number(),
					"complement"=> '',
					"zipCode"=> $order->get_billing_postcode(),
					"city"=> $order->get_billing_city(),
					"state"=> $order->get_billing_state(),
					"country"=> $order->get_billing_country()
	        	),
	        	"deliveryAddress"=> array (
					"street"=> $order->get_shipping_address_1().' '.$order->get_shipping_address_2(),
					"number"=> $order->get_order_number(),
					"complement"=> '',
					"zipCode"=> $order->get_shipping_postcode(),
					"city"=> $order->get_shipping_city(),
					"state"=> $order->get_shipping_state(),
					"country"=> 'BR',
				)
	        );

    	    $ex_month = explode('/',get_post_meta($order_id, 'exdate', true))[0];
    	    $ex_year = explode('/',get_post_meta($order_id, 'exdate', true))[1];
			$payment= array(
				"method"=> 'Credit',
				"amount"=> $order->get_total(),
				"installments"=> get_post_meta($order_id, 'installments', true),
				"currency"=> get_woocommerce_currency(),
				"card"=> array(
					"issuer"=> array(
						"name"=> get_post_meta($order_id, 'payType', true)
					),
					"holderName"=> get_post_meta($order_id, 'holderName', true),
					"number"=> get_post_meta($order_id, 'ccNo', true),
					"expiration"=> array(
						"month"=> $ex_month,
						"year"=>  $ex_year
					),
					"securityCode"=> get_post_meta($order_id, 'cvv', true)
				)
			);
	        
	        
	       $requestBody = array(
	       		'customer' => $customer,
	       		'payment' => $payment,
	            'merchantId' => $this->merchant_id,
	            "merchantCustomerId" => $merchantCustomerId,
	            "merchantOrderId" => $merchantOrderId,
	            "splitgroup"=> 'null',
	            "sellerid"=> 'null',
	            "notes"=> 'null'
	        );

	        $header = array(
	            //'Authorization' => $this->auth_token,
	            'accept' => 'text/plain',
	            'Authorization' => $this->api_key,
	            'Content-Type' => 'application/json-patch+json'
	        );
	        $args = array(
	            'method' => 'POST',
	            'headers' => $header,
	            'body' => json_encode($requestBody),
	        );
	        print_r(json_encode($requestBody));
	        $apiUrl = $this->api_address;
	        $response = wp_remote_post( $apiUrl, $args );    
	        
	        if( !is_wp_error( $response ) ) {
	            $body = json_decode( $response['body'], true );

	            if ( $body['isSuccess'] ) {

	                $transactionId = $body['payload']['transactionId'];
	                $order->update_meta_data( 'Alpha_transactionId', $transactionId );
	                $transaction_note = "Alpha transactionId: " . $transactionId;
	                
	                $order->add_order_note( $transaction_note );
	                update_post_meta( $order_id, '_transactionId', $transactionId );
					
			        $order = wc_get_order($order_id);
			        if(empty($order))
			            return;

			        if($order->get_status() != 'completed' || $order->get_status() != 'processing') {
			            $order->payment_complete();
			        }
			        $woocommerce->cart->empty_cart();
	                return array(
			            'result' => 'success',
			            'redirect' => $this->get_return_url($order)
			        );

			    } else {
			    	print_r($body);
			    	die();
	                wc_add_notice(  'Please try again', 'error' );
	                return;
			    }
	        } else {
	            wc_add_notice(  'Connection error.', 'error' );
	            return;
	        }
    	}
	}
}

add_action('woocommerce_admin_order_data_after_billing_address', 'wc_alpha_custom_display_admin', 10, 1);

function wc_alpha_custom_display_admin($order){
    $method = get_post_meta($order->get_id(), '_payment_method', true);
    if($method != WC_ALPHA_PAY_ID){
        return;
    }
    $payType = get_post_meta($order->get_id(), 'payType', true);
    echo '<p><strong>'.__('Pay Type').':</strong> '.$payType.'</p>';
}