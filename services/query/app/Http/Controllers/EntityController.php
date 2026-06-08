<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowEntityRequest;
use App\Http\Resources\EntityResource;
use App\Models\Entity;

final class EntityController extends Controller
{
    public function show(ShowEntityRequest $request, string $code): EntityResource
    {
        $entity = Entity::where('entity_code', $code)->firstOrFail();

        return new EntityResource($entity);
    }
}
