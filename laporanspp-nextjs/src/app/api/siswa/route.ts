import { NextRequest, NextResponse } from 'next/server';

import { prisma } from '@/lib/prisma';

interface SiswaWithStats {
  nis: string;
  nama: string;
  alamat: string | null;
  kelas: string;
  jurusan: string;
  sekolah: string;
  totalTransaksi: number;
  totalDana: number;
  lastPayment: string | null;
}

// GET - List all unique students with their stats
export async function GET(request: NextRequest) {
  try {
    const searchParams = request.nextUrl.searchParams;
    const kelas = searchParams.get('kelas') || '';
    const jurusan = searchParams.get('jurusan') || '';
    const sekolah = searchParams.get('sekolah') || '';
    const search = searchParams.get('search') || '';
    const page = parseInt(searchParams.get('page') || '1');
    const limit = parseInt(searchParams.get('limit') || '50');
    const offset = (page - 1) * limit;

    // Build where clause
    const whereClause: any = {};

    if (kelas) whereClause.kelas = kelas;
    if (jurusan) whereClause.jurusan = jurusan;
    if (sekolah) whereClause.sekolah = sekolah;
    if (search) {
      whereClause.OR = [
        { namaSiswa: { contains: search } },
        { idSiswa: { contains: search } },
      ];
    }

    // Get unique students using group by
    const siswaData = await prisma.rekonData.groupBy({
      by: ['idSiswa', 'namaSiswa', 'alamat', 'kelas', 'jurusan', 'sekolah'],
      where: whereClause,
      _count: {
        id: true,
      },
      _sum: {
        jumTagihan: true,
      },
      orderBy: {
        namaSiswa: 'asc',
      },
    });

    // Get last payment date for each student
    const siswaWithStats: SiswaWithStats[] = await Promise.all(
      siswaData.slice(offset, offset + limit).map(async (s) => {
        const lastTx = await prisma.rekonData.findFirst({
          where: { idSiswa: s.idSiswa },
          orderBy: { tglTx: 'desc' },
          select: { tglTxFormatted: true },
        });

        return {
          nis: s.idSiswa,
          nama: s.namaSiswa,
          alamat: s.alamat,
          kelas: s.kelas,
          jurusan: s.jurusan,
          sekolah: s.sekolah,
          totalTransaksi: s._count.id,
          totalDana: s._sum.jumTagihan || 0,
          lastPayment: lastTx?.tglTxFormatted || null,
        };
      }),
    );

    // Get total count for pagination
    const totalCount = siswaData.length;

    return NextResponse.json({
      success: true,
      data: siswaWithStats,
      pagination: {
        page,
        limit,
        total: totalCount,
        totalPages: Math.ceil(totalCount / limit),
      },
    });
  } catch (error) {
    console.error('Siswa API error:', error);
    return NextResponse.json(
      {
        success: false,
        message:
          error instanceof Error ? error.message : 'Failed to fetch students',
      },
      { status: 500 },
    );
  }
}

// POST - Get filters or specific student detail
export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const { action, nis } = body;

    if (action === 'getFilters') {
      // Get unique values for filters
      const [kelasData, jurusanData, sekolahData] = await Promise.all([
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
          select: { sekolah: true },
          distinct: ['sekolah'],
          orderBy: { sekolah: 'asc' },
        }),
      ]);

      return NextResponse.json({
        success: true,
        filters: {
          kelas: kelasData.map((k) => k.kelas),
          jurusan: jurusanData.map((j) => j.jurusan),
          sekolah: sekolahData.map((s) => s.sekolah),
        },
      });
    }

    if (action === 'getDetail' && nis) {
      // Get student detail with all transactions
      const transactions = await prisma.rekonData.findMany({
        where: { idSiswa: nis },
        orderBy: [{ tahun: 'desc' }, { bulan: 'desc' }],
      });

      if (transactions.length === 0) {
        return NextResponse.json(
          { success: false, message: 'Student not found' },
          { status: 404 },
        );
      }

      const firstTx = transactions[0];

      return NextResponse.json({
        success: true,
        siswa: {
          nis: firstTx.idSiswa,
          nama: firstTx.namaSiswa,
          alamat: firstTx.alamat,
          kelas: firstTx.kelas,
          jurusan: firstTx.jurusan,
          sekolah: firstTx.sekolah,
        },
        transactions: transactions.map((tx) => ({
          id: tx.id,
          noBukti: tx.noBukti,
          tahun: tx.tahun,
          bulan: tx.bulan,
          jumTagihan: tx.jumTagihan,
          danaMasyarakat: tx.danaMasyarakat,
          tglTx: tx.tglTxFormatted,
          kdCab: tx.kdCab,
          kdUser: tx.kdUser,
          stsBayar: tx.stsBayar,
        })),
        summary: {
          totalTransaksi: transactions.length,
          totalDana: transactions.reduce(
            (sum, tx) => sum + (parseInt(tx.danaMasyarakat) || 0),
            0,
          ),
        },
      });
    }

    return NextResponse.json(
      { success: false, message: 'Unknown action' },
      { status: 400 },
    );
  } catch (error) {
    console.error('Siswa POST error:', error);
    return NextResponse.json(
      { success: false, message: 'Failed to process request' },
      { status: 500 },
    );
  }
}
