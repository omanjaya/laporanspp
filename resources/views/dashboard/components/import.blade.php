<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">
        <i class="fas fa-file-upload text-green-500"></i> Import Data Excel/CSV (Legacy)
    </h2>
    <p class="text-gray-600">Import data pembayaran SPP dari file Excel atau CSV format lama</p>
</div>

<div class="bg-white rounded-lg shadow-md p-6">
    <div id="importSection">
        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-gray-400 transition duration-200">
            <div id="dropZone" class="drop-zone">
                <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                <p class="text-gray-600 mb-2">Drag & drop file Excel/CSV Anda di sini</p>
                <p class="text-sm text-gray-500 mb-4">atau</p>
                <input type="file" id="fileInput" accept=".csv,.xlsx,.xls" class="hidden">
                <button type="button" id="browseBtn" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition duration-200">
                    <i class="fas fa-folder-open"></i> Pilih File
                </button>
            </div>
            <div id="fileInfo" class="hidden mt-4">
                <div class="flex items-center justify-between bg-gray-50 p-3 rounded-md">
                    <div class="flex items-center">
                        <i class="fas fa-file-excel text-green-500 mr-2"></i>
                        <span id="fileName" class="text-sm text-gray-700"></span>
                    </div>
                    <button type="button" id="removeFileBtn" class="text-red-500 hover:text-red-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="mt-3">
                    <button type="button" id="uploadBtn" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-upload"></i> Upload Data
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="importProgress" class="hidden mt-4">
        <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600"></div>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700" id="importMessage">Sedang mengimport data...</p>
                </div>
            </div>
        </div>
    </div>

    <div id="importResult" class="hidden mt-4">
        <!-- Result will be inserted here -->
    </div>
</div>