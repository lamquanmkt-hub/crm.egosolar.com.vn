<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('solar_maintenance_checklist_templates')) {
            Schema::create('solar_maintenance_checklist_templates', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('maintenance_type', 50)->default('periodic')->index();
                $table->string('item_key', 100);
                $table->string('label', 255);
                $table->text('description')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_required')->default(true)->index();
                $table->boolean('requires_evidence')->default(true)->index();
                $table->unsignedSmallInteger('min_evidence')->default(1);
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('updated_by')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(
                    ['company_id', 'maintenance_type', 'item_key'],
                    'sm_checklist_templates_company_type_key_unique'
                );
            });
        }

        if (Schema::hasTable('solar_maintenance_checklist_items')) {
            Schema::table('solar_maintenance_checklist_items', function (Blueprint $table): void {
                if (! Schema::hasColumn('solar_maintenance_checklist_items', 'checklist_template_id')) {
                    $table->unsignedBigInteger('checklist_template_id')->nullable()->index();
                }
                if (! Schema::hasColumn('solar_maintenance_checklist_items', 'is_required')) {
                    $table->boolean('is_required')->default(true)->index();
                }
                if (! Schema::hasColumn('solar_maintenance_checklist_items', 'requires_evidence')) {
                    $table->boolean('requires_evidence')->default(true)->index();
                }
                if (! Schema::hasColumn('solar_maintenance_checklist_items', 'min_evidence')) {
                    $table->unsignedSmallInteger('min_evidence')->default(1);
                }
            });
        }

        if (Schema::hasTable('solar_maintenance_attachments')
            && ! Schema::hasColumn('solar_maintenance_attachments', 'checklist_item_id')) {
            Schema::table('solar_maintenance_attachments', function (Blueprint $table): void {
                $table->unsignedBigInteger('checklist_item_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('solar_maintenance_attachments')
            && Schema::hasColumn('solar_maintenance_attachments', 'checklist_item_id')) {
            Schema::table('solar_maintenance_attachments', function (Blueprint $table): void {
                $table->dropColumn('checklist_item_id');
            });
        }

        if (Schema::hasTable('solar_maintenance_checklist_items')) {
            foreach (['checklist_template_id', 'is_required', 'requires_evidence', 'min_evidence'] as $column) {
                if (Schema::hasColumn('solar_maintenance_checklist_items', $column)) {
                    Schema::table('solar_maintenance_checklist_items', function (Blueprint $table) use ($column): void {
                        $table->dropColumn($column);
                    });
                }
            }
        }

        Schema::dropIfExists('solar_maintenance_checklist_templates');
    }
};
