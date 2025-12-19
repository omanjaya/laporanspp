<?php

namespace App\Http\Controllers;

use App\Models\RekonData;
use App\Http\Requests\RekonSearchRequest;
use App\Http\Requests\RekonGetValueRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RekonSearchController extends Controller
{
    /**
     * Pencarian data berdasarkan 3 kriteria (mirip formula Excel)
     * GET /api/rekon/search?sekolah=SMAN_1_DENPASAR&tahun=2024&bulan=6
     */
    public function search(RekonSearchRequest $request): JsonResponse
    {
        try {
            $sekolah = $request->validated()['sekolah'];
            $tahun = $request->validated()['tahun'];
            $bulan = $request->validated()['bulan'];

            // Menggunakan scope yang sudah dibuat di model dengan additional security
            $data = RekonData::byKriteria($sekolah, $tahun, $bulan)
                ->select([
                    'id', 'sekolah', 'id_siswa', 'nama_siswa', 'tahun', 'bulan',
                    'dana_masyarakat', 'jum_tagihan', 'no_bukti', 'created_at'
                ])
                ->limit(1000) // Prevent data dumping attacks
                ->get();

            if ($data->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan',
                    'data' => '-'
                ], 404);
            }

            // Sanitize output data
            $sanitizedData = $data->map(function ($item) {
                return [
                    'id' => $item->id,
                    'sekolah' => e($item->sekolah),
                    'id_siswa' => e($item->id_siswa),
                    'nama_siswa' => e($item->nama_siswa),
                    'tahun' => (int) $item->tahun,
                    'bulan' => (int) $item->bulan,
                    'dana_masyarakat' => is_numeric($item->dana_masyarakat) ? (float) $item->dana_masyarakat : 0,
                    'jum_tagihan' => is_numeric($item->jum_tagihan) ? (float) $item->jum_tagihan : 0,
                    'no_bukti' => e($item->no_bukti),
                    'created_at' => $item->created_at->toISOString()
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Data ditemukan',
                'data' => $sanitizedData,
                'summary' => [
                    'total_records' => $data->count(),
                    'total_dana_masyarakat' => $data->sum(function($item) {
                        return is_numeric($item->dana_masyarakat) ? (float)$item->dana_masyarakat : 0;
                    }),
                    'unique_students' => $data->pluck('id_siswa')->unique()->count(),
                    'query_params' => [
                        'sekolah' => $sekolah,
                        'tahun' => $tahun,
                        'bulan' => $bulan
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Search error', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error. Please try again later.',
                'error_code' => 'search_failed'
            ], 500);
        }
    }

    /**
     * Get specific value (mirip INDEX/MATCH Excel)
     * GET /api/rekon/get-value?sekolah=SMAN_1_DENPASAR&tahun=2024&bulan=6&field=dana_masyarakat
     */
    public function getValue(RekonGetValueRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $data = RekonData::byKriteria(
                $validated['sekolah'],
                $validated['tahun'],
                $validated['bulan']
            )
            ->select($validated['field'])
            ->first();

            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan',
                    'value' => '-'
                ], 404);
            }

            // Sanitize and validate the returned value
            $value = $data->{$validated['field']};

            if (in_array($validated['field'], ['dana_masyarakat', 'jum_tagihan'])) {
                $value = is_numeric($value) ? (float) $value : 0;
            } else {
                $value = e($value); // Escape HTML for string fields
            }

            return response()->json([
                'success' => true,
                'message' => 'Data ditemukan',
                'value' => $value,
                'field' => $validated['field']
            ]);

        } catch (\Exception $e) {
            \Log::error('Get value error', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error. Please try again later.',
                'error_code' => 'get_value_failed'
            ], 500);
        }
    }

    /**
     * Get all data dengan pagination and security
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Validate this request manually since we're not using a FormRequest
            $perPage = min((int) $request->get('per_page', 15), 100); // Max 100 per page
            $schoolFilter = $request->get('school');

            if ($schoolFilter) {
                // Validate school filter
                if (!preg_match('/^[a-zA-Z0-9_\-\s]{2,100}$/', $schoolFilter)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid school filter parameter.',
                        'error_code' => 'invalid_filter'
                    ], 422);
                }
            }

            $query = RekonData::orderBy('created_at', 'desc')
                ->select([
                    'id', 'sekolah', 'id_siswa', 'nama_siswa', 'tahun', 'bulan',
                    'dana_masyarakat', 'jum_tagihan', 'no_bukti', 'created_at'
                ]);

            if ($schoolFilter) {
                $query->where('sekolah', 'LIKE', '%' . $schoolFilter . '%');
            }

            $data = $query->paginate($perPage);

            // Sanitize paginated data
            $sanitizedData = $data->getCollection()->map(function ($item) {
                return [
                    'id' => $item->id,
                    'sekolah' => e($item->sekolah),
                    'id_siswa' => e($item->id_siswa),
                    'nama_siswa' => e($item->nama_siswa),
                    'tahun' => (int) $item->tahun,
                    'bulan' => (int) $item->bulan,
                    'dana_masyarakat' => is_numeric($item->dana_masyarakat) ? (float) $item->dana_masyarakat : 0,
                    'jum_tagihan' => is_numeric($item->jum_tagihan) ? (float) $item->jum_tagihan : 0,
                    'no_bukti' => e($item->no_bukti),
                    'created_at' => $item->created_at->toISOString()
                ];
            });

            $data->setCollection($sanitizedData);

            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'per_page' => $perPage,
                    'school_filter' => $schoolFilter ? e($schoolFilter) : null
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Index error', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error. Please try again later.',
                'error_code' => 'index_failed'
            ], 500);
        }
    }
}