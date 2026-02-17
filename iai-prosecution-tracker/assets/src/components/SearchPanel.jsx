/**
 * SearchPanel Component
 * Applicant name search with checkbox list for selection
 */

import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { Search, Loader, RefreshCw } from 'lucide-react';

function SearchPanel( { onFetchApplications } ) {
	const [ query, setQuery ] = useState( '' );
	const [ results, setResults ] = useState( [] );
	const [ selectedNames, setSelectedNames ] = useState( [] );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( null );

	/**
	 * Handle search submission using GET to align with USPTO search capabilities
	 */
	const handleSearch = async ( e ) => {
		e?.preventDefault();

		if ( ! query.trim() ) {
			setError( 'Please enter a search query' );
			return;
		}

		setLoading( true );
		setError( null );
		setResults( [] );
		setSelectedNames( [] );

		try {
			const path = addQueryArgs( '/iai/v1/search', {
				query: query.trim(),
				limit: 50,
				offset: 0,
			} );

			const response = await apiFetch( {
				path: path,
				method: 'GET',
			} );

			// Check for direct data property in the custom REST response
			if ( response && response.applicant_names ) {
				setResults( response.applicant_names );
				if ( response.applicant_names.length === 0 ) {
					setError( 'No applicants found.' );
				}
			} else {
				setError( response.message || 'Search failed' );
			}
		} catch ( err ) {
			setError( err.message || 'Failed to search.' );
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
			<h3 className="iai-pt-search__title">Search Applicant Names</h3>
			<div className="iai-pt-search__input-group">
				<input
					type="text"
					className="iai-pt-search__input"
					placeholder="Enter applicant name..."
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
						<RefreshCw size={ 16 } />
						Fetch Applications ({ selectedNames.length })
					</button>
				</div>
			) }
		</div>
	);
}

export default SearchPanel;
