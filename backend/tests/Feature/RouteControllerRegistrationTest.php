<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteControllerRegistrationTest extends TestCase
{
    public function test_every_registered_controller_route_has_a_real_controller_method(): void
    {
        $missing = [];
        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            if (! str_contains($action, '@')) {
                continue;
            }
            [$class, $method] = explode('@', $action, 2);
            if (! class_exists($class) || ! method_exists($class, $method)) {
                $missing[] = $route->uri().' -> '.$action;
            }
        }

        $this->assertSame([], $missing, "Controller routes point to missing methods:\n".implode("\n", $missing));
    }
}
