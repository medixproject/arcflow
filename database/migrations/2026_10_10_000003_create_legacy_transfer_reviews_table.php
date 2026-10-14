<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('legacy_transfer_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('network', 64);
            $table->string('token_contract', 80);
            $table->string('from_address', 80);
            $table->string('to_address', 80);
            $table->decimal('amount', 30, 6);
            $table->string('transaction_hash', 100);
            $table->unsignedBigInteger('log_index');
            $table->unsignedBigInteger('block_number')->nullable();
            $table->unsignedInteger('confirmations')->default(0);
            $table->json('candidate_invoice_ids');
            $table->string('review_reason', 64);
            $table->timestamps();
            $table->unique(
                ['network', 'transaction_hash', 'log_index'],
                'legacy_review_event_unique'
            );
            $table->index(['network', 'to_address']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('legacy_transfer_reviews');
    }
};
