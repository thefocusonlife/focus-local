<?php

function verify_recaptcha_v3(string $token, string $expectedAction, string $secretKey, float $threshold = 0.5): bool
{
    if (empty($token) || empty($secretKey)) {
        return false;
    }

    $endpoint = 'https://www.google.com/recaptcha/api/siteverify';

    $postData = http_build_query([
        'secret'   => $secretKey,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'content' => $postData,
            'timeout' => 5,
        ],
    ];

    $context = stream_context_create($options);
    $result  = file_get_contents($endpoint, false, $context);

    if ($result === false) {
        // Optional: log error
        return false;
    }

    $data = json_decode($result, true);

    if (!($data['success'] ?? false)) {
        return false;
    }

    // Check that Google thinks this request is for the right action
    if (($data['action'] ?? '') !== $expectedAction) {
        return false;
    }

    // Check score
    $score = (float)($data['score'] ?? 0);
    if ($score < $threshold) {
        return false;
    }

    return true;
}
