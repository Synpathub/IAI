/**
 * Timeline Component
 * Main visualization showing prosecution timeline with events and entity status
 * 
 * @package IAI\ProsecutionTracker
 */

import { useState, useMemo, useRef } from '@wordpress/element';
import { Loader, AlertCircle, Calendar, FileText, Award } from 'lucide-react';
import * as Icons from 'lucide-react';
import { classify, computeEntityTimeline, getEntityRateAtDate } from '../utils/feeClassifier';
import EntityStatusBar from './EntityStatusBar';
import TimelineEvent from './TimelineEvent';
import EventDetailPopup from './EventDetailPopup';

function Timeline({ application, transactions, loading }) {
	const [filters, setFilters] = useState({
		fees: true,
		entity: true,
		milestones: true,
		rce: true,
		appeals: true,
	});
	const [selectedEvent, setSelectedEvent] = useState(null);
	const [popupPosition, setPopupPosition] = useState({ x: 0, y: 0 });
	const timelineRef = useRef(null);

	/**
	 * Process transactions into timeline events
	 */
	const timelineData = useMemo(() => {
		if (!transactions || !transactions.events || transactions.error) {
			return null;
		}

		// Classify all events
		const classifiedEvents = transactions.events.map(event => ({
			...event,
			classification: classify(event.code),
		}));

		// Filter out unclassified events
		const validEvents = classifiedEvents.filter(e => e.classification);

		// Compute entity timeline
		const entityTimeline = computeEntityTimeline(validEvents);

		// Add entity rate to each event
		const eventsWithEntity = validEvents.map(event => ({
			...event,
			entityRate: getEntityRateAtDate(entityTimeline, event.date),
		}));

		// Sort by date
		eventsWithEntity.sort((a, b) => a.date.localeCompare(b.date));

		return {
			events: eventsWithEntity,
			entityTimeline,
		};
	}, [transactions]);

	/**
	 * Filter events based on selected categories
	 */
	const filteredEvents = useMemo(() => {
		if (!timelineData) return [];

		return timelineData.events.filter(event => {
			const category = event.classification?.category;
			
			if (category === 'entity_status') return filters.entity;
			if (category === 'filing_fee' || category === 'issue_fee' || category === 'other_fee') {
				return filters.fees;
			}
			if (category === 'milestone') return filters.milestones;
			if (category === 'rce') return filters.rce;
			if (category === 'appeal') return filters.appeals;
			
			return true;
		});
	}, [timelineData, filters]);

	/**
	 * Calculate timeline dimensions and positions
	 */
	const timelineLayout = useMemo(() => {
		if (!filteredEvents.length) return null;

		const dates = filteredEvents.map(e => new Date(e.date));
		const minDate = new Date(Math.min(...dates));
		const maxDate = new Date(Math.max(...dates));
		
		// Add padding to date range
		const padding = (maxDate - minDate) * 0.1;
		const startDate = new Date(minDate.getTime() - padding);
		const endDate = new Date(maxDate.getTime() + padding);
		
		const totalDuration = endDate - startDate;
		const width = Math.max(1200, filteredEvents.length * 60);
		const height = 300;
		
		// Calculate x positions for events
		const eventsWithPosition = filteredEvents.map(event => {
			const eventDate = new Date(event.date);
			const elapsed = eventDate - startDate;
			const x = (elapsed / totalDuration) * (width - 100) + 50;
			
			return { ...event, x, y: height / 2 };
		});

		return {
			events: eventsWithPosition,
			width,
			height,
			startDate,
			endDate,
			minDate,
			maxDate,
		};
	}, [filteredEvents]);

	/**
	 * Generate year markers for timeline axis
	 */
	const yearMarkers = useMemo(() => {
		if (!timelineLayout) return [];

		const markers = [];
		const startYear = timelineLayout.minDate.getFullYear();
		const endYear = timelineLayout.maxDate.getFullYear();
		
		for (let year = startYear; year <= endYear; year++) {
			const yearDate = new Date(year, 0, 1);
			const elapsed = yearDate - timelineLayout.startDate;
			const totalDuration = timelineLayout.endDate - timelineLayout.startDate;
			const x = (elapsed / totalDuration) * (timelineLayout.width - 100) + 50;
			
			markers.push({ year, x });
		}
		
		return markers;
	}, [timelineLayout]);

	/**
	 * Handle event click
	 */
	const handleEventClick = (event, svgEvent) => {
		if (timelineRef.current) {
			const rect = timelineRef.current.getBoundingClientRect();
			setPopupPosition({
				x: event.x + 20,
				y: rect.top + event.y - 100,
			});
		}
		setSelectedEvent(event);
	};

	/**
	 * Handle filter toggle
	 */
	const handleFilterToggle = (filterKey) => {
		setFilters(prev => ({ ...prev, [filterKey]: !prev[filterKey] }));
	};

	/**
	 * Get icon component for legend
	 */
	const getIconComponent = (iconName) => {
		const pascalCase = iconName
			.split('-')
			.map(word => word.charAt(0).toUpperCase() + word.slice(1))
			.join('');
		return Icons[pascalCase] || Icons.Circle;
	};

	// Loading state
	if (loading) {
		return (
			<div className="iai-pt-timeline">
				<div className="iai-pt-timeline__loading">
					<Loader size={48} className="iai-pt-spinner" />
					<div className="iai-pt-timeline__loading-text">
						Loading transaction data...
					</div>
				</div>
			</div>
		);
	}

	// Error state
	if (!transactions || transactions.error) {
		return (
			<div className="iai-pt-timeline">
				<div className="iai-pt-timeline__error">
					<AlertCircle size={48} className="iai-pt-timeline__error-icon" />
					<div className="iai-pt-timeline__error-text">
						Failed to load timeline
					</div>
					<div className="iai-pt-timeline__error-detail">
						{transactions?.error || 'An error occurred while fetching transaction data.'}
					</div>
				</div>
			</div>
		);
	}

	// No data state
	if (!timelineData || !timelineLayout) {
		return (
			<div className="iai-pt-timeline">
				<div className="iai-pt-timeline__error">
					<AlertCircle size={48} className="iai-pt-timeline__error-icon" />
					<div className="iai-pt-timeline__error-text">
						No timeline data available
					</div>
				</div>
			</div>
		);
	}

	return (
		<div className="iai-pt-timeline">
			{/* Header with application details */}
			<div className="iai-pt-timeline__header">
				<h2 className="iai-pt-timeline__app-number">
					Application {application?.applicationNumberText || ''}
				</h2>
				<div className="iai-pt-timeline__app-title">
					{application?.inventionTitle || 'Title Not Available'}
				</div>
				<div className="iai-pt-timeline__app-meta">
					<div className="iai-pt-timeline__meta-item">
						<Calendar size={16} />
						<span className="iai-pt-timeline__meta-label">Filed:</span>
						<span>{application?.filingDate || 'N/A'}</span>
					</div>
					{application?.patentNumber && (
						<div className="iai-pt-timeline__meta-item">
							<Award size={16} />
							<span className="iai-pt-timeline__meta-label">Patent:</span>
							<span>US {application.patentNumber}</span>
						</div>
					)}
					<div className="iai-pt-timeline__meta-item">
						<FileText size={16} />
						<span className="iai-pt-timeline__meta-label">Status:</span>
						<span>{application?.applicationStatusDescriptionText || 'Unknown'}</span>
					</div>
				</div>
			</div>

			{/* Filters */}
			<div className="iai-pt-timeline__filters">
				<div className="iai-pt-timeline__filters-label">Filter Events:</div>
				<div className="iai-pt-timeline__filter-group">
					<label className="iai-pt-timeline__filter-item">
						<input
							type="checkbox"
							className="iai-pt-timeline__filter-checkbox"
							checked={filters.fees}
							onChange={() => handleFilterToggle('fees')}
						/>
						<span className="iai-pt-timeline__filter-label">Fees</span>
					</label>
					<label className="iai-pt-timeline__filter-item">
						<input
							type="checkbox"
							className="iai-pt-timeline__filter-checkbox"
							checked={filters.entity}
							onChange={() => handleFilterToggle('entity')}
						/>
						<span className="iai-pt-timeline__filter-label">Entity Status</span>
					</label>
					<label className="iai-pt-timeline__filter-item">
						<input
							type="checkbox"
							className="iai-pt-timeline__filter-checkbox"
							checked={filters.milestones}
							onChange={() => handleFilterToggle('milestones')}
						/>
						<span className="iai-pt-timeline__filter-label">Milestones</span>
					</label>
					<label className="iai-pt-timeline__filter-item">
						<input
							type="checkbox"
							className="iai-pt-timeline__filter-checkbox"
							checked={filters.rce}
							onChange={() => handleFilterToggle('rce')}
						/>
						<span className="iai-pt-timeline__filter-label">RCE</span>
					</label>
					<label className="iai-pt-timeline__filter-item">
						<input
							type="checkbox"
							className="iai-pt-timeline__filter-checkbox"
							checked={filters.appeals}
							onChange={() => handleFilterToggle('appeals')}
						/>
						<span className="iai-pt-timeline__filter-label">Appeals</span>
					</label>
				</div>
			</div>

			{/* Timeline Content */}
			<div className="iai-pt-timeline__content">
				{/* Entity Status Bar */}
				{timelineData.entityTimeline.length > 0 && (
					<EntityStatusBar
						timeline={timelineData.entityTimeline}
						startDate={timelineLayout.startDate}
						endDate={timelineLayout.endDate}
					/>
				)}

				{/* SVG Timeline */}
				<div className="iai-pt-timeline__svg-wrapper" ref={timelineRef}>
					<svg
						className="iai-pt-timeline__svg"
						width={timelineLayout.width}
						height={timelineLayout.height}
						viewBox={`0 0 ${timelineLayout.width} ${timelineLayout.height}`}
					>
						{/* Timeline axis */}
						<line
							x1="50"
							y1={timelineLayout.height / 2}
							x2={timelineLayout.width - 50}
							y2={timelineLayout.height / 2}
							stroke="#E5E7EB"
							strokeWidth="2"
						/>

						{/* Year markers */}
						{yearMarkers.map(marker => (
							<g key={marker.year}>
								<line
									x1={marker.x}
									y1={timelineLayout.height / 2 - 10}
									x2={marker.x}
									y2={timelineLayout.height / 2 + 10}
									stroke="#9CA3AF"
									strokeWidth="1"
								/>
								<text
									x={marker.x}
									y={timelineLayout.height / 2 + 30}
									textAnchor="middle"
									fontSize="12"
									fill="#6B7280"
								>
									{marker.year}
								</text>
							</g>
						))}

						{/* Events */}
						{timelineLayout.events.map((event, index) => (
							<TimelineEvent
								key={`${event.date}-${event.code}-${index}`}
								event={event}
								x={event.x}
								y={event.y}
								onClick={(e) => handleEventClick(event, e)}
							/>
						))}
					</svg>
				</div>

				{/* Legend */}
				<div className="iai-pt-timeline__legend">
					<div className="iai-pt-timeline__legend-title">Legend</div>
					<div className="iai-pt-timeline__legend-items">
						<div className="iai-pt-timeline__legend-item">
							<div className="iai-pt-timeline__legend-icon" style={{ color: '#2563EB' }}>
								{(() => {
									const StoreIcon = getIconComponent('store');
									return <StoreIcon size={20} />;
								})()}
							</div>
							<span>Small Entity</span>
						</div>
						<div className="iai-pt-timeline__legend-item">
							<div className="iai-pt-timeline__legend-icon" style={{ color: '#DC2626' }}>
								{(() => {
									const BuildingIcon = getIconComponent('building');
									return <BuildingIcon size={20} />;
								})()}
							</div>
							<span>Large Entity</span>
						</div>
						<div className="iai-pt-timeline__legend-item">
							<div className="iai-pt-timeline__legend-icon" style={{ color: '#7C3AED' }}>
								{(() => {
									const DollarIcon = getIconComponent('dollar-sign');
									return <DollarIcon size={20} />;
								})()}
							</div>
							<span>Fee Payment</span>
						</div>
						<div className="iai-pt-timeline__legend-item">
							<div className="iai-pt-timeline__legend-icon" style={{ color: '#EC4899' }}>
								{(() => {
									const RefreshIcon = getIconComponent('refresh-cw');
									return <RefreshIcon size={20} />;
								})()}
							</div>
							<span>RCE</span>
						</div>
						<div className="iai-pt-timeline__legend-item">
							<div className="iai-pt-timeline__legend-icon" style={{ color: '#EF4444' }}>
								{(() => {
									const XIcon = getIconComponent('x-circle');
									return <XIcon size={20} />;
								})()}
							</div>
							<span>Rejection</span>
						</div>
						<div className="iai-pt-timeline__legend-item">
							<div className="iai-pt-timeline__legend-icon" style={{ color: '#D97706' }}>
								{(() => {
									const AwardIcon = getIconComponent('award');
									return <AwardIcon size={20} />;
								})()}
							</div>
							<span>Issue Fee</span>
						</div>
					</div>
				</div>
			</div>

			{/* Event detail popup */}
			{selectedEvent && (
				<EventDetailPopup
					event={selectedEvent}
					position={popupPosition}
					onClose={() => setSelectedEvent(null)}
				/>
			)}
		</div>
	);
}

export default Timeline;
