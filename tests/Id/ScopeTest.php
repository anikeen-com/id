<?php

namespace Anikeen\Id\Tests;

use Anikeen\Id\Enums\Scope;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;

class ScopeTest extends TestCase
{
    public static function newScopes(): array
    {
        return [
            'products:read' => [Scope::PRODUCTS_READ, 'products:read'],
            'reseller-customers' => [Scope::RESELLER_CUSTOMERS, 'reseller-customers'],
            'reseller-customers:read' => [Scope::RESELLER_CUSTOMERS_READ, 'reseller-customers:read'],
            'reseller-grants' => [Scope::RESELLER_GRANTS, 'reseller-grants'],
            'reseller-orders' => [Scope::RESELLER_ORDERS, 'reseller-orders'],
        ];
    }

    #[DataProvider('newScopes')]
    public function test_the_product_and_reseller_scopes_have_their_names(string $constant, string $expected): void
    {
        $this->assertSame($expected, $constant);
    }

    public function test_the_preference_scopes_match_anikeen_id(): void
    {
        $this->assertSame('preferences', Scope::PREFERENCES);
        $this->assertSame('preferences:read', Scope::PREFERENCES_READ);
        $this->assertSame('preferences:client', Scope::PREFERENCES_CLIENT);
    }

    public function test_there_is_no_full_products_scope_the_console_would_not_accept(): void
    {
        // the Console's product APIs accept `user`, `user:read` and `products:read` only
        $this->assertNotContains('products', (new ReflectionClass(Scope::class))->getConstants());
    }

    public function test_every_scope_follows_the_resource_and_level_schema(): void
    {
        $scopes = array_values((new ReflectionClass(Scope::class))->getConstants());

        $this->assertSame($scopes, array_values(array_unique($scopes)), 'scope values must be unique');

        foreach ($scopes as $scope) {
            // "resource" (full access), "resource:read" or "resource:client"; Anikeen ID groups by resource
            $this->assertMatchesRegularExpression('/^[a-z]+(-[a-z]+)*(:(read|client))?$/', $scope);
        }
    }

    public function test_each_reseller_resource_is_its_own_group(): void
    {
        $groups = collect((new ReflectionClass(Scope::class))->getConstants())
            ->filter(fn (string $scope) => str_starts_with($scope, 'reseller-'))
            ->groupBy(fn (string $scope) => explode(':', $scope)[0])
            ->map(fn ($scopes) => $scopes->sort()->values()->all())
            ->all();

        $this->assertSame([
            'reseller-customers' => ['reseller-customers', 'reseller-customers:read'],
            'reseller-grants' => ['reseller-grants'],
            'reseller-orders' => ['reseller-orders'],
        ], $groups);
    }

    public function test_each_console_product_family_has_a_full_and_a_read_scope(): void
    {
        $groups = collect((new ReflectionClass(Scope::class))->getConstants())
            ->filter(fn (string $scope) => str_starts_with($scope, 'console-'))
            ->groupBy(fn (string $scope) => explode(':', $scope)[0])
            ->map(fn ($scopes) => $scopes->sort()->values()->all())
            ->all();

        $families = ['servers', 'containers', 'domains', 'zones', 'hosting', 'load-balancers', 'object-storage'];

        $this->assertSame(
            collect($families)->mapWithKeys(fn (string $family) => ["console-$family" => ["console-$family", "console-$family:read"]])->sortKeys()->all(),
            collect($groups)->sortKeys()->all()
        );
        $this->assertSame('console-containers', Scope::CONSOLE_CONTAINERS);
        $this->assertSame('console-containers:read', Scope::CONSOLE_CONTAINERS_READ);
        $this->assertSame('console-load-balancers', Scope::CONSOLE_LOAD_BALANCERS);
        $this->assertSame('console-object-storage:read', Scope::CONSOLE_OBJECT_STORAGE_READ);
    }
}
