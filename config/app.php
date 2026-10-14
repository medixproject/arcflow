<?php
return [
 'name'=>env('APP_NAME','ArcFlow'),'env'=>env('APP_ENV','production'),'debug'=>(bool)env('APP_DEBUG',false),'url'=>env('APP_URL','http://localhost'),'timezone'=>'UTC','locale'=>'en','fallback_locale'=>'en','cipher'=>'AES-256-CBC','key'=>env('APP_KEY'),'previous_keys'=>array_filter(explode(',',(string)env('APP_PREVIOUS_KEYS',''))),'webhook_signing_secret'=>env('WEBHOOK_SIGNING_SECRET'),
];
