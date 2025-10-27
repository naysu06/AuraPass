<?php

// ..._create_members_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('unique_id')->unique(); // This will be in the QR code
            $table->date('membership_expiry_date');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('members');
    }

};

    