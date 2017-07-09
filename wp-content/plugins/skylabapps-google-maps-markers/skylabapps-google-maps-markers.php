<?php 
/*
Plugin Name: Skylabapps Google Maps Markers
Description: Embeds a Google Map into your site and lets you add map markers with custom icons and information windows. Each marker can have a different icon.
Version:     1.0.0
Author:      Skylabapps
Text Domain: skylabapps-google-maps-markers
Domain Path: /languages
License:     GPL2
*/

load_plugin_textdomain( 'skylabapps-google-maps-markers', false, basename( dirname( __FILE__ ) ) . '/languages' );

define( 'SGMM_PATH', dirname( __FILE__ ) );

require_once( SGMM_PATH . '/inc/class-skylabapps-googlemapmarker-core.php' );

if ( class_exists( 'Skylabapps_GoogleMapMarker_Core' ) ) {
  
  Skylabapps_GoogleMapMarker_Core::get_instance();
}
?>