<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoice_address_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('network', 64);
            $table->string('normalized_address', 42);
            // Historical duplicates remain in invoices; this is only a reservation registry.
            $table->unsignedBigInteger('first_invoice_id')->nullable();
            $table->timestamps();

            $table->unique(
                ['network', 'normalized_address'],
                'invoice_address_reservations_network_address_unique'
            );
        });

        $now = now();

        DB::table('invoices')
            ->selectRaw('network, LOWER(payment_address) AS normalized_address, MIN(id) AS first_invoice_id')
            ->groupBy('network')
            ->groupByRaw('LOWER(payment_address)')
            ->orderBy('network')
            ->orderBy('normalized_address')
            ->chunk(500, function ($rows) use ($now) {
                $records = [];
                foreach ($rows as $row) {
                    $records[] = [
                        'network' => $row->network,
                        'normalized_address' => $row->normalized_address,
                        'first_invoice_id' => $row->first_invoice_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($records) {
                    DB::table('invoice_address_reservations')->insertOrIgnore($records);
                }
            });

        DB::table('invoice_address_allocations')
            ->selectRaw('network, LOWER(address) AS normalized_address')
            ->distinct()
            ->orderBy('network')
            ->orderBy('normalized_address')
            ->chunk(500, function ($rows) use ($now) {
                $records = [];
                foreach ($rows as $row) {
                    $records[] = [
                        'network' => $row->network,
                        'normalized_address' => $row->normalized_address,
                        'first_invoice_id' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($records) {
                    DB::table('invoice_address_reservations')->insertOrIgnore($records);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_address_reservations');
    }
};
