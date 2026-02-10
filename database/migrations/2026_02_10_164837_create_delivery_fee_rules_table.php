<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('delivery_fee_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('from_area_id');
            $table->unsignedBigInteger('to_area_id');
            $table->decimal('base_fee', 10, 2);
            $table->decimal('per_km_fee', 10, 2);
            $table->decimal('min_distance_km', 8, 2);
            $table->decimal('max_distance_km', 8, 2);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('delivery_fee_rules');
    }
};