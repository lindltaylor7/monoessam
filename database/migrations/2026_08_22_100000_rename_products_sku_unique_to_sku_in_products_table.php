<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'products_sku_unique') && !Schema::hasColumn('products', 'sku')) {
            Schema::table('products', function (Blueprint $table) {
                $table->renameColumn('products_sku_unique', 'sku');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'sku') && !Schema::hasColumn('products', 'products_sku_unique')) {
            Schema::table('products', function (Blueprint $table) {
                $table->renameColumn('sku', 'products_sku_unique');
            });
        }
    }
};
