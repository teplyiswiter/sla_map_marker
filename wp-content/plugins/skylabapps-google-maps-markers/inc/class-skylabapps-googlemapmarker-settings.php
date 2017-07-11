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
	 * @link    http://wordpress.org/extend/plugins/basic-google-maps-placemarks/
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
			add_action( 'init',       array( $this, 'updateMapCoordinates' )    );
			add_action( 'admin_menu', array( $this, 'addSettingsPage'      )    );
			add_action( 'admin_init', array( $this, 'addSettings'          )    );            // @todo - this may need to fire after admin_menu

			add_filter( 'plugin_action_links_basic-google-maps-placemarks/basic-google-maps-placemarks.php', array( $this, 'addSettingsLink' ) );
		}

		/**
		 * Performs various initialization functions
		 *
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function init() {
			// @todo saving this as a single array instead of separate options

			$this->mapApiKey       = get_option( Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-api-key',       false        );
			$this->geocodingApiKey = get_option( Skylabapps_GoogleMapMarker_Core::PREFIX . 'geocoding-api-key', false        );
			$this->mapWidth        = get_option( Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-width',         600          );
			$this->mapHeight       = get_option( Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-height',        400          );
			$this->mapAddress      = get_option( Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-address',       __( 'Seattle', 'basic-google-maps-placemarks' ) );
			$this->mapLatitude     = get_option( Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-latitude',      47.6062095   );
			$this->mapLongitude    = get_option( Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-longitude',     -122.3320708 );
			$this->mapZoom         = get_option( Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-zoom',          7            );
			$this->mapType         = get_option( Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-type',          'ROADMAP'    );

			$this->mapTypes = array(
				'ROADMAP'   => __( 'Street Map',       'basic-google-maps-placemarks' ),
				'SATELLITE' => __( 'Satellite Images', 'basic-google-maps-placemarks' ),
				'HYBRID'    => __( 'Hybrid',           'basic-google-maps-placemarks' ),
				'TERRAIN'   => __( 'Terrain',          'basic-google-maps-placemarks' )
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
		 * Get the map center coordinates from the address and update the database values
		 * The latitude/longitude need to be updated when the address changes, but there's no way to do that with the settings API
		 *
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function updateMapCoordinates() {
			// @todo - this could be done during a settings validation callback?
			global $bgmp;

			$haveCoordinates = true;

			if ( isset( $_POST[ Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-address' ] ) ) {
				if ( empty( $_POST[ Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-address' ] ) ) {
					$haveCoordinates = false;
				} else {
					$coordinates = $bgmp->geocode( $_POST[ Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-address' ] );

					if ( ! $coordinates ) {
						$haveCoordinates = false;
					}
				}

				if ( $haveCoordinates ) {
					update_option( Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-latitude',  $coordinates['latitude']  );
					update_option( Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-longitude', $coordinates['longitude'] );
				} else {
					// @todo - can't call protected from this class - $this->bgmp->enqueueMessage('That address couldn\'t be geocoded, please make sure that it\'s correct.', 'error' );

					update_option( Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-latitude',  '' );    // @todo - update these
					update_option( Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-longitude', '' );
				}
			}
		}

		/**
		 * Adds a page to Settings menu
		 *
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function addSettingsPage() {
			add_options_page(
				BGMP_NAME . ' Settings',
				BGMP_NAME, 'manage_options',
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				array( $this, 'markupSettingsPage' )
			);

			add_meta_box(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'rasr-plug',
				__( 'Re-Abolish Slavery', 'basic-google-maps-placemarks' ),
				array( $this, 'markupRASRMetaBox' ),
				'settings_page_' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				'side'
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
			$show_api_key_notice = empty( $this->mapApiKey ) || empty( $this->geocodingApiKey );

			// @todo some of above may not be needed

			if ( current_user_can( 'manage_options' ) ) {
				require_once( dirname( __FILE__ ) . '/views/settings.php' );
			} else {
				wp_die( 'Access denied.' );
			}
		}

		/**
		 * Creates the markup for the Re-Abolish Slavery Ribbon meta box
		 *
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function markupRASRMetaBox() {
			require_once( dirname( __FILE__ ) . '/views/meta-re-abolish-slavery.php' );
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
				'<a href="http://wordpress.org/extend/plugins/basic-google-maps-placemarks/faq/">' . __( 'Help', 'basic-google-maps-placemarks' ) . '</a>'
			);

			array_unshift(
				$links,
				'<a href="options-general.php?page=' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings">' . __( 'Settings', 'basic-google-maps-placemarks' ) . '</a>'
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

			add_settings_section(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'marker-cluster-settings',
				'',
				array( $this, 'markupSettingsSections' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings'
			);


			// Map Settings
			add_settings_field(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-api-key',
				__( 'Maps API Key', 'basic-google-maps-placemarks' ),
				array( $this, 'markupMapSettingsFields' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-settings',
				array( 'label_for' => Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-api-key' )
			);

			add_settings_field(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'geocoding-api-key',
				__( 'Geocoding API Key', 'basic-google-maps-placemarks' ),
				array( $this, 'markupMapSettingsFields' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-settings',
				array( 'label_for' => Skylabapps_GoogleMapMarker_Core::PREFIX . 'geocoding-api-key' )
			);

			add_settings_field(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-width',
				__( 'Map Width', 'basic-google-maps-placemarks' ),
				array( $this, 'markupMapSettingsFields' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-settings',
				array( 'label_for' => Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-width' )
			);

			add_settings_field(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-height',
				__( 'Map Height', 'basic-google-maps-placemarks' ),
				array( $this, 'markupMapSettingsFields' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-settings',
				array( 'label_for' => Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-height' )
			);

			add_settings_field(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-address',
				__( 'Map Center Address', 'basic-google-maps-placemarks' ),
				array( $this, 'markupMapSettingsFields' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-settings',
				array( 'label_for' => Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-address' )
			);

			add_settings_field(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-zoom',
				__( 'Zoom', 'basic-google-maps-placemarks' ),
				array( $this, 'markupMapSettingsFields' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-settings',
				array( 'label_for' => Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-zoom' )
			);

			add_settings_field(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-type',
				__( 'Map Type', 'basic-google-maps-placemarks' ),
				array( $this, 'markupMapSettingsFields' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-settings',
				array( 'label_for' => Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-type' )
			);

			add_settings_field(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-type-control',
				__( 'Type Control', 'basic-google-maps-placemarks' ),
				array( $this, 'markupMapSettingsFields' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-settings',
				array( 'label_for' => Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-type-control' )
			);

			add_settings_field(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-navigation-control',
				__( 'Navigation Control', 'basic-google-maps-placemarks' ),
				array( $this, 'markupMapSettingsFields' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-settings',
				array( 'label_for' => Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-navigation-control' )
			);

			add_settings_field(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-info-window-width',
				__( 'Info. Window Maximum Width', 'basic-google-maps-placemarks' ),
				array( $this, 'markupMapSettingsFields' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-settings',
				array( 'label_for' => Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-info-window-width' )
			);

			register_setting( Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings', Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-api-key'            );
			register_setting( Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings', Skylabapps_GoogleMapMarker_Core::PREFIX . 'geocoding-api-key'      );
			register_setting( Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings', Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-width'              );
			register_setting( Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings', Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-height'             );
			register_setting( Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings', Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-address'            );
			register_setting( Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings', Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-zoom'               );
			register_setting( Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings', Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-type'               );
			register_setting( Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings', Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-type-control'       );
			register_setting( Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings', Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-navigation-control' );
			register_setting( Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings', Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-info-window-width'  );


			// Marker Clustering
			add_settings_field(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'marker-clustering',
				__( 'Marker Clustering', 'basic-google-maps-placemarks' ),
				array( $this, 'markupMarkerClusterFields' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'marker-cluster-settings',
				array( 'label_for' => Skylabapps_GoogleMapMarker_Core::PREFIX . 'marker-clustering' )
			);

			add_settings_field(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'cluster-max-zoom',
				__( 'Max Zoom', 'basic-google-maps-placemarks' ),
				array( $this, 'markupMarkerClusterFields' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'marker-cluster-settings',
				array( 'label_for' => Skylabapps_GoogleMapMarker_Core::PREFIX . 'cluster-max-zoom' )
			);

			add_settings_field(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'cluster-grid-size',
				__( 'Grid Size', 'basic-google-maps-placemarks' ),
				array( $this, 'markupMarkerClusterFields' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'marker-cluster-settings',
				array( 'label_for' => Skylabapps_GoogleMapMarker_Core::PREFIX . 'cluster-grid-size' )
			);

			add_settings_field(
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'cluster-style',
				__( 'Style', 'basic-google-maps-placemarks' ),
				array( $this, 'markupMarkerClusterFields' ),
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings',
				Skylabapps_GoogleMapMarker_Core::PREFIX . 'marker-cluster-settings',
				array( 'label_for' => Skylabapps_GoogleMapMarker_Core::PREFIX . 'cluster-style' )
			);

			register_setting( Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings', Skylabapps_GoogleMapMarker_Core::PREFIX . 'marker-clustering' );
			register_setting( Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings', Skylabapps_GoogleMapMarker_Core::PREFIX . 'cluster-max-zoom'  );
			register_setting( Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings', Skylabapps_GoogleMapMarker_Core::PREFIX . 'cluster-grid-size' );
			register_setting( Skylabapps_GoogleMapMarker_Core::PREFIX . 'settings', Skylabapps_GoogleMapMarker_Core::PREFIX . 'cluster-style'     );

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
					echo '<h3>' . __( 'Map Settings', 'basic-google-maps-placemarks' ) . '</h3>';
					echo '<p>'  . __( 'The map(s) will use these settings as defaults, but you can override them on individual maps using shortcode arguments. See <a href="http://wordpress.org/extend/plugins/basic-google-maps-placemarks/installation/">the Installation page</a> for details.', 'basic-google-maps-placemarks' ) . '</p>';
					break;

				case Skylabapps_GoogleMapMarker_Core::PREFIX . 'marker-cluster-settings':
					echo '<h3>' . __( 'Marker Clustering', 'basic-google-maps-placemarks' ) . '</h3>';
					echo '<p>'  . __( 'You can group large numbers of markers into a single cluster by enabling the Cluster Markers option.', 'basic-google-maps-placemarks' ) . '</p>';
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

				case Skylabapps_GoogleMapMarker_Core::PREFIX . 'geocoding-api-key':
					echo '<input id="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'geocoding-api-key" name="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'geocoding-api-key" type="text" value="' . esc_attr( $this->geocodingApiKey ) . '" class="regular-text" /> ';
					break;

				case Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-width':
					echo '<input id="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-width" name="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-width" type="text" value="' . esc_attr( $this->mapWidth ) . '" class="small-text" /> ';
					_e( 'pixels', 'basic-google-maps-placemarks' );
					break;

				case Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-height':
					echo '<input id="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-height" name="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-height" type="text" value="' . esc_attr( $this->mapHeight ) . '" class="small-text" /> ';
					_e( 'pixels', 'basic-google-maps-placemarks' );
					break;

				case Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-address':
					echo '<input id="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-address" name="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-address" type="text" value="' . esc_attr( $this->mapAddress ) . '" class="regular-text" />';

					if ( $this->mapAddress && ! Skylabapps_GoogleMapMarker_Core::validateCoordinates( $this->mapAddress ) && $this->mapLatitude && $this->mapLongitude ) {
						echo ' <em>(' . __( 'Geocoded to:', 'basic-google-maps-placemarks' ) . ' ' . esc_html( $this->mapLatitude ) . ', ' . esc_html( $this->mapLongitude ) . ')</em>';
					} elseif ( $this->mapAddress && ( ! $this->mapLatitude || ! $this->mapLongitude ) ) {
						echo " <em>" . __( "(Error geocoding address. Please make sure it's correct and try again.)", 'basic-google-maps-placemarks' ) . "</em>";
					}

					echo '<p class="description">' . __( 'You can type in anything that you would type into a Google Maps search field, from a full address to an intersection, landmark, city, zip code or latitude/longitude coordinates.', 'basic-google-maps-placemarks' ) . '</p>';
					break;

				case Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-zoom':
					echo '<input id="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-zoom" name="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-zoom" type="text" value="' . esc_attr( $this->mapZoom ) . '" class="small-text" /> ';
					printf( __( '%d (farthest) to %d (closest)', 'basic-google-maps-placemarks' ), Skylabapps_GoogleMapMarker_Core::ZOOM_MIN, Skylabapps_GoogleMapMarker_Core::ZOOM_MAX );
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
						<option value="off" ' . ( $this->mapTypeControl == 'off' ? 'selected="selected"' : '' ) . '>' . __( 'Off', 'basic-google-maps-placemarks' ) . '</option>
						<option value="DEFAULT" ' . ( $this->mapTypeControl == 'DEFAULT' ? 'selected="selected"' : '' ) . '>' . __( 'Automatic', 'basic-google-maps-placemarks' ) . '</option>
						<option value="HORIZONTAL_BAR" ' . ( $this->mapTypeControl == 'HORIZONTAL_BAR' ? 'selected="selected"' : '' ) . '>' . __( 'Horizontal Bar', 'basic-google-maps-placemarks' ) . '</option>
						<option value="DROPDOWN_MENU" ' . ( $this->mapTypeControl == 'DROPDOWN_MENU' ? 'selected="selected"' : '' ) . '>' . __( 'Dropdown Menu', 'basic-google-maps-placemarks' ) . '</option>
					</select>';
					// @todo use selected()

					echo '<p class="description">' . esc_html__( ' "Automatic" will automatically switch to the appropriate control based on the window size and other factors.', 'basic-google-maps-placemarks' ) . '</p>';
					break;

				case Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-navigation-control':
					echo '<select id="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-navigation-control" name="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-navigation-control">
						<option value="off" ' . ( $this->mapNavigationControl == 'DEFAULT' ? 'selected="selected"' : '' ) . '>' . __( 'Off', 'basic-google-maps-placemarks' ) . '</option>
						<option value="DEFAULT" ' . ( $this->mapNavigationControl == 'DEFAULT' ? 'selected="selected"' : '' ) . '>' . __( 'Automatic', 'basic-google-maps-placemarks' ) . '</option>
						<option value="SMALL" ' . ( $this->mapNavigationControl == 'SMALL' ? 'selected="selected"' : '' ) . '>' . __( 'Small', 'basic-google-maps-placemarks' ) . '</option>
						<option value="ANDROID" ' . ( $this->mapNavigationControl == 'ANDROID' ? 'selected="selected"' : '' ) . '>' . __( 'Android', 'basic-google-maps-placemarks' ) . '</option>
						<option value="ZOOM_PAN" ' . ( $this->mapNavigationControl == 'ZOOM_PAN' ? 'selected="selected"' : '' ) . '>' . __( 'Zoom/Pan', 'basic-google-maps-placemarks' ) . '</option>
					</select>';
					// @todo use selected()

					echo '<p class="description">' . esc_html__( ' "Automatic" will automatically switch to the appropriate control based on the window size and other factors.', 'basic-google-maps-placemarks' ) . '</p>';
					break;

				case Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-info-window-width':
					echo '<input id="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-info-window-width" name="' . Skylabapps_GoogleMapMarker_Core::PREFIX . 'map-info-window-width" type="text" value="' . esc_attr( $this->mapInfoWindowMaxWidth ) . '" class="small-text" /> ';
					_e( 'pixels', 'basic-google-maps-placemarks' );
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
