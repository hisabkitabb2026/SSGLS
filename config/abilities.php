<?php

use App\Models\ConsolidationGroup;
use App\Models\Customer;
use App\Models\CustomField;
use App\Models\Estimate;
use App\Models\ExchangeRateProvider;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\LoadTrip;
use App\Models\LorryPartyProfile;
use App\Models\LorryReceipt;
use App\Models\Note;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\RecurringInvoice;
use App\Models\TaxType;
use App\Models\Truck;
use App\Models\TruckMaintenance;
use App\Models\TruckOdometerReading;
use App\Models\TruckServiceSchedule;
use App\Models\Unit;
use App\Models\WarehouseItem;

return [

    /*
    |--------------------------------------------------------------------------
    | Abilities Configuration
    |--------------------------------------------------------------------------
    |
    | This file is for defining the abilities used in the application. Each
    | ability includes a name, a unique identifier (ability), the associated
    | model, and any dependencies on other abilities. This configuration helps
    | manage user permissions and access control throughout the application.
    |
    */

    'abilities' => [

        // Customer
        [
            'name' => 'view customer',
            'ability' => 'view-customer',
            'model' => Customer::class,
        ],
        [
            'name' => 'create customer',
            'ability' => 'create-customer',
            'model' => Customer::class,
            'depends_on' => [
                'view-customer',
                'view-custom-field',
            ],
        ],
        [
            'name' => 'edit customer',
            'ability' => 'edit-customer',
            'model' => Customer::class,
            'depends_on' => [
                'view-customer',
                'view-custom-field',
            ],
        ],
        [
            'name' => 'delete customer',
            'ability' => 'delete-customer',
            'model' => Customer::class,
            'depends_on' => [
                'view-customer',
            ],
        ],

        // Item
        [
            'name' => 'view item',
            'ability' => 'view-item',
            'model' => Item::class,
        ],
        [
            'name' => 'create item',
            'ability' => 'create-item',
            'model' => Item::class,
            'depends_on' => [
                'view-item',
                'view-tax-type',
            ],
        ],
        [
            'name' => 'edit item',
            'ability' => 'edit-item',
            'model' => Item::class,
            'depends_on' => [
                'view-item',
            ],
        ],
        [
            'name' => 'delete item',
            'ability' => 'delete-item',
            'model' => Item::class,
            'depends_on' => [
                'view-item',
            ],
        ],

        // Unit
        [
            'name' => 'view unit',
            'ability' => 'view-unit',
            'model' => Unit::class,
        ],
        [
            'name' => 'create unit',
            'ability' => 'create-unit',
            'model' => Unit::class,
            'depends_on' => [
                'view-unit',
            ],
        ],
        [
            'name' => 'edit unit',
            'ability' => 'edit-unit',
            'model' => Unit::class,
            'depends_on' => [
                'view-unit',
            ],
        ],
        [
            'name' => 'delete unit',
            'ability' => 'delete-unit',
            'model' => Unit::class,
            'depends_on' => [
                'view-unit',
            ],
        ],

        // Tax Type
        [
            'name' => 'view tax type',
            'ability' => 'view-tax-type',
            'model' => TaxType::class,
        ],
        [
            'name' => 'create tax type',
            'ability' => 'create-tax-type',
            'model' => TaxType::class,
            'depends_on' => [
                'view-tax-type',
            ],
        ],
        [
            'name' => 'edit tax type',
            'ability' => 'edit-tax-type',
            'model' => TaxType::class,
            'depends_on' => [
                'view-tax-type',
            ],
        ],
        [
            'name' => 'delete tax type',
            'ability' => 'delete-tax-type',
            'model' => TaxType::class,
            'depends_on' => [
                'view-tax-type',
            ],
        ],

        // Estimate
        [
            'name' => 'view estimate',
            'ability' => 'view-estimate',
            'model' => Estimate::class,
        ],
        [
            'name' => 'create estimate',
            'ability' => 'create-estimate',
            'model' => Estimate::class,
            'depends_on' => [
                'view-estimate',
                'view-item',
                'view-tax-type',
                'view-customer',
                'view-custom-field',
                'view-all-notes',
            ],
        ],
        [
            'name' => 'edit estimate',
            'ability' => 'edit-estimate',
            'model' => Estimate::class,
            'depends_on' => [
                'view-item',
                'view-estimate',
                'view-tax-type',
                'view-customer',
                'view-custom-field',
                'view-all-notes',
            ],
        ],
        [
            'name' => 'delete estimate',
            'ability' => 'delete-estimate',
            'model' => Estimate::class,
            'depends_on' => [
                'view-estimate',
            ],
        ],
        [
            'name' => 'send estimate',
            'ability' => 'send-estimate',
            'model' => Estimate::class,
        ],

        // Quotation
        [
            'name' => 'view quotation',
            'ability' => 'view-quotation',
            'model' => Estimate::class,
        ],
        [
            'name' => 'create quotation',
            'ability' => 'create-quotation',
            'model' => Estimate::class,
            'depends_on' => [
                'view-quotation',
                'view-item',
                'view-tax-type',
                'view-customer',
                'view-custom-field',
                'view-all-notes',
            ],
        ],
        [
            'name' => 'edit quotation',
            'ability' => 'edit-quotation',
            'model' => Estimate::class,
            'depends_on' => [
                'view-item',
                'view-quotation',
                'view-tax-type',
                'view-customer',
                'view-custom-field',
                'view-all-notes',
            ],
        ],
        [
            'name' => 'delete quotation',
            'ability' => 'delete-quotation',
            'model' => Estimate::class,
            'depends_on' => [
                'view-quotation',
            ],
        ],
        [
            'name' => 'send quotation',
            'ability' => 'send-quotation',
            'model' => Estimate::class,
        ],

        // Invoice
        [
            'name' => 'view invoice',
            'ability' => 'view-invoice',
            'model' => Invoice::class,
        ],
        [
            'name' => 'create invoice',
            'ability' => 'create-invoice',
            'model' => Invoice::class,
            'owner_only' => false,
            'depends_on' => [
                'view-item',
                'view-invoice',
                'view-tax-type',
                'view-customer',
                'view-custom-field',
                'view-all-notes',
            ],
        ],
        [
            'name' => 'edit invoice',
            'ability' => 'edit-invoice',
            'model' => Invoice::class,
            'depends_on' => [
                'view-item',
                'view-invoice',
                'view-tax-type',
                'view-customer',
                'view-custom-field',
                'view-all-notes',
            ],
        ],
        [
            'name' => 'delete invoice',
            'ability' => 'delete-invoice',
            'model' => Invoice::class,
            'depends_on' => [
                'view-invoice',
            ],
        ],
        [
            'name' => 'send invoice',
            'ability' => 'send-invoice',
            'model' => Invoice::class,
        ],

        // LR Receipt
        [
            'name' => 'view lr receipt',
            'ability' => 'view-lr-receipt',
            'model' => Invoice::class,
            'group' => 'LR Receipt',
        ],
        [
            'name' => 'create lr receipt',
            'ability' => 'create-lr-receipt',
            'model' => Invoice::class,
            'group' => 'LR Receipt',
            'owner_only' => false,
            'depends_on' => [
                'view-item',
                'view-lr-receipt',
                'view-tax-type',
                'view-customer',
                'view-custom-field',
                'view-all-notes',
            ],
        ],
        [
            'name' => 'edit lr receipt',
            'ability' => 'edit-lr-receipt',
            'model' => Invoice::class,
            'group' => 'LR Receipt',
            'depends_on' => [
                'view-item',
                'view-lr-receipt',
                'view-tax-type',
                'view-customer',
                'view-custom-field',
                'view-all-notes',
            ],
        ],
        [
            'name' => 'delete lr receipt',
            'ability' => 'delete-lr-receipt',
            'model' => Invoice::class,
            'group' => 'LR Receipt',
            'depends_on' => [
                'view-lr-receipt',
            ],
        ],
        [
            'name' => 'send lr receipt',
            'ability' => 'send-lr-receipt',
            'model' => Invoice::class,
            'group' => 'LR Receipt',
        ],

        // Invoice Receipt (Office Invoice)
        [
            'name' => 'view invoice receipt',
            'ability' => 'view-invoice-receipt',
            'model' => Invoice::class,
            'group' => 'Invoice Receipt',
        ],
        [
            'name' => 'create invoice receipt',
            'ability' => 'create-invoice-receipt',
            'model' => Invoice::class,
            'group' => 'Invoice Receipt',
            'owner_only' => false,
            'depends_on' => [
                'view-item',
                'view-invoice-receipt',
                'view-tax-type',
                'view-customer',
                'view-custom-field',
                'view-all-notes',
            ],
        ],
        [
            'name' => 'edit invoice receipt',
            'ability' => 'edit-invoice-receipt',
            'model' => Invoice::class,
            'group' => 'Invoice Receipt',
            'depends_on' => [
                'view-item',
                'view-invoice-receipt',
                'view-tax-type',
                'view-customer',
                'view-custom-field',
                'view-all-notes',
            ],
        ],
        [
            'name' => 'delete invoice receipt',
            'ability' => 'delete-invoice-receipt',
            'model' => Invoice::class,
            'group' => 'Invoice Receipt',
            'depends_on' => [
                'view-invoice-receipt',
            ],
        ],
        [
            'name' => 'send invoice receipt',
            'ability' => 'send-invoice-receipt',
            'model' => Invoice::class,
            'group' => 'Invoice Receipt',
        ],

        // Lorry Receipt
        [
            'name' => 'view lorry receipt',
            'ability' => 'view-lorry-receipt',
            'model' => LorryReceipt::class,
            'group' => 'Lorry Receipt',
        ],
        [
            'name' => 'create lorry receipt',
            'ability' => 'create-lorry-receipt',
            'model' => LorryReceipt::class,
            'group' => 'Lorry Receipt',
            'owner_only' => false,
            'depends_on' => [
                'view-item',
                'view-lorry-receipt',
                'view-tax-type',
                'view-customer',
                'view-custom-field',
                'view-all-notes',
            ],
        ],
        [
            'name' => 'edit lorry receipt',
            'ability' => 'edit-lorry-receipt',
            'model' => LorryReceipt::class,
            'group' => 'Lorry Receipt',
            'depends_on' => [
                'view-item',
                'view-lorry-receipt',
                'view-tax-type',
                'view-customer',
                'view-custom-field',
                'view-all-notes',
            ],
        ],
        [
            'name' => 'delete lorry receipt',
            'ability' => 'delete-lorry-receipt',
            'model' => LorryReceipt::class,
            'group' => 'Lorry Receipt',
            'depends_on' => [
                'view-lorry-receipt',
            ],
        ],
        [
            'name' => 'send lorry receipt',
            'ability' => 'send-lorry-receipt',
            'model' => LorryReceipt::class,
            'group' => 'Lorry Receipt',
        ],

        // Recurring Invoice

        [
            'name' => 'view recurring invoice',
            'ability' => 'view-recurring-invoice',
            'model' => RecurringInvoice::class,
        ],
        [
            'name' => 'create recurring invoice',
            'ability' => 'create-recurring-invoice',
            'model' => RecurringInvoice::class,
            'depends_on' => [
                'view-item',
                'view-recurring-invoice',
                'view-tax-type',
                'view-customer',
                'view-all-notes',
                'send-invoice',
            ],
        ],
        [
            'name' => 'edit recurring invoice',
            'ability' => 'edit-recurring-invoice',
            'model' => RecurringInvoice::class,
            'depends_on' => [
                'view-item',
                'view-recurring-invoice',
                'view-tax-type',
                'view-customer',
                'view-all-notes',
                'send-invoice',
            ],
        ],
        [
            'name' => 'delete recurring invoice',
            'ability' => 'delete-recurring-invoice',
            'model' => RecurringInvoice::class,
            'depends_on' => [
                'view-recurring-invoice',
            ],
        ],

        // Payment
        [
            'name' => 'view payment',
            'ability' => 'view-payment',
            'model' => Payment::class,
        ],
        [
            'name' => 'create payment',
            'ability' => 'create-payment',
            'model' => Payment::class,
            'depends_on' => [
                'view-customer',
                'view-payment',
                'view-invoice',
                'view-custom-field',
                'view-all-notes',
            ],
        ],
        [
            'name' => 'edit payment',
            'ability' => 'edit-payment',
            'model' => Payment::class,
            'depends_on' => [
                'view-customer',
                'view-payment',
                'view-invoice',
                'view-custom-field',
                'view-all-notes',
            ],
        ],
        [
            'name' => 'delete payment',
            'ability' => 'delete-payment',
            'model' => Payment::class,
            'depends_on' => [
                'view-payment',
            ],
        ],
        [
            'name' => 'send payment',
            'ability' => 'send-payment',
            'model' => Payment::class,
        ],

        // Expense
        [
            'name' => 'view expense',
            'ability' => 'view-expense',
            'model' => Expense::class,
        ],
        [
            'name' => 'create expense',
            'ability' => 'create-expense',
            'model' => Expense::class,
            'depends_on' => [
                'view-customer',
                'view-expense',
                'view-custom-field',
            ],
        ],
        [
            'name' => 'edit expense',
            'ability' => 'edit-expense',
            'model' => Expense::class,
            'depends_on' => [
                'view-customer',
                'view-expense',
                'view-custom-field',
            ],
        ],
        [
            'name' => 'delete expense',
            'ability' => 'delete-expense',
            'model' => Expense::class,
            'depends_on' => [
                'view-expense',
            ],
        ],

        // Payment Method
        [
            'name' => 'view payment method',
            'ability' => 'view-payment_method',
            'model' => PaymentMethod::class,
        ],
        [
            'name' => 'create payment method',
            'ability' => 'create-payment_method',
            'model' => PaymentMethod::class,
            'depends_on' => [
                'view-payment_method',
            ],
        ],
        [
            'name' => 'edit payment method',
            'ability' => 'edit-payment_method',
            'model' => PaymentMethod::class,
            'depends_on' => [
                'view-payment_method',
            ],
        ],
        [
            'name' => 'delete payment method',
            'ability' => 'delete-payment_method',
            'model' => PaymentMethod::class,
            'depends_on' => [
                'view-payment_method',
            ],
        ],

        // Expense Category
        [
            'name' => 'view expense category',
            'ability' => 'view-expense_category',
            'model' => ExpenseCategory::class,
        ],
        [
            'name' => 'create expense category',
            'ability' => 'create-expense_category',
            'model' => ExpenseCategory::class,
            'depends_on' => [
                'view-expense_category',
            ],
        ],
        [
            'name' => 'edit expense category',
            'ability' => 'edit-expense_category',
            'model' => ExpenseCategory::class,
            'depends_on' => [
                'view-expense_category',
            ],
        ],
        [
            'name' => 'delete expense category',
            'ability' => 'delete-expense_category',
            'model' => ExpenseCategory::class,
            'depends_on' => [
                'view-expense_category',
            ],
        ],

        // Lorry Party Profile
        [
            'name' => 'view lorry party profile',
            'ability' => 'view-lorry_party_profile',
            'model' => LorryPartyProfile::class,
        ],
        [
            'name' => 'create lorry party profile',
            'ability' => 'create-lorry_party_profile',
            'model' => LorryPartyProfile::class,
            'depends_on' => [
                'view-lorry_party_profile',
                'view-customer',
            ],
        ],
        [
            'name' => 'edit lorry party profile',
            'ability' => 'edit-lorry_party_profile',
            'model' => LorryPartyProfile::class,
            'depends_on' => [
                'view-lorry_party_profile',
                'view-customer',
            ],
        ],
        [
            'name' => 'delete lorry party profile',
            'ability' => 'delete-lorry_party_profile',
            'model' => LorryPartyProfile::class,
            'depends_on' => [
                'view-lorry_party_profile',
            ],
        ],

        // Warehouse Item
        [
            'name' => 'view warehouse item',
            'ability' => 'view-warehouse-item',
            'model' => WarehouseItem::class,
        ],
        [
            'name' => 'create warehouse item',
            'ability' => 'create-warehouse-item',
            'model' => WarehouseItem::class,
            'depends_on' => [
                'view-warehouse-item',
                'view-invoice',
            ],
        ],
        [
            'name' => 'edit warehouse item',
            'ability' => 'edit-warehouse-item',
            'model' => WarehouseItem::class,
            'depends_on' => [
                'view-warehouse-item',
            ],
        ],
        [
            'name' => 'delete warehouse item',
            'ability' => 'delete-warehouse-item',
            'model' => WarehouseItem::class,
            'depends_on' => [
                'view-warehouse-item',
            ],
        ],

        // Consolidation Group
        [
            'name' => 'view consolidation group',
            'ability' => 'view-consolidation-group',
            'model' => ConsolidationGroup::class,
        ],
        [
            'name' => 'create consolidation group',
            'ability' => 'create-consolidation-group',
            'model' => ConsolidationGroup::class,
            'depends_on' => [
                'view-consolidation-group',
            ],
        ],
        [
            'name' => 'edit consolidation group',
            'ability' => 'edit-consolidation-group',
            'model' => ConsolidationGroup::class,
            'depends_on' => [
                'view-consolidation-group',
            ],
        ],
        [
            'name' => 'delete consolidation group',
            'ability' => 'delete-consolidation-group',
            'model' => ConsolidationGroup::class,
            'depends_on' => [
                'view-consolidation-group',
            ],
        ],

        // Load Trip
        [
            'name' => 'view load trip',
            'ability' => 'view-load-trip',
            'model' => LoadTrip::class,
        ],
        [
            'name' => 'create load trip',
            'ability' => 'create-load-trip',
            'model' => LoadTrip::class,
            'depends_on' => [
                'view-load-trip',
            ],
        ],
        [
            'name' => 'edit load trip',
            'ability' => 'edit-load-trip',
            'model' => LoadTrip::class,
            'depends_on' => [
                'view-load-trip',
            ],
        ],
        [
            'name' => 'delete load trip',
            'ability' => 'delete-load-trip',
            'model' => LoadTrip::class,
            'depends_on' => [
                'view-load-trip',
            ],
        ],

        // Fleet Truck
        ['name' => 'view truck', 'ability' => 'view-truck', 'model' => Truck::class],
        ['name' => 'create truck', 'ability' => 'create-truck', 'model' => Truck::class, 'depends_on' => ['view-truck']],
        ['name' => 'edit truck', 'ability' => 'edit-truck', 'model' => Truck::class, 'depends_on' => ['view-truck']],
        ['name' => 'delete truck', 'ability' => 'delete-truck', 'model' => Truck::class, 'depends_on' => ['view-truck']],

        // Fleet operations
        ['name' => 'view truck maintenance', 'ability' => 'view-truck-maintenance', 'model' => TruckMaintenance::class, 'group' => 'Fleet'],
        ['name' => 'create truck maintenance', 'ability' => 'create-truck-maintenance', 'model' => TruckMaintenance::class, 'group' => 'Fleet', 'depends_on' => ['view-truck-maintenance']],
        ['name' => 'edit truck maintenance', 'ability' => 'edit-truck-maintenance', 'model' => TruckMaintenance::class, 'group' => 'Fleet', 'depends_on' => ['view-truck-maintenance']],
        ['name' => 'delete truck maintenance', 'ability' => 'delete-truck-maintenance', 'model' => TruckMaintenance::class, 'group' => 'Fleet', 'depends_on' => ['view-truck-maintenance']],
        ['name' => 'view odometer reading', 'ability' => 'view-odometer-reading', 'model' => TruckOdometerReading::class, 'group' => 'Fleet'],
        ['name' => 'create odometer reading', 'ability' => 'create-odometer-reading', 'model' => TruckOdometerReading::class, 'group' => 'Fleet', 'depends_on' => ['view-odometer-reading']],
        ['name' => 'edit odometer reading', 'ability' => 'edit-odometer-reading', 'model' => TruckOdometerReading::class, 'group' => 'Fleet', 'depends_on' => ['view-odometer-reading']],
        ['name' => 'delete odometer reading', 'ability' => 'delete-odometer-reading', 'model' => TruckOdometerReading::class, 'group' => 'Fleet', 'depends_on' => ['view-odometer-reading']],
        ['name' => 'view truck service schedule', 'ability' => 'view-truck-service-schedule', 'model' => TruckServiceSchedule::class, 'group' => 'Fleet'],
        ['name' => 'create truck service schedule', 'ability' => 'create-truck-service-schedule', 'model' => TruckServiceSchedule::class, 'group' => 'Fleet', 'depends_on' => ['view-truck-service-schedule']],
        ['name' => 'edit truck service schedule', 'ability' => 'edit-truck-service-schedule', 'model' => TruckServiceSchedule::class, 'group' => 'Fleet', 'depends_on' => ['view-truck-service-schedule']],
        ['name' => 'delete truck service schedule', 'ability' => 'delete-truck-service-schedule', 'model' => TruckServiceSchedule::class, 'group' => 'Fleet', 'depends_on' => ['view-truck-service-schedule']],

        // Custom Field
        [
            'name' => 'view custom field',
            'ability' => 'view-custom-field',
            'model' => CustomField::class,
        ],
        [
            'name' => 'create custom field',
            'ability' => 'create-custom-field',
            'model' => CustomField::class,
            'depends_on' => [
                'view-custom-field',
            ],
        ],
        [
            'name' => 'edit custom field',
            'ability' => 'edit-custom-field',
            'model' => CustomField::class,
            'depends_on' => [
                'view-custom-field',
            ],
        ],
        [
            'name' => 'delete custom field',
            'ability' => 'delete-custom-field',
            'model' => CustomField::class,
            'depends_on' => [
                'view-custom-field',
            ],
        ],

        // Financial Reports
        [
            'name' => 'view financial reports',
            'ability' => 'view-financial-reports',
            'model' => null,
        ],

        // Exchange Rate Provider
        [
            'name' => 'view exchange rate provider',
            'ability' => 'view-exchange-rate-provider',
            'model' => ExchangeRateProvider::class,
            'owner_only' => false,
        ],
        [
            'name' => 'create exchange rate provider',
            'ability' => 'create-exchange-rate-provider',
            'model' => ExchangeRateProvider::class,
            'owner_only' => false,
            'depends_on' => [
                'view-exchange-rate-provider',
            ],
        ],
        [
            'name' => 'edit exchange rate provider',
            'ability' => 'edit-exchange-rate-provider',
            'model' => ExchangeRateProvider::class,
            'owner_only' => false,
            'depends_on' => [
                'view-exchange-rate-provider',
            ],
        ],
        [
            'name' => 'delete exchange rate provider',
            'ability' => 'delete-exchange-rate-provider',
            'model' => ExchangeRateProvider::class,
            'owner_only' => false,
            'depends_on' => [
                'view-exchange-rate-provider',
            ],
        ],

        // Settings Pages
        [
            'name' => 'view company information',
            'ability' => 'view-settings-company-info',
            'model' => null,
            'group' => 'Settings',
        ],
        [
            'name' => 'view preferences',
            'ability' => 'view-settings-preferences',
            'model' => null,
            'group' => 'Settings',
        ],
        [
            'name' => 'view customization',
            'ability' => 'view-settings-customization',
            'model' => null,
            'group' => 'Settings',
        ],
        [
            'name' => 'view notifications',
            'ability' => 'view-settings-notifications',
            'model' => null,
            'group' => 'Settings',
        ],
        [
            'name' => 'view roles',
            'ability' => 'view-settings-roles',
            'model' => null,
            'group' => 'Settings',
        ],
        [
            'name' => 'view mail configuration',
            'ability' => 'view-settings-mail-config',
            'model' => null,
            'group' => 'Settings',
        ],
        [
            'name' => 'view ai configuration',
            'ability' => 'view-settings-ai-config',
            'model' => null,
            'group' => 'Settings',
        ],
        [
            'name' => 'view modules',
            'ability' => 'view-settings-modules',
            'model' => null,
            'group' => 'Settings',
        ],

        // Settings — remaining settings pages
        [
            'name' => 'view exchange rate provider',
            'ability' => 'view-settings-exchange-rate',
            'model' => null,
            'group' => 'Settings',
        ],
        [
            'name' => 'view tax types',
            'ability' => 'view-settings-tax-types',
            'model' => null,
            'group' => 'Settings',
        ],
        [
            'name' => 'view payment modes',
            'ability' => 'view-settings-payment-modes',
            'model' => null,
            'group' => 'Settings',
        ],
        [
            'name' => 'view custom fields',
            'ability' => 'view-settings-custom-fields',
            'model' => null,
            'group' => 'Settings',
        ],
        [
            'name' => 'view notes',
            'ability' => 'view-settings-notes',
            'model' => null,
            'group' => 'Settings',
        ],
        [
            'name' => 'view expense categories',
            'ability' => 'view-settings-expense-categories',
            'model' => null,
            'group' => 'Settings',
        ],

        // Settings

        [
            'name' => 'view company dashboard',
            'ability' => 'dashboard',
            'model' => null,
        ],

        [
            'name' => 'view all notes',
            'ability' => 'view-all-notes',
            'model' => Note::class,
        ],
        [
            'name' => 'manage notes',
            'ability' => 'manage-all-notes',
            'model' => Note::class,
            'depends_on' => [
                'view-all-notes',
            ],
        ],
    ],
];
