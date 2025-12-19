import { NextRequest, NextResponse } from 'next/server';

import { prisma } from '@/lib/prisma';

interface PembayaranRecord {
  [key: string]: string | null; // "2023-7": "01/07/2023"
}

interface SiswaData {
  nis: string;
  nama: string;
  alamat: string | null;
  pembayaran: PembayaranRecord;
  totalBayar: number;
  totalTunggak: number;
}

interface LaporanKelasResponse {
  success: boolean;
  kelas: string;
  jurusan: string;
  tahunAjaran: string;
  periode: Array<{ tahun: number; bulan: number; label: string }>;
  data: SiswaData[];
  summary: {
    totalSiswa: number;
    totalTransaksi: number;
    totalDana: number;
    perBulan: Record<string, { bayar: number; tunggak: number }>;
  };
}

// Generate periode for academic year (July Year1 - June Year2)
function generatePeriode(
  tahunAjaran: string,
): Array<{ tahun: number; bulan: number; label: string }> {
  const [tahun1, tahun2] = tahunAjaran.split('/').map(Number);
  const bulanNames = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Ags',
    'Sep',
    'Okt',
    'Nov',
    'Des',
  ];

  const periode: Array<{ tahun: number; bulan: number; label: string }> = [];

  // July - December of first year
  for (let bulan = 7; bulan <= 12; bulan++) {
    periode.push({
      tahun: tahun1,
      bulan,
      label: `${bulanNames[bulan - 1]} ${tahun1}`,
    });
  }

  // January - June of second year
  for (let bulan = 1; bulan <= 6; bulan++) {
    periode.push({
      tahun: tahun2,
      bulan,
      label: `${bulanNames[bulan - 1]} ${tahun2}`,
    });
  }

  return periode;
}

export async function GET(request: NextRequest) {
  try {
    const searchParams = request.nextUrl.searchParams;
    const kelas = searchParams.get('kelas') || 'X.';
    const jurusan = searchParams.get('jurusan') || '';
    const tahunAjaran = searchParams.get('tahunAjaran') || '2024/2025';
    const sekolah = searchParams.get('sekolah') || '';

    const periode = generatePeriode(tahunAjaran);
    const [tahun1, tahun2] = tahunAjaran.split('/').map(Number);

    // Build where clause for filtering payments
    const whereClause: any = {
      kelas: kelas,
      OR: [
        { tahun: tahun1, bulan: { gte: 7 } },
        { tahun: tahun2, bulan: { lte: 6 } },
      ],
    };

    if (jurusan) {
      whereClause.jurusan = jurusan;
    }

    if (sekolah) {
      whereClause.sekolah = sekolah;
    }

    // Get all transactions for the class and period
    const transactions = await prisma.rekonData.findMany({
      where: whereClause,
      orderBy: [{ namaSiswa: 'asc' }, { tahun: 'asc' }, { bulan: 'asc' }],
    });

    // Get unique students (using NIS as key)
    const siswaMap = new Map<string, SiswaData>();

    transactions.forEach((tx) => {
      const key = tx.idSiswa;

      if (!siswaMap.has(key)) {
        siswaMap.set(key, {
          nis: tx.idSiswa,
          nama: tx.namaSiswa,
          alamat: tx.alamat,
          pembayaran: {},
          totalBayar: 0,
          totalTunggak: 0,
        });
      }

      const siswa = siswaMap.get(key)!;
      const periodeKey = `${tx.tahun}-${tx.bulan}`;

      // Store payment date (use formatted date or format it)
      if (tx.stsBayar === 1) {
        siswa.pembayaran[periodeKey] =
          tx.tglTxFormatted || tx.tglTx.toLocaleDateString('id-ID');
        siswa.totalBayar++;
      }
    });

    // Calculate tunggakan for each student
    siswaMap.forEach((siswa) => {
      siswa.totalTunggak = periode.length - siswa.totalBayar;
    });

    // Sort students by name
    const sortedData = Array.from(siswaMap.values()).sort((a, b) =>
      a.nama.localeCompare(b.nama),
    );

    // Calculate summary per month
    const perBulan: Record<string, { bayar: number; tunggak: number }> = {};
    const totalSiswa = sortedData.length;

    periode.forEach((p) => {
      const key = `${p.tahun}-${p.bulan}`;
      let bayar = 0;

      sortedData.forEach((siswa) => {
        if (siswa.pembayaran[key]) {
          bayar++;
        }
      });

      perBulan[key] = {
        bayar,
        tunggak: totalSiswa - bayar,
      };
    });

    // Calculate total dana
    const totalDana = transactions.reduce((sum, tx) => {
      return sum + (parseInt(tx.danaMasyarakat) || 0);
    }, 0);

    const response: LaporanKelasResponse = {
      success: true,
      kelas,
      jurusan: jurusan || 'Semua',
      tahunAjaran,
      periode,
      data: sortedData,
      summary: {
        totalSiswa,
        totalTransaksi: transactions.length,
        totalDana,
        perBulan,
      },
    };

    return NextResponse.json(response);
  } catch (error) {
    console.error('Laporan kelas error:', error);
    return NextResponse.json(
      {
        success: false,
        message:
          error instanceof Error ? error.message : 'Failed to get report data',
      },
      { status: 500 },
    );
  }
}

// Get available filters (kelas, jurusan, tahun)
export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const { action } = body;

    if (action === 'getFilters') {
      // Get unique values for filters
      const [kelasData, jurusanData, tahunData, sekolahData] =
        await Promise.all([
          prisma.rekonData.findMany({
            select: { kelas: true },
            distinct: ['kelas'],
            orderBy: { kelas: 'asc' },
          }),
          prisma.rekonData.findMany({
            select: { jurusan: true },
            distinct: ['jurusan'],
            orderBy: { jurusan: 'asc' },
          }),
          prisma.rekonData.findMany({
            select: { tahun: true },
            distinct: ['tahun'],
            orderBy: { tahun: 'desc' },
          }),
          prisma.rekonData.findMany({
            select: { sekolah: true },
            distinct: ['sekolah'],
            orderBy: { sekolah: 'asc' },
          }),
        ]);

      // Generate tahun ajaran options based on actual data
      // Academic year: July Year1 - June Year2
      // So if we have tahun 2024, it could be part of 2023/2024 (Jan-Jun) or 2024/2025 (Jul-Dec)
      const years = tahunData.map((t) => t.tahun);
      const tahunAjaranSet = new Set<string>();

      years.forEach((year) => {
        // For each year in data, add possible academic years
        tahunAjaranSet.add(`${year - 1}/${year}`); // If data is Jan-Jun
        tahunAjaranSet.add(`${year}/${year + 1}`); // If data is Jul-Dec
      });

      // Convert to array and sort descending
      const tahunAjaranOptions = Array.from(tahunAjaranSet).sort((a, b) => {
        const [yearA] = a.split('/').map(Number);
        const [yearB] = b.split('/').map(Number);
        return yearB - yearA;
      });

      return NextResponse.json({
        success: true,
        filters: {
          kelas: kelasData.map((k) => k.kelas),
          jurusan: jurusanData.map((j) => j.jurusan),
          tahunAjaran: tahunAjaranOptions,
          sekolah: sekolahData.map((s) => s.sekolah),
        },
      });
    }

    return NextResponse.json(
      { success: false, message: 'Unknown action' },
      { status: 400 },
    );
  } catch (error) {
    console.error('Filter error:', error);
    return NextResponse.json(
      { success: false, message: 'Failed to get filters' },
      { status: 500 },
    );
  }
}
