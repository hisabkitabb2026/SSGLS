<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $templates = Storage::disk('views')->files('/app/pdf/invoice');

        foreach ($templates as $key => $template) {
            $templateName = Str::before(basename($template), '.blade.php');
            if (! file_exists(resource_path("/static/img/PDF/{$templateName}.png"))) {
                $srcPath = public_path("/assets/img/PDF/{$templateName}.png");
                if (file_exists($srcPath)) {
                    copy($srcPath, public_path("/build/img/PDF/{$templateName}.png"));
                    copy($srcPath, resource_path("/static/img/PDF/{$templateName}.png"));
                }
            }
        }

        $templates = Storage::disk('views')->files('/app/pdf/estimate');

        foreach ($templates as $key => $template) {
            $templateName = Str::before(basename($template), '.blade.php');
            if (! file_exists(resource_path("/static/img/PDF/{$templateName}.png"))) {
                $srcPath = public_path("/assets/img/PDF/{$templateName}.png");
                if (file_exists($srcPath)) {
                    copy($srcPath, public_path("/build/img/PDF/{$templateName}.png"));
                    copy($srcPath, resource_path("/static/img/PDF/{$templateName}.png"));
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
