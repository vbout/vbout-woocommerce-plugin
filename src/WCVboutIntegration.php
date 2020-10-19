<?php

namespace App;

use App\Libraries\Vbout\Services\EcommerceWS;
use App\Libraries\Vbout\Services\EmailMarketingWS;

class WCVboutIntegration extends \WC_Integration
{
    private $wcFields = array('first_name', 'last_name', 'country', 'state', 'phone');

    /**
     * WCVboutIntegration constructor.
     */
    public function __construct()
    {
        $this->id = 'vbout-integration';
        $this->method_description = __('WooCommerce Vbout Integration Settings', 'woocommerce-vbout-integration');

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Actions.
        add_action('woocommerce_update_options_integration_' .  $this->id, array($this, 'process_admin_options'));


    }

    /**
     * Initialize form fields
     * @return null
     */
    public function init_form_fields()
    {
        $fields = array();


        $fields['apiKey'] = array(
            'title' => __('API Key', 'woocommerce-vbout-integration'),
            'type' => 'text',
            'description' => __('Use your Vbout API Key. You can find this in your Vbout account > Settings > API & Plugins.', 'woocommerce-vbout-integration'),
            'desc_tip' => true,
            'default' => '',
            'custom_attributes' => array(
                'required' => true
            )
        );

        if ($this->get_option('apiKey') !== '') {
             $domain  = $this->getDomainVBT($this->get_option('apiKey'));
            if ($domain) {
                 $fields['listTitle'] = array(
                    'title' => __('WOOCOMMERCE LISTS', 'woocommerce-vbout-integration'),
                    'type' => 'text',
                    'default' => 'E-commerce List',
                    'css' => 'font-weight: 700; border: 0; background: transparent; box-shadow: none; padding: 0; font-size: 16px;',
                    'custom_attributes' => array(
                        'readonly' => true
                    )
                );
                $fields['domain'] = array(
                    'title' => __('', 'woocommerce-vbout-integration'),
                    'type' => 'text',
                    'default' => ''.$domain,
                    'custom_attributes' => array(
                        'readonly' => true,
                        'hidden' => true,
                    )
                );

                $settings = $this->getSettingsMapField();

                if ($settings) {
                    foreach ($settings as $key => $value) {
                        $fields[$key] = array(
                            'title' => __('', 'woocommerce-vbout-integration'),
                            'type' => 'checkbox',
                            'label' => __($value, 'woocommerce-vbout-integration'),
                            'description' => '',
                            'desc_tip' => false,
                            'default' => 'no',
                            'custom_attributes' => array()
                        );
                    }
                }
            }
        }
        $this->form_fields = $fields;
    }

    /**
     * Sanitize/transform setting values
     * @param $settings
     * @return mixed
     */
    public function sanitize_settings($settings)
    {
        // We're just going to make the api key all upper case characters since that's how our imaginary API works
        if (isset($settings) && isset($settings['apiKey'])) {
            $settings['apiKey'] = strtoupper($settings['apiKey']);
        }

        return $settings;
    }

    /**
     * Get domain code from VBOUT
     * @param $apiKey
     * @return array
     */


    private function getDomainVBT($apiKey)
    {
        $vboutApp = new EcommerceWS(array('api_key' => $apiKey));
        $url = $_SERVER['HTTP_HOST'];

        $url = array(
            'domain' => parse_url($url)['path']
        );
        $domainCode = $vboutApp->getDomain($url);
        $domainCode = is_array($domainCode) ? null : $domainCode;
        return $domainCode;
    }

    /**
     * Added new Settings functionality
     * @return string[]
     */
    private function getSettingsMapField()
    {
        return array(
            'abandoned_carts'       =>  'Abandoned carts (When a checkout/order is created or updated on  WooCommerce) ',
            'search'                =>  'Product Search (When customers search for a specific product on WooCommerce)',
            'product_visits'        =>  'Product Visits (When customers visit a product on WooCommerce)',
            'category_visits'       =>  'Category Visits (When customers\' visit a specific category on WooCommerce )' ,
            'customers'             =>  'Customer data (When customers\' profiles are added or updated on WooCommerce)',
            'current_customers'     =>  'Existing Customers (Syncs customers\' data before installing the plugin on WooCommerce)',
            'product_feed'          =>  'Product data (When products are added or updated on WooCommerce)',
            'sync_current_products' =>  'Existing products (Syncs products data before installing the plugin on WooCommerce)',
        );
    }

}