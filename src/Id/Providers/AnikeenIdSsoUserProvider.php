<?php


namespace Anikeen\Id\Providers;

use Anikeen\Id\AnikeenId;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AnikeenIdSsoUserProvider implements UserProvider
{
    private ?string $accessTokenField = null;

    public function __construct(
        private AnikeenId $anikeenId,
        private Request   $request,
        private string    $model,
        private array     $fields
    )
    {
        $this->accessTokenField = AnikeenId::getAccessTokenField();
    }

    public function retrieveById(mixed $identifier): ?Authenticatable
    {
        $model = $this->createModel();
        $token = $this->request->bearerToken();

        $user = $this->newModelQuery($model)
            ->where($model->getAuthIdentifierName(), $identifier)
            ->first();

        // Return user when found
        if ($user) {
            // Update access token when updated
            if ($this->accessTokenField) {
                $user[$this->accessTokenField] = $token;

                if ($user->isDirty()) {
                    $user->save();
                }
            }

            return $user;
        }

        // Create new user
        $this->anikeenId->setToken($token);
        $result = $this->anikeenId->getAuthedUser();

        if (!$result->success()) {
            return null;
        }

        $attributes = Arr::only((array)$result->data(), $this->fields);
        $attributes[$model->getAuthIdentifierName()] = $result->data->id;

        if ($this->accessTokenField) {
            $attributes[$this->accessTokenField] = $token;
        }

        return $this->newModelQuery($model)->create($attributes);
    }

    /**
     * Create a new instance of the model.
     */
    public function createModel(): Model
    {
        $class = '\\' . ltrim($this->model, '\\');

        return new $class;
    }

    /**
     * Get a new query builder for the model instance.
     */
    protected function newModelQuery(?Model $model = null): Builder
    {
        return is_null($model)
            ? $this->createModel()->newQuery()
            : $model->newQuery();
    }

    public function retrieveByToken($identifier, $token)
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, $token)
    {
        // void
    }

    public function retrieveByCredentials(array $credentials)
    {
        return null;
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return false;
    }

    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false)
    {
        // TODO: Implement rehashPasswordIfRequired() method.
    }
}
