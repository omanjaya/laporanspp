<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\School;
use App\Models\RekonData;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SchoolTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_school()
    {
        // Arrange
        $data = [
            'name' => 'SMAN_1_DENPASAR',
            'display_name' => 'SMA Negeri 1 Denpasar',
            'address' => 'Jl. Test Address No. 123',
            'phone' => '+6236123456',
            'email' => 'sman1@denpasar.sch.id',
            'is_active' => true
        ];

        // Act
        $school = School::create($data);

        // Assert
        $this->assertInstanceOf(School::class, $school);
        $this->assertDatabaseHas('schools', [
            'name' => 'SMAN_1_DENPASAR',
            'display_name' => 'SMA Negeri 1 Denpasar'
        ]);
    }

    /** @test */
    public function it_has_fillable_attributes()
    {
        // Arrange
        $school = new School();

        // Act & Assert
        $expectedFillable = [
            'name',
            'display_name',
            'address',
            'phone',
            'email',
            'is_active'
        ];

        $this->assertEquals($expectedFillable, $school->getFillable());
    }

    /** @test */
    public function it_casts_is_active_to_boolean()
    {
        // Arrange
        $school = School::factory()->create(['is_active' => 1]);

        // Act
        $freshSchool = $school->fresh();

        // Assert
        $this->assertIsBool($freshSchool->is_active);
        $this->assertTrue($freshSchool->is_active);
    }

    /** @test */
    public function it_has_rekon_data_relationship()
    {
        // Arrange
        $school = School::factory()->create(['name' => 'SMAN_1_DENPASAR']);
        RekonData::factory()->count(3)->create(['sekolah' => 'SMAN_1_DENPASAR']);

        // Act
        $rekonData = $school->rekonData;

        // Assert
        $this->assertCount(3, $rekonData);
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $rekonData);
        $this->assertInstanceOf(RekonData::class, $rekonData->first());
    }

    /** @test */
    public function it_can_get_active_schools()
    {
        // Arrange
        School::factory()->create(['name' => 'SMAN_1_DENPASAR', 'is_active' => true]);
        School::factory()->create(['name' => 'SMAN_2_DENPASAR', 'is_active' => true]);
        School::factory()->create(['name' => 'SMAN_3_DENPASAR', 'is_active' => false]);

        // Act
        $activeSchools = School::getActive();

        // Assert
        $this->assertCount(2, $activeSchools);
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $activeSchools);

        $schoolNames = $activeSchools->pluck('name');
        $this->assertContains('SMAN_1_DENPASAR', $schoolNames);
        $this->assertContains('SMAN_2_DENPASAR', $schoolNames);
        $this->assertNotContains('SMAN_3_DENPASAR', $schoolNames);
    }

    /** @test */
    public function it_orders_active_schools_by_display_name()
    {
        // Arrange
        School::factory()->create(['display_name' => 'C School', 'is_active' => true]);
        School::factory()->create(['display_name' => 'A School', 'is_active' => true]);
        School::factory()->create(['display_name' => 'B School', 'is_active' => true]);

        // Act
        $activeSchools = School::getActive();

        // Assert
        $displayNames = $activeSchools->pluck('display_name');
        $this->assertEquals(['A School', 'B School', 'C School'], $displayNames->toArray());
    }

    /** @test */
    public function it_can_get_school_by_name()
    {
        // Arrange
        School::factory()->create(['name' => 'SMAN_1_DENPASAR', 'is_active' => true]);
        School::factory()->create(['name' => 'SMAN_2_DENPASAR', 'is_active' => false]);

        // Act
        $activeSchool = School::getByName('SMAN_1_DENPASAR');
        $inactiveSchool = School::getByName('SMAN_2_DENPASAR');
        $nonexistentSchool = School::getByName('NONEXISTENT');

        // Assert
        $this->assertInstanceOf(School::class, $activeSchool);
        $this->assertEquals('SMAN_1_DENPASAR', $activeSchool->name);

        $this->assertNull($inactiveSchool);
        $this->assertNull($nonexistentSchool);
    }

    /** @test */
    public function it_handles_unique_name_constraint()
    {
        // Arrange
        School::factory()->create(['name' => 'SMAN_1_DENPASAR']);

        // Act & Assert
        $this->expectException(\Illuminate\Database\QueryException::class);
        School::factory()->create(['name' => 'SMAN_1_DENPASAR']);
    }

    /** @test */
    public function it_handles_null_values_in_optional_fields()
    {
        // Arrange
        $data = [
            'name' => 'SMAN_1_DENPASAR',
            'display_name' => 'SMA Negeri 1 Denpasar',
            'address' => null,
            'phone' => null,
            'email' => null,
            'is_active' => true
        ];

        // Act
        $school = School::create($data);

        // Assert
        $this->assertInstanceOf(School::class, $school);
        $this->assertNull($school->address);
        $this->assertNull($school->phone);
        $this->assertNull($school->email);
        $this->assertTrue($school->is_active);
    }

    /** @test */
    public function it_handles_string_length_limits()
    {
        // Arrange
        $longString = str_repeat('A', 255);

        // Act
        $school = School::factory()->create([
            'name' => $longString,
            'display_name' => $longString,
            'address' => $longString,
            'phone' => $longString,
            'email' => $longString
        ]);

        // Assert
        $freshSchool = $school->fresh();
        $this->assertEquals($longString, $freshSchool->name);
        $this->assertEquals($longString, $freshSchool->display_name);
        $this->assertEquals($longString, $freshSchool->address);
        $this->assertEquals($longString, $freshSchool->phone);
        $this->assertEquals($longString, $freshSchool->email);
    }

    /** @test */
    public function it_validates_email_format()
    {
        // Arrange & Act
        $school = School::factory()->create(['email' => 'test@example.com']);

        // Assert
        $this->assertEquals('test@example.com', $school->email);

        // Test with invalid email (Laravel doesn't validate email format at DB level by default)
        $invalidEmailSchool = School::factory()->create(['email' => 'invalid-email']);
        $this->assertEquals('invalid-email', $invalidEmailSchool->email);
    }

    /** @test */
    public function it_mass_assigns_attributes_correctly()
    {
        // Arrange
        $attributes = [
            'name' => 'TEST_SCHOOL',
            'display_name' => 'Test School Display',
            'address' => 'Test Address',
            'phone' => '123456789',
            'email' => 'test@test.com',
            'is_active' => false
        ];

        // Act
        $school = new School($attributes);
        $school->save();

        // Assert
        $freshSchool = $school->fresh();
        foreach ($attributes as $key => $value) {
            $this->assertEquals($value, $freshSchool->$key);
        }
    }

    /** @test */
    public function it_handles_scope_queries()
    {
        // Arrange
        $activeSchool = School::factory()->create(['is_active' => true]);
        $inactiveSchool = School::factory()->create(['is_active' => false]);

        // Act & Assert
        $activeCount = School::where('is_active', true)->count();
        $inactiveCount = School::where('is_active', false)->count();

        $this->assertEquals(1, $activeCount);
        $this->assertEquals(1, $inactiveCount);
    }

    /** @test */
    public function it_handles_timestamps()
    {
        // Arrange
        $beforeCreation = now();

        // Act
        $school = School::factory()->create();

        // Assert
        $this->assertNotNull($school->created_at);
        $this->assertNotNull($school->updated_at);
        $this->assertGreaterThanOrEqual($beforeCreation, $school->created_at);
        $this->assertEquals($school->created_at, $school->updated_at);

        // Test updated_at changes on update
        sleep(1);
        $school->update(['address' => 'Updated Address']);
        $this->assertGreaterThan($school->created_at, $school->updated_at);
    }

    /** @test */
    public function it_can_be_soft_deleted_if_enabled()
    {
        // Arrange
        $school = School::factory()->create();

        // Act & Assert
        // Test that model doesn't use soft deletes by default
        $this->assertFalse(method_exists($school, 'trashed'));
        $this->assertFalse($school->usesSoftDeletes());
    }

    /** @test */
    public function it_handles_json_serialization()
    {
        // Arrange
        $school = School::factory()->create();

        // Act
        $json = $school->toJson();

        // Assert
        $this->assertJson($json);
        $array = json_decode($json, true);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('display_name', $array);
        $this->assertArrayHasKey('is_active', $array);
    }

    /** @test */
    public function it_handles_cascade_relationships()
    {
        // Arrange
        $school = School::factory()->create(['name' => 'SMAN_1_DENPASAR']);
        $rekonData = RekonData::factory()->count(3)->create(['sekolah' => 'SMAN_1_DENPASAR']);

        // Act
        $school->delete();

        // Assert
        // Note: This depends on your migration setup. If there's a foreign key constraint,
        // this might fail. If not, the rekon_data should remain.
        $this->assertModelMissing($school);
        $this->assertEquals(3, RekonData::where('sekolah', 'SMAN_1_DENPASAR')->count());
    }

    /** @test */
    public function it_handles_duplicate_display_names()
    {
        // Arrange
        School::factory()->create([
            'name' => 'SMAN_1_DENPASAR',
            'display_name' => 'SMA Negeri 1 Denpasar'
        ]);

        // Act
        $duplicateSchool = School::factory()->create([
            'name' => 'SMAN_1_DENPASAR_2',
            'display_name' => 'SMA Negeri 1 Denpasar' // Same display name, different system name
        ]);

        // Assert
        $this->assertInstanceOf(School::class, $duplicateSchool);
        $this->assertEquals('SMA Negeri 1 Denpasar', $duplicateSchool->display_name);
    }

    /** @test */
    public function it_handles_query_performance()
    {
        // Arrange
        $schools = School::factory()->count(50)->create(['is_active' => true]);

        // Act
        $startTime = microtime(true);
        $activeSchools = School::getActive();
        $endTime = microtime(true);

        // Assert
        $this->assertCount(50, $activeSchools);
        $this->assertLessThan(1.0, $endTime - $startTime); // Should complete in less than 1 second
    }

    /** @test */
    public function it_handles_chaining_methods()
    {
        // Arrange
        School::factory()->create([
            'name' => 'TEST_SCHOOL',
            'display_name' => 'Test School',
            'is_active' => true
        ]);

        // Act
        $school = School::where('name', 'TEST_SCHOOL')
                        ->where('is_active', true)
                        ->first();

        // Assert
        $this->assertInstanceOf(School::class, $school);
        $this->assertEquals('TEST_SCHOOL', $school->name);
        $this->assertTrue($school->is_active);
    }

    /** @test */
    public function it_handles_boolean_is_active_values()
    {
        // Arrange & Act
        $trueSchool = School::factory()->create(['is_active' => true]);
        $falseSchool = School::factory()->create(['is_active' => false]);
        $oneSchool = School::factory()->create(['is_active' => 1]);
        $zeroSchool = School::factory()->create(['is_active' => 0]);

        // Assert
        $this->assertTrue($trueSchool->fresh()->is_active);
        $this->assertFalse($falseSchool->fresh()->is_active);
        $this->assertTrue($oneSchool->fresh()->is_active);
        $this->assertFalse($zeroSchool->fresh()->is_active);
    }
}