<!-- Common Elements (used across sections) -->
<!-- Loading State -->
<div id="loading" class="hidden bg-white rounded-lg shadow-md p-6 mb-6">
    <div class="flex items-center justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <span class="ml-3 text-gray-600">Mencari data...</span>
    </div>
</div>

<!-- Error Alert -->
<div id="errorAlert" class="hidden bg-red-50 border-l-4 border-red-400 p-4 mb-6">
    <div class="flex">
        <div class="flex-shrink-0">
            <i class="fas fa-exclamation-triangle text-red-400"></i>
        </div>
        <div class="ml-3">
            <p class="text-sm text-red-700" id="errorMessage"></p>
        </div>
    </div>
</div>