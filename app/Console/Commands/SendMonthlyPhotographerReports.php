<?php

namespace App\Console\Commands;

use App\Mail\MonthlyPhotographerReportMail;
use App\Models\User;
use App\Services\PhotographerReport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('roadsidephoto:send-monthly-photographer-reports')]
#[Description('Havi értékesítési riport CSV melléklettel a bekapcsolt (report_monthly) fotósoknak — 1-jén 08:00 (EPIC-12)')]
class SendMonthlyPhotographerReports extends Command
{
    public function handle(PhotographerReport $reports): int
    {
        $from = now()->subMonthNoOverflow()->startOfMonth();
        $to = now()->subMonthNoOverflow()->endOfMonth();

        $photographers = User::query()
            ->where('role', User::ROLE_PHOTOGRAPHER)
            ->where('is_active', true)
            ->where('report_monthly', true)
            ->get();

        foreach ($photographers as $photographer) {
            $report = $reports->forPeriod($photographer, $from->copy(), $to->copy());
            $csv = $reports->toCsv($report['rows']);
            Mail::to($photographer->email)->send(new MonthlyPhotographerReportMail($photographer, $report, $csv));
        }

        $this->info("Havi riport elküldve: {$photographers->count()} fotós");

        return self::SUCCESS;
    }
}
