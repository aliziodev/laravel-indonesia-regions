<?php

namespace Aliziodev\IndonesiaRegions\Support;

use Aliziodev\IndonesiaRegions\Contracts\ApiResponderInterface;
use Illuminate\Http\JsonResponse;

class JsonApiResponder implements ApiResponderInterface
{
    public function respond(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status);
    }
}
