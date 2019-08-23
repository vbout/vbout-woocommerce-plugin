<?php

namespace App;

use App\Libraries\Vbout\Services\EcommerceWS;
use App\Libraries\Vbout\Services\EmailMarketingWS;

/**
 * WCVbout class
 *
 */
class WCVbout
{
    private $integrationId;
    private $domain  = '';
    private $apiKey;
    private $abandoned_carts = 0;
    private $product_visits = 0;
    private $category_visits = 0;
    private $customers = 0;
    private $product_feed =0;
    private $marketing = 0;
    private $current_customers = 0;
    private $sync_current_products = 0;
    private $search = 0;
    private $vboutApp2;
    private $cartID;
    private $sessionId;

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->integrationId = 'vbout-integration';
        $this->wcFields = explode(',', defined('WC_FIELDS') ? WC_FIELDS : '');
        add_action('plugins_loaded', array($this, 'init'));
    }
    /**
     * Entry point of the application
     * @return null
     */
    public function init()
    {

        if (isset($_GET['tab']) && $_GET['tab'] === 'integration') {
            add_action('admin_enqueue_scripts', array($this, 'loadScripts'));
            add_action('admin_enqueue_scripts', array($this, 'loadStyles'));
        }else{
            add_action('wp_enqueue_scripts', array($this, 'loadWPScript'));
//            add_action( 'wp_ajax_updatevboutabandon', array( $this, 'onPostData' )  );
//            add_action( 'wp_ajax_nopriv_updatevboutabandon', array( $this, 'onPostData' ) );
        }

        try {
            // Load action hooks
            add_action('woocommerce_created_customer', array($this, 'onCustomerCreated'), 10, 2);
            add_action( 'user_register', array($this, 'onCustomerCreated'),10 ,1);

            add_action('woocommerce_after_cart_contents',  array($this, 'wc_cart_data'));
            add_action('woocommerce_thankyou', array($this, 'onOrderCompleted'), 10, 1);
            add_action('clear_auth_cookie', array($this, 'onLogout'));
            add_action('woocommerce_settings_saved', array($this, 'onSettingsSaved'));
            add_action('admin_notices', array($this, 'onSettingsNotified'));
            add_action('woocommerce_after_single_product',array($this, 'wc_product_data'), 10);
            add_action('woocommerce_after_main_content', array($this, 'wc_category_data'), 10);
            add_action('woocommerce_cart_item_removed', array($this, 'wc_item_remove'), 10, 2);
            add_action('pre_get_posts', array($this, 'wc_product_search'));
            add_action('wp_login', array($this, 'wc_customer_update'), 99 );
            add_action( 'woocommerce_process_product_meta', array($this, 'wc_product_add'), 12, 2 );

            add_action( 'woocommerce_admin_order_data_after_order_details',  array($this,'wc_order_update'),10,1);
            // Register integrations
            add_filter('woocommerce_integrations', array($this, 'addIntegration'));

            $this->loadConfig();
            session_start();
            // Load email marketing object
            $this->vboutApp2 = new EcommerceWS(array('api_key' => $this->apiKey));
        } catch (\Exception $e) {
            error_log('Caught exception: "' . $e->getMessage() . '" on "' . $e->getFile() . '" line ' . $e->getLine());
        }
    }
    //Loading assets to create a new Integration tab for WooCommerce
    public function loadScripts()
    {
        wp_register_script(
            'vbout_script',
            plugins_url('assets/js/main.js', dirname(__FILE__)),
            array('jquery')
        );
        wp_enqueue_script('vbout_script');
    }
    public function loadWPScript()
    {
        wp_register_script(
            'vbout_script',
            plugins_url('assets/js/load.js', dirname(__FILE__)),
            array('jquery')
        );
        wp_enqueue_script('vbout_script');
    }
    public function loadStyles()
    {
        wp_register_style(
            'vbout_style',
            plugins_url('assets/css/style.css', dirname(__FILE__))
        );
        wp_enqueue_style('vbout_style');
    }


    /**
     * configuration handling
     */
    private function loadConfig()
    {
        $WCSettings = get_option('woocommerce_' . $this->integrationId . '_settings');

        if (!$WCSettings) {
            throw new \Exception('Incomplete settings', 1);
        }

        //New Settings
        if ( $WCSettings['abandoned_carts'] == 'yes')
            $this->abandoned_carts = 1;

        if ( $WCSettings['product_visits'] == 'yes')
            $this->product_visits = 1;

        if ( $WCSettings['category_visits'] == 'yes')
            $this->category_visits = 1;

        if ( $WCSettings['customers'] == 'yes')
            $this->customers = 1;

        if ( $WCSettings['product_feed'] == 'yes')
            $this->product_feed = 1;

        if ( $WCSettings['current_customers'] == 'yes')
            $this->current_customers = 1;

        if ( $WCSettings['sync_current_products'] == 'yes')
            $this->sync_current_products = 1;

        if ( $WCSettings['search'] == 'yes')
            $this->search = 1;

        $this->apiKey                   = $WCSettings['apiKey'];
        $this->domain                   = $WCSettings['domain'];
        $this->sessionId                = $this->wc_unique_id();
    }

    /**
     * Method to add integrations
     * @param String $integrations
     * @return Array
     */
    public function addIntegration($integrations) {
        $integrations[] = 'App\WCVboutIntegration';
        return $integrations;
    }
    // On Settings change, get new feature functionalities
    public function onSettingsSaved()
    {
        if ($_GET['tab'] === 'integration') {
            $this->loadConfig();

            if($this->domain != '')
            {
                $vboutApp = new EcommerceWS(array('api_key' => $this->apiKey));

                $settingsPayload = array(
                    'domain'  => $this->domain,
                    'apiname' => 'WooCommerce',
                    'apikey'  => $this->apiKey,
                );
                $vboutApp->sendAPIIntegrationCreation($settingsPayload,1);

                $settings = array(
                    'abandoned_carts'       => $this->abandoned_carts,
                    'sync_current_products' => $this->sync_current_products,
                    'search'                => $this->search,
                    'product_visits'        => $this->product_visits,
                    'category_visits'       => $this->category_visits,
                    'customers'             => $this->customers,
                    'product_feed'          => $this->product_feed,
                    'current_customers'     => $this->current_customers,
                    'marketing'             => $this->marketing,
                    'domain'                => $this->domain,
                    'apiName'               => 'WooCommerce',
                );
                $vboutApp->sendSettingsSync($settings);
                if($this->sync_current_products == 1)
                {
                    $this->syncCurrentProducts();
                }
                if($this->current_customers == 1)
                {
                    $this->syncCurrentCustomers();
                }
            }
            echo "<script type='text/javascript'>window.location = document.location.href + '&saved=1';</script>";
        }
    }
    // Create a notification after saving settings.
    public function onSettingsNotified()
    {
        if (isset($_GET['saved']) && $_GET['saved']) {
            echo '<div class="updated fade"><p>' . sprintf(__('%sVbout settings saved.%s If settings do not appear below, check your API key and try again. Don\'t have an account? Please click %shere%s.', 'woocommerce-vbout-integration'), '<strong>', '</strong>', '<a href="https://www.vbout.com/register/">', '</a>' ) . '</p></div>' . "\n";
        }
    }
    // create a unique id in a cookie
    private function wc_unique_id()
    {
        $sessionId = '';
        if (isset($_COOKIE['vbtEcommerceUniqueId']))
            $sessionId = $_COOKIE['vbtEcommerceUniqueId'];
        return $sessionId;
    }
    // Sync All users
    private function syncCurrentCustomers()
    {
        $users = get_users();
        if (count($users) > 0) {
            foreach ($users as $user) {
                $display_name = explode(" ",$user->data->display_name);
                if (isset($display_name[0]))
                    $first_name = $display_name[0];
                if (isset($display_name[1]))
                    $last_name = $display_name[1];

                $customer = array(
                    "firstname" => $first_name,
                    "lastname"  => $last_name,
                    "email"     => $user->data->user_email,
                    'api_key'   => $this->apiKey,
                    'domain'    => $this->domain,
                );
                $result = $this->vboutApp2->Customer($customer,1);
            }
        }
    }
    // Sync All Products
    private function syncCurrentProducts()
    {

        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        );


        $loop = new \WP_Query( $args );
        if ( $loop->have_posts() ) {

            foreach ($loop->posts as $productValue) {

                if (get_post_meta( $productValue->ID, '_regular_price', true) == false)
                    $price = 0.0;
                else $price = get_post_meta( $productValue->ID, '_regular_price', true);
                if(get_post_meta( $productValue->ID, '_sale_price', true) == false)
                {
                    $discountPrice = "0.0";
                }
                else  $discountPrice = get_post_meta( $productValue->ID, '_sale_price', true);

                //Product Variations.

                $terms = get_the_terms( $productValue->ID, 'product_cat' );
                $categoryId = 'N/A';
                $categoryName = 'N/A';
                if(count($terms)>0 )
                {
                    $categoryId = $terms[0]->term_id;
                    $categoryName = $terms[0]->name;
                }
                $product_s = wc_get_product( $productValue->ID );

                $variationArray = array();
                if ($product_s->product_type == 'variable') {
                    $product_s = new \WC_Product_Variable($productValue->ID);
                    $variations = $product_s->get_available_variations();
                    foreach ($variations as $variation) {
                        $titleKeys = array_keys($variation['attributes']);
                        foreach ($titleKeys as $titleKey) {
                            if (isset($variation['attributes'][$titleKey])) {
                                $title = explode('attribute_pa_', $titleKey);
                                if($title != '' || $variation['attributes'][$titleKey] != '' )
                                    $variationArray[$title[1]] = $variation['attributes'][$titleKey];
                            }
                        }
                    }
                }
                $productData = array(
                    "productid"     => $productValue->ID,
                    "name"          => $productValue->post_title,
                    "price"         => (float)$price,
                    "description"   => $productValue->post_content,
                    "discountprice" => $discountPrice,
                    "currency"      => get_woocommerce_currency(),
                    "sku"           => get_post_meta( $productValue->ID, '_sku', true),
                    "categoryid"    => $categoryId,
                    "variation"     => $variationArray,
                    "category"      => $categoryName,
                    "link"          => get_permalink($productValue->ID),
                    "image"         => get_the_post_thumbnail_url($productValue->ID,'full'),
                    'api_key'       => $this->apiKey,
                    'domain'        => $this->domain,
                );
                $result = $this->vboutApp2->Product($productData,1);
            }
        }
        else {
            echo __( 'No products found' );
        }
        wp_reset_postdata();

    }
    // Uninstalling the Plugin ( Deletion )
    public function uninstall()
    {
        // Load configurations
        $this->loadConfig();
        $vboutApp = new EcommerceWS(array('api_key' => $this->apiKey));
        if($this->domain != '') {
            $settingsPayload = array(
                'domain' => $this->domain,
                'apiname' => 'WooCommerce',
                'api_key' => $this->apiKey,
            );
            $result = $vboutApp->sendAPIIntegrationCreation($settingsPayload, 3);
            delete_option('woocommerce_' . $this->integrationId . '_settings');
        }
    }


    /**
     * Cart Handeling
     */
    //Get Cart Data with Cart Item
    public function wc_cart_data()
    {

        if ($this->abandoned_carts == 1) {

            global $woocommerce;
            $items = $woocommerce->cart->get_cart();

            $current_user = wp_get_current_user();
            if (isset($_SESSION['cartID']))
                $this->cartID = $_SESSION['cartID'];
            else {
                $this->cartID = sha1(mt_rand(1, 90000) . 'SALT');
                $_SESSION['cartID'] = $this->cartID;
            }
            $store = array(
                "domain"        => $this->domain,
                "cartcurrency"  => get_woocommerce_currency(),
                "cartid"        => $this->cartID,
                'ipaddress'     => $_SERVER['REMOTE_ADDR'],
                "customer"      => $current_user->user_email,
                "storename"     => $_SERVER['HTTP_HOST'],
                "abandonurl"    => $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'],
                "uniqueid"      => $this->sessionId,
            );

            $result = $this->vboutApp2->Cart($store, 1);
            foreach ($items as $item) {

                $product = $item['data'];

                //Category
                $categoryId = 'N/A';
                $categoryName = 'N/A';
                $VARIATION = wc_get_product($product->get_id());
                $variationArray = array();
                $product_s = wc_get_product($VARIATION->get_parent_id());

                if ($product_s->product_type == 'variable') {
                    $parentProductId = $VARIATION->get_parent_id();
                    $productid = $parentProductId;

                    $product_s = new \WC_Product_Variable($parentProductId);
                    $variations = $product_s->get_available_variations();
                    // Get variations
                    foreach ($variations as $variation) {
                        if ($variation['variation_id'] == $product->get_id()) {
                            $titleKeys = array_keys($variation['attributes']);
                            foreach ($titleKeys as $titleKey) {
                                if (isset($variation['attributes'][$titleKey])) {
                                    $title = explode('attribute_pa_', $titleKey);
                                    if($title != '' || $variation['attributes'][$titleKey] != '' )
                                        $variationArray[$title[1]] = $variation['attributes'][$titleKey];
                                }
                            }
                        }
                    }
                    // Get image
                    if (get_the_post_thumbnail_url($product->get_id(), 'full') == '')
                        $image = get_the_post_thumbnail_url($parentProductId, 'full');
                    else $image = get_the_post_thumbnail_url($product->get_id(), 'full');
                    $terms = get_the_terms($parentProductId, 'product_cat');
                } else {
                    $productid = $product->get_id();
                    $image = get_the_post_thumbnail_url($product->get_id(), 'full');
                    $terms = get_the_terms($product->get_id(), 'product_cat');
                }
                if (count($terms) > 0) {
                    $categoryId = $terms[0]->term_id;
                    $categoryName = $terms[0]->name;
                }
                if ($product->get_sale_price() == 0)
                    $discountPrice = '0.0';
                else $discountPrice = $product->get_sale_price();

                $productData = array(
                    "domain"        => $this->domain,
                    "cartid"        => $this->cartID,
                    "productid"     => (string)$productid,
                    "name"          => $product->get_name(),
                    "price"         => $product->get_price(),
                    "description"   => $product->get_description(),
                    "discountprice" => $discountPrice,
                    "currency"      => get_woocommerce_currency(),
                    "quantity"      => (string)$item['quantity'],
                    "categoryid"    => $categoryId,
                    "variation"     => $variationArray,
                    "category"      => $categoryName,
                    "sku"           => $product->get_sku(),
                    "link"          => get_permalink($product->get_id()),
                    "image"         => $image,
                    "uniqueid"      => $this->sessionId,
                );
                $result = $this->vboutApp2->CartItem($productData, 1);
            }
        }

    }
    //Function Remove cart
    public function wc_item_remove($removed_cart_item_key, $cart) {

        $boolVariation = false;
        $variationArray = array();

        if( $cart->removed_cart_contents[ $removed_cart_item_key ]['variation_id'] != 0)
        {
            $variation_id =  $cart->removed_cart_contents[ $removed_cart_item_key ]['variation_id'];
            $boolVariation = true;
            $parentProductId = $cart->removed_cart_contents[ $removed_cart_item_key ]['product_id'];
        }

        else $product_id = $cart->removed_cart_contents[ $removed_cart_item_key ]['product_id'];

        if($boolVariation) {
            $product_id = $parentProductId;
            $product_s = wc_get_product($parentProductId);

            if ($product_s->product_type == 'variable') {
                $productid = $parentProductId;

                $product_s = new \WC_Product_Variable($parentProductId);
                $variations = $product_s->get_available_variations();
                // Get variations
                foreach ($variations as $variation) {
                    if ($variation['variation_id'] == $variation_id) {
                        $titleKeys = array_keys($variation['attributes']);
                        foreach ($titleKeys as $titleKey) {
                            if (isset($variation['attributes'][$titleKey])) {
                                $title = explode('attribute_pa_', $titleKey);
                                if ($title != '' || $variation['attributes'][$titleKey] != '')
                                    $variationArray[$title[1]] = $variation['attributes'][$titleKey];
                            }
                        }
                    }
                }
            }
        }

        $item = array(
            "cartid"    => $_SESSION['cartID'],
            "domain"    => $this->domain,
            "productid" => $product_id,
            "variation" => $variationArray,
        );
        $result = $this->vboutApp2->CartItem($item,3);

    }

    /**
     * Creates a contact after a customer is created
     */
    public function onCustomerCreated($user_id)
    {
        $customer = get_userdata( $user_id );
        $last_name = $customer->data->last_name;
        $first_name = $customer->first_name;

        $customer = array(
            "firstname"     => $first_name,
            "lastname"      => $last_name,
            "email"         => $customer->data->user_email,
            'api_key'       => $this->apiKey,
            'domain'        => $this->domain,
            'ipaddress'     => $_SERVER['REMOTE_ADDR'],
            "uniqueid"      => $this->sessionId,
        );
        $result = $this->vboutApp2->Customer($customer,1);
    }
    //Update customer on Login
    public function wc_customer_update()
    {
        if($this->customers == 1 )
        {
            $current_user = wp_get_current_user();

            $customer = array(
                "firstname"     => $current_user->user_firstname ,
                "lastname"      => $current_user->user_lastname,
                "email"         => $current_user->data->user_email,
                'api_key'       => $this->apiKey,
                'domain'        => $this->domain,
                'ipaddress'     => $_SERVER['REMOTE_ADDR'],
                "uniqueid"      => $this->sessionId,
            );
            $result = $this->vboutApp2->Customer($customer,1);
        }

    }
    //On Logout update customer data
    public function onLogout()
    {

    }

    /**
     * Product Handeling
     */
    // Add a product
    public function wc_product_add( $post_id, $post )
    {
        if ($this->product_feed == 1) {

            $product_s = wc_get_product($post->ID);

            if (get_post_meta($post->ID, '_regular_price', true) == false)
                $price = '0.0';
            else $price = get_post_meta($post->ID, '_regular_price', true);

            if (get_post_meta($post->ID, '_sale_price', true) == false) {
                $discountPrice = "0.0";
            } else
                $discountPrice = get_post_meta($post->ID, '_sale_price', true);

            $terms = get_the_terms($post->ID, 'product_cat');
            $categoryId = 'N/A';
            $categoryName = 'N/A';

            if (count($terms) > 0) {
                $categoryId = $terms[0]->term_id;
                $categoryName = $terms[0]->name;
            }

            $variationArray = array();
            if ($product_s->product_type == 'variable') {
                $product_s = new \WC_Product_Variable($post->ID);
                $variations = $product_s->get_available_variations();
                foreach ($variations as $variation) {
                    $titleKeys = array_keys($variation['attributes']);
                    foreach ($titleKeys as $titleKey) {
                        if (isset($variation['attributes'][$titleKey])) {
                            $title = explode('attribute_pa_', $titleKey);
                            if($title != '' || $variation['attributes'][$titleKey] != '' )
                                $variationArray[$title[1]] = $variation['attributes'][$titleKey];
                        }
                    }
                }
            }
            $productData = array(
                "productid" => $post->ID,
                "name" => $post->post_title,
                "price" => $price,
                "description" => $post->post_content,
                "discountprice" => $discountPrice,
                "currency" => get_woocommerce_currency(),
                "sku" => get_post_meta($post->ID, '_sku', true),
                "categoryid" => $categoryId,
                "category" => $categoryName,
                "variation" => $variationArray,
                "link" => get_permalink($post->ID),
                "image" => get_the_post_thumbnail_url($post->ID, 'full'),
                'api_key' => $this->apiKey,
                'domain' => $this->domain,
            );
            $result = $this->vboutApp2->Product($productData, 1);
        }
    }
    //Products View Function
    public function wc_product_data()
    {
        if ($this->product_visits == 1) {
            global $product;
            $current_user = wp_get_current_user();
            $product_s = wc_get_product($product->ID);

            $variationArray= array();
            if ($product_s->product_type == 'variable') {
                $product_s = new \WC_Product_Variable($product->get_id());
                $variations = $product_s->get_available_variations();
                foreach ($variations as $variation) {
                    $titleKeys = array_keys($variation['attributes']);
                    foreach ($titleKeys as $titleKey) {
                        if (isset($variation['attributes'][$titleKey])) {
                            $title = explode('attribute_pa_', $titleKey);
                            if($title != '' || $variation['attributes'][$titleKey] != '' )
                                $variationArray[$title[1]] = $variation['attributes'][$titleKey];
                        }
                    }
                }
            }
            if ($product->get_sale_price() == 0)
                $discountPrice = '0.0';
            else $discountPrice = $product->get_sale_price();

            $product = array(
                "customer"      => $current_user->user_email,
                "productid"     => $product->get_id(),
                "name"          => $product->get_name(),
                "price"         => $product->get_price(),
                "description"   => $product->get_description(),
                "variation"     => $variationArray,
                "discountprice" => $discountPrice,
                "currency"      => get_woocommerce_currency(),
                "sku"           => $product->get_sku(),
                "categoryid"    => (string)$product->get_category_ids()[0],
                "category"      => get_the_category_by_ID($product->get_category_ids()[0]),
                "link"          => get_permalink($product->get_id()),
                "image"         => get_the_post_thumbnail_url($product->get_id(), 'full'),
                "domain"        => $this->domain,
                "uniqueid"      => $this->sessionId,
            );

            $result = $this->vboutApp2->Product($product, 1);
        }
    }
    //Function Category
    public function wc_category_data()
    {

        if ($this->category_visits == 1) {

            $queried_category = get_the_terms($post->ID, 'product_cat')[0];
            $current_user = wp_get_current_user();

            $category = array(
                "customer"      => $current_user->user_email,
                "domain"        => $this->domain,
                "categoryid"    => $queried_category->term_id,
                "name"          => $queried_category->name,
                "link"          => get_category_link($queried_category->term_id),
                'ipaddress'     =>$_SERVER['REMOTE_ADDR'],
                "uniqueid"      => $this->sessionId,
            );
            $result = $this->vboutApp2->Category($category, 1);
        }
    }
    //Function product Search Query
    public function wc_product_search()
    {
        if(get_search_query() != ''){
            if($this->search == 1)
            {
                $searchQuery    = get_search_query();
                $current_user   = wp_get_current_user();
                $ipAddress      = $_SERVER['REMOTE_ADDR'];

                $searchPayload = array(
                    'domain'    => $this->domain,
                    'customer'  => $current_user->user_email,
                    'query'     => $searchQuery,
                    'ipaddress' => $ipAddress,
                    "uniqueid"  => $this->sessionId,
                );
                $this->vboutApp2->sendProductSearch($searchPayload);
            }
        }
    }

    /**
     *Order handling
     */
    public function onOrderCompleted($orderId)
    {
        if ($this->abandoned_carts == 1) {

            $order = wc_get_order($orderId);
            global $woocommerce;
            $html = '';
            $current_user = wp_get_current_user();

            $order = array(
                "cartid"            => $_SESSION['cartID'],
                "uniqueid"          => $this->sessionId,
                'ipaddress'         => $_SERVER['REMOTE_ADDR'],
                "domain"            => $this->domain,
                "orderid"           => $orderId,
                "paymentmethod"     => $order->get_payment_method(),
                "grandtotal"        => $order->get_total(),
                "orderdate"         => strtotime($order->get_date_created()),
                "shippingmethod"    => $order->get_shipping_method(),
                "shippingcost"      => $order->get_shipping_total(),
                "subtotal"          => $order->get_subtotal(),
                "discountvalue"     => $order->get_total_discount(),
                "taxcost"           => $order->get_tax_totals(),
                "otherfeecost"      => $order->get_fees(),
                "currency"          => $order->get_currency(),
                "status"            => $order->get_status(),
                "notes"             => $order->get_customer_note(),
                "storename"         => $_SERVER['HTTP_HOST'],
                "customerinfo"      => array(
                    "firstname"         => $current_user->user_firstname,
                    "lastname"          => $current_user->user_lastname,
                    "email"             => $current_user->user_email,
                    "phone"             => $order->get_billing_phone(),
                    "company"           => $order->get_billing_company(),
                ),
                "billinginfo"       => array(
                    "firstname"         => $order->get_billing_first_name(),
                    "lastname"          => $order->get_billing_last_name(),
                    "email"             => $order->get_billing_email(),
                    "phone"             => $order->get_billing_phone(),
                    "company"           => $order->get_billing_company(),
                    "address"           => $order->get_billing_address_1(),
                    "address2"          => $order->get_billing_address_2(),
                    "city"              => $order->get_billing_city(),
                    "statename"         => $order->get_billing_state(),
                    "countryname"       => $order->get_billing_country(),
                    "zipcode"           => $order->get_billing_postcode(),
                ),
                "shippinginfo"      => array(
                    "firstname"         => $order->get_shipping_first_name(),
                    "lastname"          => $order->get_shipping_last_name(),
                    "email"             => $order->shipping_email,
                    "phone"             => $order->shipping_phone,
                    "company"           => $order->shipping_company,
                    "address"           => $order->shipping_address_1,
                    "address2"          => $order->shipping_address_2,
                    "city"              => $order->shipping_city,
                    "statename"         => $order->shipping_state,
                    "countryname"       => $order->shipping_country,
                    "zipcode"           => $order->shipping_postcode,
                )
            );
            unset($_SESSION['cartID']);
            $result = $this->vboutApp2->Order($order, 1);
        }

    }
    //Order update with status ( From admin side)
    public function wc_order_update( $order )
    {
        if ($this->abandoned_carts == 1) {

            $current_user = get_userdata( $order->get_customer_id() );

            $order = array(
                'ipaddress'         => $_SERVER['REMOTE_ADDR'],
                "domain"            => $this->domain,
                "uniqueid"          => $this->sessionId,
                "orderid"           => $order->get_id(),
                "paymentmethod"     => $order->get_payment_method(),
                "grandtotal"        => $order->get_total(),
                "orderdate"         => strtotime($order->get_date_created()),
                "shippingmethod"    => $order->get_shipping_method(),
                "shippingcost"      => $order->get_shipping_total(),
                "subtotal"          => $order->get_subtotal(),
                "discountvalue"     => $order->get_total_discount(),
                "taxcost"           => $order->get_tax_totals(),
                "otherfeecost"      => $order->get_fees(),
                "currency"          => $order->get_currency(),
                "status"            => $order->get_status(),
                "notes"             => $order->get_customer_note(),
                "storename"         => $_SERVER['HTTP_HOST'],
                "customerinfo"      => array(
                    "firstname"         => $current_user->user_firstname,
                    "lastname"          => $current_user->user_lastname,
                    "email"             => $current_user->user_email,
                    "phone"             => $order->get_billing_phone(),
                    "company"           => $order->get_billing_company(),
                ),
                "billinginfo"       => array(
                    "firstname"         => $order->get_billing_first_name(),
                    "lastname"          => $order->get_billing_last_name(),
                    "email"             => $order->get_billing_email(),
                    "phone"             => $order->get_billing_phone(),
                    "company"           => $order->get_billing_company(),
                    "address"           => $order->get_billing_address_1(),
                    "address2"          => $order->get_billing_address_2(),
                    "city"              => $order->get_billing_city(),
                    "statename"         => $order->get_billing_state(),
                    "countryname"       => $order->get_billing_country(),
                    "zipcode"           => $order->get_billing_postcode(),
                ),
                "shippinginfo"      => array(
                    "firstname"         => $order->get_shipping_first_name(),
                    "lastname"          => $order->get_shipping_last_name(),
                    "email"             => $order->shipping_email,
                    "phone"             => $order->shipping_phone,
                    "company"           => $order->shipping_company,
                    "address"           => $order->shipping_address_1,
                    "address2"          => $order->shipping_address_2,
                    "city"              => $order->shipping_city,
                    "statename"         => $order->shipping_state,
                    "countryname"       => $order->shipping_country,
                    "zipcode"           => $order->shipping_postcode,
                )
            );
            $result = $this->vboutApp2->Order($order, 2);
        }
    }

}