<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3 Cleanup: Remove transport custom fields and their values.
 *
 * Transport fields have been migrated to native columns on the invoices and
 * invoice_items tables. The custom field definitions and values for transport
 * templates (slugs starting with CUSTOM_Invoice_ or CUSTOM_Item_) are no
 * longer needed and are removed here.
 *
 * Data was previously copied to native columns by the
 * transport:migrate-custom-fields Artisan command.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Delete custom field values for transport custom fields
        DB::table('custom_field_values')
            ->whereIn('custom_field_id', function ($query) {
                $query->select('id')
                    ->from('custom_fields')
                    ->where('slug', 'LIKE', 'CUSTOM_Invoice_%')
                    ->orWhere('slug', 'LIKE', 'CUSTOM_Item_%');
            })
            ->delete();

        // Delete transport custom field definitions
        DB::table('custom_fields')
            ->where('slug', 'LIKE', 'CUSTOM_Invoice_%')
            ->orWhere('slug', 'LIKE', 'CUSTOM_Item_%')
            ->delete();
    }

    public function down(): void
    {
        // This migration is not reversible â€” the custom field definitions
        // were auto-created by CustomFieldsController and can be regenerated
        // by rolling back the CustomFieldsController changes and re-running
        // the seeder. The data itself lives in native columns now.
    }
};
