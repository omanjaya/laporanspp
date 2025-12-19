'use client';

import {
  Building2,
  ChevronRight,
  DollarSign,
  FileText,
  Search,
  TrendingUp,
  Upload,
  Users,
} from 'lucide-react';
import Link from 'next/link';
import { useEffect, useState } from 'react';

import { ImportExcel } from '@/components/import-excel';
import { SidebarLayout } from '@/components/layout/sidebar-layout';
import { Badge } from '@/components/ui/badge';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';

interface Analytics {
  summary: {
    total_transactions: number;
    total_dana: number;
    total_siswa: number;
    total_schools: number;
  };
  monthly_data: Array<{
    tahun: number;
    bulan: number;
    total: number;
    dana: number;
  }>;
  school_data: Array<{
    sekolah: string;
    total: number;
    dana: number;
    siswa: number;
  }>;
}

interface RekonData {
  id: number;
  sekolah: string;
  idSiswa: string;
  namaSiswa: string;
  kelas: string;
  jurusan: string;
  tahun: number;
  bulan: number;
  jumTagihan: number;
  danaMasyarakat: string;
  tglTxFormatted: string;
}

export default function DashboardPage() {
  const [analytics, setAnalytics] = useState<Analytics | null>(null);
  const [recentData, setRecentData] = useState<RekonData[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchData();
  }, []);

  const fetchData = async () => {
    setLoading(true);
    try {
      const [analyticsRes, dataRes] = await Promise.all([
        fetch('/api/dashboard'),
        fetch('/api/rekon?limit=5'),
      ]);

      if (analyticsRes.ok) {
        const analyticsData = await analyticsRes.json();
        setAnalytics(analyticsData);
      }

      if (dataRes.ok) {
        const dataResult = await dataRes.json();
        setRecentData(dataResult.data || []);
      }
    } catch (error) {
      // Silent
    } finally {
      setLoading(false);
    }
  };

  const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: 'IDR',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(value);
  };

  const formatCompact = (value: number) => {
    if (value >= 1_000_000_000) {
      return `Rp ${(value / 1_000_000_000).toFixed(1)}M`;
    }
    if (value >= 1_000_000) {
      return `Rp ${(value / 1_000_000).toFixed(0)}jt`;
    }
    return formatCurrency(value);
  };

  const formatMonth = (bulan: number, tahun: number) => {
    const months = [
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
    return `${months[bulan - 1]} ${tahun}`;
  };

  return (
    <SidebarLayout>
      {/* Header */}
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
        <p className="text-gray-600">Ringkasan data pembayaran SPP</p>
      </div>

      {/* Stats */}
      <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Card className="border-gray-200">
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Total Transaksi</p>
                <p className="text-2xl font-bold text-gray-900">
                  {loading
                    ? '...'
                    : (
                        analytics?.summary?.total_transactions || 0
                      ).toLocaleString()}
                </p>
              </div>
              <div className="rounded-md bg-blue-100 p-2">
                <TrendingUp className="h-5 w-5 text-blue-600" />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card className="border-gray-200">
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Total Dana</p>
                <p className="text-2xl font-bold text-gray-900">
                  {loading
                    ? '...'
                    : formatCompact(analytics?.summary?.total_dana || 0)}
                </p>
              </div>
              <div className="rounded-md bg-green-100 p-2">
                <DollarSign className="h-5 w-5 text-green-600" />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card className="border-gray-200">
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Total Siswa</p>
                <p className="text-2xl font-bold text-gray-900">
                  {loading
                    ? '...'
                    : (analytics?.summary?.total_siswa || 0).toLocaleString()}
                </p>
              </div>
              <div className="rounded-md bg-purple-100 p-2">
                <Users className="h-5 w-5 text-purple-600" />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card className="border-gray-200">
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Total Sekolah</p>
                <p className="text-2xl font-bold text-gray-900">
                  {loading ? '...' : analytics?.summary?.total_schools || 0}
                </p>
              </div>
              <div className="rounded-md bg-orange-100 p-2">
                <Building2 className="h-5 w-5 text-orange-600" />
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Quick Actions */}
      <div className="mb-6">
        <h2 className="mb-3 text-lg font-semibold text-gray-900">Aksi Cepat</h2>
        <div className="grid gap-3 sm:grid-cols-3">
          <Link href="/laporan">
            <Card className="cursor-pointer border-gray-200 transition-colors hover:border-blue-300 hover:bg-blue-50/50">
              <CardContent className="flex items-center gap-3 p-4">
                <div className="rounded-md bg-blue-100 p-2">
                  <FileText className="h-5 w-5 text-blue-600" />
                </div>
                <div className="flex-1">
                  <p className="font-medium text-gray-900">Laporan Per Kelas</p>
                  <p className="text-sm text-gray-500">Rekap pembayaran</p>
                </div>
                <ChevronRight className="h-5 w-5 text-gray-400" />
              </CardContent>
            </Card>
          </Link>

          <Link href="/siswa">
            <Card className="cursor-pointer border-gray-200 transition-colors hover:border-purple-300 hover:bg-purple-50/50">
              <CardContent className="flex items-center gap-3 p-4">
                <div className="rounded-md bg-purple-100 p-2">
                  <Users className="h-5 w-5 text-purple-600" />
                </div>
                <div className="flex-1">
                  <p className="font-medium text-gray-900">Data Siswa</p>
                  <p className="text-sm text-gray-500">Master data</p>
                </div>
                <ChevronRight className="h-5 w-5 text-gray-400" />
              </CardContent>
            </Card>
          </Link>

          <Link href="/search">
            <Card className="cursor-pointer border-gray-200 transition-colors hover:border-green-300 hover:bg-green-50/50">
              <CardContent className="flex items-center gap-3 p-4">
                <div className="rounded-md bg-green-100 p-2">
                  <Search className="h-5 w-5 text-green-600" />
                </div>
                <div className="flex-1">
                  <p className="font-medium text-gray-900">Pencarian</p>
                  <p className="text-sm text-gray-500">Cari transaksi</p>
                </div>
                <ChevronRight className="h-5 w-5 text-gray-400" />
              </CardContent>
            </Card>
          </Link>
        </div>
      </div>

      {/* Content Grid */}
      <div className="grid gap-6 lg:grid-cols-2">
        {/* Import */}
        <Card className="border-gray-200">
          <CardHeader className="pb-3">
            <CardTitle className="flex items-center gap-2 text-base text-gray-900">
              <Upload className="h-4 w-4" />
              Import Data
            </CardTitle>
            <CardDescription className="text-gray-500">
              Upload file Excel
            </CardDescription>
          </CardHeader>
          <CardContent>
            <ImportExcel />
          </CardContent>
        </Card>

        {/* Recent Transactions */}
        <Card className="border-gray-200">
          <CardHeader className="pb-3">
            <CardTitle className="text-base text-gray-900">
              Transaksi Terbaru
            </CardTitle>
            <CardDescription className="text-gray-500">
              5 transaksi terakhir
            </CardDescription>
          </CardHeader>
          <CardContent>
            {loading ? (
              <p className="py-8 text-center text-gray-500">Memuat...</p>
            ) : recentData.length > 0 ? (
              <div className="space-y-2">
                {recentData.map((row) => (
                  <div
                    key={row.id}
                    className="flex items-center justify-between rounded-md border border-gray-100 bg-gray-50 p-3"
                  >
                    <div>
                      <p className="font-medium text-gray-900">
                        {row.namaSiswa}
                      </p>
                      <p className="text-xs text-gray-500">
                        {row.kelas}
                        {row.jurusan} • {row.tglTxFormatted?.split(' ')[0]}
                      </p>
                    </div>
                    <span className="font-semibold text-green-600">
                      {formatCurrency(row.jumTagihan)}
                    </span>
                  </div>
                ))}
              </div>
            ) : (
              <p className="py-8 text-center text-gray-500">Belum ada data</p>
            )}
          </CardContent>
        </Card>

        {/* Monthly Stats */}
        <Card className="border-gray-200 lg:col-span-2">
          <CardHeader className="pb-3">
            <CardTitle className="text-base text-gray-900">
              Transaksi Bulanan
            </CardTitle>
            <CardDescription className="text-gray-500">
              Data per bulan
            </CardDescription>
          </CardHeader>
          <CardContent>
            {loading ? (
              <p className="py-8 text-center text-gray-500">Memuat...</p>
            ) : analytics?.monthly_data?.length ? (
              <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                {analytics.monthly_data
                  .sort((a, b) => b.tahun - a.tahun || b.bulan - a.bulan)
                  .slice(0, 6)
                  .map((item, i) => (
                    <div
                      key={i}
                      className="flex items-center justify-between rounded-md border border-gray-100 bg-gray-50 p-3"
                    >
                      <div>
                        <Badge variant="outline" className="mb-1 text-gray-700">
                          {formatMonth(item.bulan, item.tahun)}
                        </Badge>
                        <p className="text-xs text-gray-500">
                          {item.total} transaksi
                        </p>
                      </div>
                      <span className="font-semibold text-green-600">
                        {formatCompact(item.dana)}
                      </span>
                    </div>
                  ))}
              </div>
            ) : (
              <p className="py-8 text-center text-gray-500">Belum ada data</p>
            )}
          </CardContent>
        </Card>
      </div>
    </SidebarLayout>
  );
}
