<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_requests', function (Blueprint $table) {
            $table->id();
            $table->string('agent_id');
            $table->string('client_request_id');
            $table->string('customer_name');
            $table->string('customer_email');
            $table->date('customer_dob');
            $table->string('destination'); // domestic|international|schengen
            $table->string('plan_type');   // basic|standard|premium
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('coverage_amount', 15, 2);
            $table->decimal('premium_amount', 10, 2); // snapshot at creation time
            $table->string('status')->default('pending'); // pending|submitted|issued|failed
            $table->string('insurer_policy_number')->nullable();
            $table->json('insurer_response')->nullable();
            $table->timestamps();

            // DB-level duplicate prevention (requirement #4)
            $table->unique(['agent_id', 'client_request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_requests');
    }
};
