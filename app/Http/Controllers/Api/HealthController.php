<?php
namespace App\Http\Controllers\Api;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Http;
class HealthController { public function __invoke(): JsonResponse { $db='ok';$redis='ok';$arc='not_configured';try{DB::select('select 1');}catch(\Throwable){$db='error';}if(config('database.redis')){try{Redis::ping();}catch(\Throwable){$redis='error';}}else{$redis='not_configured';}if(config('arc.rpc_url')){try{$r=Http::timeout(5)->post(config('arc.rpc_url'),['jsonrpc'=>'2.0','id'=>1,'method'=>'eth_blockNumber','params'=>[]]);$arc=$r->successful()?'ok':'error';}catch(\Throwable){$arc='error';}}return response()->json(['status'=>$db==='ok'&&$redis!=='error'&&$arc!=='error'?'ok':'degraded','database'=>$db,'redis'=>$redis,'arc'=>$arc]);} }
