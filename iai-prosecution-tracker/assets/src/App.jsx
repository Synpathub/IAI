/**
 * File: assets/src/App.jsx
 */

import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { FileSearch } from 'lucide-react';
import SearchPanel from './components/SearchPanel';
import ApplicationList from './components/ApplicationList';
import Timeline from './components/Timeline';
import DebugLog from './components/DebugLog';

function App() {
	const [ applications, setApplications ] = useState( [] );
	const [ selectedApp, setSelectedApp ] = useState( null );
	const [ transactions, setTransactions ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ loadingTimeline, setLoadingTimeline ] = useState( false );
	const [ logs, setLogs ] = useState( [] );

	/**
	 * Helper to add logs to the debug console
	 */
	const addLog = ( type, message, data = null ) => {
		const entry = {
			timestamp: new Date().toLocaleTimeString(),
			type,
			message,
			data,
		};
		setLogs( ( prev ) => [ ...prev, entry ] );
		// Also log to browser console for backup
		if ( type === 'ERROR' ) console.error( message, data );
		else console.log( message, data );
	};

	const handleFetchApplications = async ( names ) => {
		setLoading( true );
		setSelectedApp( null );
		setTransactions( null );
		addLog( 'INFO', `Fetching applications for ${ names.length } applicants`, names );

		try {
			const response = await apiFetch( {
				path: '/iai/v1/applications',
				method: 'POST',
				data: { applicant_names: names },
			} );

			if ( response && response.applications ) {
				setApplications( response.applications );
				addLog( 'SUCCESS', `Found ${ response.total } applications` );
			} else {
				addLog( 'WARNING', 'Unexpected response format', response );
			}
		} catch ( error ) {
			addLog( 'ERROR', 'Failed to fetch applications', error );
		} finally {
			setLoading( false );
		}
	};

	const handleSelectApp = async ( appNumber ) => {
		setSelectedApp( appNumber );
		setTransactions( null );
		setLoadingTimeline( true );
		addLog( 'INFO', `Fetching transactions for ${ appNumber }` );

		try {
			const response = await apiFetch( {
				path: `/iai/v1/transactions/${ encodeURIComponent( appNumber ) }`,
				method: 'GET',
			} );

			if ( response && response.events ) {
				setTransactions( response );
				addLog( 'SUCCESS', `Loaded ${ response.events.length } events` );
			} else {
				addLog( 'WARNING', 'No events found or invalid format', response );
			}
		} catch ( error ) {
			addLog( 'ERROR', 'Failed to fetch timeline', error );
		} finally {
			setLoadingTimeline( false );
		}
	};

	const getSelectedAppData = () => applications.find( a => a.applicationNumberText === selectedApp );

	return (
		<div className="iai-pt-wrapper">
			<div className="iai-pt-container">
				<div className="iai-pt-sidebar">
					<SearchPanel 
						onFetchApplications={ handleFetchApplications } 
						onLog={ addLog } 
					/>
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
						<Timeline 
							application={ getSelectedAppData() } 
							transactions={ transactions } 
							loading={ loadingTimeline } 
						/>
					) }
				</div>
			</div>
			
			<DebugLog logs={ logs } onClear={ () => setLogs( [] ) } />
		</div>
	);
}

export default App;
