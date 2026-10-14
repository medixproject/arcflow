<?php
namespace App\Http\Middleware;
use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class ApiKeyAuth { public function handle(Request $request,Closure $next){$raw=$request->bearerToken();if(!$raw||!str_starts_with($raw,'af_'))return response()->json(['message'=>'Unauthorized'],401);$key=ApiKey::where('key_hash',hash('sha256',$raw))->whereNull('revoked_at')->with('merchant.user')->first();if(!$key)return response()->json(['message'=>'Unauthorized'],401);$key->forceFill(['last_used_at'=>now()])->save();Auth::setUser($key->merchant->user);$request->attributes->set('merchant',$key->merchant);return $next($request);} }
