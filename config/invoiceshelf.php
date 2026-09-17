<?php

use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\LorryReceipt;
use App\Models\Payment;
use App\Models\Truck;
use App\Models\WarehouseItem;

return [
    /*
    * Minimum php version.
    */
    'min_php_version' => '8.4.0',

    /*
    * Minimum mysql version.
    */

    'min_mysql_version' => '5.7.7',

    /*
    * Minimum mariadb version.
    */

    'min_mariadb_version' => '10.2.7',

    /*
    * Minimum pgsql version.
    */

    'min_pgsql_version' => '9.2.0',

    /*
    * Minimum sqlite version.
    */

    'min_sqlite_version' => '3.35.0',

    /*
    * Marketplace and updater base URL.
    *
    * The marketplace client (App\Support\Module\ModuleInstaller) and the
    * updater (App\Support\Update\Updater) both build their HTTP client base
    * URI from this value via App\Traits\SiteApi::getRemote(). Override via
    * INVOICESHELF_BASE_URL in .env to point a self-hosted instance or local
    * dev environment at a non-production marketplace (e.g. a local checkout
    * of the invoiceshelf/website repo).
    */
    'base_url' => env('INVOICESHELF_BASE_URL', 'https://invoiceshelf.com'),

    /*
    * Whether the app runs inside the official Docker image. The image's
    * docker/production/inject.sh sets CONTAINERIZED=true in .env at startup.
    * When true, the in-app updater is disabled (the API refuses and the UI hides
    * it) because containers upgrade via `docker compose pull`, not by copying
    * release files over the read-only/ephemeral image filesystem.
    */
    'containerized' => env('CONTAINERIZED', false),

    /*
    * Secure module marketplace configuration.
    *
    * Manifests are signed with Ed25519. The official key is built in; additional
    * keys can be added via MARKETPLACE_PUBLIC_KEYS for module signing by custom
    * publishers. An emergency key rotation happens by rotating the key ID and
    * replacing the old key with a new one in this config.
    *
    * ZIP bomb and teraflood protections are enforced on module downloads.
    * The lease duration controls how long a marketplace response is cached
    * before a fresh check for updates. No default: intentionally unset until
    * the marketplace is mature enough to define SLAs.
    */
    'marketplace' => [
        'channel' => env('MARKETPLACE_CHANNEL', 'stable'),
        'module_api_version' => (string) env('MARKETPLACE_MODULE_API_VERSION', '1.0.0'),
        'public_keys' => array_replace(
            ['marketplace-2026' => 'base64-ed25519-public-key-will-be-set-at-runtime'],
            json_decode((string) env('MARKETPLACE_PUBLIC_KEYS', '{}'), true) ?: [],
        ),
        'max_zip_entries' => (int) env('MARKETPLACE_MAX_ZIP_ENTRIES', 10000),
        'max_zip_compressed_bytes' => (int) env('MARKETPLACE_MAX_ZIP_COMPRESSED_BYTES', 134217728),
        'max_zip_uncompressed_bytes' => (int) env('MARKETPLACE_MAX_ZIP_UNCOMPRESSED_BYTES', 256000000),
        'max_zip_compression_ratio' => (int) env('MARKETPLACE_MAX_ZIP_COMPRESSION_RATIO', 200),
        'lease_seconds' => (int) env('MARKETPLACE_LEASE_SECONDS', 900),
    ],

    /*
    * Paths protected from cleanup during updates.
    * The updater will never delete files under these prefixes.
    */
    'update_protected_paths' => [
        '.env',
        'storage',
        'vendor',
        'node_modules',
        'Modules',
        'public/storage',
        '.git',
        'bootstrap/cache',
        'manifest.json',
        'android',
        'ios',
        'mobile',
    ],

    /*
    * List of languages supported by InvoiceShelf.
    */
    'languages' => [
        ['code' => 'ar', 'name' => 'Arabic'],
        ['code' => 'bg', 'name' => 'Bulgarian'],
        ['code' => 'zh_CN', 'name' => 'Chinese (Simplified)'],
        ['code' => 'zh', 'name' => 'Chinese (Traditional)'],
        ['code' => 'hr', 'name' => 'Croatian'],
        ['code' => 'cs', 'name' => 'Czech'],
        ['code' => 'nl', 'name' => 'Dutch'],
        ['code' => 'en', 'name' => 'English'],
        ['code' => 'fi', 'name' => 'Finnish'],
        ['code' => 'fr', 'name' => 'French'],
        ['code' => 'de', 'name' => 'German'],
        ['code' => 'el', 'name' => 'Greek'],
        ['code' => 'he', 'name' => 'עברית'],
        ['code' => 'hi', 'name' => 'Hindi'],
        ['code' => 'id', 'name' => 'Indonesian'],
        ['code' => 'it', 'name' => 'Italian'],
        ['code' => 'ja', 'name' => 'Japanese'],
        ['code' => 'ko', 'name' => 'Korean'],
        ['code' => 'lv', 'name' => 'Latvian'],
        ['code' => 'lt', 'name' => 'Lithuanian'],
        ['code' => 'mk', 'name' => 'Macedonian'],
        ['code' => 'no', 'name' => 'Norwegian'],
        ['code' => 'fa', 'name' => 'Persian'],
        ['code' => 'pl', 'name' => 'Polish'],
        ['code' => 'pt', 'name' => 'Portuguese'],
        ['code' => 'pt_BR', 'name' => 'Portuguese (Brazilian)'],
        ['code' => 'ro', 'name' => 'Romanian'],
        ['code' => 'ru', 'name' => 'Russian'],
        ['code' => 'sr', 'name' => 'Serbian Latin'],
        ['code' => 'sk', 'name' => 'Slovak'],
        ['code' => 'sl', 'name' => 'Slovenian'],
        ['code' => 'es', 'name' => 'Spanish'],
        ['code' => 'sv', 'name' => 'Svenska'],
        ['code' => 'th', 'name' => 'ไทย'],
        ['code' => 'vi', 'name' => 'Tiếng Việt'],
        ['code' => 'tr', 'name' => 'Turkish'],
        ['code' => 'uk', 'name' => 'Ukrainian'],
        ['code' => 'ur', 'name' => 'اردو'],
    ],

    /*
    * List of Fiscal Years
    */
    'fiscal_years' => [
        ['key' => 'settings.preferences.fiscal_years.january_december', 'value' => '1-12'],
        ['key' => 'settings.preferences.fiscal_years.february_january', 'value' => '2-1'],
        ['key' => 'settings.preferences.fiscal_years.march_february', 'value' => '3-2'],
        ['key' => 'settings.preferences.fiscal_years.april_march', 'value' => '4-3'],
        ['key' => 'settings.preferences.fiscal_years.may_april', 'value' => '5-4'],
        ['key' => 'settings.preferences.fiscal_years.june_may', 'value' => '6-5'],
        ['key' => 'settings.preferences.fiscal_years.july_june', 'value' => '7-6'],
        ['key' => 'settings.preferences.fiscal_years.august_july', 'value' => '8-7'],
        ['key' => 'settings.preferences.fiscal_years.september_august', 'value' => '9-8'],
        ['key' => 'settings.preferences.fiscal_years.october_september', 'value' => '10-9'],
        ['key' => 'settings.preferences.fiscal_years.november_october', 'value' => '11-10'],
        ['key' => 'settings.preferences.fiscal_years.december_november', 'value' => '12-11'],
    ],

    /*
    * List of convert estimate options
    */
    'convert_estimate_options' => [
        ['key' => 'settings.preferences.no_action', 'value' => 'no_action'],
        ['key' => 'settings.preferences.delete_estimate', 'value' => 'delete_estimate'],
        ['key' => 'settings.preferences.mark_estimate_as_accepted', 'value' => 'mark_estimate_as_accepted'],
    ],

    /*
    * List of retrospective edits
    */
    'retrospective_edits' => [
        ['key' => 'settings.preferences.allow', 'value' => 'allow'],
        ['key' => 'settings.preferences.disable_on_invoice_partial_paid', 'value' => 'disable_on_invoice_partial_paid'],
        ['key' => 'settings.preferences.disable_on_invoice_paid', 'value' => 'disable_on_invoice_paid'],
        ['key' => 'settings.preferences.disable_on_invoice_sent', 'value' => 'disable_on_invoice_sent'],
    ],

    /*
    * List of setting menu
    */
    'setting_menu' => [
        [
            'title' => 'settings.menu_title.company_information',
            'group' => '',
            'name' => 'Company information',
            'link' => '/admin/settings/company-info',
            'icon' => 'BuildingOfficeIcon',
            'owner_only' => false,
            'ability' => 'view-settings-company-info',
            'model' => '',
        ],
        [
            'title' => 'settings.menu_title.preferences',
            'group' => '',
            'name' => 'Preferences',
            'link' => '/admin/settings/preferences',
            'icon' => 'CogIcon',
            'owner_only' => false,
            'ability' => 'view-settings-preferences',
            'model' => '',
        ],
        [
            'title' => 'settings.menu_title.customization',
            'group' => '',
            'name' => 'Customization',
            'link' => '/admin/settings/customization',
            'icon' => 'PencilSquareIcon',
            'owner_only' => false,
            'ability' => 'view-settings-customization',
            'model' => '',
        ],
        [
            'title' => 'settings.roles.title',
            'group' => '',
            'name' => 'Company Roles',
            'link' => '/admin/settings/roles',
            'icon' => 'UserGroupIcon',
            'owner_only' => false,
            'ability' => 'view-settings-roles',
            'model' => '',
        ],

        [
            'title' => 'settings.menu_title.exchange_rate',
            'group' => '',
            'name' => 'Exchange Rate Provider',
            'link' => '/admin/settings/exchange-rate',
            'icon' => 'BanknotesIcon',
            'owner_only' => false,
            'ability' => 'view-settings-exchange-rate',
            'model' => '',

        ],
        [
            'title' => 'settings.menu_title.notifications',
            'group' => '',
            'name' => 'Notifications',
            'link' => '/admin/settings/notifications',
            'icon' => 'BellIcon',
            'owner_only' => false,
            'ability' => 'view-settings-notifications',
            'model' => '',
        ],

        [
            'title' => 'settings.menu_title.tax_types',
            'group' => '',
            'name' => 'Tax types',
            'link' => '/admin/settings/tax-types',
            'icon' => 'CheckCircleIcon',
            'owner_only' => false,
            'ability' => 'view-settings-tax-types',
            'model' => '',

        ],
        [
            'title' => 'settings.menu_title.payment_modes',
            'group' => '',
            'name' => 'Payment modes',
            'link' => '/admin/settings/payment-modes',
            'icon' => 'CreditCardIcon',
            'owner_only' => false,
            'ability' => 'view-settings-payment-modes',
            'model' => '',

        ],
        [
            'title' => 'settings.menu_title.custom_fields',
            'group' => '',
            'name' => 'Custom fields',
            'link' => '/admin/settings/custom-fields',
            'icon' => 'CubeIcon',
            'owner_only' => false,
            'ability' => 'view-settings-custom-fields',
            'model' => '',

        ],
        [
            'title' => 'settings.menu_title.notes',
            'group' => '',
            'name' => 'Notes',
            'link' => '/admin/settings/notes',
            'icon' => 'ClipboardDocumentCheckIcon',
            'owner_only' => false,
            'ability' => 'view-settings-notes',
            'model' => '',

        ],
        [
            'title' => 'settings.menu_title.expense_category',
            'group' => '',
            'name' => 'Expense Category',
            'link' => '/admin/settings/expense-categories',
            'icon' => 'ClipboardDocumentListIcon',
            'owner_only' => false,
            'ability' => 'view-settings-expense-categories',
            'model' => '',

        ],
        [
            'title' => 'settings.mail.company_mail_config',
            'group' => '',
            'name' => 'Mail Configuration',
            'link' => '/admin/settings/mail-config',
            'icon' => 'EnvelopeIcon',
            'owner_only' => false,
            'ability' => 'view-settings-mail-config',
            'model' => '',
        ],
        [
            'title' => 'settings.menu_title.ai_configuration',
            'group' => '',
            'name' => 'AI Configuration',
            'link' => '/admin/settings/ai-config',
            'icon' => 'SparklesIcon',
            'owner_only' => false,
            'ability' => 'view-settings-ai-config',
            'model' => '',
        ],
        [
            'title' => 'settings.menu_title.module_configuration',
            'group' => '',
            'name' => 'Module Configuration',
            'link' => '/admin/settings/modules',
            'icon' => 'PuzzlePieceIcon',
            'owner_only' => false,
            'ability' => 'view-settings-modules',
            'model' => '',
        ],

    ],

    /*
    * List of main menu
    */
    'main_menu' => [
        [
            'title' => 'navigation.dashboard',
            'group' => 'main',
            'group_label' => '',
            'priority' => 10,
            'link' => '/admin/dashboard',
            'icon' => 'HomeIcon',
            'name' => 'Dashboard',
            'owner_only' => false,
            'ability' => 'dashboard',
            'model' => '',
        ],
        [
            'title' => 'navigation.customers',
            'group' => 'main',
            'group_label' => '',
            'priority' => 20,
            'link' => '/admin/customers',
            'icon' => 'UserIcon',
            'name' => 'Customers',
            'owner_only' => false,
            'ability' => 'view-customer',
            'model' => Customer::class,
        ],
        [
            'title' => 'navigation.items',
            'group' => 'main',
            'group_label' => '',
            'priority' => 30,
            'link' => '/admin/items',
            'icon' => 'StarIcon',
            'name' => 'Items',
            'owner_only' => false,
            'ability' => 'view-item',
            'model' => Item::class,
        ],
        [
            'title' => 'navigation.estimates',
            'group' => 'documents',
            'group_label' => 'navigation.documents',
            'priority' => 10,
            'link' => '/admin/estimates',
            'icon' => 'DocumentIcon',
            'name' => 'Estimates',
            'owner_only' => false,
            'ability' => 'view-estimate',
            'model' => Estimate::class,
        ],
        [
            'title' => 'navigation.quotations',
            'group' => 'documents',
            'group_label' => 'navigation.documents',
            'priority' => 11,
            'link' => '/admin/quotations',
            'icon' => 'DocumentDuplicateIcon',
            'name' => 'Quotations',
            'owner_only' => false,
            'ability' => 'view-quotation',
            'model' => Estimate::class,
        ],
        [
            'title' => 'navigation.invoices',
            'group' => 'documents',
            'group_label' => 'navigation.documents',
            'priority' => 15,
            'link' => '/admin/standard-invoices',
            'icon' => 'DocumentIcon',
            'name' => 'Invoices',
            'owner_only' => false,
            'ability' => 'view-invoice',
            'model' => Invoice::class,
        ],
        [
            'title' => 'navigation.invoice_receipts',
            'group' => 'documents',
            'group_label' => 'navigation.documents',
            'priority' => 20,
            'link' => '/admin/invoices',
            'icon' => 'DocumentTextIcon',
            'name' => 'Invoice Receipts',
            'owner_only' => false,
            'ability' => 'view-invoice-receipt',
            'model' => Invoice::class,
        ],
        [
            'title' => 'navigation.lorry_receipts',
            'group' => 'documents',
            'group_label' => 'navigation.documents',
            'priority' => 25,
            'link' => '/admin/lorry-receipts',
            'icon' => 'TruckIcon',
            'name' => 'Lorry Receipts',
            'owner_only' => false,
            'ability' => 'view-lorry-receipt',
            'model' => LorryReceipt::class,
        ],
        [
            'title' => 'navigation.lr_receipts',
            'group' => 'documents',
            'group_label' => 'navigation.documents',
            'priority' => 22,
            'link' => '/admin/lr-receipts',
            'icon' => 'ClipboardDocumentListIcon',
            'name' => 'LR Receipts',
            'owner_only' => false,
            'ability' => 'view-lr-receipt',
            'model' => Invoice::class,
        ],
        [
            'title' => 'navigation.warehouse_items',
            'group' => 'documents',
            'group_label' => 'navigation.documents',
            'priority' => 23,
            'link' => '/admin/warehouse-items',
            'icon' => 'CubeIcon',
            'name' => 'Warehouse Operations',
            'owner_only' => false,
            'ability' => 'view-warehouse-item',
            'model' => WarehouseItem::class,
        ],
        [
            'title' => 'navigation.truck_master',
            'group' => 'documents',
            'group_label' => 'navigation.documents',
            'priority' => 24,
            'link' => '/admin/trucks',
            'icon' => 'TruckIcon',
            'name' => 'Fleet Management',
            'owner_only' => false,
            'ability' => 'view-truck',
            'model' => Truck::class,
        ],
        [
            'title' => 'navigation.payments',
            'group' => 'documents',
            'group_label' => 'navigation.documents',
            'priority' => 30,
            'link' => '/admin/payments',
            'icon' => 'CreditCardIcon',
            'name' => 'Payments',
            'owner_only' => false,
            'ability' => 'view-payment',
            'model' => Payment::class,
        ],
        [
            'title' => 'navigation.expenses',
            'group' => 'documents',
            'group_label' => 'navigation.documents',
            'priority' => 40,
            'link' => '/admin/expenses',
            'icon' => 'CalculatorIcon',
            'name' => 'Expenses',
            'owner_only' => false,
            'ability' => 'view-expense',
            'model' => Expense::class,
        ],
        [
            'title' => 'navigation.members',
            'group' => 'admin',
            'group_label' => 'navigation.admin',
            'priority' => 20,
            'link' => '/admin/members',
            'icon' => 'UsersIcon',
            'name' => 'Members',
            'owner_only' => true,
            'ability' => '',
            'model' => '',
        ],
        [
            'title' => 'navigation.reports',
            'group' => 'admin',
            'group_label' => 'navigation.admin',
            'priority' => 30,
            'link' => '/admin/reports',
            'icon' => 'ChartBarIcon',
            'name' => 'Reports',
            'owner_only' => false,
            'ability' => 'view-financial-reports',
            'model' => '',
        ],
        [
            'title' => 'navigation.settings',
            'group' => 'admin',
            'group_label' => 'navigation.admin',
            'priority' => 40,
            'link' => '/admin/settings',
            'icon' => 'CogIcon',
            'name' => 'Settings',
            'owner_only' => false,
            'ability' => '',
            'model' => '',
        ],
    ],

    /*
    * List of admin mode menu (super admin only)
    */
    'admin_menu' => [
        [
            'title' => 'navigation.dashboard',
            'group' => 1,
            'link' => '/admin/administration/dashboard',
            'icon' => 'ServerIcon',
            'name' => 'AdminDashboard',
            'owner_only' => false,
            'super_admin_only' => true,
            'ability' => '',
            'model' => '',
        ],
        [
            'title' => 'navigation.companies',
            'group' => 1,
            'link' => '/admin/administration/companies',
            'icon' => 'BuildingOfficeIcon',
            'name' => 'AdminCompanies',
            'owner_only' => false,
            'super_admin_only' => true,
            'ability' => '',
            'model' => '',
        ],
        [
            'title' => 'navigation.all_users',
            'group' => 1,
            'link' => '/admin/administration/users',
            'icon' => 'UsersIcon',
            'name' => 'AdminUsers',
            'owner_only' => false,
            'super_admin_only' => true,
            'ability' => '',
            'model' => '',
        ],
        [
            'title' => 'navigation.modules',
            'group' => 1,
            'link' => '/admin/administration/modules',
            'icon' => 'PuzzlePieceIcon',
            'name' => 'AdminModules',
            'owner_only' => false,
            'super_admin_only' => true,
            'ability' => '',
            'model' => '',
        ],
        [
            'title' => 'navigation.settings',
            'group' => 1,
            'link' => '/admin/administration/settings/mail-configuration',
            'icon' => 'CogIcon',
            'name' => 'AdminSettings',
            'owner_only' => false,
            'super_admin_only' => true,
            'ability' => '',
            'model' => '',
        ],
    ],

    /*
    * List of customer portal menu
    */
    'customer_menu' => [
        [
            'title' => 'navigation.dashboard',
            'link' => '/customer/dashboard',
            'icon' => '',
            'name' => '',
            'ability' => '',
            'owner_only' => false,
            'group' => '',
            'model' => '',
        ],
        [
            'title' => 'navigation.lr_receipts',
            'link' => '/customer/lr-receipts',
            'icon' => '',
            'name' => '',
            'ability' => '',
            'owner_only' => false,
            'group' => '',
            'model' => '',
        ],
        [
            'title' => 'navigation.lorry_receipts',
            'link' => '/customer/lorry-receipts',
            'icon' => '',
            'name' => '',
            'ability' => '',
            'owner_only' => false,
            'group' => '',
            'model' => '',
        ],
        [
            'title' => 'navigation.office_invoices',
            'link' => '/customer/office-invoices',
            'icon' => '',
            'name' => '',
            'ability' => '',
            'owner_only' => false,
            'group' => '',
            'model' => '',
        ],
        [
            'title' => 'navigation.invoices',
            'link' => '/customer/invoices',
            'icon' => '',
            'name' => '',
            'ability' => '',
            'owner_only' => false,
            'group' => '',
            'model' => '',
        ],
        [
            'title' => 'navigation.estimates',
            'link' => '/customer/estimates',
            'icon' => '',
            'name' => '',
            'owner_only' => false,
            'ability' => '',
            'group' => '',
            'model' => '',
        ],
        [
            'title' => 'navigation.payments',
            'link' => '/customer/payments',
            'icon' => '',
            'name' => '',
            'owner_only' => false,
            'ability' => '',
            'group' => '',
            'model' => '',
        ],
        [
            'title' => 'navigation.warehouse',
            'link' => '/customer/warehouse-items',
            'icon' => '',
            'name' => '',
            'ability' => '',
            'owner_only' => false,
            'group' => '',
            'model' => '',
        ],
        [
            'title' => 'navigation.load_trips',
            'link' => '/customer/load-trips',
            'icon' => '',
            'name' => '',
            'ability' => '',
            'owner_only' => false,
            'group' => '',
            'model' => '',
        ],
        [
            'title' => 'navigation.track_consignment',
            'link' => '/customer/track',
            'icon' => '',
            'name' => '',
            'ability' => '',
            'owner_only' => false,
            'group' => '',
            'model' => '',
        ],
        [
            'title' => 'navigation.settings',
            'link' => '/customer/settings',
            'icon' => '',
            'name' => '',
            'owner_only' => false,
            'ability' => '',
            'group' => '',
            'model' => '',
        ],
    ],

    /*
    * List of recurring invoice status
    */
    'recurring_invoice_status' => [
        'create_status' => [
            ['key' => 'settings.preferences.active', 'value' => 'ACTIVE'],
            ['key' => 'settings.preferences.on_hold', 'value' => 'ON_HOLD'],
        ],
        'update_status' => [
            ['key' => 'settings.preferences.active', 'value' => 'ACTIVE'],
            ['key' => 'settings.preferences.on_hold', 'value' => 'ON_HOLD'],
            ['key' => 'settings.preferences.completed', 'value' => 'COMPLETED'],
        ],
    ],

    /*
    * Exchange rate drivers and Currency Converter server options used to live here as
    * static arrays. Both have moved into the module Registry — built-in drivers are
    * registered by App\Providers\DriverRegistryProvider, and custom drivers can be
    * registered by modules via Registry::registerExchangeRateDriver(). The driver
    * list is served to the frontend by ConfigController via the same
    * /api/v1/config?key=exchange_rate_drivers endpoint.
    */

    /*
    * List of Custom field supported models
    */
    'custom_field_models' => [
        'Customer',
        'Estimate',
        'Invoice',
        'Payment',
        'Expense',
    ],
];
