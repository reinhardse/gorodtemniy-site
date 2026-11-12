<?php
// Telegram-бот «Тёмный»: на /start отвечает фразой. Токен лежит в bot-token.php (его нет в репозитории).
$cfg = __DIR__ . '/bot-token.php';
if (!is_file($cfg)) { http_response_code(500); exit; }
$TOKEN = trim(require $cfg);

$u = json_decode(file_get_contents('php://input'), true);
$msg = $u['message'] ?? null;
if (!$msg) { exit('ok'); }

$chat = $msg['chat']['id'];
$text = trim($msg['text'] ?? '');

function tg($method, $data) {
    global $TOKEN;
    $ch = curl_init("https://api.telegram.org/bot$TOKEN/$method");
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $data, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $r = curl_exec($ch); curl_close($ch); return $r;
}

if ($text === '/start' || strpos($text, '/start ') === 0) {
    tg('sendMessage', ['chat_id' => $chat, 'text' => 'Напишите ваше сообщение']);
}
echo 'ok';
