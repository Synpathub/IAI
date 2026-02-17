/**
 * Main App Component
 */

import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { FileSearch } from 'lucide-react';
import SearchPanel from './components/SearchPanel';
import ApplicationList from './components/ApplicationList';
import Timeline from './components/Timeline';

function App() {
	const [ applications, setApplications ] = useState( [] );
	const [ selectedApp, setSelectedApp ] = useState( null );
	const [ transactions, setTransactions ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ loadingTimeline, setLoadingTimeline ] = useState( false );

	const handleFetchApplications = async ( names ) => {
		setLoading( true );
		try {
			const response = await apiFetch( {
				path: '/iai/v1/applications',
				method: 'POST',
				data: { applicant_names: names },
			} );
			// Handle direct application list response
			if ( response && response.applications ) {
				setApplications( response.applications );
			}
		} catch ( error ) {
			console.error( 'Error fetching applications:', error );
		} finally {
			setLoading( false );
		}
	};

	const handleSelectApp = async ( appNumber ) => {
		setSelectedApp( appNumber );
		setLoadingTimeline( true );
		try {
			const response = await apiFetch( {
				path: `/iai/v1/transactions/${ appNumber }`,
				method: 'GET',
			} );
			if ( response && response.events ) {
				setTransactions( response );
			}
		} catch ( error ) {
			console.error( 'Error fetching transactions:', error );
		} finally {
			setLoadingTimeline( false );
		}
	};

	const getSelectedAppData = () => applications.find( a => a.applicationNumberText === selectedApp );

	return (
		<div className="iai-pt-container">
			<div className="iai-pt-sidebar">
				<SearchPanel onFetchApplications={ handleFetchApplications } />
				<ApplicationList 
					applications={ applications } 
					selectedApp={ selectedApp } 
					onSelectApp={ handleSelectApp } 
					loading={ loading } 
				/>
			</div>
			<div className="iai-pt-main">
				{ ! selectedApp ? (
					<div className="iai-pt-welcome">
						<FileSearch size={ 64 } />
						<h2>Patent Prosecution Fee Tracker</h2>
						<p>Search and select an application to view the timeline.</p>
					</div>
				) : (
					<Timeline application={ getSelectedAppData() } transactions={ transactions } loading={ loadingTimeline } />
				) }
			</div>
		</div>
	);
}

export default App;
