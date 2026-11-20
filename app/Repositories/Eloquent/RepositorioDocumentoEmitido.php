<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\DocumentoEmitido;
use App\Repositories\Contratos\RepositorioDocumentoEmitidoInterface;
final class RepositorioDocumentoEmitido extends RepositorioEloquent implements RepositorioDocumentoEmitidoInterface
{
    public function __construct(DocumentoEmitido $modelo) { parent::__construct($modelo); }
}
