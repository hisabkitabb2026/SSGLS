<?php

namespace App\Providers;

use App\Models\AiConversation;
use App\Models\ConsolidationGroup;
use App\Models\LoadTrip;
use App\Models\Truck;
use App\Models\TruckMaintenance;
use App\Models\TruckOdometerReading;
use App\Models\TruckServiceSchedule;
use App\Models\WarehouseItem;
use App\Policies\AiConversationPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\ConsolidationGroupPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\DashboardPolicy;
use App\Policies\EstimatePolicy;
use App\Policies\ExpensePolicy;
use App\Policies\InvoicePolicy;
use App\Policies\ItemPolicy;
use App\Policies\LoadTripPolicy;
use App\Policies\ModulesPolicy;
use App\Policies\NotePolicy;
use App\Policies\OwnerPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\RecurringInvoicePolicy;
use App\Policies\ReportPolicy;
use App\Policies\RolePolicy;
use App\Policies\SettingsPolicy;
use App\Policies\TruckMaintenancePolicy;
use App\Policies\TruckOdometerReadingPolicy;
use App\Policies\TruckPolicy;
use App\Policies\TruckServiceSchedulePolicy;
use App\Policies\UserPolicy;
use App\Policies\WarehouseItemPolicy;
use App\Support\Bouncer\BouncerDefaultScope;
use App\Support\Setup\InstallUtils;
use App\Support\Setup\InstallWizardAuth;
use Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;
use Silber\Bouncer\Database\Models as BouncerModels;
use Silber\Bouncer\Database\Role;
use View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/admin/dashboard';

    /**
     * The path to the "customer home" route for your application.
     *
     * This is used by Laravel authentication to redirect customers after login.
     *
     * @var string
     */
    public const CUSTOMER_HOME = '/customer/dashboard';

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureInstallWizardTokenAuth();

        if (InstallUtils::isDbCreated()) {
            $this->addMenus();
        }

        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(AiConversation::class, AiConversationPolicy::class);
        Gate::policy(WarehouseItem::class, WarehouseItemPolicy::class);
        Gate::policy(ConsolidationGroup::class, ConsolidationGroupPolicy::class);
        Gate::policy(LoadTrip::class, LoadTripPolicy::class);
        Gate::policy(Truck::class, TruckPolicy::class);
        Gate::policy(TruckMaintenance::class, TruckMaintenancePolicy::class);
        Gate::policy(TruckOdometerReading::class, TruckOdometerReadingPolicy::class);
        Gate::policy(TruckServiceSchedule::class, TruckServiceSchedulePolicy::class);

        View::addNamespace('pdf_templates', storage_path('app/templates/pdf'));

        $this->bootAuth();
        $this->bootBroadcast();

        // In demo mode, prevent all outgoing emails and notifications
        if (config('app.env') === 'demo') {
            Mail::fake();
            Notification::fake();
        }
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        BouncerModels::scope(new BouncerDefaultScope);
    }

    public function addMenus()
    {
        // main menu
        \Menu::make('main_menu', function ($menu) {
            foreach (config('invoiceshelf.main_menu') as $data) {
                $this->generateMenu($menu, $data);
            }
        });

        // admin menu (super admin mode)
        \Menu::make('admin_menu', function ($menu) {
            foreach (config('invoiceshelf.admin_menu') as $data) {
                $this->generateMenu($menu, $data);
            }
        });

        // setting menu
        \Menu::make('setting_menu', function ($menu) {
            foreach (config('invoiceshelf.setting_menu') as $data) {
                $this->generateMenu($menu, $data);
            }
        });

        \Menu::make('customer_portal_menu', function ($menu) {
            foreach (config('invoiceshelf.customer_menu') as $data) {
                $this->generateMenu($menu, $data);
            }
        });
    }

    public function generateMenu($menu, $data)
    {
        $menu->add($data['title'], $data['link'])
            ->data('icon', $data['icon'])
            ->data('name', $data['name'])
            ->data('owner_only', $data['owner_only'])
            ->data('super_admin_only', $data['super_admin_only'] ?? false)
            ->data('ability', $data['ability'])
            ->data('model', $data['model'])
            ->data('group', $data['group'])
            ->data('group_label', $data['group_label'] ?? '')
            ->data('priority', $data['priority'] ?? 100);
    }

    public function bootAuth()
    {

        Gate::define('create company', [CompanyPolicy::class, 'create']);
        Gate::define('transfer company ownership', [CompanyPolicy::class, 'transferOwnership']);
        Gate::define('delete company', [CompanyPolicy::class, 'delete']);

        Gate::define('manage modules', [ModulesPolicy::class, 'manageModules']);

        Gate::define('manage settings', [SettingsPolicy::class, 'manageSettings']);
        Gate::define('manage company', [SettingsPolicy::class, 'manageCompany']);
        Gate::define('manage backups', [SettingsPolicy::class, 'manageBackups']);
        Gate::define('manage file disk', [SettingsPolicy::class, 'manageFileDisk']);
        Gate::define('manage email config', [SettingsPolicy::class, 'manageEmailConfig']);
        Gate::define('manage ai config', [SettingsPolicy::class, 'manageAiConfig']);
        Gate::define('use ai', [SettingsPolicy::class, 'useAi']);
        Gate::define('manage pdf config', [SettingsPolicy::class, 'managePDFConfig']);
        Gate::define('manage notes', [NotePolicy::class, 'manageNotes']);
        Gate::define('view notes', [NotePolicy::class, 'viewNotes']);

        Gate::define('send invoice', [InvoicePolicy::class, 'send']);
        Gate::define('send estimate', [EstimatePolicy::class, 'send']);
        Gate::define('send payment', [PaymentPolicy::class, 'send']);

        // LR Receipt abilities — LR Receipts use the Invoice model with
        // template_name='lr_receipt', so they can't have their own policy.
        // These custom gates check Bouncer abilities directly.
        Gate::define('view lr receipt', function ($user, $invoice = null) {
            if ($user->isSuperAdmin() || $user->isOwner()) {
                return $invoice === null || $user->hasCompany($invoice->company_id);
            }

            return BouncerFacade::can('view-lr-receipt')
                && ($invoice === null || $user->hasCompany($invoice->company_id));
        });
        Gate::define('create lr receipt', function ($user) {
            if ($user->isSuperAdmin() || $user->isOwner()) {
                return true;
            }

            return BouncerFacade::can('create-lr-receipt');
        });
        Gate::define('edit lr receipt', function ($user, $invoice = null) {
            if ($user->isSuperAdmin() || $user->isOwner()) {
                return $invoice === null || $user->hasCompany($invoice->company_id);
            }

            return BouncerFacade::can('edit-lr-receipt')
                && ($invoice === null || $user->hasCompany($invoice->company_id));
        });
        Gate::define('delete lr receipt', function ($user, $invoice = null) {
            if ($user->isSuperAdmin() || $user->isOwner()) {
                return $invoice === null || $user->hasCompany($invoice->company_id);
            }

            return BouncerFacade::can('delete-lr-receipt')
                && ($invoice === null || $user->hasCompany($invoice->company_id));
        });

        // Invoice Receipt abilities — Invoice Receipts use the Invoice model
        // with template_name='office_invoice', so they can't have their own
        // policy.  These custom gates check Bouncer abilities directly.
        Gate::define('view invoice receipt', function ($user, $invoice = null) {
            if ($user->isSuperAdmin() || $user->isOwner()) {
                return $invoice === null || $user->hasCompany($invoice->company_id);
            }

            return BouncerFacade::can('view-invoice-receipt')
                && ($invoice === null || $user->hasCompany($invoice->company_id));
        });
        Gate::define('create invoice receipt', function ($user) {
            if ($user->isSuperAdmin() || $user->isOwner()) {
                return true;
            }

            return BouncerFacade::can('create-invoice-receipt');
        });
        Gate::define('edit invoice receipt', function ($user, $invoice = null) {
            if ($user->isSuperAdmin() || $user->isOwner()) {
                return $invoice === null || $user->hasCompany($invoice->company_id);
            }

            return BouncerFacade::can('edit-invoice-receipt')
                && ($invoice === null || $user->hasCompany($invoice->company_id));
        });
        Gate::define('delete invoice receipt', function ($user, $invoice = null) {
            if ($user->isSuperAdmin() || $user->isOwner()) {
                return $invoice === null || $user->hasCompany($invoice->company_id);
            }

            return BouncerFacade::can('delete-invoice-receipt')
                && ($invoice === null || $user->hasCompany($invoice->company_id));
        });

        Gate::define('delete multiple items', [ItemPolicy::class, 'deleteMultiple']);
        Gate::define('delete multiple customers', [CustomerPolicy::class, 'deleteMultiple']);
        Gate::define('delete multiple users', [UserPolicy::class, 'deleteMultiple']);
        Gate::define('delete multiple invoices', [InvoicePolicy::class, 'deleteMultiple']);
        Gate::define('delete multiple estimates', [EstimatePolicy::class, 'deleteMultiple']);
        Gate::define('delete multiple expenses', [ExpensePolicy::class, 'deleteMultiple']);
        Gate::define('delete multiple payments', [PaymentPolicy::class, 'deleteMultiple']);
        Gate::define('delete multiple recurring invoices', [RecurringInvoicePolicy::class, 'deleteMultiple']);

        Gate::define('view dashboard', [DashboardPolicy::class, 'view']);

        Gate::define('view report', [ReportPolicy::class, 'viewReport']);

        Gate::define('owner only', [OwnerPolicy::class, 'managedByOwner']);
    }

    public function bootBroadcast()
    {
        Broadcast::routes(['middleware' => 'api.auth']);
    }

    private function configureInstallWizardTokenAuth(): void
    {
        Sanctum::authenticateAccessTokensUsing(function ($accessToken, bool $isValid): bool {
            if (! $isValid) {
                return false;
            }

            $request = request();

            if (! $request instanceof Request || ! $request->attributes->get('install_wizard', false)) {
                return $isValid;
            }

            return $accessToken->can(InstallWizardAuth::TOKEN_ABILITY);
        });
    }
}

// Transport Domain - Auto-discovered via service provider
// See: app/Domains/Transport/TransportServiceProvider.php
