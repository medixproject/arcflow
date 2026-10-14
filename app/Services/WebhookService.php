<?php
namespace App\Services;
use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
class WebhookService {
 public function dispatch(Invoice $invoice,string $event,array $extra=[]): void {
  $endpoint=$invoice->merchant->webhookEndpoints()->where('active',true)->first(); if(!$endpoint) return;
  $payload=['id'=>'evt_'.Str::lower(Str::random(24)),'type'=>$event,'created_at'=>now()->toISOString(),'data'=>array_merge(['invoice_id'=>$invoice->public_id,'status'=>$invoice->status,'amount'=>(string)$invoice->amount,'currency'=>$invoice->currency,'network'=>$invoice->network],$extra)];
  $body=json_encode($payload,JSON_UNESCAPED_SLASHES); $secret=(string) config('app.webhook_signing_secret'); if($secret===''){logger()->critical('WEBHOOK_SIGNING_SECRET is not set; webhook not sent.',['event'=>$event,'invoice'=>$invoice->public_id]);return;} $sig=hash_hmac('sha256',$body,$secret);
  $delivery=$endpoint->deliveries()->create(['event_type'=>$event,'payload'=>$payload,'signature'=>$sig]);
  for($attempt=1;$attempt<=5;$attempt++){try{$r=Http::timeout(10)->withHeaders(['Content-Type'=>'application/json','X-ArcFlow-Signature'=>$sig])->withBody($body,'application/json')->post($endpoint->url);$delivery->update(['attempts'=>$attempt,'status_code'=>$r->status(),'last_attempt_at'=>now()]);if($r->successful()){$delivery->update(['delivered_at'=>now()]);break;}}catch(\Throwable $e){$delivery->update(['attempts'=>$attempt,'last_attempt_at'=>now()]);} usleep((int)(250000*2**($attempt-1)));}
 }
}
