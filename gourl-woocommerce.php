<?php
/*
Plugin Name: 		GoUrl WooCommerce - Bitcoin Altcoin Payment Gateway Addon
Plugin URI: 		https://gourl.io/bitcoin-payments-woocommerce.html
Description: 		Provides a <a href="https://gourl.io">GoUrl.io</a> Bitcoin/Altcoin Payment Gateway for <a href="https://wordpress.org/plugins/woocommerce/">WooCommerce 2.1+</a>. Support product prices in USD/EUR/etc and in Bitcoin/Altcoins directly; sends the amount straight to your business Bitcoin/Altcoin wallet. Convert your USD/EUR/etc prices to cryptocoins using Google/Cryptsy Exchange Rates. Direct Integration on your website, no external payment pages opens (as other payment gateways offer). Accept Bitcoin, Litecoin, Paycoin, Dogecoin, Dash, Speedcoin, Reddcoin, Potcoin, Feathercoin, Vertcoin, Vericoin, Peercoin, MonetaryUnit payments online. You will see the bitcoin/altcoin payment statistics in one common table on your website. No Chargebacks, Global, Secure. All in automatic mode.
Version: 			1.1.5
Author: 			GoUrl.io
Author URI: 		https://gourl.io
License: 			GPLv2
License URI: 		http://www.gnu.org/licenses/gpl-2.0.html
GitHub Plugin URI: 	https://github.com/cryptoapi/Bitcoin-Payments-Woocommerce
*/


if (!defined( 'ABSPATH' )) exit; // Exit if accessed directly

if (!function_exists('gourl_wc_gateway_load') && !function_exists('gourl_wc_action_links')) // Exit if duplicate
{
	

	DEFINE('GOURLWC', 'gourl-woocommerce');
	DEFINE('GOURLWC_RATES', json_encode(array("USD" => "US Dollar", "EUR" => "Euro", "GBP" => "British Pound", "AUD" => "Australian Dollar", "BRL" => "Brazilian Real", "CAD" => "Canadian Dollar", "CHF" => "Swiss Franc", "CLP" => "Chilean Peso", "CNY" => "Chinese Yuan Renminbi", "DKK" => "Danish Krone", "HKD"=> "Hong Kong Dollar", "ISK" => "Icelandic Krona", "JPY" => "Japanese Yen", "KRW" => "South Korean Won", "NZD" => "New Zealand Dollar", "PLN" => "Polish Zloty", "RUB" => "Russian Ruble", "SEK" => "Swedish Krona", "SGD" => "Singapore Dollar", "TWD" => "Taiwan New Dollar")));
	
	
	
	if (!defined('GOURLWC_AFFILIATE_KEY'))
	{
		DEFINE('GOURLWC_AFFILIATE_KEY', 	'gourl');
		add_action( 'plugins_loaded', 		'gourl_wc_gateway_load', 20 );
		add_filter( 'plugin_action_links', 	'gourl_wc_action_links', 10, 2 );
		add_action( 'plugins_loaded', 		'gourl_wc_load_textdomain' );
	}


	
	/*
	 *	0.
	*/
	function gourl_wc_load_textdomain() 
	{
		load_plugin_textdomain( GOURLWC, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
	
	

	
	/*
	 *	1.
	*/
	function gourl_wc_action_links($links, $file)
	{
		static $this_plugin;
		
		if (!class_exists('WC_Payment_Gateway')) return $links;
	
		if (false === isset($this_plugin) || true === empty($this_plugin)) {
			$this_plugin = plugin_basename(__FILE__);
		}
	
		if ($file == $this_plugin) {
			$settings_link = '<a href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section=wc_gateway_gourl').'">'.__( 'Settings', GOURLWC ).'</a>';
			array_unshift($links, $settings_link);
				
			if (defined('GOURL'))
			{
				$unrecognised_link = '<a href="'.admin_url('admin.php?page='.GOURL.'payments&s=unrecognised').'">'.__( 'Unrecognised', GOURLWC ).'</a>';
				array_unshift($links, $unrecognised_link);
				$payments_link = '<a href="'.admin_url('admin.php?page='.GOURL.'payments&s=gourlwoocommerce').'">'.__( 'Payments', GOURLWC ).'</a>';
				array_unshift($links, $payments_link);
			}
		}
	
		return $links;
	}
	
	
	
	
	
 /*
  *	Plugin Load
  */
 function gourl_wc_gateway_load() 
 {
	
	// WooCommerce required
	if (!class_exists('WC_Payment_Gateway') || class_exists('WC_Gateway_GoUrl')) return;
	
	add_filter( 'woocommerce_payment_gateways', 		'gourl_wc_gateway_add' );
	add_action( 'woocommerce_view_order', 				'gourl_wc_payment_history', 10, 1 );
	add_action( 'woocommerce_email_after_order_table', 	'gourl_wc_payment_link', 15, 2 );
	add_filter( 'woocommerce_currencies', 				'gourl_wc_currencies' );
	add_filter( 'woocommerce_currency_symbol', 			'gourl_wc_currency_symbol', 10, 2);

	
	
	// Set price in USD/EUR/GBR in the admin panel and display that price in Bitcoin for the front-end user
	if (!current_user_can('manage_options'))
	{
		add_filter( 'woocommerce_get_sale_price', 		'gourl_wc_btc_price', 10, 2 );
		add_filter( 'woocommerce_get_regular_price', 	'gourl_wc_btc_price', 10, 2 );
		add_filter( 'woocommerce_get_price', 			'gourl_wc_btc_price', 10, 2 );
	}

	
	
	/*
	 * 2. You set product prices in USD/EUR/etc in the admin panel, and display those prices in Bitcoins (BTC) for front-end users
	*/
	function gourl_wc_fiat_btc( $currency = "" )
	{
		if (!$currency && function_exists('get_woocommerce_currency')) $currency = get_woocommerce_currency(); 
		if (strpos($currency, "BTC") && strlen($currency) == 6 && in_array(substr($currency, 0, 3), array_keys(json_decode(GOURLWC_RATES, true))))
			return true;
		else
			return false;
	}
	
	if (gourl_wc_fiat_btc() && (!function_exists('wc_get_price_decimals') || !in_array(wc_get_price_decimals(), array(3,4)))) update_option( 'woocommerce_price_num_decimals', 4 );

	
	

	/*
	 *	3.
	 */
	function gourl_wc_gateway_add( $methods ) 
	{
		if (!in_array('WC_Gateway_Gourl', $methods)) {
			$methods[] = 'WC_Gateway_GoUrl';
		}
		return $methods;
	}

	
	
	
	/*
	 *	4.
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
	 *	5.
	*/
	function gourl_wc_payment_link( $order, $is_admin_email )
	{
		$coin = strtolower(get_post_meta($order->id, 'coinname', true));
		
		if ($coin) echo "<br><h4><a href='".$order->get_checkout_order_received_url()."&gourlcryptocoin=".$coin."'>".__( 'View Payment Details', GOURLWC )." </a></h4><br>";
		
		return true;
	}
	
	
	
	/*
	 *	6.
	*/
	function gourl_wc_currencies ( $currencies ) 
	{
		global $gourl; 
		
		if (class_exists('gourlclass') && defined('GOURL') && defined('GOURL_ADMIN') && is_object($gourl))
		{
			$arr = $gourl->coin_names(); 
		
			foreach ($arr as $k => $v)
				$currencies[$k] = __( 'Cryptocurrency', GOURLWC ) . " - " . __( ucfirst($v), GOURLWC ); 
			
			__( 'Bitcoin', GOURLWC );  // use in translation

			
			$arr = json_decode(GOURLWC_RATES, true); 
		
			foreach ($arr as $k => $v)
				$currencies[$k."BTC"] = sprintf(__( 'Admin use %s, User see prices in Bitcoins', GOURLWC ), $k);
		}
		
		return $currencies;
	}
	
	
	
	
	/*
	 *	7.	
	*/
	function gourl_wc_currency_symbol ( $currency_symbol, $currency )
	{
		global $gourl, $post;
		
		if (gourl_wc_fiat_btc($currency))
		{
			if (current_user_can('manage_options') && ((isset($post->post_type) && $post->post_type == "product") || (isset($_GET["page"]) && $_GET["page"] == "wc-settings")))
			{
				$currency_symbol = get_woocommerce_currency_symbol(substr($currency, 0, 3));
				if (!$currency_symbol) $currency_symbol = substr($currency, 0, 3);
			}
			else $currency_symbol = substr($currency, 3);			
		}
		elseif (class_exists('gourlclass') && defined('GOURL') && defined('GOURL_ADMIN') && is_object($gourl))
		{
			$arr = $gourl->coin_names();
	
			if (isset($arr[$currency])) $currency_symbol = $currency;
		}
		
		if ($currency_symbol == "BTC") $currency_symbol = "&#3647;";
	
		return $currency_symbol;
	}	

	
	
	
	/*
	 *	8.
	*/
	function gourl_wc_bitcoin_live_price ($currency)
	{
		$price 	= 0;
		$min 	= 50;
		$key 	= GOURL.'_exchange_BTC'.$currency;
		
		if (!in_array($currency, array_keys(json_decode(GOURLWC_RATES, true)))) return 0;
		
		// update one time per 20 min
		$arr2 = get_option($key);
		if ($arr2 && isset($arr2["price"]) && ($arr2["time"] + ($arr2["price"]>0?20:1)*60) > strtotime("now")) return $arr2["price"]; 
		
	
		// a. bitstamp.net
		if ($currency == "USD")
		{
			$data = gourl_wc_get_url("https://www.bitstamp.net/api/ticker/");
			$arr = json_decode($data, true);
			if (isset($arr["last"]) && isset($arr["volume"]) && $arr["last"] > $min) $price = round($arr["last"]);
		}
	
		// b. blockchain.info
		if (!$price)
		{
			$data = gourl_wc_get_url("https://blockchain.info/ticker");
			$arr = json_decode($data, true);
			if (isset($arr[$currency]["15m"]) && $arr[$currency]["15m"] > $min) $price = round($arr[$currency]["15m"]);
			if (!$price && isset($arr[$currency]["last"]) && $arr[$currency]["last"] > $min) $price = round($arr[$currency]["last"]);
		}
	
		// c. btc-e
		if (!$price && $currency == "USD")
		{
			$data = gourl_wc_get_url("https://btc-e.com/api/2/btc_usd/ticker");
			$arr = json_decode($data, true);
			if (isset($arr["ticker"]["avg"]) && $arr["ticker"]["avg"] > $min) $price = round($arr["ticker"]["avg"]);
		}

		
		// save
		if ($price > 0 || !$arr2 || ($arr2["time"] + 3*60*60) < strtotime("now"))
		{
			$arr = array("price" => $price, "time" => strtotime("now"));
			update_option($key, $arr);
		}
		
		return $price;
	}
			
	
	
	
	/*
	 *	9.
	*/
	function gourl_wc_get_url($url)
	{
		$ch = curl_init();
		curl_setopt ($ch, CURLOPT_URL, $url);
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt ($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Trident/5.0)");
		curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt ($ch, CURLOPT_TIMEOUT, 10);
		$data 		= curl_exec($ch);
		$httpcode 	= curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
	
		return ($httpcode>=200 && $httpcode<300) ? $data : false;
	}
	
	
	
	
	/*
	 *	10.
	*/
	function gourl_wc_btc_price ( $price, $product )
	{
		global $woocommerce;
		static $emultiplier = 0;
		
		if (gourl_wc_fiat_btc()) 
		{
			if (!$emultiplier)
			{
				$gateways = $woocommerce->payment_gateways->payment_gateways();
				if (isset($gateways['gourlpayments'])) $emultiplier = trim(str_replace(array("%", ","), array("", "."), $gateways['gourlpayments']->get_option('emultiplier')));
				if (!$emultiplier || !is_numeric($emultiplier) || $emultiplier < 0.01) $emultiplier = 1;
			}
			
			$live = gourl_wc_bitcoin_live_price(substr(get_woocommerce_currency(), 0, 3));
			
			if ($live > 0) $price = $price / $live * $emultiplier;
			else  $price = 9999;
		}
		
		return $price;
		
	}
	
	
	
	
	/*
	 *	11. Payment Gateway WC Class
	 */
	class WC_Gateway_GoUrl extends WC_Payment_Gateway 
	{
		
		private $payments 			= array();
		private $languages 			= array();
		private $coin_names			= array('BTC' => 'bitcoin', 'LTC' => 'litecoin', 'XPY' => 'paycoin', 'DOGE' => 'dogecoin', 'DASH' => 'dash', 'SPD' => 'speedcoin', 'RDD' => 'reddcoin', 'POT' => 'potcoin', 'FTC' => 'feathercoin', 'VTC' => 'vertcoin', 'VRC' => 'vericoin', 'PPC' => 'peercoin', 'MUE' => 'monetaryunit');
		private $statuses 			= array('processing' => 'Processing Payment', 'on-hold' => 'On Hold', 'completed' => 'Completed');
		private $mainplugin_url		= '';
		private $url				= '';
		private $url2				= '';
		private $url3				= '';
		private $cointxt			= '';
		
		private $logo				= '';
		private $emultiplier		= '';
		private $ostatus			= '';
		private $ostatus2			= '';
		private $deflang			= '';
		private $defcoin			= '';
		private $iconwidth			= '';
		
		
		
		/*
		 * 11.1
		*/
	    public function __construct() 
	    {
	    	global $gourl;
	    	
			$this->id                 	= 'gourlpayments';
			$this->mainplugin_url 		= admin_url("plugin-install.php?tab=search&type=term&s=GoUrl+Bitcoin+Payment+Gateway+Downloads");
			$this->method_title       	= __( 'GoUrl Bitcoin/Altcoins', GOURLWC );
			$this->method_description  	= "<a target='_blank' href='https://gourl.io/'><img border='0' style='float:left; margin-right:15px' src='https://gourl.io/images/gourlpayments.png'></a>";
			$this->method_description  .= "<a target='_blank' href='https://gourl.io/bitcoin-payments-woocommerce.html'>".__( 'Plugin Homepage', GOURLWC )."</a> &#160;&amp;&#160; <a target='_blank' href='https://gourl.io/bitcoin-payments-woocommerce.html#screenshot'>".__( 'screenshots', GOURLWC )." &#187;</a><br>";
			$this->method_description  .= "<a target='_blank' href='https://github.com/cryptoapi/Bitcoin-Payments-Woocommerce'>".__( 'Plugin on Github - 100% Free Open Source', GOURLWC )." &#187;</a><br><br>";
			$this->has_fields         	= false;

			$enabled = ((GOURLWC_AFFILIATE_KEY=='gourl' && $this->get_option('enabled')==='') || $this->get_option('enabled') == 'yes' || $this->get_option('enabled') == '1' || $this->get_option('enabled') === true) ? true : false;

			if (class_exists('gourlclass') && defined('GOURL') && defined('GOURL_ADMIN') && is_object($gourl))
			{ 
				if (true === version_compare(GOURL_VERSION, '1.3.3', '<'))
				{
					if ($enabled) $this->method_description .= '<div class="error"><p><b>' .sprintf(__( "Your GoUrl Bitcoin Gateway <a href='%s'>Main Plugin</a> version is too old. Requires 1.3.3 or higher version. Please <a href='%s'>update</a> to latest version.", GOURLWC ), GOURL_ADMIN.GOURL, $this->mainplugin_url)."</b> &#160; &#160; &#160; &#160; " . 
							  __( 'Information', GOURLWC ) . ": &#160; <a href='https://gourl.io/bitcoin-wordpress-plugin.html'>".__( 'Main Plugin Homepage', GOURLWC )."</a> &#160; &#160; &#160; " . 
							  "<a href='https://wordpress.org/plugins/gourl-bitcoin-payment-gateway-paid-downloads-membership/'>".__( 'WordPress.org Plugin Page', GOURLWC )."</a></p></div>";
				}
				elseif (true === version_compare(WOOCOMMERCE_VERSION, '2.1', '<'))
				{
					if ($enabled) $this->method_description .= '<div class="error"><p><b>' .sprintf(__( "Your WooCommerce version is too old. The GoUrl payment plugin requires WooCommerce 2.1 or higher to function. Please update to <a href='%s'>latest version</a>.", GOURLWC ), admin_url('plugin-install.php?tab=search&type=term&s=WooCommerce+excelling+eCommerce+WooThemes+Beautifully')).'</b></p></div>';
				}
				else 
				{
					$this->payments 			= $gourl->payments(); 		// Activated Payments
					$this->coin_names			= $gourl->coin_names(); 	// All Coins
					$this->languages			= $gourl->languages(); 		// All Languages
				}
				
				$this->url		= GOURL_ADMIN.GOURL."settings";
				$this->url2		= GOURL_ADMIN.GOURL."payments&s=gourlwoocommerce";
				$this->url3		= GOURL_ADMIN.GOURL;
				$this->cointxt 	= (implode(", ", $this->payments)) ? implode(", ", $this->payments) : __( '- Please setup -', GOURLWC );
			}
			else
			{
				if ($enabled) $this->method_description .= '<div class="error"><p><b>' . 
								sprintf(__( "You need to install GoUrl Bitcoin Gateway Main Plugin also. Go to - <a href='%s'>Automatic installation</a> or <a href='%s'>Manual</a>.", GOURLWC ), $this->mainplugin_url, "https://gourl.io/bitcoin-wordpress-plugin.html") . "</b> &#160; &#160; &#160; &#160; " .
								__( 'Information', GOURLWC ) . ": &#160; &#160;<a href='https://gourl.io/bitcoin-wordpress-plugin.html'>".__( 'Main Plugin Homepage', GOURLWC )."</a> &#160; &#160; &#160; <a href='https://wordpress.org/plugins/gourl-bitcoin-payment-gateway-paid-downloads-membership/'>" .
								__( 'WordPress.org Plugin Page', GOURLWC ) . "</a></p></div>";
				
				$this->url		= $this->mainplugin_url;
				$this->url2		= $this->url;
				$this->url3		= $this->url;
				$this->cointxt 	= '<b>'.__( 'Please install GoUrl Bitcoin Gateway WP Plugin', GOURLWC ).' &#187;</b>';
				
			}

			$this->method_description  .= "<b>" . __( "Secure payments with virtual currency. <a target='_blank' href='https://bitcoin.org/'>What is Bitcoin?</a>", GOURLWC ) . '</b><br>';
			$this->method_description  .= sprintf(__( 'Accept %s payments online in WooCommerce.', GOURLWC ), __( ucwords(implode(", ", $this->coin_names)), GOURLWC )).'<br>';
			if ($enabled) $this->method_description .= sprintf(__( "If you use multiple stores/sites online, please create separate <a target='_blank' href='%s'>GoUrl Payment Box</a> (with unique payment box public/private keys) for each of your stores/websites. Do not use the same GoUrl Payment Box with the same public/private keys on your different websites/stores.", GOURLWC ), "https://gourl.io/editrecord/coin_boxes/0") . '<br><br>';
			else $this->method_description .= '<br>';
				
			
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
			$this->gourl_settings();
			
			// Logo
			$this->icon = apply_filters('woocommerce_gourlpayments_icon', 'https://gourl.io/images/'.$this->logo."/payments.png");
				
			
			// Hooks
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_gourlpayments', array( $this, 'cryptocoin_payment' ) );
			
			if (isset($_GET["page"]) && isset($_GET["section"]) && $_GET["page"] == "wc-settings" && $_GET["section"] == "wc_gateway_gourl") add_action( 'admin_footer_text', array(&$this, 'admin_footer_text'), 25);


			return true;
	    }

	    
	    /*
	     * 11.2
	    */
	    private function gourl_settings()
	    {
	    	// Define user set variables
	    	$this->enabled      = $this->get_option( 'enabled' );
	    	$this->title        = $this->get_option( 'title' );
	    	$this->description  = $this->get_option( 'description' );
	    	$this->logo      	= $this->get_option( 'logo' );
	    	$this->emultiplier  = trim(str_replace(array("%", ","), array("", "."), $this->get_option( 'emultiplier' )));
	    	$this->ostatus  	= $this->get_option( 'ostatus' );
	    	$this->ostatus2  	= $this->get_option( 'ostatus2' );
	    	$this->deflang  	= $this->get_option( 'deflang' );
	    	$this->defcoin  	= $this->get_option( 'defcoin' );
	    	$this->iconwidth  	= trim(str_replace("px", "", $this->get_option( 'iconwidth' )));
	    		
	    	// Re-check
	    	if (!$this->title)								$this->title 		= __('GoUrl Bitcoin/Altcoins', GOURLWC);
	    	if (!$this->description)						$this->description 	= __('Secure, anonymous payment with virtual currency', GOURLWC);
	    	if (!isset($this->statuses[$this->ostatus])) 	$this->ostatus  	= 'processing';
	    	if (!isset($this->statuses[$this->ostatus2])) 	$this->ostatus2 	= 'processing';
	    	if (!isset($this->languages[$this->deflang])) 	$this->deflang 		= 'en';
	    		
	    	if (!in_array($this->logo, $this->coin_names) && $this->logo != 'global') 					$this->logo = 'bitcoin';
	    	if (!$this->emultiplier || !is_numeric($this->emultiplier) || $this->emultiplier < 0.01) 	$this->emultiplier = 1;
	    	if (!is_numeric($this->iconwidth) || $this->iconwidth < 30 || $this->iconwidth > 250) 		$this->iconwidth = 60;
	    	
	    	if ($this->defcoin && $this->payments && !isset($this->payments[$this->defcoin])) $this->defcoin = key($this->payments);
	    	elseif (!$this->payments)						$this->defcoin		= '';
	    	elseif (!$this->defcoin)						$this->defcoin		= key($this->payments);
	    	
	    	return true;
	    }
	    
	    
	    /*
	     * 11.3
	    */
	   	public function init_form_fields() 
	    {
	    	
	    	$logos = array('global' => __( "GoUrl default logo - 'Global Payments'", GOURLWC )); 
	    	foreach ($this->coin_names as $v) $logos[$v] = sprintf(__( "GoUrl logo with text - '%s Payments'", GOURLWC ), __( ucfirst($v), GOURLWC ));
	    	
	    	$this->form_fields = array(
				'enabled'		=> array(
					'title'   	  	=> __( 'Enable/Disable', GOURLWC ),
					'type'    	  	=> 'checkbox',
					'default'	  	=> (GOURLWC_AFFILIATE_KEY=='gourl'?'yes':'no'),
					'label'   	  	=> sprintf(__( "Enable Bitcoin/Altcoin Payments in WooCommerce with <a href='%s'>GoUrl Bitcoin Gateway</a>", GOURLWC ), $this->url3)
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
					'default'     	=> trim(sprintf(__( 'Secure, anonymous payment with virtual currency - %s', GOURLWC ), implode(", ", $this->payments)), " -") . ". <a target='_blank' href='https://bitcoin.org/'>" . __( 'What is bitcoin?', GOURLWC ) . "</a>",
					'description' 	=> __( 'Payment method description that the customer will see on your checkout', GOURLWC )
				),
				'logo' 	=> array(
					'title'       	=> __( 'Logo', GOURLWC ),
					'type'        	=> 'select',
					'options'  		=> $logos,
					'default'     	=> 'bitcoin',
					'description' 	=> __( 'Payment method logo that the customer will see on your checkout', GOURLWC )
				),
	    		'emultiplier' 	=> array(
   					'title' 		=> __('Exchange Rate Multiplier', GOURLWC ),
   					'type' 			=> 'text',
   					'default' 		=> '1.00',
    				'description' 	=> __('The system uses the multiplier rate with today LIVE cryptocurrency exchange rates (which are updated every 30 minutes) when the transaction is calculating from a fiat currency (e.g. USD, EUR, etc) to cryptocurrency. <br> Example: <b>1.05</b> - will add an extra 5% to the total price in bitcoin/altcoins, <b>0.85</b> - will be a 15% discount for the price in bitcoin/altcoins. Default: 1.00 ', GOURLWC )
    			),
    			'advanced' 		=> array(
   					'title'       	=> "<br>".__( 'Advanced options', GOURLWC ),
   					'type'        	=> 'title',
   					'description' 	=> ''
    			),
	    		'ostatus' 		=> array(
   					'title' 		=> __('Order Status - Cryptocoin Payment Received', GOURLWC ),
   					'type' 			=> 'select',
   					'options' 		=> $this->statuses,
   					'default' 		=> 'processing',
	    			'description' 	=> sprintf(__("Payment is received successfully from the customer. You will see the bitcoin/altcoin payment statistics in one common table <a href='%s'>'All Payments'</a> with details of all received payments.<br>If you sell digital products / software downloads you can use the status 'Completed' showing that particular customer already has instant access to your digital products", GOURLWC), $this->url2)
    			),
	    		'ostatus2' 		=> array(
	    			'title' 		=> __('Order Status - Previously Received Payment Confirmed', GOURLWC ),
	    			'type' 			=> 'select',
	    			'options' 		=> $this->statuses,
	    			'default' 		=> 'processing',
	    			'description' 	=> __("About one hour after the payment is received, the bitcoin transaction should get 6 confirmations (for transactions using other cryptocoins ~ 20-30min).<br>A transaction confirmation is needed to prevent double spending of the same money", GOURLWC)
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
   					'description' 	=> sprintf(__( "Default Coin in Crypto Payment Box. Activated Payments : <a href='%s'>%s</a>", GOURLWC ), $this->url, $this->cointxt)
    			),
	    		'iconwidth'			=> array(
					'title'       	=> __( 'Icons Size', GOURLWC ),
					'type'        	=> 'text',
	    			'label'        	=> 'px',
	    			'default'     	=> "60px",
					'description' 	=> __( "Cryptocoin icons size in 'Select Payment Method' that the customer will see on your checkout. Default 60px. Allowed: 30..250px", GOURLWC ) . "<br><br>"
				),
	    		'boxstyle'			=> array(
					'title'       	=> __( 'Payment Box Style', GOURLWC ),
					'type'        	=> 'title',
					'description' 	=> sprintf(__( "Payment Box <a href='%s'>sizes</a> and border <a href='%s'>shadow</a> you can change <a href='%s'>here &#187;</a>", GOURLWC ), "https://gourl.io/images/global/sizes.png", "https://gourl.io/images/global/styles.png", $this->url."#gourlmonetaryunitprivate_key") . "<br><br>"
				),
	    			'langstyle'		=> array(
 					'title'       	=> __( 'Languages', GOURLWC ),
    				'type'        	=> 'title',
    				'description' 	=> sprintf(__( "If you want to use GoUrl WooCommerce Bitcoin Gateway plugin in a language other than English, see the page <a href='%s'>Languages and Translations</a>", GOURLWC ), "https://gourl.io/languages.html") . "<br><br><br>"
				)
	    	);
	    	
	    	return true;
	    }
	
	    
	    
    /*
     * 11.4 Output for the order received page.
     */
	public function admin_footer_text()
    {
	    	return sprintf( __( "If you like <b>Bitcoin Gateway for WooCommerce</b> please leave us a %s rating on %s. A huge thank you from GoUrl in advance!", GOURLWC ), "<a href='https://wordpress.org/support/view/plugin-reviews/gourl-woocommerce-bitcoin-altcoin-payment-gateway-addon?filter=5#postform' target='_blank'>&#9733;&#9733;&#9733;&#9733;&#9733;</a>", "<a href='https://wordpress.org/support/view/plugin-reviews/gourl-woocommerce-bitcoin-altcoin-payment-gateway-addon?filter=5#postform' target='_blank'>WordPress.org</a>");
    }
     
	    
	    
	    
    /*
     * 11.5 Output for the order received page.
     */
    public function cryptocoin_payment( $order_id )
	{
		global $gourl;
		
		$order = new WC_Order( $order_id );

		
		if ($order === false)
		{
			echo '<br><h2>' . __( 'Information', GOURLWC ) . '</h2>' . PHP_EOL;
			echo "<div class='woocommerce-error'>". sprintf(__( 'The GoUrl payment plugin was called to process a payment but could not retrieve the order details for orderID %s. Cannot continue!', GOURLWC ), $order_id)."</div>";
		}
		elseif ($order->status == "cancelled" || $order->post_status == "wc-cancelled")
		{
			echo '<br><h2>' . __( 'Information', GOURLWC ) . '</h2>' . PHP_EOL;
			echo "<div class='woocommerce-error'>". __( "This order's status is 'Cancelled' - it cannot be paid for. Please contact us if you need assistance.", GOURLWC )."</div>";
		}
		elseif (!class_exists('gourlclass') || !defined('GOURL') || !is_object($gourl))
		{
			echo '<br><h2>' . __( 'Information', GOURLWC ) . '</h2>' . PHP_EOL;
			echo "<div class='woocommerce-error'>".sprintf(__( "Please try a different payment method. Admin need to install and activate wordpress plugin <a href='%s'>GoUrl Bitcoin Gateway for Wordpress</a> to accept Bitcoin/Altcoin Payments online.", GOURLWC), "https://gourl.io/bitcoin-wordpress-plugin.html")."</div>";
		}
		elseif (!$this->payments || !$this->defcoin || true === version_compare(WOOCOMMERCE_VERSION, '2.1', '<') || true === version_compare(GOURL_VERSION, '1.3.3', '<') || 
				(array_key_exists($order->order_currency, $this->coin_names) && !array_key_exists($order->order_currency, $this->payments)))
		{
			echo '<br><h2>' . __( 'Information', GOURLWC ) . '</h2>' . PHP_EOL;
			echo  "<div class='woocommerce-error'>".sprintf(__( 'Sorry, but there was an error processing your order. Please try a different payment method or contact us if you need assistance (GoUrl Bitcoin Plugin not configured / %s not activated).', GOURLWC ),(!$this->payments || !$this->defcoin || !isset($this->coin_names[$order->order_currency])? $this->title : $this->coin_names[$order->order_currency]))."</div>";
		}
		else 
		{ 	
			$plugin			= "gourlwoocommerce";
			$amount 		= $order->order_total; 	
			$currency 		= $order->order_currency; 
			if (gourl_wc_fiat_btc($currency)) $currency = "BTC";
			$orderID		= "order" . $order->id;
			$userID			= $order->user_id;
			$period			= "NOEXPIRY";
			$language		= $this->deflang;
			$coin 			= $this->coin_names[$this->defcoin];
			$affiliate_key 	= GOURLWC_AFFILIATE_KEY;
			$crypto			= array_key_exists($currency, $this->coin_names);
			
			if (!$userID) $userID = "guest"; // allow guests to make checkout (payments)
			

			
			if (!$userID) 
			{
				echo '<br><h2>' . __( 'Information', GOURLWC ) . '</h2>' . PHP_EOL;
				echo "<div align='center'><a href='".wp_login_url(get_permalink())."'>
						<img style='border:none;box-shadow:none;' title='".__('You need first to login or register on the website to make Bitcoin/Altcoin Payments', GOURLWC )."' vspace='10'
						src='".$gourl->box_image()."' border='0'></a></div>";
			}
			elseif ($amount <= 0)
			{
				echo '<br><h2>' . __( 'Information', GOURLWC ) . '</h2>' . PHP_EOL;
				echo "<div class='woocommerce-error'>". sprintf(__( "This order's amount is %s - it cannot be paid for. Please contact us if you need assistance.", GOURLWC ), $amount ." " . $currency)."</div>";
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
						echo '<br><h2>' . __( 'Information', GOURLWC ) . '</h2>' . PHP_EOL;
						echo "<div class='woocommerce-error'>".sprintf(__( 'Sorry, but there was an error processing your order. Please try later or use a different payment method. System cannot receive exchange rates for %s/USD from Google Finance', GOURLWC ), $currency)."</div>";
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
					
					if (!isset($result["is_paid"]) || !$result["is_paid"]) echo '<br><h2>' . __( 'Pay Now -', GOURLWC ) . '</h2>' . PHP_EOL;
					
					if ($result["error"]) echo "<div class='woocommerce-error'>".__( "Sorry, but there was an error processing your order. Please try a different payment method.", GOURLWC )."<br>".$result["error"]."</div>";
					else
					{
						// display payment box or successful payment result
						echo $result["html_payment_box"];
						
						// payment received
						if ($result["is_paid"]) 
						{	
							if (false) echo "<div align='center'>" . sprintf( __('%s Payment ID: #%s', GOURLWC), ucfirst($result["coinname"]), $result["paymentID"]) . "</div>";
							echo "<br>";
						}
					}
				}	
			}
	    }

	    echo "<br>";
	    	    
	    return true;
	}
	    
	
	
	
	    
	    /*
	     * 11.6 Forward to checkout page
	     */
	    public function process_payment( $order_id ) {
	
			$order = new WC_Order( $order_id );
			
			// Mark as pending (we're awaiting the payment)
			$order->update_status('pending', __('Awaiting payment notification from GoUrl', GOURLWC));
				
			
			// Payment Page
			$payment_link = $this->get_return_url($order);

			// New Order
			$user = (!$order->user_id) ? __('Guest', GOURLWC) : "<a href='".admin_url("user-edit.php?user_id=".$order->user_id)."'>user".$order->user_id."</a>";
			$order->add_order_note(sprintf(__('Order Created by %s<br>Awaiting Cryptocurrency Payment ...', GOURLWC), $user) . '<br>');
			
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
	     * 11.7 GoUrl Bitcoin Gateway - Instant Payment Notification
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
	    		$order->add_order_note(sprintf(__("%s Payment Received<br>%s<br>Payment id <a href='%s'>%s</a> / <a href='%s'>order page</a> <br>Awaiting network confirmation...", GOURLWC), __($coinName, GOURLWC), $amount, GOURL_ADMIN.GOURL."payments&s=payment_".$payID, $payID, $order->get_checkout_order_received_url()."&gourlcryptocoin=".$payment_details["coinname"]) . '<br>');
	    		
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
	    		$order->add_order_note(sprintf(__("%s Payment id <a href='%s'>%s</a> Confirmed", GOURLWC), __($coinName, GOURLWC), GOURL_ADMIN.GOURL."payments&s=payment_".$payID, $payID) . '<br>');
	    	}
	    	
	    	// Completed
	    	if ($status == "completed") $order->payment_complete(); 
	    	

	    	return true;
	    }
	}
	// end class WC_Gateway_GoUrl




	
	
	/*
	 *  12. Instant Payment Notification Function - pluginname."_gourlcallback"
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
 
}