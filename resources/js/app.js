import './bootstrap';

// Import the dashboard application
import DashboardApplication from './dashboard-new.js';

// The dashboard application is already initialized in dashboard-new.js
// We just need to ensure it's available globally
document.addEventListener('DOMContentLoaded', function() {
    if (window.location.pathname.includes('/dashboard')) {
        // DashboardApp is already initialized, just make sure it's available
        window.DashboardApp = DashboardApplication;
        console.log('Dashboard application ready');
    }
});
