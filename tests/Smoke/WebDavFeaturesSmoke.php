<?php

use App\Services\CachedWebDavAdapter;
use App\Services\WebDavClient;
use App\Services\WebDavFileCache;
use Sabre\HTTP\Request;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\Response;
use Sabre\HTTP\ResponseInterface;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$client = new class([
    'baseUri' => 'https://example.com',
    'userName' => 'user',
    'password' => 'password',
    'authType' => 1,
]) extends WebDavClient {
    public array $authState = [];

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
};
$response = $client->send(new Request('GET', 'https://example.com/file.png'));
$assert($response->getStatus() === 200, 'WebDAV GET redirect was not followed.');
$assert($client->authState === [true, false], 'WebDAV credentials were forwarded after a GET redirect.');
$assert(class_exists(CachedWebDavAdapter::class), 'Cached WebDAV adapter cannot be loaded.');

$root = sys_get_temp_dir() . '/lsky-webdav-smoke-' . bin2hex(random_bytes(6));
$cache = new WebDavFileCache(1, 2, $root);
$cache->put('first.png', 'first');
usleep(1000);
$cache->put('second.png', 'second');
usleep(1000);
$assert($cache->get('first.png') === 'first', 'WebDAV cache cannot read a stored file.');
usleep(1000);
$cache->put('third.png', 'third');
$assert($cache->get('first.png') === 'first', 'Recently used WebDAV cache entry was evicted.');
$assert($cache->get('second.png') === null, 'Least recently used WebDAV cache entry was not evicted.');
$assert($cache->get('third.png') === 'third', 'Newest WebDAV cache entry is missing.');
$cache->clear();
@rmdir($root);

fwrite(STDOUT, "WebDAV feature smoke test passed.\n");
