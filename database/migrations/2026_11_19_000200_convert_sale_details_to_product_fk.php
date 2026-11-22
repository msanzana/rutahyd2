<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convierte un sale_details ya creado con presentation_id al nuevo product_id.
 * No borra ventas ni productos. La migracion inicial nueva ya usa product_id.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('sale_details') || !Schema::hasColumn('sale_details', 'presentation_id')) return;

        if (!Schema::hasColumn('sale_details', 'product_id')) {
            Schema::table('sale_details', function (Blueprint $table): void {
                $table->unsignedBigInteger('product_id')->nullable()->after('sale_note_item_id');
            });
        }

        // Mapeo de la presentacion al producto real del catalogo.
        DB::statement('UPDATE sale_details sd INNER JOIN product_presentations pp
            ON pp.id = sd.presentation_id
            SET sd.product_id = pp.product_id WHERE sd.product_id IS NULL');

        if (DB::table('sale_details')->whereNull('product_id')->exists()) {
            throw new RuntimeException('Hay detalles sin producto mapeable. No se eliminan columnas anteriores.');
        }

        // Conservar datos historicos antes de retirar sus columnas.
        if (Schema::hasColumn('sale_details', 'name')) {
            DB::table('sale_details')->select([
                'id','item_code_type','item_code','name','description','extra_payload',
            ])->orderBy('id')->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $extras = is_string($row->extra_payload)
                        ? json_decode($row->extra_payload, true) : ($row->extra_payload ?? []);
                    if (!is_array($extras)) {
                        throw new RuntimeException('extra_payload contiene JSON invalido en la linea '.$row->id);
                    }
                    if (!isset($extras['legacy_product_snapshot'])) {
                        $extras['legacy_product_snapshot'] = [
                            'item_code_type'=>$row->item_code_type,
                            'item_code'=>$row->item_code,
                            'name'=>$row->name,
                            'description'=>$row->description,
                        ];
                        DB::table('sale_details')->where('id',$row->id)
                          ->update(['extra_payload'=>json_encode($extras,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
                    }
                }
            });
        }

        Schema::table('sale_details', function (Blueprint $table): void {
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
        });
        // La migracion ya puede reintentarse sin duplicar la FK.
        $hasFk = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME','sale_details')
            ->where('COLUMN_NAME','product_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();
        if (!$hasFk) {
            Schema::table('sale_details', function (Blueprint $table): void {
                $table->foreign('product_id','fk_sale_detail_product')
                      ->references('id')->on('products')->restrictOnDelete();
            });
        }

        $oldFk = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME','sale_details')
            ->where('COLUMN_NAME','presentation_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')->value('CONSTRAINT_NAME');
        if ($oldFk) {
            Schema::table('sale_details', function (Blueprint $table) use ($oldFk): void {
                $table->dropForeign($oldFk);
            });
        }

        $toDrop=['presentation_id','item_code_type','item_code','name','description'];
        foreach ($toDrop as $column) {
            if (Schema::hasColumn('sale_details',$column)) {
                Schema::table('sale_details', fn(Blueprint $table) => $table->dropColumn($column));
            }
        }
    }

    public function down(): void
    {
        throw new LogicException('Rollback no automatico: los datos historicos fueron respaldados en extra_payload; restaurarlos requiere un proceso revisado.');
    }
};
