/**
 * Reports Module - Class report generation functionality
 * Handles class-based report generation and export features
 *
 * @author SPP Rekon System
 * @version 1.0.0
 */

import { UIManagerInstance } from './ui.js';

/**
 * Reports Manager class for handling class report functionality
 */
export class ReportsManager {
    constructor() {
        this.apiKey = 'spp-rekon-2024-secret-key';
        this.generateLaporanBtn = null;
        this.exportLaporanBtn = null;
        this.generateLaporanDetailBtn = null;
        this.exportLaporanDetailBtn = null;
        this.laporanResult = null;

        this.init();
    }

    /**
     * Initialize Reports Manager
     */
    init() {
        this.cacheElements();
        this.setupEventListeners();
        this.setupFormSync();
    }

    /**
     * Cache DOM elements
     */
    cacheElements() {
        this.generateLaporanBtn = document.getElementById('generateLaporanBtn');
        this.exportLaporanBtn = document.getElementById('exportLaporanBtn');
        this.generateLaporanDetailBtn = document.getElementById('generateLaporanDetailBtn');
        this.exportLaporanDetailBtn = document.getElementById('exportLaporanDetailBtn');
        this.laporanResult = document.getElementById('laporanResult');
    }

    /**
     * Setup event listeners for report functionality
     */
    setupEventListeners() {
        if (this.generateLaporanBtn) {
            this.generateLaporanBtn.addEventListener('click', () => {
                this.generateLaporanKelas();
            });
        }

        if (this.exportLaporanBtn) {
            this.exportLaporanBtn.addEventListener('click', () => {
                this.exportLaporanKelas();
            });
        }

        if (this.generateLaporanDetailBtn) {
            this.generateLaporanDetailBtn.addEventListener('click', () => {
                this.generateFromDetailForm();
            });
        }

        if (this.exportLaporanDetailBtn) {
            this.exportLaporanDetailBtn.addEventListener('click', () => {
                this.exportFromDetailForm();
            });
        }
    }

    /**
     * Setup form synchronization between different report sections
     */
    setupFormSync() {
        const formMappings = [
            {
                source: 'laporanKelas',
                targets: ['laporanKelasDetail']
            },
            {
                source: 'laporanAngkatan',
                targets: ['laporanAngkatanDetail']
            },
            {
                source: 'laporanSekolah',
                targets: ['laporanSekolahDetail']
            }
        ];

        const detailFormMappings = [
            {
                source: 'laporanKelasDetail',
                targets: ['laporanKelas']
            },
            {
                source: 'laporanAngkatanDetail',
                targets: ['laporanAngkatan']
            },
            {
                source: 'laporanSekolahDetail',
                targets: ['laporanSekolah']
            }
        ];

        // Setup bidirectional sync
        [...formMappings, ...detailFormMappings].forEach(({ source, targets }) => {
            const sourceElement = document.getElementById(source);
            if (sourceElement) {
                sourceElement.addEventListener('input', () => {
                    targets.forEach(targetId => {
                        const targetElement = document.getElementById(targetId);
                        if (targetElement) {
                            targetElement.value = sourceElement.value;
                        }
                    });
                });
            }
        });
    }

    /**
     * Generate class report from detail form
     */
    generateFromDetailForm() {
        this.copyDetailToMainForm();
        this.generateLaporanKelas();
    }

    /**
     * Export class report from detail form
     */
    exportFromDetailForm() {
        this.copyDetailToMainForm();
        this.exportLaporanKelas();
    }

    /**
     * Copy values from detail form to main form
     */
    copyDetailToMainForm() {
        const mappings = [
            { source: 'laporanKelasDetail', target: 'laporanKelas' },
            { source: 'laporanAngkatanDetail', target: 'laporanAngkatan' },
            { source: 'laporanSekolahDetail', target: 'laporanSekolah' }
        ];

        mappings.forEach(({ source, target }) => {
            const sourceElement = document.getElementById(source);
            const targetElement = document.getElementById(target);
            if (sourceElement && targetElement) {
                targetElement.value = sourceElement.value;
            }
        });
    }

    /**
     * Generate class report
     */
    async generateLaporanKelas() {
        const reportData = this.getReportFormData();

        if (!this.validateReportData(reportData)) {
            return;
        }

        UIManagerInstance.toggleButtonLoading(
            this.generateLaporanBtn,
            true,
            '',
            'Generate...'
        );

        try {
            const response = await fetch(
                `/api/rekon/laporan-kelas?sekolah=${reportData.sekolah}&kelas=${reportData.kelas}&angkatan=${reportData.angkatan}`,
                {
                    headers: {
                        'X-API-KEY': this.apiKey
                    }
                }
            );

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                this.displayLaporanKelas(result.data);
                UIManagerInstance.showNotification('Laporan berhasil digenerate', 'success');
            } else {
                UIManagerInstance.showError(result.message);
            }

        } catch (error) {
            console.error('Laporan error:', error);
            UIManagerInstance.showError('Terjadi kesalahan saat generate laporan. Silakan coba lagi.');
        } finally {
            UIManagerInstance.toggleButtonLoading(
                this.generateLaporanBtn,
                false,
                '<i class="fas fa-chart-line"></i> Generate'
            );
        }
    }

    /**
     * Export class report to Excel
     */
    async exportLaporanKelas() {
        const reportData = this.getReportFormData();

        if (!this.validateReportData(reportData)) {
            return;
        }

        UIManagerInstance.toggleButtonLoading(
            this.exportLaporanBtn,
            true,
            '',
            'Exporting...'
        );

        try {
            const response = await fetch(
                `/api/rekon/laporan-kelas/export?sekolah=${reportData.sekolah}&kelas=${reportData.kelas}&angkatan=${reportData.angkatan}`,
                {
                    headers: {
                        'X-API-KEY': this.apiKey
                    }
                }
            );

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                this.downloadFile(result.download_url, result.filename);
                UIManagerInstance.showNotification(
                    `Export berhasil: ${result.total_records} record`,
                    'success'
                );
                alert(`${result.message}\nTotal records: ${result.total_records}\nFilename: ${result.filename}`);
            } else {
                UIManagerInstance.showError('Export failed: ' + result.message);
            }

        } catch (error) {
            console.error('Export error:', error);
            UIManagerInstance.showError('Terjadi kesalahan saat export laporan. Silakan coba lagi.');
        } finally {
            UIManagerInstance.toggleButtonLoading(
                this.exportLaporanBtn,
                false,
                '<i class="fas fa-file-excel"></i> Export Excel'
            );
        }
    }

    /**
     * Get report form data
     * @returns {Object} Report form data
     */
    getReportFormData() {
        return {
            kelas: document.getElementById('laporanKelas')?.value?.trim() || '',
            angkatan: document.getElementById('laporanAngkatan')?.value || '',
            sekolah: document.getElementById('laporanSekolah')?.value || ''
        };
    }

    /**
     * Validate report form data
     * @param {Object} reportData - Report data object
     * @returns {boolean} Validation result
     */
    validateReportData(reportData) {
        if (!reportData.kelas || !reportData.angkatan || !reportData.sekolah) {
            UIManagerInstance.showError('Mohon lengkapi semua field: Kelas, Angkatan, dan Sekolah');
            return false;
        }
        return true;
    }

    /**
     * Display class report results
     * @param {Object} data - Report data
     */
    displayLaporanKelas(data) {
        if (!this.laporanResult) return;

        const { headers, siswa } = data;

        let tableHtml = this.generateReportHeader(data);
        tableHtml += this.generateReportTable(headers, siswa);

        this.laporanResult.innerHTML = tableHtml;
        this.laporanResult.classList.remove('hidden');

        // Add scroll indicator for large tables
        this.addScrollIndicator();
    }

    /**
     * Generate report header HTML
     * @param {Object} data - Report data
     * @returns {string} Header HTML
     */
    generateReportHeader(data) {
        return `
            <div class="bg-green-50 border-l-4 border-green-400 p-3 mb-3">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800">
                            Laporan berhasil digenerate!
                        </p>
                        <p class="text-sm text-green-700">
                            Kelas ${data.kelas}, Angkatan ${data.angkatan}, Total Siswa: ${data.total_siswa}
                        </p>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Generate report table HTML
     * @param {Array} headers - Table headers
     * @param {Array} siswa - Student data
     * @returns {string} Table HTML
     */
    generateReportTable(headers, siswa) {
        let tableHtml = `
            <div class="overflow-x-auto border border-gray-300 rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 sticky top-0">
                        <tr>
                            <th class="px-2 py-1 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r bg-gray-100">No</th>
                            <th class="px-2 py-1 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r bg-gray-100">NIS</th>
                            <th class="px-4 py-1 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r bg-gray-100">Nama</th>
        `;

        // Add month headers
        headers.forEach((header, index) => {
            const isYearBreak = index > 0 && header.year !== headers[index - 1].year;
            if (isYearBreak) {
                tableHtml += `<th class="px-1 py-1 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-l border-t bg-gray-100" colspan="1">${header.year}</th>`;
            } else {
                tableHtml += `<th class="px-1 py-1 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-r bg-gray-100" title="${header.label} ${header.year}">${header.month}</th>`;
            }
        });

        tableHtml += `</tr></thead><tbody class="bg-white divide-y divide-gray-200">`;

        // Add student data rows
        siswa.forEach((student, index) => {
            tableHtml += this.generateStudentRow(student, index);
        });

        tableHtml += `</tbody></table></div>`;

        return tableHtml;
    }

    /**
     * Generate student row HTML
     * @param {Object} student - Student data
     * @param {number} index - Student index
     * @returns {string} Student row HTML
     */
    generateStudentRow(student, index) {
        let rowHtml = `
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-2 py-1 text-xs text-gray-900 border-r">${student.no}</td>
                <td class="px-2 py-1 text-xs text-gray-500 border-r">${student.nis}</td>
                <td class="px-4 py-1 text-xs text-gray-900 border-r font-medium">${student.nama}</td>
        `;

        // Add payment data for each month
        student.pembayaran.forEach((payment, paymentIndex) => {
            const isPaid = payment !== '-';
            const bgColor = isPaid ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
            const borderClass = paymentIndex < student.pembayaran.length - 1 ? 'border-r' : '';
            const title = isPaid ? `Pembayaran: ${payment}` : 'Belum bayar';

            rowHtml += `
                <td class="px-1 py-1 text-center text-xs ${bgColor} ${borderClass} min-w-[50px] cursor-pointer hover:opacity-80" title="${title}">
                    ${isPaid ? payment : '-'}
                </td>
            `;
        });

        rowHtml += `</tr>`;
        return rowHtml;
    }

    /**
     * Add scroll indicator for large tables
     */
    addScrollIndicator() {
        const tableContainer = this.laporanResult?.querySelector('.overflow-x-auto');
        if (!tableContainer) return;

        // Add horizontal scroll indicator if needed
        tableContainer.addEventListener('scroll', () => {
            const scrollLeft = tableContainer.scrollLeft;
            const maxScrollLeft = tableContainer.scrollWidth - tableContainer.clientWidth;

            if (scrollLeft > 10) {
                tableContainer.classList.add('scrolled-left');
            } else {
                tableContainer.classList.remove('scrolled-left');
            }

            if (scrollLeft < maxScrollLeft - 10) {
                tableContainer.classList.add('scrolled-right');
            } else {
                tableContainer.classList.remove('scrolled-right');
            }
        });
    }

    /**
     * Download file from URL
     * @param {string} url - Download URL
     * @param {string} filename - Filename
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
     * Clear report results
     */
    clearResults() {
        if (this.laporanResult) {
            this.laporanResult.innerHTML = '';
            this.laporanResult.classList.add('hidden');
        }
    }

    /**
     * Get report statistics
     * @returns {Object} Report statistics
     */
    getReportStatistics() {
        if (!this.laporanResult || this.laporanResult.classList.contains('hidden')) {
            return null;
        }

        const table = this.laporanResult.querySelector('table');
        if (!table) return null;

        const rows = table.querySelectorAll('tbody tr');
        const paymentCells = table.querySelectorAll('tbody td:nth-child(n+4)');

        let totalPaid = 0;
        let totalUnpaid = 0;

        paymentCells.forEach(cell => {
            if (cell.classList.contains('bg-green-100')) {
                totalPaid++;
            } else if (cell.classList.contains('bg-red-100')) {
                totalUnpaid++;
            }
        });

        return {
            totalStudents: rows.length,
            totalPaid: totalPaid,
            totalUnpaid: totalUnpaid,
            paymentRate: rows.length > 0 ? ((totalPaid / (totalPaid + totalUnpaid)) * 100).toFixed(1) : 0
        };
    }

    /**
     * Print report
     */
    printReport() {
        if (!this.laporanResult || this.laporanResult.classList.contains('hidden')) {
            UIManagerInstance.showError('Tidak ada laporan untuk dicetak');
            return;
        }

        const printWindow = window.open('', '_blank');
        const reportContent = this.laporanResult.innerHTML;

        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Laporan Kelas - Print</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    .bg-green-100 { background-color: #dcfce7; }
                    .bg-red-100 { background-color: #fee2e2; }
                    @media print {
                        .no-print { display: none; }
                    }
                </style>
            </head>
            <body>
                ${reportContent}
                <script>
                    window.onload = function() {
                        window.print();
                        window.close();
                    }
                </script>
            </body>
            </html>
        `);
        printWindow.document.close();
    }
}

// Export singleton instance
export const ReportsManagerInstance = new ReportsManager();