import { NextResponse } from 'next/server';

import { prisma } from '@/lib/prisma';

export async function GET() {
  try {
    // Get total transactions
    const totalTransactions = await prisma.rekonData.count();

    // Get total dana (sum of jumTagihan)
    const totalDana = await prisma.rekonData.aggregate({
      _sum: {
        jumTagihan: true,
      },
    });

    // Get unique students count
    const uniqueStudents = await prisma.rekonData.groupBy({
      by: ['idSiswa'],
    });

    // Get unique schools count
    const uniqueSchools = await prisma.rekonData.groupBy({
      by: ['sekolah'],
    });

    // Get monthly data
    const monthlyData = await prisma.rekonData.groupBy({
      by: ['tahun', 'bulan'],
      _count: {
        id: true,
      },
      _sum: {
        jumTagihan: true,
      },
      orderBy: [{ tahun: 'desc' }, { bulan: 'desc' }],
      take: 12,
    });

    // Get school statistics
    const schoolData = await prisma.rekonData.groupBy({
      by: ['sekolah'],
      _count: {
        id: true,
      },
      _sum: {
        jumTagihan: true,
      },
    });

    // Get unique students per school
    const studentsPerSchool: Record<string, number> = {};
    for (const school of schoolData) {
      const students = await prisma.rekonData.groupBy({
        by: ['idSiswa'],
        where: {
          sekolah: school.sekolah,
        },
      });
      studentsPerSchool[school.sekolah] = students.length;
    }

    return NextResponse.json({
      success: true,
      summary: {
        total_transactions: totalTransactions,
        total_dana: totalDana._sum.jumTagihan || 0,
        total_siswa: uniqueStudents.length,
        total_schools: uniqueSchools.length,
      },
      monthly_data: monthlyData.map((item) => ({
        tahun: item.tahun,
        bulan: item.bulan,
        total: item._count.id,
        dana: item._sum.jumTagihan || 0,
      })),
      school_data: schoolData.map((item) => ({
        sekolah: item.sekolah,
        total: item._count.id,
        dana: item._sum.jumTagihan || 0,
        siswa: studentsPerSchool[item.sekolah] || 0,
      })),
    });
  } catch (error) {
    console.error('Dashboard API error:', error);
    return NextResponse.json(
      {
        success: false,
        message:
          error instanceof Error
            ? error.message
            : 'Failed to fetch dashboard data',
        summary: {
          total_transactions: 0,
          total_dana: 0,
          total_siswa: 0,
          total_schools: 0,
        },
        monthly_data: [],
        school_data: [],
      },
      { status: 500 },
    );
  }
}
