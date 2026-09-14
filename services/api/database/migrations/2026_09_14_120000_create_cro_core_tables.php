<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('public_key', 64)->unique();
            $table->boolean('recording_enabled')->default(true);
            $table->unsignedTinyInteger('sampling_rate')->default(100);
            $table->json('privacy_settings')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('project_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('domain', 255);
            $table->timestamps();
            $table->unique(['project_id', 'domain']);
        });

        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('visitor_uuid', 80);
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at');
            $table->timestamps();
            $table->unique(['project_id', 'visitor_uuid']);
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visitor_id')->constrained()->cascadeOnDelete();
            $table->string('session_uuid', 80);
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('duration')->default(0);
            $table->text('landing_page')->nullable();
            $table->text('exit_page')->nullable();
            $table->unsignedInteger('page_count')->default(0);
            $table->string('device_type', 30)->nullable();
            $table->string('browser', 60)->nullable();
            $table->string('operating_system', 60)->nullable();
            $table->unsignedSmallInteger('screen_width')->nullable();
            $table->unsignedSmallInteger('screen_height')->nullable();
            $table->string('country', 2)->nullable();
            $table->text('referrer')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->boolean('converted')->default(false);
            $table->unsignedInteger('rage_clicks')->default(0);
            $table->unsignedInteger('dead_clicks')->default(0);
            $table->timestamps();
            $table->unique(['project_id', 'session_uuid']);
            $table->index(['project_id', 'started_at']);
        });

        Schema::create('session_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->string('title')->nullable();
            $table->dateTime('entered_at');
            $table->dateTime('exited_at')->nullable();
            $table->unsignedInteger('duration')->default(0);
            $table->timestamps();
            $table->index(['project_id', 'session_id', 'entered_at']);
        });

        Schema::create('session_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained()->cascadeOnDelete();
            $table->uuid('batch_id')->unique();
            $table->unsignedInteger('sequence');
            $table->string('storage_disk', 30)->default('recordings');
            $table->text('storage_path');
            $table->unsignedInteger('event_count');
            $table->unsignedBigInteger('compressed_bytes');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->timestamps();
            $table->unique(['session_id', 'sequence']);
        });

        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained()->cascadeOnDelete();
            $table->string('event_name', 100);
            $table->text('url')->nullable();
            $table->json('properties')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamps();
            $table->index(['project_id', 'event_name', 'occurred_at']);
        });

        Schema::create('click_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->integer('x');
            $table->integer('y');
            $table->unsignedSmallInteger('viewport_width');
            $table->unsignedSmallInteger('viewport_height');
            $table->string('selector', 500)->nullable();
            $table->dateTime('occurred_at');
            $table->timestamps();
            $table->index(['project_id', 'occurred_at']);
        });

        Schema::create('scroll_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->unsignedTinyInteger('depth');
            $table->dateTime('occurred_at');
            $table->timestamps();
            $table->index(['project_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        foreach (['scroll_events', 'click_events', 'analytics_events', 'session_chunks', 'session_pages', 'sessions', 'visitors', 'project_domains', 'projects'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
