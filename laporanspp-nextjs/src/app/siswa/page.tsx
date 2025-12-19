'use client';

import {
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
  Eye,
  Filter,
  Loader2,
  Search,
  Users,
  X,
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
import { Input } from '@/components/ui/input';
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

interface Siswa {
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

interface Transaction {
  id: number;
  noBukti: string;
  tahun: number;
  bulan: number;
  jumTagihan: number;
  danaMasyarakat: string;
  tglTx: string;
  kdCab: string;
  kdUser: string;
  stsBayar: number;
}

interface SiswaDetail {
  siswa: Siswa;
  transactions: Transaction[];
  summary: { totalTransaksi: number; totalDana: number };
}

interface Filters {
  kelas: string[];
  jurusan: string[];
  sekolah: string[];
}

interface Pagination {
  page: number;
  limit: number;
  total: number;
  totalPages: number;
}

export default function SiswaPage() {
  const [loading, setLoading] = useState(true);
  const [data, setData] = useState<Siswa[]>([]);
  const [filters, setFilters] = useState<Filters | null>(null);
  const [pagination, setPagination] = useState<Pagination>({
    page: 1,
    limit: 20,
    total: 0,
    totalPages: 0,
  });

  const [selectedKelas, setSelectedKelas] = useState('');
  const [selectedJurusan, setSelectedJurusan] = useState('');
  const [selectedSekolah, setSelectedSekolah] = useState('');
  const [searchQuery, setSearchQuery] = useState('');

  const [showDetail, setShowDetail] = useState(false);
  const [detailLoading, setDetailLoading] = useState(false);
  const [selectedSiswa, setSelectedSiswa] = useState<SiswaDetail | null>(null);

  const fetchFilters = useCallback(async () => {
    try {
      const res = await fetch('/api/siswa', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'getFilters' }),
      });
      if (res.ok) {
        const result = await res.json();
        if (result.success) setFilters(result.filters);
      }
    } catch (e) {}
  }, []);

  const fetchData = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({
        page: pagination.page.toString(),
        limit: pagination.limit.toString(),
      });
      if (selectedKelas) params.append('kelas', selectedKelas);
      if (selectedJurusan) params.append('jurusan', selectedJurusan);
      if (selectedSekolah) params.append('sekolah', selectedSekolah);
      if (searchQuery) params.append('search', searchQuery);

      const res = await fetch(`/api/siswa?${params}`);
      if (res.ok) {
        const result = await res.json();
        if (result.success) {
          setData(result.data);
          setPagination(result.pagination);
        }
      }
    } catch (e) {
    } finally {
      setLoading(false);
    }
  }, [
    pagination.page,
    pagination.limit,
    selectedKelas,
    selectedJurusan,
    selectedSekolah,
    searchQuery,
  ]);

  const fetchSiswaDetail = async (nis: string) => {
    setDetailLoading(true);
    setShowDetail(true);
    try {
      const res = await fetch('/api/siswa', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'getDetail', nis }),
      });
      if (res.ok) {
        const result = await res.json();
        if (result.success) setSelectedSiswa(result);
      }
    } catch (e) {
    } finally {
      setDetailLoading(false);
    }
  };

  useEffect(() => {
    fetchFilters();
  }, [fetchFilters]);
  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const formatCurrency = (value: number) =>
    new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: 'IDR',
      minimumFractionDigits: 0,
    }).format(value);

  const getBulanLabel = (bulan: number) => {
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

  return (
    <SidebarLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Data Siswa</h1>
        <p className="text-gray-600">Daftar siswa dan riwayat pembayaran</p>
      </div>

      <Card className="mb-6 border-gray-200">
        <CardHeader className="pb-3">
          <CardTitle className="flex items-center gap-2 text-base text-gray-900">
            <Filter className="h-4 w-4" />
            Filter & Pencarian
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div className="space-y-1 md:col-span-2">
              <label className="text-sm font-medium text-gray-700">Cari</label>
              <div className="relative">
                <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <Input
                  placeholder="Nama atau NIS..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="border-gray-300 pl-9"
                />
              </div>
            </div>
            <div className="space-y-1">
              <label className="text-sm font-medium text-gray-700">Kelas</label>
              <Select
                value={selectedKelas || 'all'}
                onValueChange={(v) => setSelectedKelas(v === 'all' ? '' : v)}
              >
                <SelectTrigger className="border-gray-300">
                  <SelectValue placeholder="Semua" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Semua</SelectItem>
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
          </div>
        </CardContent>
      </Card>

      <Card className="border-gray-200">
        <CardHeader className="pb-3">
          <CardTitle className="text-base text-gray-900">
            Daftar Siswa
          </CardTitle>
          <CardDescription className="text-gray-500">
            {pagination.total} siswa ditemukan
          </CardDescription>
        </CardHeader>
        <CardContent>
          {loading ? (
            <div className="flex items-center justify-center py-12">
              <Loader2 className="h-6 w-6 animate-spin text-gray-400" />
              <span className="ml-2 text-gray-500">Memuat...</span>
            </div>
          ) : data.length > 0 ? (
            <>
              <div className="overflow-x-auto rounded border border-gray-200">
                <Table>
                  <TableHeader>
                    <TableRow className="bg-gray-50">
                      <TableHead className="w-10 text-center text-gray-700">
                        No
                      </TableHead>
                      <TableHead className="w-24 text-gray-700">NIS</TableHead>
                      <TableHead className="text-gray-700">Nama</TableHead>
                      <TableHead className="text-center text-gray-700">
                        Kelas
                      </TableHead>
                      <TableHead className="text-center text-gray-700">
                        Transaksi
                      </TableHead>
                      <TableHead className="text-right text-gray-700">
                        Total Dana
                      </TableHead>
                      <TableHead className="w-16 text-center text-gray-700">
                        Aksi
                      </TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {data.map((siswa, idx) => (
                      <TableRow key={siswa.nis} className="hover:bg-gray-50">
                        <TableCell className="text-center text-gray-600">
                          {(pagination.page - 1) * pagination.limit + idx + 1}
                        </TableCell>
                        <TableCell className="font-mono text-sm text-gray-900">
                          {siswa.nis}
                        </TableCell>
                        <TableCell className="text-gray-900">
                          {siswa.nama}
                        </TableCell>
                        <TableCell className="text-center">
                          <Badge variant="outline" className="text-gray-700">
                            {siswa.kelas}
                            {siswa.jurusan}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-center">
                          <Badge className="bg-blue-100 text-blue-800">
                            {siswa.totalTransaksi}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-right font-medium text-green-600">
                          {formatCurrency(siswa.totalDana)}
                        </TableCell>
                        <TableCell className="text-center">
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => fetchSiswaDetail(siswa.nis)}
                          >
                            <Eye className="h-4 w-4" />
                          </Button>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>

              <div className="mt-4 flex items-center justify-between">
                <p className="text-sm text-gray-500">
                  Halaman {pagination.page} dari {pagination.totalPages || 1}
                </p>
                <div className="flex gap-1">
                  <Button
                    variant="outline"
                    size="icon"
                    onClick={() => setPagination((p) => ({ ...p, page: 1 }))}
                    disabled={pagination.page === 1}
                  >
                    <ChevronsLeft className="h-4 w-4" />
                  </Button>
                  <Button
                    variant="outline"
                    size="icon"
                    onClick={() =>
                      setPagination((p) => ({ ...p, page: p.page - 1 }))
                    }
                    disabled={pagination.page === 1}
                  >
                    <ChevronLeft className="h-4 w-4" />
                  </Button>
                  <Button
                    variant="outline"
                    size="icon"
                    onClick={() =>
                      setPagination((p) => ({ ...p, page: p.page + 1 }))
                    }
                    disabled={pagination.page >= pagination.totalPages}
                  >
                    <ChevronRight className="h-4 w-4" />
                  </Button>
                  <Button
                    variant="outline"
                    size="icon"
                    onClick={() =>
                      setPagination((p) => ({
                        ...p,
                        page: pagination.totalPages,
                      }))
                    }
                    disabled={pagination.page >= pagination.totalPages}
                  >
                    <ChevronsRight className="h-4 w-4" />
                  </Button>
                </div>
              </div>
            </>
          ) : (
            <div className="py-12 text-center">
              <Users className="mx-auto mb-3 h-10 w-10 text-gray-300" />
              <p className="text-gray-500">Tidak ada data</p>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Detail Modal */}
      {showDetail && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <Card className="max-h-[90vh] w-full max-w-3xl overflow-hidden border-gray-200">
            <CardHeader className="flex flex-row items-center justify-between border-b border-gray-200">
              <div>
                <CardTitle className="text-gray-900">Detail Siswa</CardTitle>
                <CardDescription className="text-gray-500">
                  Riwayat pembayaran
                </CardDescription>
              </div>
              <Button
                variant="ghost"
                size="icon"
                onClick={() => setShowDetail(false)}
              >
                <X className="h-4 w-4" />
              </Button>
            </CardHeader>
            <CardContent className="max-h-[calc(90vh-100px)] overflow-auto p-4">
              {detailLoading ? (
                <div className="flex items-center justify-center py-12">
                  <Loader2 className="h-6 w-6 animate-spin text-gray-400" />
                </div>
              ) : selectedSiswa ? (
                <div className="space-y-4">
                  <div className="grid grid-cols-2 gap-4 rounded border border-gray-200 bg-gray-50 p-4 md:grid-cols-4">
                    <div>
                      <p className="text-sm text-gray-500">NIS</p>
                      <p className="font-mono font-bold text-gray-900">
                        {selectedSiswa.siswa.nis}
                      </p>
                    </div>
                    <div>
                      <p className="text-sm text-gray-500">Nama</p>
                      <p className="font-bold text-gray-900">
                        {selectedSiswa.siswa.nama}
                      </p>
                    </div>
                    <div>
                      <p className="text-sm text-gray-500">Kelas</p>
                      <p className="font-bold text-gray-900">
                        {selectedSiswa.siswa.kelas}
                        {selectedSiswa.siswa.jurusan}
                      </p>
                    </div>
                    <div>
                      <p className="text-sm text-gray-500">Total Dana</p>
                      <p className="font-bold text-green-600">
                        {formatCurrency(selectedSiswa.summary.totalDana)}
                      </p>
                    </div>
                  </div>

                  <div className="rounded border border-gray-200">
                    <Table>
                      <TableHeader>
                        <TableRow className="bg-gray-50">
                          <TableHead className="text-gray-700">
                            No. Bukti
                          </TableHead>
                          <TableHead className="text-center text-gray-700">
                            Periode
                          </TableHead>
                          <TableHead className="text-right text-gray-700">
                            Jumlah
                          </TableHead>
                          <TableHead className="text-gray-700">
                            Tanggal
                          </TableHead>
                          <TableHead className="text-center text-gray-700">
                            Status
                          </TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {selectedSiswa.transactions.map((tx) => (
                          <TableRow key={tx.id}>
                            <TableCell className="font-mono text-sm text-gray-900">
                              {tx.noBukti}
                            </TableCell>
                            <TableCell className="text-center">
                              <Badge
                                variant="outline"
                                className="text-gray-700"
                              >
                                {getBulanLabel(tx.bulan)} {tx.tahun}
                              </Badge>
                            </TableCell>
                            <TableCell className="text-right font-medium text-gray-900">
                              {formatCurrency(parseInt(tx.danaMasyarakat) || 0)}
                            </TableCell>
                            <TableCell className="text-sm text-gray-600">
                              {tx.tglTx?.split(' ')[0]}
                            </TableCell>
                            <TableCell className="text-center">
                              {tx.stsBayar === 1 ? (
                                <Badge className="bg-green-100 text-green-800">
                                  Lunas
                                </Badge>
                              ) : (
                                <Badge variant="destructive">Pending</Badge>
                              )}
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </div>
                </div>
              ) : (
                <p className="py-12 text-center text-gray-500">
                  Data tidak ditemukan
                </p>
              )}
            </CardContent>
          </Card>
        </div>
      )}
    </SidebarLayout>
  );
}
