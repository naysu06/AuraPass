<?php

// ..._create_check_ins_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('check_ins', function (Blueprint $table) {
            $table->id();
            
            // Your relation to the Member
            $table->foreignId('member_id')->constrained()->onDelete('cascade');
            
            // NEW: The time they left. 
            // Nullable because when they first scan, they haven't left yet.
            $table->timestamp('check_out_at')->nullable();
            
            // Standard timestamps:
            // 'created_at' = The exact time they Checked IN.
            // 'updated_at' = The last time this record was touched.
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('check_ins');
    }
};

