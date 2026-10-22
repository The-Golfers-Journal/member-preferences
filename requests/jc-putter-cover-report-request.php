<?php
/**
 * JC Putter Cover Report Request
 * Base class for handling async report requests
 * Works in conjunction with AR controller
 *
 * @package Journal_Member_Preferences
 */

//use DeliciousBrains\WP_Offload_Media\Aws3\Aws\WrappedHttpHandler;

/**
 * JC_Putter_Cover_Report_Request class.
 *
 */
class JC_Putter_Cover_Report_Request extends JC_Async_Report_Request {

    protected $action = "putter_cover_report_request";

    protected $context = [
        'source' => 'JC Putter Cover Reports'
    ];

    /**
     * Undocumented function
     *
     * @param JC_Async_Report $report
     * @param integer $step
     * @return array
     */
	protected function fetch_data( JC_Async_Report $report, int $step ) : array {

		$offset = 1 == $step ? 0 : $this->per_step * ( $step - 1);

		$data_store = WC_Data_Store::load( 'journal_premium_entitlement' );

		$entitlements = $data_store->get_entitlements_for_number( 5, 'all', $this->per_step, $offset );

        return $entitlements;
    }

    /**
     * Undocumented function
     *
     * @param array $data
     * @param JC_Async_Report $report
     * @param integer $step
     * @return boolean
     */
    protected function process_data( array $data, JC_Async_Report $report, int $step ) : bool {

        $downloads = json_decode( $report->get_downloads() );

        if ( !property_exists( $downloads[0], 'filename' ) ) {
            wc_get_logger()->warning( "No download file", $this->context );
            return false;
        }

        $fp = fopen( $downloads[0]->filename, 'a' );

		foreach ( $data as $entitlement ) {

			$subscription = wcs_get_subscription( $entitlement->get_subscription_id() );

            if ( !$subscription ) {
                wc_get_logger()->warning( 'not a subscription ' . $entitlement->get_subscription_id(), $this->context );
                continue;
            }

			$recipient_email = '';
			$recipient_id = '';
			$gifted = false;


			if ( WCS_Gifting::is_gifted_subscription( $subscription ) ) {

				$gifted = true;
				$recipient_id = WCS_Gifting::get_recipient_user( $subscription );
				$preference = get_user_meta( $recipient_id, 'jc_putter_type', true );
				
				$gift_recipient = get_userdata( $recipient_id );

				if ( $gift_recipient ) {
					$recipient_email = $gift_recipient->user_email;
				} else {
					$recipient_email = 'Not a valid user';
				}
				
    		} else {
				$preference = get_user_meta( $subscription->get_customer_id(), 'jc_putter_type', true );
			}

            $redeemed = $entitlement->get_redemption_date() !== NULL ? true : false;

			$row = [
				$subscription->get_id(),
				$subscription->get_customer_id(),
				$subscription->get_billing_email(),
				$gifted,
				$recipient_id,
				$recipient_email,
				$subscription->get_shipping_first_name(),
				$subscription->get_shipping_last_name(),
				$preference,
                $redeemed,
				$entitlement->get_redemption_order_id()
			];

			fputcsv( $fp, $row, escape: '\\' );

		}

        fclose( $fp );
        
        return true;

    }

}

// Load the main request class.

JC_Registry::set_instance( 'putter-cover-report', new JC_Putter_Cover_Report_Request());
$request = JC_Registry::get_instance( 'putter-cover-report' );