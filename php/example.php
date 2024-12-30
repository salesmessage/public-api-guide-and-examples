<?php

const OAUTH_BASE_URL = 'https://app.salesmessage.com/auth/oauth';
const API_BASE_URL = 'https://api.salesmessage.com/pub/v2.2';

const CLIENT_ID = '';
const CLIENT_SECRET = '';
const REDIRECT_URL = '';

session_start();

function clearSession()
{
    session_destroy();
    header('Location: ' . REDIRECT_URL);
    die();
}

function displayStartPage()
{
    $session = $_SESSION;
    if (empty($_SESSION['refresh_token']) || empty($_SESSION['access_token'])) {
        echo '
            <a href="' . REDIRECT_URL . '?step=redirect' . '">Connect To Salesmsg</a>
            <br/>
        ';
    } else {
        echo '
            <a href="' . REDIRECT_URL . '?step=refresh' . '">Refresh Token</a>
            <br/>
            <a href="' . REDIRECT_URL . '?step=clear' . '">Clear Session</a>
            <br/>
            <a href="' . REDIRECT_URL . '?step=contact_list' . '">Show Last 15 Contacts</a>
            <br/>
            Session:
        ';
        print_r($session);
    }
}

function redirectToAuthorizationURL()
{
    $url = OAUTH_BASE_URL . '?response_type=code&client_id='
        . CLIENT_ID . '&redirect_uri='
        . REDIRECT_URL . '&scope=public-api&state=xcoiv98y2kd22vusuye3kch';
    header('Location: ' . $url);
    die();
}

function proccessCallbackCode()
{
    $code = $_GET['code'];

    $postParameters = [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'client_id' => CLIENT_ID,
        'client_secret' => CLIENT_SECRET,
        'redirect_uri' => REDIRECT_URL,
    ];

    $tokens = postRequest(API_BASE_URL . '/oauth/token', $postParameters);
    $_SESSION['access_token'] = $tokens['access_token'];
    $_SESSION['refresh_token'] = $tokens['refresh_token'];

    header('Location: ' . REDIRECT_URL);
    die();
}

function refreshToken()
{
    $postParameters = [
        'grant_type' => 'refresh_token',
        'client_id' => CLIENT_ID,
        'client_secret' => CLIENT_SECRET,
        'refresh_token' => $_SESSION['refresh_token'],
        'scope' => 'public-api',
    ];

    $tokens = postRequest(API_BASE_URL . '/oauth/token/refresh', $postParameters);
    $_SESSION['access_token'] = $tokens['access_token'];
    $_SESSION['refresh_token'] = $tokens['refresh_token'];

    header('Location: ' . REDIRECT_URL);
    die();
}

function getContacts()
{
    echo '
            <a href="' . REDIRECT_URL . '">Back</a>
            <br/>
        ';

    $postParameters = [
        'useShortResponse' => true,
        'page' => 0,
        'length' => 15,
        'sortBy' => 'created_at',
        'sortOrder' => 'desc',
    ];

    $authorization = 'Authorization: Bearer ' . $_SESSION['access_token'];
    $contacts = postRequest(API_BASE_URL . '/contacts/list', $postParameters, [$authorization]);

    if (empty($contacts['data'])) {
        echo 'No contacts found.';
    } else {
        echo '
            <table>
                <thead>
                    <tr>
                      <th scope="col">Name</th>
                      <th scope="col">Number</th>
                      <th scope="col">Created At</th>
                    </tr>
                </thead>
        ';
        foreach ($contacts['data'] as $contact) {
            echo '
                <tr>
                    <td>' . $contact['full_name'] . '</td>
                    <td>' . $contact['number'] . '</td>
                    <td>' . $contact['created_at'] . '</td>
                </tr>
            ';
        }
        echo '</table>';
    }
}

function postRequest(string $url, array $params = [], array $headers = []): array
{
    $curlHandle = curl_init($url);

    if (count($headers)) {
        curl_setopt($curlHandle, CURLOPT_HTTPHEADER, $headers);
    }
    curl_setopt($curlHandle, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);

    $curlResponse = curl_exec($curlHandle);
    curl_close($curlHandle);

    return json_decode($curlResponse, true);
}


$step = $_GET['step'] ?? 'start';
if (!empty($_GET['code'])) {
    $step = 'callback';
}

match ($step) {
    'start' => displayStartPage(),
    'redirect' => redirectToAuthorizationURL(),
    'callback' => proccessCallbackCode(),
    'clear' => clearSession(),
    'refresh' => refreshToken(),
    'contact_list' => getContacts(),
};
