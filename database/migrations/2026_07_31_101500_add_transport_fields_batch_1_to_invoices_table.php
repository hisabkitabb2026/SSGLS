<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
Batch 1: LR Receipt Trip & Consignment Details (31 columns)

Fields: from_code, from_name, to_code, to_name, truck_no, mode_of_payment,

   gst_tax_payable_by, description_of_goods, hsn_code, eway_bill_no,

   actual_weight, charged_weight, no_of_articles, packing,

   basic_freight, hamali, fov, local_collection, door_delivery,

   docket_charge, other_charge, net_amount, no_of_pages, no_of_packages,

   distance_kms, rate, regd_at, body_type, make, vehicle_model, colour
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // ── LR Receipt: Trip Details ──
            if (! Schema::hasColumn('invoices', 'from_code')) {
                $table->text('from_code')->nullable()->after('office_invoice_number');
            }
            if (! Schema::hasColumn('invoices', 'from_name')) {
                $table->text('from_name')->nullable()->after('from_code');
            }
            if (! Schema::hasColumn('invoices', 'to_code')) {
                $table->text('to_code')->nullable()->after('from_name');
            }
            if (! Schema::hasColumn('invoices', 'to_name')) {
                $table->text('to_name')->nullable()->after('to_code');
            }
            if (! Schema::hasColumn('invoices', 'truck_no')) {
                $table->text('truck_no')->nullable()->after('to_name');
            }
            if (! Schema::hasColumn('invoices', 'mode_of_payment')) {
                $table->text('mode_of_payment')->nullable()->after('truck_no');
            }
            if (! Schema::hasColumn('invoices', 'gst_tax_payable_by')) {
                $table->text('gst_tax_payable_by')->nullable()->after('mode_of_payment');
            }

            // ── LR Receipt: Consignment Details ──
            if (! Schema::hasColumn('invoices', 'description_of_goods')) {
                $table->text('description_of_goods')->nullable()->after('gst_tax_payable_by');
            }
            if (! Schema::hasColumn('invoices', 'hsn_code')) {
                $table->text('hsn_code')->nullable()->after('description_of_goods');
            }
            if (! Schema::hasColumn('invoices', 'eway_bill_no')) {
                $table->text('eway_bill_no')->nullable()->after('hsn_code');
            }
            if (! Schema::hasColumn('invoices', 'actual_weight')) {
                $table->text('actual_weight')->nullable()->after('eway_bill_no');
            }
            if (! Schema::hasColumn('invoices', 'charged_weight')) {
                $table->text('charged_weight')->nullable()->after('actual_weight');
            }
            if (! Schema::hasColumn('invoices', 'no_of_articles')) {
                $table->text('no_of_articles')->nullable()->after('charged_weight');
            }
            if (! Schema::hasColumn('invoices', 'packing')) {
                $table->text('packing')->nullable()->after('no_of_articles');
            }

            // ── LR Receipt: Freight Details (integer cents) ──
            if (! Schema::hasColumn('invoices', 'basic_freight')) {
                $table->bigInteger('basic_freight')->nullable()->after('packing');
            }
            if (! Schema::hasColumn('invoices', 'hamali')) {
                $table->bigInteger('hamali')->nullable()->after('basic_freight');
            }
            if (! Schema::hasColumn('invoices', 'fov')) {
                $table->bigInteger('fov')->nullable()->after('hamali');
            }
            if (! Schema::hasColumn('invoices', 'local_collection')) {
                $table->bigInteger('local_collection')->nullable()->after('fov');
            }
            if (! Schema::hasColumn('invoices', 'door_delivery')) {
                $table->bigInteger('door_delivery')->nullable()->after('local_collection');
            }
            if (! Schema::hasColumn('invoices', 'docket_charge')) {
                $table->bigInteger('docket_charge')->nullable()->after('door_delivery');
            }
            if (! Schema::hasColumn('invoices', 'other_charge')) {
                $table->bigInteger('other_charge')->nullable()->after('docket_charge');
            }
            if (! Schema::hasColumn('invoices', 'net_amount')) {
                $table->bigInteger('net_amount')->nullable()->after('other_charge');
            }

            // ── Lorry Receipt: Trip & Vehicle (Batch 1 continued) ──
            if (! Schema::hasColumn('invoices', 'no_of_pages')) {
                $table->text('no_of_pages')->nullable()->after('net_amount');
            }
            if (! Schema::hasColumn('invoices', 'no_of_packages')) {
                $table->text('no_of_packages')->nullable()->after('no_of_pages');
            }
            if (! Schema::hasColumn('invoices', 'distance_kms')) {
                $table->text('distance_kms')->nullable()->after('no_of_packages');
            }
            if (! Schema::hasColumn('invoices', 'rate')) {
                $table->text('rate')->nullable()->after('distance_kms');
            }
            if (! Schema::hasColumn('invoices', 'regd_at')) {
                $table->text('regd_at')->nullable()->after('rate');
            }
            if (! Schema::hasColumn('invoices', 'body_type')) {
                $table->text('body_type')->nullable()->after('regd_at');
            }
            if (! Schema::hasColumn('invoices', 'make')) {
                $table->text('make')->nullable()->after('body_type');
            }
            if (! Schema::hasColumn('invoices', 'vehicle_model')) {
                $table->text('vehicle_model')->nullable()->after('make');
            }
            if (! Schema::hasColumn('invoices', 'colour')) {
                $table->text('colour')->nullable()->after('vehicle_model');
            }
        });
    }

    public function down(): void
    {
        $columns = [
            'from_code', 'from_name', 'to_code', 'to_name', 'truck_no',
            'mode_of_payment', 'gst_tax_payable_by',
            'description_of_goods', 'hsn_code', 'eway_bill_no', 'actual_weight',
            'charged_weight', 'no_of_articles', 'packing',
            'basic_freight', 'hamali', 'fov', 'local_collection', 'door_delivery',
            'docket_charge', 'other_charge', 'net_amount',
            'no_of_pages', 'no_of_packages', 'distance_kms', 'rate', 'regd_at',
            'body_type', 'make', 'vehicle_model', 'colour',
        ];

        $existing = array_filter($columns, fn ($col) => Schema::hasColumn('invoices', $col));
        if (! empty($existing)) {
            Schema::table('invoices', function (Blueprint $table) use ($existing) {
                $table->dropColumn($existing);
            });
        }
    }
};
