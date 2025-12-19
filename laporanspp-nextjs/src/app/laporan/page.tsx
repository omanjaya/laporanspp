'use client';

import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import {
  ChevronLeft,
  ChevronRight,
  Download,
  FileSpreadsheet,
  FileText,
  Filter,
  Loader2,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

import { SidebarLayout } from '@/components/layout/sidebar-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';

interface Periode {
  tahun: number;
  bulan: number;
  label: string;
}

interface SiswaData {
  nis: string;
  nama: string;
  alamat: string | null;
  pembayaran: Record<string, string | null>;
  totalBayar: number;
  totalTunggak: number;
}

interface LaporanData {
  success: boolean;
  kelas: string;
  jurusan: string;
  tahunAjaran: string;
  periode: Periode[];
  data: SiswaData[];
  summary: {
    totalSiswa: number;
    totalTransaksi: number;
    totalDana: number;
    perBulan: Record<string, { bayar: number; tunggak: number }>;
  };
}

interface Filters {
  kelas: string[];
  jurusan: string[];
  tahunAjaran: string[];
  sekolah: string[];
}

export default function LaporanPage() {
  const [loading, setLoading] = useState(true);
  const [exporting, setExporting] = useState(false);
  const [data, setData] = useState<LaporanData | null>(null);
  const [filters, setFilters] = useState<Filters | null>(null);

  const [selectedKelas, setSelectedKelas] = useState('X.');
  const [selectedJurusan, setSelectedJurusan] = useState('');
  const [selectedTahunAjaran, setSelectedTahunAjaran] = useState('2024/2025');
  const [selectedSekolah, setSelectedSekolah] = useState('');

  const [visibleMonths, setVisibleMonths] = useState(6);
  const [monthOffset, setMonthOffset] = useState(0);

  const fetchFilters = useCallback(async () => {
    try {
      const res = await fetch('/api/laporan-kelas', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'getFilters' }),
      });

      if (res.ok) {
        const result = await res.json();
        if (result.success) {
          setFilters(result.filters);
          if (result.filters.kelas.length > 0)
            setSelectedKelas(result.filters.kelas[0]);
          if (result.filters.tahunAjaran.length > 0)
            setSelectedTahunAjaran(result.filters.tahunAjaran[0]);
          if (result.filters.sekolah.length > 0)
            setSelectedSekolah(result.filters.sekolah[0]);
        }
      }
    } catch (error) {
      // Silent
    }
  }, []);

  const fetchData = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({
        kelas: selectedKelas,
        tahunAjaran: selectedTahunAjaran,
      });
      if (selectedJurusan) params.append('jurusan', selectedJurusan);
      if (selectedSekolah) params.append('sekolah', selectedSekolah);

      const res = await fetch(`/api/laporan-kelas?${params}`);
      if (res.ok) {
        const result = await res.json();
        setData(result);
      }
    } catch (error) {
      // Silent
    } finally {
      setLoading(false);
    }
  }, [selectedKelas, selectedJurusan, selectedTahunAjaran, selectedSekolah]);

  useEffect(() => {
    fetchFilters();
  }, [fetchFilters]);

  useEffect(() => {
    if (selectedKelas && selectedTahunAjaran) fetchData();
  }, [fetchData, selectedKelas, selectedTahunAjaran]);

  const handleExportExcel = async () => {
    if (!data?.data.length) return;
    setExporting(true);

    try {
      const params = new URLSearchParams({
        kelas: selectedKelas,
        tahunAjaran: selectedTahunAjaran,
      });
      if (selectedJurusan) params.append('jurusan', selectedJurusan);
      if (selectedSekolah) params.append('sekolah', selectedSekolah);

      const res = await fetch(`/api/export/excel?${params}`);
      if (res.ok) {
        const blob = await res.blob();
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `Laporan_${selectedKelas}_${selectedTahunAjaran.replace('/', '-')}.xlsx`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
      }
    } catch (error) {
      // Silent
    } finally {
      setExporting(false);
    }
  };

  // Helper untuk extract angkatan dari tahun ajaran
  const getAngkatan = (tahunAjaran: string, kelas: string): number => {
    const [tahun1] = tahunAjaran.split('/').map(Number);
    if (kelas.startsWith('X.')) return tahun1;
    if (kelas.startsWith('XI.')) return tahun1 - 1;
    if (kelas.startsWith('XII.')) return tahun1 - 2;
    return tahun1;
  };

  const handleExportPDF = async () => {
    if (!data?.data.length) return;
    setExporting(true);

    try {
      const doc = new jsPDF({
        orientation: 'landscape',
        unit: 'mm',
        format: 'a4',
      });

      const angkatan = getAngkatan(selectedTahunAjaran, selectedKelas);
      const kelasNumber =
        selectedKelas.replace('.', '') + (selectedJurusan || '');

      // Title - sama seperti format PDF contoh
      doc.setFontSize(12);
      doc.setFont('helvetica', 'bold');
      doc.text('DAFTAR SISWA YANG MEMBAYAR UANG KOMITE', 148, 12, {
        align: 'center',
      });

      // Subtitle
      doc.setFontSize(10);
      doc.setFont('helvetica', 'normal');
      doc.text(`Kelas : ${kelasNumber}`, 14, 20);
      doc.text(`Angkatan : ${angkatan}`, 14, 26);

      // Build header dengan format 2-row (tahun di atas, bulan di bawah)
      const yearRow: string[] = ['', '', 'Nama'];
      const monthRow: string[] = ['No.', 'NIS', ''];

      data.periode.forEach((p) => {
        yearRow.push(String(p.tahun));
        monthRow.push(String(p.bulan));
      });

      // Table body - format tanggal DD/MM
      const tableBody = data.data.map((siswa, idx) => {
        const row: (string | number)[] = [idx + 1, siswa.nis, siswa.nama];
        data.periode.forEach((p) => {
          const key = `${p.tahun}-${p.bulan}`;
          const tgl = siswa.pembayaran[key];
          if (tgl) {
            // Convert DD/MM/YYYY to DD/MM
            const parts = tgl.split('/');
            if (parts.length >= 2) {
              row.push(`${parts[0]}/${parts[1]}`);
            } else {
              row.push(tgl.split(' ')[0]);
            }
          } else {
            row.push('-');
          }
        });
        return row;
      });

      autoTable(doc, {
        head: [yearRow, monthRow],
        body: tableBody,
        startY: 32,
        styles: {
          fontSize: 7,
          cellPadding: 1.5,
          lineWidth: 0.1,
          lineColor: [0, 0, 0],
        },
        headStyles: {
          fillColor: [255, 255, 255],
          textColor: [0, 0, 0],
          fontStyle: 'bold',
          halign: 'center',
          lineWidth: 0.1,
          lineColor: [0, 0, 0],
        },
        bodyStyles: {
          lineWidth: 0.1,
          lineColor: [0, 0, 0],
        },
        columnStyles: {
          0: { cellWidth: 8, halign: 'center' },
          1: { cellWidth: 14 },
          2: { cellWidth: 50 },
        },
        didParseCell: (hookData) => {
          // Center align month columns
          if (hookData.column.index > 2) {
            hookData.cell.styles.halign = 'center';
          }
        },
      });

      doc.save(`Daftar_Siswa_${kelasNumber}_Angkatan_${angkatan}.pdf`);
    } catch (error) {
      console.error('PDF export error:', error);
    } finally {
      setExporting(false);
    }
  };

  const handleBatchExport = async (format: 'excel' | 'pdf') => {
    if (!filters?.kelas.length) return;
    setExporting(true);

    try {
      for (const kelas of filters.kelas) {
        const params = new URLSearchParams({
          kelas,
          tahunAjaran: selectedTahunAjaran,
        });
        if (selectedSekolah) params.append('sekolah', selectedSekolah);

        if (format === 'excel') {
          const res = await fetch(`/api/export/excel?${params}`);
          if (res.ok) {
            const blob = await res.blob();
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `Laporan_${kelas}_${selectedTahunAjaran.replace('/', '-')}.xlsx`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
          }
        } else {
          // Fetch data for PDF
          const res = await fetch(`/api/laporan-kelas?${params}`);
          if (res.ok) {
            const kelasData = await res.json();
            if (kelasData.success && kelasData.data.length > 0) {
              const doc = new jsPDF({
                orientation: 'landscape',
                unit: 'mm',
                format: 'a4',
              });

              const angkatan = getAngkatan(selectedTahunAjaran, kelas);
              const kelasNumber = kelas.replace('.', '');

              // Title - sama seperti format PDF contoh
              doc.setFontSize(12);
              doc.setFont('helvetica', 'bold');
              doc.text('DAFTAR SISWA YANG MEMBAYAR UANG KOMITE', 148, 12, {
                align: 'center',
              });

              // Subtitle
              doc.setFontSize(10);
              doc.setFont('helvetica', 'normal');
              doc.text(`Kelas : ${kelasNumber}`, 14, 20);
              doc.text(`Angkatan : ${angkatan}`, 14, 26);

              // Build header dengan format 2-row
              const yearRow: string[] = ['', '', 'Nama'];
              const monthRow: string[] = ['No.', 'NIS', ''];

              kelasData.periode.forEach((p: Periode) => {
                yearRow.push(String(p.tahun));
                monthRow.push(String(p.bulan));
              });

              // Table body - format tanggal DD/MM
              const tableBody = kelasData.data.map(
                (siswa: SiswaData, idx: number) => {
                  const row: (string | number)[] = [
                    idx + 1,
                    siswa.nis,
                    siswa.nama,
                  ];
                  kelasData.periode.forEach((p: Periode) => {
                    const key = `${p.tahun}-${p.bulan}`;
                    const tgl = siswa.pembayaran[key];
                    if (tgl) {
                      const parts = tgl.split('/');
                      if (parts.length >= 2) {
                        row.push(`${parts[0]}/${parts[1]}`);
                      } else {
                        row.push(tgl.split(' ')[0]);
                      }
                    } else {
                      row.push('-');
                    }
                  });
                  return row;
                },
              );

              autoTable(doc, {
                head: [yearRow, monthRow],
                body: tableBody,
                startY: 32,
                styles: {
                  fontSize: 7,
                  cellPadding: 1.5,
                  lineWidth: 0.1,
                  lineColor: [0, 0, 0],
                },
                headStyles: {
                  fillColor: [255, 255, 255],
                  textColor: [0, 0, 0],
                  fontStyle: 'bold',
                  halign: 'center',
                  lineWidth: 0.1,
                  lineColor: [0, 0, 0],
                },
                bodyStyles: {
                  lineWidth: 0.1,
                  lineColor: [0, 0, 0],
                },
                columnStyles: {
                  0: { cellWidth: 8, halign: 'center' },
                  1: { cellWidth: 14 },
                  2: { cellWidth: 50 },
                },
                didParseCell: (hookData) => {
                  if (hookData.column.index > 2) {
                    hookData.cell.styles.halign = 'center';
                  }
                },
              });

              doc.save(`Daftar_Siswa_${kelasNumber}_Angkatan_${angkatan}.pdf`);
            }
          }
        }

        // Small delay between downloads
        await new Promise((resolve) => setTimeout(resolve, 500));
      }
    } catch (error) {
      console.error('Batch export error:', error);
    } finally {
      setExporting(false);
    }
  };

  const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: 'IDR',
      minimumFractionDigits: 0,
    }).format(value);
  };

  const visiblePeriode = data?.periode?.slice(
    monthOffset,
    monthOffset + visibleMonths,
  );
  const canScrollLeft = monthOffset > 0;
  const canScrollRight =
    data?.periode && monthOffset + visibleMonths < data.periode.length;

  return (
    <SidebarLayout>
      {/* Header */}
      <div className="mb-6 flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">
            Laporan Per Kelas
          </h1>
          <p className="text-gray-600">
            Rekap pembayaran SPP per siswa per bulan
          </p>
        </div>

        <div className="flex gap-2">
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button
                variant="outline"
                disabled={exporting || !data?.data.length}
              >
                {exporting ? (
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                ) : (
                  <Download className="mr-2 h-4 w-4" />
                )}
                Export
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem onClick={handleExportExcel}>
                <FileSpreadsheet className="mr-2 h-4 w-4" />
                Export Excel (Kelas Ini)
              </DropdownMenuItem>
              <DropdownMenuItem onClick={handleExportPDF}>
                <FileText className="mr-2 h-4 w-4" />
                Export PDF (Kelas Ini)
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => handleBatchExport('excel')}>
                <FileSpreadsheet className="mr-2 h-4 w-4" />
                Batch Export Excel (Semua Kelas)
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => handleBatchExport('pdf')}>
                <FileText className="mr-2 h-4 w-4" />
                Batch Export PDF (Semua Kelas)
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>

      {/* Filters */}
      <Card className="mb-6 border-gray-200">
        <CardHeader className="pb-3">
          <CardTitle className="flex items-center gap-2 text-base text-gray-900">
            <Filter className="h-4 w-4" />
            Filter
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div className="space-y-1">
              <label className="text-sm font-medium text-gray-700">
                Sekolah
              </label>
              <Select
                value={selectedSekolah}
                onValueChange={setSelectedSekolah}
              >
                <SelectTrigger className="border-gray-300">
                  <SelectValue placeholder="Pilih" />
                </SelectTrigger>
                <SelectContent>
                  {filters?.sekolah.map((s) => (
                    <SelectItem key={s} value={s}>
                      {s}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-1">
              <label className="text-sm font-medium text-gray-700">Kelas</label>
              <Select value={selectedKelas} onValueChange={setSelectedKelas}>
                <SelectTrigger className="border-gray-300">
                  <SelectValue placeholder="Pilih" />
                </SelectTrigger>
                <SelectContent>
                  {filters?.kelas.map((k) => (
                    <SelectItem key={k} value={k}>
                      {k}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-1">
              <label className="text-sm font-medium text-gray-700">
                Jurusan
              </label>
              <Select
                value={selectedJurusan || 'all'}
                onValueChange={(v) => setSelectedJurusan(v === 'all' ? '' : v)}
              >
                <SelectTrigger className="border-gray-300">
                  <SelectValue placeholder="Semua" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Semua</SelectItem>
                  {filters?.jurusan.map((j) => (
                    <SelectItem key={j} value={j}>
                      {j}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-1">
              <label className="text-sm font-medium text-gray-700">
                Tahun Ajaran
              </label>
              <Select
                value={selectedTahunAjaran}
                onValueChange={setSelectedTahunAjaran}
              >
                <SelectTrigger className="border-gray-300">
                  <SelectValue placeholder="Pilih" />
                </SelectTrigger>
                <SelectContent>
                  {filters?.tahunAjaran.map((t) => (
                    <SelectItem key={t} value={t}>
                      {t}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Stats */}
      <div className="mb-6 grid gap-4 sm:grid-cols-3">
        <Card className="border-gray-200">
          <CardContent className="p-4">
            <p className="text-sm text-gray-600">Total Siswa</p>
            <p className="text-2xl font-bold text-gray-900">
              {data?.summary?.totalSiswa || 0}
            </p>
          </CardContent>
        </Card>
        <Card className="border-gray-200">
          <CardContent className="p-4">
            <p className="text-sm text-gray-600">Total Transaksi</p>
            <p className="text-2xl font-bold text-gray-900">
              {data?.summary?.totalTransaksi || 0}
            </p>
          </CardContent>
        </Card>
        <Card className="border-gray-200">
          <CardContent className="p-4">
            <p className="text-sm text-gray-600">Total Dana</p>
            <p className="text-2xl font-bold text-green-600">
              {formatCurrency(data?.summary?.totalDana || 0)}
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Table */}
      <Card className="border-gray-200">
        <CardHeader className="pb-3">
          <div className="flex items-center justify-between">
            <div>
              <CardTitle className="text-base text-gray-900">
                Data Pembayaran
              </CardTitle>
              <CardDescription className="text-gray-500">
                {data?.kelas} | {data?.tahunAjaran}
              </CardDescription>
            </div>
            {data?.periode && data.periode.length > visibleMonths && (
              <div className="flex items-center gap-2">
                <Button
                  variant="outline"
                  size="icon"
                  onClick={() =>
                    setMonthOffset((prev) => Math.max(0, prev - 1))
                  }
                  disabled={!canScrollLeft}
                >
                  <ChevronLeft className="h-4 w-4" />
                </Button>
                <span className="text-sm text-gray-500">
                  {monthOffset + 1}-
                  {Math.min(monthOffset + visibleMonths, data.periode.length)}/
                  {data.periode.length}
                </span>
                <Button
                  variant="outline"
                  size="icon"
                  onClick={() => setMonthOffset((prev) => prev + 1)}
                  disabled={!canScrollRight}
                >
                  <ChevronRight className="h-4 w-4" />
                </Button>
              </div>
            )}
          </div>
        </CardHeader>
        <CardContent>
          {loading ? (
            <div className="flex items-center justify-center py-12">
              <Loader2 className="h-6 w-6 animate-spin text-gray-400" />
              <span className="ml-2 text-gray-500">Memuat...</span>
            </div>
          ) : data?.data && data.data.length > 0 ? (
            <div className="overflow-x-auto rounded border border-gray-200">
              <Table>
                <TableHeader>
                  <TableRow className="bg-gray-50">
                    <TableHead className="w-10 text-center text-gray-700">
                      No
                    </TableHead>
                    <TableHead className="w-24 text-gray-700">NIS</TableHead>
                    <TableHead className="min-w-[180px] text-gray-700">
                      Nama
                    </TableHead>
                    {visiblePeriode?.map((p) => (
                      <TableHead
                        key={`${p.tahun}-${p.bulan}`}
                        className="min-w-[80px] text-center text-xs text-gray-700"
                      >
                        {p.label}
                      </TableHead>
                    ))}
                    <TableHead className="text-center text-gray-700">
                      Bayar
                    </TableHead>
                    <TableHead className="text-center text-gray-700">
                      Tunggak
                    </TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {data.data.map((siswa, idx) => (
                    <TableRow key={siswa.nis} className="hover:bg-gray-50">
                      <TableCell className="text-center text-gray-600">
                        {idx + 1}
                      </TableCell>
                      <TableCell className="font-mono text-sm text-gray-900">
                        {siswa.nis}
                      </TableCell>
                      <TableCell className="text-gray-900">
                        <span
                          className="block max-w-[180px] truncate"
                          title={siswa.nama}
                        >
                          {siswa.nama}
                        </span>
                      </TableCell>
                      {visiblePeriode?.map((p) => {
                        const key = `${p.tahun}-${p.bulan}`;
                        const tgl = siswa.pembayaran[key];
                        return (
                          <TableCell key={key} className="text-center">
                            {tgl ? (
                              <Badge className="bg-green-100 text-xs text-green-800">
                                {tgl.split(' ')[0]}
                              </Badge>
                            ) : (
                              <span className="text-gray-400">-</span>
                            )}
                          </TableCell>
                        );
                      })}
                      <TableCell className="text-center">
                        <Badge className="bg-green-100 text-green-800">
                          {siswa.totalBayar}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-center">
                        {siswa.totalTunggak > 0 ? (
                          <Badge variant="destructive">
                            {siswa.totalTunggak}
                          </Badge>
                        ) : (
                          <Badge className="bg-green-100 text-green-800">
                            0
                          </Badge>
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          ) : (
            <div className="py-12 text-center">
              <p className="text-gray-500">Tidak ada data.</p>
            </div>
          )}
        </CardContent>
      </Card>
    </SidebarLayout>
  );
}
