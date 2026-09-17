<?php

use App\Domains\Customer\Http\Controllers\AddressController;
use App\Domains\Customer\Http\Controllers\CustomerController;
use App\Domains\Expense\Http\Controllers\CategoryController;
use App\Domains\Expense\Http\Controllers\ExpenseController;
use App\Domains\Product\Http\Controllers\ItemController;
use App\Domains\Product\Http\Controllers\UnitController;
use App\Domains\Transport\Http\Controllers\LorryReceiptController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\BackupsController;
use App\Http\Controllers\Admin\CompaniesController;
use App\Http\Controllers\Admin\CountriesController;
use App\Http\Controllers\Admin\CurrenciesController;
use App\Http\Controllers\Admin\FontController;
use App\Http\Controllers\Admin\Modules\ModuleInstallationController;
use App\Http\Controllers\Admin\Modules\ModulesController;
use App\Http\Controllers\Admin\Settings\AiConfigurationController;
use App\Http\Controllers\Admin\Settings\DiskController;
use App\Http\Controllers\Admin\Settings\MailConfigurationController;
use App\Http\Controllers\Admin\Settings\PDFConfigurationController;
use App\Http\Controllers\Admin\Settings\SettingsController;
use App\Http\Controllers\Admin\UpdateController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\AppVersionController;
use App\Http\Controllers\Company\Ai\ChatController as AiChatController;
use App\Http\Controllers\Company\Ai\ConversationController as AiConversationController;
use App\Http\Controllers\Company\Ai\GenerationController as AiGenerationController;
use App\Http\Controllers\Company\Auth\AuthController;
use App\Http\Controllers\Company\Auth\ForgotPasswordController;
use App\Http\Controllers\Company\Auth\InvitationRegistrationController;
use App\Http\Controllers\Company\Auth\ResetPasswordController;
use App\Http\Controllers\Company\Consolidation\ConsolidationGroupController;
use App\Http\Controllers\Company\Customer\CustomersController;
use App\Http\Controllers\Company\Customer\CustomerStatsController;
use App\Http\Controllers\Company\CustomField\CustomFieldsController;
use App\Http\Controllers\Company\Dashboard\DashboardController;
use App\Http\Controllers\Company\Estimate\EstimatesController;
use App\Http\Controllers\Company\Estimate\EstimateTemplatesController;
use App\Http\Controllers\Company\ExchangeRate\ExchangeRateProviderController;
use App\Http\Controllers\Company\Expense\ExpenseCategoriesController;
use App\Http\Controllers\Company\Expense\ExpensesController;
use App\Http\Controllers\Company\General\BootstrapController;
use App\Http\Controllers\Company\General\ConfigController;
use App\Http\Controllers\Company\General\FormatsController;
use App\Http\Controllers\Company\General\InvitationResponseController;
use App\Http\Controllers\Company\General\NotesController;
use App\Http\Controllers\Company\General\SearchController;
use App\Http\Controllers\Company\General\SerialNumberController;
use App\Http\Controllers\Company\Invoice\InvoicesController;
use App\Http\Controllers\Company\Invoice\InvoiceTemplatesController;
use App\Http\Controllers\Company\Invoice\LorryPartyProfileController;
use App\Http\Controllers\Company\Invoice\LrReceiptAutoFillController;
use App\Http\Controllers\Company\Invoice\LrReceiptController;
use App\Http\Controllers\Company\Item\ItemsController;
use App\Http\Controllers\Company\Item\UnitsController;
use App\Http\Controllers\Company\LoadTrip\LoadTripController;
use App\Http\Controllers\Company\Members\MembersController;
use App\Http\Controllers\Company\Modules\CompanyModulesController;
use App\Http\Controllers\Company\Modules\ModuleSettingsController;
use App\Http\Controllers\Company\Payment\PaymentMethodsController;
use App\Http\Controllers\Company\Payment\PaymentsController;
use App\Http\Controllers\Company\RecurringInvoice\RecurringInvoiceController;
use App\Http\Controllers\Company\RecurringInvoice\RecurringInvoiceFrequencyController;
use App\Http\Controllers\Company\Role\AbilitiesController;
use App\Http\Controllers\Company\Role\RolesController;
use App\Http\Controllers\Company\Settings\CompanyAiConfigurationController;
use App\Http\Controllers\Company\Settings\CompanyController;
use App\Http\Controllers\Company\Settings\CompanyMailConfigurationController;
use App\Http\Controllers\Company\Settings\CompanySettingsController;
use App\Http\Controllers\Company\Settings\CustomTemplateController;
use App\Http\Controllers\Company\Settings\InvitationController;
use App\Http\Controllers\Company\Settings\TaxTypesController;
use App\Http\Controllers\Company\Settings\UserProfileController;
use App\Http\Controllers\Company\Truck\FleetReportController;
use App\Http\Controllers\Company\Truck\TruckController;
use App\Http\Controllers\Company\Truck\TruckMaintenanceController;
use App\Http\Controllers\Company\Truck\TruckOdometerReadingController;
use App\Http\Controllers\Company\Truck\TruckServiceScheduleController;
use App\Http\Controllers\Company\WarehouseItem\WarehouseItemController;
use App\Http\Controllers\Company\WarehouseItem\WarehouseReportController;
use App\Http\Controllers\CustomerPortal\Auth\ForgotPasswordController as AuthForgotPasswordController;
use App\Http\Controllers\CustomerPortal\Auth\ResetPasswordController as AuthResetPasswordController;
use App\Http\Controllers\CustomerPortal\Estimate\AcceptEstimateController as CustomerAcceptEstimateController;
use App\Http\Controllers\CustomerPortal\Estimate\EstimatesController as CustomerEstimatesController;
use App\Http\Controllers\CustomerPortal\Expense\ExpensesController as CustomerExpensesController;
use App\Http\Controllers\CustomerPortal\General\BootstrapController as CustomerBootstrapController;
use App\Http\Controllers\CustomerPortal\General\DashboardController as CustomerDashboardController;
use App\Http\Controllers\CustomerPortal\General\ProfileController as CustomerProfileController;
use App\Http\Controllers\CustomerPortal\Invoice\InvoicesController as CustomerInvoicesController;
use App\Http\Controllers\CustomerPortal\Invoice\LorryReceiptsController as CustomerLorryReceiptsController;
use App\Http\Controllers\CustomerPortal\Invoice\LrReceiptsController as CustomerLrReceiptsController;
use App\Http\Controllers\CustomerPortal\Invoice\OfficeInvoicesController as CustomerOfficeInvoicesController;
use App\Http\Controllers\CustomerPortal\Payment\PaymentMethodController;
use App\Http\Controllers\CustomerPortal\Payment\PaymentsController as CustomerPaymentsController;
use App\Http\Controllers\CustomerPortal\Tracking\ConsignmentTrackingController as CustomerConsignmentTrackingController;
use App\Http\Controllers\CustomerPortal\Transport\LoadTripsController as CustomerLoadTripsController;
use App\Http\Controllers\CustomerPortal\Warehouse\WarehouseItemsController as CustomerWarehouseItemsController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\Setup\AiConfigurationController as InstallerAiConfigurationController;
use App\Http\Controllers\Setup\AppDomainController;
use App\Http\Controllers\Setup\DatabaseConfigurationController;
use App\Http\Controllers\Setup\FilePermissionsController;
use App\Http\Controllers\Setup\FinishController;
use App\Http\Controllers\Setup\LanguagesController;
use App\Http\Controllers\Setup\LoginController;
use App\Http\Controllers\Setup\OnboardingWizardController;
use App\Http\Controllers\Setup\RequirementsController;
use App\Http\Controllers\Webhook\CronJobController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// ping
// ----------------------------------

Route::get('ping', function () {
    return response()->json([
        'success' => 'invoiceshelf-self-hosted',
    ]);
})->name('ping');

// Health check endpoint
// ----------------------------------
Route::get('health', HealthCheckController::class)->name('health');

// Version 1 endpoints
// --------------------------------------
Route::prefix('/v1')->group(function () {

    // App version
    // ----------------------------------

    Route::get('/app/version', AppVersionController::class);

    // Authentication & Password Reset
    // ----------------------------------

    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);

        Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

        // Send reset password mail
        Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->middleware('throttle:10,2');

        // handle reset password form process
        Route::post('reset/password', [ResetPasswordController::class, 'reset']);
    });

    // Invitation Registration (public)
    // ----------------------------------

    Route::get('/invitations/{token}/details', [InvitationRegistrationController::class, 'details']);
    Route::post('/auth/register-with-invitation', [InvitationRegistrationController::class, 'register']);

    // Countries
    // ----------------------------------

    Route::get('/countries', CountriesController::class);

    // Onboarding
    // ----------------------------------

    Route::middleware(['redirect-if-installed'])->prefix('installation')->group(function () {
        Route::get('/wizard-step', [OnboardingWizardController::class, 'getStep']);

        Route::post('/wizard-step', [OnboardingWizardController::class, 'updateStep']);

        Route::post('/wizard-language', [OnboardingWizardController::class, 'saveLanguage']);

        Route::get('/languages', [LanguagesController::class, 'languages']);

        Route::get('/requirements', [RequirementsController::class, 'requirements']);

        Route::get('/permissions', [FilePermissionsController::class, 'permissions']);

        Route::post('/database/config', [DatabaseConfigurationController::class, 'saveDatabaseEnvironment']);

        Route::get('/database/config', [DatabaseConfigurationController::class, 'getDatabaseEnvironment']);

        Route::put('/set-domain', AppDomainController::class);

        Route::get('/ai/config', [InstallerAiConfigurationController::class, 'show']);
        Route::post('/ai/config', [InstallerAiConfigurationController::class, 'save']);

        Route::post('/login', LoginController::class);

        Route::post('/finish', FinishController::class);
    });

    // Super Admin
    // ----------------------------------

    Route::middleware(['auth:sanctum', 'super-admin'])->prefix('super-admin')->group(function () {
        Route::get('dashboard', [AdminDashboardController::class, 'index']);
        Route::get('companies', [CompaniesController::class, 'index']);
        Route::get('companies/{company}', [CompaniesController::class, 'show']);
        Route::put('companies/{company}', [CompaniesController::class, 'update']);

        Route::get('users', [UsersController::class, 'index']);
        Route::get('users/{user}', [UsersController::class, 'show']);
        Route::put('users/{user}', [UsersController::class, 'update']);
        Route::post('users/{user}/impersonate', [UsersController::class, 'impersonate']);
    });

    // Stop impersonation - uses auth:sanctum only (the impersonated user's token, not super-admin)
    Route::middleware(['auth:sanctum'])->prefix('super-admin')->group(function () {
        Route::post('stop-impersonating', [UsersController::class, 'stopImpersonating']);
    });

    Route::middleware(['auth:sanctum', 'company', 'throttle:300,1'])->group(function () {
        Route::middleware(['bouncer'])->group(function () {

            // Bootstrap
            // ----------------------------------

            Route::get('/bootstrap', BootstrapController::class);

            // Invitations (user-scoped — respond to invitations)
            // ----------------------------------

            Route::get('/invitations/pending', [InvitationResponseController::class, 'pending']);
            Route::post('/invitations/{invitation:token}/accept', [InvitationResponseController::class, 'accept']);
            Route::post('/invitations/{invitation:token}/decline', [InvitationResponseController::class, 'decline']);

            // Currencies
            // ----------------------------------

            Route::prefix('/currencies')->group(function () {
                Route::get('/used', [ExchangeRateProviderController::class, 'usedCurrenciesWithoutRate']);

                Route::post('/bulk-update-exchange-rate', [ExchangeRateProviderController::class, 'bulkUpdate']);
            });

            // Dashboard
            // ----------------------------------

            Route::get('/dashboard', DashboardController::class);

            // Auth check
            // ----------------------------------

            Route::get('/auth/check', [AuthController::class, 'check']);

            // Search users
            // ----------------------------------

            Route::get('/search', SearchController::class);

            Route::get('/search/user', [SearchController::class, 'users']);

            // MISC
            // ----------------------------------

            Route::get('/config', ConfigController::class);

            Route::get('/currencies', CurrenciesController::class);

            Route::get('/timezones', [FormatsController::class, 'timezones']);

            Route::get('/date/formats', [FormatsController::class, 'dateFormats']);

            Route::get('/time/formats', [FormatsController::class, 'timeFormats']);

            Route::get('/next-number', [SerialNumberController::class, 'nextNumber']);

            Route::get('/number-placeholders', [SerialNumberController::class, 'placeholders']);

            Route::get('/current-company', [BootstrapController::class, 'currentCompany']);

            // Company Invitations (company-scoped — send invitations)
            // ----------------------------------

            Route::apiResource('company-invitations', InvitationController::class)->only(['index', 'store', 'destroy']);

            // Customers
            // ----------------------------------

            Route::post('/customers/delete', [CustomersController::class, 'delete']);

            Route::get('customers/{customer}/stats', CustomerStatsController::class);

            Route::resource('customers', CustomersController::class);

            // Items
            // ----------------------------------

            Route::post('/items/delete', [ItemsController::class, 'delete']);

            Route::resource('items', ItemsController::class);

            Route::resource('units', UnitsController::class);

            // Invoices (with stricter throttling for mutations)
            // -------------------------------------------------
            Route::get('/invoices/templates', InvoiceTemplatesController::class);
            Route::get('/invoices/next-number', [InvoicesController::class, 'getNextNumber']);
            Route::get('/invoices/find-by-number', [InvoicesController::class, 'findByInvoiceNumber']);

            Route::get('invoices', [InvoicesController::class, 'index']);
            Route::get('invoices/{invoice}', [InvoicesController::class, 'show'])->whereNumber('invoice');

            Route::middleware('throttle:100,1')->group(function () {
                Route::post('/invoices/{invoice}/send/preview', [InvoicesController::class, 'sendPreview']);
                Route::post('/invoices/{invoice}/send', [InvoicesController::class, 'send']);
                Route::post('/invoices/{invoice}/clone', [InvoicesController::class, 'clone']);
                Route::post('/invoices/{invoice}/convert-to-estimate', [InvoicesController::class, 'convertToEstimate']);
                Route::post('/invoices/{invoice}/status', [InvoicesController::class, 'changeStatus']);
                Route::post('/invoices/delete', [InvoicesController::class, 'delete']);

                Route::post('invoices', [InvoicesController::class, 'store']);
                Route::put('invoices/{invoice}', [InvoicesController::class, 'update']);
                Route::delete('invoices/{invoice}', [InvoicesController::class, 'destroy']);
            });

            // Transport module - LR Receipt Auto Fill (AI-powered)
            Route::post('/invoices/auto-fill', LrReceiptAutoFillController::class);

            // Transport module - Lorry Receipts

            Route::post('/lorry-receipts/delete', [LorryReceiptController::class, 'destroy']);
            Route::apiResource('lorry-receipts', LorryReceiptController::class);

            // Transport module - LR Receipts (invoices with template_name = lr_receipt)
            Route::get('/lr-receipts', [LrReceiptController::class, 'index']);
            Route::get('/lr-receipts/{id}', [LrReceiptController::class, 'show']);
            Route::get('/lr-receipts/{id}/lorry-receipt-status', [LrReceiptController::class, 'lorryReceiptStatus']);

            // Transport module - Lorry Party Profiles (Owner/Driver/Broker)
            Route::apiResource('lorry-party-profiles', LorryPartyProfileController::class);

            // Transport module - Warehouse Items (Goods Storage & Consolidation)
            Route::post('/warehouse-items/lookup/lr', [WarehouseItemController::class, 'lookupLr']);
            Route::get('/warehouse-items/lr-options', [WarehouseItemController::class, 'lrOptions']);
            Route::get('/warehouse-items/destinations', [WarehouseItemController::class, 'destinations']);
            Route::get('/warehouse-items/warehouses', [WarehouseItemController::class, 'warehouses']);
            Route::get('/warehouse-items/dashboard', [WarehouseItemController::class, 'dashboard']);
            Route::get('/warehouse-items/destination/{destination}/summary', [WarehouseItemController::class, 'destinationSummary']);
            Route::get('/warehouse-items/destination/{destination}', [WarehouseItemController::class, 'byDestination']);
            Route::get('/warehouse-items/location/{location}', [WarehouseItemController::class, 'byLocation']);
            Route::patch('/warehouse-items/{id}/status', [WarehouseItemController::class, 'updateStatus']);
            Route::get('/warehouse-items/overdue', [WarehouseItemController::class, 'overdue']);
            Route::get('/warehouse-items/aging-report', [WarehouseItemController::class, 'agingReport']);
            Route::apiResource('warehouse-items', WarehouseItemController::class)->whereNumber('warehouse_item');

            // Transport module - Warehouse Reports (Operational)
            Route::get('/warehouse-reports/summary', [WarehouseReportController::class, 'summary']);
            Route::get('/warehouse-reports/lr-summary', [WarehouseReportController::class, 'lrSummary']);
            Route::get('/warehouse-reports/aging', [WarehouseReportController::class, 'aging']);
            Route::get('/warehouse-reports/destinations', [WarehouseReportController::class, 'destinations']);
            Route::get('/warehouse-reports/overdue', [WarehouseReportController::class, 'overdue']);

            // Transport module - Consolidation Groups (Part-Load consolidation)
            Route::get('/consolidation-groups/candidates', [ConsolidationGroupController::class, 'candidates']);
            Route::post('/consolidation-groups/{id}/items', [ConsolidationGroupController::class, 'addItem']);
            Route::post('/consolidation-groups/{id}/items/{itemId}/split', [ConsolidationGroupController::class, 'splitItem']);
            Route::delete('/consolidation-groups/{id}/items/{itemId}', [ConsolidationGroupController::class, 'removeItem']);
            Route::patch('/consolidation-groups/{id}/ready', [ConsolidationGroupController::class, 'markReady']);
            Route::apiResource('consolidation-groups', ConsolidationGroupController::class)->whereNumber('consolidation_group');

            // Transport module - Load Trips (Truck Dispatches)
            Route::get('/trucks/dashboard', [TruckController::class, 'dashboard']);
            Route::get('/fleet-reports/summary', FleetReportController::class);
            Route::get('/fleet-reports/trips', [FleetReportController::class, 'trips']);
            Route::get('/fleet-reports/report-summary', [FleetReportController::class, 'reportSummary']);
            Route::get('/fleet-reports/truck-performance', [FleetReportController::class, 'truckPerformance']);
            Route::get('/fleet-reports/expense-by-category', [FleetReportController::class, 'expenseByCategory']);
            Route::get('/fleet-reports/route-performance', [FleetReportController::class, 'routePerformance']);

            // Compliance/document management removed — not needed for current phase.
            // Route::get('/trucks/{id}/documents/{document}', [TruckController::class, 'showDocument'])->whereNumber('id');
            Route::apiResource('trucks', TruckController::class)->whereNumber('truck');
            Route::apiResource('truck-maintenances', TruckMaintenanceController::class)->whereNumber('truck_maintenance');
            Route::get('/truck-odometer-readings/{id}/image', [TruckOdometerReadingController::class, 'showImage']);
            Route::apiResource('truck-odometer-readings', TruckOdometerReadingController::class)->only(['index', 'show', 'store'])->whereNumber('truck_odometer_reading');
            Route::post('/truck-service-schedules/{id}/complete', [TruckServiceScheduleController::class, 'complete'])->whereNumber('id');
            Route::apiResource('truck-service-schedules', TruckServiceScheduleController::class)->only(['index', 'store'])->whereNumber('truck_service_schedule');
            Route::patch('/load-trips/{id}/dispatch', [LoadTripController::class, 'dispatchTrip']);
            Route::patch('/load-trips/{id}/deliver', [LoadTripController::class, 'markDelivered']);
            Route::patch('/load-trips/{id}/cancel', [LoadTripController::class, 'cancelTrip']);
            Route::apiResource('load-trips', LoadTripController::class)->whereNumber('load_trip');

            // Recurring Invoice
            // -------------------------------------------------

            Route::get('/recurring-invoice-frequency', RecurringInvoiceFrequencyController::class);

            Route::post('/recurring-invoices/delete', [RecurringInvoiceController::class, 'delete']);

            Route::apiResource('recurring-invoices', RecurringInvoiceController::class);

            // Estimates
            // -------------------------------------------------

            Route::get('/estimates/{estimate}/send/preview', [EstimatesController::class, 'sendPreview']);

            Route::post('/estimates/{estimate}/send', [EstimatesController::class, 'send']);

            Route::post('/estimates/{estimate}/clone', [EstimatesController::class, 'clone']);

            Route::post('/estimates/{estimate}/status', [EstimatesController::class, 'changeStatus']);

            Route::post('/estimates/{estimate}/convert-to-invoice', [EstimatesController::class, 'convertToInvoice']);

            Route::get('/estimates/templates', EstimateTemplatesController::class);

            Route::post('/estimates/delete', [EstimatesController::class, 'delete']);

            Route::get('/estimates/station-rates', [EstimatesController::class, 'getStationRates']);

            Route::apiResource('estimates', EstimatesController::class);

            // Expenses
            // ----------------------------------

            Route::get('/expenses/{expense}/show/receipt', [ExpensesController::class, 'showReceipt']);

            Route::post('/expenses/{expense}/upload/receipts', [ExpensesController::class, 'uploadReceipt']);

            Route::post('/expenses/delete', [ExpensesController::class, 'delete']);

            Route::apiResource('expenses', ExpensesController::class);

            Route::apiResource('categories', ExpenseCategoriesController::class);

            // Payments
            // ----------------------------------

            Route::get('/payments/{payment}/send/preview', [PaymentsController::class, 'sendPreview']);

            Route::post('/payments/{payment}/send', [PaymentsController::class, 'send']);

            Route::post('/payments/delete', [PaymentsController::class, 'delete']);

            Route::apiResource('payments', PaymentsController::class);

            Route::apiResource('payment-methods', PaymentMethodsController::class);

            // Custom fields
            // ----------------------------------

            Route::resource('custom-fields', CustomFieldsController::class);

            // Backup & Disk
            // ----------------------------------

            Route::apiResource('backups', BackupsController::class);

            Route::apiResource('/disks', DiskController::class);

            Route::get('download-backup', [BackupsController::class, 'download']);

            Route::get('/disk/drivers', [DiskController::class, 'getDiskDrivers']);
            Route::get('/disk/purposes', [DiskController::class, 'getDiskPurposes']);
            Route::put('/disk/purposes', [DiskController::class, 'updateDiskPurposes']);

            // Fonts
            // ----------------------------------

            Route::get('/fonts/status', [FontController::class, 'status']);
            Route::post('/fonts/{package}/install', [FontController::class, 'install']);

            // Exchange Rate
            // ----------------------------------

            Route::get('/currencies/{currency}/exchange-rate', [ExchangeRateProviderController::class, 'getRate']);

            Route::get('/currencies/{currency}/active-provider', [ExchangeRateProviderController::class, 'activeProvider']);

            Route::get('/used-currencies', [ExchangeRateProviderController::class, 'usedCurrencies']);

            Route::get('/supported-currencies', [ExchangeRateProviderController::class, 'supportedCurrencies']);

            Route::apiResource('exchange-rate-providers', ExchangeRateProviderController::class);

            // Settings
            // ----------------------------------

            Route::get('/me', [UserProfileController::class, 'show']);

            Route::put('/me', [UserProfileController::class, 'update']);

            Route::get('/me/settings', [UserProfileController::class, 'showSettings']);

            Route::put('/me/settings', [UserProfileController::class, 'updateSettings']);

            Route::post('/me/upload-avatar', [UserProfileController::class, 'uploadAvatar']);
            Route::post('/me/upload-signature', [UserProfileController::class, 'uploadSignature']);

            Route::put('/company', [CompanyController::class, 'updateCompany']);

            Route::post('/company/upload-logo', [CompanyController::class, 'uploadCompanyLogo']);

            Route::get('/company/settings', [CompanySettingsController::class, 'show']);

            Route::post('/company/settings', [CompanySettingsController::class, 'update']);

            Route::get('/settings', [SettingsController::class, 'show']);

            Route::post('/settings', [SettingsController::class, 'update']);

            Route::get('/company/has-transactions', [CompanySettingsController::class, 'checkTransactions']);

            // Mails
            // ----------------------------------

            Route::get('/mail/drivers', [MailConfigurationController::class, 'getMailDrivers']);

            Route::get('/mail/config', [MailConfigurationController::class, 'getMailEnvironment']);

            Route::post('/mail/config', [MailConfigurationController::class, 'saveMailEnvironment']);

            Route::post('/mail/test', [MailConfigurationController::class, 'testEmailConfig']);

            Route::get('/company/mail/config', [CompanyMailConfigurationController::class, 'getDefaultConfig']);

            Route::get('/company/mail/company-config', [CompanyMailConfigurationController::class, 'getMailConfig']);
            Route::post('/company/mail/company-config', [CompanyMailConfigurationController::class, 'saveMailConfig']);
            Route::post('/company/mail/company-test', [CompanyMailConfigurationController::class, 'testMailConfig']);

            // AI Configuration
            // ----------------------------------

            Route::get('/ai/drivers', [AiConfigurationController::class, 'getDrivers']);
            Route::get('/ai/config', [AiConfigurationController::class, 'getConfig']);
            Route::post('/ai/config', [AiConfigurationController::class, 'saveConfig']);
            Route::post('/ai/test', [AiConfigurationController::class, 'testConnection']);

            Route::get('/company/ai/config', [CompanyAiConfigurationController::class, 'getConfig']);
            Route::post('/company/ai/config', [CompanyAiConfigurationController::class, 'saveConfig']);
            Route::post('/company/ai/test', [CompanyAiConfigurationController::class, 'testConnection']);

            // AI Chat + text generation — rate-limited via the 'ai' limiter defined in RouteServiceProvider
            Route::middleware('throttle:ai')->group(function () {
                Route::post('/ai/chat', AiChatController::class);
                Route::get('/ai/conversations', [AiConversationController::class, 'index']);
                Route::get('/ai/conversations/{id}', [AiConversationController::class, 'show']);
                Route::patch('/ai/conversations/{id}', [AiConversationController::class, 'update']);
                Route::delete('/ai/conversations/{id}', [AiConversationController::class, 'destroy']);

                Route::post('/ai/generate', AiGenerationController::class);
            });

            // PDF Generation
            // ----------------------------------

            Route::get('/pdf/drivers', [PDFConfigurationController::class, 'getDrivers']);

            Route::get('/pdf/config', [PDFConfigurationController::class, 'getEnvironment']);

            Route::post('/pdf/config', [PDFConfigurationController::class, 'saveEnvironment']);

            Route::apiResource('notes', NotesController::class);

            // Tax Types
            // ----------------------------------

            Route::apiResource('tax-types', TaxTypesController::class);

            // Custom PDF Templates
            // ----------------------------------

            Route::get('/custom-templates', [CustomTemplateController::class, 'index']);
            Route::get('/custom-templates/{templateName}/download', [CustomTemplateController::class, 'download']);
            Route::get('/custom-templates/builtin/{documentType}/download', [CustomTemplateController::class, 'downloadBuiltin']);
            Route::post('/custom-templates', [CustomTemplateController::class, 'upload']);
            Route::delete('/custom-templates/{templateName}', [CustomTemplateController::class, 'destroy']);

            // Roles
            // ----------------------------------

            Route::get('abilities', AbilitiesController::class);

            Route::apiResource('roles', RolesController::class);
        });

        // Self Update
        // ----------------------------------
        // Disabled inside the official Docker image — containers upgrade via
        // `docker compose pull`, not the in-app updater (see EnsureNotContainerized).

        Route::middleware('not-containerized')->group(function () {
            Route::get('/check/update', [UpdateController::class, 'checkVersion']);
            Route::post('/update/download', [UpdateController::class, 'download']);
            Route::post('/update/unzip', [UpdateController::class, 'unzip']);
            Route::post('/update/copy', [UpdateController::class, 'copy']);
            Route::post('/update/delete', [UpdateController::class, 'delete']);
            Route::post('/update/clean', [UpdateController::class, 'clean']);
            Route::post('/update/migrate', [UpdateController::class, 'migrate']);
            Route::post('/update/finish', [UpdateController::class, 'finish']);
        });

        // Companies
        // -------------------------------------------------

        Route::post('companies', [CompaniesController::class, 'store']);

        Route::post('/transfer/ownership/{user}', [CompanySettingsController::class, 'transferOwnership']);

        Route::post('companies/delete', [CompaniesController::class, 'destroy']);

        Route::get('companies', [CompaniesController::class, 'userCompanies']);

        // Users
        // ----------------------------------

        Route::post('/members/delete', [MembersController::class, 'delete']);

        Route::apiResource('/members', MembersController::class);

        // Modules
        // ----------------------------------

        Route::prefix('/modules')->group(function () {
            Route::get('/', [ModulesController::class, 'index']);
            Route::get('/check', [ModulesController::class, 'checkToken']);
            Route::get('/{module}', [ModulesController::class, 'show']);
            Route::post('/{module}/enable', [ModulesController::class, 'enable']);
            Route::post('/{module}/disable', [ModulesController::class, 'disable']);

            Route::post('/download', [ModuleInstallationController::class, 'download']);
            Route::post('/upload', [ModuleInstallationController::class, 'upload']);
            Route::post('/unzip', [ModuleInstallationController::class, 'unzip']);
            Route::post('/copy', [ModuleInstallationController::class, 'copy']);
            Route::post('/complete', [ModuleInstallationController::class, 'complete']);

            // Per-slug settings (schema-driven, per-company storage)
            Route::get('/{slug}/settings', [ModuleSettingsController::class, 'show']);
            Route::put('/{slug}/settings', [ModuleSettingsController::class, 'update']);
        });

        // Company-context Active Modules index (read-only, lists every
        // instance-activated module with a has_settings flag)
        Route::get('/company-modules', [CompanyModulesController::class, 'index']);

    });

    Route::prefix('/{company:slug}/customer')->group(function () {

        // Authentication & Password Reset
        // ----------------------------------

        Route::prefix('auth')->group(function () {

            // Send reset password mail
            Route::post('password/email', [AuthForgotPasswordController::class, 'sendResetLinkEmail']);

            // handle reset password form process
            Route::post('reset/password', [AuthResetPasswordController::class, 'reset'])->name('customer.password.reset');
        });

        // Invoices, Estimates, Payments and Expenses endpoints
        // -------------------------------------------------------

        Route::middleware(['auth:customer', 'customer-portal'])->group(function () {
            Route::get('/bootstrap', CustomerBootstrapController::class);

            Route::get('/dashboard', CustomerDashboardController::class);

            Route::get('invoices', [CustomerInvoicesController::class, 'index']);

            Route::get('invoices/{id}', [CustomerInvoicesController::class, 'show']);

            Route::post('/estimate/{estimate}/status', CustomerAcceptEstimateController::class);

            Route::get('estimates', [CustomerEstimatesController::class, 'index']);

            Route::get('estimates/{id}', [CustomerEstimatesController::class, 'show']);

            Route::get('payments', [CustomerPaymentsController::class, 'index']);

            Route::get('payments/{id}', [CustomerPaymentsController::class, 'show']);

            Route::get('/payment-method', PaymentMethodController::class);

            Route::get('expenses', [CustomerExpensesController::class, 'index']);

            Route::get('expenses/{id}', [CustomerExpensesController::class, 'show']);

            Route::post('/profile', [CustomerProfileController::class, 'updateProfile']);

            Route::get('/me', [CustomerProfileController::class, 'getUser']);

            Route::get('/countries', CountriesController::class);

            // Transport module — LR Receipts (dockets)
            Route::get('lr-receipts', [CustomerLrReceiptsController::class, 'index']);
            Route::get('lr-receipts/{id}', [CustomerLrReceiptsController::class, 'show']);

            // Transport module — Lorry Receipts (truck payment records)
            Route::get('lorry-receipts', [CustomerLorryReceiptsController::class, 'index']);
            Route::get('lorry-receipts/{id}', [CustomerLorryReceiptsController::class, 'show']);

            // Transport module — Office Invoices (Invoice Receipts)
            Route::get('office-invoices', [CustomerOfficeInvoicesController::class, 'index']);
            Route::get('office-invoices/{id}', [CustomerOfficeInvoicesController::class, 'show']);

            // Transport module — Warehouse Items (goods tracking)
            Route::get('warehouse-items', [CustomerWarehouseItemsController::class, 'index']);
            Route::get('warehouse-items/{id}', [CustomerWarehouseItemsController::class, 'show']);

            // Transport module — Load Trips (truck dispatch tracking)
            Route::get('load-trips', [CustomerLoadTripsController::class, 'index']);
            Route::get('load-trips/{id}', [CustomerLoadTripsController::class, 'show']);

            // Transport module — Consignment Tracking (unified timeline)
            Route::get('track/{docketNumber}', CustomerConsignmentTrackingController::class);
        });
    });
});

// Domain Routes - Customer Domain
Route::middleware(['auth:sanctum', 'company'])->group(function () {
    Route::apiResource('domain/customers', CustomerController::class);
    Route::apiResource('domain/addresses', AddressController::class);
});

// Domain Routes - Product Domain
Route::middleware(['auth:sanctum', 'company'])->group(function () {
    Route::apiResource('domain/items', ItemController::class);
    Route::apiResource('domain/units', UnitController::class);
});

// Domain Routes - Expense Domain
Route::middleware(['auth:sanctum', 'company'])->group(function () {
    Route::apiResource('domain/expenses', ExpenseController::class);
    Route::apiResource('domain/expense-categories', CategoryController::class);
});

Route::get('/cron', CronJobController::class)->middleware('cron-job');
