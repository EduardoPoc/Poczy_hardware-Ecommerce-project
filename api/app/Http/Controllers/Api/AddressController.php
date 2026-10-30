<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()->addresses()->orderBy('id')->get();

        return response()->json($addresses);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $address = $request->user()->addresses()->findOrFail($id);

        return response()->json($address);
    }

    public function store(Request $request): JsonResponse
    {
        $address = $request->user()->addresses()->create($request->all());

        return response()->json($address, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $address = $request->user()->addresses()->findOrFail($id);
        $address->update($request->all());

        return response()->json($address);
    }

    public function destroy(Request $request, int $id): Response
    {
        $address = $request->user()->addresses()->findOrFail($id);
        $address->delete();

        return response()->noContent();
    }
}
