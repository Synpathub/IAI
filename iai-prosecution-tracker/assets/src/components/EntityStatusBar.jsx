/**
 * EntityStatusBar Component
 * Displays colored horizontal bands showing entity status changes over time
 * 
 * @package IAI\ProsecutionTracker
 */

function EntityStatusBar({ timeline, startDate, endDate }) {
	if (!timeline || timeline.length === 0) return null;

	/**
	 * Calculate the width percentage for each band
	 */
	const calculateBandWidth = (from, to) => {
		const start = new Date(startDate).getTime();
		const end = new Date(endDate).getTime();
		const totalDuration = end - start;

		const bandStart = new Date(from).getTime();
		const bandEnd = to ? new Date(to).getTime() : end;

		const bandDuration = bandEnd - bandStart;
		return (bandDuration / totalDuration) * 100;
	};

	return (
		<div className="iai-pt-timeline__entity-bar">
			<h4 className="iai-pt-timeline__entity-title">Entity Status</h4>
			<div className="iai-pt-timeline__entity-bands">
				{timeline.map((period, index) => (
					<div
						key={index}
						className={`iai-pt-timeline__entity-band iai-pt-timeline__entity-band--${period.status}`}
						style={{ width: `${calculateBandWidth(period.from, period.to)}%` }}
					>
						{period.status}
					</div>
				))}
			</div>
		</div>
	);
}

export default EntityStatusBar;
