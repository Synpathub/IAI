/**
 * TimelineEvent Component
 * Single event icon on the timeline
 */

import * as Icons from 'lucide-react';

function TimelineEvent( { event, x, y, onClick } ) {
	if ( ! event.classification ) {
		return null;
	}

	// Get the icon component from lucide-react
	const getIcon = ( iconName ) => {
		// Convert icon name from kebab-case to PascalCase
		// e.g., 'dollar-sign' -> 'DollarSign'
		const pascalCase = iconName
			.split( '-' )
			.map( ( word ) => word.charAt( 0 ).toUpperCase() + word.slice( 1 ) )
			.join( '' );

		return Icons[ pascalCase ] || Icons.Circle;
	};

	const IconComponent = getIcon( event.classification.icon );

	return (
		<g
			className="iai-pt-timeline__event"
			transform={ `translate(${ x }, ${ y })` }
			onClick={ () => onClick( event ) }
			style={ { cursor: 'pointer' } }
		>
			<circle
				className="iai-pt-timeline__event-circle"
				r="16"
				fill={ event.classification.color }
				stroke="#fff"
				strokeWidth="2"
			/>
			<foreignObject x="-10" y="-10" width="20" height="20">
				<div
					style={ {
						display: 'flex',
						alignItems: 'center',
						justifyContent: 'center',
						width: '100%',
						height: '100%',
					} }
				>
					<IconComponent size={ 14 } color="#fff" />
				</div>
			</foreignObject>
		</g>
	);
}

export default TimelineEvent;
