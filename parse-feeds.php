<?php
declare(strict_types=1);

$feedsFile = __DIR__ . '/feeds.txt';
$outFile   = __DIR__ . '/frontend/public/data.json';
$maxItems  = 50; // сколько новостей хранить на ленту

$urls = [];
foreach (file($feedsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') {
    continue;
}
    $urls[] = $line;
}

$result = [];

foreach ($urls as $url) {
    // Скачиваем с таймаутом и User-Agent (некоторые сайты блокируют пустой UA)
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 15,
            'user_agent' => 'readerrs/1.0 (+https://github.com/pvKuznecov)',
        ],
    ]);
    $xmlText = @file_get_contents($url, false, $ctx);

    if ($xmlText === false) {
        fwrite(STDERR, "FAIL fetch: $url\n");
        continue;
    }

    libxml_use_internal_errors(true);
    $rss = simplexml_load_string($xmlText);
    if ($rss === false) {
        fwrite(STDERR, "FAIL parse: $url\n");
        continue;
    }

    $channelTitle = (string)($rss->channel->title ?? $url);
    $items = [];

    foreach ($rss->channel->item as $item) {
        $items[] = [
            'title'       => (string)$item->title,
            'link'        => (string)$item->link,
            'description' => (string)$item->description,
            // 'description' => trim(strip_tags((string)$item->description)),
            'pubDate'     => (string)$item->pubDate,
        ];
        if (count($items) >= $maxItems) {
            break;
        }
    }

    $result[] = [
        'source'  => $url,
        'title'   => $channelTitle,
        'items'   => $items,
        'updated' => date('c'),
    ];
}

file_put_contents(
    $outFile,
    json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
);

echo "Done: " . count($result) . " feeds\n";