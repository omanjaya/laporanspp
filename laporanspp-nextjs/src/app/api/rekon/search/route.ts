import { NextRequest, NextResponse } from 'next/server';

import { prisma } from '@/lib/prisma';

export async function POST(request: NextRequest) {
  try {
    const { sekolah, tahun, bulan } = await request.json();

    // Validasi input
    if (!sekolah || !tahun || !bulan) {
      return NextResponse.json(
        { error: 'Missing required parameters: sekolah, tahun, bulan' },
        { status: 400 },
      );
    }

    // Cari data rekon dengan multi-kriteria (replikasi Excel formula)
    const rekonData = await prisma.rekonData.findFirst({
      where: {
        sekolah: sekolah,
        tahun: parseInt(tahun),
        bulan: parseInt(bulan),
        stsBayar: 1, // status aktif
        stsReversal: 0, // bukan reversal
      },
      orderBy: {
        createdAt: 'desc',
      },
    });

    if (!rekonData) {
      return NextResponse.json(
        { success: false, message: 'Data tidak ditemukan', dana: '-' },
        { status: 404 },
      );
    }

    // Return data yang sesuai dengan Excel INDEX logic
    return NextResponse.json({
      success: true,
      data: {
        sekolah: rekonData.sekolah,
        idSiswa: rekonData.idSiswa,
        namaSiswa: rekonData.namaSiswa,
        kelas: rekonData.kelas,
        jurusan: rekonData.jurusan,
        jumTagihan: rekonData.jumTagihan,
        danaMasyarakat: rekonData.danaMasyarakat,
        noBukti: rekonData.noBukti,
        tglTx: rekonData.tglTxFormatted,
        keterangan: rekonData.keterangan,
      },
      dana: rekonData.danaMasyarakat, // Return dana for Excel INDEX logic
    });
  } catch (error) {
    console.error('Search error:', error);
    return NextResponse.json(
      { error: 'Internal server error' },
      { status: 500 },
    );
  }
}

export async function GET() {
  try {
    // Return all rekon data for dashboard/list view
    const rekonData = await prisma.rekonData.findMany({
      orderBy: [{ tahun: 'desc' }, { bulan: 'desc' }, { createdAt: 'desc' }],
      take: 100, // limit untuk performance
    });

    return NextResponse.json({
      success: true,
      data: rekonData,
      total: rekonData.length,
    });
  } catch (error) {
    console.error('Get rekon data error:', error);
    return NextResponse.json(
      { error: 'Internal server error' },
      { status: 500 },
    );
  }
}
