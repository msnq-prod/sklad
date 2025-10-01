# AdamRMS — self‑hosted инвентаризация, проекты, уведомления

Этот репозиторий содержит серверную часть AdamRMS — многофункциональной self‑hosted системы для управления оборудованием, проектами, пользователями и уведомлениями. Проект написан на PHP 8, использует Twig для серверных шаблонов, MySQL в качестве СУБД, Phinx для миграций, и интеграции с почтовыми сервисами, S3‑совместимым хранилищем и Stripe. Для быстрого старта предусмотрены Dockerfile и docker‑compose, а также полноценная Dev Container конфигурация.

Ниже — подробная, практичная и исчерпывающая документация по установке, конфигурации, структуре кода и ключевым подсистемам. Если вы только начинаете, начните с «Быстрый старт».

**Содержание**
- Быстрый старт
- Возможности
- Архитектура и зависимости
- Структура репозитория
- Установка и запуск (Docker / DevContainer / вручную)
- Конфигурация (через UI и переменные окружения)
- Почтовые уведомления и шаблоны
- Файловое хранилище (S3) и CDN
- Аутентификация, права и сессии
- Уведомления в системе
- API и OpenAPI
- Миграции и данные по умолчанию
- Разработка и отладка
- Безопасность и продакшен‑настройки
- Обновление и обслуживание
- Частые проблемы и диагностика
- Лицензия

**Важно**: по умолчанию проект развернётся с дефолтным пользователем. Сразу после первого входа смените пароль и пройдите базовую настройку конфигурации в Server → Config.

## Быстрый старт

Вариант A — docker‑compose (локальная среда):
- Убедитесь, что установлен Docker и docker‑compose.
- Проверьте файл `.env` в корне. В нём должен быть `MYSQL_ROOT_PASSWORD` (по умолчанию присутствует). Пример: `MYSQL_ROOT_PASSWORD=strongpassword`.
- Запустите: `docker compose up -d`.
- Приложение доступно на `http://localhost:8088` (настройка в `docker-compose.yml`:8088→80).
- При первом старте контейнер автоматически применит миграции и сиды, затем запустит Apache (см. `migrate.sh`).

Дефолтные креды (после сидов):
- Логин: `username`
- Пароль: `password!`

После входа откройте Server → Config и заполните обязательные параметры (как минимум `Root URL`).

Вариант B — Dev Container (VS Code):
- Откройте репо в VS Code → «Reopen in Container».
- Поднимутся сервисы: MySQL, S3 mock, phpMyAdmin, Mailpit. Порты:
  - Веб: `http://localhost:8080`
  - S3 mock: `http://localhost:8081`
  - phpMyAdmin: `https://localhost:8082`
  - Mailpit UI (почта для разработки): `http://localhost:8083`
- Миграции и сиды выполняются автоматически (`postStartCommand`).

## Возможности
- Многоарендность («instances») и права доступа на уровне сервера и инстанса.
- Учёт оборудования и типов, штрих‑коды, истории сканирований, группы.
- Проекты: состав команды, статусы, планирование, привязка активов к проектам.
- Техобслуживание: заявки, статусы, назначение, уведомления.
- Клиенты и локации, CMS‑страницы для дашборда.
- Уведомления по email через Sendgrid/Mailgun/Postmark/SMTP; шаблоны на Twig.
- Хранилище файлов (S3‑совместимое), загрузки из браузера и сервера, опциональный CDN.
- OAuth вход: Google и Microsoft, magic‑links, JWT для приложений.
- Экспорт календаря (iCal), генерация QR/штрих‑кодов, экосистема OpenAPI аннотаций.

## Архитектура и зависимости
- Язык/стек: PHP 8.x + Apache (см. `Dockerfile`), Twig 3.x, Composer зависимости (`composer.json`).
- База данных: MySQL 8 (`db/`, Phinx миграции `phinx.php`).
- Обязательные расширения PHP: `mysqli`, `intl`, `gd`, `zip` (см. `Dockerfile`).
- Ключевые пакеты:
  - `twig/twig` — шаблоны интерфейса и писем.
  - `robmorgan/phinx` — миграции БД и сиды.
  - `aws/aws-sdk-php` — S3 / CloudFront.
  - Почта: `sendgrid/sendgrid`, `mailgun/mailgun-php`, `wildbit/postmark-php`, `phpmailer/phpmailer`.
  - Аутентификация: `firebase/php-jwt`, `hybridauth/hybridauth` (OAuth Google/Microsoft).
  - Деньги/валюта: `moneyphp/money`.
  - Баркоды/QR: `picqer/php-barcode-generator`, `bacon/bacon-qr-code`.
  - Документация API: `zircote/swagger-php` (OpenAPI аннотации).

## Структура репозитория
- `src/` — корень веб‑приложения (Apache DocumentRoot перенаправлен сюда).
  - `index.php` — дашборд/роутинг главной: `src/index.php:1`.
  - `common/` — общие либы, конфиг, Twig, CSP, Sentry и т. п.
    - `common/head.php` — инициализация Twig, БД, конфигурации, CSP, Sentry: `src/common/head.php:1`.
    - `common/libs/Config/` — схема конфигурации и UI формы: `src/common/libs/Config/configStructureArray.php:1`.
    - `common/libs/Auth/` — аутентификация, токены, права, телеметрия: `src/common/libs/Auth/main.php:1`.
    - `common/libs/Email/` — провайдеры почты и логирование писем: `src/common/libs/Email/EmailHandler.php:1`.
    - `common/libs/twigExtensions.php` — расширения Twig (фильтры/функции): `src/common/libs/twigExtensions.php:1`.
  - `api/` — REST эндпоинты, аннотации OpenAPI.
    - `api/apiHead.php` — общий заголовок JSON API (CORS, payload mirroring): `src/api/apiHead.php:1`.
    - `api/apiHeadSecure.php` — проверка авторизации для защищённых эндпоинтов: `src/api/apiHeadSecure.php:1`.
    - `api/notifications/` — система уведомлений (типизация и отправка писем).
    - другие модули: `assets/`, `projects/`, `instances/`, `maintenance/`, `file/`, `s3files/`, `cms/` и т. д.
  - `login/` — страницы входа, регистрации и OAuth: `src/login/index.php:1`.
  - `server/` — серверная админка (права, пользователи, аудит, конфигурация).
  - `.htaccess` — обработка 404: `src/.htaccess:1`.
- `db/` — миграции (`db/migrations`) и сиды (`db/seeds`).
- `phinx.php` — конфиг Phinx (подхватывает `DB_*` из окружения): `phinx.php:1`.
- `migrate.sh` — entrypoint контейнера: миграции+сиды, затем Apache: `migrate.sh:1`.
- `Dockerfile` — PHP 8.3 Apache образ с зависимостями.
- `docker-compose.yml` — локальная сборка (app + MySQL).
- `.devcontainer/` — расширенная среда разработки (S3 mock, phpMyAdmin, Mailpit).

## Установка и запуск

Вариант A — Docker Compose (прод/локалка):
- Проверьте `.env` (значение `MYSQL_ROOT_PASSWORD`).
- Запустите: `docker compose up -d`.
- БД: контейнер `adamrms_db` (MySQL 8), порт `3306` проброшен наружу.
- Приложение: контейнер `adamrms_app`, порт `8088` → `80` (Apache).
- При старте `adamrms_app` выполнит `phinx migrate` и `phinx seed:run` (см. `migrate.sh`).

Вариант B — VS Code Dev Container:
- Откройте репозиторий в контейнере, дождитесь `postCreateCommand`: `composer install` и последующих миграций.
- Присутствуют сервисы: `db`, `s3filestore`, `phpmyadmin`, `mailpit` (см. `.devcontainer/docker-compose.yml:1`).
- Переменные окружения для контейнера `app` инициализируют file‑storage, SMTP и root URL.

Вариант C — ручной деплой без Docker:
- Требования: PHP 8.0+, расширения `mysqli`, `intl`, `gd`, `zip`; веб‑сервер (Apache/Nginx), MySQL 8.
- `composer install` в корне проекта.
- Настройте виртуальный хост так, чтобы DocumentRoot указывал на `src/`.
- Пропишите переменные окружения (см. раздел «Конфигурация» ниже) или задайте их через Systemd/Apache env.
- Выполните миграции и сиды: `php vendor/bin/phinx migrate -e production && php vendor/bin/phinx seed:run -e production`.

## Конфигурация

Конфигурация хранится в БД (таблица `config`) согласно схеме `configStructureArray.php` и подхватывает значения из переменных окружения, если ключ присутствует (envFallback). При первой загрузке UI предложит заполнить недостающие поля через встроенную форму.

Минимальные переменные окружения БД (всегда):
- `DB_HOSTNAME`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_PORT` (опционально, по умолчанию 3306)
- `DEV_MODE` — `true` включает подробные ошибки и отключает Twig‑кэш.

Ключевые конфиги через UI/ENV:
- Общие:
  - `CONFIG_ROOTURL` → `ROOTURL` (обязателен). Пример: `http://localhost:8088` или публичный домен.
  - `TIMEZONE` (UI) — часовой пояс инстанса.
- Email (`group: Email`):
  - `CONFIG_EMAILS_ENABLED` → `EMAILS_ENABLED` (значения: `Enabled`/`Disabled`).
  - `CONFIG_EMAILS_PROVIDER` → `EMAILS_PROVIDER` (`Sendgrid`/`Mailgun`/`Postmark`/`SMTP`).
  - `CONFIG_EMAILS_FROM_EMAIL` → `EMAILS_FROMEMAIL` (адрес отправителя).
  - API ключи: `bCMS__SendGridAPIKEY` для Sendgrid, или настройте SMTP:
    - `CONFIG_EMAILS_SMTP_SERVER`, `CONFIG_EMAILS_SMTP_PORT`, `CONFIG_EMAILS_SMTP_USERNAME`, `CONFIG_EMAILS_SMTP_PASSWORD`, `CONFIG_EMAILS_SMTP_ENCRYPTION` (`None`/`SSL`/`TLS`).
  - Дополнительно: `EMAILS_FOOTER` — HTML‑футер писем.
- Файлы/S3 (`group: File Storage`):
  - `FILES_ENABLED` (UI) — включает функциональность загрузки файлов.
  - `CONFIG_AWS_S3_KEY`/`CONFIG_AWS_S3_SECRET`/`CONFIG_AWS_S3_BUCKET`.
  - `CONFIG_AWS_S3_BROWSER_ENDPOINT` и `CONFIG_AWS_S3_SERVER_ENDPOINT` (для браузера и сервера соответственно; в Docker могут отличаться).
  - `CONFIG_AWS_S3_REGION`, `CONFIG_AWS_S3_ENDPOINT_PATHSTYLE` (`Enabled` при использовании s3mock/MinIO),
  - Опц. CDN: `CONFIG_AWS_CLOUDFRONT_ENDPOINT`, пары ключей для приватного доступа.
- OAuth (`group: Authentication`):
  - Google: `AUTH_PROVIDERS_GOOGLE_KEYS_ID`, `AUTH_PROVIDERS_GOOGLE_KEYS_SECRET`; редиректы: `${ROOTURL}/login/oauth/google.php` и `${ROOTURL}/api/account/oauth-link/google.php`.
  - Microsoft: `AUTH_PROVIDERS_MICROSOFT_APP_ID`, `AUTH_PROVIDERS_MICROSOFT_KEYS_SECRET`; редиректы: `${ROOTURL}/login/oauth/microsoft.php` и `${ROOTURL}/api/account/oauth-link/microsoft.php`.
  - JWT: `CONFIG_AUTH_JWTKey` (ровно 64 символа A‑Z0‑9), `AUTH_NEXTHASH` (`sha256`/`sha512`).
- Биллинг (`group: Billing`):
  - Stripe: ключ `STRIPE_KEY`, `STRIPE_WEBHOOK_SECRET` (при необходимости).
- Телеметрия (`group: Telemetry`):
  - `TELEMETRY_MODE` (`Disabled`/`Limited`/`Standard`), `TELEMETRY_SHOW_URL`, `TELEMETRY_NOTES`.

Примечания:
- Значения, заданные через ENV, считаются валидными без валидации интерфейса и используются как дефолт при отсутствии записей в БД.
- Для отправки почты через SMTP в Dev Container всё уже настроено на Mailpit (`CONFIG_EMAILS_PROVIDER=SMTP`, `CONFIG_EMAILS_SMTP_SERVER=mailpit`, порт 1025).

## Почтовые уведомления и шаблоны

Базовый рендеринг:
- Вызов `sendEmail()` формирует HTML из шаблона и отправляет через выбранного провайдера: `src/api/notifications/email/email.php:1`.
- Выбор провайдера определяется `EMAILS_PROVIDER` (`Sendgrid`, `Mailgun`, `Postmark`, `SMTP`). Реализации: `src/common/libs/Email/*.php`.
- Отправленные письма логируются в таблицу `emailSent` через `EmailHandler::logEmail()`.

Базовый шаблон письма:
- Файл: `src/api/notifications/email/email_template.twig:1`.
- Переменные контекста: `SUBJECT`, `TEMPLATE` (опц. Twig‑включение контента), `HTML` (сырой HTML), `INSTANCE` (имя/картинка заголовка), `CONFIG`, `FOOTER`.
- Hero‑изображение вверху: выводится, если у инстанса задан файл `instances_emailHeader` и включено файловое хранилище.
- Футер: настраивается через `EMAILS_FOOTER` (HTML разрешён).

Кастомизация контента:
- Можно передавать `TEMPLATE` — путь к другому Twig‑файлу с контентом сообщения (включится `{% include TEMPLATE %}`).
- Альтернатива — `HTML|raw` (контент инлайн).
- Готовые шаблоны под события: `src/api/instances/addUser-EmailTemplate.twig`, `src/api/maintenance/*-EmailTemplate.twig`, `src/api/projects/crew/assign-EmailTemplate.twig`.

Отправка в разработке:
- В Dev Container вся исходящая почта приходит в Mailpit (`http://localhost:8083`).

## Файловое хранилище (S3) и CDN

Включение:
- Включите `FILES_ENABLED` в конфиге и заполните S3 параметры. Для разработки в Dev Container используется `adobe/s3mock` с path‑style.

Загрузка и получение:
- Браузерная загрузка идёт через выдачу временных подписей/API (см. `src/api/file/*`, `src/api/s3files/appUploader.php:1`).
- Генерация ссылок на файлы в Twig — через фильтр `|s3URL`: `src/common/libs/twigExtensions.php:1`.

CDN/CloudFront:
- Необязательный слой CDN можно указать в `AWS_CLOUDFRONT_ENDPOINT` и, при необходимости, настроить приватный доступ (пара ключей, приватный ключ в конфиге).

## Аутентификация, права и сессии

- Сессии и токены: класс `bID` управляет токенами (`web-session`, `app-v1`, `app-v2-magic-email`) и проверяет IP/прокси: `src/common/libs/Auth/main.php:1`.
- JWT (`firebase/php-jwt`) используется для обмена с мобильным приложением и magic‑link.
- Пароли: хранение с солью и алгоритмом из `AUTH_NEXTHASH` (`sha256` по умолчанию).
- OAuth: Google/Microsoft через Hybridauth (`src/login/oauth/*.php`).
- Права:
  - Серверные (`serverActions`) — роли/позиции на уровне всего сервера.
  - Инстансовые (`instanceActions`) — права в рамках выбранной «компании/инстанса».
- Переключение инстанса и выбор дефолтного инстанса происходит автоматически при логине/редиректах.

## Уведомления в системе

- Типы уведомлений и каналы определены в `src/api/notifications/notificationTypes.php:1`.
- Отправка — через функцию `notify($typeId, $userId, $instanceId, $headline, $message, $emailTemplate, $array)` из `src/api/notifications/main.php:1`.
- Каналы сейчас: Post (зарезервировано) и Email.

## API и OpenAPI

- Все API‑эндпоинты возвращают JSON и настраивают кросс‑доменные заголовки, см. `src/api/apiHead.php:1` (загрузка payload из тела в `$_POST`/`$_GET` также реализована там).
- Защищённые эндпоинты должны подключать `apiHeadSecure.php` (проверка токена/прав) — `src/api/apiHeadSecure.php:1`.
- Аннотации OpenAPI — в докблоках (`@OA\...`). Заголовочные блоки — `src/api/openApiYAMLHeaders.php:1`.
- Генерация OpenAPI спецификации (пример): `vendor/bin/openapi --output openapi.yaml src/api`.

## Миграции и данные по умолчанию

- Миграции/сиды — Phinx (`vendor/bin/phinx`). Конфиг: `phinx.php:1`.
- Сиды по умолчанию:
  - Пользователь `username` с паролем `password!` и ролью «Super‑admin»: `db/seeds/DefaultUserSeeder.php:1`.
  - Права/позиции «Administrator/Super‑admin»: `db/seeds/PositionsSeeder.php:1`.
  - Категории/группы/производители — базовые справочники: `db/seeds/AssetCategorySeeder.php:1`, `db/seeds/ManufacturersSeeder.php`.

## Разработка и отладка

- Включение DEV режима: `DEV_MODE=true` (детальные ошибки, Twig без кэша, дополнительные проверки).
- Статические ресурсы и UI: шаблоны Twig в `src/*.twig`, компоненты AdminLTE в `src/static-assets`.
- Twig фильтры/функции доступны из `src/common/libs/twigExtensions.php:1` (например, `money`, `s3URL`, `timeago`).
- Почта в dev: Mailpit на `http://localhost:8083`.
- Отладка API: все ответы проходят через `finish()` (см. `src/api/apiHead.php:1`).

## Безопасность и продакшен‑настройки

- Обязательно задайте корректный `ROOTURL`, `AUTH_JWTKey`, отключите `DEV_MODE`.
- Настройте реальную почту (провайдер/SMTP) и домен отправителя (SPF/DKIM/DMARC).
- CSP: строгие заголовки собираются в `head.php` и отдаются через `header('Content-Security-Policy: ...')`.
- Прокси/real IP: в проверке токена учитываются `CF-Connecting-IP`/`X-Forwarded-For`.
- Sentry: укажите DSN в `ERRORS_PROVIDERS_SENTRY` для отправки ошибок в продакшене.

## Обновление и обслуживание

- Пересоберите контейнер после обновления зависимостей/кода: `docker compose build --no-cache && docker compose up -d`.
- Выполните миграции при обновлениях схемы: `php vendor/bin/phinx migrate -e production`.
- Резервные копии: снимайте дампы БД и файлового хранилища (если используете локальное/S3‑совместимое).

## Частые проблемы и диагностика

- Не поднимается БД/приложение: проверьте `.env` (пароль root), переменные `DB_*` в `docker-compose.yml` и логи контейнеров (`docker compose logs`).
- Повторно появляется форма конфигурации: таблица `config` пуста или часть значений невалидна — заполните форму или задайте ключи через ENV (`CONFIG_*`).
- Письма не уходят: включите `EMAILS_ENABLED=Enabled`, проверьте провайдера/ключи/SMTP; в dev проверьте Mailpit.
- Файлы/S3 не работают: проверьте `FILES_ENABLED`, путь/эндпоинты S3 и `path-style` режим для эмуляторов.
- Проблемы с OAuth: проверьте redirect URI в консолях Google/Microsoft — см. раздел конфигурации.

Примечание: в базовом шаблоне письма блок hero зависит от наличия переменных инстанса и включенного хранилища. Если вы кастомизируете контекст, убедитесь, что передаёте необходимые значения (`INSTANCE` и включённые файлы), иначе условие не сработает.

## Лицензия

AGPL‑3.0‑only (см. `LICENSE`). Любые модификации и сетевое разворачивание подразумевают публикацию исходного кода модификаций согласно условиям AGPL.
