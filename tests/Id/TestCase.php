<?php

namespace Anikeen\Id\Tests;

use Anikeen\Id\AnikeenId;
use Anikeen\Id\Contracts\AppTokenRepository;
use Anikeen\Id\Providers\AnikeenIdServiceProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected MockHandler $http;

    /** @var list<array{request: \Psr\Http\Message\RequestInterface}> */
    protected array $sent = [];

    protected function getPackageProviders($app): array
    {
        return [AnikeenIdServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.anikeen.client_id' => 'app-client', 'services.anikeen.client_secret' => 'secret']);

        $this->http = new MockHandler();
        $stack = HandlerStack::create($this->http);
        $stack->push(Middleware::history($this->sent));

        $this->app->instance(AnikeenId::class, (new AnikeenId())->setHttpClient(new Client(['handler' => $stack, 'base_uri' => 'https://id.test/api/'])));
        $this->app->instance(AppTokenRepository::class, new class implements AppTokenRepository {
            public int $forgotten = 0;

            public function getAccessToken(): string
            {
                return $this->forgotten ? 'fresh-app-token' : 'app-token';
            }

            public function forgetAccessToken(): void
            {
                $this->forgotten++;
            }
        });
    }

    protected function respond(int $status, array $body = []): void
    {
        $this->http->append(new Response($status, ['Content-Type' => 'application/json'], json_encode($body)));
    }

    protected function sentJson(int $index): array
    {
        return json_decode((string) $this->sent[$index]['request']->getBody(), true) ?? [];
    }
}
