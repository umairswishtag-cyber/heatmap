<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('click_events', function (Blueprint $table) {
            $table->integer('page_x')->nullable()->after('y');
            $table->integer('page_y')->nullable()->after('page_x');
            $table->unsignedInteger('document_height')->nullable()->after('viewport_height');
        });

        Schema::create('funnels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->json('steps');
            $table->unsignedInteger('window_minutes')->default(30);
            $table->timestamps();
            $table->index(['project_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnels');

        Schema::table('click_events', function (Blueprint $table) {
            $table->dropColumn(['page_x', 'page_y', 'document_height']);
        });
    }
};
