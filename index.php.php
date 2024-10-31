<?php
/*
Plugin Name: Resultat Finans Betallösning
Plugin URI: https://wordpress.org/plugins/Resultat-finans-betallosning/
Description: Resultat Finans Betallösning för smidig och billig faktura- och påminnelsehantering med inkasso och efterbevakning.
Version: 2.2
Author: Resultat Finans
Author URI: http://www.resultatfinans.se
*/

add_action('plugins_loaded', 'woocommerce_mrova_payu_init', 0);

function woocommerce_mrova_payu_init() {
	
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	class WC_ResultatFinans_Payment extends WC_Payment_Gateway {
        private $_token = 'X3mzS712j2optJZKR1MvzOad79yw4Luk';
        public $new_order_status = '_none';
        public $approved_order_status = 'wc-processing';
        public $cancelled_order_status = 'wc-cancelled';
        
        public function __construct() {
			$this -> id = 'resultatfinans';
			$this -> medthod_title = 'Resultat Finans';
			$this -> has_fields = false; //true??

			$this -> init_form_fields();
			$this -> init_settings();

			$this -> title = $this -> settings['title'];
			$this -> description = $this -> settings['description'];
			$this -> merchant_id = $this -> settings['merchant_id'];
			$this -> salt = $this -> settings['salt'];
			$this -> redirect_page_id = $this -> settings['redirect_page_id'];
			$this -> liveurl = 'https://faktura.resultatfinans.se/betala';
			$this -> icon = 'https://faktura.resultatfinans.se/images/resultat_finans_wc_logo.png';
            
            $this -> new_order_status = $this -> settings['new_order_status'];
            $this -> approved_order_status = $this -> settings['approved_order_status'];
            $this -> cancelled_order_status = $this -> settings['cancelled_order_status'];
      
			$this -> msg['message'] = "";
			$this -> msg['class'] = "";

      
			//	Kommenterad för att inte uppdatera en order med automatik...
			//add_action( 'init', array( &$this, 'check_payu_response' ) );
	  
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
            add_action( 'woocommerce_receipt_payu', array( &$this, 'receipt_page' ) );
			add_action( 'woocommerce_api_wc_resultatfinans_payment', array( $this, 'check_response' ) );
		}
    
		function init_form_fields() {

            $order_statuses = array( '_none' => __( 'Current order status', 'mrova' ) ) + wc_get_order_statuses();
            
			$this -> form_fields = array(
				'enabled' => array(
					'title' => __( 'Enable/Disable', 'mrova' ),
					'type' => 'checkbox',
					'label' => __( 'Aktivera Resultat Finans Payment Module.', 'mrova' ),
					'default' => 'no'
				),
				'title' => array(
					'title' => __( 'Titel:', 'mrova' ),
					'type'=> 'text',
					'description' => __( 'Texten som visas f&ouml;r anv&auml;ndaren vid betalning.', 'mrova' ),
					'default' => __( 'Resultat Finans', 'mrova' )
				),
				'description' => array(
					'title' => __( 'Beskrivning:', 'mrova' ),
					'type' => 'textarea',
					'description' => __( 'Denna beskrivning visas f&ouml;r anv&auml;ndaren vid betalning.', 'mrova' ),
					'default' => __( 'Betala på faktura eller delbetala', 'mrova' )
				),
				'merchant_id' => array(
					'title' => __( 'Client Id', 'mrova' ),
					'type' => 'text',
					'description' => __( 'Client Id f&ouml;r du av Resultat Finans' )
				),
				'username' => array(
					'title' => __( 'Anv&auml;ndarnamn', 'mrova' ),
					'type' => 'text',
					'description' => __( 'Ert anv&auml;ndarnamn hos Resultat Finans' )
				),
				'password' => array(
					'title' => __( 'L&ouml;senord', 'mrova' ),
					'type' => 'text',
					'description' => __( 'Ert l&ouml;senord hos Resultat Finans' ),
				),
				'salt' => array(
                    'title' => __( 'Secret Key', 'mrova' ),
                    'type' => 'text',
                    'description' => __( 'A secret key that is used in communication with Resultat Finans', 'mrova' ),
                    'default' => wp_generate_password( 32 ),
                ),
                'redirect_page_id' => array(
					'title' => __( 'Return Page' ),
					'type' => 'select',
					'options' => $this -> get_pages( 'Select Page' ),
					'description' => "Sida att returneras till"
				),
                'new_order_status' => array(
                    'title' => __( 'New order status', 'mrova' ),
                    'type' => 'select',
                    'options' => $order_statuses,
                    'description' => __( 'Select the order status that will be used for new orders', 'mrova' ),
                    'default' => $this->new_order_status,
                ),
                'approved_order_status' => array(
                    'title' => __( 'Approved order status', 'mrova' ),
                    'type' => 'select',
                    'options' => $order_statuses,
                    'description' => __( 'Select the order status that will be used for approved orders', 'mrova' ),
                    'default' => $this->approved_order_status,
                ),
                'cancelled_order_status' => array(
                    'title' => __( 'Cancelled order status', 'mrova' ),
                    'type' => 'select',
                    'options' => $order_statuses,
                    'description' => __( 'Select the order status that will be used for cancelled orders', 'mrova' ),
                    'default' => $this->cancelled_order_status,
                ),
			);
		}

		public function admin_options() {
			echo '<h3>' . __( 'Resultat Finans Payment Gateway', 'mrova' ) . '</h3>';
			echo '<p>' . __( 'Resultat Finans &auml;r en ny betall&ouml;sning med faktura och delbetalning.' ) . '</p>';
			echo '<table class="form-table">';
			// Generate the HTML For the settings form.
			$this -> generate_settings_html();
			echo '</table>';

		}

		/**
		 *  There are no payment fields for payu, but we want to show the description if set.
		 **/
		function payment_fields() {
			if ( $this -> description ) {
				echo wpautop( wptexturize( $this -> description ) );
			}
		}
        
        /**
         * Receipt Page
         **/
        function receipt_page($order){
            echo '<p>'.__('Tack f&ouml;r din order. V&auml;nligen klicka på knappen nedan f&ouml;r att betala med Resultat Finans.', 'mrova').'</p>';
           echo $this -> generate_payu_form($order);
        }
        
        /**
         * Generate payu button link
         **/
        public function generate_payu_form($order_id){
            global $woocommerce;
            
            $order = new WC_Order( $order_id );
            $txnid = $order_id . '_' . date("ymds");

            $redirect_url = ( $this -> redirect_page_id=="" || $this -> redirect_page_id==0 ) ? get_site_url() . "/" : get_permalink( $this -> redirect_page_id );

            $productinfo = "Order $order_id";

            $str = "$this->merchant_id|$txnid|$order->order_total|$productinfo|$order->billing_first_name|$order->billing_email|||||||||||$this->salt";
            $hash = hash('sha512', $str);

            $payu_args = array(
                'key' => $this -> merchant_id,
                'txnid' => $txnid,
                'amount' => $order -> order_total,
                'productinfo' => $productinfo,
                'firstname' => $order -> billing_first_name,
                'lastname' => $order -> billing_last_name,
                'address1' => $order -> billing_address_1,
                'address2' => $order -> billing_address_2,
                'city' => $order -> billing_city,
                'state' => $order -> billing_state,
                'country' => $order -> billing_country,
                'zipcode' => $order -> billing_zip,
                'email' => $order -> billing_email,
                'phone' => $order -> billing_phone,
                'surl' => $redirect_url,
                'furl' => $redirect_url,
                'curl' => $redirect_url,
                'hash' => $hash,
                'pg' => 'NB'
            );

            $payu_args_array = array();
            foreach ( $payu_args as $key => $value ){
                $payu_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
            }
            
            return '<form action="'.$this -> liveurl.'" method="post" id="payu_payment_form">
                ' . implode( '', $payu_args_array ) . '
                <input type="submit" class="button-alt" id="submit_payu_payment_form" value="' . __( 'Pay via PayU', 'mrova' ).'" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __( 'Cancel order &amp; restore cart', 'mrova' ) . '</a>
                <script type="text/javascript">
                jQuery(function(){
                    jQuery("body").block(
                        {
                            message: "<img src=\"' . $woocommerce->plugin_url() . '/assets/images/ajax-loader.gif\" alt=\"Redirectingâ€¦\" style=\"float:left; margin-right: 10px;\" />' . __('Thank you for your order. We are now redirecting you to Payment Gateway to make payment.', 'mrova') . '",
                            overlayCSS:
                                {
                                    background: "#fff",
                                    opacity: 0.6
                                },
                            css: {
                                padding:        20,
                                textAlign:      "center",
                                color:          "#555",
                                border:         "3px solid #aaa",
                                backgroundColor:"#fff",
                                cursor:         "wait",
                                lineHeight:"32px"
                            }
                        });
                    jQuery("#submit_payu_payment_form").click();});</script>
            </form>';
        }
		
		function process_payment($order_id){
            global $woocommerce;
			$order = new WC_Order( $order_id );
		
            // Set order status for new order
            if ( wc_is_order_status( $this->new_order_status ) ) {
                $order->update_status( $this->new_order_status, __( 'Avvaktar godkännande', 'mrova' ) );
            }
		
			// Reduce stock levels
			//$order->reduce_order_stock();
		
			// Remove cart
			$woocommerce->cart->empty_cart();
		
		
			//Create payment information at Resultat Finans
			$text = '' . $this->settings['merchant_id'] . '|' . ( $order->get_total() - $order->get_total_tax() ) . '|' . $order->get_total_tax() . '';
            
            // Create hash key
            $hash = hash( 'sha256', "$this->merchant_id|$order->order_total|$order_id|$order->billing_first_name|$order->billing_email|$this->salt" );
			
			//Loop order rows and creat a string containing the order rows and it's info
			//|1;Kingston USB-minne 32GB;1000;250
			//Antal, Beskrivning, Belopp, Moms
			//Items separated with ¤
			
			$itemsText = '';
			$items = $order->get_items();
			
			foreach ( $items as $item ) {
			    $itemsText .= $item['qty'] . ';' . $item['name'] . ';' . $item['line_total'] . ';' . $item['line_tax'] . ';' . '10' . '¤';
			}
			
			//Shipping
			$shipping = floatval( $order->get_total_shipping() );
			if ($shipping > 0) {
			    $itemsText .= '1' . ';' . 'Frakt' . ';' . $shipping . ';' . '0' . ';' . '20' . '¤';
			}


			//Invoice dept
			$fee_title = $this->settings['pay4pay_item_title'];
			$cost = floatval( $this->settings['pay4pay_charges_fixed'] );
			
			if ( $cost > 0 ) { 
	  		    $itemsText .= '1' . ';' . $fee_title . ';' . $cost . ';' . '0' . ';' . '30' . '¤';
			}

			//Convert text to Base64
			$base64 = base64_encode( $text . '|' . $itemsText . '|' . $hash );
			
			//Auth
			$auth = '' . $this->settings['username'] . ':' . $this->settings['password'] . '';
			$auth64 = 'Basic ' . base64_encode( $auth );

			// Create a stream
			$opts = array(
				'http' => array(
					'method' => "GET",
					'header' => "Authorization:" . $auth64 . "\r\n" . "Data:" . $base64 . "\r\n"
				)
			);
			
			$context = stream_context_create( $opts );
			
			// Open the file using the HTTP headers set above
			$response = file_get_contents( 'https://faktura.resultatfinans.se/REST.svc/createorder/' . $order_id . '', false, $context);
			error_log( 'Resultat Finans REST Response: ' . $response );
			
			//Check if response is OK.
			return array(
				'result' => 'success',
				'redirect' => 'https://faktura.resultatfinans.se/betala?OrderId=' . $order_id . '&ClientId=' . $this->settings['merchant_id'] . ''
			);
		}
        
        function SendRequest( $url, $method = 'GET', $data = array(), $headers = array( 'Content-type: application/x-www-form-urlencoded' ) ) {
            $context = stream_context_create( array(
                'http' => array(
                    'method' => $method,
                    'header' => $headers,
                    'content' => http_build_query( $data )
                )
            ) );
 
            return file_get_contents($url, false, $context);
        }

		/**
		 * Check for valid payu server callback
		 **/
		function check_payu_response() {
			global $woocommerce;
			
			if ( isset( $_REQUEST['txnid'] ) && isset( $_REQUEST['mihpayid'] ) ) {
				$order_id_time = $_REQUEST['txnid'];
				$order_id = explode( '_', $_REQUEST['txnid'] );
				$order_id = ( int ) $order_id[0];
				
				if ( $order_id != '' ) {
					try{
						$order = new WC_Order( $order_id );
						$merchant_id = $_REQUEST['key'];
						$amount = $_REQUEST['Amount'];
						$hash = $_REQUEST['hash'];

						$status = $_REQUEST['status'];
						$productinfo = "Order $order_id";
						echo $hash;
						echo "{$this->salt}|$status|||||||||||{$order->billing_email}|{$order->billing_first_name}|$productinfo|{$order->order_total}|$order_id_time|{$this->merchant_id}";
						$checkhash = hash( 'sha512', "{$this->salt}|$status|||||||||||{$order->billing_email}|{$order->billing_first_name}|$productinfo|{$order->order_total}|$order_id_time|{$this->merchant_id}" );
						$transauthorised = false;
						if ( $order -> status !=='completed' ){
							if ( $hash == $checkhash ) {

								$status = strtolower( $status );

								if ( $status=="success" ){
									$transauthorised = true;
									$this -> msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.";
									$this -> msg['class'] = 'woocommerce_message';
									if ( $order -> status == 'processing' ) {

									} else {
										$order -> payment_complete();
										$order -> add_order_note( 'PayU payment successful<br/>Unnique Id from PayU: ' . $_REQUEST['mihpayid'] );
										$order -> add_order_note( $this->msg['message'] );
										$woocommerce -> cart -> empty_cart();
									}
								} elseif ( $status == "pending" ) {
									$this -> msg['message'] = "Thank you for shopping with us. Right now your payment staus is pending, We will keep you posted regarding the status of your order through e-mail";
									$this -> msg['class'] = 'woocommerce_message woocommerce_message_info';
									$order -> add_order_note( 'PayU payment status is pending<br/>Unnique Id from PayU: ' . $_REQUEST['mihpayid'] );
									$order -> add_order_note( $this->msg['message'] );
									$order -> update_status( 'on-hold' );
									$woocommerce -> cart -> empty_cart();
								} else {
									$this -> msg['class'] = 'woocommerce_error';
									$this -> msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
									$order -> add_order_note( 'Transaction Declined: ' . $_REQUEST['Error'] );
									//Here you need to put in the routines for a failed
									//transaction such as sending an email to customer
									//setting database status etc etc
								}
							} else {
								$this -> msg['class'] = 'error';
								$this -> msg['message'] = "Security Error. Illegal access detected";

								//Here you need to simply ignore this and dont need
								//to perform any operation in this condition
							}
							
							if ( $transauthorised==false ) {
								$order -> update_status( 'failed' );
								$order -> add_order_note( 'Failed' );
								$order -> add_order_note( $this->msg['message'] );
							}
							
							add_action( 'the_content', array( &$this, 'showMessage' ) );
						}
					
					} catch( Exception $e ) {
                        // $errorOccurred = true;
                        $msg = "Error";
                    }
				}
			}
		}
        
        function showMessage( $content ){
            return '<div class="box ' . $this -> msg['class'] . '-box">' . $this -> msg['message'] . '</div>' . $content;
        }
		
		// get all pages
		function get_pages( $title = false, $indent = true ) {
			$wp_pages = get_pages( 'sort_column=menu_order' );
			$page_list = array();
			if ($title) $page_list[] = $title;
			foreach ( $wp_pages as $page ) {
				$prefix = '';
				// show indented child pages?
				if ($indent) {
					$has_parent = $page->post_parent;
					while ( $has_parent ) {
						$prefix .=  ' - ';
						$next_page = get_page( $has_parent );
						$has_parent = $next_page->post_parent;
					}
				}
				// add to page list array array
				$page_list[$page->ID] = $prefix . $page->post_title;
			}
			return $page_list;
		}
        
        /**
         * Den här metoden körs vid anropet med information om godkänd/nekad betalning.
         * Följande parametrar förväntas i URL:en:
         * * orderID : ordernumret på ordern anropet avser
         * * token : hemlig nyckel som används för verifiering
         * * outcome : 'pass' eller 'fail'. Betalning är godkänd eller nekad.
         */
        function check_response() {
            if ( ! isset( $_GET['orderId'] ) ||  ! isset( $_GET['token'] ) || ! isset( $_GET['outcome'] ) ) {
                return false;
            }
            
            $order_id = $_GET['orderId'];
            $outcome = $_GET['outcome'];
            
            try {
                $order = new WC_Order( $order_id );
                
                $hash = hash( 'sha256', "$this->merchant_id|$order->order_total|$order_id|$order->billing_first_name|$order->billing_email|$this->salt" );
                if ( $hash !== $_GET['token'] ) {
                    return false;
                }
                
                //if ( $order->has_status( 'on-hold' ) ) {
                    if ( $outcome == 'pass' ) {
						if ( wc_is_order_status( $this->approved_order_status ) ) {
                            $order->update_status( $this->approved_order_status, __( 'Betalning genomförd', 'mrova' ) );
                        } else {
                            $order->payment_complete();
                        }
                    } elseif ( $outcome == 'fail' ) {
                        if ( wc_is_order_status( $this->cancelled_order_status ) ) {
                            $order->update_status( $this->cancelled_order_status, __( 'Betalning nekades', 'mrova' ) );
                        } else {
                            $order->update_status( 'failed', __( 'Betalning nekades', 'mrova' ) );
                        }
                    }
                    
                    return true;
                /*} else {
                  return false;  
                }*/
            } catch ( Exception $e ) {
                echo $e->getMessage();
                return false;
            }
        }
	}
	
	/**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_mrova_payu_gateway( $methods ) {
        $methods[] = 'WC_ResultatFinans_Payment';
        return $methods;
    }
    
    /**
     * Display configuration link in the plugin area
     */
    function woocommerce_mrova_payu_action_links( $links ) {
        $plugin_links = array(
            '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_resultatfinans_payment' ) . '">' . __( 'Settings', 'mrova' ) . '</a>',
        );
	
        return array_merge( $plugin_links, $links );
    }

    // Filter hooks
    add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'woocommerce_mrova_payu_action_links' );
    add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_mrova_payu_gateway' );
}
