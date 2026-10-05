<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaystackTransactionsTable extends Migration
{
    public function up()
    {
        Schema::create('paystack_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('order_id')->nullable()->index();
            $table->unsignedBigInteger('booking_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->string('channel', 32)->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->json('gateway_response')->nullable();
            $table->string('masked_phone', 16)->nullable();
            $table->json('webhook_payload')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('paystack_transactions');
    }
}
