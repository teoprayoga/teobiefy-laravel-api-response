<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse\Contracts;

use Illuminate\Http\JsonResponse;

interface ApiInterface
{
    public function response($status = 200, $message = null, $data = [], ...$extraData): JsonResponse;

    public function ok($message = null, $data = [], ...$extraData): JsonResponse;

    public function success($message = null, $data = [], ...$extraData): JsonResponse;

    public function notFound($message = null): JsonResponse;

    public function validation($message = null, $errors = [], ...$extraData): JsonResponse;

    public function forbidden($message = null, $data = [], ...$extraData): JsonResponse;

    public function error($message = null, $data = [], ...$extraData): JsonResponse;
}
