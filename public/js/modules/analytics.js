/**
 * Analytics Module - Chart management and dashboard data visualization
 * Handles all chart-related functionality and dashboard analytics
 *
 * @author SPP Rekon System
 * @version 1.0.0
 */

import { UIManagerInstance } from './ui.js';

/**
 * Analytics Manager class for handling dashboard charts and analytics
 */
export class AnalyticsManager {
    constructor() {
        this.monthlyChart = null;
        this.schoolChart = null;
        this.apiKey = 'spp-rekon-2024-secret-key';
        this.refreshBtn = null;

        this.init();
    }

    /**
     * Initialize Analytics Manager
     */
    init() {
        this.cacheElements();
        this.setupEventListeners();
    }

    /**
     * Cache frequently used DOM elements
     */
    cacheElements() {
        this.refreshBtn = document.getElementById('refreshDataBtn');
    }

    /**
     * Setup event listeners for analytics functionality
     */
    setupEventListeners() {
        if (this.refreshBtn) {
            this.refreshBtn.addEventListener('click', () => {
                this.handleRefreshData();
            });
        }
    }

    /**
     * Load dashboard analytics from API
     */
    async loadDashboardAnalytics() {
        try {
            UIManagerInstance.showLoading();

            const response = await fetch('/api/dashboard/analytics', {
                headers: {
                    'X-API-KEY': this.apiKey
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                this.updateSummaryCards(result.summary);
                this.updateMonthlyChart(result.monthly_data);
                this.updateSchoolChart(result.school_data);
                this.updateSummaryTable(result.school_data);
            } else {
                UIManagerInstance.showError('Failed to load analytics data');
            }
        } catch (error) {
            console.error('Error loading dashboard analytics:', error);
            UIManagerInstance.showError('Terjadi kesalahan saat memuat data analytics');
        } finally {
            UIManagerInstance.hideLoading();
        }
    }

    /**
     * Update summary cards with analytics data
     * @param {Object} summary - Summary data object
     */
    updateSummaryCards(summary) {
        const elements = {
            totalTransactions: document.getElementById('totalTransactions'),
            totalDana: document.getElementById('totalDana'),
            totalSiswa: document.getElementById('totalSiswa'),
            totalSchools: document.getElementById('totalSchools')
        };

        if (elements.totalTransactions) {
            elements.totalTransactions.textContent = UIManagerInstance.formatNumber(summary.total_transactions);
        }
        if (elements.totalDana) {
            elements.totalDana.textContent = UIManagerInstance.formatCurrency(summary.total_dana);
        }
        if (elements.totalSiswa) {
            elements.totalSiswa.textContent = UIManagerInstance.formatNumber(summary.total_siswa);
        }
        if (elements.totalSchools) {
            elements.totalSchools.textContent = UIManagerInstance.formatNumber(summary.total_schools);
        }
    }

    /**
     * Update monthly chart with payment data
     * @param {Array} monthlyData - Array of monthly payment data
     */
    updateMonthlyChart(monthlyData) {
        const ctx = document.getElementById('monthlyChart');
        if (!ctx) return;

        // Prepare data
        const labels = monthlyData.map(item => {
            const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            return `${monthNames[item.bulan - 1]} ${item.tahun}`;
        });
        const transactions = monthlyData.map(item => item.total);
        const dana = monthlyData.map(item => parseInt(item.dana));

        // Destroy existing chart if it exists
        if (this.monthlyChart) {
            this.monthlyChart.destroy();
        }

        // Create new chart
        this.monthlyChart = new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Total Transaksi',
                        data: transactions,
                        backgroundColor: 'rgba(59, 130, 246, 0.8)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Total Dana (Rp)',
                        data: dana,
                        type: 'line',
                        backgroundColor: 'rgba(34, 197, 94, 0.8)',
                        borderColor: 'rgba(34, 197, 94, 1)',
                        borderWidth: 2,
                        yAxisID: 'y1',
                        tension: 0.4
                    }
                ]
            },
            options: this.getMonthlyChartOptions()
        });
    }

    /**
     * Get configuration options for monthly chart
     * @returns {Object} Chart configuration options
     */
    getMonthlyChartOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Transaksi'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Dana (Rp)'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.dataset.label === 'Total Dana (Rp)') {
                                label += 'Rp ' + context.parsed.y.toLocaleString('id-ID');
                            } else {
                                label += context.parsed.y.toLocaleString('id-ID');
                            }
                            return label;
                        }
                    }
                }
            }
        };
    }

    /**
     * Update school distribution chart
     * @param {Array} schoolData - Array of school data
     */
    updateSchoolChart(schoolData) {
        const ctx = document.getElementById('schoolChart');
        if (!ctx) return;

        const labels = schoolData.map(item => item.sekolah);
        const data = schoolData.map(item => item.total);

        // Destroy existing chart if it exists
        if (this.schoolChart) {
            this.schoolChart.destroy();
        }

        // Create new chart
        this.schoolChart = new Chart(ctx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: this.getSchoolChartColors(),
                    borderColor: this.getSchoolChartBorderColors(),
                    borderWidth: 1
                }]
            },
            options: this.getSchoolChartOptions()
        });
    }

    /**
     * Get color palette for school chart
     * @returns {Array} Array of background colors
     */
    getSchoolChartColors() {
        return [
            'rgba(59, 130, 246, 0.8)',
            'rgba(34, 197, 94, 0.8)',
            'rgba(168, 85, 247, 0.8)',
            'rgba(251, 146, 60, 0.8)',
            'rgba(239, 68, 68, 0.8)',
            'rgba(245, 158, 11, 0.8)',
            'rgba(99, 102, 241, 0.8)',
            'rgba(16, 185, 129, 0.8)'
        ];
    }

    /**
     * Get border colors for school chart
     * @returns {Array} Array of border colors
     */
    getSchoolChartBorderColors() {
        return [
            'rgba(59, 130, 246, 1)',
            'rgba(34, 197, 94, 1)',
            'rgba(168, 85, 247, 1)',
            'rgba(251, 146, 60, 1)',
            'rgba(239, 68, 68, 1)',
            'rgba(245, 158, 11, 1)',
            'rgba(99, 102, 241, 1)',
            'rgba(16, 185, 129, 1)'
        ];
    }

    /**
     * Get configuration options for school chart
     * @returns {Object} Chart configuration options
     */
    getSchoolChartOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${label}: ${value.toLocaleString('id-ID')} (${percentage}%)`;
                        }
                    }
                }
            }
        };
    }

    /**
     * Update summary table with school data
     * @param {Array} schoolData - Array of school data
     */
    updateSummaryTable(schoolData) {
        const tbody = document.getElementById('summaryTableBody');
        if (!tbody) return;

        tbody.innerHTML = schoolData.map(item => {
            const status = item.total > 10 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
            const statusText = 'Aktif';

            return `
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${item.sekolah}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${UIManagerInstance.formatNumber(item.total)}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${UIManagerInstance.formatCurrency(parseInt(item.dana))}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${UIManagerInstance.formatNumber(item.siswa)}</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${status}">
                            ${statusText}
                        </span>
                    </td>
                </tr>
            `;
        }).join('');
    }

    /**
     * Handle refresh data button click
     */
    async handleRefreshData() {
        if (!this.refreshBtn) return;

        const originalText = this.refreshBtn.innerHTML;
        UIManagerInstance.toggleButtonLoading(
            this.refreshBtn,
            true,
            originalText,
            'Loading...'
        );

        try {
            await this.loadDashboardAnalytics();
            UIManagerInstance.showNotification('Data berhasil diperbarui', 'success');
        } catch (error) {
            UIManagerInstance.showError('Gagal memperbarui data');
        } finally {
            UIManagerInstance.toggleButtonLoading(
                this.refreshBtn,
                false,
                '<i class="fas fa-sync-alt"></i> Refresh Data'
            );
        }
    }

    /**
     * Destroy all charts to free up memory
     */
    destroyCharts() {
        if (this.monthlyChart) {
            this.monthlyChart.destroy();
            this.monthlyChart = null;
        }
        if (this.schoolChart) {
            this.schoolChart.destroy();
            this.schoolChart = null;
        }
    }

    /**
     * Resize all charts (useful when container size changes)
     */
    resizeCharts() {
        if (this.monthlyChart) {
            this.monthlyChart.resize();
        }
        if (this.schoolChart) {
            this.schoolChart.resize();
        }
    }

    /**
     * Get chart instances
     * @returns {Object} Object containing chart instances
     */
    getCharts() {
        return {
            monthlyChart: this.monthlyChart,
            schoolChart: this.schoolChart
        };
    }
}

// Export singleton instance
export const AnalyticsManagerInstance = new AnalyticsManager();