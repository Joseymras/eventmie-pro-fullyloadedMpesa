<?php

namespace App\Jobs;

use App\Services\PaystackService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPaystackWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;

    protected $reference;
    protected $eventName;

    public function __construct(string $reference, string $eventName)
    {
        $this->reference = $reference;
        $this->eventName = $eventName;
    }

    public function handle(PaystackService $paystack): void
    {
        $paystack->processWebhook($this->reference, $this->eventName);
    }
}
