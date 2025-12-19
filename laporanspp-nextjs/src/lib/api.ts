// API Configuration for Laravel Backend
const API_BASE_URL =
  process.env.NODE_ENV === 'production'
    ? 'https://your-laravel-domain.com'
    : 'http://localhost:8000';

export const apiConfig = {
  baseURL: API_BASE_URL,
  endpoints: {
    // Schools
    schools: '/api/schools',

    // Rekon Data
    rekonSearch: '/api/rekon/search',
    rekonGet: '/api/rekon/get-value',
    rekonExport: '/api/rekon/export',

    // Dashboard
    analytics: '/api/dashboard/analytics',

    // File Upload
    importCSV: '/api/rekon/import/csv',
    importExcel: '/api/rekon/import/excel',
  },
};

// API helper functions
export async function fetchFromAPI<T>(
  endpoint: string,
  options?: RequestInit,
): Promise<T> {
  const url = `${apiConfig.baseURL}${endpoint}`;

  const config: RequestInit = {
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...options?.headers,
    },
    ...options,
  };

  try {
    const response = await fetch(url, config);

    if (!response.ok) {
      throw new Error(`API Error: ${response.status} - ${response.statusText}`);
    }

    return await response.json();
  } catch (error) {
    console.error('API Request failed:', error);
    throw error;
  }
}

// Specific API calls
export const api = {
  // Get schools list
  getSchools: () => fetchFromAPI(apiConfig.endpoints.schools),

  // Search rekon data
  searchRekon: (data: { sekolah: string; tahun: number; bulan: number }) =>
    fetchFromAPI(apiConfig.endpoints.rekonSearch, {
      method: 'POST',
      body: JSON.stringify(data),
    }),

  // Get analytics data
  getAnalytics: () => fetchFromAPI(apiConfig.endpoints.analytics),

  // Export data
  exportCSV: () => fetchFromAPI(apiConfig.endpoints.rekonExport),
  exportExcel: () => fetchFromAPI(apiConfig.endpoints.rekonExport),
};
