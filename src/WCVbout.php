<?php

namespace App;

use App\Libraries\Vbout\Services\EcommerceWS;
use App\Libraries\Vbout\Services\EmailMarketingWS;
use WC_Product;

/**
 * WCVbout class
 *
 */
class WCVbout
{
    private $integrationId;
    private $domain = '';
    private $apiKey;
    private $abandoned_carts = 0;
    private $product_visits = 0;
    private $category_visits = 0;
    private $customers = 0;
    private $product_feed = 0;
    private $marketing = 0;
    private $current_customers = 0;
    private $sync_current_products = 0;
    private $search = 0;
    private $vboutApp2;
    private $cartID;
    private $sessionId;

    /**
     * WCVbout constructor.
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
        } else {
            add_action('wp_enqueue_scripts', array($this, 'loadWPScript'));
//            add_action( 'wp_ajax_updatevboutabandon', array( $this, 'onPostData' )  );
//            add_action( 'wp_ajax_nopriv_updatevboutabandon', array( $this, 'onPostData' ) );
        }

        try {
            // Load action hooks
            add_action('woocommerce_created_customer', array($this, 'onCustomerCreated'), 10, 2);
            add_action('user_register', array($this, 'onCustomerCreated'), 10, 1);

            // Default Woocommerce Case
            add_action('woocommerce_after_cart_contents', array($this, 'wc_cart_data'));
            // WooFunnel Case
            add_action('woocommerce_review_order_after_cart_contents', array($this, 'wc_cart_data'));

            add_action('woocommerce_thankyou', array($this, 'onOrderCompleted'), 10, 1);
            add_action('clear_auth_cookie', array($this, 'onLogout'));
            add_action('woocommerce_settings_saved', array($this, 'onSettingsSaved'));
            add_action('admin_notices', array($this, 'onSettingsNotified'));
            add_action('woocommerce_after_single_product', array($this, 'wc_product_data'), 10);
            add_action('woocommerce_after_main_content', array($this, 'wc_category_data'), 10);
            add_action('woocommerce_cart_item_removed', array($this, 'wc_item_remove'), 10, 2);
            add_action('pre_get_posts', array($this, 'wc_product_search'));
            add_action('wp_login', array($this, 'wc_customer_update'), 99, 2);
            add_action('woocommerce_process_product_meta', array($this, 'wc_product_add'), 12, 1);
            // LearnPress: Sync new added course into VBOUT as product
            if (class_exists('LearnPress')) {
                add_action('save_post', array($this, 'lp_course_add'), 10, 3);
            }
            add_action('woocommerce_before_checkout_process', array($this, 'wc_checkout_add'), 10);

            // Add the tracker
//            add_action('wp_head', 'add_tracker');

            add_action('woocommerce_admin_order_data_after_order_details', array($this, 'wc_order_update'), 10, 1);
            // Register integrations
            add_filter('woocommerce_integrations', array($this, 'addIntegration'));

            add_action('sync_current_customers', array($this, 'syncCurrentCustomers'));
            add_action('sync_current_products', array($this, 'syncCurrentProducts'));

            $this->loadConfig();
            session_start([
                'read_and_close' => true,
            ]);
            // Load email marketing object
            $this->vboutApp2 = new EcommerceWS(array('api_key' => $this->apiKey));
        } catch (\Exception $e) {
            error_log('Caught exception: "' . $e->getMessage() . '" on "' . $e->getFile() . '" line ' . $e->getLine());
        }
    }

    /**
     * Loading assets to create a new Integration tab for WooCommerce
     */

    /**
     * Load VBOUT main.js script
     */
    public function loadScripts()
    {
        wp_register_script(
            'vbout_script',
            plugins_url('assets/js/main.js', dirname(__FILE__)),
            array('jquery')
        );
        wp_enqueue_script('vbout_script');
    }

    /**
     * Load VBOUT load.js script
     */
    public function loadWPScript()
    {
        wp_register_script(
            'vbout_script',
            plugins_url('assets/js/load.js', dirname(__FILE__)),
            array('jquery')
        );
        wp_enqueue_script('vbout_script');
    }

    /**
     * Load VBOUT style
     */
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
     * @param bool $deleteRequest
     * @throws \Exception
     */
    private function loadConfig($deleteRequest = false)
    {
        $WCSettings = get_option('woocommerce_' . $this->integrationId . '_settings');

        if (!$WCSettings) {
            if ($deleteRequest)
                return;
            else
                throw new \Exception('Incomplete settings', 1);
        }

        //New Settings
        if (isset($WCSettings['abandoned_carts']) && $WCSettings['abandoned_carts'] == 'yes')
            $this->abandoned_carts = 1;

        if (isset($WCSettings['product_visits']) && $WCSettings['product_visits'] == 'yes')
            $this->product_visits = 1;

        if (isset($WCSettings['category_visits']) && $WCSettings['category_visits'] == 'yes')
            $this->category_visits = 1;

        if (isset($WCSettings['customers']) && $WCSettings['customers'] == 'yes')
            $this->customers = 1;

        if (isset($WCSettings['product_feed']) && $WCSettings['product_feed'] == 'yes')
            $this->product_feed = 1;

        if (isset($WCSettings['current_customers']) && $WCSettings['current_customers'] == 'yes')
            $this->current_customers = 1;

        if (isset($WCSettings['sync_current_products']) && $WCSettings['sync_current_products'] == 'yes')
            $this->sync_current_products = 1;

        if (isset($WCSettings['search']) && $WCSettings['search'] == 'yes')
            $this->search = 1;

        $this->apiKey = isset($WCSettings['apiKey']) ? $WCSettings['apiKey'] : null;
        $this->domain = isset($WCSettings['domain']) ? $WCSettings['domain'] : null;
        $this->sessionId = $this->wc_unique_id();
    }

    /**
     * Method to add integrations
     * @param $integrations
     * @return mixed
     */
    public function addIntegration($integrations)
    {
        $integrations[] = 'App\WCVboutIntegration';
        return $integrations;
    }

    /**
     * On Settings change, get new feature functionalities
     * @throws \Exception
     */
    public function onSettingsSaved()
    {
        if ($_GET['tab'] === 'integration') {
            $this->loadConfig();

            if ($this->domain != '') {
                $vboutApp = new EcommerceWS(array('api_key' => $this->apiKey));

                $settingsPayload = array(
                    'domain' => $this->domain,
                    'apiname' => 'WooCommerce',
                    'apikey' => $this->apiKey,
                );
                $vboutApp->sendAPIIntegrationCreation($settingsPayload, 1);

                $settings = array(
                    'abandoned_carts' => $this->abandoned_carts,
                    'sync_current_products' => $this->sync_current_products,
                    'search' => $this->search,
                    'product_visits' => $this->product_visits,
                    'category_visits' => $this->category_visits,
                    'customers' => $this->customers,
                    'product_feed' => $this->product_feed,
                    'current_customers' => $this->current_customers,
                    'marketing' => $this->marketing,
                    'domain' => $this->domain,
                    'apiName' => 'WooCommerce',
                );
                $vboutApp->sendSettingsSync($settings);

                if ($this->sync_current_products == 1) {
                    // schedule customers sync after saving settings
                    wp_schedule_single_event(time(), 'sync_current_products');
                }

                if ($this->current_customers == 1) {
                    // schedule customers sync after saving settings
                    wp_schedule_single_event(time(), 'sync_current_customers');
                }
            }
            echo "<script type='text/javascript'>window.location = document.location.href + '&saved=1';</script>";
        }
    }

    /**
     * Create a notification after saving settings
     */
    public function onSettingsNotified()
    {
        if (isset($_GET['saved']) && $_GET['saved']) {
            echo '<div class="updated fade"><p>' . sprintf(__('%sVbout settings saved.%s If settings do not appear below, check your API key and try again. Don\'t have an account? Please click %shere%s.', 'woocommerce-vbout-integration'), '<strong>', '</strong>', '<a href="https://www.vbout.com/pricing/">', '</a>') . '</p></div>' . "\n";
        }
    }

    /**
     * create a unique id in a cookie
     * @return mixed|string
     */
    private function wc_unique_id()
    {
        $sessionId = '';
        if (isset($_COOKIE['vbtEcommerceUniqueId']))
            $sessionId = $_COOKIE['vbtEcommerceUniqueId'];
        return $sessionId;
    }

    /**
     * Sync All users
     */
    public function syncCurrentCustomers()
    {
        $users = get_users(['role__in' => 'customer']);
        if (count($users) > 0) {
            foreach ($users as $user) {
                $userID = $user->data->ID;

                $first_name = get_user_meta($userID, 'first_name', true);
                $last_name = get_user_meta($userID, 'last_name', true);
                $email = $user->data->user_email;
                $phone = get_user_meta($userID, 'billing_phone', true);
                $company = get_user_meta($userID, 'billing_company', true);

                $country_code = get_user_meta($userID, 'billing_country', true);
                $country = $country_code ? WC()->countries->countries[$country_code] : '';

                $state_code = get_user_meta($userID, 'billing_state', true);
                if ($country_code && $state_code) {
                    $states = WC()->countries->get_states($country_code);
                    $state = !empty($states[$state_code]) ? $states[$state_code] : $state_code;
                } else {
                    $state = $state_code;
                }

                $city = get_user_meta($userID, 'billing_city', true);
                $postcode = get_user_meta($userID, 'billing_postcode', true);
                $address_1 = get_user_meta($userID, 'billing_address_1', true);
                $address_2 = get_user_meta($userID, 'billing_address_2', true);

                $customer = array(
                    "firstname" => $first_name,
                    "lastname" => $last_name,
                    "email" => $email,
                    "phone" => $phone,
                    "company" => $company,
                    "country" => $country,
                    "state" => $state,
                    "city" => $city,
                    "postcode" => $postcode,
                    "address_1" => $address_1,
                    "address_2" => $address_2,
                    'api_key' => $this->apiKey,
                    'domain' => $this->domain,
                );
                $this->vboutApp2->Customer($customer, 1);
            }
        }
    }

    /**
     * Sync All Products
     */
    public function syncCurrentProducts()
    {

        $productFound = false;

        $wooArgs = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        );

        # Woocommerce Product
        $loop = new \WP_Query($wooArgs);
        if ($loop->have_posts()) {
            $productFound = true;
            foreach ($loop->posts as $productPost) {
                $productID = $productPost->ID;
                $productCategoryID = 'N/A';
                $productCategoryName = 'N/A';
                $product = wc_get_product($productPost->ID);
                $productName = $product->get_name();
                $productDescription = $product->get_description();
                $productSku = $product->get_sku();
                $productPrice = ($product->get_regular_price()) ? $product->get_regular_price() : (($product->get_price()) ? $product->get_price() : '0.0');
                $productDiscountPrice = $product->get_sale_price() ? $product->get_sale_price() : '0.0';
                $terms = get_the_terms($productID, 'product_cat');
                if (count($terms) > 0) {
                    $productCategoryID = $terms[0]->term_id;
                    $productCategoryName = $terms[0]->name;
                }

                $productData = array(
                    "productid" => $productID,
                    "name" => $productName,
                    "price" => $productPrice,
                    "description" => $productDescription,
                    "discountprice" => $productDiscountPrice,
                    "currency" => get_woocommerce_currency(),
                    "sku" => $productSku,
                    "categoryid" => $productCategoryID,
                    "category" => $productCategoryName,
                    "link" => get_permalink($productID),
                    "image" => get_the_post_thumbnail_url($productID, 'full'),
                    'api_key' => $this->apiKey,
                    'domain' => $this->domain,
                );
                $this->vboutApp2->Product($productData, 1);
            }
        }

        # LearnPress Courses
        if (class_exists('LearnPress')) {
            $LpArgs = array(
                'post_type' => 'lp_course',
                'post_status' => 'publish',
                'posts_per_page' => -1,
            );
            $loop = new \WP_Query($LpArgs);
            if ($loop->have_posts()) {
                $productFound = true;
                foreach ($loop->posts as $productPost) {
                    $productID = $productPost->ID;
                    $productName = $productPost->post_title;
                    $productDescription = $productPost->post_content;
                    $productSku = null;
                    $productPrice = (get_post_meta($productID, '_lp_price', true)) ? get_post_meta($productID, '_lp_price', true) : '0.0';
                    $productDiscountPrice = (get_post_meta($productID, '_lp_sale_price', true)) ? get_post_meta($productID, '_lp_sale_price', true) : '0.0';

                    $productCategoryID = 'N/A';
                    $productCategoryName = 'N/A';
                    $terms = get_the_terms($productID, 'course_category');
                    if (count($terms) > 0) {
                        $productCategoryID = $terms[0]->term_id;
                        $productCategoryName = $terms[0]->name;
                    }

                    $productData = array(
                        "productid" => $productID,
                        "name" => $productName,
                        "price" => $productPrice,
                        "description" => $productDescription,
                        "discountprice" => $productDiscountPrice,
                        "currency" => get_woocommerce_currency(),
                        "sku" => $productSku,
                        "categoryid" => $productCategoryID,
                        "category" => $productCategoryName,
                        "link" => get_permalink($productID),
                        "image" => get_the_post_thumbnail_url($productID, 'full'),
                        'api_key' => $this->apiKey,
                        'domain' => $this->domain,
                    );
                    $this->vboutApp2->Product($productData, 1);
                }
            }
        }

        if (!$productFound) {
            echo __('No products found');
        }
        wp_reset_postdata();

    }

    /**
     * Uninstalling the Plugin ( Deletion )
     * @throws \Exception
     */
    public function uninstall()
    {
        // Load configurations
        $this->loadConfig(true);
        $vboutApp = new EcommerceWS(array('api_key' => $this->apiKey));
        if ($this->domain != '') {
            $settingsPayload = array(
                'domain' => $this->domain,
                'apiname' => 'WooCommerce',
                'api_key' => $this->apiKey,
            );
            $vboutApp->sendAPIIntegrationCreation($settingsPayload, 3);
        }
        delete_option('woocommerce_' . $this->integrationId . '_settings');
        delete_transient('vbout_update_' . vbout_woocommerce_slug);
    }


    /**
     * Cart Handling
     */

    /**
     * Get Cart Data with Cart Item
     */
    public function wc_cart_data()
    {
        if ($this->abandoned_carts == 1) {
            global $woocommerce;
            $products = $woocommerce->cart->get_cart();

            $current_user = wp_get_current_user();
            if (isset($_SESSION['cartID']))
                $this->cartID = $_SESSION['cartID'];
            else {
                $this->cartID = sha1(mt_rand(1, 90000) . 'SALT');
                $_SESSION['cartID'] = $this->cartID;
            }
            $store = array(
                "domain" => $this->domain,
                "cartcurrency" => get_woocommerce_currency(),
                "cartid" => $this->cartID,
                'ipaddress' => $this->getClientIPAddress(),
                "customer" => $current_user->user_email,
                "storename" => $_SERVER['HTTP_HOST'],
                "abandonurl" => $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'],
                "uniqueid" => $this->sessionId,
            );
            $this->vboutApp2->Cart($store, 1);

            // Get Cart Items
            foreach ($products as $product) {
                $productID = $product['product_id'];
                $productQuantity = $product['quantity'];
                $productVariations = array();
                $categoryID = '';
                $categoryName = '';
                $image = '';
                try {
                    $productObj = new WC_Product($productID);
                    $productName = $productObj->get_name();
                    $productDescription = $productObj->get_description();
                    $productSku = $productObj->get_sku();
                    $productPrice = ($productObj->get_price()) ? $productObj->get_price() : '0.0';
                    $productDiscountPrice = ($productObj->get_sale_price()) ? $productObj->get_sale_price() : '0.0';

                    $variationID = $product['variation_id'];
                    if ($variationID != 0) {
                        $variationObj = wc_get_product($variationID);
                        if ($variationObj) {
                            $productVariations = $variationObj->get_attributes();
                            foreach ($productVariations as $key => $productVariation) {
                                if (strpos($key, 'pa_') !== false) {
                                    $cleanAttributeKey = explode('pa_', $key)[1];
                                    $productVariations[$cleanAttributeKey] = $productVariation;
                                    unset($productVariations[$key]);
                                }
                            }
                            $imageID = $variationObj->get_image_id();
                            $image = wp_get_attachment_image_url($imageID, 'full');
                        }
                    } else {
                        $image = get_the_post_thumbnail_url($productID, 'full');
                    }

                    $category = get_the_terms($productID, 'product_cat');
                } catch (\Exception $ex) {
                    // Non Woocommerce Products
                    # LearPress
                    $postType = get_post_type($productID);
                    if (class_exists('LearnPress') && $postType == 'lp_course') {
                        $productObj = $product['data']->post;
                        $productName = $productObj->post_title;
                        $productDescription = $productObj->post_content;
                        $productSku = null;
                        $productDiscountPrice = get_post_meta($productID, '_lp_sale_price', true);
                        if ($productDiscountPrice) {
                            $productPrice = $productDiscountPrice;
                        } else {
                            $productDiscountPrice = '0.0';
                            $productPrice = (get_post_meta($productID, '_lp_price', true)) ? get_post_meta($productID, '_lp_price', true) : '0.0';
                        }
                        $image = get_the_post_thumbnail_url($productID, 'full');
                        $category = get_the_terms($productID, 'course_category');
                    }
                }

                if (is_array($category)) {
                    $categoryID = $category[0]->term_id;
                    $categoryName = $category[0]->name;
                }

                $productData = array(
                    "domain" => $this->domain,
                    "cartid" => $this->cartID,
                    "productid" => (string)$productID,
                    "name" => $productName,
                    "price" => $productPrice,
                    "description" => $productDescription,
                    "discountprice" => $productDiscountPrice,
                    "currency" => get_woocommerce_currency(),
                    "quantity" => (string)$productQuantity,
                    "categoryid" => $categoryID,
                    "variation" => $productVariations,
                    "category" => $categoryName,
                    "sku" => $productSku,
                    "link" => get_permalink($productID),
                    "image" => $image,
                    "uniqueid" => $this->sessionId,
                );
                $this->vboutApp2->CartItem($productData, 1);
            }
        }
    }

    /**
     * Remove item from cart
     * @param $removed_cart_item_key
     * @param $cart
     */
    public function wc_item_remove($removed_cart_item_key, $cart)
    {
        $productVariations = array();
        $productID = $cart->removed_cart_contents[$removed_cart_item_key]['product_id'];
        $variationID = $cart->removed_cart_contents[$removed_cart_item_key]['variation_id'];

        if ($variationID != 0) {
            $variationObj = wc_get_product($variationID);
            if ($variationObj) {
                $productVariations = $variationObj->get_attributes();
                foreach ($productVariations as $key => $productVariation) {
                    if (strpos($key, 'pa_') !== false) {
                        $cleanAttributeKey = explode('pa_', $key)[1];
                        $productVariations[$cleanAttributeKey] = $productVariation;
                        unset($productVariations[$key]);
                    }
                }
            }
        }

        $item = array(
            "cartid" => $_SESSION['cartID'],
            "domain" => $this->domain,
            "productid" => $productID,
            "variation" => $productVariations,
        );
        $this->vboutApp2->CartItem($item, 3);
    }

    /**
     * Creates a contact after a customer is created
     * @param $user_id
     */
    public function onCustomerCreated($user_id)
    {
        $customer = get_userdata($user_id);
        $email = $customer->data->user_email;

        if (isset($_POST['billing_first_name']) || isset($_POST['billing_last_name'])) {
            $first_name = isset($_POST['billing_first_name']) ? $_POST['billing_first_name'] : '';
            $last_name = isset($_POST['billing_last_name']) ? $_POST['billing_last_name'] : '';
        } else {
            $first_name = isset($_POST['first_name']) ? $_POST['first_name'] : '';
            $last_name = isset($_POST['last_name']) ? $_POST['last_name'] : '';
        }

        $phone = isset($_POST['billing_phone']) ? $_POST['billing_phone'] : '';
        $company = isset($_POST['billing_company']) ? $_POST['billing_company'] : '';

        $country_code = isset($_POST['billing_country']) ? $_POST['billing_country'] : '';
        $country = $country_code ? WC()->countries->countries[$country_code] : '';

        $state_code = isset($_POST['billing_state']) ? $_POST['billing_state'] : '';
        if ($country_code && $state_code) {
            $states = WC()->countries->get_states($country_code);
            $state = !empty($states[$state_code]) ? $states[$state_code] : $state_code;
        } else {
            $state = $state_code;
        }

        $city = isset($_POST['billing_city']) ? $_POST['billing_city'] : '';
        $postcode = isset($_POST['billing_postcode']) ? $_POST['billing_postcode'] : '';
        $address_1 = isset($_POST['billing_address_1']) ? $_POST['billing_address_1'] : '';
        $address_2 = isset($_POST['billing_address_2']) ? $_POST['billing_address_2'] : '';

        $customer = array(
            "firstname" => $first_name,
            "lastname" => $last_name,
            "email" => $email,
            "phone" => $phone,
            "company" => $company,
            "country" => $country,
            "state" => $state,
            "city" => $city,
            "postcode" => $postcode,
            "address_1" => $address_1,
            "address_2" => $address_2,
            'api_key' => $this->apiKey,
            'domain' => $this->domain,
            'ipaddress' => $this->getClientIPAddress(),
            "uniqueid" => $this->sessionId,
        );

        $this->vboutApp2->Customer($customer, 1);
    }

    /**
     * Update customer on Login
     * @param $user_login
     * @param $user
     */
    public function wc_customer_update($user_login, $user)
    {
        if ($this->customers == 1) {
            if (in_array('customer', $user->roles)) {
                $userID = $user->data->ID;

                $first_name = get_user_meta($userID, 'first_name', true);
                $last_name = get_user_meta($userID, 'last_name', true);
                $email = $user->data->user_email;
                $phone = get_user_meta($userID, 'billing_phone', true);
                $company = get_user_meta($userID, 'billing_company', true);

                $country_code = get_user_meta($userID, 'billing_country', true);
                $country = $country_code ? WC()->countries->countries[$country_code] : '';

                $state_code = get_user_meta($userID, 'billing_state', true);
                if ($country_code && $state_code) {
                    $states = WC()->countries->get_states($country_code);
                    $state = !empty($states[$state_code]) ? $states[$state_code] : $state_code;
                } else {
                    $state = $state_code;
                }

                $city = get_user_meta($userID, 'billing_city', true);
                $postcode = get_user_meta($userID, 'billing_postcode', true);
                $address_1 = get_user_meta($userID, 'billing_address_1', true);
                $address_2 = get_user_meta($userID, 'billing_address_2', true);

                $customer = array(
                    "firstname" => $first_name,
                    "lastname" => $last_name,
                    "email" => $email,
                    "phone" => $phone,
                    "company" => $company,
                    "country" => $country,
                    "state" => $state,
                    "city" => $city,
                    "postcode" => $postcode,
                    "address_1" => $address_1,
                    "address_2" => $address_2,
                    'api_key' => $this->apiKey,
                    'domain' => $this->domain,
                );
                $this->vboutApp2->Customer($customer, 1);
            }
        }
    }

    /**
     * On Logout update customer data
     */
    public function onLogout()
    {

    }

    /**
     * Product Handling
     */

    /**
     * Add a product
     * @param $post_id
     */
    public function wc_product_add($post_id)
    {
        if ($this->product_feed == 1) {
            $product = wc_get_product($post_id);
            $productID = $product->get_id();
            $productName = $product->get_name();
            $productDescription = $product->get_description();
            $productSku = $product->get_sku();
            $productPrice = ($product->get_regular_price()) ? $product->get_regular_price() : (($product->get_price()) ? $product->get_price() : '0.0');
            $productDiscountPrice = $product->get_sale_price() ? $product->get_sale_price() : '0.0';

            $productCategoryID = 'N/A';
            $productCategoryName = 'N/A';
            $terms = get_the_terms($productID, 'product_cat');
            if (count($terms) > 0) {
                $productCategoryID = $terms[0]->term_id;
                $productCategoryName = $terms[0]->name;
            }

            $productData = array(
                "productid" => $productID,
                "name" => $productName,
                "price" => $productPrice,
                "description" => $productDescription,
                "discountprice" => $productDiscountPrice,
                "currency" => get_woocommerce_currency(),
                "sku" => $productSku,
                "categoryid" => $productCategoryID,
                "category" => $productCategoryName,
                "link" => get_permalink($productID),
                "image" => get_the_post_thumbnail_url($productID, 'full'),
                'api_key' => $this->apiKey,
                'domain' => $this->domain,
            );
            $this->vboutApp2->Product($productData, 1);
        }
    }

    public function lp_course_add($post_id, $post, $update)
    {
        if ($this->product_feed == 1) {
            if ($post->post_status == 'publish' && $post->post_type == 'lp_course') {
                $productID = $post_id;
                $productObj = $post;
                $productName = $productObj->post_title;
                $productDescription = $productObj->post_content;
                $productSku = null;
                $productPrice = (get_post_meta($productID, '_lp_price', true)) ? get_post_meta($productID, '_lp_price', true) : '0.0';
                $productDiscountPrice = (get_post_meta($productID, '_lp_sale_price', true)) ? get_post_meta($productID, '_lp_sale_price', true) : '0.0';

                $productCategoryID = 'N/A';
                $productCategoryName = 'N/A';
                $terms = get_the_terms($productID, 'course_category');
                if (count($terms) > 0) {
                    $productCategoryID = $terms[0]->term_id;
                    $productCategoryName = $terms[0]->name;
                }

                $productData = array(
                    "productid" => $productID,
                    "name" => $productName,
                    "price" => $productPrice,
                    "description" => $productDescription,
                    "discountprice" => $productDiscountPrice,
                    "currency" => get_woocommerce_currency(),
                    "sku" => $productSku,
                    "categoryid" => $productCategoryID,
                    "category" => $productCategoryName,
                    "link" => get_permalink($productID),
                    "image" => get_the_post_thumbnail_url($productID, 'full'),
                    'api_key' => $this->apiKey,
                    'domain' => $this->domain,
                );
                $this->vboutApp2->Product($productData, 1);
            }
        }
    }

    /**
     * Products View Function
     */
    public function wc_product_data()
    {
        if ($this->product_visits == 1) {
            global $product;

            $productID = $product->get_id();
            $productName = $product->get_name();
            $productDescription = $product->get_description();
            $productSku = $product->get_sku();
            $productPrice = ($product->get_regular_price()) ? $product->get_regular_price() : (($product->get_price()) ? $product->get_price() : '0.0');
            $productDiscountPrice = ($product->get_sale_price()) ? $product->get_sale_price() : '0.0';
            $productCategoryID = (string)$product->get_category_ids()[0];
            $productCategoryName = get_the_category_by_ID($product->get_category_ids()[0]);

            $current_user = wp_get_current_user();

            $productData = array(
                "customer" => $current_user->user_email,
                "productid" => $productID,
                "name" => $productName,
                "price" => $productPrice,
                "description" => $productDescription,
                "discountprice" => $productDiscountPrice,
                "currency" => get_woocommerce_currency(),
                "sku" => $productSku,
                'ipaddress' => $this->getClientIPAddress(),
                "categoryid" => $productCategoryID,
                "category" => $productCategoryName,
                "link" => get_permalink($productID),
                "image" => get_the_post_thumbnail_url($productID, 'full'),
                "domain" => $this->domain,
                "uniqueid" => $this->sessionId,
            );
            $this->vboutApp2->Product($productData, 1);
        }
    }

    /**
     * Function Category
     */
    public function wc_category_data()
    {

        if ($this->category_visits == 1) {
            global $product;
            $queried_category = get_the_terms($product->get_id(), 'product_cat')[0];
            $current_user = wp_get_current_user();

            $category = array(
                "customer" => $current_user->user_email,
                "domain" => $this->domain,
                "categoryid" => $queried_category->term_id,
                "name" => $queried_category->name,
                "link" => get_category_link($queried_category->term_id),
                'ipaddress' => $this->getClientIPAddress(),
                "uniqueid" => $this->sessionId,
            );
            $result = $this->vboutApp2->Category($category, 1);
        }
    }

    /**
     * Function product Search Query
     * @param $query
     */
    public function wc_product_search($query)
    {
        if ($this->search == 1) {
            if (!is_admin() && $query->is_main_query()) {
                if ($query->is_search) {
                    $searchQuery = get_search_query();
                    $current_user = wp_get_current_user();
                    $ipAddress = $this->getClientIPAddress();

                    $searchPayload = array(
                        'domain' => $this->domain,
                        'customer' => $current_user->user_email,
                        'query' => $searchQuery,
                        'ipaddress' => $ipAddress,
                        'uniqueid' => $this->sessionId,
                    );
                    $this->vboutApp2->sendProductSearch($searchPayload);
                }
            }
        }
    }

    /**
     *Order handling
     * @param $orderId
     */
    public function onOrderCompleted($orderId)
    {
        if ($this->abandoned_carts == 1) {

            $order = wc_get_order($orderId);

            //if Checkout add user guest
            if ($_SESSION['checkout_bool']) {
                $customer = array(
                    "firstname" => $order->get_billing_first_name(),
                    "lastname" => $order->get_billing_last_name(),
                    "email" => $order->get_billing_email(),
                    "phone" => $order->get_billing_phone(),
                    "company" => $order->get_billing_company(),
                    "country" => $order->get_billing_country(),
                    "state" => $order->get_billing_state(),
                    "city" => $order->get_billing_city(),
                    "postcode" => $order->get_billing_postcode(),
                    "address_1" => $order->get_billing_address_1(),
                    "address_2" => $order->get_billing_address_2(),
                    'api_key' => $this->apiKey,
                    'domain' => $this->domain,
                    'ipaddress' => $this->getClientIPAddress(),
                    "uniqueid" => $this->sessionId,
                );
                $this->vboutApp2->Customer($customer, 1);
                unset($_SESSION['checkout_bool']);
            }

            $order = array(
                "cartid" => $_SESSION['cartID'],
                "uniqueid" => $this->sessionId,
                'ipaddress' => $this->getClientIPAddress(),
                "domain" => $this->domain,
                "orderid" => $orderId,
                "paymentmethod" => $order->get_payment_method(),
                "grandtotal" => $order->get_total(),
                "orderdate" => strtotime($order->get_date_created()),
                "shippingmethod" => $order->get_shipping_method(),
                "shippingcost" => $order->get_shipping_total(),
                "subtotal" => $order->get_subtotal(),
                "discountvalue" => $order->get_total_discount(),
                "taxcost" => $order->get_tax_totals(),
                "otherfeecost" => $order->get_fees(),
                "currency" => $order->get_currency(),
                "status" => $order->get_status(),
                "notes" => $order->get_customer_note(),
                "storename" => $_SERVER['HTTP_HOST'],
                "customerinfo" => array(
                    "firstname" => $order->get_billing_first_name(),
                    "lastname" => $order->get_billing_last_name(),
                    "email" => $order->get_billing_email(),
                    "phone" => $order->get_billing_phone(),
                    "company" => $order->get_billing_company(),
                ),
                "billinginfo" => array(
                    "firstname" => $order->get_billing_first_name(),
                    "lastname" => $order->get_billing_last_name(),
                    "email" => $order->get_billing_email(),
                    "phone" => $order->get_billing_phone(),
                    "company" => $order->get_billing_company(),
                    "address" => $order->get_billing_address_1(),
                    "address2" => $order->get_billing_address_2(),
                    "city" => $order->get_billing_city(),
                    "statename" => $order->get_billing_state(),
                    "countryname" => $order->get_billing_country(),
                    "zipcode" => $order->get_billing_postcode(),
                ),
                "shippinginfo" => array(
                    "firstname" => $order->get_shipping_first_name(),
                    "lastname" => $order->get_shipping_last_name(),
                    "email" => $order->get_billing_email(),
                    "phone" => $order->get_billing_phone(),
                    "company" => $order->get_shipping_company(),
                    "address" => $order->get_shipping_address_1(),
                    "address2" => $order->get_shipping_address_2(),
                    "city" => $order->get_shipping_city(),
                    "statename" => $order->get_shipping_state(),
                    "countryname" => $order->get_shipping_country(),
                    "zipcode" => $order->get_shipping_postcode(),
                )
            );
            unset($_SESSION['cartID']);
            $this->vboutApp2->Order($order, 1);
        }
    }

    /**
     * Order update with status ( From admin side)
     * @param $order
     */
    public function wc_order_update($order)
    {
        if ($this->abandoned_carts == 1) {

            if ($order->get_customer_id()) {
                $current_user = get_userdata($order->get_customer_id());
                $firstName = $current_user->user_firstname;
                $lastName = $current_user->user_lastname;
                $email = $current_user->user_email;
            } else {
                $firstName = $order->get_billing_first_name();
                $lastName = $order->get_billing_last_name();
                $email = $order->get_billing_email();
            }

            $order = array(
                "domain" => $this->domain,
                "orderid" => $order->get_id(),
                "paymentmethod" => $order->get_payment_method(),
                "grandtotal" => $order->get_total(),
                "orderdate" => strtotime($order->get_date_created()),
                "shippingmethod" => $order->get_shipping_method(),
                "shippingcost" => $order->get_shipping_total(),
                "subtotal" => $order->get_subtotal(),
                "discountvalue" => $order->get_total_discount(),
                "taxcost" => $order->get_tax_totals(),
                "otherfeecost" => $order->get_fees(),
                "currency" => $order->get_currency(),
                "status" => $order->get_status(),
                "notes" => $order->get_customer_note(),
                "storename" => $_SERVER['HTTP_HOST'],
                "customerinfo" => array(
                    "firstname" => $firstName,
                    "lastname" => $lastName,
                    "email" => $email,
                    "phone" => $order->get_billing_phone(),
                    "company" => $order->get_billing_company(),
                ),
                "billinginfo" => array(
                    "firstname" => $order->get_billing_first_name(),
                    "lastname" => $order->get_billing_last_name(),
                    "email" => $order->get_billing_email(),
                    "phone" => $order->get_billing_phone(),
                    "company" => $order->get_billing_company(),
                    "address" => $order->get_billing_address_1(),
                    "address2" => $order->get_billing_address_2(),
                    "city" => $order->get_billing_city(),
                    "statename" => $order->get_billing_state(),
                    "countryname" => $order->get_billing_country(),
                    "zipcode" => $order->get_billing_postcode(),
                ),
                "shippinginfo" => array(
                    "firstname" => $order->get_shipping_first_name(),
                    "lastname" => $order->get_shipping_last_name(),
                    "email" => $order->get_billing_email(),
                    "phone" => $order->get_billing_phone(),
                    "company" => $order->get_shipping_company(),
                    "address" => $order->get_shipping_address_1(),
                    "address2" => $order->get_shipping_address_2(),
                    "city" => $order->get_shipping_city(),
                    "statename" => $order->get_shipping_state(),
                    "countryname" => $order->get_shipping_country(),
                    "zipcode" => $order->get_shipping_postcode(),
                )
            );
            $this->vboutApp2->Order($order, 2);
        }
    }

    /**
     * Checkout
     */
    public function wc_checkout_add()
    {
        if ($this->abandoned_carts == 1) {

            global $woocommerce;
            $products = $woocommerce->cart->get_cart();

            $current_user = wp_get_current_user();
            if (isset($_SESSION['cartID']))
                $this->cartID = $_SESSION['cartID'];
            else {
                $this->cartID = sha1(mt_rand(1, 90000) . 'SALT');
                $_SESSION['cartID'] = $this->cartID;
            }

            // Checkout ?
            $_SESSION['checkout_bool'] = true;

            $store = array(
                "domain" => $this->domain,
                "cartcurrency" => get_woocommerce_currency(),
                "cartid" => $this->cartID,
                'ipaddress' => $this->getClientIPAddress(),
                "customer" => $current_user->user_email,
                "storename" => $_SERVER['HTTP_HOST'],
                "abandonurl" => $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'],
                "uniqueid" => $this->sessionId,
            );
            $this->vboutApp2->Cart($store, 1);

            // Get Cart Items
            foreach ($products as $product) {
                $productID = $product['product_id'];
                $productQuantity = $product['quantity'];
                $productVariations = array();
                $categoryID = '';
                $categoryName = '';
                $image = '';
                try {
                    $productObj = new WC_Product($productID);
                    $productName = $productObj->get_name();
                    $productDescription = $productObj->get_description();
                    $productSku = $productObj->get_sku();
                    $productPrice = ($productObj->get_price()) ? $productObj->get_price() : '0.0';
                    $productDiscountPrice = ($productObj->get_sale_price()) ? $productObj->get_sale_price() : '0.0';

                    $variationID = $product['variation_id'];
                    if ($variationID != 0) {
                        $variationObj = wc_get_product($variationID);
                        if ($variationObj) {
                            $productVariations = $variationObj->get_attributes();
                            foreach ($productVariations as $key => $productVariation) {
                                if (strpos($key, 'pa_') !== false) {
                                    $cleanAttributeKey = explode('pa_', $key)[1];
                                    $productVariations[$cleanAttributeKey] = $productVariation;
                                    unset($productVariations[$key]);
                                }
                            }
                            $imageID = $variationObj->get_image_id();
                            $image = wp_get_attachment_image_url($imageID, 'full');
                        }
                    } else {
                        $image = get_the_post_thumbnail_url($productID, 'full');
                    }

                    $category = get_the_terms($productID, 'product_cat');
                } catch (\Exception $ex) {
                    // Non Woocommerce Products
                    # LearPress
                    $postType = get_post_type($productID);
                    if (class_exists('LearnPress') && $postType == 'lp_course') {
                        $productObj = $product['data']->post;
                        $productName = $productObj->post_title;
                        $productDescription = $productObj->post_content;
                        $productSku = null;
                        $productDiscountPrice = get_post_meta($productID, '_lp_sale_price', true);
                        if ($productDiscountPrice) {
                            $productPrice = $productDiscountPrice;
                        } else {
                            $productDiscountPrice = '0.0';
                            $productPrice = (get_post_meta($productID, '_lp_price', true)) ? get_post_meta($productID, '_lp_price', true) : '0.0';
                        }
                        $image = get_the_post_thumbnail_url($productID, 'full');
                        $category = get_the_terms($productID, 'course_category');
                    }
                }

                if (is_array($category)) {
                    $categoryID = $category[0]->term_id;
                    $categoryName = $category[0]->name;
                }

                $productData = array(
                    "domain" => $this->domain,
                    "cartid" => $this->cartID,
                    "productid" => (string)$productID,
                    "name" => $productName,
                    "price" => $productPrice,
                    "description" => $productDescription,
                    "discountprice" => $productDiscountPrice,
                    "currency" => get_woocommerce_currency(),
                    "quantity" => (string)$productQuantity,
                    "categoryid" => $categoryID,
                    "variation" => $productVariations,
                    "category" => $categoryName,
                    "sku" => $productSku,
                    "link" => get_permalink($productID),
                    "image" => $image,
                    "uniqueid" => $this->sessionId,
                );
                $this->vboutApp2->CartItem($productData, 1);
            }
        }
    }

    /**
     * Get Client IP
     * @return mixed
     */
    private function getClientIPAddress()
    {
        // Whether IP is from shared internet
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } // Whether IP is from proxy
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } // Whether IP is from remote address
        else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }
}