<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Traits\Macroable;
use Teoprayoga\TeobiefyLaravelApiResponse\Contracts\ApiInterface;

class ApiResponse implements ApiInterface
{
    use Macroable;

    public const HTTP_OK = 200;

    public const HTTP_NOT_FOUND = 404;

    public const HTTP_FORBIDDEN = 403;

    public const HTTP_UNPROCESSABLE_ENTITY = 422;

    public const HTTP_INTERNAL_SERVER_ERROR = 500;

    public function __construct(
        private readonly PayloadTransformer $transformer,
        private readonly RouteProfileResolver $profiles,
    ) {}

    public function response($status = 200, $message = null, $data = [], ...$extraData): JsonResponse
    {
        $json = [
            config('teobiefy.keys.status') => config('teobiefy.stringify') ? (string) $status : $status,
            config('teobiefy.keys.message') => $message,
            config('teobiefy.keys.data') => $data,
        ];

        if (is_countable($data) && config('teobiefy.include_data_count', false) && ! empty($data)) {
            $json[config('teobiefy.keys.data_count')] = config('teobiefy.stringify') ? (string) count($data) : count($data);
        }

        foreach ($extraData as $extra) {
            $json = array_merge($json, $extra);
        }

        $profile = $this->profiles->responseProfile(request());
        $json = $this->transformer->transformResponse($json, config('teobiefy.keys.data'), $profile);

        return response()->json($json, config('teobiefy.match_status') ? $status : 200);
    }

    public function ok($message = null, $data = [], ...$extraData): JsonResponse
    {
        return $this->response(
            self::HTTP_OK,
            $message ?? trans('api-response::messages.success'),
            $data,
            ...$extraData
        );
    }

    public function success($message = null, $data = [], ...$extraData): JsonResponse
    {
        return $this->ok($message, $data, ...$extraData);
    }

    public function notFound($message = null): JsonResponse
    {
        return $this->response(
            self::HTTP_NOT_FOUND,
            $message ?? trans('api-response::messages.notfound'),
            []
        );
    }

    public function validation($message = null, $errors = [], ...$extraData): JsonResponse
    {
        return $this->response(
            self::HTTP_UNPROCESSABLE_ENTITY,
            $message ?? trans('api-response::messages.validation'),
            $errors,
            ...$extraData
        );
    }

    public function forbidden($message = null, $data = [], ...$extraData): JsonResponse
    {
        return $this->response(
            self::HTTP_FORBIDDEN,
            $message ?? trans('api-response::messages.forbidden'),
            $data,
            ...$extraData
        );
    }

    public function error($message = null, $data = [], ...$extraData): JsonResponse
    {
        return $this->response(
            self::HTTP_INTERNAL_SERVER_ERROR,
            $message ?? trans('api-response::messages.error'),
            $data,
            ...$extraData
        );
    }
}
