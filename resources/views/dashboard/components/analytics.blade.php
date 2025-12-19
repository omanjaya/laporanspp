<!-- Dashboard Analytics -->
<div class="mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">
        <i class="fas fa-chart-line text-purple-500"></i> Dashboard Analytics
    </h2>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Total Transaksi</p>
                    <p class="text-2xl font-bold text-blue-600" id="totalTransactions">-</p>
                </div>
                <div class="bg-blue-100 p-3 rounded-full">
                    <i class="fas fa-file-invoice text-blue-500"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Total Dana</p>
                    <p class="text-2xl font-bold text-green-600" id="totalDana">-</p>
                </div>
                <div class="bg-green-100 p-3 rounded-full">
                    <i class="fas fa-money-bill-wave text-green-500"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Siswa Aktif</p>
                    <p class="text-2xl font-bold text-purple-600" id="totalSiswa">-</p>
                </div>
                <div class="bg-purple-100 p-3 rounded-full">
                    <i class="fas fa-users text-purple-500"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Sekolah Aktif</p>
                    <p class="text-2xl font-bold text-orange-600" id="totalSchools">-</p>
                </div>
                <div class="bg-orange-100 p-3 rounded-full">
                    <i class="fas fa-school text-orange-500"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Monthly Transactions Chart -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-800">
                <i class="fas fa-chart-bar text-blue-500"></i> Transaksi per Bulan
            </h3>
            <canvas id="monthlyChart" width="400" height="200"></canvas>
        </div>

        <!-- School Distribution Chart -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-800">
                <i class="fas fa-chart-pie text-green-500"></i> Distribusi per Sekolah
            </h3>
            <canvas id="schoolChart" width="400" height="200"></canvas>
        </div>
    </div>

    <!-- Data Table Summary -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-table text-purple-500"></i> Ringkasan Data
            </h3>
            <button id="refreshDataBtn" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                <i class="fas fa-sync-alt"></i> Refresh Data
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sekolah</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Transaksi</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Dana</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Siswa Unik</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody id="summaryTableBody" class="bg-white divide-y divide-gray-200">
                    <!-- Data will be inserted here -->
                </tbody>
            </table>
        </div>
    </div>
</div>