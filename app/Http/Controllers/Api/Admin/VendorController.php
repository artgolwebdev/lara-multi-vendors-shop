<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVendorRequest;
use App\Http\Requests\Admin\UpdateVendorRequest;
use App\Http\Resources\VendorResource;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VendorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return VendorResource::collection(Vendor::query()->withCount('products')->orderBy('name')->paginate($perPage));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVendorRequest $request): JsonResponse
    {
        $vendor = Vendor::create($request->validated());

        return (new VendorResource($vendor))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Vendor $vendor): VendorResource
    {
        return new VendorResource($vendor->loadCount('products'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVendorRequest $request, Vendor $vendor): VendorResource
    {
        $vendor->update($request->validated());

        return new VendorResource($vendor->refresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Vendor $vendor): JsonResponse
    {
        $vendor->delete();

        return response()->json(status: 204);
    }
}
