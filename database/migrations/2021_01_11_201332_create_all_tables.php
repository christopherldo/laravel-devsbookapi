<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAllTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id');
            $table->string('email', 50);
            $table->string('password', 60);
            $table->string('salt', 64);
            $table->string('name', 50);
            $table->date('birthdate');
            $table->string('city', 50)->nullable();
            $table->string('work', 50)->nullable();
            $table->string('avatar', 50)->default('default.jpg');
            $table->string('cover', 50)->default('cover.jpg');
        });

        Schema::create('user_relations', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_from');
            $table->uuid('user_to');
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id');
            $table->uuid('id_user');
            $table->string('type', 25);
            $table->dateTime('created_at');
            $table->text('body');
        });

        Schema::create('post_likes', function (Blueprint $table) {
            $table->id();
            $table->uuid('id_post');
            $table->uuid('id_user');
            $table->dateTime('created_at');
        });

        Schema::create('post_comments', function (Blueprint $table) {
            $table->id();
            $table->uuid('id_post');
            $table->uuid('id_user');
            $table->dateTime('created_at');
            $table->text('body');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('user_relations');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('post_likes');
        Schema::dropIfExists('post_comments');
    }
}
