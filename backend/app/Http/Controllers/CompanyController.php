<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCompanyRequest;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for company (tenant) settings.
 *
 * Provides endpoints for reading and updating the current tenant's company data.
 * The Company model IS the tenant — users.tenant_id points to companies.id.
 */
class CompanyController extends Controller
{
    /**
     * Display the current tenant's company data.
     *
     * GET /api/v1/company
     */
    public function show(Request $request): JsonResponse
    {
        $company = Company::find($request->user()->tenant_id);

        if (! $company) {
            return response()->json([
                'error' => [
                    'code' => 'COMPANY_NOT_FOUND',
                    'message' => 'Company not found.',
                ],
            ], 404);
        }

        return response()->json([
            'data' => $this->formatCompany($company),
        ]);
    }

    /**
     * Update the current tenant's company data.
     *
     * PUT /api/v1/company
     */
    public function update(UpdateCompanyRequest $request): JsonResponse
    {
        $company = Company::find($request->user()->tenant_id);

        if (! $company) {
            return response()->json([
                'error' => [
                    'code' => 'COMPANY_NOT_FOUND',
                    'message' => 'Company not found.',
                ],
            ], 404);
        }

        $company->update($request->validated());

        return response()->json([
            'data' => $this->formatCompany($company),
        ]);
    }

    /**
     * Format a company model for API response.
     */
    protected function formatCompany(Company $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'email_domain' => $company->email_domain,
            'subscription_status' => $company->subscription_status,
            'settings' => $company->settings,
        ];
    }
}
