/**
 * SearchPanel Component
 * Applicant name search with checkbox list for selection
 */

import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Search, Loader, RefreshCw } from 'lucide-react';

function SearchPanel( { onFetchApplications } ) {
	const [ query, setQuery ] = useState( '' );
	const [ results, setResults ] = useState( [] );
	const [ selectedNames, setSelectedNames ] = useState( [] );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( null );

	/**
	 * Handle search submission
	 *
	 * @param {Event} e - Form submission event.
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
			const response = await apiFetch( {
				path: '/iai/v1/search',
				method: 'POST',
				data: {
					query: query.trim(),
					limit: 50,
					offset: 0,
				},
			} );

			if ( response.success && response.data ) {
				setResults( response.data );
				if ( response.data.length === 0 ) {
					setError(
						'No applicants found. Try a different search term.'
					);
				}
			} else {
				setError( response.message || 'Search failed' );
			}
		} catch ( err ) {
			// eslint-disable-next-line no-console
			console.error( 'Search error:', err );
			setError( err.message || 'Failed to search. Please try again.' );
		} finally {
			setLoading( false );
		}
	};

	/**
	 * Handle checkbox toggle
	 *
	 * @param {string} name - Applicant name to toggle.
	 */
	const handleToggle = ( name ) => {
		setSelectedNames( ( prev ) => {
			if ( prev.includes( name ) ) {
				return prev.filter( ( n ) => n !== name );
			}
			return [ ...prev, name ];
		} );
	};

	/**
	 * Select all results
	 */
	const handleSelectAll = () => {
		setSelectedNames( results.map( ( r ) => r.name ) );
	};

	/**
	 * Clear all selections
	 */
	const handleClear = () => {
		setSelectedNames( [] );
	};

	/**
	 * Handle fetch applications button
	 */
	const handleFetch = () => {
		if ( selectedNames.length > 0 ) {
			onFetchApplications( selectedNames );
		}
	};

	/**
	 * Handle Enter key in search input
	 *
	 * @param {Event} e - Keyboard event.
	 */
	const handleKeyPress = ( e ) => {
		if ( e.key === 'Enter' ) {
			handleSearch();
		}
	};

	return (
		<div className="iai-pt-search">
			<h3 className="iai-pt-search__title">Search Applicant Names</h3>

			<div className="iai-pt-search__input-group">
				<input
					type="text"
					className="iai-pt-search__input"
					placeholder="Use + for AND, * for wildcard"
					value={ query }
					onChange={ ( e ) => setQuery( e.target.value ) }
					onKeyPress={ handleKeyPress }
					disabled={ loading }
				/>
				<button
					className="iai-pt-search__button"
					onClick={ handleSearch }
					disabled={ loading }
					aria-label="Search"
				>
					{ loading ? (
						<Loader size={ 16 } className="iai-pt-spinner" />
					) : (
						<Search size={ 16 } />
					) }
				</button>
			</div>

			{ error && <div className="iai-pt-search__error">{ error }</div> }

			{ results.length > 0 && (
				<>
					<div className="iai-pt-search__results">
						{ results.map( ( result ) => (
							<div
								key={ result.name }
								className="iai-pt-search__result-item"
								onClick={ () => handleToggle( result.name ) }
							>
								<input
									type="checkbox"
									className="iai-pt-search__checkbox"
									checked={ selectedNames.includes(
										result.name
									) }
									onChange={ () =>
										handleToggle( result.name )
									}
									onClick={ ( e ) => e.stopPropagation() }
								/>
								<span className="iai-pt-search__result-label">
									{ result.name }
								</span>
								<span className="iai-pt-search__result-count">
									({ result.count })
								</span>
							</div>
						) ) }
					</div>

					<div className="iai-pt-search__actions">
						<button
							className="iai-pt-search__action-button"
							onClick={ handleSelectAll }
						>
							Select All
						</button>
						<button
							className="iai-pt-search__action-button"
							onClick={ handleClear }
						>
							Clear
						</button>
					</div>

					<button
						className="iai-pt-search__fetch-button"
						onClick={ handleFetch }
						disabled={ selectedNames.length === 0 }
					>
						<RefreshCw size={ 16 } />
						Fetch Applications ({ selectedNames.length })
					</button>
				</>
			) }
		</div>
	);
}

export default SearchPanel;
