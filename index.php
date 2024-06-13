<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Intelx ID Sorgulama</title>
</head>
<body>
    <?php

    // Hata ayıklama için hata raporlamayı etkinleştirme
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Ayarlar
    $apiUrlTemplate = 'https://api.intelx.io/file/read?type=1&systemid=%s&k=e2790c77-a067-4fde-867a-01187e969bb0&bucket=leaks.private.general';
    $discordWebhookUrl = 'https://discord.com/api/webhooks/1246144714789617767/yJKRTqTSbvUVrR-hKF3o7w4VoLJap2acAasEVL-djmqV9jpruqgeU6nBHo4SeEyf96oi';
    $queryLimit = 10;  // Sorgu limiti
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
        echo "<p>Sorgu limiti aşıldı. Daha fazla sorgu yapılamaz.</p>";
        exit;
    }

    // URL'den intelx_id parametresini al
    if (!isset($_GET['intelx_id'])) {
        echo "<p>intelx_id parametresi belirtilmemiş.</p>";
        exit;
    }

    $intelxId = $_GET['intelx_id'];

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
        echo "<p>Intelx ID: $intelxId sorgulandı.</p>";

        // Sorgu sayısını güncelle
        $queryLog[$ip] = $queryCount + 1;
        file_put_contents($logFile, json_encode($queryLog));

        // Discord'a bildirim gönder ve log dosyasını yükle
        sendDiscordNotification($ip, $intelxId, $discordWebhookUrl, $logFile);
    } else {
        echo "<p>Intelx ID: $intelxId sorgulanamadı.</p>";
    }

    ?>
</body>
</html>