/**
 * Fee Classifier - JavaScript version mirroring PHP taxonomy
 * MUST stay in sync with includes/models/class-fee-classifier.php
 */

export const CATEGORIES = {
	entity_status: {
		'BIG.': {
			label: 'Large Entity (Undiscounted)',
			icon: 'building',
			color: '#DC2626',
		},
		SMAL: {
			label: 'Small Entity',
			icon: 'store',
			color: '#2563EB',
		},
		MICR: {
			label: 'Micro Entity',
			icon: 'user',
			color: '#059669',
		},
	},
	filing_fee: {
		'FEE.': {
			label: 'Fee Payment',
			icon: 'dollar-sign',
			color: '#7C3AED',
		},
		FLFEE: {
			label: 'Additional Filing Fee',
			icon: 'dollar-sign',
			color: '#7C3AED',
		},
		ADDFLFEE: {
			label: 'Additional Filing Fees',
			icon: 'dollar-sign',
			color: '#7C3AED',
		},
	},
	issue_fee: {
		IFEE: {
			label: 'Issue Fee Paid',
			icon: 'award',
			color: '#D97706',
		},
		IFEEHA: {
			label: 'Issue Fee (Hague)',
			icon: 'award',
			color: '#D97706',
		},
	},
	rce: {
		BRCE: {
			label: 'RCE Filed',
			icon: 'refresh-cw',
			color: '#EC4899',
		},
		FRCE: {
			label: 'RCE Complete',
			icon: 'check-circle',
			color: '#EC4899',
		},
	},
	appeal: {
		'AP.B': {
			label: 'Appeal Brief Filed',
			icon: 'file-text',
			color: '#F59E0B',
		},
		'AP.C': {
			label: 'Pre-Appeal Conference',
			icon: 'users',
			color: '#F59E0B',
		},
		APOH: {
			label: 'Oral Hearing Request',
			icon: 'mic',
			color: '#F59E0B',
		},
	},
	milestone: {
		COMP: {
			label: 'Application Complete',
			icon: 'check',
			color: '#6B7280',
		},
		'371COMP': {
			label: '371 National Stage Complete',
			icon: 'globe',
			color: '#6B7280',
		},
		CTNF: {
			label: 'Non-Final Rejection',
			icon: 'x-circle',
			color: '#EF4444',
		},
		CTFR: {
			label: 'Final Rejection',
			icon: 'x-octagon',
			color: '#B91C1C',
		},
		DIST: {
			label: 'Terminal Disclaimer',
			icon: 'scissors',
			color: '#6B7280',
		},
		ABN6: {
			label: 'Abandoned (No Issue Fee)',
			icon: 'alert-triangle',
			color: '#991B1B',
		},
	},
	other_fee: {
		IRFND: {
			label: 'Refund Requested',
			icon: 'rotate-ccw',
			color: '#6B7280',
		},
		'EIDS.': {
			label: 'Electronic IDS',
			icon: 'list',
			color: '#6B7280',
		},
		M923: {
			label: '371 Supplemental Fees Missing',
			icon: 'alert-circle',
			color: '#DC2626',
		},
	},
};

/**
 * Classify a transaction code
 *
 * @param {string} code - Transaction code
 * @return {Object|null} - Classification object or null
 */
export function classify( code ) {
	for ( const [ category, codes ] of Object.entries( CATEGORIES ) ) {
		if ( codes[ code ] ) {
			return {
				category,
				...codes[ code ],
			};
		}
	}
	return null;
}

/**
 * Check if code is a fee event
 *
 * @param {string} code - Transaction code
 * @return {boolean} - True if the code is a fee event
 */
export function isFeeEvent( code ) {
	const classification = classify( code );
	if ( ! classification ) {
		return false;
	}
	const feeCategories = [ 'filing_fee', 'issue_fee', 'other_fee' ];
	return feeCategories.includes( classification.category );
}

/**
 * Check if code is an entity status change
 *
 * @param {string} code - Transaction code
 * @return {boolean} - True if the code represents an entity status change
 */
export function isEntityChange( code ) {
	const classification = classify( code );
	if ( ! classification ) {
		return false;
	}
	return classification.category === 'entity_status';
}

/**
 * Get all codes
 *
 * @return {Object} - Full taxonomy
 */
export function getAllCodes() {
	return CATEGORIES;
}

/**
 * Compute entity status timeline from events
 *
 * @param {Array} events - Array of event objects with date and code
 * @return {Array} - Timeline periods with from, to, status
 */
export function computeEntityTimeline( events ) {
	if ( ! events || events.length === 0 ) {
		return [];
	}

	// Sort events chronologically
	const sortedEvents = [ ...events ].sort( ( a, b ) =>
		a.date.localeCompare( b.date )
	);

	const timeline = [];
	let currentStatus = 'undiscounted';
	// Initialize currentFrom with the date of the very first event
	// This ensures the timeline starts at the beginning of prosecution
	let currentFrom = sortedEvents[ 0 ].date;

	const entityCodeMap = {
		'BIG.': 'undiscounted',
		SMAL: 'small',
		MICR: 'micro',
	};

	for ( const event of sortedEvents ) {
		const { code, date } = event;

		if ( entityCodeMap[ code ] ) {
			const newStatus = entityCodeMap[ code ];

			// Only close existing period if time has actually passed
			// This prevents 0-length periods if the first event is a status change
			if ( date > currentFrom ) {
				timeline.push( {
					from: currentFrom,
					to: date,
					status: currentStatus,
				} );
			}

			// Start new period
			currentStatus = newStatus;
			currentFrom = date;
		}
	}

	// Close final period (to = null means ongoing)
	timeline.push( {
		from: currentFrom,
		to: null,
		status: currentStatus,
	} );

	return timeline;
}

/**
 * Get entity rate active at a specific date
 *
 * @param {Array}  timeline - Entity timeline from computeEntityTimeline
 * @param {string} date     - Date in ISO format
 * @return {string} - Entity rate: 'small', 'micro', or 'undiscounted'
 */
export function getEntityRateAtDate( timeline, date ) {
	for ( const period of timeline ) {
		const { from, to, status } = period;

		if ( date >= from && ( to === null || date < to ) ) {
			return status;
		}
	}

	return 'undiscounted';
}
