<?php

namespace Tests\Unit;

use App\Services\WebDavClient;
use PHPUnit\Framework\TestCase;
use Sabre\HTTP\Request;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\Response;
use Sabre\HTTP\ResponseInterface;

class WebDavClientTest extends TestCase
{
    public function test_get_redirects_clear_authentication_after_the_first_request()
    {
        $client = $this->fakeClient();
        $response = $client->send(new Request('GET', 'https://example.com/file.png'));

        $this->assertSame(200, $response->getStatus());
        $this->assertSame([true, false], $client->authStates());
        $this->assertTrue($client->hasAuthentication());
    }

    public function test_put_redirects_keep_authentication()
    {
        $client = $this->fakeClient();
        $response = $client->send(new Request('PUT', 'https://example.com/file.png'));

        $this->assertSame(200, $response->getStatus());
        $this->assertSame([true, true], $client->authStates());
    }

    private function fakeClient(): WebDavClient
    {
        return new class([
            'baseUri' => 'https://example.com',
            'userName' => 'user',
            'password' => 'password',
            'authType' => 1,
        ]) extends WebDavClient {
            private array $authState = [];

            protected function doRequest(RequestInterface $request): ResponseInterface
            {
                $this->authState[] = array_key_exists(CURLOPT_USERPWD, $this->curlSettings);
                $response = new Response();
                if (count($this->authState) === 1) {
                    $response->setStatus(302);
                    $response->addHeader('Location', 'https://example.com/download/file.png');
                } else {
                    $response->setStatus(200);
                    $response->setBody('ok');
                }

                return $response;
            }

            public function authStates(): array
            {
                return $this->authState;
            }

            public function hasAuthentication(): bool
            {
                return array_key_exists(CURLOPT_USERPWD, $this->curlSettings);
            }
        };
    }
}
