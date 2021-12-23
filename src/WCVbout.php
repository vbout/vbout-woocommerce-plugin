<?php

namespace App;

use App\Libraries\Vbout\Services\EcommerceWS;
use \WC_Geolocation;

/**
 * WCVbout class
 *
 */
class WCVbout
{
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
    private $sync_current_orders = 0;
    private $search = 0;
    private $vboutApp2;
    private $cartID;
    private $sessionId;

    private $flags = array();

    /**
     * WCVbout constructor.
     */
    public function __construct()
    {
        add_action('plugins_loaded', array($this, 'init'));

		add_filter( 'plugin_action_links_' . VBOUT_WOOCOMMERCE_PLUGIN_BASE, array( $this, 'plugin_action_links' ) );
    }

    /**
     * Entry point of the application
     * @return null
     */
    public function init()
    {
        if ( !empty($_GET['tab']) && $_GET['tab'] === 'integration') {
            add_action('admin_enqueue_scripts', array($this, 'loadScripts'));
            add_action('admin_enqueue_scripts', array($this, 'loadStyles'));
        }
        else {
            add_action('wp_enqueue_scripts', array($this, 'loadWPScript'));
//            add_action( 'wp_ajax_updatevboutabandon', array( $this, 'onPostData' )  );
//            add_action( 'wp_ajax_nopriv_updatevboutabandon', array( $this, 'onPostData' ) );
        }

        try {
            // Load action hooks
            add_action('woocommerce_created_customer', array($this, 'onCustomerCreated'), 10, 2);

            add_action('woocommerce_add_to_cart', array($this, 'wc_sync_cart_item_data'));
            add_action('woocommerce_after_cart_item_quantity_update', array($this, 'wc_sync_cart_item_data'));
			add_action('woocommerce_cart_item_removed', array($this, 'wc_cart_item_removed'), 10, 2);
			add_action('woocommerce_before_checkout_process', array($this, 'wc_before_checkout_process'), 10);
            add_action('woocommerce_checkout_order_created', array($this, 'wc_checkout_order_created'), 10, 1);

            add_action('woocommerce_settings_save_' . 'integration', array($this, 'onSettingsSaved'));
            add_action('woocommerce_after_single_product', array($this, 'wc_product_data'), 10);
            add_action('woocommerce_after_main_content', array($this, 'wc_category_data'), 10);
            add_action('woocommerce_process_product_meta', array($this, 'wc_product_add'), 12, 1);

			add_action('user_register', array($this, 'onCustomerCreated'), 10, 1);
			add_action('clear_auth_cookie', array($this, 'onLogout'));
			add_action('admin_notices', array($this, 'onSettingsNotified'));
			add_action('pre_get_posts', array($this, 'wc_product_search'));
			add_action('wp_login', array($this, 'wc_customer_update'), 99, 2);

            // LearnPress: Sync new added course into VBOUT as product
            if (class_exists('LearnPress')) {
                add_action('save_post', array($this, 'lp_course_add'), 10, 3);
            }

            // Add the tracker
//            add_action('wp_head', 'add_tracker');

            add_action('woocommerce_admin_order_data_after_order_details', array($this, 'wc_order_update'), 10, 1);
            // Register integrations
            add_filter('woocommerce_integrations', array($this, 'addIntegration'));

            add_action('sync_current_customers', array($this, 'syncCurrentCustomers'));
            add_action('sync_current_products', array($this, 'syncCurrentProducts'));
            add_action('sync_current_orders', array($this, 'syncCurrentOrders'));

            $this->loadConfig();

			if(!isset($_SESSION)) {
				session_start();
			}

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
    private function loadConfig($deleteRequest = false, $createNewSettings = false)
    {
        $WCSettings = get_option('woocommerce_' . VBOUT_WOOCOMMERCE_INTEGRATION_ID . '_settings');

        if (!$WCSettings) {
            if ($deleteRequest) {
				return;
			}
            if( !$createNewSettings ) {
				throw new \Exception('Incomplete settings', 1);
			}

			$WCSettings = array();
        }

        //New Settings
        if (isset($WCSettings['abandoned_carts']) && $WCSettings['abandoned_carts'] == 'yes') {
			$this->abandoned_carts = 1;
		}

        if (isset($WCSettings['product_visits']) && $WCSettings['product_visits'] == 'yes') {
			$this->product_visits = 1;
		}

        if (isset($WCSettings['category_visits']) && $WCSettings['category_visits'] == 'yes') {
			$this->category_visits = 1;
		}

        if (isset($WCSettings['customers']) && $WCSettings['customers'] == 'yes') {
			$this->customers = 1;
		}

        if (isset($WCSettings['product_feed']) && $WCSettings['product_feed'] == 'yes') {
			$this->product_feed = 1;
		}

        if (isset($WCSettings['current_customers']) && $WCSettings['current_customers'] == 'yes') {
			$this->current_customers = 1;
		}

        if (isset($WCSettings['sync_current_products']) && $WCSettings['sync_current_products'] == 'yes') {
			$this->sync_current_products = 1;
		}

        if (isset($WCSettings['sync_current_orders']) && $WCSettings['sync_current_orders'] == 'yes') {
			$this->sync_current_orders = 1;
		}

        if (isset($WCSettings['search']) && $WCSettings['search'] == 'yes') {
			$this->search = 1;
		}

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
    public function onSettingsSaved() {
    	if ( !(!empty($_GET['section']) && $_GET['section'] === VBOUT_WOOCOMMERCE_INTEGRATION_ID)) {
			return;
		}

		$this->loadConfig(false, true);

		if ( !empty($this->domain) ) {
			$vboutApp = new EcommerceWS(array('api_key' => $this->apiKey));

			$settingsPayload = array(
				'domain' => $this->domain,
				'apiname' => 'WooCommerce',
				'apikey' => $this->apiKey,
			);
			$vboutApp->sendAPIIntegrationCreation($settingsPayload, 1);

			$settings = array(
				'abandoned_carts' => $this->abandoned_carts,
				'search' => $this->search,
				'product_visits' => $this->product_visits,
				'category_visits' => $this->category_visits,
				'customers' => $this->customers,
				'product_feed' => $this->product_feed,
				'current_customers' => $this->current_customers,
				'sync_current_products' => $this->sync_current_products,
				'sync_current_orders' => $this->sync_current_orders,
				'marketing' => $this->marketing,
				'domain' => $this->domain,
				'apiName' => 'WooCommerce',
			);
			$vboutApp->sendSettingsSync($settings);

			// Check if we are in sync progress
			$syncSettings = WCVboutSync::getSyncSettings();

			if ($this->current_customers == 1) {
				if( empty($syncSettings['customers']['status']) ) {
					// schedule customers sync after saving settings
					wp_schedule_single_event(time(), 'sync_current_customers');
				}
			}

			if ($this->sync_current_products == 1) {
				if( empty($syncSettings['products']['status']) ) {
					// schedule products sync after saving settings
					wp_schedule_single_event(time(), 'sync_current_products');
				}
			}

			if ($this->sync_current_orders == 1) {
				if( empty($syncSettings['orders']['status']) ) {
					// schedule orders sync after saving settings
					wp_schedule_single_event(time(), 'sync_current_orders');
				}
			}
		}

		echo "<script type='text/javascript'>window.location = document.location.href + '&saved=1';</script>";
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
    public function syncCurrentCustomers() {
		WCVboutSync::SyncAllCustomers( $this->domain, $this->apiKey );
    }

    /**
     * Sync All Products
     */
    public function syncCurrentProducts() {
		$products = WCVboutSync::SyncAllProducts( $this->domain, $this->apiKey );

        if( empty( $products ) ) {
            echo __('No products found');
        }

        wp_reset_postdata();
    }

    /**
     * Sync All Orders
     */
    public function syncCurrentOrders() {
update_option('walid_debug', array(
	'$orders' => [$this->domain, $this->apiKey],
));
		WCVboutSync::SyncAllOrders( $this->domain, $this->apiKey );
    }

    /**
     * Uninstalling the Plugin ( Deletion )
     * @throws \Exception
     */
    public function uninstall()
    {
        // Load configurations
        $this->loadConfig(true);

        if ($this->domain != '') {
			$vboutApp = new EcommerceWS(array('api_key' => $this->apiKey));

            $settingsPayload = array(
                'domain' => $this->domain,
                'apiname' => 'WooCommerce',
                'api_key' => $this->apiKey,
            );
            $vboutApp->sendAPIIntegrationCreation($settingsPayload, 3);
        }

        delete_option('woocommerce_' . VBOUT_WOOCOMMERCE_INTEGRATION_ID . '_settings');
		delete_option('woocommerce_' . VBOUT_WOOCOMMERCE_INTEGRATION_ID . '_sync_settings');
        delete_transient('vbout_update_' . VBOUT_WOOCOMMERCE_SLUG);
    }


    /**
     * Cart Handling
     */

    /**
     * Get Cart Data with Cart Item
     */
    public function wc_sync_cart_item_data($cart_item_key) {

        if ($this->abandoned_carts == 1) {
			$cart_item     = WC()->cart->get_cart_item( $cart_item_key );

			$cartItemData = WCVboutSyncData::getCartItemData( $cart_item );

			$cartItemData['create-cart'] = 'yes';
			$cartItemData['domain'] = $this->domain;
			$cartItemData['cartid'] = $this->getCartId();
			$cartItemData['uniqueid'] = $this->sessionId;
			$cartItemData['ipaddress'] = $this->getClientIPAddress();
			$cartItemData['cartcurrency'] = get_woocommerce_currency();
			$cartItemData['storename'] = $_SERVER['HTTP_HOST'];
			$cartItemData['abandonurl'] = $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];

			// attach customer info
			self::attachCurrentCustomerInfoData( $cartItemData );

			$this->vboutApp2->CartItem($cartItemData, 1);
        }
    }

    /**
     * Remove item from cart
     * @param $removed_cart_item_key
     * @param $cart
     */
    public function wc_cart_item_removed($removed_cart_item_key, $cart) {

        $cartId = $this->getCartId( false );

		if ( $cartId ) {
			$productVariations = array();
			$productID = $cart->removed_cart_contents[$removed_cart_item_key]['product_id'];
			$variationID = $cart->removed_cart_contents[$removed_cart_item_key]['variation_id'];

			if ($variationID != 0) {
				$variations = WCVboutSyncData::getCartItemVariations( $variationID, false );

				$productVariations = $variations['variations'];
			}

			$item = array(
				"cartid" => $cartId,
				"domain" => $this->domain,
				"productid" => $productID,
				"variation" => $productVariations,
			);

			$this->vboutApp2->CartItem($item, 3);
		}
    }

    /**
     * Creates a contact after a customer is created
     * @param $user_id
     */
    public function onCustomerCreated($user_id)
    {
    	if( !empty( $flags['customerCreated'] )) {
    		return ;
		}
		if ($this->customers == 1) {

			$this->flags['customerCreated'] = true;

			$customer = get_userdata($user_id);
			$email = $customer->data->user_email;

			$form_data = $this->getDataFromFormDataPost();

			// Part 1, get the data from POST

			$first_last_name = $this->getDataFromPost(true,
				array($form_data, ['first_name', 'last_name']),
				array($_POST, ['billing_first_name', 'billing_last_name']),
				array($_POST, ['first_name', 'last_name'], true)
			);

			$phone = $this->getDataFromPost(false,
				array($form_data, ['billing_phone']),
				array($_POST, ['billing_phone'], true)
			);

			$company = $this->getDataFromPost(false,
				array($form_data, ['billing_company']),
				array($_POST, ['billing_company'], true)
			);

			$country_code = $this->getDataFromPost(false,
				array($form_data, ['billing_country']),
				array($_POST, ['billing_country'], true)
			);

			// Part 2, adjust the data

			$first_name = $first_last_name ? $first_last_name[0] : $customer->user_firstname;
			$last_name = $first_last_name ? $first_last_name[1] : $customer->user_lastname;

			if ( empty( $phone ) ) {
				$phone = get_user_meta($user_id, 'phone_number', true);
				if (empty($phone)) {
					$phone = get_user_meta($user_id, 'user_phone', true);
				}
				if (empty($phone)) {
					$phone = get_user_meta($user_id, 'phone', true);
				}
			}

			$customer = array(
				'firstname' => $first_name,
				'lastname' => $last_name,
				'email' => $email,
				'phone' => $phone,
				'company' => $company,
				'country_code' => $country_code,
			);

			WCVboutSyncData::attachCountryStateNames($customer, 'country_code', 'country');

			$customer['domain'] = $this->domain;
			$customer['uniqueid'] = $this->sessionId;
			$customer['ipaddress'] = $this->getClientIPAddress();

			$this->vboutApp2->Customer($customer, 1);
		}
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

				$customer = WCVboutSyncData::getCustomerInfoData( $user->data, null, false );

				$customer['domain'] = $this->domain;

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
            $productCategoryIDs = $product->get_category_ids();
            $productCategoryID = (string) !empty($productCategoryIDs) ? $productCategoryIDs[0] : 0;
            $productCategoryName = !empty($productCategoryID) ? get_the_category_by_ID( $productCategoryID ) : '';

            $productData = array(
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

			// attach customer info
			self::attachCurrentCustomerEmailAddress( $productData );

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
            if ($product != NULL) {
                $product_cat = get_the_terms($product->get_id(), 'product_cat');

				if( !empty($product_cat) ) {
					$queried_category = $product_cat[0];

					$category = array(
						"domain" => $this->domain,
						"categoryid" => $queried_category->term_id,
						"name" => $queried_category->name,
						"link" => get_category_link($queried_category->term_id),
						'ipaddress' => $this->getClientIPAddress(),
						"uniqueid" => $this->sessionId,
					);

					// attach customer info
					self::attachCurrentCustomerEmailAddress( $category );

					$result = $this->vboutApp2->Category($category, 1);
				}
            }
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
                    $ipAddress = $this->getClientIPAddress();

                    $searchPayload = array(
                        'domain' => $this->domain,
                        'query' => $searchQuery,
                        'ipaddress' => $ipAddress,
                        'uniqueid' => $this->sessionId,
                    );

					// attach customer info
					self::attachCurrentCustomerEmailAddress( $searchPayload );

                    $this->vboutApp2->sendProductSearch($searchPayload);
                }
            }
        }
    }

    /**
     *Order handling
     * @param $orderId
     */
    public function wc_checkout_order_created($order)
    {
        if ($this->abandoned_carts == 1) {

			$orderData = WCVboutSyncData::getOrderData( $order, true );

			$orderData['domain'] = $this->domain;
			$orderData['cartid'] = $this->getCartId();
			$orderData['uniqueid'] = $this->sessionId;
			$orderData['ipaddress'] = $this->getClientIPAddress();

			$this->unsetCartId();

            $this->vboutApp2->Order($orderData, 1);
        }
    }

    /**
     * Order update with status ( From admin side)
     * @param $order
     */
    public function wc_order_update($order)
    {
        if ($this->abandoned_carts == 1) {

			$orderData = WCVboutSyncData::getOrderData( $order, true );

			$orderData['domain'] = $this->domain;

            $this->vboutApp2->Order($orderData, 2);
        }
    }

	/**
	 * sync the whole cart to VBOUT just before creating the order
	 */
    public function wc_before_checkout_process() {
        if ($this->abandoned_carts == 1) {

			$cart_items     = WC()->cart->get_cart();

			$cart = array(
                'domain' => $this->domain,
                'cartcurrency' => get_woocommerce_currency(),
                'cartid' => $this->getCartId(),
                'ipaddress' => $this->getClientIPAddress(),
                'storename' => $_SERVER['HTTP_HOST'],
                'abandonurl' => $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'],
                'uniqueid' => $this->sessionId,
				'cartproducts'	=> array(),
            );

            // Get Cart Items
            foreach ($cart_items as $cart_item) {
				$cartItemData = WCVboutSyncData::getCartItemData( $cart_item );

				$cart['cartproducts'][] = $cartItemData;
            }

			// attach customer info
			self::attachCurrentCustomerInfoData( $cartItemData );

			$this->vboutApp2->CartUpsert( $cart );
        }
    }

    private static function attachCurrentCustomerInfoData(array &$dataArray) {
		// get current user
		$current_user = wp_get_current_user();

		// try to get customer info from current user and order
		$customerInfo = WCVboutSyncData::getCustomerInfoData( $current_user, null, false );

		// lets attach those information if we have an email address
		if( !empty($customerInfo['email'] )) {
			$dataArray['customerinfo'] = $customerInfo;
			$dataArray['customer'] = $customerInfo['email'];
		}
	}

    private static function attachCurrentCustomerEmailAddress(array &$dataArray) {
		// get current user
		$current_user = wp_get_current_user();

		// lets attach the email address if we have it
		if( $current_user && !empty( $current_user->user_email )) {
			$dataArray['customer'] = $current_user->user_email;
		}
	}

	/**
	 * Show action links on the plugin screen.
	 *
	 * @param mixed $links Plugin Action links.
	 *
	 * @return array
	 */
	public static function plugin_action_links( $links ) {
		$action_links = array(
			'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=integration&section=' . VBOUT_WOOCOMMERCE_INTEGRATION_ID ) . '" aria-label="' . esc_attr__( 'View VBOUT WooCommerce Plugin settings', 'vbout-woocommerce' ) . '">' . esc_html__( 'Settings', 'vbout-woocommerce' ) . '</a>',
		);

		return array_merge( $action_links, $links );
	}

    /**
     * Get Client IP
     * @return mixed
     */
    private function getClientIPAddress() {
        // Whether IP is from shared internet
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
        }

        return WC_Geolocation::get_ip_address();
    }

    private function getDataFromFormDataPost() {
		$data = array();

    	if( !empty( $_POST['form_data'] ) ) {
			$form_data = $_POST['form_data'];
			if( !is_array( $form_data ) ) {
				$form_data = json_decode($_POST['form_data'], true );

				if( !is_array( $form_data ) ) {
					$form_data = json_decode(stripslashes( $_POST['form_data'] ), true );
				}

				if( !is_array( $form_data ) ) {
					$form_data = array();
				}
			}

			foreach( $form_data as $row) {
				if(is_array($row) && !empty( $row['field_name'] ) ) {
					$field_name = $row['field_name'];
					switch($field_name) {
						case 'first_name':
						case 'last_name':
						case 'user_email':
						case 'billing_company':
						case 'billing_phone':
						case 'billing_address_1':
						case 'billing_address_2':
						case 'billing_country':
						case 'billing_state':
						case 'billing_city':
						case 'billing_postcode':
							$data[ $field_name ] = array_key_exists('value', $row) ? $row['value'] : null;
							break;
					}
				}
			}
		}

    	return $data;
	}

	private function getCartId( $createId = true ) {
		$vbtCartId = WC()->session->get('vbtCartID');

		if (is_null($vbtCartId) && $createId) {
			$vbtCartId = sha1(mt_rand(1, 90000) . 'SALT');

			WC()->session->set('vbtCartID', $vbtCartId);
		}

		return $vbtCartId;
	}

	private function unsetCartId() {
		WC()->session->set('vbtCartID', null);
	}

	private function getDataFromPost($twoValues) {
    	$args = func_get_args();

		$twoValues = !empty( array_shift($args) );

    	foreach($args as $arg) {
			$data = $arg[0];
			$fields = $arg[1];
			$extraSearch = !empty($arg[2]);

			if ($twoValues && ( !empty($data[ $fields[0] ]) || !empty($data[ $fields[1] ])) ) {
				return array(
					0 => !empty($data[ $fields[0] ]) ? $data[ $fields[0] ] : '',
					1 => !empty($data[ $fields[1] ]) ? $data[ $fields[1] ] : '',
				);
			}
			else if ( !empty($data[ $fields[0] ]) ) {
				return $data[ $fields[0] ];
			}

			// should we do postfix search?
			if($extraSearch) {

				// check if we have "form_id" and it is numeric
				if( !empty($data['form_id']) && is_numeric($data['form_id'])) {
					$postFix = '-' . $data['form_id'];

					if ($twoValues && ( !empty($data[ $fields[0] . $postFix ]) || !empty($data[ $fields[1] . $postFix ])) ) {
						return array(
							0 => !empty($data[ $fields[0] . $postFix ]) ? $data[ $fields[0] . $postFix ] : '',
							1 => !empty($data[ $fields[1] . $postFix ]) ? $data[ $fields[1] . $postFix ] : '',
						);
					}
					else if ( !empty($data[ $fields[0] . $postFix ]) ) {
						return $data[ $fields[0] . $postFix ];
					}
				}
			}
		}

		if ( $twoValues ) {
			return array();
		}
		return '';
	}
}
