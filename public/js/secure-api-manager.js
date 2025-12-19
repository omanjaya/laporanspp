/**
 * Secure API Manager for SPP Dashboard
 * Handles secure API key management and authentication
 */
class SecureApiManager {
    constructor() {
        this.isAuthenticated = false;
        this.apiKey = null;
        this.sessionCheckInterval = null;
        this.init();
    }

    /**
     * Initialize the API manager
     */
    async init() {
        await this.checkAuthStatus();
        this.startSessionMonitoring();
    }

    /**
     * Check current authentication status
     */
    async checkAuthStatus() {
        try {
            const response = await fetch('/api/auth/status', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (response.ok) {
                const data = await response.json();
                this.isAuthenticated = data.authenticated;

                if (this.isAuthenticated) {
                    this.hideLoginForm();
                    this.showDashboard();
                } else {
                    this.showLoginForm();
                    this.hideDashboard();
                }
            } else {
                this.showLoginForm();
                this.hideDashboard();
            }
        } catch (error) {
            console.error('Auth status check failed:', error);
            this.showLoginForm();
            this.hideDashboard();
        }
    }

    /**
     * Login with username and password
     */
    async login(username, password) {
        try {
            const formData = new FormData();
            formData.append('username', username);
            formData.append('password', password);

            const response = await fetch('/api/auth/login', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.isAuthenticated = true;
                this.hideLoginForm();
                this.showDashboard();
                this.showNotification('Login successful', 'success');
                return true;
            } else {
                this.showNotification(data.message || 'Login failed', 'error');
                return false;
            }
        } catch (error) {
            console.error('Login failed:', error);
            this.showNotification('Login failed. Please try again.', 'error');
            return false;
        }
    }

    /**
     * Logout current user
     */
    async logout() {
        try {
            const response = await fetch('/api/auth/logout', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            });

            if (response.ok) {
                this.isAuthenticated = false;
                this.showLoginForm();
                this.hideDashboard();
                this.showNotification('Logged out successfully', 'success');
            }
        } catch (error) {
            console.error('Logout failed:', error);
        }
    }

    /**
     * Make authenticated API request
     */
    async makeAuthenticatedRequest(url, options = {}) {
        const defaultOptions = {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        };

        // Only add CSRF token for POST/PUT/DELETE requests
        if (['POST', 'PUT', 'DELETE'].includes(options.method?.toUpperCase())) {
            defaultOptions.headers['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        }

        const mergedOptions = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...options.headers
            }
        };

        try {
            const response = await fetch(url, mergedOptions);

            // Handle authentication failures
            if (response.status === 401) {
                this.isAuthenticated = false;
                this.showLoginForm();
                this.hideDashboard();
                this.showNotification('Session expired. Please login again.', 'warning');
                throw new Error('Authentication required');
            }

            // Handle rate limiting
            if (response.status === 429) {
                const retryAfter = response.headers.get('Retry-After') || 60;
                this.showNotification(`Rate limit exceeded. Please wait ${retryAfter} seconds.`, 'warning');
                throw new Error('Rate limit exceeded');
            }

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `HTTP ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error('API request failed:', error);
            throw error;
        }
    }

    /**
     * Start monitoring session status
     */
    startSessionMonitoring() {
        // Check session status every 5 minutes
        this.sessionCheckInterval = setInterval(() => {
            this.checkAuthStatus();
        }, 5 * 60 * 1000);
    }

    /**
     * Stop session monitoring
     */
    stopSessionMonitoring() {
        if (this.sessionCheckInterval) {
            clearInterval(this.sessionCheckInterval);
            this.sessionCheckInterval = null;
        }
    }

    /**
     * Show login form
     */
    showLoginForm() {
        const loginForm = document.getElementById('login-form');
        const dashboard = document.getElementById('dashboard-content');

        if (loginForm) loginForm.style.display = 'block';
        if (dashboard) dashboard.style.display = 'none';
    }

    /**
     * Hide login form
     */
    hideLoginForm() {
        const loginForm = document.getElementById('login-form');
        if (loginForm) loginForm.style.display = 'none';
    }

    /**
     * Show dashboard content
     */
    showDashboard() {
        const dashboard = document.getElementById('dashboard-content');
        if (dashboard) dashboard.style.display = 'block';
    }

    /**
     * Hide dashboard content
     */
    hideDashboard() {
        const dashboard = document.getElementById('dashboard-content');
        if (dashboard) dashboard.style.display = 'none';
    }

    /**
     * Show notification message
     */
    showNotification(message, type = 'info') {
        // Create notification element if it doesn't exist
        let notification = document.getElementById('notification');
        if (!notification) {
            notification = document.createElement('div');
            notification.id = 'notification';
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 12px 20px;
                border-radius: 4px;
                z-index: 9999;
                font-weight: 500;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                transition: all 0.3s ease;
            `;
            document.body.appendChild(notification);
        }

        // Set color based on type
        const colors = {
            success: '#10b981',
            error: '#ef4444',
            warning: '#f59e0b',
            info: '#3b82f6'
        };

        notification.style.backgroundColor = colors[type] || colors.info;
        notification.style.color = 'white';
        notification.textContent = message;
        notification.style.display = 'block';

        // Auto hide after 5 seconds
        setTimeout(() => {
            notification.style.display = 'none';
        }, 5000);
    }

    /**
     * Cleanup resources
     */
    destroy() {
        this.stopSessionMonitoring();
    }
}

// Initialize the secure API manager
const secureApiManager = new SecureApiManager();

// Export for global use
window.secureApiManager = secureApiManager;