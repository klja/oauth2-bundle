<?php

namespace App\Tests\Acceptance;

use Trikoder\Bundle\OAuth2Bundle\Event\UserResolveEvent;
use Trikoder\Bundle\OAuth2Bundle\Manager\RefreshTokenManagerInterface;
use Trikoder\Bundle\OAuth2Bundle\Tests\Acceptance\AbstractAcceptanceTest;
use Trikoder\Bundle\OAuth2Bundle\Tests\Fixtures\FixtureFactory;
use Trikoder\Bundle\OAuth2Bundle\Tests\TestHelper;

final class TokenEndpointTest extends AbstractAcceptanceTest
{
    public function testSuccessfulClientCredentialsRequest()
    {
        $this->client->request('POST', '/token', [
            'client_id' => 'foo',
            'client_secret' => 'secret',
            'grant_type' => 'client_credentials',
            'scope' => 'fancy',
        ]);

        $response = $this->client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json; charset=UTF-8', $response->headers->get('Content-Type'));

        $jsonResponse = json_decode($response->getContent(), true);

        $this->assertSame('Bearer', $jsonResponse['token_type']);
        $this->assertSame(3600, $jsonResponse['expires_in']);
        $this->assertNotEmpty($jsonResponse['access_token']);
    }

    public function testFailedClientCredentialsRequestForMissingScope()
    {
        $this->client->request('POST', '/token', [
            'client_id' => 'foo',
            'client_secret' => 'secret',
            'grant_type' => 'client_credentials',
        ]);

        $response = $this->client->getResponse();

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));

        $jsonResponse = json_decode($response->getContent(), true);

        $this->assertSame('invalid_scope', $jsonResponse['error']);
        $this->assertSame('The requested scope is invalid, unknown, or malformed', $jsonResponse['message']);
        $this->assertSame('Specify a scope in the request or set a default scope', $jsonResponse['hint']);
    }

    public function testSuccessfulPasswordRequest()
    {
        $this->client
            ->getContainer()
            ->get('event_dispatcher')
            ->addListener('trikoder.oauth2.user_resolve', function (UserResolveEvent $event) {
                $event->setUser(FixtureFactory::createUser());
            });

        $this->client->request('POST', '/token', [
            'client_id' => 'foo',
            'client_secret' => 'secret',
            'grant_type' => 'password',
            'scope' => 'fancy',
            'username' => 'user',
            'password' => 'pass',
        ]);

        $response = $this->client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json; charset=UTF-8', $response->headers->get('Content-Type'));

        $jsonResponse = json_decode($response->getContent(), true);

        $this->assertSame('Bearer', $jsonResponse['token_type']);
        $this->assertSame(3600, $jsonResponse['expires_in']);
        $this->assertNotEmpty($jsonResponse['access_token']);
        $this->assertNotEmpty($jsonResponse['refresh_token']);
    }

    public function testSuccessfulRefreshTokenRequest()
    {
        $refreshToken = $this->client
            ->getContainer()
            ->get(RefreshTokenManagerInterface::class)
            ->find(FixtureFactory::FIXUTRE_REFRESH_TOKEN);

        $this->client->request('POST', '/token', [
            'client_id' => 'foo',
            'client_secret' => 'secret',
            'grant_type' => 'refresh_token',
            'refresh_token' => TestHelper::generateEncryptedPayload($refreshToken),
        ]);

        $response = $this->client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json; charset=UTF-8', $response->headers->get('Content-Type'));

        $jsonResponse = json_decode($response->getContent(), true);

        $this->assertSame('Bearer', $jsonResponse['token_type']);
        $this->assertSame(3600, $jsonResponse['expires_in']);
        $this->assertNotEmpty($jsonResponse['access_token']);
        $this->assertNotEmpty($jsonResponse['refresh_token']);
    }

    public function testFailedTokenRequest()
    {
        $this->client->request('GET', '/token');

        $response = $this->client->getResponse();

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));

        $jsonResponse = json_decode($response->getContent(), true);

        $this->assertSame('unsupported_grant_type', $jsonResponse['error']);
        $this->assertSame('The authorization grant type is not supported by the authorization server.', $jsonResponse['message']);
        $this->assertSame('Check that all required parameters have been provided', $jsonResponse['hint']);
    }

    public function testFailedClientCredentialsTokenRequest()
    {
        $this->client->request('POST', '/token', [
            'client_id' => 'foo',
            'client_secret' => 'wrong',
            'grant_type' => 'client_credentials',
        ]);

        $response = $this->client->getResponse();

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));

        $jsonResponse = json_decode($response->getContent(), true);

        $this->assertSame('invalid_client', $jsonResponse['error']);
        $this->assertSame('Client authentication failed', $jsonResponse['message']);
    }
}
