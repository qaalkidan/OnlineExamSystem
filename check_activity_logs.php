<?php
require 'backend/vendor/autoload.php';
$app = require_once 'backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$count = App\Models\ActivityLog::count();
echo "Total Activity Logs in DB: {$count}\n";

$logs = App\Models\ActivityLog::with('user')->latest()->take(10)->get();
foreach ($logs as $l) {
    echo "#{$l->id} | {$l->created_at} | User: {$l->user?->name} ({$l->user?->email}) | Role: {$l->actor_role} | Dept: {$l->department_id} | Action: {$l->action} | Type: {$l->type} | Module: {$l->module} | Status: {$l->log_status} | IP: {$l->ip_address}\n";
    echo "   Details: {$l->details}\n";
}
