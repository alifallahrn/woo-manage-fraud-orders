<?php
/**
 * Main class
 * Handles everything from here, includes the file for the backend settings and
 * blacklisting funcitonalities, inlcudes the frontend handlers as well.
 *
 * @package woo-manage-fraud-orders
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

if ( ! class_exists( 'Woo_Manage_Fraud_Orders' ) ) {

	/**
	 * Class Woo_Manage_Fraud_Orders
	 */
	class Woo_Manage_Fraud_Orders {

		/**
		 * The current plugin version.
		 *
		 * @var string $version
		 */
		public $version = '2.2.0';

		/**
		 * Store the class singleton.
		 *
		 * @var ?Woo_Manage_Fraud_Orders
		 */
		protected static $instance = null;

		/**
		 * Instantiate the class.
		 */
		protected function __construct() {
			$this->define_constants();
			$this->includes();
			$this->init_hooks();
		}

		/**
		 * Get an instance of the class.
		 *
		 * @return Woo_Manage_Fraud_Orders
		 */
		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Define constants
		 */
		private function define_constants() {
			$upload_dir = wp_upload_dir( null, false );

			$this->define( 'WMFO_ABSPATH', dirname( WMFO_PLUGIN_FILE ) . '/' );
			$this->define( 'WMFO_PLUGIN_BASENAME', plugin_basename( WMFO_PLUGIN_FILE ) );
			$this->define( 'WMFO_VERSION', $this->version );
			$this->define( 'WMFO_LOG_DIR', $upload_dir['basedir'] . '/wmfo-logs/' );
		}

		/**
		 * Define a constant if it has not already been defined.
		 *
		 * @param string $name The name of the constant to define.
		 * @param mixed  $value The value of the constant.
		 */
		private function define( $name, $value ) {
			if ( ! defined( $name ) ) {
				define( $name, $value );
			}
		}

		/**
		 * Init hooks
		 */
		private function init_hooks() {
			register_activation_hook( WMFO_PLUGIN_FILE, array( $this, 'install' ) );

			add_filter( 'plugin_action_links_' . plugin_basename( WMFO_PLUGIN_FILE ), array( $this, 'action_links' ), 99, 1 );
			add_action( 'plugins_loaded', array( $this, 'load_text_domain' ) );
			add_action('init', array($this, 'may_be_create_log_dir_db_table'));
			add_action('admin_menu', array($this, 'init_sub_menu'), 9999);
		}

		/**
		 * Check is WooCommerce active.
		 * Create log dir
		 * Create log db table
		 */
		public function install() {

			if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
				require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
			}

			// multisite
			if ( is_multisite() ) {
				// this plugin is network activated - Woo must be network activated
				if ( is_plugin_active_for_network( plugin_basename(__FILE__) ) ) {
					$need = ! is_plugin_active_for_network( 'woocommerce/woocommerce.php' );
					// this plugin is locally activated - Woo can be network or locally activated
				} else {
					$need = ! is_plugin_active( 'woocommerce/woocommerce.php' );
				}
				// this plugin runs on a single site
			} else {
				$need =  ! is_plugin_active( 'woocommerce/woocommerce.php');
			}

			if ( $need ) {

				echo sprintf( esc_html__( 'Woo Manage Fraud Orders depends on %s to work!', 'woo-manage-fraud-orders' ), '<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">' . esc_html__( 'WooCommerce', 'woo-manage-fraud-orders' ) . '</a>' );
				@trigger_error( '', E_USER_ERROR );

			}

			$this->may_be_create_log_dir_db_table();

		}

		/**
		 * Function to handle the creation of debug folder and DB table
		 */
		public function may_be_create_log_dir_db_table(){
			require_once plugin_dir_path(WMFO_PLUGIN_FILE) . 'includes/class-wmfo-activator.php';

			WMFO_Activator::create_db_table();

			WMFO_Activator::create_upload_dir();

		}

		/**
		 * Add the `Settings` link under the plugin name on plugins.php.
		 *
		 * @hooked plugin_action_links_{plugin_basename}
		 * @see WP_Plugins_List_Table::single_row()
		 *
		 * @param array<string, string> $actions The existing registered links.
		 * @return array<string, string>
		 */
		public static function action_links( $actions ): array {

			$new_actions = array(
				'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=settings_tab_blacklists' ) . '">' . __( 'Settings', 'woo-manage-fraud-orders' ) . '</a>',
			);

			return array_merge( $new_actions, $actions );
		}

		/**
		 * Load text domain for translation
		 *
		 * @hooked plugins_loaded
		 */
		public function load_text_domain() {
			load_plugin_textdomain(
				'woo-manage-fraud-orders',
				false,
				dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
			);
		}

		/**
		 * Include required files.
		 */
		public function includes() {
			require_once WMFO_ABSPATH . 'includes/wmfo-functions.php';
			require_once WMFO_ABSPATH . 'includes/class-wmfo-blacklist-handler.php';
			require_once WMFO_ABSPATH . 'includes/class-wmfo-debug-log.php';
			require_once WMFO_ABSPATH . 'includes/class-wmfo-track-fraud-attempts.php';
			require_once WMFO_ABSPATH . 'includes/class-wmfo-logs-handler.php';
			if ( is_admin() ) {
				require_once WMFO_ABSPATH . 'includes/admin/class-wmfo-settings-tab.php';
				require_once WMFO_ABSPATH . 'includes/admin/class-wmfo-order-metabox.php';
				require_once WMFO_ABSPATH . 'includes/admin/class-wmfo-order-actions.php';
				require_once WMFO_ABSPATH . 'includes/admin/class-wmfo-bulk-blacklist.php';
			}
		}

		public function init_sub_menu(){
			add_submenu_page( 'woocommerce', __( 'WMFO Logs', 'woo-manage-fraud-orders' ), __( 'WMFO Logs', 'woo-manage-fraud-orders' ),
				'manage_options', 'wmfo-logs', array( $this, 'render_logs' ), 99999 );
		}

		public function render_logs() {
			require_once plugin_dir_path(WMFO_PLUGIN_FILE) . 'includes/admin/class-wmfo-logs-table.php';
			$logs = new WMFO_Logs_Table();
			$logs->prepare_items();
			?>
			<div class="wrap">
				<form method="post">
					<h2><?php _e( 'Logs of Blacklisted attempts.', 'woo-manage-fraud-orders' ) ?></h2>
                    <p><?php _e('This is not the blacklisted customer details. Rather,  It is the list of customers who could not manage to place order due to blacklisting.', 'woo-manage-fraud-orders'); ?></p>
					<?php $logs->display(); ?>
				</form>
			</div>
			<?php
		}

	}
}
