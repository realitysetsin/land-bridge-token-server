<?php

define('TWILIO_ACCOUNT_SID',       'ACfdb1e374d1a0b57765ca9271c3b9880c');
define('TWILIO_API_KEY',           'SKc533dabb252865843d0efdc56156e7ab');
define('TWILIO_API_SECRET',        '65BkWIywGCcFTacrskhc08S4p0MPdNg5');
define('TWILIO_CONFIGURATION_SID', 'VSc40b1e2105e132ce61c98a514efaeaa0');

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require 'vendor/autoload.php';

use Twilio\Jwt\AccessToken;
use Twilio\Jwt\Grants\ConversationsGrant;

$app = new \Slim\App;
$app->get('/token', function (Request $request, Response $response) {

    $q = $request->getQueryParams();
    $identity = $q['identity'];

    // Create access token, which we will serialize and send to the client
    $token = new AccessToken(
        TWILIO_ACCOUNT_SID,
        TWILIO_API_KEY,
        TWILIO_API_SECRET,
        300,
        $identity
    );

    // Grant access to Conversations
    $grant = new ConversationsGrant();
    $grant->setConfigurationProfileSid(TWILIO_CONFIGURATION_SID);
    $token->addGrant($grant);

    $response->getBody()->write(json_encode(
        [
            'identity' => $identity,
            'token' => $token->toJWT(),
        ]
    ));

    return $response;
});
$app->post('/idm', function (Request $request, Response $response) {
    // passthrough
    $client = new GuzzleHttp\Client();
    $res = $client->post('https://api.beachbody.com/api/token', [
        'auth' => [
            $_SERVER['PHP_AUTH_USER'],
            $_SERVER['PHP_AUTH_PW']
        ],
        'verify' => false,
    ]);
    $response->getBody()->write($res->getBody());

    return $response;
});
$app->run();