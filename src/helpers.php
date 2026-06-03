<?php

use Illuminate\Http\JsonResponse;
use Teoprayoga\TeobiefyLaravelApiResponse\ApiResponse;
use Teoprayoga\TeobiefyLaravelApiResponse\Contracts\ApiInterface;

if (! function_exists('api')) {
    /**
     * @return ApiResponse|JsonResponse
     */
    function api($status = 200, $message = '', $data = [], ...$extraData)
    {
        if (func_num_args() === 0) {
            return app(ApiInterface::class);
        }

        return app(ApiInterface::class)->response($status, $message, $data, ...$extraData);
    }
}

if (! function_exists('ok')) {
    function ok($message = '', $data = [], ...$extraData): JsonResponse
    {
        return api()->ok($message, $data, ...$extraData);
    }
}

if (! function_exists('success')) {
    function success($message = '', $data = [], ...$extraData): JsonResponse
    {
        return api()->success($message, $data, ...$extraData);
    }
}
