<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * Galería de productos almacenada exclusivamente en MariaDB (DDL25).
 * Las imágenes no se copian a /public ni se exponen en rutas sin JWT.
 */
final class FotosProductoControlador extends Controller
{
    private function producto(int $productoId): object
    {
        abort_unless(Schema::hasTable('productos_fotos'),503,
            'Falta importar el DDL25 de fotografías de productos');
        $producto=DB::table('productos')->where('id',$productoId)
            ->first(['id','sku','nombre','activo']);
        abort_unless($producto,404,'Producto inexistente');
        return $producto;
    }

    public function listar(int $productoId)
    {
        $producto=$this->producto($productoId);
        $fotos=DB::table('productos_fotos')->where('producto_id',$productoId)
            ->orderBy('posicion')->orderBy('id')
            // No seleccionar el LONGBLOB en el listado.
            ->get(['id','producto_id','sku','mime_tipo','nombre_archivo',
                   'tamano_bytes','posicion','creado_en']);
        return response()->json([
            'producto_id'=>$productoId,'sku'=>$producto->sku,
            'items'=>$fotos,'total'=>$fotos->count()
        ])->header('Cache-Control','no-store');
    }

    public function imagen(int $productoId,int $fotoId)
    {
        $this->producto($productoId);
        $foto=DB::table('productos_fotos')
            ->where('producto_id',$productoId)->where('id',$fotoId)
            ->first(['id','mime_tipo','foto']);
        abort_unless($foto,404,'Foto inexistente en este producto');
        abort_unless(in_array($foto->mime_tipo,['image/jpeg','image/png','image/webp'],true),415);
        // Descarga autenticada; no generar enlaces públicos, ni permitir sniffing.
        return response($foto->foto,200,[
            'Content-Type'=>$foto->mime_tipo,
            'Content-Disposition'=>'inline; filename="producto-'.$fotoId.'"',
            'Cache-Control'=>'private, no-store, max-age=0',
            'X-Content-Type-Options'=>'nosniff',
            'Content-Security-Policy'=>"default-src 'none'; sandbox",
        ]);
    }

    public function subir(Request $request,int $productoId)
    {
        $this->producto($productoId);
        $datos=Validator::make($request->all(),[
            'fotos'=>['required','array','min:1','max:6'],
            'fotos.*'=>['required','file','mimes:jpg,jpeg,png,webp','max:2048'],
        ])->validate();
        $files=$request->file('fotos',[]);
        abort_if(!is_array($files)||count($files)===0,422,'Selecciona fotografías válidas');
        $total=0;$preparadas=[];
        foreach($files as $file){
            abort_unless($file->isValid(),422,'La imagen no se pudo cargar');
            $size=(int)$file->getSize();
            $total+=$size;
            abort_if($size<1||$size>2097152||$total>10485760,422,
                'Cada foto admite hasta 2 MB y el conjunto hasta 10 MB');
            $mime=$file->getMimeType();
            abort_unless(in_array($mime,['image/jpeg','image/png','image/webp'],true),
                422,'Solo se permiten fotografías JPEG, PNG y WebP');
            $dimensions=@getimagesize($file->getRealPath());
            abort_unless($dimensions && in_array($dimensions[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP],true),
                422,'El archivo no es una imagen compatible');
            abort_if($dimensions[0]>8000||$dimensions[1]>8000,422,
                'La foto excede la resolución máxima de 8000 píxeles');
            $name=mb_substr(basename((string)$file->getClientOriginalName()),0,180);
            // No conservar path ni nombres controlados por el cliente en la URL.
            $preparadas[]=[
                'foto'=>file_get_contents($file->getRealPath()),
                'mime_tipo'=>$mime,'nombre_archivo'=>$name ?: 'imagen.webp',
                'tamano_bytes'=>$size,
            ];
        }
        $created=DB::transaction(function()use($productoId,$preparadas){
            $producto=DB::table('productos')->where('id',$productoId)
                ->lockForUpdate()->first(['id','sku']);
            abort_unless($producto,404,'Producto inexistente');
            $position=(int)DB::table('productos_fotos')
                ->where('producto_id',$productoId)->max('posicion');
            $ids=[];
            foreach($preparadas as $item){
                $item['producto_id']=$productoId;
                $item['sku']=$producto->sku;
                $item['posicion']=++$position;
                $ids[]=(int)DB::table('productos_fotos')->insertGetId($item);
            }
            return $ids;
        },3);
        return response()->json(['producto_id'=>$productoId,'ids'=>$created,
            'cantidad'=>count($created)],201)->header('Cache-Control','no-store');
    }

    public function eliminar(int $productoId,int $fotoId)
    {
        $this->producto($productoId);
        $affected=DB::table('productos_fotos')->where('producto_id',$productoId)
            ->where('id',$fotoId)->delete();
        abort_unless($affected,404,'Foto inexistente');
        return response()->json(['id'=>$fotoId,'eliminada'=>true])
            ->header('Cache-Control','no-store');
    }
}
