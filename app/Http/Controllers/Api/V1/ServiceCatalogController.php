<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Support\ApiResponse;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceCatalogController extends Controller
{
    use ApiResponse;

    public function categories(Request $request): JsonResponse
    {
        $categories = ServiceCategory::withCount('services')->get();

        return $this->success($categories);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active'   => ['sometimes', 'boolean'],
        ]);

        $category = ServiceCategory::create($data);

        return $this->created($category, 'Category created successfully.');
    }

    public function index(Request $request): JsonResponse
    {
        $query = Service::query()->with('category');

        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($search = $request->string('search')->trim()->value()) {
            $query->where('name', 'like', "%{$search}%");
        }

        $services = $query->orderBy('name')->paginate($request->integer('per_page', 25));

        return $this->paginated($services);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = app('currentCompanyId');

        $data = $request->validate([
            'category_id'        => ['nullable', 'integer', Rule::exists('service_categories', 'id')->where('company_id', $companyId)],
            'name'               => ['required', 'string', 'max:160'],
            'description'        => ['nullable', 'string', 'max:2000'],
            'base_price'         => ['required', 'numeric', 'min:0'],
            'estimated_duration' => ['sometimes', 'integer', 'min:5', 'max:1440'],
            'pricing_type'       => ['sometimes', 'in:fixed,hourly,custom'],
            'is_active'          => ['sometimes', 'boolean'],
        ]);

        $service = Service::create($data);

        return $this->created($service->load('category'), 'Service added to catalog.');
    }

    public function show(Service $service): JsonResponse
    {
        return $this->success($service->load('category'));
    }

    public function update(Request $request, Service $service): JsonResponse
    {
        $companyId = app('currentCompanyId');

        $data = $request->validate([
            'category_id'        => ['nullable', 'integer', Rule::exists('service_categories', 'id')->where('company_id', $companyId)],
            'name'               => ['sometimes', 'string', 'max:160'],
            'description'        => ['nullable', 'string', 'max:2000'],
            'base_price'         => ['sometimes', 'numeric', 'min:0'],
            'estimated_duration' => ['sometimes', 'integer', 'min:5', 'max:1440'],
            'pricing_type'       => ['sometimes', 'in:fixed,hourly,custom'],
            'is_active'          => ['sometimes', 'boolean'],
        ]);

        $service->update($data);

        return $this->success($service->fresh()->load('category'), 'Service updated successfully.');
    }

    public function destroy(Service $service): JsonResponse
    {
        $service->delete();

        return $this->success(null, 'Service removed.');
    }
}

