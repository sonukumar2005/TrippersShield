<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_request_id')->constrained()->onDelete('cascade');
            $table->string('policy_number')->unique(); // prevents duplicate policy numbers (#9)
            $table->timestamp('issued_at');
            $table->string('status')->default('active'); // active|cancelled
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policies');
    }
};
