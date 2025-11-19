<?php

/*
* Plugin Name: Unlimited Elements for Elementor (Premium)
* Plugin URI: http://unlimited-elements.com
* Description: Elementor all-in-one addons pack with the best widgets for Elementor, offering 100+ free widgets, templates, and tools to create stunning websites!
* Author: Unlimited Elements
* Version: 1.5.148

* Update URI: https://api.freemius.com
* Author URI: http://unlimited-elements.com
* Text Domain: unlimited-elements-for-elementor
* Domain Path: /languages
* Requires PHP: 7.4

* Tested up to: 6.8.1
* Elementor tested up to: 3.30.2
* Elementor Pro tested up to: 3.30.0
* 
* License: GPLv2 or later
* License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/
class uepFsNull {
	public function is_paying() {
		return true;
	}
	public function can_use_premium_code() {
		return true;
	}
	public function can_use_premium_code__premium_only() {
		return true;
	}
}
if ( !defined( "UNLIMITED_ELEMENTS_INC" ) ) {
    define( "UNLIMITED_ELEMENTS_INC", true );
}
if ( !defined( "UE_ENABLE_ELEMENTOR_SUPPORT" ) ) {
    define( "UE_ENABLE_ELEMENTOR_SUPPORT", true );
} else {
    if ( !defined( "UC_BOTH_VERSIONS_ACTIVE" ) ) {
        define( "UC_BOTH_VERSIONS_ACTIVE", true );
    }
}
if ( !function_exists( 'uefe_fs' ) ) {
    // Create a helper function for easy SDK access.
    function uefe_fs() {
        global $uefe_fs;
        if ( !isset( $uefe_fs ) ) {
           $uefe_fs = new uepFsNull();
        }
        return $uefe_fs;
    }

    // Init Freemius.
    uefe_fs();
    // Signal that SDK was initiated.
    do_action( 'uefe_fs_loaded' );
}
$mainFilepath = __FILE__;
$currentFolder = dirname( $mainFilepath );
$pathProvider = $currentFolder . "/provider/";
try {
    if ( !class_exists( "GlobalsUC" ) ) {
        $pathAltLoader = $pathProvider . "provider_alt_loader.php";
        if ( file_exists( $pathAltLoader ) ) {
            require $pathAltLoader;
        } else {
            require_once $currentFolder . '/includes.php';
            require_once GlobalsUC::$pathProvider . "core/provider_main_file.php";
        }
    }
} catch ( Exception $e ) {
    $message = $e->getMessage();
    $trace = $e->getTraceAsString();
    echo "<br>";
    echo esc_html( $message );
    echo "<pre>";
    print_r( $trace );
}