<?php
return ['default'=>env('CACHE_STORE','database'),'stores'=>['database'=>['driver'=>'database','table'=>'cache','connection'=>null],'array'=>['driver'=>'array','serialize'=>false]],'prefix'=>env('CACHE_PREFIX','arcflow_cache')];
