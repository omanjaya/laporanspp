import ExcelJS from 'exceljs';
import { NextRequest, NextResponse } from 'next/server';

import { prisma } from '@/lib/prisma';

// Column mapping from Excel to database
const COLUMN_MAPPING: Record<string, string> = {
  Instansi: 'sekolah',
  'No. Tagihan': 'idSiswa',
  Nama: 'namaSiswa',
  Tagihan: 'jumTagihan',
  'Biaya Adm.': 'biayaAdm',
  'Tagihan Lain': 'tagihanLain',
  'Ket. Tagihan Lain': 'ketTagihanLain',
  Alamat: 'alamat',
  Kelas: 'kelas',
  Jurusan: 'jurusan',
  Tahun: 'tahun',
  Bulan: 'bulan',
  'Dana Masyarakat': 'danaMasyarakat',
  Keterangan: 'keterangan',
  'Tanggal Transaksi': 'tglTx',
  'Status Bayar': 'stsBayar',
  'Kode Cabang': 'kdCab',
  User: 'kdUser',
  'Status Reversal': 'stsReversal',
  'No. Bukti': 'noBukti',
};

interface ImportResult {
  success: boolean;
  message: string;
  stats: {
    total: number;
    inserted: number;
    updated: number;
    skipped: number;
    errors: number;
  };
  errors?: Array<{ row: number; message: string }>;
}

function parseDate(dateStr: string): Date {
  if (!dateStr || dateStr === '-') {
    return new Date();
  }

  const parts = dateStr.split(' ');
  const datePart = parts[0];
  const timePart = parts[1] || '00:00:00';

  const [day, month, year] = datePart.split('/');
  const [hour, minute, second] = timePart.split(':');

  return new Date(
    parseInt(year),
    parseInt(month) - 1,
    parseInt(day),
    parseInt(hour) || 0,
    parseInt(minute) || 0,
    parseInt(second) || 0,
  );
}

function parseNumber(value: unknown): number {
  if (value === null || value === undefined || value === '-' || value === '') {
    return 0;
  }
  const num = parseFloat(String(value).replace(/[^\d.-]/g, ''));
  return isNaN(num) ? 0 : Math.round(num);
}

function parseString(value: unknown): string {
  if (value === null || value === undefined || value === '-') {
    return '';
  }
  return String(value).trim();
}

export async function POST(request: NextRequest) {
  try {
    const formData = await request.formData();
    const file = formData.get('file') as File;
    const mode = (formData.get('mode') as string) || 'skip';

    if (!file) {
      return NextResponse.json(
        { success: false, message: 'No file uploaded' },
        { status: 400 },
      );
    }

    // Validate file type
    if (!file.name.endsWith('.xlsx') && !file.name.endsWith('.xls')) {
      return NextResponse.json(
        {
          success: false,
          message:
            'Invalid file type. Please upload an Excel file (.xlsx or .xls)',
        },
        { status: 400 },
      );
    }

    // Read file using ExcelJS
    const buffer = await file.arrayBuffer();
    const workbook = new ExcelJS.Workbook();
    await workbook.xlsx.load(buffer);

    // Get first sheet
    const worksheet = workbook.worksheets[0];
    if (!worksheet) {
      return NextResponse.json(
        { success: false, message: 'No worksheet found in file' },
        { status: 400 },
      );
    }

    // Get headers from first row
    const headerRow = worksheet.getRow(1);
    const headers: string[] = [];
    const columnIndexMap: Record<string, number> = {};

    headerRow.eachCell((cell, colNumber) => {
      const header = String(cell.value || '').trim();
      headers.push(header);
      if (COLUMN_MAPPING[header]) {
        columnIndexMap[COLUMN_MAPPING[header]] = colNumber;
      }
    });

    // Validate required columns
    const requiredColumns = [
      'sekolah',
      'idSiswa',
      'namaSiswa',
      'tahun',
      'bulan',
    ];
    const missingColumns = requiredColumns.filter(
      (col) => columnIndexMap[col] === undefined,
    );

    if (missingColumns.length > 0) {
      return NextResponse.json(
        {
          success: false,
          message: `Missing required columns: ${missingColumns.join(', ')}. Found: ${headers.join(', ')}`,
        },
        { status: 400 },
      );
    }

    // Count data rows
    const rowCount = worksheet.rowCount;

    const result: ImportResult = {
      success: true,
      message: '',
      stats: {
        total: rowCount - 1,
        inserted: 0,
        updated: 0,
        skipped: 0,
        errors: 0,
      },
      errors: [],
    };

    // Process rows (skip header)
    for (let rowNumber = 2; rowNumber <= rowCount; rowNumber++) {
      const row = worksheet.getRow(rowNumber);

      try {
        // Get cell values
        const getValue = (field: string) => {
          const colNum = columnIndexMap[field];
          if (colNum === undefined) return null;
          const cell = row.getCell(colNum);
          return cell.value;
        };

        const sekolah = parseString(getValue('sekolah'));
        const idSiswa = parseString(getValue('idSiswa'));
        const tahun = parseNumber(getValue('tahun'));
        const bulan = parseNumber(getValue('bulan'));

        // Skip if missing key fields
        if (!sekolah || !idSiswa || !tahun || !bulan) {
          result.stats.skipped++;
          continue;
        }

        // Parse date
        const tglTxRaw = getValue('tglTx');
        let tglTx: Date;
        let tglTxFormatted: string;

        if (tglTxRaw instanceof Date) {
          tglTx = tglTxRaw;
          tglTxFormatted = tglTx.toLocaleString('id-ID');
        } else if (typeof tglTxRaw === 'string') {
          tglTx = parseDate(tglTxRaw);
          tglTxFormatted = tglTxRaw;
        } else {
          tglTx = new Date();
          tglTxFormatted = tglTx.toLocaleString('id-ID');
        }

        const data = {
          sekolah,
          idSiswa,
          namaSiswa: parseString(getValue('namaSiswa')),
          alamat: parseString(getValue('alamat')) || null,
          kelas: parseString(getValue('kelas')),
          jurusan: parseString(getValue('jurusan')),
          jumTagihan: parseNumber(getValue('jumTagihan')),
          biayaAdm: parseNumber(getValue('biayaAdm')),
          tagihanLain: parseNumber(getValue('tagihanLain')),
          ketTagihanLain: parseString(getValue('ketTagihanLain')) || null,
          keterangan: parseString(getValue('keterangan')) || null,
          tahun,
          bulan,
          danaMasyarakat: parseString(getValue('danaMasyarakat')),
          tglTx,
          tglTxFormatted,
          stsBayar: parseNumber(getValue('stsBayar')) || 1,
          kdCab: parseString(getValue('kdCab')),
          kdUser: parseString(getValue('kdUser')),
          stsReversal: parseNumber(getValue('stsReversal')),
          noBukti: parseString(getValue('noBukti')),
        };

        // Check for existing record
        const existing = await prisma.rekonData.findFirst({
          where: {
            idSiswa: data.idSiswa,
            tahun: data.tahun,
            bulan: data.bulan,
            noBukti: data.noBukti,
          },
        });

        if (existing) {
          if (mode === 'update') {
            await prisma.rekonData.update({ where: { id: existing.id }, data });
            result.stats.updated++;
          } else {
            result.stats.skipped++;
          }
        } else {
          await prisma.rekonData.create({ data });
          result.stats.inserted++;
        }
      } catch (error) {
        result.stats.errors++;
        result.errors?.push({
          row: rowNumber,
          message: error instanceof Error ? error.message : 'Unknown error',
        });
      }
    }

    result.message = `Import completed. Inserted: ${result.stats.inserted}, Updated: ${result.stats.updated}, Skipped: ${result.stats.skipped}, Errors: ${result.stats.errors}`;

    return NextResponse.json(result);
  } catch (error) {
    console.error('Import error:', error);
    return NextResponse.json(
      {
        success: false,
        message:
          error instanceof Error ? error.message : 'Failed to import file',
        stats: { total: 0, inserted: 0, updated: 0, skipped: 0, errors: 1 },
      },
      { status: 500 },
    );
  }
}

export async function GET() {
  return NextResponse.json({
    success: true,
    message: 'Import API ready',
    template: {
      requiredColumns: [
        'Instansi',
        'No. Tagihan',
        'Nama',
        'Tagihan',
        'Tahun',
        'Bulan',
        'Dana Masyarakat',
        'Tanggal Transaksi',
      ],
      optionalColumns: [
        'Biaya Adm.',
        'Tagihan Lain',
        'Ket. Tagihan Lain',
        'Alamat',
        'Kelas',
        'Jurusan',
        'Keterangan',
        'Status Bayar',
        'Kode Cabang',
        'User',
        'Status Reversal',
        'No. Bukti',
      ],
    },
  });
}
