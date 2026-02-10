<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gym_settings', function (Blueprint $table) {
            $table->id();
            
            // Kiosk & Camera
            $table->boolean('camera_mirror')->default(true);
            $table->integer('kiosk_debounce_seconds')->default(10);
            $table->boolean('strict_mode')->default(false)->comment('Require photo for entry');
            
            // Automation
            $table->integer('auto_checkout_hours')->default(12);
            
            // Notifications
            $table->boolean('email_reminders_enabled')->default(true);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_settings');
    }
};