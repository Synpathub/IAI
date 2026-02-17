/**
 * File: assets/src/components/DebugLog.jsx
 * A developer-friendly log panel to capture and copy errors/API responses.
 */

import { useState, useEffect, useRef } from '@wordpress/element';
import { Copy, Terminal, ChevronDown, ChevronUp, Trash2 } from 'lucide-react';

const DebugLog = ( { logs, onClear } ) => {
	const [ isOpen, setIsOpen ] = useState( true );
	const [ copyFeedback, setCopyFeedback ] = useState( 'Copy Log' );
	const endRef = useRef( null );

	// Auto-scroll to bottom when new logs arrive
	useEffect( () => {
		if ( isOpen && endRef.current ) {
			endRef.current.scrollIntoView( { behavior: 'smooth' } );
		}
	}, [ logs, isOpen ] );

	if ( ! logs || logs.length === 0 ) {
		return null;
	}

	const handleCopy = () => {
		const text = logs
			.map(
				( l ) =>
					`[${ l.timestamp }] [${ l.type }]\nMessage: ${ l.message }\nData: ${ l.data ? JSON.stringify( l.data, null, 2 ) : 'N/A' }\n-----------------------------------`
			)
			.join( '\n' );

		navigator.clipboard.writeText( text ).then( () => {
			setCopyFeedback( 'Copied!' );
			setTimeout( () => setCopyFeedback( 'Copy Log' ), 2000 );
		} );
	};

	return (
		<div className="iai-pt-debug-container">
			<div className="iai-pt-debug-header" onClick={ () => setIsOpen( ! isOpen ) }>
				<div className="iai-pt-debug-title">
					<Terminal size={ 16 } />
					<span>System Logs ({ logs.length })</span>
				</div>
				<div className="iai-pt-debug-actions">
					<button
						className="iai-pt-debug-btn"
						onClick={ ( e ) => {
							e.stopPropagation();
							handleCopy();
						} }
					>
						<Copy size={ 14 } /> { copyFeedback }
					</button>
					<button
						className="iai-pt-debug-btn"
						onClick={ ( e ) => {
							e.stopPropagation();
							onClear();
						} }
					>
						<Trash2 size={ 14 } /> Clear
					</button>
					{ isOpen ? <ChevronDown size={ 16 } /> : <ChevronUp size={ 16 } /> }
				</div>
			</div>

			{ isOpen && (
				<div className="iai-pt-debug-content">
					{ logs.map( ( log, i ) => (
						<div key={ i } className={ `iai-pt-log-entry iai-pt-log-${ log.type.toLowerCase() }` }>
							<div className="iai-pt-log-meta">
								<span className="iai-pt-log-time">{ log.timestamp }</span>
								<span className="iai-pt-log-tag">{ log.type }</span>
							</div>
							<div className="iai-pt-log-message">{ log.message }</div>
							{ log.data && (
								<pre className="iai-pt-log-data">
									{ JSON.stringify( log.data, null, 2 ) }
								</pre>
							) }
						</div>
					) ) }
					<div ref={ endRef } />
				</div>
			) }
		</div>
	);
};

export default DebugLog;
