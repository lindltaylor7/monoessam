<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dosifications', function (Blueprint $table) {
            $table->decimal('magnesium', 8, 2)->nullable()->after('potassium');
            $table->decimal('zinc', 8, 2)->nullable()->after('magnesium');
            $table->decimal('selenium', 8, 2)->nullable()->after('zinc');
            $table->decimal('a_folic', 8, 2)->nullable()->after('selenium');
            $table->decimal('v_b6', 8, 2)->nullable()->after('a_folic');
            $table->decimal('v_e', 8, 2)->nullable()->after('v_b6');
            $table->decimal('v_b12', 8, 2)->nullable()->after('v_e');
            $table->decimal('v_b9', 8, 2)->nullable()->after('v_b12');
            $table->decimal('iodine', 8, 2)->nullable()->after('v_b9');
            $table->decimal('cholesterol', 8, 2)->nullable()->after('iodine');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dosifications', function (Blueprint $table) {
            $table->dropColumn([
                'magnesium',
                'zinc',
                'selenium',
                'a_folic',
                'v_b6',
                'v_e',
                'v_b12',
                'v_b9',
                'iodine',
                'cholesterol'
            ]);
        });
    }
};
