<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('merchant_derivation_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->string('network', 64);
            $table->text('encrypted_xpub');
            $table->string('derivation_path', 128)->default("m/44'/60'/0'/0");
            $table->unsignedBigInteger('next_index')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['merchant_id', 'network']);
            $table->index(['network', 'is_active']);
        });

        Schema::create('invoice_address_allocations', function (Blueprint $table) {
            $table->id();
            // Intentionally no cascading FK: allocation history must survive invoice/merchant deletion.
            $table->unsignedBigInteger('invoice_id')->unique();
            $table->unsignedBigInteger('merchant_id')->index();
            $table->unsignedBigInteger('config_id')->index();
            $table->string('network', 64);
            $table->string('address', 42);
            $table->unsignedBigInteger('derivation_index');
            $table->timestamps();
            $table->unique(['network', 'address']);
            $table->unique(['config_id', 'derivation_index']);
            $table->index(['network', 'merchant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_address_allocations');
        Schema::dropIfExists('merchant_derivation_configs');
    }
};
