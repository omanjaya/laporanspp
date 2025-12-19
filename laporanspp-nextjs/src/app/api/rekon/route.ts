import { NextRequest, NextResponse } from 'next/server';

import { prisma } from '@/lib/prisma';

export async function GET(request: NextRequest) {
  try {
    const searchParams = request.nextUrl.searchParams;
    const limit = parseInt(searchParams.get('limit') || '50');
    const offset = parseInt(searchParams.get('offset') || '0');
    const sekolah = searchParams.get('sekolah');
    const tahun = searchParams.get('tahun');
    const bulan = searchParams.get('bulan');
    const kelas = searchParams.get('kelas');
    const search = searchParams.get('search');

    // Build where clause
    const where: any = {};

    if (sekolah) {
      where.sekolah = sekolah;
    }

    if (tahun) {
      where.tahun = parseInt(tahun);
    }

    if (bulan) {
      where.bulan = parseInt(bulan);
    }

    if (kelas) {
      where.kelas = { contains: kelas };
    }

    if (search) {
      where.OR = [
        { namaSiswa: { contains: search } },
        { idSiswa: { contains: search } },
      ];
    }

    // Get total count
    const total = await prisma.rekonData.count({ where });

    // Get data with pagination
    const data = await prisma.rekonData.findMany({
      where,
      orderBy: [{ createdAt: 'desc' }, { tahun: 'desc' }, { bulan: 'desc' }],
      take: limit,
      skip: offset,
      select: {
        id: true,
        sekolah: true,
        idSiswa: true,
        namaSiswa: true,
        alamat: true,
        kelas: true,
        jurusan: true,
        jumTagihan: true,
        biayaAdm: true,
        tagihanLain: true,
        ketTagihanLain: true,
        keterangan: true,
        tahun: true,
        bulan: true,
        danaMasyarakat: true,
        tglTx: true,
        tglTxFormatted: true,
        stsBayar: true,
        kdCab: true,
        kdUser: true,
        stsReversal: true,
        noBukti: true,
        createdAt: true,
      },
    });

    return NextResponse.json({
      success: true,
      data,
      pagination: {
        total,
        limit,
        offset,
        hasMore: offset + limit < total,
      },
    });
  } catch (error) {
    console.error('Rekon API error:', error);
    return NextResponse.json(
      {
        success: false,
        message:
          error instanceof Error ? error.message : 'Failed to fetch data',
        data: [],
        pagination: { total: 0, limit: 50, offset: 0, hasMore: false },
      },
      { status: 500 },
    );
  }
}
