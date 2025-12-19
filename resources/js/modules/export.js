/**
 * Export Module - Excel/CSV export functionality
 * Handles data export operations with various formats and filters
 *
 * @author SPP Rekon System
 * @version 1.0.0
 */

import { UIManagerInstance } from './ui.js';

/**
 * Export Manager class for handling export functionality
 */
export class ExportManager {
    constructor() {
        this.apiKey = 'spp-rekon-2024-secret-key';
        this.exportExcelBtn = null;
        this.exportCSVBtn = null;

        this.init();
    }

    /**
     * Initialize Export Manager
     */
    init() {
        this.cacheElements();
        this.setupEventListeners();
    }

    /**
     * Cache DOM elements
     */
    cacheElements() {
        this.exportExcelBtn = document.getElementById('exportExcelBtn');
        this.exportCSVBtn = document.getElementById('exportCSVBtn');
    }

    /**
     * Setup event listeners for export functionality
     */
    setupEventListeners() {
        if (this.exportExcelBtn) {
            this.exportExcelBtn.addEventListener('click', () => {
                this.exportData('excel');
            });
        }

        if (this.exportCSVBtn) {
            this.exportCSVBtn.addEventListener('click', () => {
                this.exportData('csv');
            });
        }
    }

    /**
     * Export data in specified format
     * @param {string} type - Export type ('excel' or 'csv')
     */
    async exportData(type) {
        try {
            // Get current filters
            const filters = this.getCurrentFilters();

            // Build query parameters
            const params = new URLSearchParams();
            Object.entries(filters).forEach(([key, value]) => {
                if (value) {
                    params.append(key, value);
                }
            });

            const btn = type === 'excel' ? this.exportExcelBtn : this.exportCSVBtn;
            if (!btn) return;

            const originalText = btn.innerHTML;

            UIManagerInstance.toggleButtonLoading(
                btn,
                true,
                originalText,
                'Exporting...'
            );

            const response = await fetch(`/api/rekon/export/${type}?${params}`, {
                headers: {
                    'X-API-KEY': this.apiKey
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                this.downloadFile(result.download_url, result.filename);
                this.showExportSuccess(result, type);
            } else {
                UIManagerInstance.showError('Export failed: ' + result.message);
            }

        } catch (error) {
            console.error('Export error:', error);
            UIManagerInstance.showError('Terjadi kesalahan saat export. Silakan coba lagi.');
        } finally {
            const btn = type === 'excel' ? this.exportExcelBtn : this.exportCSVBtn;
            if (btn) {
                const buttonText = type === 'excel' ? '<i class="fas fa-file-excel"></i> Export Excel' : '<i class="fas fa-file-csv"></i> Export CSV';
                UIManagerInstance.toggleButtonLoading(btn, false, buttonText);
            }
        }
    }

    /**
     * Get current filter values
     * @returns {Object} Filter values object
     */
    getCurrentFilters() {
        return {
            sekolah: document.getElementById('sekolah')?.value || '',
            tahun: document.getElementById('tahun')?.value || '',
            bulan: document.getElementById('bulan')?.value || ''
        };
    }

    /**
     * Download file from URL
     * @param {string} url - Download URL
     * @param {string} filename - Filename for download
     */
    downloadFile(url, filename) {
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        link.style.display = 'none';

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    /**
     * Show export success notification
     * @param {Object} result - Export result data
     * @param {string} type - Export type
     */
    showExportSuccess(result, type) {
        const message = `${result.message}\nTotal records: ${result.total_records}\nFilename: ${result.filename}`;

        UIManagerInstance.showNotification(
            `Export ${type.toUpperCase()} berhasil: ${result.total_records} record`,
            'success'
        );

        // Also show detailed success message (keeping the original alert for now)
        alert(message);
    }

    /**
     * Export search results to CSV (alternative method)
     * @param {Array} data - Data to export
     * @param {string} filename - Custom filename
     */
    exportToCSV(data, filename = null) {
        if (!data || data.length === 0) {
            UIManagerInstance.showError('Tidak ada data untuk diekspor');
            return;
        }

        const headers = Object.keys(data[0]);
        let csvContent = headers.join(',') + '\n';

        data.forEach(item => {
            const row = headers.map(header => {
                const value = item[header] || '';
                // Escape quotes and wrap in quotes if contains comma or quote
                if (typeof value === 'string' && (value.includes(',') || value.includes('"'))) {
                    return `"${value.replace(/"/g, '""')}"`;
                }
                return value;
            });
            csvContent += row.join(',') + '\n';
        });

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(link);

        const defaultFilename = `export_${new Date().toISOString().split('T')[0]}.csv`;
        link.setAttribute('href', url);
        link.setAttribute('download', filename || defaultFilename);
        link.style.visibility = 'hidden';

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        UIManagerInstance.showNotification('Data berhasil diekspor ke CSV', 'success');
    }

    /**
     * Export data to JSON format
     * @param {Array} data - Data to export
     * @param {string} filename - Custom filename
     */
    exportToJSON(data, filename = null) {
        if (!data || data.length === 0) {
            UIManagerInstance.showError('Tidak ada data untuk diekspor');
            return;
        }

        const jsonContent = JSON.stringify(data, null, 2);
        const blob = new Blob([jsonContent], { type: 'application/json' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);

        const defaultFilename = `export_${new Date().toISOString().split('T')[0]}.json`;
        link.setAttribute('href', url);
        link.setAttribute('download', filename || defaultFilename);
        link.style.visibility = 'hidden';

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        UIManagerInstance.showNotification('Data berhasil diekspor ke JSON', 'success');
    }

    /**
     * Create export configuration
     * @param {string} type - Export type
     * @param {Object} options - Export options
     * @returns {Object} Export configuration
     */
    createExportConfig(type, options = {}) {
        const defaultConfig = {
            type: type,
            filters: this.getCurrentFilters(),
            timestamp: new Date().toISOString(),
            includeHeaders: true,
            dateFormat: 'id-ID'
        };

        return { ...defaultConfig, ...options };
    }

    /**
     * Validate export parameters
     * @param {string} type - Export type
     * @param {Object} filters - Filter parameters
     * @returns {boolean} Validation result
     */
    validateExportParams(type, filters) {
        const validTypes = ['excel', 'csv', 'json'];

        if (!validTypes.includes(type)) {
            UIManagerInstance.showError('Tipe export tidak valid');
            return false;
        }

        // Additional validation can be added here
        return true;
    }

    /**
     * Get export history from localStorage
     * @returns {Array} Export history
     */
    getExportHistory() {
        try {
            const history = localStorage.getItem('export_history');
            return history ? JSON.parse(history) : [];
        } catch (error) {
            console.error('Error getting export history:', error);
            return [];
        }
    }

    /**
     * Add export to history
     * @param {Object} exportInfo - Export information
     */
    addToExportHistory(exportInfo) {
        try {
            const history = this.getExportHistory();
            history.unshift({
                ...exportInfo,
                timestamp: new Date().toISOString()
            });

            // Keep only last 50 exports
            const limitedHistory = history.slice(0, 50);
            localStorage.setItem('export_history', JSON.stringify(limitedHistory));
        } catch (error) {
            console.error('Error adding to export history:', error);
        }
    }

    /**
     * Clear export history
     */
    clearExportHistory() {
        try {
            localStorage.removeItem('export_history');
            UIManagerInstance.showNotification('Riwayat export berhasil dihapus', 'success');
        } catch (error) {
            console.error('Error clearing export history:', error);
            UIManagerInstance.showError('Gagal menghapus riwayat export');
        }
    }

    /**
     * Get export statistics
     * @returns {Object} Export statistics
     */
    getExportStatistics() {
        const history = this.getExportHistory();
        const stats = {
            totalExports: history.length,
            exportByType: {},
            exportByDate: {},
            lastExport: null
        };

        history.forEach(exportItem => {
            // Count by type
            stats.exportByType[exportItem.type] = (stats.exportByType[exportItem.type] || 0) + 1;

            // Count by date
            const date = exportItem.timestamp.split('T')[0];
            stats.exportByDate[date] = (stats.exportByDate[date] || 0) + 1;
        });

        // Set last export
        if (history.length > 0) {
            stats.lastExport = history[0].timestamp;
        }

        return stats;
    }
}

// Export singleton instance
export const ExportManagerInstance = new ExportManager();