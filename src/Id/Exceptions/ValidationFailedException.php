<?php

namespace Anikeen\Id\Exceptions;

/**
 * Anikeen ID refused the input (422). `error` is the first code; endpoints with per-field codes
 * (the profile API) send them as `codes`: `{field: code}`, e.g. `["username" => "username_taken"]`.
 * Branch on the codes, the messages are for people and may change.
 */
class ValidationFailedException extends ApiException
{
    /**
     * @return array<string, string> field => code; empty when the endpoint sends none
     */
    public function errorCodes(): array
    {
        $codes = $this->body()->codes ?? null;

        return is_object($codes) || is_array($codes) ? array_map('strval', (array) $codes) : [];
    }

    /**
     * The code of $field, or of its first failed sub-field when the codes are keyed by those
     * (`crop` finds `crop.x`, `crop.y` or `crop.size`).
     */
    public function errorCodeFor(string $field): ?string
    {
        $codes = $this->errorCodes();

        if (isset($codes[$field])) {
            return $codes[$field];
        }

        foreach ($codes as $key => $code) {
            if (str_starts_with((string) $key, $field . '.')) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Whether any field failed with $code, or the response's `error` is $code.
     */
    public function hasErrorCode(string $code): bool
    {
        return $this->error === $code || in_array($code, $this->errorCodes(), true);
    }

    /**
     * The messages per field (`errors`), for showing them to people.
     *
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        $errors = $this->body()->errors ?? null;

        return is_object($errors) || is_array($errors) ? array_map(fn ($messages) => array_values((array) $messages), (array) $errors) : [];
    }
}
