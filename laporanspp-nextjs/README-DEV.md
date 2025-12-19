# SPP System Development Scripts

Scripts untuk menjalankan development environment SPP System dengan mudah.

## Cara Penggunaan

### 1. Menjalankan Frontend Only
```bash
./start-dev.sh
```
- Membersihkan port 3000
- Menjalankan Next.js development server di port 3000
- Frontend: http://localhost:3000

### 2. Menjalankan Backend Only
```bash
./start-laravel.sh
```
- Membersihkan port 8000
- Menjalankan Laravel API server di port 8000
- Backend API: http://localhost:8000

### 3. Menjalankan Keduanya (Recommended)
```bash
./start-all.sh
```
- Membersihkan port 3000 dan 8000
- Menjalankan Laravel backend di port 8000
- Menjalankan Next.js frontend di port 3000
- Frontend: http://localhost:3000
- Backend API: http://localhost:8000

## Fitur

✅ **Automatic Port Cleaning** - Script otomatis mematikan proses yang menggunakan port yang diperlukan
✅ **Error Handling** - Menangani kasus ketika port tidak digunakan
✅ **Clear Output** - Menampilkan informasi yang jelas tentang status server
✅ **Database Configuration** - Otomatis mengatur DATABASE_URL untuk Next.js

## Struktur Aplikasi

- **Frontend**: Next.js 15 dengan React 19 dan TypeScript
- **Backend**: Laravel 12.x dengan MySQL/PostgreSQL
- **Database**: Prisma ORM dengan SQLite untuk development
- **UI Components**: Shadcn/UI dengan premium shadcnblocks

## Login Demo

Email: `admin@spp.demo`
Password: `demo123`

## Troubleshooting

Jika ada masalah dengan port yang tetap terkunci:
```bash
# Kill semua port secara manual
for port in 3000 8000; do
    kill -9 $(lsof -ti:$port) 2>/dev/null
done
```

Jika database tidak terkoneksi:
```bash
# Generate ulang Prisma client
DATABASE_URL="file:./dev.db" npx prisma generate
```