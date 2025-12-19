<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">
        <i class="fas fa-chalkboard text-green-500"></i> Laporan per Kelas
    </h2>
    <p class="text-gray-600">Generate laporan pembayaran SPP per kelas dengan detail tanggal pembayaran</p>
</div>

<div class="bg-white rounded-lg shadow-md p-6">
    <form id="laporanForm" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Kelas</label>
                <input type="text" id="laporanKelasDetail" placeholder="Contoh: X.2, XI.1, XII.3"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Angkatan</label>
                <select id="laporanAngkatanDetail" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="">Pilih Angkatan</option>
                    <option value="2024">2024</option>
                    <option value="2023">2023</option>
                    <option value="2022">2022</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Sekolah</label>
                <select id="laporanSekolahDetail" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="">Pilih Sekolah</option>
                    <option value="SMAN_1_DENPASAR">SMAN 1 DENPASAR</option>
                    <option value="SMAN_2_DENPASAR">SMAN 2 DENPASAR</option>
                    <option value="SMAK_1_DENPASAR">SMAK 1 DENPASAR</option>
                </select>
            </div>
        </div>
        <div class="flex space-x-3">
            <button type="button" id="generateLaporanDetailBtn" class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700 transition duration-200">
                <i class="fas fa-chart-line"></i> Generate Laporan
            </button>
            <button type="button" id="exportLaporanDetailBtn" class="bg-purple-600 text-white px-6 py-2 rounded-md hover:bg-purple-700 transition duration-200">
                <i class="fas fa-file-excel"></i> Export Excel
            </button>
        </div>
    </form>
    <div id="laporanDetailResult" class="mt-6">
        <!-- Result will be inserted here -->
    </div>
</div>