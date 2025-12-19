# Laporan SPP - Sistem Rekonsiliasi Pembayaran

Sistem untuk mengelola dan merekonsiliasi pembayaran SPP (Sumbangan Pembinaan Pendidikan) sekolah di Indonesia.

## 🚀 Fitur Utama

- **📥 Import Data Excel** - Upload dan import file Excel dari sistem bank
- **🔍 Pencarian Data** - Cari data pembayaran berdasarkan nama, kelas, periode
- **📊 Dashboard Analytics** - Visualisasi statistik pembayaran
- **📤 Export CSV** - Export hasil pencarian ke file CSV

## 🛠️ Teknologi

- **Framework**: Next.js 15 (App Router + Turbopack)
- **Database**: SQLite + Prisma ORM
- **UI**: shadcn/ui + Tailwind CSS 4
- **Language**: TypeScript

## 📦 Instalasi

```bash
# Clone repository
git clone <repository-url>
cd laporanspp-nextjs

# Install dependencies
npm install

# Generate Prisma client
npx prisma generate

# Run migrations
npx prisma migrate dev

# Start development server
npm run dev
```

## 🔑 Login Demo

- **Email**: <admin@spp.demo>
- **Password**: demo123

## 📁 Struktur Project

```
laporanspp-nextjs/
├── src/
│   ├── app/
│   │   ├── page.tsx              # Halaman login
│   │   ├── dashboard/            # Dashboard utama
│   │   ├── search/               # Halaman pencarian
│   │   └── api/                  # API routes
│   │       ├── auth/             # Authentication
│   │       ├── dashboard/        # Dashboard data
│   │       ├── rekon/            # Rekonsiliasi data
│   │       └── import/           # Import Excel
│   └── components/
│       ├── ui/                   # shadcn/ui components
│       └── import-excel.tsx      # Komponen import
├── prisma/
│   └── schema.prisma             # Database schema
```

## 📊 Format File Excel

File Excel yang diimport harus memiliki kolom berikut:

| Kolom | Deskripsi |
|-------|-----------|
| Instansi | Nama sekolah (contoh: SMAN_1_DENPASAR) |
| No. Tagihan | Nomor ID siswa |
| Nama | Nama lengkap siswa |
| Tagihan | Jumlah tagihan |
| Tahun | Tahun pembayaran (contoh: 2024) |
| Bulan | Bulan pembayaran (1-12) |
| Tanggal Transaksi | Tanggal transaksi (DD/MM/YYYY HH:mm:ss) |

## 🔧 Environment Variables

Buat file `.env` dengan konfigurasi:

```env
DATABASE_URL="file:./dev.db"
```

## 📝 License

MIT License
