<?php
// Telegram-бот «Тёмный». Отвечает на /start прямо в ответе на webhook — токен на сервере не нужен.
header('Content-Type: application/json; charset=utf-8');
$u = json_decode(file_get_contents('php://input'), true);
$msg = $u['message'] ?? null;
$text = trim($msg['text'] ?? '');
if ($msg && ($text === '/start' || strpos($text, '/start ') === 0)) {
    echo json_encode(['method' => 'sendMessage', 'chat_id' => $msg['chat']['id'], 'text' => 'Напишите ваше сообщение'], JSON_UNESCAPED_UNICODE);
} else {
    echo '{}';
}
