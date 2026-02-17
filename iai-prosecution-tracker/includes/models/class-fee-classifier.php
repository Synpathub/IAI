<?php
/**
 * Fee Classifier - USPTO Transaction Code Taxonomy
 *
 * @package IAI\ProsecutionTracker\Models
 */

namespace IAI\ProsecutionTracker\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fee_Classifier class - Classifies USPTO transaction codes into categories
 */
class Fee_Classifier {

	/**
	 * Transaction code taxonomy
	 *
	 * @var array
	 */
	const CATEGORIES = array(
		'entity_status' => array(
			'BIG.'  => array(
				'label' => 'Large Entity (Undiscounted)',
				'icon'  => 'building',
				'color' => '#DC2626',
			),
			'SMAL'  => array(
				'label' => 'Small Entity',
				'icon'  => 'store',
				'color' => '#2563EB',
			),
			'MICR'  => array(
				'label' => 'Micro Entity',
				'icon'  => 'user',
				'color' => '#059669',
			),
		),
		'filing_fee'    => array(
			'FEE.'     => array(
				'label' => 'Fee Payment',
				'icon'  => 'dollar-sign',
				'color' => '#7C3AED',
			),
			'FLFEE'    => array(
				'label' => 'Additional Filing Fee',
				'icon'  => 'dollar-sign',
				'color' => '#7C3AED',
			),
			'ADDFLFEE' => array(
				'label' => 'Additional Filing Fees',
				'icon'  => 'dollar-sign',
				'color' => '#7C3AED',
			),
		),
		'issue_fee'     => array(
			'IFEE'   => array(
				'label' => 'Issue Fee Paid',
				'icon'  => 'award',
				'color' => '#D97706',
			),
			'IFEEHA' => array(
				'label' => 'Issue Fee (Hague)',
				'icon'  => 'award',
				'color' => '#D97706',
			),
		),
		'rce'           => array(
			'BRCE' => array(
				'label' => 'RCE Filed',
				'icon'  => 'refresh-cw',
				'color' => '#EC4899',
			),
			'FRCE' => array(
				'label' => 'RCE Complete',
				'icon'  => 'check-circle',
				'color' => '#EC4899',
			),
		),
		'appeal'        => array(
			'AP.B' => array(
				'label' => 'Appeal Brief Filed',
				'icon'  => 'file-text',
				'color' => '#F59E0B',
			),
			'AP.C' => array(
				'label' => 'Pre-Appeal Conference',
				'icon'  => 'users',
				'color' => '#F59E0B',
			),
			'APOH' => array(
				'label' => 'Oral Hearing Request',
				'icon'  => 'mic',
				'color' => '#F59E0B',
			),
		),
		'milestone'     => array(
			'COMP'    => array(
				'label' => 'Application Complete',
				'icon'  => 'check',
				'color' => '#6B7280',
			),
			'371COMP' => array(
				'label' => '371 National Stage Complete',
				'icon'  => 'globe',
				'color' => '#6B7280',
			),
			'CTNF'    => array(
				'label' => 'Non-Final Rejection',
				'icon'  => 'x-circle',
				'color' => '#EF4444',
			),
			'CTFR'    => array(
				'label' => 'Final Rejection',
				'icon'  => 'x-octagon',
				'color' => '#B91C1C',
			),
			'DIST'    => array(
				'label' => 'Terminal Disclaimer',
				'icon'  => 'scissors',
				'color' => '#6B7280',
			),
			'ABN6'    => array(
				'label' => 'Abandoned (No Issue Fee)',
				'icon'  => 'alert-triangle',
				'color' => '#991B1B',
			),
		),
		'other_fee'     => array(
			'IRFND' => array(
				'label' => 'Refund Requested',
				'icon'  => 'rotate-ccw',
				'color' => '#6B7280',
			),
			'EIDS.' => array(
				'label' => 'Electronic IDS',
				'icon'  => 'list',
				'color' => '#6B7280',
			),
			'M923'  => array(
				'label' => '371 Supplemental Fees Missing',
				'icon'  => 'alert-circle',
				'color' => '#DC2626',
			),
		),
	);

	/**
	 * Classify a transaction code
	 *
	 * @param string $code Transaction code.
	 * @return array|null Array with category, label, icon, color or null if not found.
	 */
	public function classify( $code ) {
		foreach ( self::CATEGORIES as $category => $codes ) {
			if ( isset( $codes[ $code ] ) ) {
				return array_merge(
					array( 'category' => $category ),
					$codes[ $code ]
				);
			}
		}
		return null;
	}

	/**
	 * Check if a code is a fee event
	 *
	 * @param string $code Transaction code.
	 * @return bool True if the code is a fee event.
	 */
	public function is_fee_event( $code ) {
		$classification = $this->classify( $code );
		if ( ! $classification ) {
			return false;
		}
		$fee_categories = array( 'filing_fee', 'issue_fee', 'other_fee' );
		return in_array( $classification['category'], $fee_categories, true );
	}

	/**
	 * Check if a code is an entity status change
	 *
	 * @param string $code Transaction code.
	 * @return bool True if the code is an entity status change.
	 */
	public function is_entity_change( $code ) {
		$classification = $this->classify( $code );
		if ( ! $classification ) {
			return false;
		}
		return 'entity_status' === $classification['category'];
	}

	/**
	 * Get all transaction codes
	 *
	 * @return array Full CATEGORIES constant.
	 */
	public function get_all_codes() {
		return self::CATEGORIES;
	}

	/**
	 * Compute entity status timeline from events
	 *
	 * Walks events chronologically, tracks BIG./SMAL/MICR changes.
	 * Default starting status is 'undiscounted' if no entity event precedes first fee.
	 *
	 * @param array $events Array of event objects with 'date' and 'code' keys.
	 * @return array Array of timeline periods with 'from', 'to', and 'status'.
	 */
	public function compute_entity_timeline( $events ) {
		// Sort events by date chronologically
		usort( $events, function ( $a, $b ) {
			return strcmp( $a['date'], $b['date'] );
		});

		$timeline        = array();
		$current_status  = 'undiscounted'; // Default starting status
		$current_from    = null;
		$entity_code_map = array(
			'BIG.'  => 'undiscounted',
			'SMAL'  => 'small',
			'MICR'  => 'micro',
		);

		foreach ( $events as $event ) {
			$code = $event['code'];
			
			// Check if this is an entity status change
			if ( isset( $entity_code_map[ $code ] ) ) {
				$new_status = $entity_code_map[ $code ];
				
				// If we have an existing period, close it
				if ( null !== $current_from ) {
					$timeline[] = array(
						'from'   => $current_from,
						'to'     => $event['date'],
						'status' => $current_status,
					);
				}
				
				// Start a new period
				$current_status = $new_status;
				$current_from   = $event['date'];
			}
		}

		// Close the final period (to = null means ongoing)
		if ( null !== $current_from ) {
			$timeline[] = array(
				'from'   => $current_from,
				'to'     => null,
				'status' => $current_status,
			);
		}

		return $timeline;
	}

	/**
	 * Get the entity rate active at a given date
	 *
	 * @param array  $timeline Entity status timeline from compute_entity_timeline().
	 * @param string $date     Date in ISO format (YYYY-MM-DD).
	 * @return string Entity rate: 'small', 'micro', or 'undiscounted'.
	 */
	public function get_entity_rate_at_date( $timeline, $date ) {
		foreach ( $timeline as $period ) {
			$from = $period['from'];
			$to   = $period['to'];
			
			// Check if date falls within this period
			if ( $date >= $from && ( null === $to || $date < $to ) ) {
				return $period['status'];
			}
		}
		
		// Default to undiscounted if no matching period found
		return 'undiscounted';
	}
}
