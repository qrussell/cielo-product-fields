/**
 * WordPress dependencies
 * We use @wordpress/element which is a wrapper for React/ReactDOM to ensure 
 * compatibility with the version of React shipped with WordPress.
 */
import { render } from '@wordpress/element';

/**
 * Internal dependencies
 */
import App from './TemplateBuilder';
import './builder-styles.scss';

// Wait for the DOM to be ready before mounting the app
document.addEventListener('DOMContentLoaded', () => {
    // This ID matches the mount point we created in admin-meta-box.php
    const rootElement = document.getElementById('cielo-react-root');
    
    if (rootElement) {
        // Render the TemplateBuilder component into the WordPress meta box
        render(<App />, rootElement);
    }
});