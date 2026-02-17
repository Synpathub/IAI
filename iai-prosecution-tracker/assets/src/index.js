/**
 * React Entry Point
 * Mounts the App component into the WordPress page
 */

import { render, createElement } from '@wordpress/element';
import App from './App';
import './styles/variables.css';
import './styles/main.css';
import './styles/timeline.css';

// Wait for DOM to be ready
document.addEventListener( 'DOMContentLoaded', () => {
	const container = document.getElementById( 'iai-prosecution-tracker' );

	if ( container ) {
		// Use WP 6.4+ createRoot if available, fallback to render
		if (
			typeof wp !== 'undefined' &&
			wp.element &&
			wp.element.createRoot
		) {
			const root = wp.element.createRoot( container );
			root.render( createElement( App ) );
		} else {
			render( createElement( App ), container );
		}
	}
} );
