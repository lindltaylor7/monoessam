<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un quebrado (PO) puede consolidar varias programaciones guardadas a la vez. purchase_orders
 * conserva weekly_program_id (la primera programación) por compatibilidad; la lista completa
 * vive en este pivote, que se rellena con las órdenes ya existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_weekly_program', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('weekly_program_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['purchase_order_id', 'weekly_program_id'], 'po_weekly_program_unique');
        });

        $now = now();
        DB::table('purchase_orders')
            ->whereNotNull('weekly_program_id')
            ->whereIn('weekly_program_id', DB::table('weekly_programs')->select('id'))
            ->orderBy('id')
            ->select('id', 'weekly_program_id')
            ->chunk(500, function ($orders) use ($now) {
                DB::table('purchase_order_weekly_program')->insert($orders->map(fn ($o) => [
                    'purchase_order_id' => $o->id,
                    'weekly_program_id' => $o->weekly_program_id,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_weekly_program');
    }
};
