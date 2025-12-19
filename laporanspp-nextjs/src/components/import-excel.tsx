'use client';

import {
  AlertTriangle,
  CheckCircle,
  FileSpreadsheet,
  Loader2,
  Upload,
  XCircle,
} from 'lucide-react';
import { useCallback, useState } from 'react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';

interface ImportStats {
  total: number;
  inserted: number;
  updated: number;
  skipped: number;
  errors: number;
}

interface ImportResult {
  success: boolean;
  message: string;
  stats: ImportStats;
  errors?: Array<{ row: number; message: string }>;
}

export function ImportExcel() {
  const [file, setFile] = useState<File | null>(null);
  const [isDragging, setIsDragging] = useState(false);
  const [isUploading, setIsUploading] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [result, setResult] = useState<ImportResult | null>(null);
  const [importMode, setImportMode] = useState<'skip' | 'update'>('skip');

  const handleDragOver = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(true);
  }, []);

  const handleDragLeave = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
  }, []);

  const handleDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);

    const droppedFile = e.dataTransfer.files[0];
    if (
      droppedFile &&
      (droppedFile.name.endsWith('.xlsx') || droppedFile.name.endsWith('.xls'))
    ) {
      setFile(droppedFile);
      setResult(null);
    }
  }, []);

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const selectedFile = e.target.files?.[0];
    if (selectedFile) {
      setFile(selectedFile);
      setResult(null);
    }
  };

  const handleUpload = async () => {
    if (!file) return;

    setIsUploading(true);
    setUploadProgress(0);
    setResult(null);

    try {
      // Simulate progress for UX
      const progressInterval = setInterval(() => {
        setUploadProgress((prev) => Math.min(prev + 10, 90));
      }, 200);

      const formData = new FormData();
      formData.append('file', file);
      formData.append('mode', importMode);

      const response = await fetch('/api/import', {
        method: 'POST',
        body: formData,
      });

      clearInterval(progressInterval);
      setUploadProgress(100);

      const data: ImportResult = await response.json();
      setResult(data);
    } catch (error) {
      setResult({
        success: false,
        message: error instanceof Error ? error.message : 'Upload failed',
        stats: { total: 0, inserted: 0, updated: 0, skipped: 0, errors: 1 },
      });
    } finally {
      setIsUploading(false);
    }
  };

  const handleReset = () => {
    setFile(null);
    setResult(null);
    setUploadProgress(0);
  };

  return (
    <Card className="w-full">
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <FileSpreadsheet className="h-5 w-5" />
          Import Data Excel
        </CardTitle>
        <CardDescription>
          Upload file Excel (.xlsx) untuk import data rekonsiliasi SPP
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-6">
        {/* Import Mode Selection */}
        <div className="flex items-center gap-4">
          <label className="text-sm font-medium">Mode Import:</label>
          <Select
            value={importMode}
            onValueChange={(value: 'skip' | 'update') => setImportMode(value)}
          >
            <SelectTrigger className="w-48">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="skip">Skip Duplikat</SelectItem>
              <SelectItem value="update">Update Duplikat</SelectItem>
            </SelectContent>
          </Select>
          <span className="text-muted-foreground text-xs">
            {importMode === 'skip'
              ? 'Data yang sudah ada akan dilewati'
              : 'Data yang sudah ada akan diperbarui'}
          </span>
        </div>

        {/* Drop Zone */}
        <div
          className={`rounded-lg border-2 border-dashed p-8 text-center transition-colors ${
            isDragging
              ? 'border-primary bg-primary/5'
              : file
                ? 'border-green-500 bg-green-50'
                : 'border-muted-foreground/25 hover:border-primary/50'
          }`}
          onDragOver={handleDragOver}
          onDragLeave={handleDragLeave}
          onDrop={handleDrop}
        >
          {file ? (
            <div className="space-y-2">
              <FileSpreadsheet className="mx-auto h-12 w-12 text-green-600" />
              <p className="font-medium">{file.name}</p>
              <p className="text-muted-foreground text-sm">
                {(file.size / 1024).toFixed(1)} KB
              </p>
              <Button variant="outline" size="sm" onClick={handleReset}>
                Ganti File
              </Button>
            </div>
          ) : (
            <div className="space-y-4">
              <Upload className="text-muted-foreground mx-auto h-12 w-12" />
              <div>
                <p className="font-medium">Drag & drop file Excel di sini</p>
                <p className="text-muted-foreground text-sm">atau</p>
              </div>
              <label htmlFor="file-upload">
                <Button variant="outline" asChild>
                  <span>Pilih File</span>
                </Button>
                <input
                  id="file-upload"
                  type="file"
                  accept=".xlsx,.xls"
                  className="hidden"
                  onChange={handleFileChange}
                />
              </label>
              <p className="text-muted-foreground text-xs">
                Format: .xlsx atau .xls (max 10MB)
              </p>
            </div>
          )}
        </div>

        {/* Upload Progress */}
        {isUploading && (
          <div className="space-y-2">
            <div className="flex items-center gap-2">
              <Loader2 className="h-4 w-4 animate-spin" />
              <span className="text-sm">Mengimport data...</span>
            </div>
            <Progress value={uploadProgress} className="h-2" />
          </div>
        )}

        {/* Result */}
        {result && (
          <Alert variant={result.success ? 'success' : 'destructive'}>
            {result.success ? (
              <CheckCircle className="h-4 w-4" />
            ) : (
              <XCircle className="h-4 w-4" />
            )}
            <AlertTitle>
              {result.success ? 'Import Berhasil' : 'Import Gagal'}
            </AlertTitle>
            <AlertDescription>
              <p>{result.message}</p>
              {result.success && (
                <div className="mt-3 grid grid-cols-2 gap-4 md:grid-cols-4">
                  <div className="rounded border bg-white p-2 text-center">
                    <div className="text-2xl font-bold text-blue-600">
                      {result.stats.total}
                    </div>
                    <div className="text-xs">Total Baris</div>
                  </div>
                  <div className="rounded border bg-white p-2 text-center">
                    <div className="text-2xl font-bold text-green-600">
                      {result.stats.inserted}
                    </div>
                    <div className="text-xs">Ditambahkan</div>
                  </div>
                  <div className="rounded border bg-white p-2 text-center">
                    <div className="text-2xl font-bold text-orange-600">
                      {result.stats.updated}
                    </div>
                    <div className="text-xs">Diperbarui</div>
                  </div>
                  <div className="rounded border bg-white p-2 text-center">
                    <div className="text-2xl font-bold text-gray-600">
                      {result.stats.skipped}
                    </div>
                    <div className="text-xs">Dilewati</div>
                  </div>
                </div>
              )}
              {result.errors && result.errors.length > 0 && (
                <div className="mt-3">
                  <p className="mb-1 text-sm font-medium">Errors:</p>
                  <ul className="max-h-32 space-y-1 overflow-y-auto text-xs">
                    {result.errors.slice(0, 10).map((err, i) => (
                      <li key={i} className="flex items-start gap-1">
                        <AlertTriangle className="mt-0.5 h-3 w-3 shrink-0" />
                        Row {err.row}: {err.message}
                      </li>
                    ))}
                    {result.errors.length > 10 && (
                      <li className="text-muted-foreground">
                        ...dan {result.errors.length - 10} error lainnya
                      </li>
                    )}
                  </ul>
                </div>
              )}
            </AlertDescription>
          </Alert>
        )}

        {/* Upload Button */}
        <div className="flex justify-end gap-2">
          {result && (
            <Button variant="outline" onClick={handleReset}>
              Import File Lain
            </Button>
          )}
          <Button
            onClick={handleUpload}
            disabled={!file || isUploading}
            className="min-w-32"
          >
            {isUploading ? (
              <>
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                Importing...
              </>
            ) : (
              <>
                <Upload className="mr-2 h-4 w-4" />
                Import Data
              </>
            )}
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}
