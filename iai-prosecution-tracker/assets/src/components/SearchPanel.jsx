/**
 * File: assets/src/components/SearchPanel.jsx
 */

import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { Search, Loader, RefreshCw } from 'lucide-react';

function SearchPanel( { onFetchApplications, onLog } ) {
	const [ query, setQuery ] = useState( '' );
	const [ results, setResults ] = useState( [] );
	const [ selectedNames, setSelectedNames ] = useState( [] );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( null );

	const handleSearch = async ( e ) => {
		e?.preventDefault();
		if ( ! query.trim() ) {
			setError( 'Please enter a name' );
			return;
		}

		setLoading( true );
		setError( null );
		setResults( [] );
		setSelectedNames( [] );
		
		// Log the attempt
		if ( onLog ) onLog( 'INFO', `Searching for: ${ query }` );

		try {
			const path = addQueryArgs( '/iai/v1/search', {
				query: query.trim(),
				limit: 50,
				offset: 0,
			} );

			const response = await apiFetch( { path, method: 'GET' } );

			if ( response && response.applicant_names ) {
				setResults( response.applicant_names );
				if ( onLog ) onLog( 'SUCCESS', `Found ${ response.applicant_names.length } results` );
				
				if ( response.applicant_names.length === 0 ) {
					setError( 'No matches found.' );
				}
			} else {
				const msg = response.message || 'Search failed';
				setError( msg );
				if ( onLog ) onLog( 'WARNING', msg, response );
			}
		} catch ( err ) {
			const msg = err.message || 'Server error occurred during search';
			setError( msg );
			// Capture the full error object for the debug log
			if ( onLog ) onLog( 'ERROR', 'Search API Failed', err );
		} finally {
			setLoading( false );
		}
	};

	const handleToggle = ( name ) => {
		setSelectedNames( ( prev ) => 
			prev.includes( name ) ? prev.filter( ( n ) => n !== name ) : [ ...prev, name ]
		);
	};

	const handleFetch = () => {
		if ( selectedNames.length > 0 ) {
			onFetchApplications( selectedNames );
		}
	};

	return (
		<div className="iai-pt-search">
			<h3 className="iai-pt-search__title">Search Applicants</h3>
			<div className="iai-pt-search__input-group">
				<input
					type="text"
					className="iai-pt-search__input"
					placeholder="Enter name (e.g. Mira)..."
					value={ query }
					onChange={ ( e ) => setQuery( e.target.value ) }
					onKeyDown={ (e) => e.key === 'Enter' && handleSearch() }
					disabled={ loading }
				/>
				<button className="iai-pt-search__button" onClick={ handleSearch } disabled={ loading }>
					{ loading ? <Loader size={ 16 } className="iai-pt-spinner" /> : <Search size={ 16 } /> }
				</button>
			</div>

			{ error && <div className="iai-pt-search__error">{ error }</div> }

			{ results.length > 0 && (
				<div className="iai-pt-results-wrapper">
					<div className="iai-pt-search__results">
						{ results.map( ( result ) => (
							<div key={ result.name } className="iai-pt-search__result-item" onClick={ () => handleToggle( result.name ) }>
								<input type="checkbox" checked={ selectedNames.includes( result.name ) } readOnly />
								<span className="iai-pt-search__result-label">{ result.name } ({ result.count })</span>
							</div>
						) ) }
					</div>
					<button className="iai-pt-search__fetch-button" onClick={ handleFetch } disabled={ selectedNames.length === 0 }>
						<RefreshCw size={ 16 } /> Fetch Applications ({ selectedNames.length })
					</button>
				</div>
			) }
		</div>
	);
}

export default SearchPanel;
