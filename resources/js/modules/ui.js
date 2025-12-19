/**
 * UI Module - Core UI utilities and navigation functionality
 * Provides common UI operations, navigation management, and utility functions
 *
 * @author SPP Rekon System
 * @version 1.0.0
 */

/**
 * UI Manager class for handling UI interactions and navigation
 */
export class UIManager {
    constructor() {
        this.currentSection = 'dashboard';
        this.loadingElement = null;
        this.errorAlert = null;
        this.errorMessage = null;

        this.init();
    }

    /**
     * Initialize UI Manager
     */
    init() {
        this.cacheElements();
        this.setupEventListeners();
    }

    /**
     * Cache frequently used DOM elements
     */
    cacheElements() {
        this.loadingElement = document.getElementById('loading');
        this.errorAlert = document.getElementById('errorAlert');
        this.errorMessage = document.getElementById('errorMessage');
    }

    /**
     * Setup global event listeners
     */
    setupEventListeners() {
        // Navigation event listeners with event delegation
        document.addEventListener('click', (e) => {
            const navLink = e.target.closest('.nav-link');
            if (navLink) {
                e.preventDefault();
                const sectionName = navLink.getAttribute('data-section');
                if (sectionName) {
                    this.showSection(sectionName);
                }
            }
        });
    }

    /**
     * Show a specific section and hide others
     * @param {string} sectionName - The name of the section to show
     */
    showSection(sectionName) {
        // Hide all sections
        document.querySelectorAll('.content-section').forEach(section => {
            section.classList.add('hidden');
        });

        // Show selected section
        const targetSection = document.getElementById(sectionName);
        if (targetSection) {
            targetSection.classList.remove('hidden');
            this.currentSection = sectionName;
        }

        // Update nav links styling
        this.updateNavStyles(sectionName);
    }

    /**
     * Update navigation link styles to reflect active section
     * @param {string} activeSection - The currently active section
     */
    updateNavStyles(activeSection) {
        document.querySelectorAll('.nav-link').forEach(link => {
            link.classList.remove('text-white', 'font-medium');
            link.classList.add('text-blue-100');
        });

        // Highlight active nav link
        const activeLink = document.querySelector(`[data-section="${activeSection}"]`);
        if (activeLink) {
            activeLink.classList.remove('text-blue-100');
            activeLink.classList.add('text-white', 'font-medium');
        }
    }

    /**
     * Show loading indicator
     */
    showLoading() {
        if (this.loadingElement) {
            this.loadingElement.classList.remove('hidden');
        }
    }

    /**
     * Hide loading indicator
     */
    hideLoading() {
        if (this.loadingElement) {
            this.loadingElement.classList.add('hidden');
        }
    }

    /**
     * Show error message
     * @param {string} message - The error message to display
     */
    showError(message) {
        if (this.errorMessage) {
            this.errorMessage.textContent = message;
        }
        if (this.errorAlert) {
            this.errorAlert.classList.remove('hidden');
        }
    }

    /**
     * Hide error message
     */
    hideError() {
        if (this.errorAlert) {
            this.errorAlert.classList.add('hidden');
        }
    }

    /**
     * Toggle button loading state
     * @param {HTMLElement} button - The button element
     * @param {boolean} isLoading - Whether to show loading state
     * @param {string} originalText - Original button text (for restoration)
     * @param {string} loadingText - Loading state text
     */
    toggleButtonLoading(button, isLoading, originalText = '', loadingText = 'Loading...') {
        if (!button) return;

        if (isLoading) {
            button.disabled = true;
            button.dataset.originalText = button.innerHTML;
            button.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${loadingText}`;
        } else {
            button.disabled = false;
            button.innerHTML = originalText || button.dataset.originalText || button.innerHTML;
        }
    }

    /**
     * Create and show a success notification
     * @param {string} message - Success message
     * @param {string} type - Type of notification (success, warning, info)
     */
    showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        const typeClasses = {
            success: 'bg-green-50 border-green-400 text-green-800',
            warning: 'bg-yellow-50 border-yellow-400 text-yellow-800',
            error: 'bg-red-50 border-red-400 text-red-800',
            info: 'bg-blue-50 border-blue-400 text-blue-800'
        };

        const icons = {
            success: 'fa-check-circle',
            warning: 'fa-exclamation-triangle',
            error: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };

        const iconColors = {
            success: 'text-green-400',
            warning: 'text-yellow-400',
            error: 'text-red-400',
            info: 'text-blue-400'
        };

        notification.className = `${typeClasses[type]} border-l-4 p-4 mb-4 rounded-md shadow-sm notification-item`;
        notification.innerHTML = `
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas ${icons[type]} ${iconColors[type]}"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium ${typeClasses[type].split(' ')[2]}">
                        ${message}
                    </p>
                </div>
                <div class="ml-auto pl-3">
                    <div class="-mx-1.5 -my-1.5">
                        <button type="button" class="inline-flex rounded-md p-1.5 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-${typeClasses[type].split('-')[1]}-50 focus:ring-${typeClasses[type].split('-')[1]}-500">
                            <span class="sr-only">Dismiss</span>
                            <i class="fas fa-times text-gray-400"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Add dismiss functionality
        const dismissBtn = notification.querySelector('button');
        dismissBtn.addEventListener('click', () => {
            notification.remove();
        });

        // Add to container or create one
        let container = document.getElementById('notification-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notification-container';
            container.className = 'fixed top-4 right-4 z-50 space-y-2';
            document.body.appendChild(container);
        }

        container.appendChild(notification);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }

    /**
     * Format currency in Indonesian format
     * @param {number} amount - The amount to format
     * @returns {string} Formatted currency string
     */
    formatCurrency(amount) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(amount);
    }

    /**
     * Format number in Indonesian format
     * @param {number} number - The number to format
     * @returns {string} Formatted number string
     */
    formatNumber(number) {
        return new Intl.NumberFormat('id-ID').format(number);
    }

    /**
     * Debounce function to limit function calls
     * @param {Function} func - Function to debounce
     * @param {number} wait - Wait time in milliseconds
     * @returns {Function} Debounced function
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Throttle function to limit function calls
     * @param {Function} func - Function to throttle
     * @param {number} limit - Limit in milliseconds
     * @returns {Function} Throttled function
     */
    throttle(func, limit) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    /**
     * Get current active section
     * @returns {string} Current section name
     */
    getCurrentSection() {
        return this.currentSection;
    }
}

// Export singleton instance
export const UIManagerInstance = new UIManager();