<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class RouteProfileResolver
{
    public function responseProfile(?Request $request = null): Profile
    {
        return $this->resolve('response', $request);
    }

    public function requestProfile(Request $request): Profile
    {
        return $this->resolve('request', $request);
    }

    private function resolve(string $direction, ?Request $request): Profile
    {
        $default = config("teobiefy.{$direction}.default_profile", Profile::PLAIN);
        $profiles = config("teobiefy.{$direction}.route_profiles", []);

        if ($request && is_array($profiles)) {
            $matched = $this->matchRouteProfile($profiles, $request);

            if ($matched !== null) {
                return Profile::from($matched);
            }
        }

        return Profile::from($default);
    }

    /**
     * @param  array<string, string>  $profiles
     */
    private function matchRouteProfile(array $profiles, Request $request): ?string
    {
        $route = $request->route();
        $candidates = array_filter([
            $route?->getName(),
            $route?->uri(),
            $request->path(),
        ]);

        foreach ($profiles as $pattern => $profile) {
            foreach ($candidates as $candidate) {
                if ($pattern === $candidate || Str::is($pattern, $candidate)) {
                    return $profile;
                }
            }
        }

        if (Arr::isAssoc($profiles)) {
            return null;
        }

        $profile = reset($profiles);

        return $profile ?: null;
    }
}
