<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EodReport;
use App\Models\Sale;
use Illuminate\Support\Facades\Mail;

class SendDailyEodReport extends Command
{
    protected $signature = 'eod:send {date?}';
    protected $description = 'Email the daily end of day report to the shop owner';

    public function handle()
    {
        $date = $this->argument('date') ?? now()->toDateString();
        $ownerEmail = env('OWNER_EMAIL');

        if (!$ownerEmail) {
            $this->error('Set OWNER_EMAIL in your .env file first.');
            return 1;
        }

        $reports = EodReport::with('staff')->whereDate('report_date', $date)->orderBy('role')->get();
        $sales = Sale::whereDate('created_at', $date)->get();
        $totalRevenue = $sales->sum('total_amount');
        $totalTransactions = $sales->count();

        Mail::send('emails.eod-report', compact('date', 'reports', 'totalRevenue', 'totalTransactions'), function ($message) use ($ownerEmail, $date) {
            $message->to($ownerEmail)->subject("Omie Store — Daily Report for {$date}");
        });

        $this->info("Report sent to {$ownerEmail}");
        return 0;
    }
}