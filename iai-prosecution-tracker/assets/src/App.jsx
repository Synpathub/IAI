/**
 * Main App Component
 * Two-panel layout with search/applications sidebar and timeline main area
 * 
 * @package IAI\ProsecutionTracker
 */

import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { FileSearch } from 'lucide-react';
import SearchPanel from './components/SearchPanel';
import ApplicationList from './components/ApplicationList';
import Timeline from './components/Timeline';

function App() {
	const [selectedNames, setSelectedNames] = useState([]);
	const [applications, setApplications] = useState([]);
	const [selectedApp, setSelectedApp] = useState(null);
	const [transactions, setTransactions] = useState(null);
	const [loading, setLoading] = useState(false);
	const [loadingTimeline, setLoadingTimeline] = useState(false);

	/**
	 * Handle fetching applications for selected applicant names
	 */
	const handleFetchApplications = async (names) => {
		if (!names || names.length === 0) return;

		setLoading(true);
		setSelectedNames(names);
		setApplications([]);
		setSelectedApp(null);
		setTransactions(null);

		try {
			const response = await apiFetch({
				path: '/iai/v1/applications',
				method: 'POST',
				data: {
					applicant_names: names,
					limit: 100,
					offset: 0,
				},
			});

			if (response.success && response.data) {
				setApplications(response.data);
			} else {
				console.error('Failed to fetch applications:', response.message);
			}
		} catch (error) {
			console.error('Error fetching applications:', error);
		} finally {
			setLoading(false);
		}
	};

	/**
	 * Handle selecting an application to view timeline
	 */
	const handleSelectApp = async (appNumber) => {
		if (appNumber === selectedApp) return;

		setSelectedApp(appNumber);
		setTransactions(null);
		setLoadingTimeline(true);

		try {
			const response = await apiFetch({
				path: `/iai/v1/transactions/${appNumber}`,
				method: 'GET',
			});

			if (response.success && response.data) {
				setTransactions(response.data);
			} else {
				console.error('Failed to fetch transactions:', response.message);
			}
		} catch (error) {
			console.error('Error fetching transactions:', error);
			setTransactions({ error: error.message || 'Failed to load timeline data' });
		} finally {
			setLoadingTimeline(false);
		}
	};

	/**
	 * Get selected application metadata
	 */
	const getSelectedAppData = () => {
		if (!selectedApp) return null;
		return applications.find(app => app.applicationNumberText === selectedApp);
	};

	return (
		<div className="iai-pt-container">
			<div className="iai-pt-sidebar">
				<SearchPanel onFetchApplications={handleFetchApplications} />
				<ApplicationList
					applications={applications}
					selectedApp={selectedApp}
					onSelectApp={handleSelectApp}
					loading={loading}
				/>
			</div>
			<div className="iai-pt-main">
				{!selectedApp ? (
					<div className="iai-pt-welcome">
						<div className="iai-pt-welcome__icon">
							<FileSearch size={64} />
						</div>
						<h2 className="iai-pt-welcome__title">
							Patent Prosecution Fee Tracker
						</h2>
						<p className="iai-pt-welcome__text">
							Search for applicant names and select an application to view its prosecution timeline and fee history.
						</p>
					</div>
				) : (
					<Timeline
						application={getSelectedAppData()}
						transactions={transactions}
						loading={loadingTimeline}
					/>
				)}
			</div>
		</div>
	);
}

export default App;
