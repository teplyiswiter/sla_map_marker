<?php
if ( ! defined( 'ABSPATH' ) )
  exit;

if ( ! class_exists( 'Skylabapps_GoogleMapMarker_Core' ) ) {
class Skylabapps_GoogleMapMarker_Core {

	protected $settings, $options, $updatedOptions, $userMessageCount, $mapShortcodeCalled, $mapShortcodeCategories;
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
		  $this->settings = new SGMM_Settings();
  }
	/**
		 * Validates and cleans the map shortcode arguments
		 *
		 * @author Ian Dunn <ian@iandunn.name>
		 *
		 * @param array
		 *
		 * @return array
		 */
		protected function cleanMapShortcodeArguments( $arguments ) {
			// @todo - not doing this in settings yet, but should. want to make sure it's DRY when you do.
			// @todo - Any errors generated in there would stack up until admin loads page, then they'll all be displayed, include  ones from geocode() etc. that's not great solution, but is there better way?
			// maybe add a check to enqueuemessage() to make sure that messages doesn't already exist. that way there'd only be 1 of them. if do that, make sure to fix the bug where they're getting adding twice before, b/c this would mask that
			// maybe call getMapShortcodeArguments() when saving post so they get immediate feedback about any errors in shortcode
			// do something similar for list shortcode arguments?

			global $post;

			if ( ! is_array( $arguments ) ) {
				return array();
			}


			// Placemark
			if ( isset( $arguments['placemark'] ) ) {
				$pass       = true;
				$originalID = $arguments['placemark'];

				// Check for valid placemark ID
				if ( ! is_numeric( $arguments['placemark'] ) ) {
					$pass = false;
				}

				$arguments['placemark'] = (int) $arguments['placemark'];

				if ( $arguments['placemark'] <= 0 ) {
					$pass = false;
				}

				$placemark = get_post( $arguments['placemark'] );
				if ( ! $placemark ) {
					$pass = false;
				}

				if ( ! $pass ) {
					$error = sprintf(
						__( '%s shortcode error: %s is not a valid placemark ID.', 'basic-google-maps-placemarks' ),
						SGMM_NAME,
						is_scalar( $originalID ) ? (string) $originalID : gettype( $originalID )
					);
				}

				// Check for valid coordinates
				if ( $pass ) {
					$latitude    = get_post_meta( $arguments['placemark'], self::PREFIX . 'latitude', true );
					$longitude   = get_post_meta( $arguments['placemark'], self::PREFIX . 'longitude', true );
					$coordinates = $this->validateCoordinates( $latitude . ',' . $longitude );

					if ( $coordinates === false ) {
						$pass  = false;
						$error = sprintf(
							__( '%s shortcode error: %s does not have a valid address.', 'basic-google-maps-placemarks' ),
							SGMM_NAME,
							(string) $originalID
						);
					}
				}


				// Remove the option if it isn't a valid placemark
				if ( ! $pass ) {
					$this->enqueueMessage( $error, 'error' );
					unset( $arguments['placemark'] );
				}
			}


			// Categories
			if ( isset( $arguments['categories'] ) ) {
				if ( is_string( $arguments['categories'] ) ) {
					$arguments['categories'] = explode( ',', $arguments['categories'] );
				} elseif ( ! is_array( $arguments['categories'] ) || empty( $arguments['categories'] ) ) {
					unset( $arguments['categories'] );
				}

				if ( isset( $arguments['categories'] ) && ! empty( $arguments['categories'] ) ) {
					foreach ( $arguments['categories'] as $index => $term ) {
						if ( ! term_exists( $term, self::TAXONOMY ) ) {
							unset( $arguments['categories'][ $index ] );    // Note - This will leave holes in the key sequence, but it doesn't look like that's a problem with the way we're using it.
							$this->enqueueMessage( sprintf(
								__( '%s shortcode error: %s is not a valid category.', 'basic-google-maps-placemarks' ),
								BGMP_NAME,
								$term
							), 'error' );
						}
					}
				}
			}

			// Rename width and height keys to match internal ones. Using different ones in shortcode to make it easier for user.
			if ( isset( $arguments['width'] ) ) {
				if ( is_numeric( $arguments['width'] ) && $arguments['width'] > 0 ) {
					$arguments['mapWidth'] = $arguments['width'];
				} else {
					$this->enqueueMessage( sprintf(
						__( '%s shortcode error: %s is not a valid width.', 'basic-google-maps-placemarks' ),
						BGMP_NAME,
						$arguments['width']
					), 'error' );
				}

				unset( $arguments['width'] );
			}

			if ( isset( $arguments['height'] ) && $arguments['height'] > 0 ) {
				if ( is_numeric( $arguments['height'] ) ) {
					$arguments['mapHeight'] = $arguments['height'];
				} else {
					$this->enqueueMessage( sprintf(
						__( '%s shortcode error: %s is not a valid height.', 'basic-google-maps-placemarks' ),
						BGMP_NAME,
						$arguments['height']
					), 'error' );
				}

				unset( $arguments['height'] );
			}


			// Center
			if ( isset( $arguments['center'] ) ) {
				// Note: Google's API has a daily request limit, which could be a problem when geocoding map shortcode center address each time page loads. Users could get around that by using a caching plugin, though.

				$coordinates = $this->geocode( $arguments['center'] );
				if ( $coordinates ) {
					$arguments = array_merge( $arguments, $coordinates );
				}

				unset( $arguments['center'] );
			}


			// Zoom
			if ( isset( $arguments['zoom'] ) ) {
				if ( ! is_numeric( $arguments['zoom'] ) || $arguments['zoom'] < self::ZOOM_MIN || $arguments['zoom'] > self::ZOOM_MAX ) {
					$this->enqueueMessage( sprintf(
						__( '%s shortcode error: %s is not a valid zoom level.', 'basic-google-maps-placemarks' ),
						BGMP_NAME,
						$arguments['zoom']
					), 'error' );

					unset( $arguments['zoom'] );
				}
			}


			// Type
			if ( isset( $arguments['type'] ) ) {
				$arguments['type'] = strtoupper( $arguments['type'] );

				if ( ! array_key_exists( $arguments['type'], $this->settings->mapTypes ) ) {
					$this->enqueueMessage( sprintf(
						__( '%s shortcode error: %s is not a valid map type.', 'basic-google-maps-placemarks' ),
						SGMM_NAME,
						$arguments['type']
					), 'error' );

					unset( $arguments['type'] );
				}
			}


			return apply_filters( self::PREFIX . 'clean-map-shortcode-arguments-return', $arguments );
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
					SGMM_NAME,
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

	/**
		 * Gets map options
		 *
		 * @author Ian Dunn <ian@iandunn.name>
		 *
		 * @param array $attributes
		 *
		 * @return array
		 */
		public function getMapOptions( $attributes ) {
			$clusterStyles = array(
				'default' => array(
					array(
						'url'       => plugins_url( 'includes/marker-clusterer/images/m1.png', __FILE__ ),
						'height'    => 52,
						'width'     => 53,
						'anchor'    => array( 16, 0 ),
						'textColor' => '#ff00ff',
						'textSize'  => 10
					),

					array(
						'url'       => plugins_url( 'includes/marker-clusterer/images/m2.png', __FILE__ ),
						'height'    => 55,
						'width'     => 56,
						'anchor'    => array( 24, 0 ),
						'textColor' => '#ff0000',
						'textSize'  => 11
					),

					array(
						'url'       => plugins_url( 'includes/marker-clusterer/images/m3.png', __FILE__ ),
						'height'    => 65,
						'width'     => 66,
						'anchor'    => array( 32, 0 ),
						'textColor' => '#ffffff',
						'textSize'  => 12
					),

					array(
						'url'       => plugins_url( 'includes/marker-clusterer/images/m4.png', __FILE__ ),
						'height'    => 77,
						'width'     => 78,
						'anchor'    => array( 32, 0 ),
						'textColor' => '#ffffff',
						'textSize'  => 12
					),

					array(
						'url'       => plugins_url( 'includes/marker-clusterer/images/m5.png', __FILE__ ),
						'height'    => 89,
						'width'     => 90,
						'anchor'    => array( 32, 0 ),
						'textColor' => '#ffffff',
						'textSize'  => 12
					),
				),

				'people' => array(
					array(
						'url'       => plugins_url( 'includes/marker-clusterer/images/people35.png', __FILE__ ),
						'height'    => 35,
						'width'     => 35,
						'anchor'    => array( 16, 0 ),
						'textColor' => '#ff00ff',
						'textSize'  => 10
					),

					array(
						'url'       => plugins_url( 'includes/marker-clusterer/images/people45.png', __FILE__ ),
						'height'    => 45,
						'width'     => 45,
						'anchor'    => array( 24, 0 ),
						'textColor' => '#ff0000',
						'textSize'  => 11
					),

					array(
						'url'       => plugins_url( 'includes/marker-clusterer/images/people55.png', __FILE__ ),
						'height'    => 55,
						'width'     => 55,
						'anchor'    => array( 32, 0 ),
						'textColor' => '#ffffff',
						'textSize'  => 12
					)
				),

				'conversation' => array(
					array(
						'url'       => plugins_url( 'includes/marker-clusterer/images/conv30.png', __FILE__ ),
						'height'    => 27,
						'width'     => 30,
						'anchor'    => array( 3, 0 ),
						'textColor' => '#ff00ff',
						'textSize'  => 10
					),

					array(
						'url'       => plugins_url( 'includes/marker-clusterer/images/conv40.png', __FILE__ ),
						'height'    => 36,
						'width'     => 40,
						'anchor'    => array( 6, 0 ),
						'textColor' => '#ff0000',
						'textSize'  => 11
					),

					array(
						'url'      => plugins_url( 'includes/marker-clusterer/images/conv50.png', __FILE__ ),
						'height'   => 50,
						'width'    => 45,
						'anchor'   => array( 8, 0 ),
						'textSize' => 12
					)
				),

				'hearts' => array(
					array(
						'url'       => plugins_url( 'includes/marker-clusterer/images/heart30.png', __FILE__ ),
						'height'    => 26,
						'width'     => 30,
						'anchor'    => array( 4, 0 ),
						'textColor' => '#ff00ff',
						'textSize'  => 10
					),

					array(
						'url'       => plugins_url( 'includes/marker-clusterer/images/heart40.png', __FILE__ ),
						'height'    => 35,
						'width'     => 40,
						'anchor'    => array( 8, 0 ),
						'textColor' => '#ff0000',
						'textSize'  => 11
					),

					array(
						'url'      => plugins_url( 'includes/marker-clusterer/images/heart50.png', __FILE__ ),
						'height'   => 50,
						'width'    => 44,
						'anchor'   => array( 12, 0 ),
						'textSize' => 12
					)
				)
			);

			$options = array(
				'mapWidth'           => $this->settings->mapWidth,  // @todo move these into 'map' subarray? but then have to worry about backwards compat
				'mapHeight'          => $this->settings->mapHeight,
				'latitude'           => $this->settings->mapLatitude,
				'longitude'          => $this->settings->mapLongitude,
				'zoom'               => $this->settings->mapZoom,
				'type'               => $this->settings->mapType,
				'typeControl'        => $this->settings->mapTypeControl,
				'navigationControl'  => $this->settings->mapNavigationControl,
				'infoWindowMaxWidth' => $this->settings->mapInfoWindowMaxWidth,
				'streetViewControl'  => apply_filters( self::PREFIX . 'street-view-control', true ),    // todo deprecated b/c of bgmp_map-options filter?
				'viewOnMapScroll'    => false,

				'clustering' => array(
					'enabled'  => $this->settings->markerClustering,
					'maxZoom'  => $this->settings->clusterMaxZoom,
					'gridSize' => $this->settings->clusterGridSize,
					'style'    => $this->settings->clusterStyle,
					'styles'   => $clusterStyles
				)
			);

			// Reset center/zoom when only displaying single placemark
			if ( isset( $attributes['placemark'] ) && apply_filters( self::PREFIX . 'reset-individual-map-center-zoom', true ) ) {
				$latitude    = get_post_meta( $attributes['placemark'], self::PREFIX . 'latitude',  true );
				$longitude   = get_post_meta( $attributes['placemark'], self::PREFIX . 'longitude', true );
				$coordinates = $this->validateCoordinates( $latitude . ',' . $longitude );

				if ( $coordinates !== false ) {
					$options['latitude']  = $latitude;
					$options['longitude'] = $longitude;
					$options['zoom']      = apply_filters( self::PREFIX . 'individual-map-default-zoom', 13 );    // deprecated b/c of bgmp_map-options filter?
				}
			}

			$options = shortcode_atts( $options, $attributes );

			return apply_filters( self::PREFIX . 'map-options', $options );
		}

		/**
		 * Gets the published placemarks from the database, formats and outputs them.
		 *
		 * @author Ian Dunn <ian@iandunn.name>
		 *
		 * @param array $attributes
		 *
		 * @return string JSON-encoded array
		 */
		public function getMapPlacemarks( $attributes ) {
			$placemarks = array();

			$query = array(
				'numberposts' => - 1,
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish'
			);

			if ( isset( $attributes['placemark'] ) ) {
				$query['p'] = $attributes['placemark'];
			}

			if ( isset( $attributes['categories'] ) && ! empty( $attributes['categories'] ) ) {
				$query['tax_query'] = array(
					array(
						'taxonomy' => self::TAXONOMY,
						'field'    => 'slug',
						'terms'    => $attributes['categories']
					)
				);
			}

			$query               = apply_filters( self::PREFIX . 'get-placemarks-query', $query );        // @todo - filter name deprecated
			$publishedPlacemarks = get_posts( apply_filters( self::PREFIX . 'get-map-placemarks-query', $query ) );

			if ( $publishedPlacemarks ) {
				foreach ( $publishedPlacemarks as $pp ) {
					$postID = $pp->ID;

					$categories = get_the_terms( $postID, self::TAXONOMY );
					if ( ! is_array( $categories ) ) {
						$categories = array();
					}

					$icon        = wp_get_attachment_image_src( get_post_thumbnail_id( $postID ), apply_filters( self::PREFIX . 'featured-icon-size', 'thumbnail' ) );
					$defaultIcon = apply_filters( self::PREFIX . 'default-icon', plugins_url( 'images/default-marker.png', __FILE__ ), $postID );

					$placemark = array(
						'id'         => $postID,
						'title'      => apply_filters( 'the_title', $pp->post_title ),
						'latitude'   => get_post_meta( $postID, self::PREFIX . 'latitude', true ),
						'longitude'  => get_post_meta( $postID, self::PREFIX . 'longitude', true ),
						'details'    => apply_filters( 'the_content', $pp->post_content ),	// note: don't use setup_postdata/get_the_content() in this instance -- http://lists.automattic.com/pipermail/wp-hackers/2013-January/045053.html
						'categories' => $categories,
						'icon'       => is_array( $icon ) ? $icon[0] : $defaultIcon,
						'zIndex'     => get_post_meta( $postID, self::PREFIX . 'zIndex', true )
					);

					$placemarks[] = apply_filters( self::PREFIX . 'get-map-placemarks-individual-placemark', $placemark );
				}
			}

			$placemarks = apply_filters( self::PREFIX . 'get-placemarks-return', $placemarks );    // @todo - filter name deprecated
			return apply_filters( self::PREFIX . 'get-map-placemarks-return', $placemarks );
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
			//plugins_url( 'js/marker-clusterer/markerclusterer_packed.js', __FILE__ ),
			SGMM_SCRIPT_PATH . '/js/marker-clusterer/markerclusterer_packed.js',
			array(),
			'1.0',
			true
		);

		wp_register_script(
			'sgmm',
			//plugins_url( 'js/functions.js', __FILE__ ),
			SGMM_SCRIPT_PATH .  '/js/functions.js',
			array( 'googleMapsAPI', 'jquery' ),
			self::VERSION,
			true
		);

		wp_register_style(
			self::PREFIX . 'style',
			//plugins_url( 'style.css', __FILE__ ),
			SGMM_SCRIPT_PATH . '/style.css',
			false,
			self::VERSION
		);


		wp_enqueue_script( 'googleMapsAPI' );
		wp_enqueue_script( 'markerClusterer' );

		wp_enqueue_script( 'sgmm' );

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