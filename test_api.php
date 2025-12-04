<?php
// test_api.php

$url = 'https://api.h5p.org/v1/sites';
$data = http_build_query([
    'uuid' => '',
    'platform_name' => 'Test',
    'platform_version' => '1.0',
    'h5p_version' => '1.24',
    'core_api_version' => '1.24',
    'local_id' => 12345,
    'type' => 'local',
    'disabled' => 0
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

$response = curl_exec($ch);
echo "Respuesta: " . $response . "\n";

$result = json_decode($response);
if ($result && isset($result->uuid)) {
    echo "✅ UUID obtenido: " . $result->uuid . "\n";
} else {
    echo "❌ Error al registrar\n";
}
