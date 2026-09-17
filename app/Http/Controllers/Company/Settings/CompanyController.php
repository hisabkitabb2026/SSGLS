<?php

namespace App\Http\Controllers\Company\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyLogoRequest;
use App\Http\Requests\CompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;

class CompanyController extends Controller
{
    public function updateCompany(CompanyRequest $request)
    {
        $company = Company::find($request->header('company'));

        $this->authorize('manage company', $company);

        $company->update($request->getCompanyPayload());

        $company->address()->updateOrCreate(['company_id' => $company->id], $request->address);

        return new CompanyResource($company);
    }

    public function uploadCompanyLogo(CompanyLogoRequest $request)
    {
        $company = Company::find($request->header('company'));

        $this->authorize('manage company', $company);

        $data = json_decode($request->company_logo);

        if (isset($request->is_company_logo_removed) && (bool) $request->is_company_logo_removed) {
            $company->clearMediaCollection('logo');
        }
        if ($data) {
            $company = Company::find($request->header('company'));

            if ($company) {
                $company->clearMediaCollection('logo');

                $company->addMediaFromBase64($data->data)
                    ->usingFileName($data->name)
                    ->toMediaCollection('logo');
            }
        }

        // Return the updated company resource so the frontend gets the new logo URL.
        // The Company model's getLogoAttribute() generates the full media URL, which
        // changes after upload — the frontend needs this to refresh the top bar logo.
        // Re-fetch to get a clean instance with the updated media relationship.
        $company = Company::find($request->header('company'));
        $company->load('address');

        return new CompanyResource($company);
    }
}
