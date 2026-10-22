<?php

/**
* The controller for putter cover reports
 *
 * @link       https://21applications.com
 * @since      1.0.0
 *
 * @package    Journal_Member_Preferences
 * @subpackage Journal_Member_Preferences/controllers
 */

/**
 * The controller functionality of the plugin.
 *
 * @package    Journal_Member_Preferences
 * @subpackage Journal_Member_Preferences/controllers
 * @author     Roger Coathup <roger@21applications.com>
 */
class JC_Putter_Cover_Controller extends JC_AR_Report_Controller {


    public function __construct() {

	    $this->report_type = 'putter-cover';
        $this->action = 'putter-cover';
        $this->base_action = 'jc_putter_cover_report';
        $this->async_request = JC_Registry::get_instance( 'putter-cover-report' );

        parent::__construct();
        
    }

    protected function get_total_steps( JC_Async_Report &$report ) : int {
        return 0;
    }

    /**
     * Undocumented function
     *
     * @param array $context
     * @return JC_Async_Report
     */
    protected function setup_context( JC_Async_Report &$report, array $context ) {

        $file = $this->create_file( $context );
        $report->set_downloads( json_encode( [ $file ] ) );

        $report->set_context( json_encode( $context ) );

    }

    /**
     * Undocumented function
     *
     * @param [type] $context
     * @return void
     */
    protected function create_file( array $context ) {

        $upload_dir = wp_upload_dir();
        $filename =  sanitize_file_name( date('Y-m-d-H:i:s') . '.csv' );
        $title = isset( $context['title'] ) ? $context['title'] : 'Status Report';

        $file = [
            'title' => $title,
            'filename' => trailingslashit( $upload_dir['path'] ) . $filename,
            'fileurl' => trailingslashit( $upload_dir['url'] ) . $filename
        ];

        $exists = file_exists( $file['filename'] );
		$fp = fopen( $file['filename'], 'a' );

		if ( !$exists ) {

			// New file - write the header information
			// Write header information for special characters
			fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));

			// Write the column headers
			$header_row = array(
				'Subscription #', 'Customer #', 'Billing Email', 'Gift', 'Recipient #', 'Recipient Email', 'Shipping First', 'Shipping Last', 'Preference', 'Redeemed', 'Redemption Order #'
			);

			fputcsv( $fp, $header_row, escape: '\\' );

		}


        fclose( $fp );

		return $file;
    }

}

JC_Registry::set_instance( 'putter-cover', new JC_Putter_Cover_Controller());
$controller = JC_Registry::get_instance( 'putter-cover' );
