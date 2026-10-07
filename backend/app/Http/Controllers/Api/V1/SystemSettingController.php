<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\LogActivity;
use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemSettingController extends Controller
{
    /**
     * Get all system settings as key-value pairs with sensible defaults.
     */
    public function index(): JsonResponse
    {
        $defaults = [
            'universityName'   => 'Wollo University',
            'systemTitle'      => 'Online Examination System',
            'institutionCode'  => 'WU',
            'campusLocation'   => 'Dessie & Kombolcha, Ethiopia',
            'supportEmail'     => 'admin@wollo.edu.et',
            'timezone'         => 'Africa/Addis_Ababa',
            'language'         => 'English',
            'academicYear'     => '2026',
            'semester'         => 'Second Semester',
            'maintenanceMode'  => 'false',
        ];

        $dbSettings = SystemSetting::pluck('value', 'key')->toArray();
        $merged = array_merge($defaults, $dbSettings);

        return response()->json($merged);
    }

    /**
     * Store or update system settings.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'academicYear'     => 'sometimes|nullable|string|max:50',
            'semester'         => 'sometimes|nullable|string|max:50',
            'universityName'   => 'sometimes|nullable|string|max:150',
            'systemTitle'      => 'sometimes|nullable|string|max:150',
            'institutionCode'  => 'sometimes|nullable|string|max:20',
            'campusLocation'   => 'sometimes|nullable|string|max:200',
            'supportEmail'     => 'sometimes|nullable|email|max:100',
            'timezone'         => 'sometimes|nullable|string|max:100',
            'language'         => 'sometimes|nullable|string|max:50',
            'maintenanceMode'  => 'sometimes|nullable|string|in:true,false',
        ]);

        foreach ($validated as $key => $value) {
            if ($value !== null) {
                SystemSetting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
            }
        }

        LogActivity::record(
            'Updated',
            'System Settings',
            'Updated university system settings configuration'
        );

        $dbSettings = SystemSetting::pluck('value', 'key')->toArray();

        return response()->json([
            'message'  => 'Settings updated successfully',
            'settings' => $dbSettings,
        ]);
    }

    /**
     * Get real runtime system information.
     */
    public function systemInfo(): JsonResponse
    {
        $dbStatus = 'Operational';
        $dbLatency = '0ms';
        $dbVersion = 'Unknown';
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $dbLatency = round((microtime(true) - $start) * 1000, 1) . 'ms';
            $versionRow = DB::select('SELECT VERSION() as v');
            $dbVersion = $versionRow[0]->v ?? 'MySQL';
        } catch (\Throwable $e) {
            $dbStatus = 'Unavailable';
            $dbLatency = 'Timeout';
        }

        $storageWritable = is_writable(storage_path('app/public'));

        return response()->json([
            'application_name' => config('app.name', 'Wollo University Online Examination System'),
            'app_environment'  => app()->environment(),
            'app_debug'        => config('app.debug') ? 'Enabled' : 'Disabled',
            'php_version'      => PHP_VERSION,
            'laravel_version'  => app()->version(),
            'server_software'  => request()->server('SERVER_SOFTWARE', 'PHP Built-in Server'),
            'server_time'      => now()->toIso8601String(),
            'server_timezone'  => config('app.timezone', 'Africa/Addis_Ababa'),
            'database_driver'  => config('database.default'),
            'database_status'  => $dbStatus,
            'database_latency' => $dbLatency,
            'database_version' => $dbVersion,
            'storage_writable' => $storageWritable ? 'Writable' : 'Read-Only / Degraded',
            'cache_driver'     => config('cache.default'),
            'mail_driver'      => config('mail.default'),
        ]);
    }

    /**
     * Reset general system settings to institutional defaults.
     */
    public function resetDefaults(): JsonResponse
    {
        $defaults = [
            'universityName'   => 'Wollo University',
            'systemTitle'      => 'Online Examination System',
            'institutionCode'  => 'WU',
            'campusLocation'   => 'Dessie & Kombolcha, Ethiopia',
            'supportEmail'     => 'admin@wollo.edu.et',
            'timezone'         => 'Africa/Addis_Ababa',
            'language'         => 'English',
            'maintenanceMode'  => 'false',
        ];

        foreach ($defaults as $key => $value) {
            SystemSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        LogActivity::record(
            'Reset',
            'System Settings',
            'Reset general system settings to institutional defaults'
        );

        $dbSettings = SystemSetting::pluck('value', 'key')->toArray();

        return response()->json([
            'message'  => 'System settings reset to default successfully',
            'settings' => $dbSettings,
        ]);
    }
}

