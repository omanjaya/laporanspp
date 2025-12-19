<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">
        <i class="fas fa-university text-blue-500"></i> Rekonciliasi Bank
    </h2>
    <p class="text-gray-600">Upload CSV rekening koran dari bank untuk proses rekonciliasi pembayaran SPP</p>
</div>

<div class="bg-white rounded-lg shadow-md p-6">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Upload CSV Bank -->
        <div>
            <h3 class="text-lg font-medium mb-3 text-gray-700">
                <i class="fas fa-university text-gray-500"></i> Upload CSV Bank
            </h3>
            <div class="border-2 border-dashed border-blue-300 rounded-lg p-4 text-center hover:border-blue-400 transition duration-200">
                <div id="bankDropZone" class="drop-zone">
                    <i class="fas fa-university text-4xl text-blue-400 mb-3"></i>
                    <p class="text-gray-600 text-sm mb-2">Upload CSV rekening koran dari Bank</p>
                    <p class="text-xs text-gray-500 mb-3">Format: Instansi, No. Tagihan, Nama, Tanggal Transaksi, dll</p>
                    <input type="file" id="bankFileInput" accept=".csv,.xlsx,.xls" class="hidden">
                    <button type="button" id="bankBrowseBtn" class="bg-blue-600 text-white px-3 py-2 rounded-md hover:bg-blue-700 transition duration-200 text-sm">
                        <i class="fas fa-folder-open"></i> Pilih File CSV Bank
                    </button>
                </div>
                <div id="bankFileInfo" class="hidden mt-3">
                    <div class="flex items-center justify-between bg-blue-50 p-2 rounded-md">
                        <div class="flex items-center">
                            <i class="fas fa-file-csv text-blue-500 mr-2"></i>
                            <span id="bankFileName" class="text-xs text-gray-700"></span>
                        </div>
                        <button type="button" id="bankRemoveFileBtn" class="text-red-500 hover:text-red-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="mt-2">
                        <button type="button" id="bankUploadBtn" class="w-full bg-blue-600 text-white px-3 py-2 rounded-md hover:bg-blue-700 transition duration-200 text-sm">
                            <i class="fas fa-upload"></i> Upload CSV Bank
                        </button>
                    </div>
                </div>
            </div>
            <div id="bankImportProgress" class="hidden mt-3">
                <div class="bg-blue-50 border-l-4 border-blue-400 p-3">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
                        </div>
                        <div class="ml-2">
                            <p class="text-xs text-blue-700" id="bankImportMessage">Sedang memproses CSV Bank...</p>
                        </div>
                    </div>
                </div>
            </div>
            <div id="bankImportResult" class="hidden mt-3">
                <!-- Result will be inserted here -->
            </div>
        </div>

        <!-- Quick Laporan Preview -->
        <div>
            <h3 class="text-lg font-medium mb-3 text-gray-700">
                <i class="fas fa-chalkboard text-green-500"></i> Quick Laporan Kelas
            </h3>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kelas</label>
                    <input type="text" id="laporanKelas" placeholder="X.2, XI.1, XII.3"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Angkatan</label>
                    <select id="laporanAngkatan" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 text-sm">
                        <option value="">Pilih Angkatan</option>
                        <option value="2024">2024</option>
                        <option value="2023">2023</option>
                        <option value="2022">2022</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sekolah</label>
                    <select id="laporanSekolah" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 text-sm">
                        <option value="">Pilih Sekolah</option>
                        <option value="SMAN_1_DENPASAR">SMAN 1 DENPASAR</option>
                        <option value="SMAN_2_DENPASAR">SMAN 2 DENPASAR</option>
                        <option value="SMAK_1_DENPASAR">SMAK 1 DENPASAR</option>
                    </select>
                </div>
                <div class="flex space-x-2">
                    <button type="button" id="generateLaporanBtn" class="flex-1 bg-green-600 text-white px-3 py-2 rounded-md hover:bg-green-700 transition duration-200 text-sm">
                        <i class="fas fa-chart-line"></i> Generate
                    </button>
                    <button type="button" id="exportLaporanBtn" class="flex-1 bg-purple-600 text-white px-3 py-2 rounded-md hover:bg-purple-700 transition duration-200 text-sm">
                        <i class="fas fa-file-excel"></i> Export
                    </button>
                </div>
            </div>
            <div id="laporanResult" class="hidden mt-3">
                <!-- Result will be inserted here -->
            </div>
        </div>
    </div>
</div>