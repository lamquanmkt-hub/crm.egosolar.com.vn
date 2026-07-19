<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
	    Schema::create('crm_customers', function (Blueprint $table) {
		    $table->id();
		    $table->string('name', 255);
		    $table->string('phone', 50)->nullable();
		    $table->string('facebook_name', 255)->nullable();
		    $table->string('facebook_link', 511)->nullable();
		    $table->string('zalo_id', 255)->nullable();
		    $table->string('customer_type', 100)->nullable();
		    $table->foreignId('region_id')->nullable()->constrained('crm_regions')->nullOnDelete();
		    $table->timestamps();
	    });

	    Schema::create('crm_leads', function (Blueprint $table) {
		    $table->id();
		    $table->foreignId('customer_id')->constrained('crm_customers')->cascadeOnDelete();
		    $table->foreignId('source_id')->nullable()->constrained('crm_sources')->nullOnDelete();
		    $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
		    $table->date('contact_date')->nullable();
		    $table->foreignId('status_id')->nullable()->constrained('crm_lead_statuses')->nullOnDelete();
		    $table->text('note')->nullable();
		    $table->text('marketing_note')->nullable();
		    $table->timestamp('last_update')->useCurrent()->useCurrentOnUpdate();
		    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
		    $table->timestamps();
	    });

	    Schema::create('crm_marketing_ratings', function (Blueprint $table) {
		    $table->id();
		    $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
		    $table->string('rating', 50);
		    $table->text('note')->nullable();
		    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
		    $table->timestamps();
	    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
	    Schema::dropIfExists('crm_marketing_ratings');
	    Schema::dropIfExists('crm_leads');
	    Schema::dropIfExists('crm_customers');
    }
};
