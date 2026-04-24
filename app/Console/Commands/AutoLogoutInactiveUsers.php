<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\AttendanceLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class AutoLogoutInactiveUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:auto-logout';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Logout inactive users after 30 minutes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $threshold = now()->subMinutes(300);

        $users = User::where('is_logged_in', true)
            ->where('last_active', '<=', $threshold)
            ->get();

        foreach ($users as $user) {
            $today = now()->toDateString();

            $lastLog = AttendanceLog::where('user_id', $user->id)
                ->whereDate('date', $today)
                ->whereNull('punch_out')
                ->latest()
                ->first();

            if ($lastLog) {
                $lastLog->update(['punch_out' => now()]);
            }

            // 🔹 Kill sessions if using database driver
            if (config('session.driver') === 'database') {
                DB::table('sessions')
                    ->where('user_id', $user->id)
                    ->delete();
            }

            // Update user status
            $user->update(['is_logged_in' => false]);
        }

        $this->info("Inactive users logged out and sessions killed successfully.");
    }
}
