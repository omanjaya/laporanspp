<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sistem Rekon SPP</title>

    @if(app()->environment('local'))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <!-- Production: Use Tailwind CDN + inline styles -->
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="{{ asset('js/dashboard.js') }}" defer></script>
    @endif
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header with Navigation -->
    @include('dashboard.components.header')

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <!-- Dashboard Section -->
        <section id="dashboard" class="content-section">
            @include('dashboard.components.hero')
        </section>

        <!-- Rekonciliasi Section -->
        <section id="rekonciliasi" class="content-section hidden">
            @include('dashboard.components.rekonciliasi')
        </section>

        <!-- Laporan Section -->
        <section id="laporan" class="content-section hidden">
            @include('dashboard.components.laporan')
        </section>

        <!-- Pencarian Section -->
        <section id="pencarian" class="content-section hidden">
            @include('dashboard.components.pencarian')
        </section>

        <!-- Import Section -->
        <section id="import" class="content-section hidden">
            @include('dashboard.components.import')
        </section>

        <!-- Common Elements -->
        @include('dashboard.components.common-elements')
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-6 mt-12">
        <div class="container mx-auto px-4 text-center">
            <p class="text-sm">
                <i class="fas fa-code"></i>
                Sistem Rekon SPP - Dikembangkan dengan Laravel
            </p>
        </div>
    </footer>

    <!-- JavaScript -->
    <script>
        // Dashboard initialization will be handled by Vite's app.js
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Sistem Rekon SPP - Dashboard loaded');
        });
    </script>
</body>
</html>