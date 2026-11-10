<?php
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);
echo date('Y-m-d H:i:s') . " deploy.php запущен, php " . PHP_VERSION . ", папка " . __DIR__ . PHP_EOL;
/*
 * Автообновление сайта «Тёмный» с GitHub.
 * Запускается по ссылке (её вызывает GitHub после каждого обновления кода, либо ты вручную).
 * Лежит в папке сайта; имя файла секретное, state и log лежат уровнем выше (из браузера недоступны) (из браузера недоступны).
 * Не трогает папки assets/ и cgi-bin/ (картинки, видео, служебные файлы хостинга).
 */

const REPO   = 'reinhardse/gorodtemniy-site';
const BRANCH = 'main';
const TARGET = __DIR__;                          // папка сайта (скрипт лежит прямо в ней)
const STATE  = __DIR__ . '/../deploy-state.txt';    // здесь храним хэш последнего коммита
const LOG    = __DIR__ . '/../deploy.log';
const KEEP   = ['cgi-bin', '.well-known'];            // эти папки не перезаписываем

function logmsg(string $m): void {
    echo $m . PHP_EOL;   // попадёт в вывод страницы
    if (@file_put_contents(LOG, date('Y-m-d H:i:s') . ' ' . $m . PHP_EOL, FILE_APPEND) === false) {
        echo 'Не удалось записать deploy.log' . PHP_EOL;
    }
}

function http_get(string $url): ?string {
    $headers = ['User-Agent: temny-deploy', 'Accept: application/vnd.github+json'];
    if (function_exists('curl_init')) {          // на большинстве хостингов есть curl
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($body !== false && $code === 200) ? $body : null;
    }
    $ctx = stream_context_create(['http' => [
        'header'  => implode("\r\n", $headers) . "\r\n",
        'timeout' => 60,
        'follow_location' => 1,
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    return $body === false ? null : $body;
}

echo 'curl: ' . (function_exists('curl_init') ? 'есть' : 'нет') . ', ZipArchive: ' . (class_exists('ZipArchive') ? 'есть' : 'нет') . ', папка сайта: ' . (is_dir(TARGET) ? 'найдена' : 'НЕ НАЙДЕНА') . PHP_EOL;

// 1. Узнаём хэш последнего коммита
$json = http_get('https://api.github.com/repos/' . REPO . '/commits/' . BRANCH);
if ($json === null) { logmsg('Не удалось связаться с GitHub'); exit(1); }
$data = json_decode($json, true);
$sha  = $data['sha'] ?? null;
if (!$sha) { logmsg('GitHub вернул непонятный ответ'); exit(1); }

// 2. Если ничего не поменялось, выходим
$prev = is_file(STATE) ? trim((string)file_get_contents(STATE)) : '';
if ($prev === $sha) { echo 'Без изменений' . PHP_EOL; exit(0); }

// 3. Скачиваем архив ветки
$zipData = http_get('https://codeload.github.com/' . REPO . '/zip/' . $sha);   // по хэшу коммита: так архив всегда свежий, без кэша ветки
if ($zipData === null) { logmsg('Не удалось скачать архив'); exit(1); }
$tmpZip = sys_get_temp_dir() . '/temny-' . $sha . '.zip';
file_put_contents($tmpZip, $zipData);

$zip = new ZipArchive();
if ($zip->open($tmpZip) !== true) { logmsg('Архив повреждён'); unlink($tmpZip); exit(1); }
$tmpDir = sys_get_temp_dir() . '/temny-' . $sha;
$zip->extractTo($tmpDir);
$zip->close();
unlink($tmpZip);

// архив GitHub распаковывается в одну папку вида gorodtemniy-site-main
$inner = glob($tmpDir . '/*', GLOB_ONLYDIR);
$src   = $inner ? $inner[0] : $tmpDir;

// 4. Копируем файлы в папку сайта (кроме KEEP)
$count = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    $rel = substr($file->getPathname(), strlen($src) + 1);
    $top = explode('/', $rel)[0];
    if (in_array($top, KEEP, true)) { continue; }
    $dest = TARGET . '/' . $rel;
    if (!is_dir(dirname($dest))) { mkdir(dirname($dest), 0755, true); }
    copy($file->getPathname(), $dest);
    $count++;
}

// 5. Убираем временные файлы и запоминаем версию
exec('rm -rf ' . escapeshellarg($tmpDir));
file_put_contents(STATE, $sha);
logmsg("Обновлено: $count файлов, коммит $sha");
exit(0);
