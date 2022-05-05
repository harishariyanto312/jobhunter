<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bot_users', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id');
            $table->boolean('is_authenticated')->default(false);
            $table->string('current_operation')->default('idle');
            $table->text('personal_data')->nullable()->default('{}');
            $table->text('documents')->nullable()->default('[]');
            $table->string('documents_url')->nullable();
            $table->text('mem')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bot_users');
    }
};
