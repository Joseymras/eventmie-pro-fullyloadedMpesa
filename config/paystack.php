<?php

/*
 * This file is part of the Laravel Paystack package.
 *
 * (c) Prosper Otemuyiwa <prosperotemuyiwa@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [
    'enabled' => env('PAYSTACK_ENABLED', false),
    'publicKey' => env('PAYSTACK_PUBLIC_KEY'),
    'secretKey' => env('PAYSTACK_SECRET_KEY'),
    'paymentUrl' => env('PAYSTACK_BASE_URL', env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co')),
    'timeout' => env('PAYSTACK_TIMEOUT', 15),
    'webhookUrl' => env('PAYSTACK_WEBHOOK_URL'),
    'transferEnabled' => env('PAYSTACK_TRANSFERS_ENABLED', false),
    'merchantEmail' => env('MERCHANT_EMAIL'),
];
