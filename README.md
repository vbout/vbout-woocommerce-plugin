# WooCommerce-Plugin-Vbout
WooCommerce Plugin that link Metadata of orders, carts customers , searches, products and with Integration settings.

## The Plugin has the Following Features :

  - Abandoned Cart Data
  - Search Data 
  - Registering a new customer data
  - Adding a new Product Data ( With variations, Category , price and images , descriptions)
  - Product Visits Data
  - Product Category Visit Data
  - Syncing Customers ( For customer data prior the use of the plugin) 
  - Syncing Product   ( For Product data prior the use of the plugin)
  ## limitations : 
    1 - Upon registration, the only attribute is sent is user email, first name and last name are not sent (this is handeled once this user signs in).
    2 - if the Administrator has many Customers/Products, the sync might take a while , so it needs optimization with cron.php in Magento.
  
## Variations : 
  
 Variations in Magento are handeled as a product with aditional SKU (ammended to the original SKu EX: orginialsky-newsku : xxxx-yyyy). 
 For this they are being handeled as Parent product (origninal SKU and Price ) are added to product Feed, and all the ammendments ( SKKU and Price ) 
 are added in the cart product data.
 
 Variations are sent as an array upon adding a product, syncing a product and viewing a produnct.
 
## Search : 
  
  There is a no observer for hooks activity for this we added a listener/observer for every page load, where we searched if it has Getter 'q'
  if it is present, this means than there is a search query and we handle it.
  
## Orders and Abandonded Carts : 
  
  ### Checkout : 
    
        There is no listener for checkout and does the following  since WooCommerce doesn't allow you to checkout without being registered and logged in first.

  ### Create and Update Cart  : 
          there is a listener for both Cart Update and Cart create and they have the following functionalities : 
            - Get the current logged in customer and it's data. 
            - Create a new cart
            - Products are added ( a loop to handle them ) 

  ### Cart Item Remove : 

        Since the variation is handeled as a product with an independent Product id, upon this function we get the variation id , and the parent ID. we get the variation ( since many products will have the same product id but with different variations) to remove the product id with the same variation ( they will be comapred on VBOUT Server to check if they are the same product or different).

  ### Orders Create and Update : 
      The both have different listeners, they work the same. An Order is added with Shipping and Biling information, alongside with customer's information.
      
      - Updating Cart : 
          - In the process of updating cart, any update to status( Cancelled, Pending, Paid, Shipped/success), details, products is updated directly.

## Customers Add, Update and Sync :
    - Customer email is added upon registration. We get customers data upon loggin in.
    - Customer's sync adds all the users in the system that had previous orders or registered.

## Product Add Update and Sync :
      products are handeled differently in WooCommerce. Every product having a variation, this variation can have different ( considered an independed product but under a parent product ) : 
      - Descritpion
      - Product id
      - Image
      - quantity 
      - price
      - sale price 

      For this the product Price and sale price will be zero only for parent products with Variations, this is because once you create a variation, you are not allowed to put products price, quantity and sale price.

    - Products are added , updated on an Admin page.
    - Product's sync adds all products that are in the system that were added and are still in stock.
    
## IP and Customer Link: 
    - If a customer is roaming the website, and the features are turned on, the IP will be sent at every event. 
    - Upon User registration, all the searches, Cart , product and category views will be linked to this account through IP.
    
      
## Listeners ---- > Functions Used and Explanation :

      - Handeled and called in the frontend
  
                    Listener                              | Function Name               | Descritpion
                    ------------------------------------  | --------------------------- | ------------------------------------------------------------------
                    *__user_register__*                   | `customerRegisterSuccess`   | `This function is to create users on registrations.`
                    ------------------------------------  | --------------------------- | ------------------------------------------------------------------
                    *__woocommerce_after_cart_contents__* | `wc_cart_data`              | This function to Add To Cart Function, that creates a cart and Adds the Items`
                    ------------------------------------  | --------------------------- | ------------------------------------------------------------------
                    *__woocommerce_thankyou__*            | `onOrderCompleted`          | `Create an order with all it's data (After placing an order).`
                    ------------------------------------  | --------------------------- | ------------------------------------------------------------------
                    *__woocommerce_cart_item_removed__*   | `wc_item_remove`            | `Removes cart items from a cart.`
                    ------------------------------------  | --------------------------- | ------------------------------------------------------------------
                    *__woocommerce_after_single_product__*| `wc_product_data`           | `This function to send the product view by the customer or IP address ( then it can be linked)`
                    ------------------------------------  | --------------------------- | ------------------------------------------------------------------
                    *__wp_login__*                        | `wc_customer_update`        | `Create an order with all it's data (After placing an order).`
                    ------------------------------------  | --------------------------- | ------------------------------------------------------------------

                    ------------------------------------  | --------------------------- | ------------------------------------------------------------------
                    *__woocommerce_after_main_content__*  | `wc_category_data`          | `Get's the category of the visited product and sends it.`
                    ------------------------------------  | --------------------------- | ------------------------------------------------------------------
                                 *__pre_get_posts__*      | `wc_product_search`         | `This is a listener sends searched queries.`


        - Handeled and called in the amdin seciton
        
                    Listener                              | Function Name               | Descritpion
               -----------------------------------------  | --------------------------- | ------------------------------------------------------------------
              *__woocommerce_process_product_meta__*      | `wc_product_add`            | `Once a product is added or Updated.`
                    ------------------------------------  | --------------------------- | ------------------------------------------------------------------
                    *__woocommerce_settings_saved__*      | `onSettingsSaved`        | `Customized configuration for integration settings between WooCommerce and Vbout. This also allows the user to control what features they want in the admin/Configuration/Vbout page.`
                    ------------------------------------  | --------------------------- | ------------------------------------------------------------------
     *__woocommerce_admin_order_data_after_order_details__*   | `wc_order_update`           | `When an admin wants to Update order, it updates the order and order status(taking in consideration Billing and Shipping info).`
                    ------------------------------------  | --------------------------- | ------------------------------------------------------------------

