<?php
/**
 * JC Putter Cover Redemption Request
 * Works in conjunction with AR controller
 *
 * @package Journal_Member_Preferences
 */

//use DeliciousBrains\WP_Offload_Media\Aws3\Aws\WrappedHttpHandler;

/**
 * JC_Putter_Cover_Redemption_Request class.
 *
 */
class JC_Putter_Cover_Redemption_Request extends JC_Async_Report_Request {

    protected $action = "putter_cover_redemption_request";
	protected $per_step = 50;

    protected $context = [
        'source' => 'JC Putter Cover Redemptions'
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

			$redeem = '';

			switch ( $context['redeem'] ) {

				case 'blade' : 
				case 'mallet' :
				case 'square-mallet' :
					$redeem = $context['redeem'];
					break;
				case 'no-preference' :
					switch ( $context['no-preference-choice'] ) {
						case 'mallet' :
						case 'square-mallet' : 
						case 'blade' :
							$redeem = $context['no-preference-choice'];
							break;
						default:
							wc_get_logger()->warning( 'Unknown preference type', $this->context );
							return [];
					}
					break;
				default:
					wc_get_logger()->warning( 'Unknown redemption type', $this->context );
					return [];
			}

			$context['current_levels'] = [];
			
			if ( $redeem === 'blade' ) {
				$context['current_levels'] = 
					[ 
						'blade-black' => $context['blade-black'],
						'blade-white' => $context['blade-white'],
						'blade-green' => $context['blade-green']
					];
			}

			if ( $redeem === 'mallet' ) {
				$context['current_levels'] =
					[ 
						'mallet-black' => $context['mallet-black'],
						'mallet-white' => $context['mallet-white'],
						'mallet-tan' => $context['mallet-tan']
					];
			}


			if ( $redeem === 'square-mallet' ) {
				$context['current_levels'] =
					[ 
						'square-mallet-black' => $context['square-mallet-black'],
						'square-mallet-white' => $context['square-mallet-white'],
						'square-mallet-green' => $context['square-mallet-green']
					];
			}



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

		$entitlements = $data_store->get_entitlements_for_number( 5, 'all', $this->per_step, $offset, $cutoff );

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

		$redeem = '';

		switch ( $context['redeem'] ) {
			case 'blade' :
			case 'mallet' :
			case 'square-mallet' :
			case 'no-preference' :
				$redeem = $context['redeem'];
				break;
			default: 
				wc_get_logger()->warning( "Unknown redemption type", $this->context );
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

			// skip orders that occurred before 13 Feb 2022
			$date_bought = new DateTime( $entitlement->get_date_bought() );
			$ignore_date = new DateTime( '2023-02-13' );
			
			if ( $date_bought < $ignore_date ) {
				continue;
			}


			$current_levels = $context['current_levels'];

			// Check whether we've reached the limit
			if ( max( $current_levels ) <= 0 ) {
				break;
			}

			// Get the variation ID to use
			$variation_id = 0;
			$color = array_search( max( $context['current_levels']), $context['current_levels'] );

			$variation_id = $context[$color . '-id'];

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


			// Only process where preference matches the redemption type
			// Handle no preference
			if ( ( $context['redeem'] === 'no-preference' ) && $preference ) {
				continue;
			}
		
			if ( ( $context['redeem'] !== 'no-preference' ) && ( $preference !== $redeem ) ) {
				continue;
			}

			$product = wc_get_product( $variation_id );

			if ( !$product ) {
				wc_get_logger()->warning( 'Not a product for ' . $variation_id, $this->context );
				continue;
			}

			$order_id = journal_create_redemption_order( $entitlement, $product );


			$entitlement->set_redemption_order_id( $order_id );
			$entitlement->set_redemption_date( date( 'Y-m-d H:i:s') );
			$entitlement->set_redemption_gift( $product->get_name() . ' ' . $color );
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

JC_Registry::set_instance( 'putter-cover-redemption-request', new JC_Putter_Cover_Redemption_Request());
$request = JC_Registry::get_instance( 'putter-cover-redemption-request' );