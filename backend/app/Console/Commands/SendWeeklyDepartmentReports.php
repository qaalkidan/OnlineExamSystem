<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\DepartmentNotificationService;

class SendWeeklyDepartmentReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'department:send-weekly-reports {--force : Force send reports even if already sent this week}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and send weekly department executive reports to authorized Department Heads';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $force = (bool)$this->option('force');
        $this->info("Scanning for Department Heads with active weekly report notifications...");

        $deptHeads = User::where('role', 'dept_head')->with('department')->get();
        if ($deptHeads->isEmpty()) {
            $this->warn("No Department Heads found.");
            return Command::SUCCESS;
        }

        $sentCount = 0;
        $skippedCount = 0;

        foreach ($deptHeads as $deptHead) {
            $prefs = $deptHead->notification_preferences;
            if (!is_array($prefs) || empty($prefs['weeklyReport'])) {
                $this->line("Skipping {$deptHead->name}: weekly report toggle is disabled.");
                $skippedCount++;
                continue;
            }

            $result = DepartmentNotificationService::sendWeeklyDepartmentReport($deptHead, $force);
            if ($result['success']) {
                $this->info("✓ Report dispatched to {$deptHead->name} ({$result['target']})");
                $sentCount++;
            } elseif (($result['skipped'] ?? false) === true) {
                $this->line("Skipping {$deptHead->name}: {$result['message']}");
                $skippedCount++;
            } else {
                $this->error("✗ Failed sending report to {$deptHead->name}: {$result['error']}");
            }
        }

        $this->info("Weekly department report dispatch complete: {$sentCount} sent, {$skippedCount} skipped.");
        return Command::SUCCESS;
    }
}
