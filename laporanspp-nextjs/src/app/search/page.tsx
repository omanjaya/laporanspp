'use client';

import {
  Download,
  FileSpreadsheet,
  Filter,
  RefreshCw,
  Search,
} from 'lucide-react';
import { useEffect, useState } from 'react';

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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

interface RekonData {
  id: number;
  sekolah: string;
  idSiswa: string;
  namaSiswa: string;
  kelas: string;
  jurusan: string;
  jumTagihan: number;
  tahun: number;
  bulan: number;
  danaMasyarakat: string;
  tglTxFormatted: string;
  noBukti: string;
}

interface Pagination {
  total: number;
  limit: number;
  offset: number;
  hasMore: boolean;
}

export default function SearchPage() {
  const [filters, setFilters] = useState({
    search: '',
    tahun: '',
    bulan: '',
    kelas: '',
  });
  const [results, setResults] = useState<RekonData[]>([]);
  const [pagination, setPagination] = useState<Pagination>({
    total: 0,
    limit: 20,
    offset: 0,
    hasMore: false,
  });
  const [loading, setLoading] = useState(false);

  const years = ['2022', '2023', '2024', '2025', '2026'];
  const months = [
    { value: '1', label: 'Januari' },
    { value: '2', label: 'Februari' },
    { value: '3', label: 'Maret' },
    { value: '4', label: 'April' },
    { value: '5', label: 'Mei' },
    { value: '6', label: 'Juni' },
    { value: '7', label: 'Juli' },
    { value: '8', label: 'Agustus' },
    { value: '9', label: 'September' },
    { value: '10', label: 'Oktober' },
    { value: '11', label: 'November' },
    { value: '12', label: 'Desember' },
  ];

  const fetchData = async (offset = 0) => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      params.set('limit', '20');
      params.set('offset', offset.toString());
      if (filters.search) params.set('search', filters.search);
      if (filters.tahun) params.set('tahun', filters.tahun);
      if (filters.bulan) params.set('bulan', filters.bulan);
      if (filters.kelas) params.set('kelas', filters.kelas);

      const response = await fetch(`/api/rekon?${params.toString()}`);
      const data = await response.json();
      if (data.success) {
        setResults(data.data);
        setPagination(data.pagination);
      }
    } catch (error) {
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  const handleSearch = () => fetchData(0);
  const handleReset = () => {
    setFilters({ search: '', tahun: '', bulan: '', kelas: '' });
    fetchData(0);
  };
  const handleNextPage = () => {
    if (pagination.hasMore) fetchData(pagination.offset + pagination.limit);
  };
  const handlePrevPage = () => {
    if (pagination.offset > 0)
      fetchData(Math.max(0, pagination.offset - pagination.limit));
  };

  const formatCurrency = (amount: number) =>
    new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: 'IDR',
      minimumFractionDigits: 0,
    }).format(amount);

  const formatMonth = (bulan: number) => {
    const names = [
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
    return names[bulan - 1] || bulan;
  };

  const handleExportCSV = () => {
    if (results.length === 0) return;
    const headers = [
      'No',
      'Nama Siswa',
      'No. Tagihan',
      'Kelas',
      'Tahun',
      'Bulan',
      'Tagihan',
      'Tanggal',
    ];
    const rows = results.map((item, idx) => [
      idx + 1,
      item.namaSiswa,
      item.idSiswa,
      `${item.kelas}${item.jurusan}`,
      item.tahun,
      item.bulan,
      item.jumTagihan,
      item.tglTxFormatted,
    ]);
    const csv = [headers, ...rows].map((row) => row.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `rekon_data_${new Date().toISOString().split('T')[0]}.csv`;
    link.click();
  };

  return (
    <SidebarLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Pencarian Data</h1>
        <p className="text-gray-600">Cari dan filter data pembayaran</p>
      </div>

      <Card className="mb-6 border-gray-200">
        <CardHeader className="pb-3">
          <CardTitle className="flex items-center gap-2 text-base text-gray-900">
            <Filter className="h-4 w-4" />
            Filter Pencarian
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div className="space-y-1">
              <Label className="text-gray-700">Nama / No. Tagihan</Label>
              <Input
                placeholder="Cari..."
                value={filters.search}
                onChange={(e) =>
                  setFilters({ ...filters, search: e.target.value })
                }
                onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
                className="border-gray-300"
              />
            </div>
            <div className="space-y-1">
              <Label className="text-gray-700">Tahun</Label>
              <Select
                value={filters.tahun || 'all'}
                onValueChange={(v) =>
                  setFilters({ ...filters, tahun: v === 'all' ? '' : v })
                }
              >
                <SelectTrigger className="border-gray-300">
                  <SelectValue placeholder="Semua" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Semua</SelectItem>
                  {years.map((y) => (
                    <SelectItem key={y} value={y}>
                      {y}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label className="text-gray-700">Bulan</Label>
              <Select
                value={filters.bulan || 'all'}
                onValueChange={(v) =>
                  setFilters({ ...filters, bulan: v === 'all' ? '' : v })
                }
              >
                <SelectTrigger className="border-gray-300">
                  <SelectValue placeholder="Semua" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Semua</SelectItem>
                  {months.map((m) => (
                    <SelectItem key={m.value} value={m.value}>
                      {m.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label className="text-gray-700">Kelas</Label>
              <Select
                value={filters.kelas || 'all'}
                onValueChange={(v) =>
                  setFilters({ ...filters, kelas: v === 'all' ? '' : v })
                }
              >
                <SelectTrigger className="border-gray-300">
                  <SelectValue placeholder="Semua" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Semua</SelectItem>
                  <SelectItem value="X.">Kelas X</SelectItem>
                  <SelectItem value="XI.">Kelas XI</SelectItem>
                  <SelectItem value="XII.">Kelas XII</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
          <div className="flex gap-2 pt-2">
            <Button onClick={handleSearch} disabled={loading}>
              {loading ? (
                <RefreshCw className="mr-2 h-4 w-4 animate-spin" />
              ) : (
                <Search className="mr-2 h-4 w-4" />
              )}
              Cari
            </Button>
            <Button variant="outline" onClick={handleReset}>
              <RefreshCw className="mr-2 h-4 w-4" />
              Reset
            </Button>
            <Button
              variant="outline"
              onClick={handleExportCSV}
              disabled={results.length === 0}
            >
              <Download className="mr-2 h-4 w-4" />
              Export CSV
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card className="border-gray-200">
        <CardHeader className="pb-3">
          <div className="flex items-center justify-between">
            <div>
              <CardTitle className="text-base text-gray-900">
                Hasil Pencarian
              </CardTitle>
              <CardDescription className="text-gray-500">
                {results.length} dari {pagination.total} data
              </CardDescription>
            </div>
            {results.length > 0 && (
              <Badge className="bg-green-100 text-green-800">
                Total:{' '}
                {formatCurrency(
                  results.reduce((sum, item) => sum + item.jumTagihan, 0),
                )}
              </Badge>
            )}
          </div>
        </CardHeader>
        <CardContent>
          {loading ? (
            <div className="py-12 text-center">
              <RefreshCw className="mx-auto mb-2 h-6 w-6 animate-spin text-blue-600" />
              <p className="text-gray-500">Memuat...</p>
            </div>
          ) : results.length > 0 ? (
            <>
              <div className="overflow-x-auto rounded border border-gray-200">
                <Table>
                  <TableHeader>
                    <TableRow className="bg-gray-50">
                      <TableHead className="w-10 text-gray-700">#</TableHead>
                      <TableHead className="text-gray-700">Nama</TableHead>
                      <TableHead className="text-gray-700">
                        No. Tagihan
                      </TableHead>
                      <TableHead className="text-gray-700">Kelas</TableHead>
                      <TableHead className="text-gray-700">Periode</TableHead>
                      <TableHead className="text-right text-gray-700">
                        Tagihan
                      </TableHead>
                      <TableHead className="text-gray-700">Tanggal</TableHead>
                      <TableHead className="text-gray-700">No. Bukti</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {results.map((item, idx) => (
                      <TableRow key={item.id} className="hover:bg-gray-50">
                        <TableCell className="text-gray-500">
                          {pagination.offset + idx + 1}
                        </TableCell>
                        <TableCell className="font-medium text-gray-900">
                          {item.namaSiswa}
                        </TableCell>
                        <TableCell className="text-gray-700">
                          {item.idSiswa}
                        </TableCell>
                        <TableCell>
                          <Badge variant="outline" className="text-gray-700">
                            {item.kelas}
                            {item.jurusan}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-gray-700">
                          {formatMonth(item.bulan)} {item.tahun}
                        </TableCell>
                        <TableCell className="text-right font-medium text-green-600">
                          {formatCurrency(item.jumTagihan)}
                        </TableCell>
                        <TableCell className="text-sm text-gray-500">
                          {item.tglTxFormatted}
                        </TableCell>
                        <TableCell className="text-sm text-gray-500">
                          {item.noBukti || '-'}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
              <div className="mt-4 flex items-center justify-between">
                <p className="text-sm text-gray-500">
                  Halaman {Math.floor(pagination.offset / pagination.limit) + 1}{' '}
                  dari {Math.ceil(pagination.total / pagination.limit)}
                </p>
                <div className="flex gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={handlePrevPage}
                    disabled={pagination.offset === 0}
                  >
                    Sebelumnya
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={handleNextPage}
                    disabled={!pagination.hasMore}
                  >
                    Selanjutnya
                  </Button>
                </div>
              </div>
            </>
          ) : (
            <div className="py-12 text-center">
              <FileSpreadsheet className="mx-auto mb-3 h-10 w-10 text-gray-300" />
              <p className="text-gray-500">Belum ada data</p>
            </div>
          )}
        </CardContent>
      </Card>
    </SidebarLayout>
  );
}
