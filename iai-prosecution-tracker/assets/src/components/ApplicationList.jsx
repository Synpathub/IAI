/**
 * ApplicationList Component
 * Scrollable list of patent applications with pagination
 * 
 * @package IAI\ProsecutionTracker
 */

import { useState, useMemo } from '@wordpress/element';
import { ChevronLeft, ChevronRight, Loader } from 'lucide-react';

const ITEMS_PER_PAGE = 25;

function ApplicationList({ applications, selectedApp, onSelectApp, loading }) {
	const [currentPage, setCurrentPage] = useState(1);
	const [sortBy, setSortBy] = useState('date-desc');

	/**
	 * Sort applications based on selected option
	 */
	const sortedApplications = useMemo(() => {
		const sorted = [...applications];
		
		switch (sortBy) {
			case 'date-desc':
				return sorted.sort((a, b) => 
					(b.filingDate || '').localeCompare(a.filingDate || '')
				);
			case 'date-asc':
				return sorted.sort((a, b) => 
					(a.filingDate || '').localeCompare(b.filingDate || '')
				);
			case 'status':
				return sorted.sort((a, b) => 
					(a.applicationStatusDescriptionText || '').localeCompare(
						b.applicationStatusDescriptionText || ''
					)
				);
			default:
				return sorted;
		}
	}, [applications, sortBy]);

	/**
	 * Calculate pagination
	 */
	const totalPages = Math.ceil(sortedApplications.length / ITEMS_PER_PAGE);
	const startIndex = (currentPage - 1) * ITEMS_PER_PAGE;
	const endIndex = startIndex + ITEMS_PER_PAGE;
	const currentApplications = sortedApplications.slice(startIndex, endIndex);

	/**
	 * Format application number (e.g., 16123456 -> 16/123,456)
	 */
	const formatAppNumber = (number) => {
		if (!number) return '';
		const str = number.toString().replace(/\D/g, '');
		if (str.length < 8) return number;
		
		const series = str.slice(0, 2);
		const serial = str.slice(2);
		const formatted = serial.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
		return `${series}/${formatted}`;
	};

	/**
	 * Truncate title to max length
	 */
	const truncateTitle = (title, maxLength = 60) => {
		if (!title) return '';
		return title.length > maxLength ? title.slice(0, maxLength) + '...' : title;
	};

	/**
	 * Handle page navigation
	 */
	const handlePrevPage = () => {
		setCurrentPage(prev => Math.max(1, prev - 1));
	};

	const handleNextPage = () => {
		setCurrentPage(prev => Math.min(totalPages, prev + 1));
	};

	/**
	 * Reset to page 1 when sort changes
	 */
	const handleSortChange = (e) => {
		setSortBy(e.target.value);
		setCurrentPage(1);
	};

	if (loading) {
		return (
			<div className="iai-pt-apps">
				<div className="iai-pt-apps__loading">
					<Loader size={32} className="iai-pt-spinner" />
					<div>Loading applications...</div>
				</div>
			</div>
		);
	}

	if (applications.length === 0) {
		return (
			<div className="iai-pt-apps">
				<div className="iai-pt-apps__empty">
					No applications loaded. Search for applicants above.
				</div>
			</div>
		);
	}

	return (
		<div className="iai-pt-apps">
			<div className="iai-pt-apps__header">
				<h3 className="iai-pt-apps__title">
					Applications ({sortedApplications.length})
				</h3>
				<select
					className="iai-pt-apps__sort"
					value={sortBy}
					onChange={handleSortChange}
				>
					<option value="date-desc">Newest First</option>
					<option value="date-asc">Oldest First</option>
					<option value="status">By Status</option>
				</select>
			</div>

			<div className="iai-pt-apps__list">
				{currentApplications.map((app) => (
					<div
						key={app.applicationNumberText}
						className={`iai-pt-apps__card ${
							selectedApp === app.applicationNumberText
								? 'iai-pt-apps__card--selected'
								: ''
						}`}
						onClick={() => onSelectApp(app.applicationNumberText)}
					>
						<div className="iai-pt-apps__card-header">
							<div className="iai-pt-apps__card-number">
								{formatAppNumber(app.applicationNumberText)}
							</div>
							<div className="iai-pt-apps__card-date">
								{app.filingDate}
							</div>
						</div>
						
						{app.patentNumber && (
							<div className="iai-pt-apps__card-patent">
								US {app.patentNumber} — Patented
							</div>
						)}
						
						<div className="iai-pt-apps__card-title">
							{truncateTitle(app.inventionTitle)}
						</div>
						
						<div className="iai-pt-apps__card-status">
							{app.applicationStatusDescriptionText || 'Status Unknown'}
						</div>
					</div>
				))}
			</div>

			{totalPages > 1 && (
				<div className="iai-pt-apps__pagination">
					<div className="iai-pt-apps__page-info">
						Page {currentPage} of {totalPages}
					</div>
					<div className="iai-pt-apps__page-buttons">
						<button
							className="iai-pt-apps__page-button"
							onClick={handlePrevPage}
							disabled={currentPage === 1}
						>
							<ChevronLeft size={16} />
							Prev
						</button>
						<button
							className="iai-pt-apps__page-button"
							onClick={handleNextPage}
							disabled={currentPage === totalPages}
						>
							Next
							<ChevronRight size={16} />
						</button>
					</div>
				</div>
			)}
		</div>
	);
}

export default ApplicationList;
