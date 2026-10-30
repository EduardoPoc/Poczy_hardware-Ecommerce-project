<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAddressRequest;
use App\Http\Requests\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Services\AddressService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AddressController extends Controller
{
    public function __construct(private AddressService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return AddressResource::collection($this->service->listUserAddresses($request->user()));
    }

    public function show(Request $request, int $id): AddressResource
    {
        return new AddressResource($this->service->getUserAddress($request->user(), $id));
    }

    public function store(StoreAddressRequest $request): AddressResource
    {
        return new AddressResource($this->service->create($request->user(), $request->validated()));
    }

    public function update(UpdateAddressRequest $request, int $id): AddressResource
    {
        return new AddressResource($this->service->update($request->user(), $id, $request->validated()));
    }

    public function destroy(Request $request, int $id): Response
    {
        $this->service->delete($request->user(), $id);

        return response()->noContent();
    }
}
