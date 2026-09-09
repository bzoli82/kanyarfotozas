<?php

namespace App\Console\Commands;

use App\Mail\WeeklyPhotographerReportMail;
use App\Models\User;
use App\Services\PhotographerReport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('roadsidephoto:send-weekly-photographer-reports')]
#[Description('Heti értékesítési riport a bekapcsolt (report_weekly) fotósoknak — hétfő 08:00 (EPIC-12)')]
class SendWeeklyPhotographerReports extends Command
{
    public function handle(PhotographerReport $reports): int
    {
        $from = now()->subWeek()->startOfDay();
        $to = now()->subDay()->endOfDay();

        $photographers = User::query()
            ->where('role', User::ROLE_PHOTOGRAPHER)
            ->where('is_active', true)
            ->where('report_weekly', true)
            ->get();

        foreach ($photographers as $photographer) {
            $report = $reports->forPeriod($photographer, $from->copy(), $to->copy());
            Mail::to($photographer->email)->send(new WeeklyPhotographerReportMail($photographer, $report));
        }

        $this->info("Heti riport elküldve: {$photographers->count()} fotós");

        return self::SUCCESS;
    }
}
