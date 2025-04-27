<?php


namespace Anikeen\Id\Traits;

use Anikeen\Id\Result;
use GuzzleHttp\Exception\RequestException;


trait OauthTrait
{

    /**
     * Retrieving an oauth token using a given grant type.
     */
    public function retrievingToken(string $grantType, array $attributes): Result
    {
        try {
            $response = $this->client->request('POST', '/oauth/token', [
                'form_params' => $attributes + [
                        'grant_type' => $grantType,
                        'client_id' => $this->getClientId(),
                        'client_secret' => $this->getClientSecret(),
                    ],
            ]);

            $result = new Result($response, null);
        } catch (RequestException $exception) {
            $result = new Result($exception->getResponse(), $exception);
        }

        $result->anikeenId = $this;

        return $result;
    }
}