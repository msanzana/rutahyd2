<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Support/RutChileno.php';
use App\Support\RutChileno;
$tests=[
 ['11111111-1','11111111-1'],
 ['11.111.111-1','11111111-1'],
 ['11111111-0',null],
 ['11111111-K',null],
 ['abc',null],
 ['',null],
 ['111111111111111',null],
];
foreach($tests as [$input,$expected]){
  $received=RutChileno::normalizar($input);
  if($received!==$expected){
    fwrite(STDERR,"Fallo normalización de RUT.\n");exit(1);
  }
}
echo "OK: normalización y dígito verificador.\n";
