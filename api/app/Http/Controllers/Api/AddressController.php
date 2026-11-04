<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAddressRequest;
use App\Http\Requests\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Services\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AddressController extends Controller
{
    public function index(Request $request, AddressService $addresses): JsonResponse
    {
        return AddressResource::collection($addresses->listUserAddresses($request->user()))->response()->setStatusCode(200);
    }

    public function show(Request $request, int $id, AddressService $addresses): JsonResponse
    {
        $address = $addresses->getUserAddress($request->user(), $id);

        return (new AddressResource($address))->response()->setStatusCode(200);
    }

    public function store(StoreAddressRequest $request, AddressService $addresses): JsonResponse
    {
        $address = $addresses->create($request->user(), $request->validated());

        return (new AddressResource($address))->response()->setStatusCode(201);
    }

    public function update(UpdateAddressRequest $request, int $id, AddressService $addresses): JsonResponse
    {
        $address = $addresses->update($request->user(), $id, $request->validated());

        return (new AddressResource($address))->response()->setStatusCode(200);
    }

    public function destroy(Request $request, int $id, AddressService $addresses): Response
    {
        $addresses->delete($request->user(), $id);

        return response()->noContent();
    }
}
