<?php
/*
Plugin Name: 		GoUrl WooCommerce - Bitcoin Altcoin Payment Gateway Addon. White Label Solution  
Plugin URI: 		https://gourl.io/bitcoin-payments-woocommerce.html
Description: 		Provides a <a href="https://gourl.io">GoUrl.io</a> Bitcoin/Altcoin Payment Gateway for <a href="https://wordpress.org/plugins/woocommerce/">WooCommerce 2.1+</a>. Support product prices in USD/EUR/etc and in Bitcoin/Altcoins directly; sends the amount straight to your business Bitcoin/Altcoin wallet. Convert your USD/EUR/etc prices to cryptocoins using Google/Poloniex Exchange Rates. Direct Integration on your website, no external payment pages opens (as other payment gateways offer). Accept Bitcoin, BitcoinCash, Litecoin, Dash, Dogecoin, Speedcoin, Reddcoin, Potcoin, Feathercoin, Vertcoin, Peercoin, MonetaryUnit payments online. You will see the bitcoin/altcoin payment statistics in one common table on your website. No Chargebacks, Global, Secure. All in automatic mode.
Version: 		1.2.2
Author: 		GoUrl.io
Author URI: 		https://gourl.io
WC requires at least: 	2.1.0
WC tested up to: 	3.9.0
License: 		GPLv2
License URI: 		http://www.gnu.org/licenses/gpl-2.0.html
GitHub Plugin URI: 	https://github.com/cryptoapi/Bitcoin-Payments-Woocommerce 
*/


if (!defined( 'ABSPATH' )) exit; // Exit if accessed directly

if (!function_exists('gourl_wc_gateway_load') && !function_exists('gourl_wc_action_links')) // Exit if duplicate
{
	

	DEFINE('GOURLWC', 'gourl-woocommerce');
	DEFINE('GOURLWC_2WAY', json_encode(array("BTC", "BCH", "LTC", "DASH", "DOGE")));
	
	
	if (!defined('GOURLWC_AFFILIATE_KEY'))
	{
		DEFINE('GOURLWC_AFFILIATE_KEY', 	'gourl');
		add_action( 'plugins_loaded', 		'gourl_wc_gateway_load', 20 );
		add_filter( 'plugin_action_links', 	'gourl_wc_action_links', 10, 2 );
		add_action( 'plugins_loaded', 		'gourl_wc_load_textdomain' );
	}


	
	/*
	 *	1.
	*/
	function gourl_wc_load_textdomain()
	{
		load_plugin_textdomain( GOURLWC, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
	
	

	
	/*
	 *	2.
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
	add_filter( 'wc_get_price_decimals',                'gourl_wc_currency_decimals', 10, 1 );
	
	

	// Set price in USD/EUR/GBR in the admin panel and display that price in Bitcoin for the front-end user
	if (!current_user_can('manage_options'))
	{
	    if (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<'))
	    { // WooCommerce 2.x+
		  add_filter( 'woocommerce_get_sale_price', 	'gourl_wc_crypto_price', 10, 2 );
		  add_filter( 'woocommerce_get_regular_price', 	'gourl_wc_crypto_price', 10, 2 );
		  add_filter( 'woocommerce_get_price', 			'gourl_wc_crypto_price', 10, 2 );
	    }
	    else 
	    {  // WooCommerce 3.x+
	        add_filter( 'woocommerce_product_get_sale_price',              'gourl_wc_crypto_price', 10, 2 );
	        add_filter( 'woocommerce_product_get_regular_price',           'gourl_wc_crypto_price', 10, 2 );
	        add_filter( 'woocommerce_product_get_price', 			       'gourl_wc_crypto_price', 10, 2 );

	        add_filter( 'woocommerce_product_variation_get_sale_price',    'gourl_wc_crypto_price', 10, 2 );
	        add_filter( 'woocommerce_product_variation_get_regular_price', 'gourl_wc_crypto_price', 10, 2 );
	        add_filter( 'woocommerce_product_variation_get_price',         'gourl_wc_crypto_price', 10, 2 );
	    
    		add_filter('woocommerce_variation_prices_sale_price',          'gourl_wc_crypto_price', 10, 2 );
    		add_filter('woocommerce_variation_prices_regular_price',       'gourl_wc_crypto_price', 10, 2 );
    		add_filter('woocommerce_variation_prices_price',               'gourl_wc_crypto_price', 10, 2 );
	    }	
	}

	add_filter('woocommerce_get_variation_prices_hash',              'gourl_wc_variation_prices_hash', 10, 1 );
	add_action('woocommerce_before_calculate_totals',                'gourl_wc_2way_prices' );
	add_action('woocommerce_admin_order_data_after_billing_address', 'gourl_wc_admin_order_stats');
	
	
	
	

	
	

	/*
	 *	3. Add new cryptocurrencies to WooCommerce
	 */
	function gourl_wc_currencies ( $currencies )
	{
	    $currencies['IRR'] = __( 'Iranian Rial', GOURLWC );
	    $currencies['IRT'] = __( 'Iranian Toman', GOURLWC );
	     
	    if (class_exists('gourlclass') && defined('GOURL') && defined('GOURL_ADMIN'))
	    {
	        $arr = gourlclass::coin_names();
	
	        foreach ($arr as $k => $v)
	            $currencies[$k] = __( '&#9658; Cryptocurrency', GOURLWC ) . " - " . __( ucfirst($v), GOURLWC );
	            	
	            __( 'Bitcoin', GOURLWC );
	            __( 'Bitcoincash', GOURLWC );
	            __( 'Litecoin', GOURLWC );
	            __( 'Doge', GOURLWC );
	            __( 'DASH', GOURLWC ); // use in translation
	
	            	
	            $arr2 = json_decode(GOURL_RATES, true);
	
	            	
	            foreach ($arr2 as $k2 => $v2)
	                foreach ($arr as $k => $v)
	                    if (in_array($k, json_decode(GOURLWC_2WAY, true)))
	                        $currencies[$k2.$k] = sprintf(__( '&#9658; Admin use %s, Users see LIVE prices in %s', GOURLWC ), ucwords($v2), str_replace("Bitcoincash", "Bitcoin Cash", ucwords($v)));

	    }
	
	    asort($currencies);
	
	    return $currencies;
	}
	
		
	
	
	/*
	 *  4. You set product prices in USD/EUR/etc in the admin panel, and display those prices in Cryptocurrency (Bitcoin, LTC, BCH, DASH, DOGE)  for front-end users
	 *  Admin user - if current_user_can('manage_options') return true
	*/
	function gourl_wc_currency_type( $currency = "" )
	{
	    static $res = array();
	    
	    if (!$currency && function_exists('get_woocommerce_currency')) $currency = get_woocommerce_currency();
	    
	    if ($currency && isset($res[$currency]["user"]) && $res[$currency]["user"]) return $res[$currency];
	     
	    if (in_array(strlen($currency), array(6, 7)) && in_array(substr($currency, 3), json_decode(GOURLWC_2WAY, true)) && in_array(substr($currency, 0, 3), array_keys(json_decode(GOURL_RATES, true))))
	    {
	        $user_currency  = substr($currency, 3);
	        $admin_currency = substr($currency, 0, 3);
	        $twoway = true;
	    }
	    else
	    {
	        $user_currency  = $admin_currency = $currency;
	        $twoway = false;
	    }
	     
	    $res[$currency] = array(   "2way"  => $twoway,
            	                   "admin" => $admin_currency,
            	                   "user"  => $user_currency
            	                );
	    
	    return $res[$currency];
	}

	
	

	/*
	 *	5. Currency symbol
	 */
	function gourl_wc_currency_symbol ( $currency_symbol, $currency )
	{
	    global $post;
	
	
	    if (!function_exists('gourl_bitcoin_live_price') || !function_exists('gourl_altcoin_btc_price')) return substr($currency, 0, 3);
	
	    if (gourl_wc_currency_type($currency)["2way"])
	    {
	        if (current_user_can('manage_options') && isset($post->post_type) && $post->post_type == "product")
	        {
	            $currency_symbol = get_woocommerce_currency_symbol(substr($currency, 0, 3));
	            if (!$currency_symbol) $currency_symbol = substr($currency, 0, 3);
	        }
	        elseif (current_user_can('manage_options') && isset($_GET["page"]) && $_GET["page"] == "wc-settings" && (!isset($_GET["tab"]) || $_GET["tab"] == "general"))
	        {
	            $currency_symbol = substr($currency, 0, 3) . " &#10143; " . substr($currency, 3);  // Currency options Menu
	        }
	        else $currency_symbol = substr($currency, 3);
	    }
	    elseif (class_exists('gourlclass') && defined('GOURL') && defined('GOURL_ADMIN'))
	    {
	        $arr = gourlclass::coin_names();
	
	        if (isset($arr[$currency])) $currency_symbol = $currency;
	    }
	
	    if ($currency_symbol == "BTC") $currency_symbol = "&#3647;";
	    if ($currency == "IRR") $currency_symbol = "&#65020;";
	    if ($currency == "IRT") $currency_symbol = "&#x62A;&#x648;&#x645;&#x627;&#x646;";
	     
	
	    return $currency_symbol;
	}

	
	

 	/*
	 *	 6. Allowance: For fiat - 0..2 decimals, for cryptocurrency 0..4 decimals
	 */
	function gourl_wc_currency_decimals( $decimals )
	{
	    global $post;
	    static $res;
	    
	    if ($res) return $res;
	    
	    $arr = gourl_wc_currency_type();

	    // Set price in USD/EUR/GBR in the admin panel and display that price in Bitcoin/BitcoinCash/Litcoin/DASH/Dogecoin for the front-end user
        if ($arr["2way"])
        {
            $decimals = absint($decimals);
           

            if (current_user_can('manage_options') && isset($post->post_type) && $post->post_type == "product")
            {
                $decimals = 2;
            }
            elseif (function_exists('get_woocommerce_currency'))
            {
                $currency = $arr["user"]; // user visible currency
                if (in_array($currency, array("BTC", "BCH", "DASH")) && !in_array($decimals, array(3,4))) $decimals = 4;
                if (in_array($currency, array("LTC")) && !in_array($decimals, array(2,3)))                $decimals = 3;
                if (in_array($currency, array("DOGE")) && !in_array($decimals, array(0)))                 $decimals = 0;
            }
        }
	    
        $res = $decimals;
        
        return $decimals;
	}
	


	
	
	/*
	 *   7. You set product prices in USD/EUR/etc in the admin panel, and display those prices in Cryptocurrency (2way mode)
	 *      Fix 'View Cart' preview 2way mode for admin
	 */
	function gourl_wc_2way_prices( $cart_object )
	{
	
	    if (gourl_wc_currency_type()["2way"] && current_user_can('manage_options'))
	        foreach ( $cart_object->cart_contents as $value )
	        {
	            if (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) $value['data']->price = gourl_wc_crypto_price( $value['data']->price );
	            else $value['data']->set_price( gourl_wc_crypto_price( $value['data']->get_price() ) );
	        }
	}
	
	

	
	/*
	 *	 8. Convert Fiat to cryptocurrency for end user
	 */
	function gourl_wc_crypto_price ( $price, $product = '' )
	{
	    global $woocommerce;
	    static $emultiplier = 0;
	
	    $live = 0;
	
	    if (!$price) return $price;
	    if (!function_exists('gourl_bitcoin_live_price') || !function_exists('gourl_altcoin_btc_price')) return $price;
	
	    $arr = gourl_wc_currency_type();
	
	    if ($arr["2way"])
	    {
	        if (!$emultiplier)
	        {
	            $gateways = $woocommerce->payment_gateways->payment_gateways();
	            if (isset($gateways['gourlpayments'])) $emultiplier = trim(str_replace(array("%", ","), array("", "."), $gateways['gourlpayments']->get_option('emultiplier')));
	            if (!$emultiplier || !is_numeric($emultiplier) || $emultiplier < 0.01) $emultiplier = 1;
	        }
	        	
	        $btc = gourl_bitcoin_live_price ($arr["admin"]); // 1BTC bitcoin price  in USD/EUR/AUD/RUB/GBP/etc.
	        	
	        if ($arr["user"] == "BTC") $live = $btc;
	        elseif (in_array($arr["user"], json_decode(GOURLWC_2WAY, true))) $live = $btc * gourl_altcoin_btc_price ($arr["user"]); // altcoins 1LTC/1DASH/1BCH/1DOGE  in USD/EUR/AUD/RUB/GBP/etc.
	
	        if ($live > 0) $price = floatval($price) / floatval($live) * 1.01 * floatval($emultiplier);
	        else  $price = 99999;
	    }
	
	    return $price;
	
	}
	
	
	
	
	/*
	 *   9. Clear cache for live 2way crypto prices (update every hour)
	 */
	function gourl_wc_variation_prices_hash( $hash )
	{
	    $arr = gourl_wc_currency_type();
	    if ($arr["2way"]) $hash[] = (current_user_can('manage_options') ? $arr["admin"] : $arr["user"]."-".date("Ymdh"));
	     
	    return $hash;
	}
	
	
	
	
	
	/*
	 *	10. Add GoUrl gateway
	 */
	function gourl_wc_gateway_add( $methods ) 
	{
		if (!in_array('WC_Gateway_Gourl', $methods)) {
			$methods[] = 'WC_Gateway_GoUrl';
		}
		return $methods;
	}

	
	
	
	
	/*
	 *	11. Transactions history
	 */
	function gourl_wc_payment_history( $order_id ) 
	{
		$order = new WC_Order( $order_id );

		$order_id     = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->id          : $order->get_id();
		$order_status = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->status      : $order->get_status();
		$post_status  = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->post_status : get_post_status( $order_id );
		$userID       = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->user_id     : $order->get_user_id();
		
		$coin = get_post_meta($order_id, '_gourl_worder_coinname', true);
		if (!$coin) $coin = get_post_meta($order_id, 'coinname', true); // compatible with old version gourl wc plugin
		
		if (is_user_logged_in() && ($coin || (stripos($order->payment_method_title, "bitcoin")!==false && ($order_status == "pending" || $post_status=="wc-pending"))) && (is_super_admin() || get_current_user_id() == $userID))
		{
			echo "<br><a href='".$order->get_checkout_order_received_url()."&".CRYPTOBOX_COINS_HTMLID."=".strtolower($coin)."&prvw=1' class='button wc-forward'>".__( 'View Payment Details', GOURLWC )." </a>";

		}
		
		return true;
	}
	

	
	
	
	/*
	 *	12.
	*/
	function gourl_wc_payment_link( $order, $is_admin_email )
	{
		$order_id    = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->id          : $order->get_id();
	    
		$coin = get_post_meta($order_id, '_gourl_worder_coinname', true);
		if (!$coin) $coin = get_post_meta($order_id, 'coinname', true); // compatible with old version gourl wc plugin
		
		if ($coin) echo "<br><h4><a href='".$order->get_checkout_order_received_url()."&".CRYPTOBOX_COINS_HTMLID."=".strtolower($coin)."&prvw=1'>".__( 'View Payment Details', GOURLWC )." </a></h4><br>";
		
		return true;
	}
	
	
	
	

	/*
	 *	13. Payment info on order page
	*/
	function gourl_wc_admin_order_stats( $order )
	{
	    global $gourl;
	    
	    $order_id     = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->id          : $order->get_id();
	    $order_status = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->status      : $order->get_status();
	    $post_status  = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->post_status : get_post_status( $order_id );
	     
	    
	    echo "<script>
                        jQuery(document).ready(function() {
                        	jQuery('.woocommerce-Order-customerIP').replaceWith(function() {
                        		var ip = jQuery.trim(jQuery(this).text());
                        		return '<a href=\"https://myip.ms/info/whois/'+ip+'\" target=\"_blank\">' + ip + '</a>';
                        	});
                        });
        	    </script>";
	        
	    if (!class_exists('gourlclass') || !defined('GOURL') || !is_object($gourl)) return;
	    
	    
	    $original_orderID     = get_post_meta( $order_id, '_gourl_worder_orderid', true );
	    $original_userID      = get_post_meta( $order_id, '_gourl_worder_userid', 	true );
	    $original_createtime  = get_post_meta( $order_id, '_gourl_worder_createtime',  true );
	    
	    if ($original_orderID && $original_orderID == $order_id && strtotime($original_createtime))
	    { 
	       
	        $coinName      = get_post_meta( $order_id, '_gourl_worder_coinname', true );
	        $confirmed     = get_post_meta( $order_id, '_gourl_worder_confirmed', true );
	        $orderpage     = get_post_meta( $order_id, '_gourl_worder_orderpage', true );
	        $created       = get_post_meta( $order_id, '_gourl_worder_created', true );
	        $preceived     = get_post_meta( $order_id, '_gourl_worder_preceived', true );
	        $paymentID     = get_post_meta( $order_id, '_gourl_worder_paymentid', true ); 
	        $pdetails      = get_post_meta( $order_id, '_gourl_worder_pdetails', true );
	        $pcountry      = get_post_meta( $order_id, '_gourl_worder_pcountry', true );
	        $pcountrycode  = get_post_meta( $order_id, '_gourl_worder_pcountrycode', true );
	        
	        $amountcrypto  = get_post_meta( $order_id, '_gourl_worder_amountcrypto', true );
	        $amountfiat    = get_post_meta( $order_id, '_gourl_worder_amountfiat', true );
	        
	        $pamountcrypto = get_post_meta( $order_id, '_gourl_worder_pamountcrypto', true ); // 1.1 BTC
	        $pamountusd    = get_post_meta( $order_id, '_gourl_worder_pamountusd', true );    // 4350 USD
	        $pamountmain   = get_post_meta( $order_id, '_gourl_worder_pamountmain', true );   // 3300 GBP/EUR/DASH/BTC
	        	    
	        $txID          = get_post_meta( $order_id, '_gourl_worder_txid', true );
	        $txURL         = $gourl->blockexplorer_tr_url($txID, $coinName);
	        $addr          = get_post_meta( $order_id, '_gourl_worder_addrid', true );
	        $addrURL       = $gourl->blockexplorer_addr_url($addr, $coinName);
	        
	        $userprofile   = (!$original_userID) ? __('Guest', GOURLWC) : "<a href='".admin_url("user-edit.php?user_id=".$original_userID)."'>user".$original_userID."</a>";
	        
	        if (!$confirmed) $pdetails .= "&b=".$paymentID;
	        
	        $tmp = "<div class='clear'></div>
        	        <div>";
        	       
	       $h = ""; 
	       if ($coinName)  // payment received
	       {    
	           $h .= sprintf(__( "%s Payment Received", GOURLWC ), strtoupper($coinName)) . " - ";
	           if ($confirmed == "1") $h .= "<span style='color:green'>".__( 'CONFIRMED', GOURLWC )."</span>";
	           else $h .= "<a title='".__( 'Check Live Status', GOURLWC )."' href='".$pdetails."'><span style='color:red'>".__( 'unconfirmed', GOURLWC )."</span></a>";
	       }
	       elseif ($order_status == "pending" || $post_status=="wc-pending" || $order_status == "cancelled" || $post_status=="wc-cancelled") $h .= "<span style='color:red'>".__( 'CRYPTO PAYMENT NOT RECEIVED YET !', GOURLWC )."</span>";
	         
	       if ($h) $tmp .= "<br><h3 class='gourlnowrap'>$h</h3>";
	       
	       if ($coinName) $tmp .= "<p>"; else $tmp .= "<br>";
	       $tmp .= "<table cellspacing='5' class='gourlnowrap'>
	                <tr><td>".__( 'Order created', GOURLWC )."</td><td>&#160; ".$created." ".__( 'GMT', GOURLWC )."</td><td>/ &#160;<a href='".$orderpage."'>".__( 'view', GOURLWC )."</a></td></tr>";

	       if ($coinName)
	       {
	           $tmp .= "<tr><td>".__( 'Payment received', GOURLWC )."</td><td>&#160; ".$preceived." ".__( 'GMT', GOURLWC )."</td><td>/ &#160;<a href='".$pdetails."'>#".$paymentID."</a></td></tr>";
	           $tmp .= "<tr><td colspan='2'>".sprintf(__( "Paid by %s located in %s", GOURLWC ), " &#160;".$userprofile." &#160; &#160; &#160; ", "<img width='16' border='0' style='margin:0 3px 0 6px' src='".GOURL_IMG."flags/".$pcountrycode.".png'> ".$pcountry)."</td></tr>";
	       }
	        
	       $tmp .= "</table>";
	       if ($coinName) $tmp .= "</p>";
	       $tmp .= "<table cellspacing='5' class='gourlnowrap'> 
                    <tr><td>".__( 'Original order', GOURLWC ).":</td><td>&#160; ".$amountcrypto."</td><td>".($amountfiat!=$amountcrypto?"/ ".$amountfiat:"")."</td></tr>";
            
	       if ($coinName)
	       {
	           $v = "/ ";
	           if ($pamountmain) $v .= "<b>~".$pamountmain."</b>";
	           if ($pamountmain != $pamountusd)
	           {
	               if ($pamountmain) $v .= "  &#160;(" . $pamountusd . ")";
	               else $v .= $pamountusd;
	           }
	           $tmp .= "<tr><td>".__( 'Actual Received', GOURLWC ).":</td><td><b>&#160; ".$pamountcrypto."</b></td><td>".$v."</td></tr>";
	       }
	        
	       $refunded = $order->get_total_refunded();
	       if ($refunded > 0) 
	       {
	           $currencies = get_post_meta( $order_id, '_gourl_worder_currencies', false )[0];
	           $tmp .= "<tr><td>".__( 'Refunded', GOURLWC ).":</td><td colspan='2' style='color:red'><b>&#160; -".$refunded." ".$currencies["user"]."</b></td></tr>";
	       }
           
	       $tmp .= "</table>";
	        
	       if ($coinName)
	       {
	           $tmp .= "<p>".sprintf(__( "%s Transaction", GOURLWC ), $coinName)." <a target='_blank' href='".$txURL."'>".$txID."</a> ".__( "on address", GOURLWC )." <a target='_blank' href='".$addrURL."'>".$addr."</a></p>";
	       }
	       
	       $tmp .= "</div>";
	       
	       echo $tmp;
	   }	
	
	   return;
	}

	
	




	
	
	
	/*
	 *	14. Payment Gateway WC Class
	 */
	class WC_Gateway_GoUrl extends WC_Payment_Gateway 
	{
		
		private $payments           = array();
		private $languages          = array();
		private $coin_names         = array('BTC' => 'bitcoin', 'BCH' => 'bitcoincash', 'LTC' => 'litecoin', 'DASH' => 'dash', 'DOGE' => 'dogecoin', 'SPD' => 'speedcoin', 'RDD' => 'reddcoin', 'POT' => 'potcoin', 'FTC' => 'feathercoin', 'VTC' => 'vertcoin', 'PPC' => 'peercoin', 'MUE' => 'monetaryunit');
		private $statuses           = array('processing' => 'Processing Payment', 'on-hold' => 'On Hold', 'completed' => 'Completed');
		private $showhidemenu       = array('show' => 'Show Menu', 'hide' => 'Hide Menu');
		private $mainplugin_url     = '';
		private $url                = '';
		private $url2               = '';
		private $url3               = '';
		private $cointxt            = '';

		private $logo               = '';
		private $emultiplier        = '';
		private $ostatus            = '';
		private $ostatus2           = '';
		private $deflang            = '';
		private $defcoin            = '';
		private $iconwidth          = '';

		private $customtext         = '';
		private $qrcodesize         = '';
		private $langmenu           = '';
		private $redirect           = '';


		
		/*
		 * 14.1
		*/
	    public function __construct()
	    {
	    	global $gourl;
	    	
			$this->id                 	= 'gourlpayments';
			$this->mainplugin_url 		= admin_url("plugin-install.php?tab=search&type=term&s=GoUrl+Official+Bitcoin+Payment+Gateway");
			$this->method_title       	= __( 'GoUrl Bitcoin/Altcoins', GOURLWC );
			$this->method_description  	= "<a target='_blank' href='https://gourl.io/'><img border='0' style='float:left; margin-right:15px' src='https://gourl.io/images/gourlpayments.png'></a>";
			$this->method_description  .= "<a target='_blank' href='https://gourl.io/bitcoin-payments-woocommerce.html'>".__( 'Plugin Homepage', GOURLWC )."</a> &#160;&amp;&#160; <a target='_blank' href='https://gourl.io/bitcoin-payments-woocommerce.html#screenshot'>".__( 'screenshots', GOURLWC )." &#187;</a><br>";
			$this->method_description  .= "<a target='_blank' href='https://github.com/cryptoapi/Bitcoin-Payments-Woocommerce'>".__( 'Plugin on Github - 100% Free Open Source', GOURLWC )." &#187;</a><br><br>";
			$this->has_fields         	= false;

			$enabled = ((GOURLWC_AFFILIATE_KEY=='gourl' && $this->get_option('enabled')==='') || $this->get_option('enabled') == 'yes' || $this->get_option('enabled') == '1' || $this->get_option('enabled') === true) ? true : false;

			if (class_exists('gourlclass') && defined('GOURL') && defined('GOURL_ADMIN') && is_object($gourl))
			{ 
				if (true === version_compare(GOURL_VERSION, '1.4.5', '<'))
				{
					if ($enabled) $this->method_description .= '<div class="error"><p><b>' .sprintf(__( "Your GoUrl Bitcoin Gateway <a href='%s'>Main Plugin</a> version is too old. Requires 1.4.5 or higher version. Please <a href='%s'>update</a> to latest version.", GOURLWC ), GOURL_ADMIN.GOURL, $this->mainplugin_url)."</b> &#160; &#160; &#160; &#160; " . 
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
				if ($enabled) $this->method_description .= '<div class="error" style="color:red"><p><b>' . 
								sprintf(__( "You need to install GoUrl Bitcoin Gateway Main Plugin also. Go to - <a href='%s'>Automatic installation</a> or <a href='%s'>Manual</a>.", GOURLWC ), $this->mainplugin_url, "https://gourl.io/bitcoin-wordpress-plugin.html") . "</b> &#160; &#160; &#160; &#160; " .
								__( 'Information', GOURLWC ) . ": &#160; &#160;<a href='https://gourl.io/bitcoin-wordpress-plugin.html'>".__( 'Main Plugin Homepage', GOURLWC )."</a> &#160; &#160; &#160; <a href='https://wordpress.org/plugins/gourl-bitcoin-payment-gateway-paid-downloads-membership/'>" .
								__( 'WordPress.org Plugin Page', GOURLWC ) . "</a></p></div>";
				
				$this->url		= $this->mainplugin_url;
				$this->url2		= $this->url;
				$this->url3		= $this->url;
				$this->cointxt 	= '<b>'.__( 'Please install GoUrl Bitcoin Gateway WP Plugin', GOURLWC ).' &#187;</b>';
				
			}

			$this->method_description  .= "<b>" . __( "White Label Product. Secure payments with virtual currency. <a target='_blank' href='https://bitcoin.org/'>What is Bitcoin?</a>", GOURLWC ) . '</b><br>';
			$this->method_description  .= sprintf(__( 'Accept %s payments online in WooCommerce.', GOURLWC ), __( ucwords(implode(", ", $this->coin_names)), GOURLWC )).'<br>';
			if ($enabled) $this->method_description .= sprintf(__( "If you use multiple stores/sites online, please create separate <a target='_blank' href='%s'>GoUrl Payment Box</a> (with unique payment box public/private keys) for each of your stores/websites. Do not use the same GoUrl Payment Box with the same public/private keys on your different websites/stores.", GOURLWC ), "https://gourl.io/editrecord/coin_boxes/0") . '<br>'.sprintf(__( "Add additional altcoins (Litecoin/DASH/Bitcoin Cash/etc) to payment box <a href='%s'>here &#187;</a>", GOURLWC ), $this->url).'<br><br>';
			else $this->method_description .= '<br>';
				
			
			// Update some WooCommerce settings
			// --------------------------------
			// for WooCommerce 2.1x
			if ($enabled && gourl_wc_currency_type()["2way"] && !function_exists('wc_get_price_decimals')) update_option( 'woocommerce_price_num_decimals', 4 );
			
			// increase Hold stock to 200 minutes
			if ($enabled && get_option( 'woocommerce_hold_stock_minutes' ) > 0 && get_option( 'woocommerce_hold_stock_minutes' ) < 80) update_option( 'woocommerce_hold_stock_minutes', 200 );
				
			
			
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
			$this->gourl_settings();
			
			// Logo
			//$this->icon = apply_filters('woocommerce_gourlpayments_icon', 'https://gourl.io/images/'.$this->logo."/payments.png");
				
			
			// Hooks
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_gourlpayments', array( $this, 'cryptocoin_payment' ) );
			
			if (isset($_GET["page"]) && isset($_GET["section"]) && $_GET["page"] == "wc-settings" && $_GET["section"] == "wc_gateway_gourl") add_action( 'admin_footer_text', array(&$this, 'admin_footer_text'), 25);


			return true;
	    }

	    
	    /*
	     * 14.2
	    */
	    private function gourl_settings()
	    {
	    	// Define user set variables
	    	$this->enabled      = $this->get_option( 'enabled' );
	    	$this->title        = $this->get_option( 'title' );
	    	$this->description  = $this->get_option( 'description' );
	    	$this->logo         = $this->get_option( 'logo' );
	    	$this->emultiplier  = trim(str_replace(array("%", ","), array("", "."), $this->get_option( 'emultiplier' )));
	    	$this->ostatus      = $this->get_option( 'ostatus' );
	    	$this->ostatus2     = $this->get_option( 'ostatus2' );
	    	$this->deflang      = $this->get_option( 'deflang' );
	    	$this->defcoin      = $this->get_option( 'defcoin' );
	    	$this->iconwidth    = trim(str_replace("px", "", $this->get_option( 'iconwidth' )));

	    	$this->customtext   = $this->get_option( 'customtext' );
	    	$this->qrcodesize   = trim(str_replace("px", "", $this->get_option( 'qrcodesize' )));
	    	$this->langmenu     = $this->get_option( 'langmenu' );
	    	$this->redirect     = $this->get_option( 'redirect' );


	    	// Re-check
	    	if (!$this->title)                                  $this->title 		= __('GoUrl Bitcoin/Altcoins', GOURLWC);
	    	if (!$this->description)                            $this->description 	= __('Secure, anonymous payment with virtual currency', GOURLWC);
	    	if (!isset($this->statuses[$this->ostatus]))        $this->ostatus  	= 'processing';
	    	if (!isset($this->statuses[$this->ostatus2]))       $this->ostatus2 	= 'processing';
	    	if (!isset($this->languages[$this->deflang]))       $this->deflang 		= 'en';

	    	if (!in_array($this->logo, $this->coin_names) && $this->logo != 'global')                   $this->logo = 'bitcoin';
	    	if (!$this->emultiplier || !is_numeric($this->emultiplier) || $this->emultiplier < 0.01)    $this->emultiplier = 1;
	    	if (!is_numeric($this->iconwidth) || $this->iconwidth < 30 || $this->iconwidth > 250)       $this->iconwidth = 60;
	    	if (!is_numeric($this->qrcodesize) || $this->qrcodesize < 0 || $this->qrcodesize > 500)     $this->qrcodesize = 200;

	    	if ($this->defcoin && $this->payments && !isset($this->payments[$this->defcoin]))           $this->defcoin = key($this->payments);
	    	elseif (!$this->payments)                                                                   $this->defcoin = '';
	    	elseif (!$this->defcoin)                                                                    $this->defcoin = key($this->payments);

	    	if (!isset($this->showhidemenu[$this->langmenu])) 	$this->langmenu     = 'show';
	    	if ($this->langmenu == 'hide') define("CRYPTOBOX_LANGUAGE_HTMLID_IGNORE", TRUE);

	    	if (stripos($this->redirect, "http") !== 0)         $this->redirect     = '';

	    	return true;
	    }


	    /*
	     * 14.3
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
                /*
                'logo' 	=> array(
                    'title'       	=> __( 'Logo', GOURLWC ),
                    'type'        	=> 'select',
                    'options'  		=> $logos,
                    'default'     	=> 'bitcoin',
                    'description' 	=> __( 'Payment method logo that the customer will see on your checkout', GOURLWC )
                ),
                */
                    'emultiplier' 	=> array(
                    'title' 		=> __('Exchange Rate Multiplier', GOURLWC ),
                    'type' 			=> 'text',
                    'default' 		=> '1.00',
                    'description' 	=> __('The system uses the multiplier rate with today LIVE cryptocurrency exchange rates (which are updated every 30 minutes) when the transaction is calculating from a fiat currency (e.g. USD, EUR, etc) to cryptocurrency. <br> Example: <b>1.05</b> - will add an extra 5% to the total price in bitcoin/altcoins, <b>0.85</b> - will be a 15% discount for the price in bitcoin/altcoins. Default: 1.00 ', GOURLWC )
                ),
                    'advanced' 		=> array(
                    'title'       	=> "<br>".__( 'Advanced options', GOURLWC ),
                    'type'        	=> 'title',
                    'description' 	=> '<img width="45" height="35" alt="new" src="https://gourl.io/images/new.png" style="float:left;margin:3px 8px"> '.sprintf(__("Your shop can display product prices in Bitcoin/BCH/DASH/LTC/DOGE also.<br>Simple select 'Currency' - <a href='%s'>'Admin use USD/EUR/etc, users see Live Prices in Bitcoin/Altcoin'</a>", GOURLWC ), admin_url('admin.php?page=wc-settings&tab=general#woocommerce_currency'))."&#160; --> &#160; <a href='https://gourl.io/images/woocommerce/woocommerce-usd-btc.html' target='_blank'>".__("see screenshot", GOURLWC)."</a><br><br>"
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
                    'description' 	=> __( "Cryptocoin icons size in 'Select Payment Method' that the customer will see on your checkout. Default 60px. Allowed: 30..250px", GOURLWC ) . "<br><br><br><br>"
                ),
                    'mobstyle'			=> array(
                    'title'       	=> __( 'Mobile Friendly Payment Box Style', GOURLWC ),
                    'type'        	=> 'title',
                    'description' 	=> sprintf(__( "Additional options for Mobile Friendly Payment Box (not iFrame).<br>Box Color Theme (white/black/blue/etc) you can change <a href='%s'>here &#187;</a> &#160; Payment Box <a target='_blank' href='%s'>Live Demo &#187;</a>", GOURLWC ), $this->url, "https://gourl.io/lib/examples/example_customize_box.php?alang=en#hacrypto") . "<br><br>"
                ),
                    'customtext' 	=> array(
                    'title'       	=> __( 'Custom Payment Text', GOURLWC ),
                    'type'        	=> 'textarea',
                    'default'     	=> '',
                    'description' 	=> __( 'Your text on payment page below "Pay Now" title. For example, you can add "If you have any questions please feel free to contact us at any time on (telephone) or contact by (email)"', GOURLWC )
                ),
                    'qrcodesize'		=> array(
                    'title'       	=> __( 'QRcode Size', GOURLWC ),
                    'type'        	=> 'text',
                    'label'        	=> 'px',
                    'default'     	=> "200px",
                    'description' 	=> __( "QRcode image size in payment box. Default 200px. Allowed: 0..500px", GOURLWC )
                ),
                    'langmenu' 		=> array(
                    'title' 		=> __('Language Menu', GOURLWC ),
                    'type' 			=> 'select',
                    'options' 		=> $this->showhidemenu,
                    'default' 		=> key($this->showhidemenu),
                    'description' 	=> __( "Show or hide language selection menu above payment box", GOURLWC )
                ),
                    'redirect'			=> array(
                    'title'       	=> __( 'Redirect Url', GOURLWC ),
                    'type'        	=> 'text',
                    'default'     	=> '',
                    'description' 	=> __( 'Redirect to another page after payment is received (3 seconds delay). For example, http://yoursite.com/thank_you.php', GOURLWC ) . "<br><br><br><br><br>"
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
     * 14.4 Admin footer page text 
     */
	public function admin_footer_text()
    {
	    	return sprintf( __( "If you like <b>Bitcoin Gateway for WooCommerce</b> please leave us a %s rating on %s. A huge thank you from GoUrl in advance!", GOURLWC ), "<a href='https://wordpress.org/support/view/plugin-reviews/gourl-woocommerce-bitcoin-altcoin-payment-gateway-addon?filter=5#postform' target='_blank'>&#9733;&#9733;&#9733;&#9733;&#9733;</a>", "<a href='https://wordpress.org/support/view/plugin-reviews/gourl-woocommerce-bitcoin-altcoin-payment-gateway-addon?filter=5#postform' target='_blank'>WordPress.org</a>");
    }
     

	    
	  
    /*
     * 14.5 Forward to WC Checkout Page
     */
    public function process_payment( $order_id )
    {
        global $woocommerce;
        static $emultiplier = 0;
         
        // New Order
        $order = new WC_Order( $order_id );
        
        $order_id    = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->id          : $order->get_id();
        $userID      = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->user_id     : $order->get_user_id();
        $order_total = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->order_total : $order->get_total();
        	
        // Mark as pending (we're awaiting the payment)
        $order->update_status('pending', __('Awaiting payment notification from GoUrl', GOURLWC));
    
        	
        // Payment Page
        $payment_link = $this->get_return_url($order);
    
        	
        // Get original price in fiat
        $live = $totalFiat = 0;
        $arr = gourl_wc_currency_type();
        if ($arr["2way"])
        {
            if (!$emultiplier)
            {
                $gateways = $woocommerce->payment_gateways->payment_gateways();
                if (isset($gateways['gourlpayments'])) $emultiplier = trim(str_replace(array("%", ","), array("", "."), $gateways['gourlpayments']->get_option('emultiplier')));
                if (!$emultiplier || !is_numeric($emultiplier) || $emultiplier < 0.01) $emultiplier = 1;
            }
             
            $btc = gourl_bitcoin_live_price ($arr["admin"]); // 1BTC bitcoin price  in USD/EUR/AUD/RUB/GBP/etc.
    
            if ($arr["user"] == "BTC") $live = $btc;
            elseif (in_array($arr["user"], json_decode(GOURLWC_2WAY, true))) $live = $btc * gourl_altcoin_btc_price ($arr["user"]); // atcoins 1LTC/1DASH/1BCH/1DOGE  in USD/EUR/AUD/RUB/GBP/etc.
             
            if ($live > 0)
            {
                $totalFiat = round(floatval($order_total) * floatval($live) / 1.01 / floatval($emultiplier), 2);
                if ($totalFiat > 10)     $totalFiat = number_format($totalFiat);
                elseif ($totalFiat > 1)  $totalFiat = round($totalFiat, 1);
                $totalFiat .= " " . $arr["admin"];
            }
        }
        elseif ($arr["admin"] == $arr["user"] && array_key_exists($arr["admin"], $this->coin_names)) // cryptocurrency selected; show price in USD
        {
            $btc = gourl_bitcoin_live_price ("USD"); // USD
            if ($arr["user"] == "BTC") $live = $btc;
            else $live = $btc * gourl_altcoin_btc_price ($arr["user"]); // atcoins 1LTC/1DASH/1BCH/1DOGE  in USD
            
            $totalFiat = round(floatval($order_total) * floatval($live), 2);
            
            if ($totalFiat > 10)     $totalFiat = number_format($totalFiat);
            elseif ($totalFiat > 1)  $totalFiat = round($totalFiat, 1);
            $totalFiat .= " USD";
        }
    
        
        
        $total = ($order_total >= 1000 ? number_format($order_total) : $order_total)." ".$arr["user"];
        $orderpage = $order->get_checkout_order_received_url()."&prvw=1";

        if (!get_post_meta( $order_id, '_gourl_worder_orderid', true ))
        {
            update_post_meta( $order_id, '_gourl_worder_orderid', 	    $order_id );
            update_post_meta( $order_id, '_gourl_worder_userid', 	    $userID );
            update_post_meta( $order_id, '_gourl_worder_createtime',   gmdate("c") );
            
            update_post_meta( $order_id, '_gourl_worder_orderpage',     $orderpage );
            update_post_meta( $order_id, '_gourl_worder_created',      gmdate("d M Y, H:i") );
            
            update_post_meta( $order_id, '_gourl_worder_currencies', $arr );
            update_post_meta( $order_id, '_gourl_worder_amountcrypto', $total );
            update_post_meta( $order_id, '_gourl_worder_amountfiat',   ($totalFiat?$totalFiat:$total) );          
        }
        
        
        $total_html = $total;
        if ($totalFiat) $total_html .= " / <b> ".$totalFiat."</b>";
        else $total_html = "<b>" . $total_html . "</b>";
    
        $userprofile = (!$userID) ? __('Guest', GOURLWC) : "<a href='".admin_url("user-edit.php?user_id=".$userID)."'>user".$userID."</a>";
        $order->add_order_note(sprintf(__("Order Created by %s<br>Order Total: %s<br>Awaiting Cryptocurrency <a href='%s'>Payment</a> ...", GOURLWC), $userprofile, $total_html, $orderpage) . '<br>');
        	
        // Remove cart
        WC()->cart->empty_cart();
    
        // Return redirect
        return array(
            'result' 	=> 'success',
            'redirect'	=> $payment_link
        );
    }    
    
    
    
    

    /*
     * 14.6 WC Order Checkout Page
     */
    public function cryptocoin_payment( $order_id )
	{
		global $gourl;
		
		$order = new WC_Order( $order_id );
		
		$order_id       = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->id             : $order->get_id();
		$order_status   = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->status         : $order->get_status();
		$post_status    = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->post_status    : get_post_status( $order_id );
		$userID         = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->user_id        : $order->get_user_id();
		$order_currency = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->order_currency : $order->get_currency();
		$order_total    = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->order_total    : $order->get_total();

		
		if ($order === false)
		{
			echo '<br><h2>' . __( 'Information', GOURLWC ) . '</h2>' . PHP_EOL;
			echo "<div class='woocommerce-error'>". sprintf(__( 'The GoUrl payment plugin was called to process a payment but could not retrieve the order details for orderID %s. Cannot continue!', GOURLWC ), $order_id)."</div>";
		}
		elseif ($order_status == "cancelled" || $post_status == "wc-cancelled")
		{
			echo '<br><h2>' . __( 'Information', GOURLWC ) . '</h2>' . PHP_EOL;
			echo "<div class='woocommerce-error'>". __( "This order's status is 'Cancelled' - it cannot be paid for. Please contact us if you need assistance.", GOURLWC )."</div>";
		}
		elseif (!class_exists('gourlclass') || !defined('GOURL') || !is_object($gourl))
		{
			echo '<br><h2>' . __( 'Information', GOURLWC ) . '</h2>' . PHP_EOL;
			echo "<div class='woocommerce-error'>".sprintf(__( "Please try a different payment method. Admin need to install and activate wordpress plugin <a href='%s'>GoUrl Bitcoin Gateway for Wordpress</a> to accept Bitcoin/Altcoin Payments online.", GOURLWC), "https://gourl.io/bitcoin-wordpress-plugin.html")."</div>";
		}
		elseif (!$this->payments || !$this->defcoin || true === version_compare(WOOCOMMERCE_VERSION, '2.1', '<') || true === version_compare(GOURL_VERSION, '1.4.5', '<'))
		{
			echo '<br><h2>' . __( 'Information', GOURLWC ) . '</h2>' . PHP_EOL;
			echo  "<div class='woocommerce-error'>".sprintf(__( 'Sorry, but there was an error processing your order. Please try a different payment method or contact us if you need assistance (Bitcoin Gateway Plugin v1.4.5+ not configured / %s not activated).', GOURLWC ),(!$this->payments || !$this->defcoin || !isset($this->coin_names[$order_currency])? $this->title : $this->coin_names[$order_currency]))."</div>";
		}
		else 
		{ 	
		    
			$plugin          = "gourlwoocommerce";
			$amount          = $order_total; 	
			$currency        = (gourl_wc_currency_type($order_currency)["2way"]) ? gourl_wc_currency_type($order_currency)["user"] : $order_currency; 
			$period          = "NOEXPIRY";
			$language        = $this->deflang;
			$coin            = $this->coin_names[$this->defcoin];
			$crypto          = array_key_exists($currency, $this->coin_names);
				
			
			// you can place below your affiliate key, i.e. $affiliate_key ='DEV.....';
			// more info - https://gourl.io/affiliates.html
			$affiliate_key   = GOURLWC_AFFILIATE_KEY;
					
			// try to use original readonly order values
			$original_orderID     = get_post_meta( $order_id, '_gourl_worder_orderid', true );
			$original_userID      = get_post_meta( $order_id, '_gourl_worder_userid', 	true );
			$original_createtime  = get_post_meta( $order_id, '_gourl_worder_createtime',  true );
			if ($original_orderID && $original_orderID == $order_id && strtotime($original_createtime)) $userID = $original_userID;
			else $original_orderID = $original_createtime = $original_userID = '';
			
			
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
						echo "<div class='woocommerce-error'>".sprintf(__( 'Sorry, but there was an error processing your order. Please try later or use a different payment method. System cannot receive exchange rates for %s/USD from xe.com', GOURLWC ), $currency)."</div>";
					}
					else $currency = "USD";
				}
					
								

				// Payment Box
				// ------------------
				if ($amount > 0)
				{

					// crypto payment gateway
					$result = $gourl->cryptopayments ($plugin, $amount, $currency, "order".$order_id, $period, $language, $coin, $affiliate_key, $userID, $this->iconwidth, $this->emultiplier, array("customtext" => $this->customtext, "qrcodesize" => $this->qrcodesize, "showlanguages" => ($this->langmenu=='hide'?false:true), "redirect" => (isset($_GET["prvw"]) && $_GET["prvw"] == "1"?"":$this->redirect)));

					if (!isset($result["is_paid"]) || !$result["is_paid"]) 
					{   
					    //echo '<h2>' . __( 'Pay Now -', GOURLWC ) . '</h2>' . PHP_EOL;
					    
					    echo  "<script>
    					           jQuery(document).ready(function() {
	   				                   jQuery( '.entry-title' ).text('" . __( 'Pay Now -', GOURLWC ) . "');
					                   jQuery( '.woocommerce-thankyou-order-received' ).remove();
					               });
					           </script>";
					}
				
					
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
	     * 14.7 GoUrl Bitcoin Gateway - Instant Payment Notification
	     */
	    public function gourlcallback( $user_id, $order_id, $payment_details, $box_status) 
	    {
	    	if (!in_array($box_status, array("cryptobox_newrecord", "cryptobox_updated"))) return false;
	    	
	    	if (strpos($order_id, "order") === 0) $order_id = substr($order_id, 5); else return false;
	    	
	    	if (!$user_id || $payment_details["status"] != "payment_received") return false;
	    	
	    	$order = new WC_Order( $order_id );  if ($order === false) return false;
	    	
	    	$order_id = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->id : $order->get_id();
	

	    	// try to use original readonly order values
	    	// sometimes can be created duplicate order numbers, if you restore out-of-date WC from backup, etc
	    	$original_orderID     = get_post_meta( $order_id, '_gourl_worder_orderid', true );
	    	$original_userID      = get_post_meta( $order_id, '_gourl_worder_userid', 	true );
	    	$original_createtime  = get_post_meta( $order_id, '_gourl_worder_createtime',  true );
	    	if ($original_orderID && $original_orderID == $order_id && strtotime($original_createtime)) 
	    	{
	    	    if (!$original_userID) $original_userID = 'guest';
	    	    if ($user_id != $original_userID) return false;
	    	    if (abs(strtotime($original_createtime) - $payment_details["paymentTimestamp"]) > 2*24*60*60) return false;
	    	}

	    	
	    	$coinName 	= ucfirst($payment_details["coinname"]);
	    	$amount		= $payment_details["amount"] . " " . $payment_details["coinlabel"] . "&#160; (<b>~ " . $payment_details["amountusd"] . " USD</b>)";
	    	$payID		= $payment_details["paymentID"];
	    	$confirmed	= $payment_details["is_confirmed"];
	    	$status		= ($confirmed) ? $this->ostatus2 : $this->ostatus;
	    	
	    	
	    	// New Payment Received
	    	if ($box_status == "cryptobox_newrecord") 
	    	{	
	    	    
	    	    update_post_meta( $order_id, '_gourl_worder_coinname', $coinName );
	    	    update_post_meta( $order_id, '_gourl_worder_confirmed', $confirmed );
	    	    update_post_meta( $order_id, '_gourl_worder_preceived', date("d M Y, H:i", $payment_details["paymentTimestamp"]) );
	    	    update_post_meta( $order_id, '_gourl_worder_paymentid', $payID );
	    	    update_post_meta( $order_id, '_gourl_worder_pdetails', GOURL_ADMIN.GOURL."payments&s=payment_".$payID );
	    	    update_post_meta( $order_id, '_gourl_worder_pcountry', get_country_name($payment_details["usercountry"]) );
	    	    update_post_meta( $order_id, '_gourl_worder_pcountrycode', $payment_details["usercountry"] );

	    	    delete_post_meta( $order_id, '_gourl_worder_orderpage' );
	    	    update_post_meta( $order_id, '_gourl_worder_orderpage', $order->get_checkout_order_received_url()."&".CRYPTOBOX_COINS_HTMLID."=".strtolower($coinName)."&prvw=1" );

	    	    $currencies = get_post_meta( $order_id, '_gourl_worder_currencies', false )[0];
	    	    
	    	    update_post_meta( $order_id, '_gourl_worder_pamountcrypto', $payment_details["amount"] . " " . $payment_details["coinlabel"] ); // 1.1 BTC
	    	    update_post_meta( $order_id, '_gourl_worder_pamountusd', $payment_details["amountusd"] . " USD" );    // 4350 USD
	    	    
	    	    if ($currencies["admin"] == "USD")
	    	    {
	    	        $v = $payment_details["amountusd"] . " USD";
	    	    }
	    	    elseif (array_key_exists($currencies["admin"], $this->coin_names)) // cryptocurrency 
	    	    {
	    	        $btc = gourl_bitcoin_live_price ("USD"); // USD
	    	        if ($currencies["admin"] == "BTC") $live = $btc;
	    	        else $live = $btc * gourl_altcoin_btc_price ($currencies["admin"]); // atcoins 1LTC/1DASH/1BCH/1DOGE  in USD

	    	        $v = round(floatval($payment_details["amountusd"]) / floatval($live), 5);
	    	        
	    	        if ($v > 1)      $v = number_format($v, 3);
	    	        elseif ($v > 0)  $v = number_format($v, 5);
	    	        
	    	        if ($v) $v .= " " . $currencies["admin"];
	    	    }
	    	    else 
	    	    {
	    	        $v = gourl_convert_currency("USD", $currencies["admin"], $payment_details["amountusd"]);
	    	        if ($v) $v .= " " . $currencies["admin"];
	    	    }
	    	        
	    	    update_post_meta( $order_id, '_gourl_worder_pamountmain', $v);   // in main woocommerce admin currency 3300 EUR / BTC / etc
	    	    
	    	    update_post_meta( $order_id, '_gourl_worder_txid', $payment_details["tx"] );
	    	    update_post_meta( $order_id, '_gourl_worder_addrid', $payment_details["addr"] );

	    	    
	    		// Reduce stock levels
	    		if (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '>')) wc_reduce_stock_levels( $order_id ); else $order->reduce_order_stock();	    	    	    
	    	    
	    		// Update Status
	    		$order->update_status($status);
	    	
	    		$order->add_order_note(sprintf(__("%s Payment Received<br>%s<br>Payment id <a href='%s'>%s</a> / <a href='%s'>order page</a> <br>Awaiting network confirmation...", GOURLWC), __($coinName, GOURLWC), $amount, GOURL_ADMIN.GOURL."payments&s=payment_".$payID, $payID, $order->get_checkout_order_received_url()."&".CRYPTOBOX_COINS_HTMLID."=".$payment_details["coinname"]."&prvw=1") . '<br>');
	    	}
	    	
	    	

	    	
	    	
	    	// Existing Payment confirmed (6+ confirmations)
	    	if ($confirmed)
	    	{	
	    		delete_post_meta( $order_id, '_gourl_worder_confirmed' );
	    		update_post_meta( $order_id, '_gourl_worder_confirmed', $confirmed );
	    			    		 
	    		$order->update_status($status);
	    		
	    		$order->add_order_note(sprintf(__("%s Payment id <a href='%s'>%s</a> Confirmed", GOURLWC), __($coinName, GOURLWC), GOURL_ADMIN.GOURL."payments&s=payment_".$payID, $payID) . '<br>');
	    	}
	    	
	    	
	    	// Completed
	    	if ($status == "completed") $order->payment_complete(); 
	    	

	    	return true;
	    }
	}
	// end class WC_Gateway_GoUrl




	
	
	/*
	 *  15. Instant Payment Notification Function - pluginname."_gourlcallback"
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