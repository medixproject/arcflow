<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
class PaymentController extends Controller { public function show(Request $r,Payment $payment){abort_unless($payment->invoice->merchant->user_id===$r->user()->id,404);return response()->json(['data'=>$payment]);} }
