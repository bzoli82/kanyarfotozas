<?php

namespace App\Listeners;

use App\Models\FailedLoginAttempt;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Request;

class LogFailedLoginAttempt
{
    public function handle(Failed $event): void
    {
        FailedLoginAttempt::create([
            'email' => (string) ($event->credentials['email'] ?? ''),
            'ip_hash' => hash('sha256', Request::ip() ?? ''),
        ]);
    }
}
