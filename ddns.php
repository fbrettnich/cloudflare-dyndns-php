<?php

header('Content-Type: application/json');

if(!isset($_GET['email']) || !isset($_GET['api_key']) || !isset($_GET['domain']) || !isset($_GET['record']) || !isset($_GET['ip'])) {
    die(json_encode(
        [
            "success" => false,
            "message" => "Not all arguments were set!",
            "synology_code" => "911"
        ]
    ));
}

$email = $_GET['email']; // Cloudflare account email
$api_key = $_GET['api_key']; // Cloudflare Global API Key
$domain = $_GET['domain']; // Cloudflare Domain (example.com)
$record = $_GET['record']; // DNS Record (my-ddns.example.com)
$ip = $_GET['ip']; // New IP (1.1.1.1)
$ttl = empty($_GET['ttl']) ? 1 : $_GET['ttl']; // TTL (1 = auto)


// Authentication Header
$headers = [
    'X-Auth-Email: ' . $email,
    'X-Auth-Key: ' . $api_key,
    'Content-Type: application/json'
];

// DNS Entry Data
$data = [
    'type' => 'A',
    'name' => $record,
    'content' => $ip,
    'ttl' => (int)$ttl,
    'proxied' => false // Do you want to enable the Cloudflare proxy? Attention! Here the IP address is disguised and can no longer be used for e.g. VPN connections - should only be used for pure websites
];


// Get Cloudflare Zone
$result = sendRequest("https://api.cloudflare.com/client/v4/zones?name={$domain}", "GET");
$resultJson = json_decode($result, true);

if(!$resultJson['success']) {
    die(json_encode(
        [
            "success" => false,
            "message" => "Cloudflare error response #1",
            "synology_code" => "911",
            "cloudflare_errors" => ($resultJson['errors'] == null) ? $resultJson : $resultJson['errors']
        ]
    ));
}

$cloudflareZoneID = $resultJson['result']['0']['id'];


// Get Cloudflare DNS record
$result = sendRequest("https://api.cloudflare.com/client/v4/zones/{$cloudflareZoneID}/dns_records?name={$record}", "GET");
$resultJson = json_decode($result, true);

if(!$resultJson['success']) {
    die(json_encode(
        [
            "success" => false,
            "message" => "Cloudflare error response #2",
            "synology_code" => "911",
            "cloudflare_errors" => ($resultJson['errors'] == null) ? $resultJson : $resultJson['errors']
        ]
    ));
}

$cloudflareDnsEntryId = $resultJson['result']['0']['id'];
$cloudflareCurrentIp = $resultJson['result']['0']['content'];


if ($cloudflareCurrentIp == $ip) {

    // The new IP address is already the current..
    die(json_encode(
        [
            "success" => true,
            "message" => "The IP doesn't have to be changed!",
            "synology_code" => "good"
        ]
    ));

} else {

    // Update Cloudflare DNS entry with new IP
    $result = sendRequest("https://api.cloudflare.com/client/v4/zones/{$cloudflareZoneID}/dns_records/{$cloudflareDnsEntryId}", "PUT", true);
    $resultJson = json_decode($result, true);

    if($resultJson['success']) {

        die(json_encode(
            [
                "success" => true,
                "message" => "The IP address was successfully changed from {$cloudflareCurrentIp} to {$ip}!",
                "synology_code" => "good"
            ]
        ));

    } else {

        die(json_encode(
            [
                "success" => false,
                "message" => "Cloudflare error response #3",
                "synology_code" => "911",
                "cloudflare_errors" => ($resultJson['errors'] == null) ? $resultJson : $resultJson['errors']
            ]
        ));

    }
}

// Cloudflare Request
function sendRequest($url, $requestMethod, $postData = false)
{
    global $headers, $data;

    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $requestMethod);
    if ($postData) curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($curl);

    if (curl_errno($curl)) {
        die(json_encode(
            [
                "success" => false,
                "message" => "cURL Error: " . curl_error($curl),
                "synology_code" => "911"
            ]
        ));
    }

    curl_close($curl);

    return $result;
}
