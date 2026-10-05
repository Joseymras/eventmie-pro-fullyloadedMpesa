<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

class PaystackPreflight extends Command
{
    protected $signature = 'wazi:preflight';
    protected $description = 'Check production readiness for WaziEvents and Paystack.';

    public function handle(): int
    {
        $checks = [];
        $checks['Paystack enabled'] = (bool) config('paystack.enabled');
        $checks['Paystack public key'] = config('paystack.publicKey') !== null && config('paystack.publicKey') !== '';
        $checks['Paystack secret key'] = config('paystack.secretKey') !== null && config('paystack.secretKey') !== '';

        try {
            DB::connection()->getPdo();
            $checks['Database connection'] = true;
        } catch (\Throwable $exception) {
            $checks['Database connection'] = false;
        }

        $checks['Storage writable'] = is_writable(storage_path())
            && is_writable(storage_path('framework/cache'));
        $checks['Webhook route registered'] = Route::has('paystack.webhook');

        if ($checks['Webhook route registered']) {
            $webhookUrl = config('paystack.webhookUrl') ?: route('paystack.webhook', [], true);

            try {
                $response = Http::timeout(5)->post($webhookUrl, ['preflight' => true]);
                $checks['Webhook route reachable'] = in_array($response->status(), [400, 403, 405, 419, 422], true);
            } catch (\Throwable $exception) {
                $checks['Webhook route reachable'] = false;
            }
        } else {
            $checks['Webhook route reachable'] = false;
        }

        foreach ($checks as $name => $passed) {
            $this->line(sprintf('%s %s', $passed ? '<info>PASS</info>' : '<error>FAIL</error>', $name));
        }

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
