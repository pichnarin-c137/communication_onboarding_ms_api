<?php

namespace App\Console\Commands;

use App\Jobs\SendDailyDigest;
use App\Models\User;
use Illuminate\Console\Command;

class SendDailyDigestCommand extends Command
{
    protected $signature = 'reports:daily-digest';

    protected $description = 'Dispatch daily digest notifications to all admins and sales.';

    public function handle(): int
    {
        $users = User::whereHas('role', fn ($q) => $q->whereIn('role', ['admin', 'sale']))
            ->get(['id']);

        foreach ($users as $user) {
            SendDailyDigest::dispatch($user->id);
        }

        $this->info("Dispatched daily digest for {$users->count()} user(s).");

        return self::SUCCESS;
    }
}
