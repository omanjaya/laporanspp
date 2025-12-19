# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel-based **SPP (School Fee Payment) Reconciliation System** for Indonesian schools. The system processes bank CSV files, matches payments to student records, and generates class-level payment reports with Excel export functionality.

## Development Commands

### Setup
```bash
composer run setup        # Install dependencies, generate keys, migrate, install npm packages, build assets
```

### Development Server
```bash
composer run dev          # Start Laravel server, queue worker, logs, and Vite concurrently
php artisan serve         # Laravel development server only
npm run dev              # Vite development server for frontend assets
```

### Testing
```bash
composer run test         # Run PHPUnit tests
php artisan test          # Run tests directly
```

### Code Quality
```bash
php artisan route:list    # List all available routes
php artisan migrate       # Run database migrations
php artisan tinker        # Laravel REPL for testing code snippets
```

## Architecture Overview

### Controller Structure (SOLID Principles)
The system follows a **separation of concerns** architecture with specialized controllers:

- **DashboardController**: Analytics data, school listing, dashboard overview
- **RekonSearchController**: Payment data search with Excel-like INDEX/MATCH functionality
- **RekonImportController**: File upload handling (both legacy format and bank CSV)
- **RekonReportController**: Class report generation and Excel/CSV exports

### Service Layer
Business logic is extracted into dedicated services:

- **RekonImportService**: Standard CSV/Excel file processing
- **BankCsvImportService**: Bank-specific CSV format handling with column mapping
- **RekonExportService**: Excel and CSV export functionality using PhpSpreadsheet

### View Architecture
The frontend uses **component-based Blade templates**:

- Main view: `resources/views/dashboard.blade.php` → extends `dashboard.index`
- Components: `resources/views/dashboard/components/*.blade.php`
  - `header.blade.php` - Navigation
  - `analytics.blade.php` - Charts and dashboard metrics
  - `rekonciliasi.blade.php` - Bank CSV upload interface
  - `pencarian.blade.php` - Search form and results
  - `laporan.blade.php` - Class report generation
  - `import.blade.php` - Legacy file import

### Frontend Technology
- **JavaScript**: Separate file at `public/js/dashboard.js` (1200+ lines)
- **CSS**: Tailwind CSS v4 with custom components
- **Charts**: Chart.js for dashboard analytics
- **Icons**: Font Awesome 6.4

### Data Flow
1. **Bank CSV Upload** → `RekonImportController::importBankCsv()` → `BankCsvImportService`
2. **Data Search** → `RekonSearchController::search()` → Excel INDEX/MATCH logic
3. **Report Generation** → `RekonReportController::getLaporanKelas()` → Payment date matching
4. **Exports** → `RekonExportService` → PhpSpreadsheet Excel generation

### Database Schema
- **RekonData**: Main table storing payment records with columns for school, student ID, payment amounts, dates
- **Key Logic**: Match records by NIS (student ID) + Bulan (month) + Tahun (year) to determine payment dates

### API Security
All API endpoints use `X-API-KEY: 'spp-rekon-2024-secret-key'` header for authentication.

## Key Business Logic

### Payment Matching Algorithm
The core functionality matches bank transactions to student payments using:
- **NIS** (Student ID)
- **Bulan** (Month 1-12)
- **Tahun** (Year)

### Excel Formula Equivalent
The system replicates this Excel formula: `=IFERROR(INDEX(Rekon!$P:$P; MATCH(1; (Rekon!$B:$B=B$7)*(Rekon!$L:$L=D5)*(Rekon!$K:$K=D4); 0));"-")`

### File Processing
- **Bank CSV**: Custom column mapping for various bank formats
- **Legacy Import**: Standard Excel/CSV processing for existing data
- **Export**: Multi-sheet Excel files with payment tracking per student

## Important Notes

- **No npm run dev/php artisan serve**: User has requested not to run these commands automatically
- **Indonesian Language**: All UI text and labels are in Indonesian
- **Date Formatting**: Uses Indonesian date formats and conventions
- **Error Handling**: Comprehensive validation with user-friendly Indonesian error messages