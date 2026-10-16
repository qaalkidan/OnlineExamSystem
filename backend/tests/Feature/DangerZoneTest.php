<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DangerZoneTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name'     => 'Super Admin',
            'email'    => 'admin@wollo.edu.et',
            'role'     => 'admin',
            'status'   => 'active',
        ]);
    }

    public function test_super_admin_can_remove_custom_profile_photo()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('avatar.jpg');
        $path = $file->store('profile_pictures', 'public');

        $this->admin->profile_picture = $path;
        $this->admin->save();

        Sanctum::actingAs($this->admin);

        $response = $this->deleteJson('/api/v1/user/profile-photo');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Profile photo removed successfully.',
                'profile_picture' => null,
                'profile_picture_url' => null,
            ]);

        $this->assertNull($this->admin->fresh()->profile_picture);
        Storage::disk('public')->assertMissing($path);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->admin->id,
            'module'  => 'Users',
            'type'    => 'Updated',
        ]);
    }

    public function test_super_admin_can_reset_system_settings_to_institutional_defaults()
    {
        // Customize some settings first
        SystemSetting::updateOrCreate(['key' => 'universityName'], ['value' => 'Old University']);
        SystemSetting::updateOrCreate(['key' => 'systemTitle'], ['value' => 'Old System']);
        SystemSetting::updateOrCreate(['key' => 'institutionCode'], ['value' => 'OLD']);
        SystemSetting::updateOrCreate(['key' => 'maintenanceMode'], ['value' => 'true']);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/admin/settings/reset-defaults');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'System settings reset to default successfully',
            ]);

        $this->assertDatabaseHas('system_settings', [
            'key'   => 'universityName',
            'value' => 'Wollo University',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'key'   => 'systemTitle',
            'value' => 'Online Examination System',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'key'   => 'institutionCode',
            'value' => 'WU',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'key'   => 'maintenanceMode',
            'value' => 'false',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->admin->id,
            'module'  => 'System Settings',
            'type'    => 'Reset',
        ]);
    }
}
