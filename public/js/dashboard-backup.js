// Dashboard JavaScript
// Security: Define API key (in production, this should be handled server-side)
const API_KEY = 'spp-rekon-2024-secret-key';

// Global variables
let selectedFile = null;
let selectedBankFile = null;
let monthlyChart = null;
let schoolChart = null;

// Navigation functionality
function showSection(sectionName) {
    // Hide all sections
    document.querySelectorAll('.content-section').forEach(section => {
        section.classList.add('hidden');
    });

    // Show selected section
    const targetSection = document.getElementById(sectionName);
    if (targetSection) {
        targetSection.classList.remove('hidden');
    }

    // Update nav links styling
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('text-white', 'font-medium');
        link.classList.add('text-blue-100');
    });

    // Highlight active nav link
    const activeLink = document.querySelector(`[data-section="${sectionName}"]`);
    if (activeLink) {
        activeLink.classList.remove('text-blue-100');
        activeLink.classList.add('text-white', 'font-medium');
    }
}

// Add event listeners for navigation
document.addEventListener('DOMContentLoaded', () => {
    // Navigation event listeners
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const sectionName = link.getAttribute('data-section');
            showSection(sectionName);
        });
    });
});

// Common utility functions
function showLoading() {
    const loading = document.getElementById('loading');
    if (loading) loading.classList.remove('hidden');
}

function hideLoading() {
    const loading = document.getElementById('loading');
    if (loading) loading.classList.add('hidden');
}

function showError(message) {
    const errorAlert = document.getElementById('errorAlert');
    const errorMessage = document.getElementById('errorMessage');

    if (errorMessage) errorMessage.textContent = message;
    if (errorAlert) errorAlert.classList.remove('hidden');
}

function hideError() {
    const errorAlert = document.getElementById('errorAlert');
    if (errorAlert) errorAlert.classList.add('hidden');
}

// Dashboard Analytics functionality
async function loadDashboardAnalytics() {
    try {
        const response = await fetch('/api/dashboard/analytics', {
            headers: {
                'X-API-KEY': API_KEY
            }
        });
        const result = await response.json();

        if (result.success) {
            updateSummaryCards(result.summary);
            updateMonthlyChart(result.monthly_data);
            updateSchoolChart(result.school_data);
            updateSummaryTable(result.school_data);
        }
    } catch (error) {
        console.error('Error loading dashboard analytics:', error);
    }
}

function updateSummaryCards(summary) {
    const totalTransactions = document.getElementById('totalTransactions');
    const totalDana = document.getElementById('totalDana');
    const totalSiswa = document.getElementById('totalSiswa');
    const totalSchools = document.getElementById('totalSchools');

    if (totalTransactions) totalTransactions.textContent = summary.total_transactions.toLocaleString('id-ID');
    if (totalDana) totalDana.textContent = 'Rp ' + summary.total_dana.toLocaleString('id-ID');
    if (totalSiswa) totalSiswa.textContent = summary.total_siswa.toLocaleString('id-ID');
    if (totalSchools) totalSchools.textContent = summary.total_schools.toLocaleString('id-ID');
}

function updateMonthlyChart(monthlyData) {
    const ctx = document.getElementById('monthlyChart');
    if (!ctx) return;

    // Prepare data
    const labels = monthlyData.map(item => {
        const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        return `${monthNames[item.bulan - 1]} ${item.tahun}`;
    });
    const transactions = monthlyData.map(item => item.total);
    const dana = monthlyData.map(item => parseInt(item.dana));

    if (monthlyChart) {
        monthlyChart.destroy();
    }

    monthlyChart = new Chart(ctx.getContext('2d'), {
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
        options: {
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
        }
    });
}

function updateSchoolChart(schoolData) {
    const ctx = document.getElementById('schoolChart');
    if (!ctx) return;

    const labels = schoolData.map(item => item.sekolah);
    const data = schoolData.map(item => item.total);

    if (schoolChart) {
        schoolChart.destroy();
    }

    schoolChart = new Chart(ctx.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: [
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(34, 197, 94, 0.8)',
                    'rgba(168, 85, 247, 0.8)',
                    'rgba(251, 146, 60, 0.8)',
                    'rgba(239, 68, 68, 0.8)'
                ],
                borderColor: [
                    'rgba(59, 130, 246, 1)',
                    'rgba(34, 197, 94, 1)',
                    'rgba(168, 85, 247, 1)',
                    'rgba(251, 146, 60, 1)',
                    'rgba(239, 68, 68, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
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
        }
    });
}

function updateSummaryTable(schoolData) {
    const tbody = document.getElementById('summaryTableBody');
    if (!tbody) return;

    tbody.innerHTML = schoolData.map(item => {
        const status = item.total > 10 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
        const statusText = 'Aktif';

        return `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${item.sekolah}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${item.total.toLocaleString('id-ID')}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Rp ${parseInt(item.dana).toLocaleString('id-ID')}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${item.siswa.toLocaleString('id-ID')}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${status}">
                        ${statusText}
                    </span>
                </td>
            </tr>
        `;
    }).join('');
}

// Refresh button functionality
document.addEventListener('DOMContentLoaded', () => {
    const refreshDataBtn = document.getElementById('refreshDataBtn');
    if (refreshDataBtn) {
        refreshDataBtn.addEventListener('click', () => {
            refreshDataBtn.disabled = true;
            refreshDataBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';

            loadDashboardAnalytics().then(() => {
                refreshDataBtn.disabled = false;
                refreshDataBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh Data';
            });
        });
    }
});

// Export functionality
document.addEventListener('DOMContentLoaded', () => {
    const exportExcelBtn = document.getElementById('exportExcelBtn');
    const exportCSVBtn = document.getElementById('exportCSVBtn');

    if (exportExcelBtn) {
        exportExcelBtn.addEventListener('click', () => {
            exportData('excel');
        });
    }

    if (exportCSVBtn) {
        exportCSVBtn.addEventListener('click', () => {
            exportData('csv');
        });
    }
});

async function exportData(type) {
    try {
        // Get current filters
        const sekolah = document.getElementById('sekolah')?.value || '';
        const tahun = document.getElementById('tahun')?.value || '';
        const bulan = document.getElementById('bulan')?.value || '';

        // Build query parameters
        const params = new URLSearchParams();
        if (sekolah) params.append('sekolah', sekolah);
        if (tahun) params.append('tahun', tahun);
        if (bulan) params.append('bulan', bulan);

        const btn = type === 'excel' ? document.getElementById('exportExcelBtn') : document.getElementById('exportCSVBtn');
        if (!btn) return;

        const originalText = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';

        const response = await fetch(`/api/rekon/export/${type}?${params}`, {
            headers: {
                'X-API-KEY': API_KEY
            }
        });
        const result = await response.json();

        if (result.success) {
            // Trigger download
            const downloadUrl = result.download_url;
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.download = result.filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            // Show success message
            alert(`${result.message}\nTotal records: ${result.total_records}\nFilename: ${result.filename}`);
        } else {
            alert('Export failed: ' + result.message);
        }

    } catch (error) {
        console.error('Export error:', error);
        alert('Terjadi kesalahan saat export. Silakan coba lagi.');
    } finally {
        const btn = type === 'excel' ? document.getElementById('exportExcelBtn') : document.getElementById('exportCSVBtn');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
}

// Legacy Import functionality
document.addEventListener('DOMContentLoaded', () => {
    const fileInput = document.getElementById('fileInput');
    const browseBtn = document.getElementById('browseBtn');
    const dropZone = document.getElementById('dropZone');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const removeFileBtn = document.getElementById('removeFileBtn');
    const uploadBtn = document.getElementById('uploadBtn');

    if (browseBtn && fileInput) {
        browseBtn.addEventListener('click', () => {
            fileInput.click();
        });
    }

    if (fileInput) {
        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                handleFileSelect(file);
            }
        });
    }

    if (dropZone) {
        // Drag and drop functionality
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
                handleFileSelect(files[0]);
            }
        });
    }

    if (removeFileBtn) {
        removeFileBtn.addEventListener('click', () => {
            clearFileSelection();
        });
    }

    if (uploadBtn) {
        uploadBtn.addEventListener('click', () => {
            if (selectedFile) {
                uploadFile();
            }
        });
    }
});

function handleFileSelect(file) {
    // Validate file type
    const validExtensions = ['.csv', '.xlsx', '.xls'];
    const fileName = file.name.toLowerCase();
    const isValidExtension = validExtensions.some(ext => fileName.endsWith(ext));

    if (!isValidExtension) {
        showError('File harus berformat CSV, XLS, atau XLSX');
        return;
    }

    // Validate file size (10MB max)
    const maxSize = 10 * 1024 * 1024; // 10MB
    if (file.size > maxSize) {
        showError('Ukuran file maksimal 10MB');
        return;
    }

    selectedFile = file;
    const fileNameElement = document.getElementById('fileName');
    if (fileNameElement) fileNameElement.textContent = file.name;

    const dropZone = document.getElementById('dropZone');
    const fileInfo = document.getElementById('fileInfo');

    if (dropZone) dropZone.classList.add('hidden');
    if (fileInfo) fileInfo.classList.remove('hidden');
    hideError();
}

function clearFileSelection() {
    selectedFile = null;
    const fileInput = document.getElementById('fileInput');
    const dropZone = document.getElementById('dropZone');
    const fileInfo = document.getElementById('fileInfo');
    const importProgress = document.getElementById('importProgress');
    const importResult = document.getElementById('importResult');

    if (fileInput) fileInput.value = '';
    if (dropZone) dropZone.classList.remove('hidden');
    if (fileInfo) fileInfo.classList.add('hidden');
    if (importProgress) importProgress.classList.add('hidden');
    if (importResult) importResult.classList.add('hidden');
}

async function uploadFile() {
    if (!selectedFile) return;

    const formData = new FormData();
    formData.append('file', selectedFile);

    const uploadBtn = document.getElementById('uploadBtn');
    const importProgress = document.getElementById('importProgress');
    const importResult = document.getElementById('importResult');

    // Show progress
    if (importProgress) importProgress.classList.remove('hidden');
    if (importResult) importResult.classList.add('hidden');
    if (uploadBtn) {
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengupload...';
    }

    try {
        const response = await fetch('/api/rekon/import', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'X-API-KEY': API_KEY
            }
        });

        const result = await response.json();

        if (importProgress) importProgress.classList.add('hidden');
        if (uploadBtn) {
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload Data';
        }

        if (result.success) {
            showImportResult(result);
            clearFileSelection();
            // Refresh dashboard analytics
            loadDashboardAnalytics();
        } else {
            showImportError(result.message);
        }

    } catch (error) {
        if (importProgress) importProgress.classList.add('hidden');
        if (uploadBtn) {
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload Data';
        }
        showImportError('Terjadi kesalahan saat mengupload file. Silakan coba lagi.');
        console.error('Upload error:', error);
    }
}

function showImportResult(result) {
    const importResult = document.getElementById('importResult');
    if (!importResult) return;

    const successClass = result.error_count > 0 ? 'bg-yellow-50 border-yellow-400' : 'bg-green-50 border-green-400';

    importResult.innerHTML = `
        <div class="${successClass} border-l-4 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle ${result.error_count > 0 ? 'text-yellow-400' : 'text-green-400'}"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium ${result.error_count > 0 ? 'text-yellow-800' : 'text-green-800'}">
                        ${result.message}
                    </p>
                    <div class="mt-2 text-sm ${result.error_count > 0 ? 'text-yellow-700' : 'text-green-700'}">
                        <p><strong>Detail:</strong></p>
                        <ul class="list-disc list-inside mt-1">
                            <li>Total baris diproses: ${result.total_rows}</li>
                            <li>Berhasil diimport: ${result.imported}</li>
                            ${result.error_count > 0 ? `<li>Gagal: ${result.error_count}</li>` : ''}
                        </ul>
                        ${result.error_count > 0 ? `
                            <details class="mt-2">
                                <summary class="cursor-pointer font-medium">Lihat Error</summary>
                                <ul class="list-disc list-inside mt-1">
                                    ${result.errors.map(error => `<li>${error}</li>`).join('')}
                                </ul>
                            </details>
                        ` : ''}
                    </div>
                </div>
            </div>
        </div>
    `;
    importResult.classList.remove('hidden');
}

function showImportError(message) {
    const importResult = document.getElementById('importResult');
    if (!importResult) return;

    importResult.innerHTML = `
        <div class="bg-red-50 border-l-4 border-red-400 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-red-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700">
                        <strong>Error:</strong> ${message}
                    </p>
                </div>
            </div>
        </div>
    `;
    importResult.classList.remove('hidden');
}

// Bank CSV Import functionality
document.addEventListener('DOMContentLoaded', () => {
    const bankFileInput = document.getElementById('bankFileInput');
    const bankBrowseBtn = document.getElementById('bankBrowseBtn');
    const bankDropZone = document.getElementById('bankDropZone');
    const bankFileInfo = document.getElementById('bankFileInfo');
    const bankFileName = document.getElementById('bankFileName');
    const bankRemoveFileBtn = document.getElementById('bankRemoveFileBtn');
    const bankUploadBtn = document.getElementById('bankUploadBtn');

    if (bankBrowseBtn && bankFileInput) {
        bankBrowseBtn.addEventListener('click', () => {
            bankFileInput.click();
        });
    }

    if (bankFileInput) {
        bankFileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                handleBankFileSelect(file);
            }
        });
    }

    if (bankDropZone) {
        bankDropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            bankDropZone.classList.add('border-blue-500', 'bg-blue-50');
        });

        bankDropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            bankDropZone.classList.remove('border-blue-500', 'bg-blue-50');
        });

        bankDropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            bankDropZone.classList.remove('border-blue-500', 'bg-blue-50');

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleBankFileSelect(files[0]);
            }
        });
    }

    if (bankRemoveFileBtn) {
        bankRemoveFileBtn.addEventListener('click', () => {
            clearBankFileSelection();
        });
    }

    if (bankUploadBtn) {
        bankUploadBtn.addEventListener('click', () => {
            if (selectedBankFile) {
                uploadBankFile();
            }
        });
    }
});

function handleBankFileSelect(file) {
    const validExtensions = ['.csv', '.xlsx', '.xls'];
    const fileName = file.name.toLowerCase();
    const isValidExtension = validExtensions.some(ext => fileName.endsWith(ext));

    if (!isValidExtension) {
        showError('File harus berformat CSV, XLS, atau XLSX');
        return;
    }

    const maxSize = 10 * 1024 * 1024; // 10MB
    if (file.size > maxSize) {
        showError('Ukuran file maksimal 10MB');
        return;
    }

    selectedBankFile = file;
    const bankFileName = document.getElementById('bankFileName');
    if (bankFileName) bankFileName.textContent = file.name;

    const bankDropZone = document.getElementById('bankDropZone');
    const bankFileInfo = document.getElementById('bankFileInfo');

    if (bankDropZone) bankDropZone.classList.add('hidden');
    if (bankFileInfo) bankFileInfo.classList.remove('hidden');
    hideError();
}

function clearBankFileSelection() {
    selectedBankFile = null;
    const bankFileInput = document.getElementById('bankFileInput');
    const bankDropZone = document.getElementById('bankDropZone');
    const bankFileInfo = document.getElementById('bankFileInfo');
    const bankImportProgress = document.getElementById('bankImportProgress');
    const bankImportResult = document.getElementById('bankImportResult');

    if (bankFileInput) bankFileInput.value = '';
    if (bankDropZone) bankDropZone.classList.remove('hidden');
    if (bankFileInfo) bankFileInfo.classList.add('hidden');
    if (bankImportProgress) bankImportProgress.classList.add('hidden');
    if (bankImportResult) bankImportResult.classList.add('hidden');
}

async function uploadBankFile() {
    if (!selectedBankFile) return;

    const formData = new FormData();
    formData.append('file', selectedBankFile);

    const bankUploadBtn = document.getElementById('bankUploadBtn');
    const bankImportProgress = document.getElementById('bankImportProgress');
    const bankImportResult = document.getElementById('bankImportResult');

    bankImportProgress.classList.remove('hidden');
    if (bankImportResult) bankImportResult.classList.add('hidden');
    if (bankUploadBtn) {
        bankUploadBtn.disabled = true;
        bankUploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengupload...';
    }

    try {
        const response = await fetch('/api/rekon/import-bank', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'X-API-KEY': API_KEY
            }
        });

        const result = await response.json();

        if (bankImportProgress) bankImportProgress.classList.add('hidden');
        if (bankUploadBtn) {
            bankUploadBtn.disabled = false;
            bankUploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload CSV Bank';
        }

        if (result.success) {
            showBankImportResult(result);
            clearBankFileSelection();
            // Refresh dashboard analytics
            loadDashboardAnalytics();
        } else {
            showBankImportError(result.message);
        }

    } catch (error) {
        if (bankImportProgress) bankImportProgress.classList.add('hidden');
        if (bankUploadBtn) {
            bankUploadBtn.disabled = false;
            bankUploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload CSV Bank';
        }
        showBankImportError('Terjadi kesalahan saat mengupload file bank. Silakan coba lagi.');
        console.error('Bank upload error:', error);
    }
}

function showBankImportResult(result) {
    const bankImportResult = document.getElementById('bankImportResult');
    if (!bankImportResult) return;

    const successClass = result.error_count > 0 ? 'bg-yellow-50 border-yellow-400' : 'bg-green-50 border-green-400';

    bankImportResult.innerHTML = `
        <div class="${successClass} border-l-4 p-3">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-university ${result.error_count > 0 ? 'text-yellow-400' : 'text-green-400'}"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium ${result.error_count > 0 ? 'text-yellow-800' : 'text-green-800'}">
                        ${result.message}
                    </p>
                    <div class="mt-2 text-sm ${result.error_count > 0 ? 'text-yellow-700' : 'text-green-700'}">
                        <p><strong>Detail Import CSV Bank:</strong></p>
                        <ul class="list-disc list-inside">
                            <li>Total baris diproses: ${result.total_rows}</li>
                            <li>Berhasil diimport: ${result.imported}</li>
                            ${result.duplicates > 0 ? `<li>Duplicate dilewati: ${result.duplicates}</li>` : ''}
                            ${result.error_count > 0 ? `<li>Gagal: ${result.error_count}</li>` : ''}
                        </ul>
                        ${result.error_count > 0 ? `
                            <details class="mt-2">
                                <summary class="cursor-pointer font-medium">Lihat Error</summary>
                                <ul class="list-disc list-inside mt-1">
                                    ${result.errors.map(error => `<li class="text-xs">${error}</li>`).join('')}
                                </ul>
                            </details>
                        ` : ''}
                    </div>
                </div>
            </div>
        </div>
    `;
    bankImportResult.classList.remove('hidden');
}

function showBankImportError(message) {
    const bankImportResult = document.getElementById('bankImportResult');
    if (!bankImportResult) return;

    bankImportResult.innerHTML = `
        <div class="bg-red-50 border-l-4 border-red-400 p-3">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-red-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700">
                        <strong>Error Bank Import:</strong> ${message}
                    </p>
                </div>
            </div>
        </div>
    `;
    bankImportResult.classList.remove('hidden');
}

// Laporan Kelas functionality
document.addEventListener('DOMContentLoaded', () => {
    const generateLaporanBtn = document.getElementById('generateLaporanBtn');
    const exportLaporanBtn = document.getElementById('exportLaporanBtn');
    const generateLaporanDetailBtn = document.getElementById('generateLaporanDetailBtn');
    const exportLaporanDetailBtn = document.getElementById('exportLaporanDetailBtn');

    if (generateLaporanBtn) {
        generateLaporanBtn.addEventListener('click', () => {
            generateLaporanKelas();
        });
    }

    if (exportLaporanBtn) {
        exportLaporanBtn.addEventListener('click', () => {
            exportLaporanKelas();
        });
    }

    if (generateLaporanDetailBtn) {
        generateLaporanDetailBtn.addEventListener('click', () => {
            // Copy values from detailed form to main form and trigger
            const kelas = document.getElementById('laporanKelasDetail')?.value || '';
            const angkatan = document.getElementById('laporanAngkatanDetail')?.value || '';
            const sekolah = document.getElementById('laporanSekolahDetail')?.value || '';

            const laporanKelas = document.getElementById('laporanKelas');
            const laporanAngkatan = document.getElementById('laporanAngkatan');
            const laporanSekolah = document.getElementById('laporanSekolah');

            if (laporanKelas) laporanKelas.value = kelas;
            if (laporanAngkatan) laporanAngkatan.value = angkatan;
            if (laporanSekolah) laporanSekolah.value = sekolah;

            generateLaporanKelas();
        });
    }

    if (exportLaporanDetailBtn) {
        exportLaporanDetailBtn.addEventListener('click', () => {
            // Copy values from detailed form to main form and trigger
            const kelas = document.getElementById('laporanKelasDetail')?.value || '';
            const angkatan = document.getElementById('laporanAngkatanDetail')?.value || '';
            const sekolah = document.getElementById('laporanSekolahDetail')?.value || '';

            const laporanKelas = document.getElementById('laporanKelas');
            const laporanAngkatan = document.getElementById('laporanAngkatan');
            const laporanSekolah = document.getElementById('laporanSekolah');

            if (laporanKelas) laporanKelas.value = kelas;
            if (laporanAngkatan) laporanAngkatan.value = angkatan;
            if (laporanSekolah) laporanSekolah.value = sekolah;

            exportLaporanKelas();
        });
    }

    // Sync form inputs between different laporan sections
    syncLaporanForms();
});

function syncLaporanForms() {
    const forms = [
        { source: 'laporanKelas', targets: ['laporanKelasDetail'] },
        { source: 'laporanAngkatan', targets: ['laporanAngkatanDetail'] },
        { source: 'laporanSekolah', targets: ['laporanSekolahDetail'] }
    ];

    forms.forEach(({ source, targets }) => {
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

    // Sync detailed form back to main form
    const detailForms = [
        { source: 'laporanKelasDetail', targets: ['laporanKelas'] },
        { source: 'laporanAngkatanDetail', targets: ['laporanAngkatan'] },
        { source: 'laporanSekolahDetail', targets: ['laporanSekolah'] }
    ];

    detailForms.forEach(({ source, targets }) => {
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

async function generateLaporanKelas() {
    const kelas = document.getElementById('laporanKelas')?.value?.trim() || '';
    const angkatan = document.getElementById('laporanAngkatan')?.value || '';
    const sekolah = document.getElementById('laporanSekolah')?.value || '';

    if (!kelas || !angkatan || !sekolah) {
        showError('Mohon lengkapi semua field: Kelas, Angkatan, dan Sekolah');
        return;
    }

    const generateLaporanBtn = document.getElementById('generateLaporanBtn');
    if (generateLaporanBtn) {
        generateLaporanBtn.disabled = true;
        generateLaporanBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generate...';
    }

    try {
        const response = await fetch(`/api/rekon/laporan-kelas?sekolah=${sekolah}&kelas=${kelas}&angkatan=${angkatan}`, {
            headers: {
                'X-API-KEY': API_KEY
            }
        });

        const result = await response.json();

        if (generateLaporanBtn) {
            generateLaporanBtn.disabled = false;
            generateLaporanBtn.innerHTML = '<i class="fas fa-chart-line"></i> Generate';
        }

        if (result.success) {
            displayLaporanKelas(result.data);
        } else {
            showError(result.message);
        }

    } catch (error) {
        if (generateLaporanBtn) {
            generateLaporanBtn.disabled = false;
            generateLaporanBtn.innerHTML = '<i class="fas fa-chart-line"></i> Generate';
        }
        showError('Terjadi kesalahan saat generate laporan. Silakan coba lagi.');
        console.error('Laporan error:', error);
    }
}

function displayLaporanKelas(data) {
    const laporanResult = document.getElementById('laporanResult');
    if (!laporanResult) return;

    const headers = data.headers;
    const siswa = data.siswa;

    // Build HTML table for laporan kelas
    let tableHtml = `
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
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 border border-gray-300">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-2 py-1 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r">No</th>
                        <th class="px-2 py-1 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r">NIS</th>
                        <th class="px-4 py-1 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
    `;

    // Add month headers
    headers.forEach((header, index) => {
        const isYearBreak = index > 0 && header.year !== headers[index-1].year;
        if (isYearBreak) {
            tableHtml += `<th class="px-1 py-1 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-l border-t" colspan="1">${header.year}</th>`;
        } else {
            tableHtml += `<th class="px-1 py-1 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-r" title="${header.label} ${header.year}">${header.month}</th>`;
        }
    });

    tableHtml += `</tr></thead><tbody class="bg-white divide-y divide-gray-200">`;

    // Add student data
    siswa.forEach((student, index) => {
        tableHtml += `
            <tr class="hover:bg-gray-50">
                <td class="px-2 py-1 text-xs text-gray-900 border-r">${student.no}</td>
                <td class="px-2 py-1 text-xs text-gray-500 border-r">${student.nis}</td>
                <td class="px-4 py-1 text-xs text-gray-900 border-r">${student.nama}</td>
        `;

        // Add payment data for each month
        student.pembayaran.forEach((payment, paymentIndex) => {
            const isPaid = payment !== '-';
            const bgColor = isPaid ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
            const borderClass = paymentIndex < student.pembayaran.length - 1 ? 'border-r' : '';

            tableHtml += `
                <td class="px-1 py-1 text-center text-xs ${bgColor} ${borderClass} min-w-[50px]">
                    ${isPaid ? payment : '-'}
                </td>
            `;
        });

        tableHtml += `</tr>`;
    });

    tableHtml += `</tbody></table></div>`;

    laporanResult.innerHTML = tableHtml;
    laporanResult.classList.remove('hidden');
}

async function exportLaporanKelas() {
    const kelas = document.getElementById('laporanKelas')?.value?.trim() || '';
    const angkatan = document.getElementById('laporanAngkatan')?.value || '';
    const sekolah = document.getElementById('laporanSekolah')?.value || '';

    if (!kelas || !angkatan || !sekolah) {
        showError('Mohon lengkapi semua field: Kelas, Angkatan, dan Sekolah');
        return;
    }

    const exportLaporanBtn = document.getElementById('exportLaporanBtn');
    if (exportLaporanBtn) {
        exportLaporanBtn.disabled = true;
        exportLaporanBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
    }

    try {
        const response = await fetch(`/api/rekon/laporan-kelas/export?sekolah=${sekolah}&kelas=${kelas}&angkatan=${angkatan}`, {
            headers: {
                'X-API-KEY': API_KEY
            }
        });

        const result = await response.json();

        if (exportLaporanBtn) {
            exportLaporanBtn.disabled = false;
            exportLaporanBtn.innerHTML = '<i class="fas fa-file-excel"></i> Export Excel';
        }

        if (result.success) {
            // Trigger download
            const link = document.createElement('a');
            link.href = result.download_url;
            link.download = result.filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            alert(`${result.message}\nTotal records: ${result.total_records}\nFilename: ${result.filename}`);
        } else {
            showError('Export failed: ' + result.message);
        }

    } catch (error) {
        if (exportLaporanBtn) {
            exportLaporanBtn.disabled = false;
            exportLaporanBtn.innerHTML = '<i class="fas fa-file-excel"></i> Export Excel';
        }
        showError('Terjadi kesalahan saat export laporan. Silakan coba lagi.');
        console.error('Export error:', error);
    }
}

// Search functionality
document.addEventListener('DOMContentLoaded', () => {
    const searchForm = document.getElementById('searchForm');
    const clearBtn = document.getElementById('clearBtn');

    if (searchForm) {
        searchForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(searchForm);
            const sekolah = formData.get('sekolah');
            const tahun = formData.get('tahun');
            const bulan = formData.get('bulan');

            searchData(sekolah, tahun, bulan);
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            if (searchForm) searchForm.reset();
            const resultCard = document.getElementById('resultCard');
            const dataTable = document.getElementById('dataTable');
            if (resultCard) resultCard.classList.add('hidden');
            if (dataTable) dataTable.classList.add('hidden');
            hideError();
        });
    }

    // Load schools for search form
    loadSchools();
});

// Search function
async function searchData(sekolah, tahun, bulan) {
    try {
        showLoading();
        hideError();

        const response = await fetch(`/api/rekon/search?sekolah=${sekolah}&tahun=${tahun}&bulan=${bulan}`, {
            headers: {
                'X-API-KEY': API_KEY
            }
        });
        const result = await response.json();

        hideLoading();

        if (result.success) {
            displayResult(result);
        } else {
            showError(result.message);
        }
    } catch (error) {
        hideLoading();
        showError('Terjadi kesalahan saat mengambil data. Silakan coba lagi.');
        console.error('Error:', error);
    }
}

// Display result
function displayResult(result) {
    const resultContent = document.getElementById('resultContent');
    const tableBody = document.getElementById('tableBody');

    if (!resultContent || !tableBody) return;

    // Summary card
    resultContent.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-blue-50 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-users text-blue-500 text-2xl mr-3"></i>
                    <div>
                        <p class="text-sm text-gray-600">Total Records</p>
                        <p class="text-xl font-bold text-blue-600">${result.data.length}</p>
                    </div>
                </div>
            </div>
            <div class="bg-green-50 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-money-bill-wave text-green-500 text-2xl mr-3"></i>
                    <div>
                        <p class="text-sm text-gray-600">Total Dana Masyarakat</p>
                        <p class="text-xl font-bold text-green-600">Rp ${parseInt(result.summary.total_dana_masyarakat).toLocaleString('id-ID')}</p>
                    </div>
                </div>
            </div>
            <div class="bg-purple-50 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-user-graduate text-purple-500 text-2xl mr-3"></i>
                    <div>
                        <p class="text-sm text-gray-600">Siswa Unik</p>
                        <p class="text-xl font-bold text-purple-600">${result.summary.unique_students}</p>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Table data
    tableBody.innerHTML = result.data.map(item => `
        <tr class="hover:bg-gray-50">
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${item.sekolah}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${item.id_siswa}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${item.nama_siswa}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${item.kelas}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${item.jurusan}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Rp ${parseInt(item.jum_tagihan).toLocaleString('id-ID')}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Rp ${parseInt(item.dana_masyarakat).toLocaleString('id-ID')}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${item.tgl_tx_formatted}</td>
        </tr>
    `).join('');

    const resultCard = document.getElementById('resultCard');
    const dataTable = document.getElementById('dataTable');
    if (resultCard) resultCard.classList.remove('hidden');
    if (dataTable) dataTable.classList.remove('hidden');
}

// Load schools dynamically
async function loadSchools() {
    try {
        const response = await fetch('/api/schools', {
            headers: {
                'X-API-KEY': API_KEY
            }
        });
        const result = await response.json();

        if (result.success) {
            const sekolahSelect = document.getElementById('sekolah');
            if (sekolahSelect) {
                sekolahSelect.innerHTML = '<option value="">Pilih Sekolah</option>';

                result.data.forEach(school => {
                    const option = document.createElement('option');
                    option.value = school.name;
                    option.textContent = school.display_name;
                    sekolahSelect.appendChild(option);
                });
            }
        }
    } catch (error) {
        console.error('Error loading schools:', error);
        // Fallback to hardcoded options
        const sekolahSelect = document.getElementById('sekolah');
        if (sekolahSelect) {
            sekolahSelect.innerHTML = `
                <option value="">Pilih Sekolah</option>
                <option value="SMAN_1_DENPASAR">SMAN 1 DENPASAR</option>
                <option value="SMAN_2_DENPASAR">SMAN 2 DENPASAR</option>
                <option value="SMAK_1_DENPASAR">SMAK 1 DENPASAR</option>
            `;
        }
    }
}

// Initialize on load
window.addEventListener('DOMContentLoaded', () => {
    console.log('Sistem Rekon SPP - Ready');
    loadDashboardAnalytics();

    // Show dashboard by default
    showSection('dashboard');
});