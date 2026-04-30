<?php

namespace App\Http\Controllers;

use App\Exceptions\Business\CompanyNotFoundException;
use App\Exceptions\Business\DocumentExtractionFailedException;
use App\Http\Requests\Business\ExtractDocumentRequest;
use App\Http\Requests\Business\StoreCompanyRequest;
use App\Http\Requests\Business\UpdateCompanyRequest;
use App\Models\Company;
use App\Services\Business\CompanyService;
use App\Services\Business\DocumentExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyService $companyService,
        private readonly DocumentExtractionService $documentExtractionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['business_type_id', 'search']);
        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $page = max(1, (int) $request->input('page', 1));

        $result = $this->companyService->list($filters, $perPage, $page);

        return response()->json([
            'success' => true,
            'message' => 'Companies retrieved successfully.',
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $userId = $request->get('auth_user_id');
        $company = $this->companyService->create($request->validated(), $userId);

        return response()->json([
            'success' => true,
            'message' => 'Company created successfully.',
            'data' => $company,
        ], 201);
    }

    /**
     * @throws CompanyNotFoundException
     */
    public function show(string $id): JsonResponse
    {
        $company = $this->companyService->get($id);

        return response()->json([
            'success' => true,
            'message' => 'Company retrieved successfully.',
            'data' => $company,
        ]);
    }

    /**
     * @throws CompanyNotFoundException
     */
    public function update(UpdateCompanyRequest $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($id);
        $company = $this->companyService->update($company, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Company updated successfully.',
            'data' => $company,
        ]);
    }

    /**
     * @throws CompanyNotFoundException
     */
    public function destroy(string $id): JsonResponse
    {
        $company = $this->resolveCompany($id);
        $this->companyService->delete($company);

        return response()->json([
            'success' => true,
            'message' => 'Company deleted successfully.',
            'data' => null,
        ]);
    }

    /**
     * @throws DocumentExtractionFailedException
     */
    public function extractDocument(ExtractDocumentRequest $request): JsonResponse
    {
        $result = $this->documentExtractionService->extract($request->file('document'));

        return response()->json([
            'success' => true,
            'message' => 'Document processed successfully.',
            'data' => $result,
        ]);
    }

    /**
     * @throws CompanyNotFoundException
     */
    private function resolveCompany(string $id): Company
    {
        $company = Company::find($id);

        if (! $company) {
            throw new CompanyNotFoundException(
                "Company with ID '$id' not found.",
                context: ['company_id' => $id]
            );
        }

        return $company;
    }
}
