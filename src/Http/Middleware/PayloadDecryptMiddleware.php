<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Teoprayoga\TeobiefyLaravelApiResponse\Exceptions\InvalidPayloadException;
use Teoprayoga\TeobiefyLaravelApiResponse\Exceptions\PayloadTooLargeException;
use Teoprayoga\TeobiefyLaravelApiResponse\PayloadTransformer;
use Teoprayoga\TeobiefyLaravelApiResponse\RouteProfileResolver;

class PayloadDecryptMiddleware
{
    private const METADATA_KEYS = [
        'data_enc',
        'data_comp',
        'nonce',
        'cipher',
        'compression',
    ];

    public function __construct(
        private readonly PayloadTransformer $transformer,
        private readonly RouteProfileResolver $profiles,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $profile = $this->profiles->requestProfile($request);

        if ($profile->isPlain()) {
            return $next($request);
        }

        try {
            $decoded = $this->transformer->decodeRequest($request->all(), $profile);
            $request->replace(array_merge($this->withoutMetadata($request->all()), $decoded));
        } catch (PayloadTooLargeException) {
            return api()->response(413, 'Payload too large', []);
        } catch (InvalidPayloadException $exception) {
            Log::warning($exception->getMessage());

            return api()->response(406, 'Invalid payload', []);
        }

        return $next($request);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withoutMetadata(array $payload): array
    {
        foreach (self::METADATA_KEYS as $key) {
            unset($payload[$key]);
        }

        return $payload;
    }
}
