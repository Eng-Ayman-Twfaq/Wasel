<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('required_documents', function (Blueprint $table) {
            $table->id();
            $table->enum('user_role', ['مالك_محل']);
            $table->string('document_type');
            $table->string('document_name');
            $table->boolean('is_required')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('required_documents');
    }
};