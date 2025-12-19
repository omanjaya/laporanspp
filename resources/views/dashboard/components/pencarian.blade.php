<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">
        <i class="fas fa-search text-blue-500"></i> Pencarian Data Rekon
    </h2>
    <p class="text-gray-600">Cari data pembayaran SPP berdasarkan sekolah, tahun, dan bulan</p>
</div>

<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <form id="searchForm" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="sekolah" class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-school"></i> Sekolah
                </label>
                <select id="sekolah" name="sekolah" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Pilih Sekolah</option>
                </select>
            </div>
            <div>
                <label for="tahun" class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-calendar"></i> Tahun
                </label>
                <select id="tahun" name="tahun" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Pilih Tahun</option>
                    <option value="2025">2025</option>
                    <option value="2024">2024</option>
                    <option value="2023">2023</option>
                    <option value="2022">2022</option>
                </select>
            </div>
            <div>
                <label for="bulan" class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-calendar-alt"></i> Bulan
                </label>
                <select id="bulan" name="bulan" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Pilih Bulan</option>
                    <option value="1">Januari</option>
                    <option value="2">Februari</option>
                    <option value="3">Maret</option>
                    <option value="4">April</option>
                    <option value="5">Mei</option>
                    <option value="6">Juni</option>
                    <option value="7">Juli</option>
                    <option value="8">Agustus</option>
                    <option value="9">September</option>
                    <option value="10">Oktober</option>
                    <option value="11">November</option>
                    <option value="12">Desember</option>
                </select>
            </div>
        </div>
        <div class="flex space-x-3">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                <i class="fas fa-search"></i> Cari Data
            </button>
            <button type="button" id="clearBtn" class="bg-gray-500 text-white px-6 py-2 rounded-md hover:bg-gray-600 transition duration-200">
                <i class="fas fa-times"></i> Clear
            </button>
        </div>
    </form>
</div>

<div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
    <div class="flex">
        <div class="flex-shrink-0">
            <i class="fas fa-info-circle text-yellow-400"></i>
        </div>
        <div class="ml-3">
            <p class="text-sm text-yellow-700">
                <strong>Formula Excel Equivalent:</strong>
                <code class="bg-yellow-100 px-2 py-1 rounded text-xs">
                    =IFERROR(INDEX(Rekon!$P:$P; MATCH(1; (Rekon!$B:$B=B$7)*(Rekon!$L:$L=D5)*(Rekon!$K:$K=D4); 0));"-")
                </code>
            </p>
        </div>
    </div>
</div>

<!-- Search results area -->
<div id="searchResults">
    <div id="resultCard" class="hidden bg-white rounded-lg shadow-md p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4 text-gray-800">
            <i class="fas fa-chart-bar text-green-500"></i> Hasil Pencarian
        </h3>
        <div id="resultContent">
            <!-- Content will be inserted here -->
        </div>
    </div>

    <div id="dataTable" class="hidden bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-table text-blue-500"></i> Detail Data
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sekolah</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Siswa</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Siswa</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kelas</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jurusan</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tagihan</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dana Masyarakat</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                    </tr>
                </thead>
                <tbody id="tableBody" class="bg-white divide-y divide-gray-200">
                    <!-- Data rows will be inserted here -->
                </tbody>
            </table>
        </div>
    </div>
</div>