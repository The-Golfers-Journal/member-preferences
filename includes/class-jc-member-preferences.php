<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       21applications.com
 * @since      1.0.0
 *
 * @package    JC_Member_Preferences
 * @subpackage JC_Member_Preferences/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    JC_Member_Preferences
 * @subpackage JC_Member_Preferences/includes
 * @author     Roger Coathup <roger@21applications.com>
 */
class JC_Member_Preferences {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      JC_Member_Preferences_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'JC_MEMBER_PREFERENCES_VERSION' ) ) {
			$this->version = JC_MEMBER_PREFERENCES_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'jc-member-preferences';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_block_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - JC_Member_Preferences_Loader. Orchestrates the hooks of the plugin.
	 * - JC_Member_Preferences_i18n. Defines internationalization functionality.
	 * - JC_Member_Preferences_Admin. Defines all hooks for the admin area.
	 * - JC_Member_Preferences_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jc-member-preferences-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jc-member-preferences-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-jc-member-preferences-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-jc-member-preferences-public.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'blocks/putter-type.php';


		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'blocks/headcover.php';

		add_action( 'plugins_loaded', [ $this, 'load_dependant_classes' ], 999 );

		$this->loader = new JC_Member_Preferences_Loader();

	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function load_dependant_classes() {

		if ( is_woocommerce_active() ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . '/includes/class-jc-member-preferences-query.php';
			new JC_Member_Preferences_Query();
		}

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the JC_Member_Preferences_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new JC_Member_Preferences_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new JC_Member_Preferences_Admin( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'journal_options_fields', $plugin_admin, 'register_options', 10, 2 );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {


		$plugin_public = new JC_Member_Preferences_Public( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	private function define_block_hooks() {

		$block = new JC_Member_Preferences_Putter_Type( $this->get_version() );

		$this->loader->add_action( 'init', $block, 'enqueue_styles' );
		$this->loader->add_action( 'init', $block, 'enqueue_scripts' );
		$this->loader->add_action( 'init', $block, 'register' );
		$this->loader->add_action( 'wp_ajax_nopriv_jc_set_putter_type', $block, 'handle_submission' );
		$this->loader->add_action( 'wp_ajax_jc_set_putter_type', $block, 'handle_submission' );

		$block = new JC_Member_Preferences_Headcover( $this->get_version() );

		$this->loader->add_action( 'init', $block, 'enqueue_styles' );
		$this->loader->add_action( 'init', $block, 'enqueue_scripts' );
		$this->loader->add_action( 'init', $block, 'register' );
		$this->loader->add_action( 'init', $block, 'handle_submission' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    JC_Member_Preferences_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
