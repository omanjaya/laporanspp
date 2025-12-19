<header class="bg-blue-600 text-white shadow-lg">
    <div class="container mx-auto px-4 py-6">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center space-x-3">
                <i class="fas fa-school text-2xl"></i>
                <div>
                    <h1 class="text-2xl font-bold">Sistem Rekon SPP</h1>
                    <p class="text-blue-100 text-sm">Pencarian Data Pembayaran SPP Berbasis Web</p>
                </div>
            </div>
            <div class="text-sm">
                <span class="bg-blue-700 px-3 py-1 rounded-full mr-2">
                    <i class="fas fa-database"></i> Laravel + MySQL
                </span>
                <button id="exportExcelBtn" class="bg-green-600 text-white px-3 py-1 rounded-full hover:bg-green-700 transition duration-200">
                    <i class="fas fa-file-excel"></i> Export Excel
                </button>
                <button id="exportCSVBtn" class="bg-purple-600 text-white px-3 py-1 rounded-full hover:bg-purple-700 transition duration-200">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="border-t border-blue-500 pt-4">
            <ul class="flex space-x-6">
                <li>
                    <a href="#dashboard" class="nav-link flex items-center space-x-2 text-white hover:text-blue-200 transition duration-200 font-medium" data-section="dashboard">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="#rekonciliasi" class="nav-link flex items-center space-x-2 text-blue-100 hover:text-white transition duration-200" data-section="rekonciliasi">
                        <i class="fas fa-university"></i>
                        <span>Rekonciliasi Bank</span>
                    </a>
                </li>
                <li>
                    <a href="#laporan" class="nav-link flex items-center space-x-2 text-blue-100 hover:text-white transition duration-200" data-section="laporan">
                        <i class="fas fa-chalkboard"></i>
                        <span>Laporan Kelas</span>
                    </a>
                </li>
                <li>
                    <a href="#pencarian" class="nav-link flex items-center space-x-2 text-blue-100 hover:text-white transition duration-200" data-section="pencarian">
                        <i class="fas fa-search"></i>
                        <span>Pencarian Data</span>
                    </a>
                </li>
                <li>
                    <a href="#import" class="nav-link flex items-center space-x-2 text-blue-100 hover:text-white transition duration-200" data-section="import">
                        <i class="fas fa-upload"></i>
                        <span>Import Legacy</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</header>