import ExcelJS from 'exceljs';
import { NextRequest, NextResponse } from 'next/server';

import { prisma } from '@/lib/prisma';

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
    const format = searchParams.get('format') || 'excel'; // 'excel' or 'pdf'

    const periode = generatePeriode(tahunAjaran);
    const [tahun1, tahun2] = tahunAjaran.split('/').map(Number);

    // Build where clause
    const whereClause: Record<string, unknown> = {
      kelas: kelas,
      OR: [
        { tahun: tahun1, bulan: { gte: 7 } },
        { tahun: tahun2, bulan: { lte: 6 } },
      ],
    };

    if (jurusan) whereClause.jurusan = jurusan;
    if (sekolah) whereClause.sekolah = sekolah;

    // Get transactions
    const transactions = await prisma.rekonData.findMany({
      where: whereClause,
      orderBy: [{ namaSiswa: 'asc' }, { tahun: 'asc' }, { bulan: 'asc' }],
    });

    // Build student data
    const siswaMap = new Map<
      string,
      {
        nis: string;
        nama: string;
        pembayaran: Record<string, string | null>;
        totalBayar: number;
      }
    >();

    transactions.forEach((tx) => {
      const key = tx.idSiswa;

      if (!siswaMap.has(key)) {
        siswaMap.set(key, {
          nis: tx.idSiswa,
          nama: tx.namaSiswa,
          pembayaran: {},
          totalBayar: 0,
        });
      }

      const siswa = siswaMap.get(key)!;
      const periodeKey = `${tx.tahun}-${tx.bulan}`;

      if (tx.stsBayar === 1) {
        siswa.pembayaran[periodeKey] =
          tx.tglTxFormatted || tx.tglTx.toLocaleDateString('id-ID');
        siswa.totalBayar++;
      }
    });

    const sortedData = Array.from(siswaMap.values()).sort((a, b) =>
      a.nama.localeCompare(b.nama),
    );

    if (format === 'pdf') {
      // Return JSON data for client-side PDF generation
      return NextResponse.json({
        success: true,
        kelas,
        jurusan: jurusan || 'Semua',
        tahunAjaran,
        sekolah,
        periode,
        data: sortedData,
        summary: {
          totalSiswa: sortedData.length,
          totalTransaksi: transactions.length,
        },
      });
    }

    // Generate Excel using ExcelJS
    const workbook = new ExcelJS.Workbook();
    workbook.creator = 'Laporan SPP';
    workbook.created = new Date();

    const worksheet = workbook.addWorksheet('Laporan Per Kelas');

    // Title
    worksheet.mergeCells(
      'A1:' + String.fromCharCode(65 + periode.length + 3) + '1',
    );
    worksheet.getCell('A1').value =
      `LAPORAN PEMBAYARAN SPP - ${sekolah || 'Semua Sekolah'}`;
    worksheet.getCell('A1').font = { bold: true, size: 14 };
    worksheet.getCell('A1').alignment = { horizontal: 'center' };

    worksheet.mergeCells(
      'A2:' + String.fromCharCode(65 + periode.length + 3) + '2',
    );
    worksheet.getCell('A2').value =
      `Kelas: ${kelas}${jurusan || ''} | Tahun Ajaran: ${tahunAjaran}`;
    worksheet.getCell('A2').alignment = { horizontal: 'center' };

    // Header row
    const headerRow = [
      'No',
      'NIS',
      'Nama Siswa',
      ...periode.map((p) => p.label),
      'Bayar',
      'Tunggak',
    ];
    worksheet.addRow([]);
    const headers = worksheet.addRow(headerRow);

    headers.eachCell((cell) => {
      cell.font = { bold: true };
      cell.fill = {
        type: 'pattern',
        pattern: 'solid',
        fgColor: { argb: 'FF4472C4' },
      };
      cell.font = { bold: true, color: { argb: 'FFFFFFFF' } };
      cell.alignment = { horizontal: 'center', vertical: 'middle' };
      cell.border = {
        top: { style: 'thin' },
        left: { style: 'thin' },
        bottom: { style: 'thin' },
        right: { style: 'thin' },
      };
    });

    // Data rows
    sortedData.forEach((siswa, idx) => {
      const rowData: (string | number)[] = [idx + 1, siswa.nis, siswa.nama];

      periode.forEach((p) => {
        const key = `${p.tahun}-${p.bulan}`;
        rowData.push(siswa.pembayaran[key] || '-');
      });

      rowData.push(siswa.totalBayar);
      rowData.push(periode.length - siswa.totalBayar);

      const row = worksheet.addRow(rowData);

      row.eachCell((cell, colNumber) => {
        cell.border = {
          top: { style: 'thin' },
          left: { style: 'thin' },
          bottom: { style: 'thin' },
          right: { style: 'thin' },
        };

        // Center align month columns
        if (colNumber > 3 && colNumber <= 3 + periode.length) {
          cell.alignment = { horizontal: 'center' };
          // Green background for paid
          if (cell.value && cell.value !== '-') {
            cell.fill = {
              type: 'pattern',
              pattern: 'solid',
              fgColor: { argb: 'FFC6EFCE' },
            };
          }
        }

        // Center align bayar/tunggak
        if (colNumber > 3 + periode.length) {
          cell.alignment = { horizontal: 'center' };
        }
      });
    });

    // Set column widths
    worksheet.getColumn(1).width = 5;
    worksheet.getColumn(2).width = 12;
    worksheet.getColumn(3).width = 35;
    for (let i = 4; i <= 3 + periode.length; i++) {
      worksheet.getColumn(i).width = 12;
    }
    worksheet.getColumn(4 + periode.length).width = 8;
    worksheet.getColumn(5 + periode.length).width = 8;

    // Summary row
    worksheet.addRow([]);
    const summaryRow = worksheet.addRow([
      '',
      '',
      'TOTAL',
      ...Array(periode.length).fill(''),
      sortedData.reduce((sum, s) => sum + s.totalBayar, 0),
      sortedData.reduce((sum, s) => sum + (periode.length - s.totalBayar), 0),
    ]);
    summaryRow.font = { bold: true };

    // Generate buffer
    const buffer = await workbook.xlsx.writeBuffer();

    // Return Excel file
    return new NextResponse(buffer, {
      status: 200,
      headers: {
        'Content-Type':
          'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'Content-Disposition': `attachment; filename="Laporan_${kelas}_${tahunAjaran.replace('/', '-')}.xlsx"`,
      },
    });
  } catch (error) {
    console.error('Export error:', error);
    return NextResponse.json(
      {
        success: false,
        message: error instanceof Error ? error.message : 'Export failed',
      },
      { status: 500 },
    );
  }
}
