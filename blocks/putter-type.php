<?php

/**
 * The putter-type block
 *
 * @link       21applications.com
 * @since      1.0.0
 *
 * @package    Journal_Member_Preferences
 * @subpackage Journal_Member_Preferences/blocks
 */

/**
 * The picks block.
 *
 * @package    Journal_Member_Preferences
 * @subpackage Journal_Member_Preferences/blocks
 * @author     Roger Coathup <roger@21applications.com>
 */
class JC_Member_Preferences_Putter_Type {


	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $version ) {

		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		wp_register_script( 
			'journal-member-preferences-putter-type', 
			plugin_dir_url( __FILE__ ) . 'js/putter-type.js', 
			[
				'wp-blocks',
				'wp-i18n',
				'wp-element',
				'wp-components',
			], 
			$this->version, 
			'all' 
		);

	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function register() {
		register_block_type('journal-member-preferences/putter-type', array(
			'editor_script' => 'journal-member-preferences-putter-type',
			'editor_style' => 'journal-member-preferences-putter-type',
			'render_callback' => [$this, 'render' ],
			'attributes' => []
		));
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $atts
	 * @return void
	 */
	public function render( $attr, $content ){

		if ( is_user_logged_in() ) {
			return $this->preference_form();
		} else {
			return '';
		}
		
	}

	/**
	 * [feature description]
	 * @param  [type] $attr [description]
	 * @return [type]       [description]
	 */
	public function preference_form() {

        wp_enqueue_script( 'holdon' );
        wp_enqueue_style( 'holdon' );
        wp_enqueue_script( 'journal-member-preferences-public' );
		wp_enqueue_style( 'journal-member-preferences-public' );
		ob_start();

		$user_id = get_current_user_id();

		$checked = 'blade';

		if ( $user_id ) {
			$preferred_type = get_user_meta( $user_id, 'jc_putter_type', true );

			if ( $preferred_type ) {
				$checked = $preferred_type;
			}
		}
		?>

		<div class="putter-type-block">
			<div class="inner-wrapper">

				<div class="message alert" style="display: none;">&nbsp;</div>
				<h4>Your selection:</h4>
                <form class="putter-type-preference" name="putter_type_preference">

                    <?php wp_nonce_field( 'jc-preference-putter-type' );?>
					<div class="choices">
						<input type="radio" id="blade" name="putter-type" value="blade" <?php 
						if ( !in_array( $checked, [ 'mallet', 'square-mallet' ] ) ) echo 'checked';?> />
						<label for="blade">Blade</label>
						<input type="radio" id="mallet" name="putter-type" value="mallet" <?php 
						if ( $checked === 'mallet' ) echo 'checked';?>/>
						<label for="mallet">Mallet</label>

						<?php 
						if ( $checked === 'square-mallet' ) {
							?>
						<input type="radio" id="square-mallet" name="putter-type" value="square-mallet" <?php 
						if ( $checked === 'square-mallet' ) echo 'checked';?>/>
						<label for="square-mallet">Square Mallet</label>
						<?php
						}
						?>

					</div>
                    <input type="submit" class="button" value="Save Choice"/>
                </form>

			</div>
		</div>


		<?php

		$output = ob_get_clean();

		return $output;
	}

    /**
     * Undocumented function
     *
     * @return void
     */
    public function handle_submission() {

        $response = new WP_Ajax_Response;

        // Add nonce check
        if ( !check_ajax_referer( 'jc-preference-putter-type', 'nonce', false ) ) {
            // Failed
            $response->add( [
                'data' => 'error',
                'supplemental' => [
                    'message' => __( 'Problem selecting putter type', 'journal-member-preferences' ),
                ],
            ] );

            $response->send();
            exit();

        }

		if ( !is_user_logged_in() ) {

			$response->add( [
                'data' => 'error',
                'supplemental' => [
                    'message' => __( 'You need to be logged in to select your putter type', 'journal-member-preferences' ),
                ],
            ] );

            $response->send();
            exit();
		}



		$user_id = get_current_user_id();

		// Set the data 
		update_user_meta( $user_id, 'jc_putter_type', $_POST['putter_type'] );
		as_enqueue_async_action( 'jc_preference_selected', [$user_id, 'Putter Cover Preference', $_POST['putter_type']] );

		$response->add( [
			'data' => 'success',
			'supplemental' => [
				'message' => sprintf('Your preferred putter type has been set to <strong>%1$s</strong>.<br/>If you would like to change this, please <a href="%2$s">click here</a>', $_POST['putter_type'], get_permalink() )
			],
		] );

		$response->send();
		exit(); 
 
    }

}
