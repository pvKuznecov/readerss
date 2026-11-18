# RSS-читалка на Vue + PHP + GitHub Actions
## Идея
Фронтенд-читалка RSS-новостей без собственного бэкенда. Vue-приложение на GitHub Pages читает готовый data.json, который автоматически обновляется PHP-скриптом через GitHub Actions по расписанию.

## Почему не через CORS-прокси:
Браузер не может напрямую загружать большинство RSS-лент из-за CORS. Публичные прокси решают проблему, но зависят от чужого сервиса, видят твои ленты и могут подменить контент. GitHub Actions переносит сбор данных на сервер — архитектурно чище и логичнее.

## Архитектура
### Общий набросок:
```
GitHub Actions (cron, каждые 2 часа)
        │
        ▼
parse-feeds.php  ──►  скачивает RSS  ──►  парсит SimpleXML  ──►  data.json
        │
        ▼
git commit + push data.json
        │
        ▼
Vue-фронт (GitHub Pages)  ──►  fetch('./data.json')  ──►  рендер
```

### Структура репозитория:
```
.
├── .github/
│   └── workflows/
│       └── update-feeds.yml
├── parse-feeds.php
├── feeds.txt
├── data.json          # генерируется автоматически
├── index.html
├── src/               # Vue-приложение
└── README.md
```

## Шаг 1. Список лент — feeds.txt
Один URL на строку, пустые строки и # игнорируются.

```
https://example.com/rss.xml
https://another-site.com/feed
```

### Шаг 2. Парсер — parse-feeds.php
Чистый PHP, без зависимостей. SimpleXML для RSS, проверка на ошибки, сохранение в JSON.

```
php
<?php
declare(strict_types=1);

$feedsFile = __DIR__ . '/feeds.txt';
$outFile   = __DIR__ . '/data.json';
$maxItems  = 50; // сколько новостей хранить на ленту

$urls = [];
foreach (file($feedsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) {
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
            'user_agent' => 'RSSReader/1.0 (+https://github.com/pvKuznecov)',
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
```

Заметки по коду:
- stream_context_create с user_agent — многие сайты отдают 403 пустому UA.
- libxml_use_internal_errors(true) — чтобы битый XML не сыпал warning'ами, а обрабатывался через проверку === false.
- JSON_UNESCAPED_UNICODE — кириллица в JSON останется читаемой.
- Если лента Atom, а не RSS — структура другая (entry вместо item, namespace'ы). Начни с RSS, Atom добавишь отдельной веткой if.

## Шаг 3. Workflow — .github/workflows/update-feeds.yml

```
yaml
name: Update RSS Data

on:
  schedule:
    - cron: '0 */2 * * *'   # каждые 2 часа
  workflow_dispatch:         # ручной запуск кнопкой

permissions:
  contents: write            # нужно для push

jobs:
  update:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Parse feeds
        run: php parse-feeds.php

      - name: Commit and push
        run: |
          git config user.name "github-actions[bot]"
          git config user.email "github-actions[bot]@users.noreply.github.com"
          git add data.json
          if git diff --staged --quiet; then
            echo "No changes"
          else
            git commit -m "chore: update RSS data"
            git push
          fi
```

Заметки:
- permissions: contents: write — без этого git push упадёт (по умолчанию токен read-only).
- workflow_dispatch — можно запустить вручную из вкладки Actions, удобно для отладки.
- Проверка git diff --staged --quiet — не создаём пустые коммиты, если данные не изменились.
- Cron в GitHub Actions работает в UTC и может задерживаться на 10–15 минут — это нормально.

## Шаг 4. Фронт — Vue
Минимальная логика:

```
js
const res = await fetch('./data.json');
const feeds = await res.json();
// feeds: [{ source, title, items: [{ title, link, description, pubDate }], updated }]
```

### Правила безопасности и UX:
- Никогда не вставляй description через v-html без очистки — это чужой HTML, прямой путь к XSS. Используй textContent или простую очистку от тегов.
- Сортируй новости по pubDate на клиенте.
- Прочитанные храни в localStorage (ключ — link).
- Список подписок — тоже localStorage, но источник истины для сбора данных — feeds.txt в репозитории.

## Что показать в портфолио
В README проекта стоит явно перечислить, что демонстрирует этот репозиторий:
- Архитектурное решение проблемы CORS без публичных прокси.
- Парсинг XML на чистом PHP (SimpleXML, обработка ошибок, таймауты).
- Автоматизация через GitHub Actions: cron, ручной запуск, условный коммит.
- Разделение сбора данных и отображения (статический JSON как «контракт» между бэкендом и фронтом).
- Работа с Vue без сборщика или с минимальной сборкой — по желанию.

## Порядок работы (чтобы не запутаться)
- Локально: parse-feeds.php парсит одну ленту, выводит заголовки в консоль.
- Локально: тот же скрипт пишет data.json, проверяешь структуру глазами.
- Пушишь в репозиторий, добавляешь workflow, запускаешь вручную через workflow_dispatch.
- Смотришь, что data.json закоммитился. Проверяешь cron.
- Только после этого — Vue-фронт, который читает data.json.

Опционально: Atom-ленты, сортировка, фильтры, тёмная тема.
