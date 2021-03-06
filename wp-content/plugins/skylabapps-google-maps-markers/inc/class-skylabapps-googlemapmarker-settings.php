<?php

if ( $_SERVER['SCRIPT_FILENAME'] == __FILE__ ) {
	die( 'Access denied.' );
}

if ( ! class_exists( 'SGMMSettings' ) ) {
	/**
	 * Registers and handles the plugin's settings
	 *
	 * @package Skylabapps_GoogleMapMarker_Core
	 * @author  Ian Dunn <ian@iandunn.name>
	 * @link    http://wordpress.org/extend/plugins/skylabapps-google-maps-markers/
	 */
	class SGMM_Settings {
		public $mapApiKey, $geocodingApiKey, $mapWidth, $mapHeight, $mapAddress, $mapLatitude, $mapLongitude, $mapZoom, $mapType, $mapTypes, $mapTypeControl, $mapNavigationControl, $mapInfoWindowMaxWidth;

		/**
		 * Constructor
		 *
		 * @author Ian Dunn <ian@iandunn.name>
		 *
		 * @param object Skylabapps_GoogleMapMarker_Core object
		 */
		public function __construct() {
			add_action( 'init',       array( $this, 'init'                 ), 9 );    // lower priority so that variables defined here will be available to other init callbacks
			//add_action( 'init',       array( $this, 'updateMapCoordinates' )    );
			add_action( 'admin_menu', array( $this, 'addSettingsPage'      )    );
			add_action( 'admin_init', array( $this, 'addSettings'          )    );            // @todo - this may need to fire after admin_menu

			add_filter( 'plugin_action_links_skylabapps-google-maps-markers/skylabapps-google-maps-markers.php', array( $this, 'addSettingsLink' ) );
		}

		/**
		 * Performs various initialization functions
		 *
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function init() {
			// @todo saving this as a single array instead of separate options

			$this->mapApiKey       = get_option( Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-api-key',       false        );
			//$this->geocodingApiKey = get_option( Skylabapps_GoogleMapMarker_Core::PREFIX . 'geocoding-api-key', false        );
			$this->mapWidth        = get_option( Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-width',         600          );
			$this->mapHeight       = get_option( Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-height',        400          );
			$this->mapLatitude     = get_option( Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-latitude',      47.6062095   );
			$this->mapLongitude    = get_option( Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-longitude',     -122.3320708 );
			$this->mapZoom         = get_option( Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-zoom',          7            );
			$this->mapType         = get_option( Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-type',          'ROADMAP'    );

			$this->mapTypes = array(
				'ROADMAP'   => __( 'Street Map',       'skylabapps-google-maps-markers' ),
				'SATELLITE' => __( 'Satellite Images', 'skylabapps-google-maps-markers' ),
				'HYBRID'    => __( 'Hybrid',           'skylabapps-google-maps-markers' ),
				'TERRAIN'   => __( 'Terrain',          'skylabapps-google-maps-markers' )
			);

			$this->mapTypeControl        = get_option( Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-type-control',       'off'     );
			$this->mapNavigationControl  = get_option( Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-navigation-control', 'DEFAULT' );
			$this->mapInfoWindowMaxWidth = get_option( Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-info-window-width',   500      );

			$this->markerClustering = get_option( Skylabapps_GoogleMapMarker_Core::PREFIX . 'marker-clustering', ''       );
			$this->clusterMaxZoom   = get_option( Skylabapps_GoogleMapMarker_Core::PREFIX . 'cluster-max-zoom',  '7'      );
			$this->clusterGridSize  = get_option( Skylabapps_GoogleMapMarker_Core::PREFIX . 'cluster-grid-size', '40'     );
			$this->clusterStyle     = get_option( Skylabapps_GoogleMapMarker_Core::PREFIX . 'cluster-style', '   default' );

			// @todo - this isn't DRY, same values in BGMP::singleActivate() and upgrade()
		}

		/**
		 * Adds a page to Settings menu
		 *
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function addSettingsPage() {
			add_options_page(
				SGMM_NAME . ' Settings',
				SGMM_NAME, 'manage_options',
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				array( $this, 'markupSettingsPage' )
			);

   	}

		/**
		 * Creates the markup for the settings page
		 *
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function markupSettingsPage() {
			$rasrMetaBoxID       = Skylabapps_GoogleMapMarker_Core::PREFIX . 'rasr-plug';
			$rasrMetaBoxPage     = Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings';    // @todo better var name
			$hidden              = get_hidden_meta_boxes( $rasrMetaBoxPage );
			$hidden_class        = in_array( $rasrMetaBoxPage, $hidden ) ? ' hide-if-js' : '';
			//$show_api_key_notice = empty( $this->mapApiKey ) || empty( $this->geocodingApiKey );
			$show_api_key_notice = empty( $this->mapApiKey );

			// @todo some of above may not be needed

			if ( current_user_can( 'manage_options' ) ) {
				require_once( dirname( __FILE__ ) . '/views/settings.php' );
			} else {
				wp_die( 'Access denied.' );
			}
		}

		/**
		 * Adds a 'Settings' link to the Plugins page
		 *
		 * @author Ian Dunn <ian@iandunn.name>
		 *
		 * @param array $links The links currently mapped to the plugin
		 *
		 * @return array
		 */
		public function addSettingsLink( $links ) {
			array_unshift(
				$links,
				'<a href="http://wordpress.org/extend/plugins/skylabapps-google-maps-markers/faq/">' . __( 'Help', 'skylabapps-google-maps-markers' ) . '</a>'
			);

			array_unshift(
				$links,
				'<a href="options-general.php?page=' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings">' . __( 'Settings', 'skylabapps-google-maps-markers' ) . '</a>'
			);

			return $links;
		}

		/**
		 * Adds our custom settings to the admin Settings pages
		 * We intentionally don't register the map-latitude and map-longitude settings because they're set by updateMapCoordinates()
		 *
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function addSettings() {
			add_settings_section(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-settings',
				'',
				array( $this, 'markupSettingsSections' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings'
			);

			/*add_settings_section(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'marker-cluster-settings',
				'',
				array( $this, 'markupSettingsSections' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings'
			);*/


			// Map Settings
			add_settings_field(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-api-key',
				__( 'Maps API Key', 'skylabapps-google-maps-markers' ),
				array( $this, 'markupMapSettingsFields' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-settings',
				array( 'label_for' => Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-api-key' )
			);

			/*add_settings_field(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'geocoding-api-key',
				__( 'Geocoding API Key', 'skylabapps-google-maps-markers' ),
				array( $this, 'markupMapSettingsFields' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-settings',
				array( 'label_for' => Skylabapps_GoogleMapMarker_Core::PREFIX . 'geocoding-api-key' )
			);*/

			add_settings_field(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-width',
				__( 'Map Width', 'skylabapps-google-maps-markers' ),
				array( $this, 'markupMapSettingsFields' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-settings',
				array( 'label_for' => Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-width' )
			);

			add_settings_field(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-height',
				__( 'Map Height', 'skylabapps-google-maps-markers' ),
				array( $this, 'markupMapSettingsFields' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-settings',
				array( 'label_for' => Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-height' )
			);

			add_settings_field(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-zoom',
				__( 'Zoom', 'skylabapps-google-maps-markers' ),
				array( $this, 'markupMapSettingsFields' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-settings',
				array( 'label_for' => Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-zoom' )
			);

			add_settings_field(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-type',
				__( 'Map Type', 'skylabapps-google-maps-markers' ),
				array( $this, 'markupMapSettingsFields' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-settings',
				array( 'label_for' => Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-type' )
			);

			add_settings_field(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-type-control',
				__( 'Type Control', 'skylabapps-google-maps-markers' ),
				array( $this, 'markupMapSettingsFields' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-settings',
				array( 'label_for' => Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-type-control' )
			);

			add_settings_field(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-navigation-control',
				__( 'Navigation Control', 'skylabapps-google-maps-markers' ),
				array( $this, 'markupMapSettingsFields' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-settings',
				array( 'label_for' => Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-navigation-control' )
			);

			add_settings_field(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-info-window-width',
				__( 'Info. Window Maximum Width', 'skylabapps-google-maps-markers' ),
				array( $this, 'markupMapSettingsFields' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-settings',
				array( 'label_for' => Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-info-window-width' )
			);

			register_setting( Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings', Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-api-key'            );
			//register_setting( Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings', Skylabapps_GoogleMapMarker_Core::PREFIX . 'geocoding-api-key'      );
			register_setting( Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings', Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-width'              );
			register_setting( Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings', Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-height'             );
			register_setting( Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings', Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-zoom'               );
			register_setting( Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings', Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-type'               );
			register_setting( Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings', Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-type-control'       );
			register_setting( Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings', Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-navigation-control' );
			register_setting( Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings', Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-info-window-width'  );


			// Marker Clustering
			/*add_settings_field(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'marker-clustering',
				__( 'Marker Clustering', 'skylabapps-google-maps-markers' ),
				array( $this, 'markupMarkerClusterFields' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'marker-cluster-settings',
				array( 'label_for' => Skylabapps_GoogleMapMarker_Core::PREFIX . 'marker-clustering' )
			);

			add_settings_field(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'cluster-max-zoom',
				__( 'Max Zoom', 'skylabapps-google-maps-markers' ),
				array( $this, 'markupMarkerClusterFields' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'marker-cluster-settings',
				array( 'label_for' => Skylabapps_GoogleMapMarker_Core::PREFIX . 'cluster-max-zoom' )
			);

			add_settings_field(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'cluster-grid-size',
				__( 'Grid Size', 'skylabapps-google-maps-markers' ),
				array( $this, 'markupMarkerClusterFields' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'marker-cluster-settings',
				array( 'label_for' => Skylabapps_GoogleMapMarker_Core::PREFIX . 'cluster-grid-size' )
			);

			add_settings_field(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'cluster-style',
				__( 'Style', 'skylabapps-google-maps-markers' ),
				array( $this, 'markupMarkerClusterFields' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'marker-cluster-settings',
				array( 'label_for' => Skylabapps_GoogleMapMarker_Core::PREFIX . 'cluster-style' )
			);

			register_setting( Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings', Skylabapps_GoogleMapMarker_Core::PREFIX . 'marker-clustering' );
			register_setting( Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings', Skylabapps_GoogleMapMarker_Core::PREFIX . 'cluster-max-zoom'  );
			register_setting( Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings', Skylabapps_GoogleMapMarker_Core::PREFIX . 'cluster-grid-size' );
			register_setting( Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings', Skylabapps_GoogleMapMarker_Core::PREFIX . 'cluster-style'     );*/

			// @todo - add input validation  -- http://ottopress.com/2009/wordpress-settings-api-tutorial/
		}

		/**
		 * Adds the markup for the  section introduction text to the Settings page
		 *
		 * @author Ian Dunn <ian@iandunn.name>
		 *
		 * @param array $section
		 */
		public function markupSettingsSections( $section ) {
			// @todo move this to an external view file

			switch ( $section['id'] ) {
				case Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-settings':
					echo '<h3>' . __( 'Map Settings', 'skylabapps-google-maps-markers' ) . '</h3>';
					//echo '<p>'  . __( 'The map(s) will use these settings as defaults, but you can override them on individual maps using shortcode arguments. See <a href="http://wordpress.org/extend/plugins/skylabapps-google-maps-markers/installation/">the Installation page</a> for details.', 'skylabapps-google-maps-markers' ) . '</p>';
					break;

				case Skylabapps_GoogleMapMarker_Core::PREFIX . 'marker-cluster-settings':
					echo '<h3>' . __( 'Marker Clustering', 'skylabapps-google-maps-markers' ) . '</h3>';
					echo '<p>'  . __( 'You can group large numbers of markers into a single cluster by enabling the Cluster Markers option.', 'skylabapps-google-maps-markers' ) . '</p>';
					break;
			}
		}

		/**
		 * Adds the markup for the all of the fields in the Map Settings section
		 *
		 * @author Ian Dunn <ian@iandunn.name>
		 *
		 * @param array $field
		 */
		public function markupMapSettingsFields( $field ) {
			// @todo move this to an external view file

			switch ( $field['label_for'] ) {
				case Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-api-key':
					echo '<input id="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-api-key" name="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-api-key" type="text" value="' . esc_attr( $this->mapApiKey ) . '" class="regular-text" /> ';
					break;

				/*case Skylabapps_GoogleMapMarker_Core::PREFIX . 'geocoding-api-key':
					echo '<input id="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'geocoding-api-key" name="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'geocoding-api-key" type="text" value="' . esc_attr( $this->geocodingApiKey ) . '" class="regular-text" /> ';
					break;*/

				case Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-width':
					echo '<input id="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-width" name="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-width" type="text" value="' . esc_attr( $this->mapWidth ) . '" class="small-text" /> ';
					_e( 'percents or pixels', 'skylabapps-google-maps-markers' );
					break;

				case Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-height':
					echo '<input id="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-height" name="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-height" type="text" value="' . esc_attr( $this->mapHeight ) . '" class="small-text" /> ';
					_e( 'percents or pixels', 'skylabapps-google-maps-markers' );
					break;

				case Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-zoom':
					echo '<input id="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-zoom" name="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-zoom" type="text" value="' . esc_attr( $this->mapZoom ) . '" class="small-text" /> ';
					printf( __( '%d (farthest) to %d (closest)', 'skylabapps-google-maps-markers' ), Skylabapps_GoogleMapMarker_Core::ZOOM_MIN, Skylabapps_GoogleMapMarker_Core::ZOOM_MAX );
					break;

				case Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-type':
					echo '<select id="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-type" name="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-type">';

					foreach ( $this->mapTypes as $code => $label ) {
						echo '<option value="' . esc_attr( $code ) . '" ' . ( $this->mapType == $code ? 'selected="selected"' : '' ) . '>' . esc_html( $label ) . '</option>';
					}

					echo '</select>';
					break;

				case Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-type-control':
					echo '<select id="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-type-control" name="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-type-control">
						<option value="off" ' . ( $this->mapTypeControl == 'off' ? 'selected="selected"' : '' ) . '>' . __( 'Off', 'skylabapps-google-maps-markers' ) . '</option>
						<option value="DEFAULT" ' . ( $this->mapTypeControl == 'DEFAULT' ? 'selected="selected"' : '' ) . '>' . __( 'Automatic', 'skylabapps-google-maps-markers' ) . '</option>
						<option value="HORIZONTAL_BAR" ' . ( $this->mapTypeControl == 'HORIZONTAL_BAR' ? 'selected="selected"' : '' ) . '>' . __( 'Horizontal Bar', 'skylabapps-google-maps-markers' ) . '</option>
						<option value="DROPDOWN_MENU" ' . ( $this->mapTypeControl == 'DROPDOWN_MENU' ? 'selected="selected"' : '' ) . '>' . __( 'Dropdown Menu', 'skylabapps-google-maps-markers' ) . '</option>
					</select>';
					// @todo use selected()

					echo '<p class="description">' . esc_html__( ' "Automatic" will automatically switch to the appropriate control based on the window size and other factors.', 'skylabapps-google-maps-markers' ) . '</p>';
					break;

				case Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-navigation-control':
					echo '<select id="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-navigation-control" name="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-navigation-control">
						<option value="off" ' . ( $this->mapNavigationControl == 'DEFAULT' ? 'selected="selected"' : '' ) . '>' . __( 'Off', 'skylabapps-google-maps-markers' ) . '</option>
						<option value="DEFAULT" ' . ( $this->mapNavigationControl == 'DEFAULT' ? 'selected="selected"' : '' ) . '>' . __( 'Automatic', 'skylabapps-google-maps-markers' ) . '</option>
						<option value="SMALL" ' . ( $this->mapNavigationControl == 'SMALL' ? 'selected="selected"' : '' ) . '>' . __( 'Small', 'skylabapps-google-maps-markers' ) . '</option>
						<option value="ANDROID" ' . ( $this->mapNavigationControl == 'ANDROID' ? 'selected="selected"' : '' ) . '>' . __( 'Android', 'skylabapps-google-maps-markers' ) . '</option>
						<option value="ZOOM_PAN" ' . ( $this->mapNavigationControl == 'ZOOM_PAN' ? 'selected="selected"' : '' ) . '>' . __( 'Zoom/Pan', 'skylabapps-google-maps-markers' ) . '</option>
					</select>';
					// @todo use selected()

					echo '<p class="description">' . esc_html__( ' "Automatic" will automatically switch to the appropriate control based on the window size and other factors.', 'skylabapps-google-maps-markers' ) . '</p>';
					break;

				case Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-info-window-width':
					echo '<input id="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-info-window-width" name="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-info-window-width" type="text" value="' . esc_attr( $this->mapInfoWindowMaxWidth ) . '" class="small-text" /> ';
					_e( 'pixels', 'skylabapps-google-maps-markers' );
					break;
			}
		}

		/**
		 * Adds the markup for the all of the fields in the Map Settings section
		 *
		 * @author Ian Dunn <ian@iandunn.name>
		 *
		 * @param array $field
		 */
		public function markupMarkerClusterFields( $field ) {
			require( dirname( __FILE__ ) . '/views/settings-marker-clusterer.php' );
		}
	} // end BGMPSettings
}
