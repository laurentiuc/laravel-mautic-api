<?php

namespace Triibo\Mautic\Factories;

use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Triibo\Mautic\Models\MauticConsumer;
use GuzzleHttp\Exception\ClientException;
use Mautic\Auth\OAuth;

class MauticFactory
{
    /**
     * Get default connection.
     *
     * @return  array
     */
    public function getDefaultConnection()
    {
        $connectionName = config("mautic.default");

        return config("mautic.connections.$connectionName");
    }

    /**
     * Make a new Mautic url.
     *
     * @param   string|null     $endpoints
     * @return  string
     */
    protected function getMauticUrl(?string $endpoints = null)
    {
        $conn = $this->getDefaultConnection();
        $url  = $conn["baseUrl"] . "/";

        return (!empty($endpoints)) ? $url . $endpoints : $url;
    }

    /**
     * Check AccessToken Expiration Time.
     *
     * @param   int     $expireTimestamp
     * @return  bool
     */
    public function checkExpirationTime(int $expireTimestamp)
    {
        $now = time();

        return ($now > $expireTimestamp) ? true : false;
    }

    /**
     * Make a new Mautic client.
     *
     * @param   array   $config
     * @return  MauticConsumer
     */
    public function make(array $config)
    {
        $config = $this->getConfig($config);

        return $this->getClient($config);
    }

    /**
     * Get the configuration data.
     *
     * @param   array   $config
     * @throws  InvalidArgumentException
     * @return  array
     */
    protected function getConfig(array $config)
    {
        $keys = ["clientKey", "clientSecret"];

        foreach ($keys as $key) {
            if (!array_key_exists($key, $config)) {
                throw new \InvalidArgumentException("The Mautic client requires configuration.");
            }
        }

        return Arr::only($config, ["version", "baseUrl", "clientKey", "clientSecret", "callback"]);
    }

    /**
     * Get the Mautic client.
     *
     * @param   array   $setting
     * @return  MauticConsumer
     */
    protected function getClient(array $setting): MauticConsumer
    {
        session_name("mauticOAuth");
        session_start();

        /** @var OAuth $auth */
        $auth     = (new OAuth())->setup(
            baseUrl: $setting["baseUrl"],
            clientKey: $setting["clientKey"],
            clientSecret: $setting["clientSecret"],
        );

        // Initiate process for obtaining an access token; this will redirect the user to the $authorizationUrl and/or
        // set the access_tokens when the user is redirected back after granting authorization

        if ($auth->validateAccessToken()) {
            if ($auth->accessTokenUpdated()) {
                $accessTokenData = $auth->getAccessTokenData();
                return  MauticConsumer::create($accessTokenData);
            }
        }

        return MauticConsumer::create([
            "access_token"  => null,
            "token_type"    => null,
            "refresh_token" => null,
            "expires"       => null,
        ]);
    }

    /**
     * Call Mautic Api
     *
     * @param   string  $method
     * @param   string  $endpoints
     * @param   array   $body
     * @param   string  $token
     * @return  mixed
     */
    public function callMautic($method, $endpoints, $body, $token)
    {
        $mauticURL = $this->getMauticUrl("api/$endpoints");
        $conn      = $this->getDefaultConnection();

        $params    = [];

        if (!empty($body)) {
            foreach ($body as $key => $item) {
                $params["form_params"][$key] = $item;
            }
        }

        if ($conn["version"] == "BasicAuth") {
            $user = $conn["username"];
            $pass = $conn["password"];
            $b64  = base64_encode("$user:$pass");
            $auth = "Basic $b64";
        } else {
            $auth = "Bearer $token";
        }

        $headers = [
            "headers" => [
                "Authorization" => $auth,
            ]
        ];

        $client  = new Client($headers);

        try {
            $response = $client->request($method, $mauticURL, $params);

            return json_decode($response->getBody(), true);
        } catch (ClientException $e) {
            return $e->getResponse()->getStatusCode();
        }
    }

    /**
     * Generate new token once old one expire and store in consumer table.
     *
     * @param   string  $refreshToken
     * @return  MauticConsumer|array
     */
    public function refreshToken(string $refreshToken)
    {
        $mauticURL = $this->getMauticUrl("oauth/v2/token");
        $config    = config("mautic.connections.main");
        $client    = new Client();

        try {
            $response = $client->request("POST", $mauticURL, array(
                "form_params" => [
                    "client_id"     => $config["clientKey"],
                    "client_secret" => $config["clientSecret"],
                    "redirect_uri"  => $config["callback"],
                    "refresh_token" => $refreshToken,
                    "grant_type"    => "refresh_token"
                ]
            ));

            $response = json_decode($response->getBody(), true);

            return MauticConsumer::create([
                "access_token"  => $response["access_token"],
                "token_type"    => $response["token_type"],
                "refresh_token" => $response["refresh_token"],
                "expires"       => time() + $response["expires_in"],
            ]);
        } catch (ClientException $e) {
            $response = $e->getResponse()->getBody();
            $errors   = json_decode($response, true);

            return $errors;
        }
    }
}
