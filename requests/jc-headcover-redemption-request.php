<?php
/**
 * JC Headcover Redemption Request
 * Works in conjunction with AR controller
 *
 * @package Journal_Member_Preferences
 */

//use DeliciousBrains\WP_Offload_Media\Aws3\Aws\WrappedHttpHandler;

/**
 * JC_Headcover_Redemption_Request class.
 *
 */
class JC_Headcover_Redemption_Request extends JC_Async_Report_Request {

    protected $action = "headcover_redemption_request";
	protected $per_step = 50;

    protected $context = [
        'source' => 'JC Headcover Redemptions'
    ];

    /**
     * Undocumented function
     *
     * @param JC_Async_Report $report
     * @param integer $step
     * @return array
     */
	protected function fetch_data( JC_Async_Report $report, int $step ) : array {

		$context = json_decode( $report->get_context(), true );

		// Check we have a redeem type specified
		if ( !isset( $context['redeem'] ) ) {
			wc_get_logger()->warning( 'No redemption type specified', $this->context );
			return [];
		}

		// Maybe initialise the current stock levels
		if ( !isset( $context['current_levels'] ) ) {

			$context['current_levels'] = [ 
				'tan' => $context['tan'],
				'white' => $context['white'],
				'black' => $context['black']
			];
		

			$report->set_context( json_encode( $context ) );
			$report->save();
 
		}

		$cutoff = false;

		if ( $context['redeem'] === 'no-preference' && isset( $context['cutoff'] ) ) {
			$cutoff = $context['cutoff'];
		}
		
		// Check if we've reached our stock limit
		$level = max( $context['current_levels'] );

		if ( $level <= 0 ) {
			return [];
		}

		$offset = 1 == $step ? 0 : $this->per_step * ( $step - 1);

		$data_store = WC_Data_Store::load( 'journal_premium_entitlement' );

		$entitlements = $data_store->get_entitlements_for_number( 6, 'all', $this->per_step, $offset, $cutoff);

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
		$context = json_decode( $report->get_context(), true );

		if ( !isset( $context['redeem'] ) ) {
			wc_get_logger()->warning( "No redemption type", $this->context );
            return false;
		}

        if ( !property_exists( $downloads[0], 'filename' ) ) {
            wc_get_logger()->warning( "No download file", $this->context );
            return false;
        }

        $fp = fopen( $downloads[0]->filename, 'a' );


		foreach ( $data as $entitlement ) {

			if ( $entitlement->get_redemption_date() != NULL || $entitlement->get_redemption_order_id() != '' ) {
				continue;
			}

			// Check whether we've reached the limit
			if ( max( $context['current_levels'] ) <= 0 ) {
				wc_get_logger()->info( 'Current levels exhausted ' . print_r( $context, true ), $this->context );
				break;
			}

			// Get the preferred colour

			$recipient_email = '';
			$recipient_id = '';
			$gifted = false;

			$subscription = wcs_get_subscription( $entitlement->get_subscription_id() );

            if ( !$subscription ) {
                wc_get_logger()->warning( 'not a subscription ' . $entitlement->get_subscription_id(), $this->context );
                continue;
            }

			if ( WCS_Gifting::is_gifted_subscription( $subscription ) ) {

				$gifted = true;
				$recipient_id = WCS_Gifting::get_recipient_user( $subscription );
				$preference = get_user_meta( $recipient_id, 'jc_headcover', true );
				
				$gift_recipient = get_userdata( $recipient_id );

				if ( $gift_recipient ) {
					$recipient_email = $gift_recipient->user_email;
				} else {
					$recipient_email = 'Not a valid user';
				}
				
    		} else {
				$preference = get_user_meta( $subscription->get_customer_id(), 'jc_headcover', true );
			}


			// Only process where preference matches the redemption type
			// Handle no preference
			if ( ( $context['redeem'] === 'no-preference' ) ) {

				if ( $preference ) {
					continue;
				}

				$color = array_search( max( $context['current_levels']), $context['current_levels'] );
				
			} else {

				if ( !$preference ) {
					continue;
				}

				// Move to next entitlement if color exhausted
				if ( $context['current_levels'][$preference] <= 0 ) {
					wc_get_logger()->info( 'color exhausted ' . $preference . ' Entitlement: ' . $entitlement->get_id(), $this->context );
					continue;
				} 

				$color = $preference;
				
			}

			// Get the variation ID to use
			$variation_id = 0;
			$variation_id = $context[$color . '-id'];

			$product = wc_get_product( $variation_id );

			if ( !$product ) {
				wc_get_logger()->warning( 'No a product for ' . $variation_id, $this->context );
				continue;
			}

			$order_id = journal_create_redemption_order( $entitlement, $product );

			$entitlement->set_redemption_order_id( $order_id );
			$entitlement->set_redemption_date( date( 'Y-m-d H:i:s') );
			$entitlement->set_redemption_gift( $product->get_name() );
			$entitlement->set_earned_income( $entitlement->get_deferred_amount() );
			$entitlement->set_deferred_amount( 0.0 );
	
			$entitlement->save();
			$context['current_levels'][$color]--;

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
				$entitlement->get_redemption_gift(),
				$entitlement->get_redemption_order_id()
			];

			fputcsv( $fp, $row, escape: '\\' );

		}

		// Save the updated stock levels
		$report->set_context( json_encode( $context ) );
		$report->save();

        fclose( $fp );
        
        return true;

    }

}

// Load the main request class.

JC_Registry::set_instance( 'headcover-redemption-request', new JC_Headcover_Redemption_Request());
$request = JC_Registry::get_instance( 'headcover-redemption-request' );