<?php

namespace Webman\support;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use support\Response;

class Http
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var string
     */
    protected $url;

    /**
     * @var string|null
     */
    protected $bodyFormat = null;

    /**
     * @var array|string
     */
    protected $body;

    /**
     * @var array
     */
    protected $headers = [];

    /**
     * @var array
     */
    protected $options = [];

    public function __construct()
    {
        $this->client = new Client([
            'cookies' => true
        ]);
    }

    /**
     * @param array $headers
     * @return $this
     */
    public function withHeaders(array $headers): self
    {
        foreach ($headers as $index => $header)
            $this->headers[$index] = $header;

        return $this;
    }

    /**
     * @param array $options
     * @return $this
     */
    public function withOptions(array $options): self
    {
        foreach ($options as $index => $option)
            $this->options[$index] = $option;

        return $this;
    }

    /**
     * @param string|array $name
     * @param string $contents
     * @param string|null $filename
     * @param array $headers
     * @return $this
     */
    public function attach($name, string $contents = '', string $filename = null, array $headers = []): self
    {
        if (is_array($name)) {
            foreach ($name as $file) {
                $this->attach(...$file);
            }

            return $this;
        }

        $this->asMultipart();

        $this->body[] = array_filter([
            'name' => $name,
            'contents' => $contents,
            'headers' => $headers,
            'filename' => $filename,
        ]);

        return $this;
    }

    /**
     * @param string $token
     * @param string $type
     * @return $this
     */
    public function withToken(string $token , string $type = 'Bearer'): self
    {
        return $this->withHeaders([
            'Authorization' => "$type $token"
        ]);
    }

    /**
     * @param string $username
     * @param string $password
     * @return $this
     */
    public function withBasicAuth(string $username, string $password): self
    {
        return $this->withHeaders([
            'auth' => [
                $username ,
                $password
            ]
        ]);
    }

    /**
     * @param string $username
     * @param string $password
     * @return $this
     */
    public function withDigestAuth(string $username, string $password): self
    {
        return $this->withHeaders([
            'auth' => [
                $username ,
                $password ,
                'digest'
            ]
        ]);
    }

    /**
     * @param string $userAgent
     * @return $this
     */
    public function withUserAgent(string $userAgent): self
    {
        return $this->withHeaders([
            'User-Agent' => $userAgent
        ]);
    }

    /**
     * @param array $cookies
     * @param string $domain
     * @return $this
     */
    public function withCookies(array $cookies, string $domain): self
    {
        return $this->withOptions([
            'cookies' => CookieJar::fromArray($cookies, $domain),
        ]);
    }

    /**
     * @return $this
     */
    public function withoutRedirecting(): self
    {
        return $this->withOptions([
            'allow_redirects' => false,
        ]);
    }

    /**
     * @return $this
     */
    public function withoutVerifying(): self
    {
        return $this->withOptions([
            'verify' => false,
        ]);
    }

    /**
     * @param int $seconds
     * @return $this
     */
    public function timeout(int $seconds): self
    {
        return $this->withOptions([
            'timeout' => $seconds,
        ]);
    }

    /**
     * @param string $contentType
     * @return $this
     */
    public function asBody(string $contentType): self
    {
        return $this->bodyFormat('body')->contentType($contentType);
    }

    /**
     * @return $this
     */
    public function asJson(): self
    {
        return $this->bodyFormat('json')->contentType('application/json');
    }

    /**
     * @return $this
     */
    public function asForm(): self
    {
        return $this->bodyFormat('form_params')->contentType('application/x-www-form-urlencoded');
    }

    /**
     * @return $this
     */
    public function asMultipart(): self
    {
        return $this->bodyFormat('multipart');
    }

    /**
     * @param string $format
     * @return $this
     */
    public function bodyFormat(string $format): self
    {
        $this->bodyFormat = $format;

        return $this;
    }

    /**
     * @param string $contentType
     * @return $this
     */
    public function contentType(string $contentType): self
    {
        return $this->withHeaders(['Content-Type' => $contentType]);
    }

    /**
     * @param string $contentType
     * @return $this
     */
    public function accept(string $contentType = 'application/json'): self
    {
        return $this->withHeaders(['Accept' => $contentType]);
    }

    /**
     * @param string $url
     * @param array $query
     * @return Response
     */
    public function head(string $url , array $query = []): Response
    {
        $this->parsGetRequest($url , $query);

        return $this->send('HEAD');
    }

    /**
     * @param string $url
     * @param array $query
     * @return Response
     */
    public function get(string $url , array $query = []): Response
    {
        $this->parsGetRequest($url , $query);

        return $this->send('GET');
    }

    /**
     * @param string $url
     * @param array $body
     * @param string $contentType
     * @return Response
     */
    public function post(string $url , array $body = [] , string $contentType = 'application/json'): Response
    {
        $this->parsPostRequest($url , $body , $contentType);

        return $this->send('POST');
    }

    /**
     * @param string $url
     * @param array $body
     * @param string $contentType
     * @return Response
     */
    public function patch(string $url , array $body = [] , string $contentType = 'application/json'): Response
    {
        $this->parsPostRequest($url , $body , $contentType);

        return $this->send('PATCH');
    }

    /**
     * @param string $url
     * @param array $body
     * @param string $contentType
     * @return Response
     */
    public function put(string $url , array $body = [] , string $contentType = 'application/json'): Response
    {
        $this->parsPostRequest($url , $body , $contentType);

        return $this->send('PUT');
    }

    /**
     * @param string $url
     * @param array $body
     * @param string $contentType
     * @return Response
     */
    public function delete(string $url , array $body = [] , string $contentType = 'application/json'): Response
    {
        $this->parsPostRequest($url , $body , $contentType);

        return $this->send('DELETE');
    }

    /**
     * @param string $url
     * @param array $body
     * @param string $contentType
     * @return void
     */
    protected function parsPostRequest(string $url , array $body , string $contentType): void
    {
        $this->url = $url;

        if (!empty($body)){
            if (is_null($this->bodyFormat) || $this->bodyFormat == 'body'){
                $this->asBody($contentType);
                $this->body = json_encode($body);
            }else{
                $this->body = $body;
            }
        }
    }

    /**
     * @param string $url
     * @param array $query
     * @return void
     */
    protected function parsGetRequest(string $url , array $query): void
    {
        if (!empty($query)){
            $url = "$url?";
            foreach ($query as $index => $item){
                $url .= "$index=$item&";
            }
            $url = substr($url, 0, -1);
        }
        $this->url = $url;
    }

    /**
     * @param string $type
     * @return Response
     */
    protected function send(string $type): Response
    {
        $options = [];

        if (!is_null($this->bodyFormat)){
            $options[$this->bodyFormat] = $this->body;
        }

        if (!empty($this->headers)){
            foreach ($this->headers as $index => $header){
                $options['headers'][$index] = $header;
            }
        }

        if (!empty($this->options)){
            foreach ($this->options as $index => $option){
                $options[$index] = $option;
            }
        }

        try {
            return new Response($this->client->request($type , $this->url , $options));
        }catch (RequestException|GuzzleException $exception){
            return new Response($exception->getResponse());
        }
    }
}