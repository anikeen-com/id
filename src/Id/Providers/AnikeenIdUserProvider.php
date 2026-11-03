<?php

namespace Anikeen\Id\Providers;

use Anikeen\Id\AnikeenId;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AnikeenIdUserProvider implements UserProvider
{
    private ?string $accessTokenField;

    public function __construct(
        private AnikeenId $anikeenId,
        private Request   $request,
        private string    $model,
        private array     $fields = [],
        private ?string   $column = null,
    ) {
        $this->accessTokenField = AnikeenId::getAccessTokenField();
    }

    /**
     * {@inheritDoc}
     */
    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        // Token from request (if not already pass from $token):
        $token = $token ?: $this->request->bearerToken();
        if (! $token) {
            return null;
        }

        // Set token in SSO client and request user info
        $this->anikeenId->setToken($token);
        $result = $this->anikeenId->getAuthedUser();
        if (! $result->success()) {
            return null;
        }

        // Only the desired fields
        $data = Arr::only((array)$result->data(), $this->fields);
        // Primary key (e.g. $user->id)
        $pk = $this->createModel()->getAuthIdentifierName();
        $data[$pk] = $result->data->id;

        // Fill in access token field, if available
        if ($this->accessTokenField) {
            $data[$this->accessTokenField] = $token;
        }

        // Local eloquent model: either find or create a new one
        /** @var Model $modelInstance */
        $modelInstance = $this->newModelQuery()
            ->firstOrNew([$pk => $data[$pk]]);

        $modelInstance->fill($data);
        $modelInstance->save();

        return $modelInstance;
    }

    /**
     * {@inheritDoc}
     */
    public function updateRememberToken(Authenticatable $user, $token): void
    {
        // no-op
    }

    /**
     * {@inheritDoc}
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function retrieveById($identifier): ?Authenticatable
    {
        return $this->newModelQuery()
            ->where($this->createModel()->getAuthIdentifierName(), $identifier)
            ->first();
    }

    /**
     * Find the user of an Anikeen ID (the `sub` of an access token) by the provider's `column`,
     * else `services.anikeen.user_id_column`, else AnikeenId::getUserIdField() (`anikeen_id`).
     */
    public function retrieveByAnikeenId(string $anikeenId): ?Authenticatable
    {
        $column = $this->column ?: (config('services.anikeen.user_id_column') ?: AnikeenId::getUserIdField());

        return $this->newModelQuery()->where($column, $anikeenId)->first();
    }

    /**
     * {@inheritDoc}
     */
    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false): void
    {
        // no-op
    }

    /**
     * @return Model
     */
    protected function createModel(): Model
    {
        $class = '\\' . ltrim($this->model, '\\');
        return new $class;
    }

    /**
     * @return Builder
     */
    protected function newModelQuery(): Builder
    {
        return $this->createModel()->newQuery();
    }
}
