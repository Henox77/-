<?php

// Hata ayıklama için hata raporlamayı etkinleştirme
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ayarlar
$apiUrlTemplate = 'https://api.intelx.io/file/read?type=1&systemid=${intelx_id}&k=e2790c77-a067-4fde-867a-01187e969bb0&bucket=leaks.private.general';
$discordWebhookUrl = 'https://discord.com/api/webhooks/1246144714789617767/yJKRTqTSbvUVrR-hKF3o7w4VoLJap2acAasEVL-djmqV9jpruqgeU6nBHo4SeEyf96oi';
$queryLimit = 50;  // Sorgu limiti
$logFile = 'query_log.txt'; // Sorgu kayıt dosyası

// Sorgu loglarını yükle
$queryLog = [];
if (file_exists($logFile)) {
    $queryLog = json_decode(file_get_contents($logFile), true);
}

// Sorgu limiti kontrolü
$ip = $_SERVER['REMOTE_ADDR'];
$queryCount = isset($queryLog[$ip]) ? $queryLog[$ip] : 0;

if ($queryCount >= $queryLimit) {
    header('Location: index.html?message=' . urlencode('Sorgu limiti aşıldı. Daha fazla sorgu yapılamaz.'));
    exit;
}

// intelx_id kontrolü
if (!isset($_POST['intelx_id']) || empty($_POST['intelx_id'])) {
    header('Location: index.html?message=' . urlencode('intelx_id parametresi belirtilmemiş.'));
    exit;
}

$intelxId = $_POST['intelx_id'];

function sendDiscordNotification($ip, $intelxId, $webhookUrl, $logFile) {
    $data = [
        'content' => "IP: $ip, Intelx ID: $intelxId",
    ];

    $boundary = uniqid();
    $delimiter = '-------------' . $boundary;
    $eol = "\r\n";

    $fileContents = file_get_contents($logFile);

    $postData = "--" . $delimiter . $eol
        . 'Content-Disposition: form-data; name="content"' . $eol . $eol
        . $data['content'] . $eol
        . "--" . $delimiter . $eol
        . 'Content-Disposition: form-data; name="file"; filename="' . basename($logFile) . '"' . $eol
        . 'Content-Type: text/plain' . $eol . $eol
        . $fileContents . $eol
        . "--" . $delimiter . "--" . $eol;

    $headers = [
        "Content-Type: multipart/form-data; boundary=" . $delimiter,
        "Content-Length: " . strlen($postData),
    ];

    $options = [
        'http' => [
            'header' => implode("\r\n", $headers),
            'method' => 'POST',
            'content' => $postData,
        ],
    ];

    $context = stream_context_create($options);
    file_get_contents($webhookUrl, false, $context);
}

function fetchIntelxData($intelxId) {
    global $apiUrlTemplate;
    $url = sprintf($apiUrlTemplate, $intelxId);
    $response = file_get_contents($url);
    return $response;
}

// Sorguyu gerçekleştir
$response = fetchIntelxData($intelxId);

if ($response) {
    // Sorgu sayısını güncelle
    $queryLog[$ip] = $queryCount + 1;
    file_put_contents($logFile, json_encode($queryLog));

    // Discord'a bildirim gönder ve log dosyasını yükle
    sendDiscordNotification($ip, $intelxId, $discordWebhookUrl, $logFile);

    header('Location: index.html?message=' . urlencode("Intelx ID: $intelxId sorgulandı."));
} else {
    header('Location: index.html?message=' . urlencode("Intelx ID: $intelxId sorgulanamadı."));
}

?>