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
    private AnikeenId $anikeenId;
    private ?string $accessTokenField = null;
    private array $fields;
    private string $model;
    private Request $request;

    public function __construct(
        AnikeenId $anikeenId,
        Request   $request,
        string    $model,
        array     $fields,
        ?string   $accessTokenField = null
    )
    {
        $this->request = $request;
        $this->model = $model;
        $this->fields = $fields;
        $this->accessTokenField = $accessTokenField;
        $this->anikeenId = $anikeenId;
    }

    public function retrieveById(mixed $identifier): Builder|Model|null
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
}
