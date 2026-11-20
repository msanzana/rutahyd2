<?php
return [
'paths'=>['api/*'],'allowed_methods'=>['GET','POST','PUT','PATCH','DELETE','OPTIONS'],
'allowed_origins'=>array_values(array_filter([env('FRONTEND_ORIGIN')])),
'allowed_origins_patterns'=>[],
'allowed_headers'=>['Authorization','Content-Type','Accept'],
'exposed_headers'=>[],'max_age'=>3600,'supports_credentials'=>false,
];
