<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\InvoiceBalance;

class PaymentPageController extends Controller
{
    public function show(Invoice $invoice, InvoiceBalance $balance)
    {
        $invoice->load(['payments' => fn ($query) => $query->latest('id')]);

        return view('payment', [
            'invoice' => $invoice,
            'payment' => $invoice->payments->first(),
            'payments' => $invoice->payments->take(10),
            'balance' => $balance->summarize($invoice),
        ]);
    }
}
