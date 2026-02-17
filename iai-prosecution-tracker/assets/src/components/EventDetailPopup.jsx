/**
 * EventDetailPopup Component
 * Popup showing detailed information about a timeline event
 */

import { X } from 'lucide-react';

function EventDetailPopup( { event, position, onClose } ) {
	if ( ! event ) {
		return null;
	}

	return (
		<div
			className="iai-pt-timeline__popup"
			style={ {
				left: `${ position.x }px`,
				top: `${ position.y }px`,
			} }
		>
			<div className="iai-pt-timeline__popup-header">
				<h4 className="iai-pt-timeline__popup-title">
					{ event.classification?.label || event.code }
				</h4>
				<button
					className="iai-pt-timeline__popup-close"
					onClick={ onClose }
					aria-label="Close"
				>
					<X size={ 16 } />
				</button>
			</div>

			<div className="iai-pt-timeline__popup-content">
				<div className="iai-pt-timeline__popup-row">
					<span className="iai-pt-timeline__popup-label">Date:</span>
					<span className="iai-pt-timeline__popup-value">
						{ event.date }
					</span>
				</div>

				<div className="iai-pt-timeline__popup-row">
					<span className="iai-pt-timeline__popup-label">Code:</span>
					<span className="iai-pt-timeline__popup-value">
						{ event.code }
					</span>
				</div>

				{ event.description && (
					<div className="iai-pt-timeline__popup-row">
						<span className="iai-pt-timeline__popup-label">
							Description:
						</span>
						<span className="iai-pt-timeline__popup-value">
							{ event.description }
						</span>
					</div>
				) }

				{ event.classification && (
					<div className="iai-pt-timeline__popup-row">
						<span className="iai-pt-timeline__popup-label">
							Category:
						</span>
						<span
							className="iai-pt-timeline__popup-category"
							style={ {
								backgroundColor:
									event.classification.color + '20',
								color: event.classification.color,
							} }
						>
							{ event.classification.category.replace(
								'_',
								' '
							) }
						</span>
					</div>
				) }

				{ event.entityRate && (
					<div className="iai-pt-timeline__popup-row">
						<span className="iai-pt-timeline__popup-label">
							Entity:
						</span>
						<span className="iai-pt-timeline__popup-value">
							{ event.entityRate.charAt( 0 ).toUpperCase() +
								event.entityRate.slice( 1 ) }
						</span>
					</div>
				) }
			</div>
		</div>
	);
}

export default EventDetailPopup;
