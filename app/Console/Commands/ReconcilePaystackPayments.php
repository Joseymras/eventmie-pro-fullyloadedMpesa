<?php

namespace App\Console\Commands;

use App\Models\PaystackTransaction;
use App\Services\PaystackService;
use Illuminate\Console\Command;

class ReconcilePaystackPayments extends Command
{
    protected $signature = 'paystack:reconcile {--limit=50}';
    protected $description = 'Verify pending Paystack payments and expire stale attempts.';

    public function handle(PaystackService $paystack): int
    {
        $checked = 0;
        $expired = 0;

        PaystackTransaction::where('status', 'pending')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '<=', now());
            })
            ->limit(max(1, (int) $this->option('limit')))
            ->get()
            ->each(function (PaystackTransaction $transaction) use ($paystack, &$checked, &$expired) {
                $checked++;

                try {
                    $result = $paystack->verifyTransaction($transaction->reference);
                    if (!$result['verified']) {
                        $transaction->refresh();
                        if ($transaction->status === 'pending' && $transaction->expires_at && $transaction->expires_at->isPast()) {
                            $transaction->update(['status' => 'expired']);
                            $expired++;
                        }
                    }
                } catch (\Illuminate\Http\Client\ConnectionException|\RuntimeException $exception) {
                    $this->warn('A pending payment could not be checked; it will be retried on the next run.');
                }
            });

        $this->info(sprintf('Checked %d pending payment(s); expired %d stale attempt(s).', $checked, $expired));

        return self::SUCCESS;
    }
}
