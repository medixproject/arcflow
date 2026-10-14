<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class InvoiceAddressReservation
{
    /**
     * Permanently reserve an address for a network.
     *
     * Must be called inside the same database transaction as invoice creation.
     * Returns false if the normalized address is already reserved.
     */
    public function reserve(string $network, string $address): bool
    {
        $normalized = strtolower(trim($address));
        $now = now();

        $inserted = DB::table('invoice_address_reservations')->insertOrIgnore([
            'network' => $network,
            'normalized_address' => $normalized,
            'first_invoice_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $inserted === 1;
    }

    /**
     * Associate a newly created invoice with its reservation.
     * Never overwrite an existing historical invoice association.
     */
    public function associateInvoice(string $network, string $address, int $invoiceId): void
    {
        DB::table('invoice_address_reservations')
            ->where('network', $network)
            ->where('normalized_address', strtolower(trim($address)))
            ->whereNull('first_invoice_id')
            ->update([
                'first_invoice_id' => $invoiceId,
                'updated_at' => now(),
            ]);
    }
}
