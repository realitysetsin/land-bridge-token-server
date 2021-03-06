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

use Aws\Credentials\CredentialProvider;
use Aws\Credentials\Credentials;
use Aws\Result;

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
$app->post('/user', function (Request $request, Response $response) {
    $client = new GuzzleHttp\Client();
    $res = $client->post(
        'https://iupvv9x848.execute-api.us-west-2.amazonaws.com/test/registertrainer', [
        'body' => $request->getBody(),
        'verify' =>false,
    ]);

    $response->getBody()->write($res->getBody());

    return $response;
});

$app->get('/user', function (Request $request, Response $response) {

    $credentials= new Credentials(getenv('AccessKeyId'), getenv('AccessSecretKey'));

    $sdk = new Aws\Sdk([
        'region'   => 'us-west-2', // US West (Oregon) Region
        'version'  => 'latest',  // Use the latest version of the AWS SDK for PHP
        'credentials' => $credentials
    ]);

    // Create a new DynamoDB client
    $dynamodb = $sdk->createDynamoDb();

    $scan_response = $dynamodb->scan(array(
        'TableName' => 'trainer'
    ));

    $data = array();

    foreach ($scan_response['Items'] as $key => $value) {
        $record = array(
            "guid" => $value['guid']['S'],
            "fullname" => $value['fullname']['S'],
            "available" => $value['available']['S'],
            "starrating" => $value['starrating']['S'],
            "issubscriber" => $value['issubscriber']['S'],
        );
        $data[] = $record;
    }

    echo json_encode($data);

});

$app->put('/user', function (Request $request, Response $response) {

    $credentials= new Credentials(getenv('AccessKeyId'), getenv('AccessSecretKey'));

    $sdk = new Aws\Sdk([
        'region'   => 'us-west-2', // US West (Oregon) Region
        'version'  => 'latest',  // Use the latest version of the AWS SDK for PHP
        'credentials' => $credentials
    ]);

    $parsed_body = $request->getParsedBody();

    // Create a new DynamoDB client
    $dynamodb = $sdk->createDynamoDb();

    $response2 = $dynamodb->putItem([
        'TableName' => 'trainer',
        'Item' => [
            'guid'          => ['S'      => $parsed_body['guid']      ], // Primary Key
            'fullname'      => ['S'      => $parsed_body['fullname'] ],
            'available'     => ['S'      => $parsed_body['available'] ],
            'starrating'    => ['S'      => $parsed_body['starrating'] ],
            'issubscriber'  => ['S'      => $parsed_body['issubscriber'] ]
        ]
    ]);

    print_r($response2);

});

$app->get('/user/{guid}', function (Request $request) {

    $credentials= new Credentials(getenv('AccessKeyId'), getenv('AccessSecretKey'));

    $sdk = new Aws\Sdk([
        'region'   => 'us-west-2', // US West (Oregon d) Region
        'version'  => 'latest',  // Use the latest version of the AWS SDK for PHP
        'credentials' => $credentials
    ]);

    // Create a new DynamoDB client
    $dynamodb = $sdk->createDynamoDb();

    $route = $request->getAttribute('route');
    $guid = $route->getArgument('guid');


    $response = $dynamodb->query([
        'TableName' => 'trainer',
        'KeyConditionExpression' => 'guid = :v_guid',
        'ExpressionAttributeValues' =>  [
            ':v_guid' => ['S' => $guid]
        ]
    ]);

    $response_items = $response['Items'][0];

    $data = [
        "guid" => $response_items['guid']['S'],
        "fullname" => $response_items['fullname']['S'],
        "available" => $response_items['available']['S'],
        "starrating" => $response_items['starrating']['S'],
        "issubscriber" => $response_items['issubscriber']['S'],
    ];

    echo json_encode($data);

});

$app->run();