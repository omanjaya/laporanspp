/**
 * Import Module - File upload and CSV processing functionality
 * Handles both legacy file imports and bank CSV imports with drag-and-drop support
 *
 * @author SPP Rekon System
 * @version 1.0.0
 */

import { UIManagerInstance } from './ui.js';
import { AnalyticsManagerInstance } from './analytics.js';

/**
 * Import Manager class for handling file uploads and imports
 */
export class ImportManager {
    constructor() {
        this.selectedFile = null;
        this.selectedBankFile = null;
        this.apiKey = 'spp-rekon-2024-secret-key';
        this.validExtensions = ['.csv', '.xlsx', '.xls'];
        this.maxFileSize = 10 * 1024 * 1024; // 10MB

        // Legacy import elements
        this.fileInput = null;
        this.browseBtn = null;
        this.dropZone = null;
        this.fileInfo = null;
        this.fileName = null;
        this.removeFileBtn = null;
        this.uploadBtn = null;
        this.importProgress = null;
        this.importResult = null;

        // Bank import elements
        this.bankFileInput = null;
        this.bankBrowseBtn = null;
        this.bankDropZone = null;
        this.bankFileInfo = null;
        this.bankFileName = null;
        this.bankRemoveFileBtn = null;
        this.bankUploadBtn = null;
        this.bankImportProgress = null;
        this.bankImportResult = null;

        this.init();
    }

    /**
     * Initialize Import Manager
     */
    init() {
        this.cacheElements();
        this.setupEventListeners();
    }

    /**
     * Cache DOM elements
     */
    cacheElements() {
        // Legacy import elements
        this.fileInput = document.getElementById('fileInput');
        this.browseBtn = document.getElementById('browseBtn');
        this.dropZone = document.getElementById('dropZone');
        this.fileInfo = document.getElementById('fileInfo');
        this.fileName = document.getElementById('fileName');
        this.removeFileBtn = document.getElementById('removeFileBtn');
        this.uploadBtn = document.getElementById('uploadBtn');
        this.importProgress = document.getElementById('importProgress');
        this.importResult = document.getElementById('importResult');

        // Bank import elements
        this.bankFileInput = document.getElementById('bankFileInput');
        this.bankBrowseBtn = document.getElementById('bankBrowseBtn');
        this.bankDropZone = document.getElementById('bankDropZone');
        this.bankFileInfo = document.getElementById('bankFileInfo');
        this.bankFileName = document.getElementById('bankFileName');
        this.bankRemoveFileBtn = document.getElementById('bankRemoveFileBtn');
        this.bankUploadBtn = document.getElementById('bankUploadBtn');
        this.bankImportProgress = document.getElementById('bankImportProgress');
        this.bankImportResult = document.getElementById('bankImportResult');
    }

    /**
     * Setup event listeners for import functionality
     */
    setupEventListeners() {
        this.setupLegacyImportListeners();
        this.setupBankImportListeners();
    }

    /**
     * Setup legacy import event listeners
     */
    setupLegacyImportListeners() {
        // Browse button
        if (this.browseBtn && this.fileInput) {
            this.browseBtn.addEventListener('click', () => {
                this.fileInput.click();
            });
        }

        // File input change
        if (this.fileInput) {
            this.fileInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    this.handleFileSelect(file, 'legacy');
                }
            });
        }

        // Drag and drop
        if (this.dropZone) {
            this.setupDragAndDrop(this.dropZone, 'legacy');
        }

        // Remove file
        if (this.removeFileBtn) {
            this.removeFileBtn.addEventListener('click', () => {
                this.clearFileSelection('legacy');
            });
        }

        // Upload button
        if (this.uploadBtn) {
            this.uploadBtn.addEventListener('click', () => {
                if (this.selectedFile) {
                    this.uploadFile('legacy');
                }
            });
        }
    }

    /**
     * Setup bank import event listeners
     */
    setupBankImportListeners() {
        // Browse button
        if (this.bankBrowseBtn && this.bankFileInput) {
            this.bankBrowseBtn.addEventListener('click', () => {
                this.bankFileInput.click();
            });
        }

        // File input change
        if (this.bankFileInput) {
            this.bankFileInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    this.handleFileSelect(file, 'bank');
                }
            });
        }

        // Drag and drop
        if (this.bankDropZone) {
            this.setupDragAndDrop(this.bankDropZone, 'bank');
        }

        // Remove file
        if (this.bankRemoveFileBtn) {
            this.bankRemoveFileBtn.addEventListener('click', () => {
                this.clearFileSelection('bank');
            });
        }

        // Upload button
        if (this.bankUploadBtn) {
            this.bankUploadBtn.addEventListener('click', () => {
                if (this.selectedBankFile) {
                    this.uploadFile('bank');
                }
            });
        }
    }

    /**
     * Setup drag and drop functionality
     * @param {HTMLElement} dropZone - Drop zone element
     * @param {string} type - Import type ('legacy' or 'bank')
     */
    setupDragAndDrop(dropZone, type) {
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('border-blue-500', 'bg-blue-50');
        });

        dropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            dropZone.classList.remove('border-blue-500', 'bg-blue-50');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('border-blue-500', 'bg-blue-50');

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                this.handleFileSelect(files[0], type);
            }
        });
    }

    /**
     * Handle file selection
     * @param {File} file - Selected file
     * @param {string} type - Import type ('legacy' or 'bank')
     */
    handleFileSelect(file, type) {
        // Validate file type
        const fileName = file.name.toLowerCase();
        const isValidExtension = this.validExtensions.some(ext => fileName.endsWith(ext));

        if (!isValidExtension) {
            UIManagerInstance.showError('File harus berformat CSV, XLS, atau XLSX');
            return;
        }

        // Validate file size
        if (file.size > this.maxFileSize) {
            UIManagerInstance.showError('Ukuran file maksimal 10MB');
            return;
        }

        // Store file reference
        if (type === 'legacy') {
            this.selectedFile = file;
            if (this.fileName) this.fileName.textContent = file.name;
            if (this.dropZone) this.dropZone.classList.add('hidden');
            if (this.fileInfo) this.fileInfo.classList.remove('hidden');
        } else {
            this.selectedBankFile = file;
            if (this.bankFileName) this.bankFileName.textContent = file.name;
            if (this.bankDropZone) this.bankDropZone.classList.add('hidden');
            if (this.bankFileInfo) this.bankFileInfo.classList.remove('hidden');
        }

        UIManagerInstance.hideError();
    }

    /**
     * Clear file selection
     * @param {string} type - Import type ('legacy' or 'bank')
     */
    clearFileSelection(type) {
        if (type === 'legacy') {
            this.selectedFile = null;
            if (this.fileInput) this.fileInput.value = '';
            if (this.dropZone) this.dropZone.classList.remove('hidden');
            if (this.fileInfo) this.fileInfo.classList.add('hidden');
            if (this.importProgress) this.importProgress.classList.add('hidden');
            if (this.importResult) this.importResult.classList.add('hidden');
        } else {
            this.selectedBankFile = null;
            if (this.bankFileInput) this.bankFileInput.value = '';
            if (this.bankDropZone) this.bankDropZone.classList.remove('hidden');
            if (this.bankFileInfo) this.bankFileInfo.classList.add('hidden');
            if (this.bankImportProgress) this.bankImportProgress.classList.add('hidden');
            if (this.bankImportResult) this.bankImportResult.classList.add('hidden');
        }
    }

    /**
     * Upload file to server
     * @param {string} type - Import type ('legacy' or 'bank')
     */
    async uploadFile(type) {
        const file = type === 'legacy' ? this.selectedFile : this.selectedBankFile;
        if (!file) return;

        const formData = new FormData();
        formData.append('file', file);

        const uploadBtn = type === 'legacy' ? this.uploadBtn : this.bankUploadBtn;
        const progressElement = type === 'legacy' ? this.importProgress : this.bankImportProgress;
        const resultElement = type === 'legacy' ? this.importResult : this.bankImportResult;
        const endpoint = type === 'legacy' ? '/api/rekon/import' : '/api/rekon/import-bank';
        const buttonText = type === 'legacy' ? 'Upload Data' : 'Upload CSV Bank';

        // Show progress
        if (progressElement) progressElement.classList.remove('hidden');
        if (resultElement) resultElement.classList.add('hidden');
        UIManagerInstance.toggleButtonLoading(uploadBtn, true, '', 'Mengupload...');

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'X-API-KEY': this.apiKey
                }
            });

            const result = await response.json();

            if (progressElement) progressElement.classList.add('hidden');
            UIManagerInstance.toggleButtonLoading(uploadBtn, false, '', `<i class="fas fa-upload"></i> ${buttonText}`);

            if (result.success) {
                this.showImportResult(result, type);
                this.clearFileSelection(type);
                // Refresh analytics
                await AnalyticsManagerInstance.loadDashboardAnalytics();
                UIManagerInstance.showNotification(result.message, 'success');
            } else {
                this.showImportError(result.message, type);
            }

        } catch (error) {
            console.error(`${type} upload error:`, error);

            if (progressElement) progressElement.classList.add('hidden');
            UIManagerInstance.toggleButtonLoading(uploadBtn, false, '', `<i class="fas fa-upload"></i> ${buttonText}`);

            const errorMessage = type === 'legacy'
                ? 'Terjadi kesalahan saat mengupload file. Silakan coba lagi.'
                : 'Terjadi kesalahan saat mengupload file bank. Silakan coba lagi.';

            this.showImportError(errorMessage, type);
        }
    }

    /**
     * Show import result
     * @param {Object} result - Import result data
     * @param {string} type - Import type ('legacy' or 'bank')
     */
    showImportResult(result, type) {
        const resultElement = type === 'legacy' ? this.importResult : this.bankImportResult;
        if (!resultElement) return;

        const successClass = result.error_count > 0 ? 'bg-yellow-50 border-yellow-400' : 'bg-green-50 border-green-400';
        const icon = type === 'legacy' ? 'fa-check-circle' : 'fa-university';
        const title = type === 'legacy' ? 'Detail Import:' : 'Detail Import CSV Bank:';

        let resultHtml = `
            <div class="${successClass} border-l-4 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas ${icon} ${result.error_count > 0 ? 'text-yellow-400' : 'text-green-400'}"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium ${result.error_count > 0 ? 'text-yellow-800' : 'text-green-800'}">
                            ${result.message}
                        </p>
                        <div class="mt-2 text-sm ${result.error_count > 0 ? 'text-yellow-700' : 'text-green-700'}">
                            <p><strong>${title}</strong></p>
                            <ul class="list-disc list-inside mt-1">
                                <li>Total baris diproses: ${result.total_rows}</li>
                                <li>Berhasil diimport: ${result.imported}</li>
                                ${result.duplicates > 0 ? `<li>Duplicate dilewati: ${result.duplicates}</li>` : ''}
                                ${result.error_count > 0 ? `<li>Gagal: ${result.error_count}</li>` : ''}
                            </ul>
        `;

        if (result.error_count > 0 && result.errors && result.errors.length > 0) {
            resultHtml += `
                <details class="mt-2">
                    <summary class="cursor-pointer font-medium">Lihat Error</summary>
                    <ul class="list-disc list-inside mt-1">
                        ${result.errors.map(error => `<li class="text-xs">${error}</li>`).join('')}
                    </ul>
                </details>
            `;
        }

        resultHtml += `
                        </div>
                    </div>
                </div>
            </div>
        `;

        resultElement.innerHTML = resultHtml;
        resultElement.classList.remove('hidden');
    }

    /**
     * Show import error
     * @param {string} message - Error message
     * @param {string} type - Import type ('legacy' or 'bank')
     */
    showImportError(message, type) {
        const resultElement = type === 'legacy' ? this.importResult : this.bankImportResult;
        if (!resultElement) return;

        const title = type === 'legacy' ? 'Error' : 'Error Bank Import';

        resultElement.innerHTML = `
            <div class="bg-red-50 border-l-4 border-red-400 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700">
                            <strong>${title}:</strong> ${message}
                        </p>
                    </div>
                </div>
            </div>
        `;
        resultElement.classList.remove('hidden');
    }

    /**
     * Get current file selection status
     * @returns {Object} Current file selection status
     */
    getFileSelectionStatus() {
        return {
            hasLegacyFile: !!this.selectedFile,
            hasBankFile: !!this.selectedBankFile,
            legacyFileName: this.selectedFile ? this.selectedFile.name : null,
            bankFileName: this.selectedBankFile ? this.selectedBankFile.name : null
        };
    }

    /**
     * Clear all file selections and results
     */
    clearAll() {
        this.clearFileSelection('legacy');
        this.clearFileSelection('bank');
    }
}

// Export singleton instance
export const ImportManagerInstance = new ImportManager();