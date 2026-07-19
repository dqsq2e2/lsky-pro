<?php

namespace App\Services;

use Sabre\DAV\Client;
use Sabre\HTTP\ClientException;
use Sabre\HTTP\ClientHttpException;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;
use Sabre\Uri;

class WebDavClient extends Client
{
    public function send(RequestInterface $request): ResponseInterface
    {
        $this->emit('beforeRequest', [$request]);

        $retryCount = 0;
        $redirects = 0;
        $originalAuthSettings = [];
        foreach ([CURLOPT_HTTPAUTH, CURLOPT_USERPWD] as $setting) {
            if (array_key_exists($setting, $this->curlSettings)) {
                $originalAuthSettings[$setting] = $this->curlSettings[$setting];
            }
        }

        try {
            do {
                $doRedirect = false;
                $retry = false;

                try {
                    $response = $this->doRequest($request);
                    $code = $response->getStatus();

                    if ($redirects < $this->maxRedirects && in_array($code, [301, 302, 307, 308], true)) {
                        $oldLocation = $request->getUrl();
                        $request = clone $request;
                        $request->setUrl(Uri\resolve($oldLocation, $response->getHeader('Location')));
                        $doRedirect = true;
                        ++$redirects;

                        if ($request->getMethod() === 'GET') {
                            unset($this->curlSettings[CURLOPT_HTTPAUTH], $this->curlSettings[CURLOPT_USERPWD]);
                        }
                    }

                    if ($code >= 400) {
                        $this->emit('error', [$request, $response, &$retry, $retryCount]);
                        $this->emit('error:' . $code, [$request, $response, &$retry, $retryCount]);
                    }
                } catch (ClientException $e) {
                    $this->emit('exception', [$request, $e, &$retry, $retryCount]);
                    if (! $retry) {
                        throw $e;
                    }
                }

                if ($retry) {
                    ++$retryCount;
                }
            } while ($retry || $doRedirect);

            $this->emit('afterRequest', [$request, $response]);

            if ($this->throwExceptions && $code >= 400) {
                throw new ClientHttpException($response);
            }

            return $response;
        } finally {
            foreach ([CURLOPT_HTTPAUTH, CURLOPT_USERPWD] as $setting) {
                if (array_key_exists($setting, $originalAuthSettings)) {
                    $this->curlSettings[$setting] = $originalAuthSettings[$setting];
                } else {
                    unset($this->curlSettings[$setting]);
                }
            }
        }
    }
}
