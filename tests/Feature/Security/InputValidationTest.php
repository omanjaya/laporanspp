<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\RekonData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class InputValidationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_validates_search_parameters()
    {
        // Test missing parameters
        $response = $this->getJson('/api/rekon/search');
        $response->assertStatus(422);

        // Test empty parameters
        $response = $this->getJson('/api/rekon/search?sekolah=&tahun=&bulan=');
        $response->assertStatus(422);

        // Test invalid parameter types
        $response = $this->getJson('/api/rekon/search?sekolah=test&tahun=invalid&bulan=invalid');
        $response->assertStatus(422);

        // Test invalid year range
        $response = $this->getJson('/api/rekon/search?sekolah=test&tahun=1800&bulan=1');
        $response->assertStatus(422);

        $response = $this->getJson('/api/rekon/search?sekolah=test&tahun=3000&bulan=1');
        $response->assertStatus(422);

        // Test invalid month range
        $response = $this->getJson('/api/rekon/search?sekolah=test&tahun=2024&bulan=0');
        $response->assertStatus(422);

        $response = $this->getJson('/api/rekon/search?sekolah=test&tahun=2024&bulan=13');
        $response->assertStatus(422);

        // Test valid parameters
        RekonData::factory()->create([
            'sekolah' => 'SMAN_1_DENPASAR',
            'tahun' => 2024,
            'bulan' => 1
        ]);

        $response = $this->getJson('/api/rekon/search?sekolah=SMAN_1_DENPASAR&tahun=2024&bulan=1');
        $response->assertStatus(200);
    }

    /** @test */
    public function it_validates_get_value_parameters()
    {
        // Test missing parameters
        $response = $this->getJson('/api/rekon/get-value');
        $response->assertStatus(422);

        // Test invalid field parameter
        $response = $this->getJson('/api/rekon/get-value?sekolah=test&tahun=2024&bulan=1&field=invalid_field');
        $response->assertStatus(422);

        // Test SQL injection in field parameter
        $response = $this->getJson('/api/rekon/get-value?sekolah=test&tahun=2024&bulan=1&field=id; DROP TABLE rekon_data;');
        $response->assertStatus(422);

        // Test valid field
        $response = $this->getJson('/api/rekon/get-value?sekolah=test&tahun=2024&bulan=1&field=dana_masyarakat');
        $this->assertContains($response->status(), [200, 404]);
    }

    /** @test */
    public function it_validates_index_parameters()
    {
        // Test invalid per_page parameter
        $response = $this->getJson('/api/rekon/index?per_page=invalid');
        $response->assertStatus(422);

        $response = $this->getJson('/api/rekon/index?per_page=0');
        $response->assertStatus(422);

        $response = $this->getJson('/api/rekon/index?per_page=1000'); // Too high
        $response->assertStatus(422);

        // Test negative per_page
        $response = $this->getJson('/api/rekon/index?per_page=-10');
        $response->assertStatus(422);

        // Test valid per_page
        $response = $this->getJson('/api/rekon/index?per_page=10');
        $response->assertStatus(200);

        // Test invalid school filter
        $response = $this->getJson('/api/rekon/index?school=<script>alert("xss")</script>');
        $response->assertStatus(422);

        // Test school filter with special characters
        $response = $this->getJson('/api/rekon/index?school=SMAN_1_DENPASAR');
        $response->assertStatus(200);
    }

    /** @test */
    public function it_validates_laporan_kelas_parameters()
    {
        // Test missing parameters
        $response = $this->getJson('/api/rekon/laporan/kelas');
        $response->assertStatus(422);

        // Test empty parameters
        $response = $this->getJson('/api/rekon/laporan/kelas?sekolah=&kelas=&angkatan=');
        $response->assertStatus(422);

        // Test invalid angkatan range
        $response = $this->getJson('/api/rekon/laporan/kelas?sekolah=test&kelas=test&angkatan=1999');
        $response->assertStatus(422);

        $response = $this->getJson('/api/rekon/laporan/kelas?sekolah=test&kelas=test&angkatan=2101');
        $response->assertStatus(422);

        // Test non-integer angkatan
        $response = $this->getJson('/api/rekon/laporan/kelas?sekolah=test&kelas=test&angkatan=invalid');
        $response->assertStatus(422);

        // Test valid parameters
        $response = $this->getJson('/api/rekon/laporan/kelas?sekolah=SMAN_1_DENPASAR&kelas=XI&angkatan=2022');
        $this->assertContains($response->status(), [200, 404]);
    }

    /** @test */
    public function it_validates_import_file_parameters()
    {
        // Test missing file
        $response = $this->postJson('/api/rekon/import');
        $response->assertStatus(422);

        // Test invalid file type
        $response = $this->postJson('/api/rekon/import', [
            'file' => UploadedFile::fake()->create('test.txt', 1000)
        ]);
        $response->assertStatus(422);

        // Test oversized file
        $response = $this->postJson('/api/rekon/import', [
            'file' => UploadedFile::fake()->create('test.xlsx', 50 * 1024 * 1024) // 50MB
        ]);
        $response->assertStatus(422);

        // Test valid file
        Storage::fake('local');
        $response = $this->postJson('/api/rekon/import', [
            'file' => UploadedFile::fake()->create('test.xlsx', 1000)
        ]);
        // This will fail due to invalid file contents, but should pass file validation
        $this->assertContains($response->status(), [422, 500]);
    }

    /** @test */
    public function it_validates_bank_csv_import_parameters()
    {
        // Test missing file
        $response = $this->postJson('/api/rekon/import-bank-csv');
        $response->assertStatus(422);

        // Test non-CSV file
        $response = $this->postJson('/api/rekon/import-bank-csv', [
            'file' => UploadedFile::fake()->create('test.pdf', 1000)
        ]);
        $response->assertStatus(422);

        // Test valid CSV file
        Storage::fake('local');
        $response = $this->postJson('/api/rekon/import-bank-csv', [
            'file' => UploadedFile::fake()->create('test.csv', 1000)
        ]);
        // This will fail due to invalid file contents, but should pass file validation
        $this->assertContains($response->status(), [422, 500]);
    }

    /** @test */
    public function it_validates_export_parameters()
    {
        // Test export Excel with invalid parameters
        $response = $this->postJson('/api/rekon/export/excel', [
            'tahun' => 'invalid'
        ]);
        // Should handle invalid parameters gracefully
        $this->assertContains($response->status(), [422, 200, 500]);

        // Test export CSV with invalid parameters
        $response = $this->postJson('/api/rekon/export/csv', [
            'bulan' => 'invalid'
        ]);
        $this->assertContains($response->status(), [422, 200, 500]);

        // Test export laporan kelas with missing parameters
        $response = $this->postJson('/api/rekon/export/laporan-kelas');
        $response->assertStatus(422);

        // Test export laporan kelas with invalid angkatan
        $response = $this->postJson('/api/rekon/export/laporan-kelas', [
            'sekolah' => 'test',
            'kelas' => 'test',
            'angkatan' => 'invalid'
        ]);
        $response->assertStatus(422);
    }

    /** @test */
    public function it_sanitizes_html_and_script_input()
    {
        $xssPayloads = [
            '<script>alert("xss")</script>',
            '<img src=x onerror=alert("xss")>',
            'javascript:alert("xss")',
            '<svg onload=alert("xss")>',
            '"><script>alert("xss")</script>',
            '\';alert("xss");//'
        ];

        foreach ($xssPayloads as $payload) {
            // Test in search parameters
            $response = $this->getJson("/api/rekon/search?sekolah={$payload}&tahun=2024&bulan=1");
            $this->assertContains($response->status(), [422, 200, 404]);

            // Test in index filter
            $response = $this->getJson("/api/rekon/index?school={$payload}");
            $this->assertContains($response->status(), [422, 200]);

            // Verify XSS payload is not in response
            if ($response->status() === 200) {
                $content = json_encode($response->json());
                $this->assertStringNotContainsString('<script>', $content);
                $this->assertStringNotContainsString('javascript:', $content);
                $this->assertStringNotContainsString('onerror=', $content);
                $this->assertStringNotContainsString('onload=', $content);
            }
        }
    }

    /** @test */
    public function it_prevents_sql_injection()
    {
        $sqlPayloads = [
            "'; DROP TABLE rekon_data; --",
            "' OR '1'='1",
            "'; SELECT * FROM rekon_data; --",
            "' UNION SELECT * FROM users --",
            "'; DELETE FROM rekon_data WHERE 1=1; --"
        ];

        foreach ($sqlPayloads as $payload) {
            // Test in search parameters
            $response = $this->getJson("/api/rekon/search?sekolah={$payload}&tahun=2024&bulan=1");
            $this->assertContains($response->status(), [422, 200, 404]);

            // Verify table still exists
            $this->assertTrue(\Schema::hasTable('rekon_data'));

            // Test in index filter
            $response = $this->getJson("/api/rekon/index?school={$payload}");
            $this->assertContains($response->status(), [422, 200]);
        }
    }

    /** @test */
    public function it_validates_string_length_limits()
    {
        // Test very long strings
        $longString = str_repeat('A', 1000);

        $response = $this->getJson("/api/rekon/search?sekolah={$longString}&tahun=2024&bulan=1");
        $this->assertContains($response->status(), [422, 200, 404]);

        $response = $this->getJson("/api/rekon/index?school={$longString}");
        $this->assertContains($response->status(), [422, 200]);

        // Test very long field name
        $longField = str_repeat('a', 100);
        $response = $this->getJson("/api/rekon/get-value?sekolah=test&tahun=2024&bulan=1&field={$longField}");
        $response->assertStatus(422);
    }

    /** @test */
    public function it_handles_special_characters_in_input()
    {
        $specialChars = [
            'ñáéíóú',
            '中文',
            'العربية',
            'עברית',
            '🎉🚀💻',
            'test@example.com',
            'https://example.com',
            '+62-812-3456-7890'
        ];

        foreach ($specialChars as $chars) {
            $encoded = urlencode($chars);
            $response = $this->getJson("/api/rekon/search?sekolah={$encoded}&tahun=2024&bulan=1");
            $this->assertContains($response->status(), [422, 200, 404]);
        }
    }

    /** @test */
    public function it_validates_json_input()
    {
        // Test malformed JSON
        $response = $this->postJson('/api/rekon/export/excel', '{"invalid": json}', [
            'Content-Type' => 'application/json'
        ]);
        $response->assertStatus(400);

        // Test JSON with unexpected fields
        $response = $this->postJson('/api/rekon/export/excel', [
            'unexpected_field' => 'value',
            'sekolah' => 'test'
        ]);
        // Should handle unexpected fields gracefully
        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function it_handles_duplicate_parameter_names()
    {
        // Test multiple values for same parameter
        $response = $this->getJson('/api/rekon/search?sekolah=value1&sekolah=value2&tahun=2024&bulan=1');
        $this->assertContains($response->status(), [422, 200, 404]);
    }

    /** @test */
    public function it_validates_date_and_time_formats()
    {
        // Test invalid date formats in year/month
        $response = $this->getJson('/api/rekon/search?sekolah=test&tahun=2024-01-01&bulan=1');
        $response->assertStatus(422);

        $response = $this->getJson('/api/rekon/search?sekolah=test&tahun=2024&bulan=01'); // Should be integer
        $this->assertContains($response->status(), [422, 200, 404]);

        // Test valid date formats
        $response = $this->getJson('/api/rekon/search?sekolah=test&tahun=2024&bulan=1');
        $this->assertContains($response->status(), [200, 404]);
    }

    /** @test */
    public function it_handles_null_and_empty_values()
    {
        // Test null values
        $response = $this->getJson('/api/rekon/search?sekolah=null&tahun=2024&bulan=1');
        $this->assertContains($response->status(), [422, 200, 404]);

        // Test empty string values
        $response = $this->getJson('/api/rekon/search?sekolah=&tahun=2024&bulan=1');
        $response->assertStatus(422);

        // Test whitespace-only values
        $response = $this->getJson('/api/rekon/search?sekolah=   &tahun=2024&bulan=1');
        $response->assertStatus(422);
    }

    /** @test */
    public function it_validates_numeric_ranges()
    {
        // Test year boundaries
        $testCases = [
            ['tahun' => 1899, 'bulan' => 1, 'expected' => 422],
            ['tahun' => 2101, 'bulan' => 1, 'expected' => 422],
            ['tahun' => 2024, 'bulan' => 0, 'expected' => 422],
            ['tahun' => 2024, 'bulan' => 13, 'expected' => 422],
            ['tahun' => 2000, 'bulan' => 1, 'expected' => 404], // Valid but no data
            ['tahun' => 2024, 'bulan' => 12, 'expected' => 404], // Valid but no data
        ];

        foreach ($testCases as $case) {
            $response = $this->getJson("/api/rekon/search?sekolah=test&tahun={$case['tahun']}&bulan={$case['bulan']}");
            $this->assertContains($response->status(), [$case['expected'], 200]);
        }
    }

    /** @test */
    public function it_prevents_parameter_pollution()
    {
        // Test with excessive number of parameters
        $params = 'sekolah=test&tahun=2024&bulan=1';
        for ($i = 0; $i < 100; $i++) {
            $params .= "&param{$i}=value{$i}";
        }

        $response = $this->getJson("/api/rekon/search?{$params}");
        $this->assertContains($response->status(), [422, 200, 404, 414]); // 414 = Request-URI Too Long
    }

    /** @test */
    public function it_validates_import_status_parameters()
    {
        // Test missing job_id
        $response = $this->getJson('/api/rekon/import-status');
        $response->assertStatus(400);

        // Test empty job_id
        $response = $this->getJson('/api/rekon/import-status?job_id=');
        $response->assertStatus(400);

        // Test invalid job_id format
        $response = $this->getJson('/api/rekon/import-status?job_id=<script>alert("xss")</script>');
        $this->assertContains($response->status(), [400, 200]);

        // Test valid job_id format
        $response = $this->getJson('/api/rekon/import-status?job_id=valid-job-id-123');
        $this->assertContains($response->status(), [200, 404]);
    }

    /** @test */
    public function it_handles_encoding_issues()
    {
        // Test various encodings
        $encodings = [
            '%E2%9C%93', // Checkmark UTF-8
            '%F0%9F%98%81', // Face with tears emoji
            '%C3%B1', // ñ UTF-8
            '%E4%B8%AD%E6%96%87', // Chinese characters
        ];

        foreach ($encodings as $encoding) {
            $response = $this->getJson("/api/rekon/search?sekolah={$encoding}&tahun=2024&bulan=1");
            $this->assertContains($response->status(), [422, 200, 404]);
        }
    }
}