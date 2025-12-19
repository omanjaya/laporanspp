/**
 * Dashboard Main Module - Main orchestrator for all dashboard functionality
 * Coordinates all modules and provides centralized initialization
 *
 * @author SPP Rekon System
 * @version 2.0.0
 */

// Import all modules
import { UIManagerInstance } from './modules/ui.js';
import { AnalyticsManagerInstance } from './modules/analytics.js';
import { ImportManagerInstance } from './modules/import.js';
import { SearchManagerInstance } from './modules/search.js';
import { ExportManagerInstance } from './modules/export.js';
import { ReportsManagerInstance } from './modules/reports.js';

/**
 * Dashboard Application class - Main application controller
 */
class DashboardApplication {
    constructor() {
        this.isInitialized = false;
        this.modules = new Map();
        this.performanceMetrics = {
            initTime: 0,
            moduleLoadTimes: new Map()
        };

        // API Configuration
        this.apiKey = 'spp-rekon-2024-secret-key';

        // Initialize performance tracking
        this.startTime = performance.now();
    }

    /**
     * Initialize the dashboard application
     */
    async init() {
        try {
            console.log('🚀 Sistem Rekon SPP - Initializing Dashboard...');

            // Register all modules
            this.registerModules();

            // Initialize all modules
            await this.initializeModules();

            // Setup global event listeners
            this.setupGlobalEventListeners();

            // Load initial data
            await this.loadInitialData();

            // Setup performance monitoring
            this.setupPerformanceMonitoring();

            this.isInitialized = true;
            const initTime = performance.now() - this.startTime;
            this.performanceMetrics.initTime = initTime;

            console.log(`✅ Dashboard initialized successfully in ${initTime.toFixed(2)}ms`);

            // Show ready notification
            UIManagerInstance.showNotification('Sistem Rekon SPP - Ready', 'success');

        } catch (error) {
            console.error('❌ Dashboard initialization failed:', error);
            UIManagerInstance.showError('Gagal menginisialisasi dashboard. Silakan refresh halaman.');
        }
    }

    /**
     * Register all modules
     */
    registerModules() {
        this.modules.set('ui', UIManagerInstance);
        this.modules.set('analytics', AnalyticsManagerInstance);
        this.modules.set('import', ImportManagerInstance);
        this.modules.set('search', SearchManagerInstance);
        this.modules.set('export', ExportManagerInstance);
        this.modules.set('reports', ReportsManagerInstance);

        console.log('📦 Registered modules:', Array.from(this.modules.keys()));
    }

    /**
     * Initialize all modules
     */
    async initializeModules() {
        console.log('🔧 Initializing modules...');

        for (const [name, module] of this.modules) {
            const moduleStartTime = performance.now();

            try {
                // Modules are already initialized in their constructors
                // but we can add any additional async initialization here if needed
                if (typeof module.init === 'function') {
                    await module.init();
                }

                const loadTime = performance.now() - moduleStartTime;
                this.performanceMetrics.moduleLoadTimes.set(name, loadTime);
                console.log(`  ✅ ${name} module initialized (${loadTime.toFixed(2)}ms)`);

            } catch (error) {
                console.error(`  ❌ ${name} module initialization failed:`, error);
                throw new Error(`Module ${name} initialization failed`);
            }
        }
    }

    /**
     * Setup global event listeners
     */
    setupGlobalEventListeners() {
        // Window resize handler with throttling
        window.addEventListener('resize', UIManagerInstance.throttle(() => {
            this.handleWindowResize();
        }, 250));

        // Window online/offline handlers
        window.addEventListener('online', () => {
            UIManagerInstance.showNotification('Koneksi internet tersambung', 'success');
            this.retryFailedOperations();
        });

        window.addEventListener('offline', () => {
            UIManagerInstance.showNotification('Koneksi internet terputus', 'warning');
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            this.handleKeyboardShortcuts(e);
        });

        // Error handling
        window.addEventListener('error', (e) => {
            console.error('Global error:', e.error);
            this.handleGlobalError(e.error);
        });

        // Unhandled promise rejection handler
        window.addEventListener('unhandledrejection', (e) => {
            console.error('Unhandled promise rejection:', e.reason);
            this.handlePromiseRejection(e.reason);
        });

        console.log('🎧 Global event listeners configured');
    }

    /**
     * Load initial data
     */
    async loadInitialData() {
        try {
            console.log('📊 Loading initial data...');

            // Load dashboard analytics
            await AnalyticsManagerInstance.loadDashboardAnalytics();

            // Load schools for search
            SearchManagerInstance.loadSchools();

            console.log('📈 Initial data loaded successfully');

        } catch (error) {
            console.warn('⚠️ Some initial data failed to load:', error);
            // Don't throw here, allow the app to continue with fallback data
        }
    }

    /**
     * Setup performance monitoring
     */
    setupPerformanceMonitoring() {
        // Log performance metrics every 30 seconds
        setInterval(() => {
            this.logPerformanceMetrics();
        }, 30000);

        // Monitor memory usage if available
        if (performance.memory) {
            setInterval(() => {
                const memoryUsage = {
                    used: Math.round(performance.memory.usedJSHeapSize / 1048576),
                    total: Math.round(performance.memory.totalJSHeapSize / 1048576),
                    limit: Math.round(performance.memory.jsHeapSizeLimit / 1048576)
                };

                if (memoryUsage.used / memoryUsage.limit > 0.8) {
                    console.warn('⚠️ High memory usage detected:', memoryUsage);
                    this.optimizeMemoryUsage();
                }
            }, 10000);
        }
    }

    /**
     * Handle window resize
     */
    handleWindowResize() {
        // Resize charts
        AnalyticsManagerInstance.resizeCharts();
    }

    /**
     * Handle keyboard shortcuts
     * @param {KeyboardEvent} e - Keyboard event
     */
    handleKeyboardShortcuts(e) {
        // Ctrl/Cmd + R: Refresh data
        if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
            e.preventDefault();
            this.refreshAllData();
        }

        // Ctrl/Cmd + E: Export current data
        if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
            e.preventDefault();
            this.exportCurrentData();
        }

        // Escape: Clear notifications
        if (e.key === 'Escape') {
            this.clearAllNotifications();
        }
    }

    /**
     * Handle global errors
     * @param {Error} error - Error object
     */
    handleGlobalError(error) {
        UIManagerInstance.showError('Terjadi kesalahan yang tidak terduga');
        console.error('Global error handled:', error);
    }

    /**
     * Handle promise rejections
     * @param {*} reason - Rejection reason
     */
    handlePromiseRejection(reason) {
        UIManagerInstance.showError('Terjadi kesalahan pada operasi async');
        console.error('Promise rejection handled:', reason);
    }

    /**
     * Refresh all data
     */
    async refreshAllData() {
        try {
            UIManagerInstance.showLoading();

            await Promise.all([
                AnalyticsManagerInstance.loadDashboardAnalytics(),
                SearchManagerInstance.loadSchools()
            ]);

            UIManagerInstance.showNotification('Semua data berhasil diperbarui', 'success');

        } catch (error) {
            UIManagerInstance.showError('Gagal memperbarui data');
        } finally {
            UIManagerInstance.hideLoading();
        }
    }

    /**
     * Export current data based on active section
     */
    exportCurrentData() {
        const currentSection = UIManagerInstance.getCurrentSection();

        switch (currentSection) {
            case 'dashboard':
                ExportManagerInstance.exportData('excel');
                break;
            case 'pencarian':
                SearchManagerInstance.exportSearchResults();
                break;
            case 'laporan':
                ReportsManagerInstance.exportLaporanKelas();
                break;
            default:
                UIManagerInstance.showError('Tidak ada data untuk diekspor pada bagian ini');
        }
    }

    /**
     * Clear all notifications
     */
    clearAllNotifications() {
        const container = document.getElementById('notification-container');
        if (container) {
            container.innerHTML = '';
        }
        UIManagerInstance.hideError();
    }

    /**
     * Retry failed operations
     */
    async retryFailedOperations() {
        // Retry failed data loads
        try {
            await this.loadInitialData();
            UIManagerInstance.showNotification('Data berhasil dimuat ulang', 'success');
        } catch (error) {
            console.error('Retry failed:', error);
        }
    }

    /**
     * Optimize memory usage
     */
    optimizeMemoryUsage() {
        // Clear old notifications
        this.clearAllNotifications();

        // Destroy and recreate charts if needed
        AnalyticsManagerInstance.destroyCharts();
        setTimeout(() => {
            AnalyticsManagerInstance.loadDashboardAnalytics();
        }, 100);

        console.log('🧹 Memory optimization completed');
    }

    /**
     * Log performance metrics
     */
    logPerformanceMetrics() {
        if (this.performanceMetrics.initTime > 0) {
            console.log('📊 Performance Metrics:', {
                initTime: `${this.performanceMetrics.initTime.toFixed(2)}ms`,
                moduleLoadTimes: Object.fromEntries(
                    Array.from(this.performanceMetrics.moduleLoadTimes).map(([name, time]) => [
                        name,
                        `${time.toFixed(2)}ms`
                    ])
                ),
                memory: performance.memory ? {
                    used: `${Math.round(performance.memory.usedJSHeapSize / 1048576)}MB`,
                    total: `${Math.round(performance.memory.totalJSHeapSize / 1048576)}MB`
                } : 'N/A'
            });
        }
    }

    /**
     * Get module instance
     * @param {string} moduleName - Module name
     * @returns {Object|null} Module instance
     */
    getModule(moduleName) {
        return this.modules.get(moduleName) || null;
    }

    /**
     * Get application status
     * @returns {Object} Application status
     */
    getStatus() {
        return {
            initialized: this.isInitialized,
            modules: Array.from(this.modules.keys()),
            currentSection: UIManagerInstance.getCurrentSection(),
            performance: this.performanceMetrics,
            uptime: performance.now() - this.startTime
        };
    }

    /**
     * Cleanup and destroy application
     */
    destroy() {
        // Destroy charts
        AnalyticsManagerInstance.destroyCharts();

        // Clear all file selections
        ImportManagerInstance.clearAll();

        // Clear search results
        SearchManagerInstance.clearResults();

        // Clear report results
        ReportsManagerInstance.clearResults();

        // Clear notifications
        this.clearAllNotifications();

        this.isInitialized = false;
        console.log('🔌 Dashboard application destroyed');
    }
}

// Create and export the application instance
const DashboardApp = new DashboardApplication();

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        DashboardApp.init();
    });
} else {
    // DOM already loaded
    DashboardApp.init();
}

// Make available globally for debugging
window.DashboardApp = DashboardApp;

// Export for module usage
export default DashboardApp;