/**
 * Search Module - Search functionality and results display
 * Handles data searching with filters and results visualization
 *
 * @author SPP Rekon System
 * @version 1.0.0
 */

import { UIManagerInstance } from './ui.js';

/**
 * Search Manager class for handling search functionality
 */
export class SearchManager {
    constructor() {
        this.apiKey = 'spp-rekon-2024-secret-key';
        this.searchForm = null;
        this.clearBtn = null;
        this.sekolahSelect = null;
        this.resultCard = null;
        this.dataTable = null;
        this.resultContent = null;
        this.tableBody = null;

        this.schools = [];
        this.lastSearchData = null;

        this.init();
    }

    /**
     * Initialize Search Manager
     */
    init() {
        this.cacheElements();
        this.setupEventListeners();
        this.loadSchools();
    }

    /**
     * Cache DOM elements
     */
    cacheElements() {
        this.searchForm = document.getElementById('searchForm');
        this.clearBtn = document.getElementById('clearBtn');
        this.sekolahSelect = document.getElementById('sekolah');
        this.resultCard = document.getElementById('resultCard');
        this.dataTable = document.getElementById('dataTable');
        this.resultContent = document.getElementById('resultContent');
        this.tableBody = document.getElementById('tableBody');
    }

    /**
     * Setup event listeners for search functionality
     */
    setupEventListeners() {
        // Search form submission
        if (this.searchForm) {
            this.searchForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleSearchSubmit();
            });
        }

        // Clear button
        if (this.clearBtn) {
            this.clearBtn.addEventListener('click', () => {
                this.clearResults();
            });
        }

        // Auto-populate year with current year
        this.populateCurrentYear();
    }

    /**
     * Populate year field with current year
     */
    populateCurrentYear() {
        const yearInput = document.getElementById('tahun');
        if (yearInput && !yearInput.value) {
            const currentYear = new Date().getFullYear();
            yearInput.value = currentYear;
        }
    }

    /**
     * Handle search form submission
     */
    async handleSearchSubmit() {
        const formData = new FormData(this.searchForm);
        const searchData = {
            sekolah: formData.get('sekolah') || '',
            tahun: formData.get('tahun') || '',
            bulan: formData.get('bulan') || ''
        };

        // Validate search criteria
        if (!searchData.sekolah && !searchData.tahun && !searchData.bulan) {
            UIManagerInstance.showError('Mohon pilih setidaknya satu kriteria pencarian');
            return;
        }

        await this.searchData(searchData);
    }

    /**
     * Search data with given criteria
     * @param {Object} searchCriteria - Search criteria object
     */
    async searchData(searchCriteria) {
        try {
            UIManagerInstance.showLoading();
            UIManagerInstance.hideError();

            const queryParams = new URLSearchParams(searchCriteria).toString();
            const response = await fetch(`/api/rekon/search?${queryParams}`, {
                headers: {
                    'X-API-KEY': this.apiKey
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                this.lastSearchData = result;
                this.displayResult(result);
                UIManagerInstance.showNotification(
                    `Ditemukan ${result.data.length} record`,
                    'success'
                );
            } else {
                UIManagerInstance.showError(result.message);
            }

        } catch (error) {
            console.error('Search error:', error);
            UIManagerInstance.showError('Terjadi kesalahan saat mengambil data. Silakan coba lagi.');
        } finally {
            UIManagerInstance.hideLoading();
        }
    }

    /**
     * Display search results
     * @param {Object} result - Search result data
     */
    displayResult(result) {
        if (!result.data || result.data.length === 0) {
            this.displayNoResults();
            return;
        }

        this.displaySummaryCards(result.summary);
        this.displayDataTable(result.data);
        this.showResultsContainer();
    }

    /**
     * Display no results message
     */
    displayNoResults() {
        if (this.resultContent) {
            this.resultContent.innerHTML = `
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                <strong>Tidak ada data ditemukan</strong>
                            </p>
                            <p class="text-sm text-yellow-600 mt-1">
                                Coba ubah kriteria pencarian Anda.
                            </p>
                        </div>
                    </div>
                </div>
            `;
        }

        if (this.tableBody) {
            this.tableBody.innerHTML = '';
        }

        this.showResultsContainer();
    }

    /**
     * Display summary cards with search statistics
     * @param {Object} summary - Summary data object
     */
    displaySummaryCards(summary) {
        if (!this.resultContent) return;

        this.resultContent.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                    <div class="flex items-center">
                        <i class="fas fa-users text-blue-500 text-2xl mr-3"></i>
                        <div>
                            <p class="text-sm text-gray-600">Total Records</p>
                            <p class="text-xl font-bold text-blue-600">
                                ${UIManagerInstance.formatNumber(summary.total_records)}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                    <div class="flex items-center">
                        <i class="fas fa-money-bill-wave text-green-500 text-2xl mr-3"></i>
                        <div>
                            <p class="text-sm text-gray-600">Total Dana Masyarakat</p>
                            <p class="text-xl font-bold text-green-600">
                                ${UIManagerInstance.formatCurrency(summary.total_dana_masyarakat)}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
                    <div class="flex items-center">
                        <i class="fas fa-user-graduate text-purple-500 text-2xl mr-3"></i>
                        <div>
                            <p class="text-sm text-gray-600">Siswa Unik</p>
                            <p class="text-xl font-bold text-purple-600">
                                ${UIManagerInstance.formatNumber(summary.unique_students)}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Detail Transaksi</h3>
                <button onclick="window.searchManager.exportSearchResults()"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors">
                    <i class="fas fa-download mr-2"></i>Export CSV
                </button>
            </div>
        `;
    }

    /**
     * Display data table with search results
     * @param {Array} data - Array of search result data
     */
    displayDataTable(data) {
        if (!this.tableBody) return;

        this.tableBody.innerHTML = data.map(item => `
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    ${item.sekolah}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    ${item.id_siswa}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${item.nama_siswa}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    ${item.kelas}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    ${item.jurusan || '-'}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${UIManagerInstance.formatCurrency(parseInt(item.jum_tagihan))}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${UIManagerInstance.formatCurrency(parseInt(item.dana_masyarakat))}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    ${item.tgl_tx_formatted}
                </td>
            </tr>
        `).join('');
    }

    /**
     * Show results container
     */
    showResultsContainer() {
        if (this.resultCard) this.resultCard.classList.remove('hidden');
        if (this.dataTable) this.dataTable.classList.remove('hidden');
    }

    /**
     * Clear search results and reset form
     */
    clearResults() {
        if (this.searchForm) {
            this.searchForm.reset();
            this.populateCurrentYear();
        }

        if (this.resultCard) this.resultCard.classList.add('hidden');
        if (this.dataTable) this.dataTable.classList.add('hidden');
        if (this.resultContent) this.resultContent.innerHTML = '';
        if (this.tableBody) this.tableBody.innerHTML = '';

        UIManagerInstance.hideError();
        this.lastSearchData = null;
    }

    /**
     * Load schools from API
     */
    async loadSchools() {
        try {
            const response = await fetch('/api/schools', {
                headers: {
                    'X-API-KEY': this.apiKey
                }
            });

            const result = await response.json();

            if (result.success && result.data) {
                this.schools = result.data;
                this.populateSchoolSelect();
            } else {
                this.populateFallbackSchools();
            }
        } catch (error) {
            console.error('Error loading schools:', error);
            this.populateFallbackSchools();
        }
    }

    /**
     * Populate school select dropdown
     */
    populateSchoolSelect() {
        if (!this.sekolahSelect) return;

        this.sekolahSelect.innerHTML = '<option value="">Pilih Sekolah</option>';

        this.schools.forEach(school => {
            const option = document.createElement('option');
            option.value = school.name;
            option.textContent = school.display_name;
            this.sekolahSelect.appendChild(option);
        });
    }

    /**
     * Populate school select with fallback options
     */
    populateFallbackSchools() {
        if (!this.sekolahSelect) return;

        const fallbackSchools = [
            { name: 'SMAN_1_DENPASAR', display_name: 'SMAN 1 DENPASAR' },
            { name: 'SMAN_2_DENPASAR', display_name: 'SMAN 2 DENPASAR' },
            { name: 'SMAK_1_DENPASAR', display_name: 'SMAK 1 DENPASAR' }
        ];

        this.sekolahSelect.innerHTML = '<option value="">Pilih Sekolah</option>';

        fallbackSchools.forEach(school => {
            const option = document.createElement('option');
            option.value = school.name;
            option.textContent = school.display_name;
            this.sekolahSelect.appendChild(option);
        });
    }

    /**
     * Export search results to CSV
     */
    exportSearchResults() {
        if (!this.lastSearchData || !this.lastSearchData.data) {
            UIManagerInstance.showError('Tidak ada data untuk diekspor');
            return;
        }

        const data = this.lastSearchData.data;
        const headers = [
            'Sekolah',
            'ID Siswa',
            'Nama Siswa',
            'Kelas',
            'Jurusan',
            'Jumlah Tagihan',
            'Dana Masyarakat',
            'Tanggal Transaksi'
        ];

        let csvContent = headers.join(',') + '\n';

        data.forEach(item => {
            const row = [
                `"${item.sekolah}"`,
                `"${item.id_siswa}"`,
                `"${item.nama_siswa}"`,
                `"${item.kelas}"`,
                `"${item.jurusan || ''}"`,
                `"${item.jum_tagihan}"`,
                `"${item.dana_masyarakat}"`,
                `"${item.tgl_tx_formatted}"`
            ];
            csvContent += row.join(',') + '\n';
        });

        // Create download link
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);

        link.setAttribute('href', url);
        link.setAttribute('download', `search_results_${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        UIManagerInstance.showNotification('Data berhasil diekspor', 'success');
    }

    /**
     * Get last search data
     * @returns {Object|null} Last search result data
     */
    getLastSearchData() {
        return this.lastSearchData;
    }

    /**
     * Get schools list
     * @returns {Array} Array of school objects
     */
    getSchools() {
        return this.schools;
    }
}

// Export singleton instance
export const SearchManagerInstance = new SearchManager();

// Make it globally accessible for export button onclick
window.searchManager = SearchManagerInstance;