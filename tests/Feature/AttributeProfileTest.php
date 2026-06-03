<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route as RouteFacade;
use Teoprayoga\TeobiefyLaravelApiResponse\AttributeProfileReader;
use Teoprayoga\TeobiefyLaravelApiResponse\Profile;
use Teoprayoga\TeobiefyLaravelApiResponse\RouteProfileResolver;
use Teoprayoga\TeobiefyLaravelApiResponse\Tests\Fixtures\ClassAttributeController;
use Teoprayoga\TeobiefyLaravelApiResponse\Tests\Fixtures\InvokableController;
use Teoprayoga\TeobiefyLaravelApiResponse\Tests\Fixtures\MethodAttributeController;
use Teoprayoga\TeobiefyLaravelApiResponse\Tests\Fixtures\OverrideController;
use Teoprayoga\TeobiefyLaravelApiResponse\Tests\Fixtures\PlainController;
use Teoprayoga\TeobiefyLaravelApiResponse\Tests\TestCase;

class AttributeProfileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeProfileReader::flush();
    }

    public function test_method_response_profile_attribute_wins_over_config(): void
    {
        config()->set('teobiefy.response.route_profiles', [
            'attr/*' => Profile::COMPRESSED,
        ]);

        $request = $this->requestForRoute('GET', 'attr/method', [MethodAttributeController::class, 'show']);

        $profile = $this->resolver()->responseProfile($request);

        $this->assertSame(Profile::ENCRYPTED, $profile->name());
    }

    public function test_class_request_profile_attribute_applies_when_method_has_none(): void
    {
        $request = $this->requestForRoute('POST', 'attr/class', [ClassAttributeController::class, 'store']);

        $profile = $this->resolver()->requestProfile($request);

        $this->assertSame(Profile::ENCRYPTED, $profile->name());
    }

    public function test_closure_route_falls_back_to_config_pattern(): void
    {
        config()->set('teobiefy.response.route_profiles', [
            'closure/*' => Profile::COMPRESSED,
        ]);

        $request = $this->requestForRoute('GET', 'closure/foo', fn () => 'ok');

        $profile = $this->resolver()->responseProfile($request);

        $this->assertSame(Profile::COMPRESSED, $profile->name());
    }

    public function test_method_attribute_overrides_class_attribute(): void
    {
        $request = $this->requestForRoute('GET', 'attr/override', [OverrideController::class, 'show']);

        $profile = $this->resolver()->responseProfile($request);

        $this->assertSame(Profile::ENCRYPTED, $profile->name());
    }

    public function test_invokable_controller_class_attribute_resolves(): void
    {
        $request = $this->requestForRoute('GET', 'attr/invokable', InvokableController::class);

        $profile = $this->resolver()->responseProfile($request);

        $this->assertSame(Profile::ENCRYPTED, $profile->name());
    }

    public function test_attribute_reader_caches_reflection_lookups(): void
    {
        AttributeProfileReader::flush();
        $this->assertSame(0, AttributeProfileReader::cacheSize());

        $request = $this->requestForRoute('GET', 'attr/method', [MethodAttributeController::class, 'show']);

        $this->resolver()->responseProfile($request);
        $sizeAfterFirst = AttributeProfileReader::cacheSize();
        $this->assertSame(1, $sizeAfterFirst);

        $this->resolver()->responseProfile($request);
        $this->assertSame($sizeAfterFirst, AttributeProfileReader::cacheSize());

        AttributeProfileReader::flush();
        $this->assertSame(0, AttributeProfileReader::cacheSize());
    }

    public function test_no_attribute_no_match_returns_default(): void
    {
        config()->set('teobiefy.response.default_profile', Profile::PLAIN);
        config()->set('teobiefy.response.route_profiles', []);

        $request = $this->requestForRoute('GET', 'plain/foo', [PlainController::class, 'show']);

        $profile = $this->resolver()->responseProfile($request);

        $this->assertTrue($profile->isPlain());
    }

    private function resolver(): RouteProfileResolver
    {
        return $this->app->make(RouteProfileResolver::class);
    }

    private function requestForRoute(string $method, string $uri, mixed $action): Request
    {
        $route = new Route([$method], $uri, $action);
        $route->bind(Request::create($uri, $method));

        $request = Request::create($uri, $method);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }
}
