<?php
/*
Plugin Name: 		GoUrl WooCommerce - Bitcoin Altcoin Payment Gateway Addon
Plugin URI: 		https://gourl.io/bitcoin-payments-woocommerce.html
Description: 		Provides a <a href="https://gourl.io">GoUrl.io</a> Payment Gateway for WooCommerce 2.1+. Support product prices in USD/EUR/etc or in Bitcoin/Altcoins directly and sends the amount straight to your business Bitcoin/Altcoin wallet. Convert your USD/EUR/etc prices to cryptocoins using Google/Cryptsy Exchange Rates. Direct Integration on your website, no external payment pages opens (as other payment gateways offer). Accept Bitcoin, Litecoin, Dogecoin, Speedcoin, Darkcoin, Vertcoin, Reddcoin, Feathercoin, Vericoin, Potcoin payments online. You will see the bitcoin/altcoin payment statistics in one common table on your website. No Chargebacks, Global, Secure. All in automatic mode.
Version: 			1.0.2
Author: 			GoUrl.io
Author URI: 		https://gourl.io
License: 			GPLv2
License URI: 		http://www.gnu.org/licenses/gpl-2.0.html
GitHub Plugin URI: 	https://github.com/cryptoapi/Bitcoin-Payments-Woocommerce
*/


if (!defined( 'ABSPATH' )) exit; // Exit if accessed directly in wordpress


add_action( 'plugins_loaded', 'gourl_wc_gateway_load', 0 );


function gourl_wc_gateway_load() 
{
	
	// WooCommerce required
	if (!class_exists('WC_Payment_Gateway')) return;

	
	DEFINE('GOURLWC', "gourlwc");
	
	add_filter( 'woocommerce_payment_gateways', 		'gourl_wc_gateway_add' );
	add_filter( 'plugin_action_links', 					'gourl_wc_action_links', 10, 2 );
	add_action( 'woocommerce_view_order', 				'gourl_wc_payment_history', 10, 1 );
	add_action( 'woocommerce_email_after_order_table', 	'gourl_wc_payment_link', 15, 2 );
	add_filter( 'woocommerce_currencies', 				'gourl_wc_currency' );
	add_filter( 'woocommerce_currency_symbol', 			'gourl_wc_currency_symbol', 10, 2);
	
	
	
	/*
	 *	1. 
	 */
	function gourl_wc_gateway_add( $methods ) 
	{
		if (!in_array('WC_Gateway_Gourl', $methods)) {
			$methods[] = 'WC_Gateway_GoUrl';
		}
		return $methods;
	}

	
	/*
	 *	2. 
	 */
	function gourl_wc_action_links($links, $file) 
	{
		static $this_plugin;
	
		if (false === isset($this_plugin) || true === empty($this_plugin)) {
			$this_plugin = plugin_basename(__FILE__);
		}
	
		if ($file == $this_plugin) {
			$settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=wc_gateway_gourl">'.__( 'Settings', GOURLWC ).'</a>';
			array_unshift($links, $settings_link);
			
			if (defined('GOURL'))
			{
				$unrecognised_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page='.GOURL.'payments&s=unrecognised">'.__( 'Unrecognised', GOURLWC ).'</a>';
				array_unshift($links, $unrecognised_link);
				$payments_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page='.GOURL.'payments&s=gourlwoocommerce">'.__( 'Payments', GOURLWC ).'</a>';
				array_unshift($links, $payments_link);
			}
		}
		
		return $links;
	}
	
	

	/*
	 *	3. 
	 */
	function gourl_wc_payment_history( $order_id ) 
	{
		$order = new WC_Order( $order_id );
		
		$coin = strtolower(get_post_meta($order->id, 'coinname', true));
		
		if (is_user_logged_in() && ($coin || (stripos($order->payment_method_title, "bitcoin")!==false && ($order->status == "pending" || $order->post_status=="wc-pending"))) && (is_super_admin() || get_current_user_id() == $order->user_id))
		{
			echo "<br><a href='".$order->get_checkout_order_received_url()."&gourlcryptocoin=".$coin."' class='button wc-forward'>".__( 'View Payment Details', GOURLWC )." </a>";
		
		}
		
		return true;
	}
	

	/*
	 *	4.
	*/
	function gourl_wc_payment_link( $order, $is_admin_email )
	{
		$coin = strtolower(get_post_meta($order->id, 'coinname', true));
		
		if ($coin) echo "<br><h4><a href='".$order->get_checkout_order_received_url()."&gourlcryptocoin=".$coin."'>".__( 'View Payment Details', GOURLWC )." </a></h4><br>";
		
		return true;
	}
	


	/*
	 *	5.
	*/
	function gourl_wc_currency ( $currencies ) 
	{
		global $gourl; 
		
		if (class_exists('gourlclass') && defined('GOURL') && defined('GOURL_ADMIN') && is_object($gourl))
		{
			$arr = $gourl->coin_names(); 
		
			foreach ($arr as $k => $v)
				$currencies[$k] = __( "Cryptocurrency", 'woocommerce' ) . " - " . __( ucfirst($v), 'woocommerce' );
		}
		
		return $currencies;
	}
	
	
	
	
	/*
	 *	6.
	*/
	function gourl_wc_currency_symbol ( $currency_symbol, $currency )
	{
		global $gourl;
	
		if (class_exists('gourlclass') && defined('GOURL') && defined('GOURL_ADMIN') && is_object($gourl))
		{
			$arr = $gourl->coin_names();
	
			if (isset($arr[$currency])) $currency_symbol = $currency; 
		}
	
		return $currency_symbol;
	}	
	
	
	
	
	/*
	 *	7. Payment Gateway WC Class 
	 */
	class WC_Gateway_GoUrl extends WC_Payment_Gateway 
	{
		
		private $payments 			= array();
		private $languages 			= array();
		private $coin_names			= array();
		private $statuses 			= array('processing' => 'Processing Payment', 'on-hold' => 'On Hold', 'completed' => 'Completed');
		private $mainplugin_url		= "/wp-admin/plugin-install.php?tab=search&type=term&s=GoUrl+Bitcoin+Payment+Gateway+Downloads";
		
		
		/*
		 * 7.1
		*/
	    public function __construct() 
	    {
	    	global $gourl;
	    	
			$this->id                 	= 'gourlpayments';
			$this->icon         	  	= apply_filters('woocommerce_gourlpayments_icon', plugin_dir_url( __FILE__ ).'gourlpayments.png' );
			$this->method_title       	= __( 'GoUrl Bitcoin/Altcoins', GOURLWC );
			$this->method_description  	= "<img style='float:left; margin-right:15px' src='".plugin_dir_url( __FILE__ )."gourlpayments.png'>";
			$this->method_description  .= __( '<a target="_blank" href="https://gourl.io/bitcoin-payments-woocommerce.html">Plugin Homepage &#187;</a>', GOURLWC ) . "<br>";
			$this->method_description  .= __( '<a target="_blank" href="https://github.com/cryptoapi/Bitcoin-Payments-Woocommerce">Plugin on Github - 100% Free Open Source &#187;</a>', GOURLWC ) . "<br><br>";
			$this->has_fields         	= false;
				
			if (class_exists('gourlclass') && defined('GOURL') && defined('GOURL_ADMIN') && is_object($gourl))
			{ 
				if (true === version_compare(GOURL_VERSION, '1.2.6', '<'))
				{
					$this->method_description .= '<div class="error"><p>' .sprintf(__( '<b>Your GoUrl Bitcoin Gateway <a href="%s">Main Plugin</a> version is too old. Requires 1.2.6 or higher version. Please <a href="%s">update</a> to latest version.</b>  &#160; &#160; &#160; &#160; Information: &#160; <a href="https://gourl.io/bitcoin-wordpress-plugin.html">Plugin Homepage</a> &#160; &#160; &#160; <a href="https://wordpress.org/plugins/gourl-bitcoin-payment-gateway-paid-downloads-membership/">WordPress.org Plugin Page</a>', GOURLWC ), GOURL_ADMIN.GOURL, $this->mainplugin_url).'</p></div>';
				}
				elseif (true === version_compare(WOOCOMMERCE_VERSION, '2.1', '<'))
				{
					$this->method_description .= '<div class="error"><p><b>' .__( 'Your WooCommerce version is too old. The GoUrl payment plugin requires WooCommerce 2.1 or higher to function. Please contact your web server administrator for assistance.', GOURLWC ).'</b></p></div>';
				}
				else 
				{
					$this->payments 			= $gourl->payments(); 		// Activated Payments
					$this->coin_names			= $gourl->coin_names(); 	// All Coins
					$this->languages			= $gourl->languages(); 		// All Languages
				}
			}
			else
			{
				$this->method_description .= '<div class="error"><p>' .sprintf(__( '<b>You need to install GoUrl Bitcoin Gateway Main Plugin also. Go to - <a href="%s">Bitcoin Gateway plugin page</a></b> &#160; &#160; &#160; &#160; Information: &#160; <a href="https://gourl.io/bitcoin-wordpress-plugin.html">Plugin Homepage</a> &#160; &#160; &#160; <a href="https://wordpress.org/plugins/gourl-bitcoin-payment-gateway-paid-downloads-membership/">WordPress.org Plugin Page</a> ', GOURLWC ), $this->mainplugin_url).'</p></div>';
			}
			
			$this->method_description  .= sprintf(__( 'Accept %s payments online in WooCommerce.', GOURLWC), ($this->coin_names?ucwords(implode(", ", $this->coin_names)):"Bitcoin, Litecoin, Dogecoin, Speedcoin, Darkcoin, Vertcoin, Reddcoin, Feathercoin, Vericoin, Potcoin")).'<br/>';
			$this->method_description .= __( 'If you use multiple stores, please create separate <a target="_blank" href="https://gourl.io/editrecord/coin_boxes/0">GoUrl Payment Box</a> (with unique payment box public/private keys) for each of your stores/websites. Do not use the same GoUrl Payment Box with the same public/private keys on your different websites/stores.', GOURLWC );
			$this->method_description .= '<br/><br/>';
				

			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
	
			
			// Define user set variables
			$this->enabled       = $this->get_option( 'enabled' );
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->emultiplier  = trim(str_replace("%", "", $this->get_option( 'emultiplier' )));
			$this->ostatus  	= $this->get_option( 'ostatus' );
			$this->ostatus2  	= $this->get_option( 'ostatus2' );
			$this->deflang  	= $this->get_option( 'deflang' );
			$this->defcoin  	= $this->get_option( 'defcoin' );
			$this->iconwidth  	= str_replace("px", "", $this->get_option( 'iconwidth' ));
			
			
			// Re-check
			if (!$this->title)								$this->title 		= __('GoUrl Bitcoin/Altcoins');
			if (!$this->description)						$this->description 	= __('Pay with virtual currency', GOURLWC);
			if (!isset($this->statuses[$this->ostatus])) 	$this->ostatus  	= 'processing';
			if (!isset($this->statuses[$this->ostatus2])) 	$this->ostatus2 	= 'completed';
			if (!isset($this->languages[$this->deflang])) 	$this->deflang 		= 'en';
			
			if (!$this->emultiplier || !is_numeric($this->emultiplier) || $this->emultiplier < 0.01) 	$this->emultiplier = 1;
			if (!is_numeric($this->iconwidth) || $this->iconwidth < 30 || $this->iconwidth > 250) 		$this->iconwidth = 60;
				
			if ($this->defcoin && $this->payments && !isset($this->payments[$this->defcoin])) $this->defcoin = key($this->payments);
			elseif (!$this->payments)						$this->defcoin		= '';
			elseif (!$this->defcoin)						$this->defcoin		= key($this->payments);

			
			// Hooks
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_gourlpayments', array( $this, 'cryptocoin_payment' ) );
			
			return true;
	    }

	    
	    
	    /*
	     * 7.2
	    */
	   	public function init_form_fields() 
	    {
	    	global $gourl;
	    	
	    	$coins 		= implode(", ", $this->payments);
	    	
	    	if (class_exists('gourlclass') && defined('GOURL') && is_object($gourl))
	    	{
	    		$url	= GOURL_ADMIN.GOURL."settings";
	    		$text 	= ($coins) ? $coins : __( '- Please setup -', GOURLWC );
	    		$url2	= GOURL_ADMIN.GOURL."payments&s=gourlwoocommerce";
	    		$url3	= GOURL_ADMIN.GOURL;
	    	}  
	    	else
	    	{
	    		$url	= $this->mainplugin_url;
	    		$text 	= __( 'Please install GoUrl Bitcoin Gateway WP Plugin &#187;', GOURLWC );
	    		$url2	= $url;
	    		$url3	= $url;
	    	}
	    	
	    	$this->form_fields = array(
				'enabled'		=> array(
					'title'   	  	=> __( 'Enable/Disable', GOURLWC ),
					'type'    	  	=> 'checkbox',
					'default'	  	=> 'yes',
					'label'   	  	=> sprintf(__( 'Enable Bitcoin/Altcoins Payments in WooCommerce with <a href="%s">GoUrl Bitcoin Gateway</a>', GOURLWC ), $url3)
				),
	    		'title'			=> array(
					'title'       	=> __( 'Title', GOURLWC ),
					'type'        	=> 'text',
	    			'default'     	=> __( 'Bitcoin/Altcoin', GOURLWC ),
					'description' 	=> __( 'Payment method title that the customer will see on your checkout', GOURLWC )
				),
				'description' 	=> array(
					'title'       	=> __( 'Description', GOURLWC ),
					'type'        	=> 'textarea',
					'default'     	=> trim(sprintf(__( 'Pay with virtual currency - %s', GOURLWC ), $coins), " -") . '. ' . __( '<a target="_blank" href="https://bitcoin.org/en/">What is bitcoin?</a>'),
					'description' 	=> __( 'Payment method description that the customer will see on your checkout', GOURLWC )
				),
    			'emultiplier' 	=> array(
   					'title' 		=> __('Exchange Rate Multiplier', GOURLWC ),
   					'type' 			=> 'text',
   					'default' 		=> '1.00',
    				'description' 	=> sprintf(__('The system uses the multiplier rate with today LIVE cryptocurrency exchange rates (which are updated every 30 minutes) when the transaction is calculating from a fiat currency (e.g. USD, EUR, etc) to %s. <br />Example: <b>1.05</b> - will add an extra 5%% to the total price in bitcoin/altcoins, <b>0.85</b> - will be a 15%% discount for the price in bitcoin/altcoins. Default: 1.00 ', GOURLWC ), $coins)
    			),
    			'advanced' 		=> array(
   					'title'       	=> __( 'Advanced options', GOURLWC ),
   					'type'        	=> 'title',
   					'description' 	=> ''
    			),
	    		'ostatus' 		=> array(
   					'title' 		=> __('Order Status - Cryptocoin Payment Received', GOURLWC ),
   					'type' 			=> 'select',
   					'options' 		=> $this->statuses,
   					'default' 		=> 'processing',
	    			'description' 	=> sprintf(__("Payment is received successfully from the customer. You will see the bitcoin/altcoin payment statistics in one common table <a href='%s'>'All Payments'</a> with details of all received payments.<br/>If you sell digital products / software downloads you can use the status 'Completed' showing that particular customer already has instant access to your digital products", GOURLWC), $url2)
    			),
	    		'ostatus2' 		=> array(
	    			'title' 		=> __('Order Status - Previously Received Payment Confirmed', GOURLWC ),
	    			'type' 			=> 'select',
	    			'options' 		=> $this->statuses,
	    			'default' 		=> 'completed',
	    			'description' 	=> __("About one hour after the payment is received, the bitcoin transaction should get 6 confirmations (for transactions using other cryptocoins ~ 20-30min).<br>A transaction confirmation is needed to prevent double spending of the same money.", GOURLWC)
	    		),
    			'deflang' 		=> array(
    				'title' 		=> __('PaymentBox Language', GOURLWC ),
    				'type' 			=> 'select',
    				'options' 		=> $this->languages,
    				'default' 		=> 'en',
    				'description' 	=> __("Default Crypto Payment Box Localisation", GOURLWC)
    			),
    			'defcoin' 		=> array(
   					'title' 		=> __('PaymentBox Default Coin', GOURLWC ),
   					'type' 			=> 'select',
   					'options' 		=> $this->payments,
   					'default' 		=> key($this->payments),
   					'description' 	=> sprintf(__( 'Default Coin in Crypto Payment Box. &#160; Activated Payments : <a href="%s">%s</a>', GOURLWC ), $url, $text)
    			),
	    		'iconwidth'			=> array(
					'title'       	=> __( 'Icon Width', GOURLWC ),
					'type'        	=> 'text',
	    			'label'        	=> 'px',
	    			'default'     	=> "60px",
					'description' 	=> __( 'Cryptocoin icons width in "Select Payment Method". Default 60px. Allowed: 30..250px', GOURLWC )
				)
	    	);
	    	
	    	return true;
	    }
	
	    
	    
	    
	    
	    
	    
    /*
     * 7.4 Output for the order received page.
     */
    public function cryptocoin_payment( $order_id )
	{
		global $gourl;
		
		$order = new WC_Order( $order_id );
		
		if ($order === false) throw new Exception('The GoUrl payment plugin was called to process a payment but could not retrieve the order details for order_id ' . $order_id . '. Cannot continue!');
		
		if ($order->status == "cancelled" || $order->post_status == "wc-cancelled")
		{
			echo '<h2>' . __( 'Information', GOURLWC ) . '</h2>' . PHP_EOL;
			echo "<div class='woocommerce-error'>". __( 'This order&rsquo;s status is &ldquo;Cancelled&rdquo; &mdash; it cannot be paid for. Please contact us if you need assistance.', GOURLWC )."</div>";
		}
		elseif (!class_exists('gourlclass') || !defined('GOURL') || !is_object($gourl))
		{
			echo '<h2>' . __( 'Information', GOURLWC ) . '</h2>' . PHP_EOL;
			echo "<div class='woocommerce-error'>".__( "Please try a different payment method. Admin need to install and activate wordpress plugin 'GoUrl Bitcoin Gateway' (https://gourl.io/bitcoin-wordpress-plugin.html) to accept Bitcoin/Altcoin Payments online", GOURLWC )."</div>";
		}
		elseif (!$this->payments || !$this->defcoin || true === version_compare(WOOCOMMERCE_VERSION, '2.1', '<') || true === version_compare(GOURL_VERSION, '1.2.6', '<') || 
				(array_key_exists($order->order_currency, $this->coin_names) && !array_key_exists($order->order_currency, $this->payments)))
		{
			echo '<h2>' . __( 'Information', GOURLWC ) . '</h2>' . PHP_EOL;
			echo  "<div class='woocommerce-error'>".sprintf(__( 'Sorry, but there was an error processing your order. Please try a different payment method or contact us if you need assistance. (GoUrl Bitcoin Plugin not configured - %s not activated)', GOURLWC ),(!$this->payments || !$this->defcoin?$this->title:$this->coin_names[$order->order_currency]))."</div>";
		}
		else 
		{ 	
			$plugin			= "gourlwoocommerce";
			$amount 		= $order->order_total; 	
			$currency 		= $order->order_currency; 
			$orderID		= "order" . $order->id;
			$userID			= $order->user_id;
			$period			= "NOEXPIRY";
			$language		= $this->deflang;
			$coin 			= $this->coin_names[$this->defcoin];
			$affiliate_key 	= "gourl";
			$crypto			= array_key_exists($currency, $this->coin_names);
			
			if (!$userID) $userID = "guest"; // allow guests to make checkout (payments)
			

			
			if (!$userID) 
			{
				echo '<h2>' . __( 'Information', GOURLWC ) . '</h2>' . PHP_EOL;
				echo "<div align='center'><a href='".wp_login_url(get_permalink())."'>
						<img style='border:none;box-shadow:none;' title='".__('You need to login or register on website first', GOURLWC )."' vspace='10'
						src='".$gourl->box_image()."' border='0'></a></div>";
			}
			elseif ($amount <= 0)
			{
				echo '<h2>' . __( 'Information', GOURLWC ) . '</h2>' . PHP_EOL;
				echo "<div class='error'>". sprintf(__( 'This order&rsquo;s amount is &ldquo;%s&rdquo; &mdash; it cannot be paid for. Please contact us if you need assistance.', GOURLWC ), $amount ." " . $currency)."</div>";
			}
			else
			{

				// Exchange (optional)
				// --------------------
				if ($currency != "USD" && !$crypto)
				{
					$amount = gourl_convert_currency($currency, "USD", $amount);
						
					if ($amount <= 0)
					{
						echo '<h2>' . __( 'Information', GOURLWC ) . '</h2>' . PHP_EOL;
						echo "<div class='woocommerce-error'>".sprintf(__( 'Sorry, but there was an error processing your order. Please try later or use a different payment method. Cannot receive exchange rates for %s/USD from Google Finance', GOURLWC ), $currency)."</div>";
					}
					else $currency = "USD";
				}
					
				if (!$crypto) $amount = $amount * $this->emultiplier;
					
				
					
				// Payment Box
				// ------------------
				if ($amount > 0)
				{
					// crypto payment gateway
					$result = $gourl->cryptopayments ($plugin, $amount, $currency, $orderID, $period, $language, $coin, $affiliate_key, $userID, $this->iconwidth);
					
					if (!$result["is_paid"]) echo '<h2>' . __( 'Pay Now', GOURLWC ) . '</h2>' . PHP_EOL;
					else echo "<br>";
					
					if ($result["error"]) echo "<div class='woocommerce-error'>".__( "Sorry, but there was an error processing your order. Please try a different payment method.", GOURLWC )."<br/>".$result["error"]."</div>";
					else
					{
						// display payment box or successful payment result
						echo $result["html_payment_box"];
						
						// payment received
						if ($result["is_paid"]) 
						{	
							echo "<div align='center'>" . sprintf( __('%s Payment ID: #%s', GOURLWC), ucfirst($result["coinname"]), $result["paymentID"]) . "</div><br>";
						}
					}
				}	
			}
	    }

	    echo "<br>";
	    	    
	    return true;
	}
	    
	
	
	
	    
	    /*
	     * 7.5 Forward to checkout page
	     */
	    public function process_payment( $order_id ) {
	
			$order = new WC_Order( $order_id );
			
			// Mark as pending (we're awaiting the payment)
			$order->update_status('pending', 'Awaiting payment notification from GoUrl');
				
			
			// Payment Page
			$payment_link = $this->get_return_url($order);
			
			// Reduce stock levels
			$order->reduce_order_stock();
	
			// Remove cart
			WC()->cart->empty_cart();
	
			// Return redirect
			return array(
				'result' 	=> 'success',
				'redirect'	=> $payment_link
			);
	    }
	    
	    
	    
	    
	    /*
	     * 7.6 GoUrl Bitcoin Gateway - Instant Payment Notification
	     */
	    public function gourlcallback( $user_id, $order_id, $payment_details, $box_status) 
	    {
	    	if (!in_array($box_status, array("cryptobox_newrecord", "cryptobox_updated"))) return false;
	    	
	    	if (strpos($order_id, "order") === 0) $order_id = substr($order_id, 5); else return false;
	    	
	    	if (!$user_id || $payment_details["status"] != "payment_received") return false;
	    	
	    	$order = new WC_Order( $order_id );  if ($order === false) return false;
	    	
	    	
	    	$coinName 	= ucfirst($payment_details["coinname"]);
	    	$amount		= $payment_details["amount"] . " " . $payment_details["coinlabel"] . "&#160; ( $" . $payment_details["amountusd"] . " )";
	    	$payID		= $payment_details["paymentID"];
	    	$status		= ($payment_details["is_confirmed"]) ? $this->ostatus2 : $this->ostatus;
	    	$confirmed	= ($payment_details["is_confirmed"]) ? __('Yes', GOURLWC) : __('No', GOURLWC);
	    	
	    	
	    	// New Payment Received
	    	if ($box_status == "cryptobox_newrecord") 
	    	{	
	    		$order->add_order_note(sprintf(__('%s Payment Received<br>%s<br>Payment id <a href="%s">%s</a> &#160; (<a href="%s">page</a>)<br>Awaiting network confirmation...<br>', GOURLWC), $coinName, $amount, GOURL_ADMIN.GOURL."payments&s=payment_".$payID, $payID, $order->get_checkout_order_received_url()."&gourlcryptocoin=".$payment_details["coinname"]));
	    		
	    		update_post_meta( $order->id, 'coinname', 	$coinName);
	    		update_post_meta( $order->id, 'amount', 	$payment_details["amount"] . " " . $payment_details["coinlabel"] );
	    		update_post_meta( $order->id, 'userid', 	$payment_details["userID"] );
	    		update_post_meta( $order->id, 'country', 	get_country_name($payment_details["usercountry"]) );
	    		update_post_meta( $order->id, 'tx', 		$payment_details["tx"] );
	    		update_post_meta( $order->id, 'confirmed', 	$confirmed );
	    		update_post_meta( $order->id, 'details', 	$payment_details["paymentLink"] );
	    	}
	    	
	    	
	    	// Update Status
	    	$order->update_status($status);
	    	
	    	
	    	// Existing Payment confirmed (6+ confirmations)
	    	if ($payment_details["is_confirmed"]) 
	    	{	
	    		update_post_meta( $order->id, 'confirmed', $confirmed );
	    		$order->add_order_note(sprintf(__('%s Payment id <a href="%s">%s</a> Confirmed<br>', GOURLWC), $coinName, GOURL_ADMIN.GOURL."payments&s=payment_".$payID, $payID));
	    	}
	    	
	    	// Completed
	    	if ($status == "completed") $order->payment_complete(); 
	    	

	    	return true;
	    }
	}
	// end class WC_Gateway_GoUrl
	
	
	
	
	
	
	/*
	 *  8. Instant Payment Notification Function - pluginname."_gourlcallback"
	 *  
	 *  This function will appear every time by GoUrl Bitcoin Gateway when a new payment from any user is received successfully. 
	 *  Function gets user_ID - user who made payment, current order_ID (the same value as you provided to bitcoin payment gateway), 
	 *  payment details as array and box status.
	 *  
	 *  The function will automatically appear for each new payment usually two times :  
	 *  a) when a new payment is received, with values: $box_status = cryptobox_newrecord, $payment_details[is_confirmed] = 0
	 *  b) and a second time when existing payment is confirmed (6+ confirmations) with values: $box_status = cryptobox_updated, $payment_details[is_confirmed] = 1.
	 *
	 *  But sometimes if the payment notification is delayed for 20-30min, the payment/transaction will already be confirmed and the function will
	 *  appear once with values: $box_status = cryptobox_newrecord, $payment_details[is_confirmed] = 1
	 *  
	 *  Payment_details example - https://gourl.io/images/plugin2.png
	 *  Read more - https://gourl.io/affiliates.html#wordpress
	 */ 
	function gourlwoocommerce_gourlcallback ($user_id, $order_id, $payment_details, $box_status)
	{
		global $woocommerce;
		
		$gateways = $woocommerce->payment_gateways->payment_gateways();
		
		if (!isset($gateways['gourlpayments'])) return;
		
		if (!in_array($box_status, array("cryptobox_newrecord", "cryptobox_updated"))) return false;
		
		// forward data to WC_Gateway_GoUrl
		$gateways['gourlpayments']->gourlcallback( $user_id, $order_id, $payment_details, $box_status);
		
		return true;
	}





}
// end gourl_wc_gateway_load()           
