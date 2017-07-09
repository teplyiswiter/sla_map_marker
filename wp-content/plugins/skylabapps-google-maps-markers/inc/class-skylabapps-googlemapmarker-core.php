<?php
if ( ! defined( 'ABSPATH' ) )
  exit;

if ( ! class_exists( 'Skylabapps_GoogleMapMarker_Core' ) ) {
class Skylabapps_GoogleMapMarker_Core {
  const PREFIX     = 'sgmm_';
  const POST_TYPE  = 'sgmm';
  const TAXONOMY   = 'sgmm-category';
  const ZOOM_MIN   = 0;
  const ZOOM_MAX   = 21;
  const DEBUG_MODE = false;
  const VERSION    = '20170907';

  protected static $instance = NULL;

  /**
   * Creates or returns an instance of this class.
   *
   * @return  single instance of this class.
   */
  public static function get_instance() {
    if ( null == self::$instance ) {
      self::$instance = new self;
    }
    return self::$instance;
  }

  /**
   * Constructor
   *
   */
  private function __construct() {

    //add_action( 'init',                  array( $this, 'init'                    ), 8  );   // lower priority so that variables defined here will be available to BGMPSettings class and other init callbacks
	  //add_action( 'init',                  array( $this, 'upgrade'                 )     );
    add_action( 'init',                  array( $this, 'createPostType'          )     );
    add_action( 'init',                  array( $this, 'createCategoryTaxonomy'  )     );
		/*add_action( 'after_setup_theme',     array( $this, 'addFeaturedImageSupport' ), 11 );   // @todo add note explaining why higher priority
		add_action( 'admin_init',            array( $this, 'addMetaBoxes'            )     );*/
		add_action( 'wp',                    array( $this, 'loadResources'           ), 11 );   // @todo - should be wp_enqueue_scripts instead?	// @todo add note explaining why higher priority
		add_action( 'admin_enqueue_scripts', array( $this, 'loadResources'           ), 11 );
		/*add_action( 'wp_head',               array( $this, 'outputHead'              )     );
		add_action( 'admin_notices',         array( $this, 'printMessages'           )     );
		add_action( 'save_post',             array( $this, 'saveCustomFields'        )     );
		add_action( 'wpmu_new_blog',         array( $this, 'activateNewSite'         )     );
		add_action( 'shutdown',              array( $this, 'shutdown'                )     );

		add_filter( 'parse_query', array( $this, 'sortAdminView' ) );*/
		add_shortcode( 'sgmm-map',  array( $this, 'mapShortcode'  ) );
		/*add_shortcode( 'bgmp-list', array( $this, 'listShortcode' ) );*/

		/*register_activation_hook(
			dirname( __FILE__ ) . '/basic-google-maps-placemarks.php',
			array( $this, 'networkActivate' )
		);*/

      require_once( SGMM_PATH . '/inc/class-skylabapps-googlemapmarker-settings.php' );
		//$this->settings = new BGMPSettings();
  }
  /**
		 * Defines the [bgmp-map] shortcode
		 *
		 * @author Ian Dunn <ian@iandunn.name>
		 *
		 * @param array $attributes Array of parameters automatically passed in by WordPress
		 *
		 * @return string The output of the shortcode
		 */
		public function mapShortcode( $attributes ) {
			if ( ! wp_script_is( 'googleMapsAPI', 'queue' ) || ! wp_script_is( 'sgmm', 'queue' ) || ! wp_style_is( self::PREFIX . 'style', 'queue' ) ) {
				$error = sprintf(
					__( '<p class="error">%s error: JavaScript and/or CSS files aren\'t loaded. If you\'re using do_shortcode() you need to add a filter to your theme first. See <a href="%s">the FAQ</a> for details.</p>', 'basic-google-maps-placemarks' ),
					BGMP_NAME,
					'http://wordpress.org/extend/plugins/basic-google-maps-placemarks/faq/'
				);

				// @todo maybe change this to use views/message.php

				return $error;
			}

			if ( isset( $attributes['categories'] ) ) {
				$attributes['categories'] = apply_filters( self::PREFIX . 'mapShortcodeCategories', $attributes['categories'] );
			}   // @todo - deprecated b/c 1.9 output bgmpdata in post; can now just set args in do_shortcode() . also  not consistent w/ shortcode naming scheme and have filter for all arguments now. need a way to notify people

			$attributes = apply_filters( self::PREFIX . 'map-shortcode-arguments', $attributes );   // @todo - deprecated b/c 1.9 output bgmpdata in post...
			$attributes = $this->cleanMapShortcodeArguments( $attributes );

			ob_start();
			do_action( BasicGoogleMapsPlacemarks::PREFIX . 'meta-address-before' );
			require_once( SGMM_PATH . '/inc/views/shortcode-bgmp-map.php' );
			do_action( BasicGoogleMapsPlacemarks::PREFIX . 'shortcode-bgmp-map-after' );
			$output = ob_get_clean();

			return $output;
		}
  protected function getGoogleMapsApiUrl() {
		$url           = 'https://maps.google.com/maps/api/js';
		$urlParameters = array();
		

		if ( ! empty( $this->settings->mapApiKey ) ) {
			$urlParameters['key'] = rawurlencode( $this->settings->mapApiKey );
		}

		$urlParameters = apply_filters( 'sgmm_maps-api-url-parameters', $urlParameters );
		$url           = add_query_arg( $urlParameters, $url );
		return $url;
	}
  /**
   * Registers the custom post type
   *
   */
  public function createPostType() {
    if ( ! post_type_exists( self::POST_TYPE ) ) {
      $singular = apply_filters('cherryisa_faq_label_singular', _x( 'Map Marker', 'post type singular name', 'cherryisa-faq' ));
      $plural = apply_filters('cherryisa_faq_label_plural', _x( 'Map markers', 'post type general name', 'cherryisa-faq' ));
      $labels = array(
        'name'               => $plural,
        'singular_name'      => $singular,
        'menu_name'          => $plural,
        'name_admin_bar'     => $singular,
        'add_new'            => _x( 'Add New', 'question', 'cherryisa-faq' ),
        'add_new_item'       => sprintf(__( 'Add New %s', 'cherryisa-faq' ), $singular),
        'new_item'           => sprintf(__( 'New %s', 'cherryisa-faq' ), $singular),
        'edit_item'          => sprintf(__( 'Edit %s', 'cherryisa-faq' ), $singular),
        'view_item'          => sprintf(__( 'View %s', 'cherryisa-faq' ), $singular),
        'all_items'          => sprintf(__( 'All', 'cherryisa-faq' ) . ' ' . $plural),
        'search_items'       => sprintf(__( 'Search %s', 'cherryisa-faq' ), $plural),
        'parent_item_colon'  => sprintf(__( 'Parent %s', 'cherryisa-faq' ), $plural),
        'not_found'          => sprintf(__( 'No %s found.', 'cherryisa-faq' ), $plural),
        'not_found_in_trash' => sprintf(__( 'No %s found in Trash.', 'cherryisa-faq' ), $plural),
      );

	    $postTypeParams = array(
        'labels'          => $labels,
        'singular_label'  => $singular,
        'public'          => true,
        'menu_position'   => 20,
        'hierarchical'    => false,
        'capability_type' => 'post',
        'rewrite'         => array( 'slug' => 'map_markers', 'with_front' => false ),
        'query_var'       => true,
        //'supports'        => array( 'title', 'editor', 'author', 'thumbnail', 'comments', 'revisions' )
        'supports'        => array( 'title', 'editor', 'thumbnail' )
      );

      register_post_type(
        self::POST_TYPE,
        apply_filters( self::PREFIX . 'post-type-params', $postTypeParams )
      );
    }
  } 

	/**
	 * Registers the category taxonomy
	 *
	 */
	public function createCategoryTaxonomy() {
    if ( ! taxonomy_exists( self::TAXONOMY ) ) {
		  $taxonomyParams = array(
	      'label'                 => __( 'Category', 'basic-google-maps-placemarks' ),
		    'labels'                => array(
		    'name'              => __( 'Categories', 'basic-google-maps-placemarks' ),
		    'singular_name'     => __( 'Category',   'basic-google-maps-placemarks' ),
        ),
		    'hierarchical'          => true,
		    'rewrite'               => array( 'slug' => self::TAXONOMY ),
		    'update_count_callback' => '_update_post_term_count'
      );

		  register_taxonomy(
		    self::TAXONOMY,
		    self::POST_TYPE,
		    apply_filters( self::PREFIX . 'category-taxonomy-params', $taxonomyParams )
		  );
	  }
	}

  /**
	 * Load CSS and JavaScript files
	 *
	 */
	public function loadResources() {
	  wp_register_script(
			'googleMapsAPI',
			$this->getGoogleMapsApiUrl(),
			array(),
			false,
			true
		);

		wp_register_script(
			'markerClusterer',
			plugins_url( 'js/marker-clusterer/markerclusterer_packed.js', __FILE__ ),
			array(),
			'1.0',
			true
		);

		wp_register_script(
			'sgmm',
			plugins_url( 'js/functions.js', __FILE__ ),
			array( 'googleMapsAPI', 'jquery' ),
			self::VERSION,
			true
		);

		wp_register_style(
			self::PREFIX . 'style',
			plugins_url( 'style.css', __FILE__ ),
			false,
			self::VERSION
		);


		wp_enqueue_script( 'googleMapsAPI' );
		wp_enqueue_script( 'markerClusterer' );

		wp_enqueue_script( 'bgmp' );

		wp_enqueue_style( self::PREFIX . 'style' );

		// Load meta box resources for settings page
		/*if ( isset( $_GET['page'] ) && $_GET['page'] == self::PREFIX . 'settings' )	{   // @todo better way than $_GET ?
			wp_enqueue_style( self::PREFIX . 'style' );
			wp_enqueue_script( 'dashboard' );
		}*/
	}

  
}
  
}
?>