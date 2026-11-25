<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\DamagedProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/** Productos dañados del backend. Todas las rutas exigen token (auth:sanctum). */
class ProductController extends Controller
{
    /** GET /api/products: el admin ve todos; el operador, solo los suyos. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $productos = DamagedProduct::query()
            ->when(! $user->esAdmin(), fn ($q) => $q->where('created_by', $user->id))
            ->orderBy('id')
            ->get();

        return ProductResource::collection($productos);
    }

    /** GET /api/products/{product} */
    public function show(DamagedProduct $product): ProductResource
    {
        Gate::authorize('view', $product);

        return new ProductResource($product);
    }

    /** POST /api/products: el dueño es siempre el usuario del token. */
    public function store(ProductRequest $request): JsonResponse
    {
        Gate::authorize('create', DamagedProduct::class);

        $product = DamagedProduct::create([
            ...$request->attributesForModel(),
            'created_by' => $request->user()->id,
        ]);

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    /** PATCH /api/products/{product} */
    public function update(ProductRequest $request, DamagedProduct $product): ProductResource
    {
        Gate::authorize('update', $product);

        $product->update($request->attributesForModel());

        return new ProductResource($product->refresh());
    }

    /** DELETE /api/products/{product}: solo administrador. */
    public function destroy(DamagedProduct $product): Response
    {
        Gate::authorize('delete', $product);

        $product->delete();

        return response()->noContent();
    }
}
