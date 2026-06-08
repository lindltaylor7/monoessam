<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_dispatches', function (Blueprint $table) {
            $table->id();

            // Polymorphic equipment reference
            $table->string('equipable_type');
            $table->unsignedBigInteger('equipable_id');
            $table->index(['equipable_type', 'equipable_id'], 'equip_dispatch_morphable_index');

            // Origin warehouse
            $table->foreignId('origin_headquarter_id')
                ->nullable()
                ->constrained('headquarters')
                ->nullOnDelete();

            // Destination (cafe, unit, mine, or headquarter)
            $table->string('destination_type'); // 'cafe' | 'unit' | 'mine' | 'headquarter'
            $table->unsignedBigInteger('destination_id');

            // Responsible person at destination
            $table->foreignId('staff_id')
                ->nullable()
                ->constrained('staff')
                ->nullOnDelete();

            $table->text('description')->nullable();
            $table->string('dispatch_number')->unique();
            $table->enum('status', ['active', 'returned'])->default('active');

            $table->timestamp('dispatched_at')->useCurrent();
            $table->timestamp('returned_at')->nullable();

            $table->foreignId('dispatched_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_dispatches');
    }
};
