<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('tokens_acceso')) return;
        Schema::create('tokens_acceso',function(Blueprint $t): void {
            $t->id();
            $t->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $t->char('hash_token',64)->unique();
            $t->string('nombre',80)->default('sesion_api');
            $t->dateTime('expira_en')->index();
            $t->dateTime('ultimo_uso_en')->nullable();
            $t->dateTime('creado_en');
        });
    }
    public function down(): void { Schema::dropIfExists('tokens_acceso'); }
};
