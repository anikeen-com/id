<?php

require __DIR__ . '/../vendor/autoload.php';

use Anikeen\Id\AnikeenId;
use Anikeen\Id\Billable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

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

// Liste der Klassen, die ausgewertet werden sollen
$classes = [
    AnikeenId::class,
    Billable::class,
];

$allMarkdown = collect($classes)
    ->map(function (string $class) {
        $className = Arr::last(explode('\\', $class));
        $markdown  = "## {$className}\n\n";

        // alle Traits der Klasse, außer denen aus ApiOperations
        $traits = collect(class_uses($class) ?: [])
            ->reject(function (string $trait) {
                return Str::contains($trait, 'ApiOperations\\');
            })
            ->all();

        if (empty($traits)) {
            $markdown .= '_Keine Traits gefunden._';
            return $markdown;
        }

        // für jeden Trait die Methoden extrahieren
        $markdown .= collect($traits)
            ->map(function (string $trait) {
                $title      = str_replace('Trait', '', Arr::last(explode('\\', $trait)));
                $reflection = new ReflectionClass($trait);

                $methods = collect($reflection->getMethods())
                    ->reject->isAbstract()
                    ->reject->isPrivate()
                    ->reject->isProtected()
                    ->reject->isConstructor()
                    ->map(function (ReflectionMethod $method) {
                        // Methodendeklaration starten
                        $decl = 'public function ' . $method->getName() . '(';

                        // Parameter-Typen und Default-Werte
                        $decl .= collect($method->getParameters())
                            ->map(function (ReflectionParameter $p) {
                                // Typ-Hint
                                $typeHint = '';
                                if ($p->hasType()) {
                                    $typeHint = typeName($p->getType()) . ' ';
                                }

                                // Parameter-Name
                                $param = $typeHint . '$' . $p->getName();

                                // Default-Wert
                                if ($p->isDefaultValueAvailable()) {
                                    $default = $p->getDefaultValue();
                                    if (is_array($default) && empty($default)) {
                                        // leeres Array → Short-Syntax
                                        $param .= ' = []';
                                    } elseif ($default === null) {
                                        // NULL → null (kleingeschrieben)
                                        $param .= ' = null';
                                    } else {
                                        // sonst var_export, Newlines entfernen
                                        $def = var_export($default, true);
                                        $param .= ' = ' . str_replace(PHP_EOL, '', $def);
                                    }
                                }

                                return $param;
                            })
                            ->implode(', ');

                        $decl .= ')';

                        // Rückgabetyp, falls vorhanden
                        if ($method->hasReturnType()) {
                            $decl    .= ': ' . typeName($method->getReturnType());
                        }

                        return $decl;
                    })
                    ->all();

                // Markdown-Block für diesen Trait
                $md  = "### {$title}\n\n```php\n";
                $md .= implode("\n", $methods) . "\n```\n";
                return $md;
            })
            ->implode("\n");

        return $markdown;
    })
    ->implode("\n\n");

// README zusammenbauen und schreiben
$stub    = file_get_contents(__DIR__ . '/../README.stub');
$content = str_replace('<!-- GENERATED-DOCS -->', $allMarkdown, $stub);
file_put_contents(__DIR__ . '/../README.md', $content);
