<?php

require __DIR__ . '/../vendor/autoload.php';

use Anikeen\Id\AnikeenId;
use Anikeen\Id\Billable;
use Anikeen\Id\Resources\AppCharge;
use Anikeen\Id\Resources\AppCheckoutSession;
use Anikeen\Id\Resources\AppCheckoutSessions;
use Anikeen\Id\Resources\AppInventory;
use Anikeen\Id\Resources\AppOrders;
use Anikeen\Id\Resources\AppShipping;
use Anikeen\Id\Resources\AppSubscription;
use Anikeen\Id\Resources\AppSubscriptions;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

// Classes whose traits (one per API area) are listed
$classes = [
    AnikeenId::class,
    Billable::class,
];

// Resources the traits hand out (app token), with how to get one
$resources = [
    AppInventory::class => '`AnikeenId::inventory()`',
    AppShipping::class => '`AnikeenId::shipping()`',
    AppOrders::class => '`AnikeenId::appOrders()`, `$user->appOrders()`',
    AppSubscriptions::class => '`$user->appSubscriptions()`',
    AppSubscription::class => '`$user->appSubscriptions()->find()` / `->create()`',
    AppCheckoutSessions::class => '`AnikeenId::appCheckoutSessions()`, `$user->appCheckoutSessions()`',
    AppCheckoutSession::class => '`->appCheckoutSessions()->find()` / `->create()`',
    AppCharge::class => '`AnikeenId::createChargeForUser()`, `findChargeForUser()`, `voidChargeForUser()`',
];

// short type name incl. union, intersection and nullable types
function typeName(ReflectionType $type): string
{
    if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
        $separator = $type instanceof ReflectionUnionType ? '|' : '&';

        return collect($type->getTypes())->map(fn (ReflectionType $t) => typeName($t))->implode($separator);
    }

    $name = Arr::last(explode('\\', $type->getName()));

    return ($type->allowsNull() && !in_array($name, ['mixed', 'null'], true) ? '?' : '') . $name;
}

// the declaration of a method, e.g. "public function find(string $id): ?object"
function declaration(ReflectionMethod $method): string
{
    $parameters = collect($method->getParameters())
        ->map(function (ReflectionParameter $p) {
            $param = ($p->hasType() ? typeName($p->getType()) . ' ' : '') . '$' . $p->getName();

            if ($p->isDefaultValueAvailable()) {
                $default = $p->getDefaultValue();
                if (is_array($default) && empty($default)) {
                    $param .= ' = []';
                } elseif ($default === null) {
                    $param .= ' = null';
                } else {
                    $param .= ' = ' . str_replace(PHP_EOL, '', var_export($default, true));
                }
            }

            return $param;
        })
        ->implode(', ');

    $decl = 'public function ' . $method->getName() . '(' . $parameters . ')';

    if ($method->hasReturnType()) {
        $decl .= ': ' . typeName($method->getReturnType());
    }

    return $decl;
}

// the public API methods of a class or trait as a PHP code block
function methodBlock(ReflectionClass $reflection): string
{
    $methods = collect($reflection->getMethods(ReflectionMethod::IS_PUBLIC))
        ->reject->isAbstract()
        ->reject->isConstructor()
        // magic accessors and serialization are no API of their own
        ->reject(fn (ReflectionMethod $method) => Str::startsWith($method->getName(), '__')
            || in_array($method->getName(), ['jsonSerialize', 'toArray'], true))
        ->map(fn (ReflectionMethod $method) => declaration($method))
        ->all();

    return "```php\n" . implode("\n", $methods) . "\n```\n";
}

$traitMarkdown = collect($classes)
    ->map(function (string $class) {
        $markdown = '## ' . Arr::last(explode('\\', $class)) . "\n\n";

        // all traits of the class, except those of ApiOperations
        $traits = collect(class_uses($class) ?: [])
            ->reject(fn (string $trait) => Str::contains($trait, 'ApiOperations\\'))
            ->all();

        if (empty($traits)) {
            return $markdown . '_No traits found._';
        }

        return $markdown . collect($traits)
            ->map(function (string $trait) {
                $title = str_replace('Trait', '', Arr::last(explode('\\', $trait)));

                return "### {$title}\n\n" . methodBlock(new ReflectionClass($trait));
            })
            ->implode("\n");
    })
    ->implode("\n\n");

$resourceMarkdown = "## Resources\n\n"
    . "What the methods above return for the app's own token. Nothing is requested until one of\n"
    . "their methods is called.\n\n"
    . collect($resources)
        ->map(function (string $from, string $class) {
            $title = Arr::last(explode('\\', $class));

            return "### {$title}\n\nFrom {$from}.\n\n" . methodBlock(new ReflectionClass($class));
        })
        ->implode("\n");

// build and write the README
$stub    = file_get_contents(__DIR__ . '/../README.stub');
$content = str_replace('<!-- GENERATED-DOCS -->', $traitMarkdown . "\n\n" . $resourceMarkdown, $stub);
file_put_contents(__DIR__ . '/../README.md', $content);
