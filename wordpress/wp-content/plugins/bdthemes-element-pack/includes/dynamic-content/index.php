<?php

namespace ElementPack\Includes\DynamicContent;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class ElementPack_Dynamic_Content
{

    /**
     * List of dynamic tags
     * Add new tags here to automatically register them
     */
    private $dynamic_tags_text = [
        // Post Tags
        'post-title' => 'ElementPack_Dynamic_Tag_Post_Title',
        'post-id' => 'ElementPack_Dynamic_Tag_Post_ID',
        'post-slug' => 'ElementPack_Dynamic_Tag_Post_Slug',
        'post-date' => 'ElementPack_Dynamic_Tag_Post_Date',
        'post-time' => 'ElementPack_Dynamic_Tag_Post_Time',
        'post-author' => 'ElementPack_Dynamic_Tag_Post_Author',
        'post-comments' => 'ElementPack_Dynamic_Tag_Post_Comments',
        'post-excerpt' => 'ElementPack_Dynamic_Tag_Post_Excerpt',
        'post-terms' => 'ElementPack_Dynamic_Tag_Post_Terms',
        'post-status' => 'ElementPack_Dynamic_Tag_Post_Status',
        'post-type' => 'ElementPack_Dynamic_Tag_Post_Type',
        'post-custom-field' => 'ElementPack_Dynamic_Tag_Post_Custom_Field',
        'post-featured-image' => 'ElementPack_Dynamic_Tag_Post_Featured_Image',
        // Site Tags
        'site-title' => 'ElementPack_Dynamic_Tag_Site_Title',
        'site-tagline' => 'ElementPack_Dynamic_Tag_Site_Tagline',
        'current-date-time' => 'ElementPack_Dynamic_Tag_Current_Date_Time',
        'request-parameter' => 'ElementPack_Dynamic_Tag_Request_Parameter',
        'shortcode' => 'ElementPack_Dynamic_Tag_Shortcode',
        // Archive Tags
        'archive-title' => 'ElementPack_Dynamic_Tag_Archive_Title',
        'archive-description' => 'ElementPack_Dynamic_Tag_Archive_Description',
        'archive-meta' => 'ElementPack_Dynamic_Tag_Archive_Meta',
        // Term Tags
        'term-id' => 'ElementPack_Dynamic_Tag_Term_ID',
        'term-title' => 'ElementPack_Dynamic_Tag_Term_Title',
        'term-description' => 'ElementPack_Dynamic_Tag_Term_Description',
        'term-slug' => 'ElementPack_Dynamic_Tag_Term_Slug',
        'term-count' => 'ElementPack_Dynamic_Tag_Term_Count',
        'term-meta' => 'ElementPack_Dynamic_Tag_Term_Meta',
        // User Tags
        'user-info' => 'ElementPack_Dynamic_Tag_User_Info',
        'user-meta' => 'ElementPack_Dynamic_Tag_User_Meta',
        // Search
        'search-query' => 'ElementPack_Dynamic_Tag_Search_Query',
        'search-results-count' => 'ElementPack_Dynamic_Tag_Search_Results_Count',
    ];

    private $dynamic_tags_url = [
        // Post Tags
        'post-url' => 'ElementPack_Dynamic_Tag_Post_URL',
        'post-terms-url' => 'ElementPack_Dynamic_Tag_Post_Terms_URL',
        'post-author-url' => 'ElementPack_Dynamic_Tag_Post_Author_URL',
        'post-comments-url' => 'ElementPack_Dynamic_Tag_Post_Comments_URL',
        'post-featured-image-url' => 'ElementPack_Dynamic_Tag_Post_Featured_Image_URL',
        'post-navigation-url' => 'ElementPack_Dynamic_Tag_Post_Navigation_URL',
        // Archive Tags
        'archive-url' => 'ElementPack_Dynamic_Tag_Archive_URL',
        // Site Tags
        'site-url' => 'ElementPack_Dynamic_Tag_Site_URL',
        // Term Tags
        'term-url' => 'ElementPack_Dynamic_Tag_Term_URL',
        // User Tags
        'user-url' => 'ElementPack_Dynamic_Tag_User_URL',
        'login-logout-url' => 'ElementPack_Dynamic_Tag_Login_Logout_URL',
        // Media Tags
        'attachment-url' => 'ElementPack_Dynamic_Tag_Attachment_URL',
        
    ];

    private $dynamic_tags_image = [
        // Post Tags
        'post-featured-image' => 'ElementPack_Dynamic_Tag_Post_Featured_Image_Data',
        'post-author-avatar' => 'ElementPack_Dynamic_Tag_Post_Author_Avatar',
        'post-custom-field-image' => 'ElementPack_Dynamic_Tag_Post_Custom_Field_Image',

        // Archive Tags
        'archive-meta-image' => 'ElementPack_Dynamic_Tag_Archive_Meta_Image',

        // Site Tags
        'site-logo' => 'ElementPack_Dynamic_Tag_Site_Logo',
        'site-icon' => 'ElementPack_Dynamic_Tag_Site_Icon',
        // User Tags
        'user-avatar' => 'ElementPack_Dynamic_Tag_User_Avatar',
    ];

    private $dynamic_tags_woocommerce_text = [
        // Product Tags
        'product-attribute' => 'ElementPack_Dynamic_Tag_Product_Attribute',
        'product-description' => 'ElementPack_Dynamic_Tag_Product_Description',
        'product-price' => 'ElementPack_Dynamic_Tag_Product_Price',
        'product-purchase-note' => 'ElementPack_Dynamic_Tag_Product_Purchase_Note',
        'product-rating' => 'ElementPack_Dynamic_Tag_Product_Rating',
        'product-sale' => 'ElementPack_Dynamic_Tag_Product_Sale',
        'product-shipping' => 'ElementPack_Dynamic_Tag_Product_Shipping',
        'product-sku' => 'ElementPack_Dynamic_Tag_Product_SKU',
        'product-stock' => 'ElementPack_Dynamic_Tag_Product_Stock',
        'product-terms' => 'ElementPack_Dynamic_Tag_Product_Terms',
        'product-title' => 'ElementPack_Dynamic_Tag_Product_Title',
        'product-type' => 'ElementPack_Dynamic_Tag_Product_Type',
    ];
    private $dynamic_tags_woocommerce_url = [
        'product-url' => 'ElementPack_Dynamic_Tag_Product_URL',
        'product-add-to-cart-url' => 'ElementPack_Dynamic_Tag_Product_Add_To_Cart_URL',
        'product-term-url' => 'ElementPack_Dynamic_Tag_Product_Term_URL',
        'product-review-url' => 'ElementPack_Dynamic_Tag_Product_Review_URL',
        'product-checkout-url' => 'ElementPack_Dynamic_Tag_Product_Checkout_URL',
        'product-back-to-shop-url' => 'ElementPack_Dynamic_Tag_Product_Back_To_Shop_URL',
        'product-archive-url' => 'ElementPack_Dynamic_Tag_Product_Archive_URL',
        'product-term-archive-url' => 'ElementPack_Dynamic_Tag_Product_Term_Archive_URL',
    ];

    private $dynamic_tags_woocommerce_image = [
        'product-featured-image' => 'ElementPack_Dynamic_Tag_Product_Featured_Image',
        'product-gallery-image' => 'ElementPack_Dynamic_Tag_Product_Gallery_Image',
        'product-term-image' => 'ElementPack_Dynamic_Tag_Product_Term_Image',
    ];
    
    public function __construct()
    {
        add_action('elementor/dynamic_tags/register', [$this, 'register_dynamic_tag_group'], 1);
        add_action('elementor/dynamic_tags/register', [$this, 'register_dynamic_tag']);
    }

    public function register_dynamic_tag_group($dynamic_tags_manager)
    {
        $dynamic_tags_manager->register_group(
            'element-pack-post',
            [
                'title' => esc_html__('Element Pack - Post', 'bdthemes-element-pack')
            ]
        );
        
        $dynamic_tags_manager->register_group(
            'element-pack-site',
            [
                'title' => esc_html__('Element Pack - Site', 'bdthemes-element-pack')
            ]
        );

        $dynamic_tags_manager->register_group(
            'element-pack-archive',
            [
                'title' => esc_html__('Element Pack - Archive', 'bdthemes-element-pack')
            ]
        );

        $dynamic_tags_manager->register_group(
            'element-pack-term',
            [
                'title' => esc_html__('Element Pack - Term', 'bdthemes-element-pack')
            ]
        );

        $dynamic_tags_manager->register_group(
            'element-pack-user',
            [
                'title' => esc_html__('Element Pack - User', 'bdthemes-element-pack')
            ]
        );

        $dynamic_tags_manager->register_group(
            'element-pack-search',
            [
                'title' => esc_html__('Element Pack - Search', 'bdthemes-element-pack')
            ]
        );

        $dynamic_tags_manager->register_group(
            'element-pack-media',
            [
                'title' => esc_html__('Element Pack - Media', 'bdthemes-element-pack')
            ]
        );

        if (class_exists('WooCommerce')) {
            $dynamic_tags_manager->register_group(
                'element-pack-woocommerce',
                [
                    'title' => esc_html__('Element Pack - WooCommerce', 'bdthemes-element-pack')
                ]
            );
        }
    }

    public function register_dynamic_tag($dynamic_tags_manager)
    {
        require_once(BDTEP_PATH . 'includes/dynamic-content/utils-trait.php');
        $this->register_text_tags($dynamic_tags_manager);
        $this->register_url_tags($dynamic_tags_manager);
        $this->register_image_tags($dynamic_tags_manager);
        $this->register_woocommerce_text_tags($dynamic_tags_manager);
        $this->register_woocommerce_url_tags($dynamic_tags_manager);
        $this->register_woocommerce_image_tags($dynamic_tags_manager);
    }

    private function register_text_tags($dynamic_tags_manager)
    {
        foreach ($this->dynamic_tags_text as $tag => $class) {
            // Include the tag file
            $file = BDTEP_PATH . 'includes/dynamic-content/tags/text/' . $tag . '.php';
            if (file_exists($file)) {
                require_once($file);
                if (class_exists($class)) {
                    $dynamic_tags_manager->register(new $class());
                }
            }
        }
    }

    private function register_url_tags($dynamic_tags_manager)
    {
        foreach ($this->dynamic_tags_url as $tag => $class) {
            $file = BDTEP_PATH . 'includes/dynamic-content/tags/url/' . $tag . '.php';
            if (file_exists($file)) {
                require_once($file);
                if (class_exists($class)) {
                    $dynamic_tags_manager->register(new $class());
                }
            }
        }
    }

    private function register_image_tags($dynamic_tags_manager)
    {
        foreach ($this->dynamic_tags_image as $tag => $class) {
            $file = BDTEP_PATH . 'includes/dynamic-content/tags/image/' . $tag . '.php';
            if (file_exists($file)) {
                require_once($file);
                if (class_exists($class)) {
                    $dynamic_tags_manager->register(new $class());
                }
            }
        }
    }

    private function register_woocommerce_text_tags($dynamic_tags_manager)
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        foreach ($this->dynamic_tags_woocommerce_text as $tag => $class) {
            $file = BDTEP_PATH . 'includes/dynamic-content/tags/woocommerce/text/' . $tag . '.php';
            if (file_exists($file)) {
                require_once($file);
                if (class_exists($class)) {
                    $dynamic_tags_manager->register(new $class());
                }
            }
        }
    }

    private function register_woocommerce_url_tags($dynamic_tags_manager)
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        foreach ($this->dynamic_tags_woocommerce_url as $tag => $class) {
            $file = BDTEP_PATH . 'includes/dynamic-content/tags/woocommerce/url/' . $tag . '.php';
            if (file_exists($file)) {
                require_once($file);
                if (class_exists($class)) {
                    $dynamic_tags_manager->register(new $class());
                }
            }
        }
    }

    private function register_woocommerce_image_tags($dynamic_tags_manager)
    {
        if (!class_exists('WooCommerce')) {
            return;
        }   

        foreach ($this->dynamic_tags_woocommerce_image as $tag => $class) {
            $file = BDTEP_PATH . 'includes/dynamic-content/tags/woocommerce/image/' . $tag . '.php';
            if (file_exists($file)) {
                require_once($file);

                if (class_exists($class)) {
                    $dynamic_tags_manager->register(new $class());
                }
            }
        }
    }
}

new ElementPack_Dynamic_Content();
