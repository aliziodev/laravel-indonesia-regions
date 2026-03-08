<?php

namespace Aliziodev\IndonesiaRegions\Contracts;

use Illuminate\Http\JsonResponse;

interface ApiResponderInterface
{
    public function respond(mixed $data, int $status = 200): JsonResponse;
}
