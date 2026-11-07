<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class ApiContractTest extends TestCase
{
    private function contract(): array
    {
        return Yaml::parseFile(public_path('api/docs/openapi.yaml'));
    }

    public function test_every_registered_api_operation_is_documented(): void
    {
        $operations = [];
        foreach ($this->contract()['paths'] as $path => $definition) {
            foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                if (isset($definition[$method])) {
                    $operations[$method.' '.preg_replace('/\{[^}]+\}/', '{}', $path)] = true;
                }
            }
        }
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }
            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                $key = strtolower($method).' /'.preg_replace('/\{[^}]+\}/', '{}', $route->uri());
                $this->assertArrayHasKey($key, $operations, $key.' is missing from OpenAPI.');
                $checked++;
            }
        }
        $this->assertGreaterThan(50, $checked);
    }

    public function test_all_local_schema_references_resolve(): void
    {
        $contract = $this->contract();
        $walk = function (array $node) use (&$walk, $contract): void {
            if (isset($node['$ref']) && str_starts_with($node['$ref'], '#/')) {
                $target = $contract;
                foreach (explode('/', substr($node['$ref'], 2)) as $key) {
                    $key = str_replace(['~1', '~0'], ['/', '~'], $key);
                    $this->assertIsArray($target, $node['$ref']);
                    $this->assertArrayHasKey($key, $target, $node['$ref']);
                    $target = $target[$key];
                }
            }
            foreach ($node as $value) {
                if (is_array($value)) {
                    $walk($value);
                }
            }
        };
        $walk($contract);
    }
}
