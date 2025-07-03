# BitrixCMS-Sentry

## Описание

Модуль для интеграции Bitrix CMS с Sentry. Позволяет отправлять PHP-ошибки и исключения из Bitrix напрямую в Sentry, а также вести локальный лог ошибок.

## Требования

- Composer
- PHP >= 8.0
- Bitrix Framework
- Sentry SDK >= 4.5.0

## Установка

```bash
composer require enex/sentry
```

## Настройка

### Подключение composer autoload

В файле `init.php` подключите autoload Composer, если это ещё не сделано:

```php
require_once($_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php');
```

### Переменные окружения

В проекте используются следующие переменные окружения:

| Переменная                | Описание                                                                                 | Пример значения                |
|---------------------------|----------------------------------------------------------------------------------------|-------------------------------|
| `APP_ENV`                 | Окружение приложения. Если `local`, ошибки не отправляются в Sentry.                    | `production`, `local`         |
| `SENTRY_DSN`              | DSN (Data Source Name) для подключения к вашему проекту Sentry.                         | `https://<key>@sentry.io/<id>`|
| `SENTRY_ATTACHSTACKTRACE` | Включать ли stacktrace для ошибок (bool, строка или число).                             | `true`, `false`, `1`, `0`     |
| `SENTRY_SEND_DEFAULT_PII` | Отправлять ли персональные данные пользователя (bool, строка или число).                | `true`, `false`, `1`, `0`     |

> Все переменные можно определить в файле `.env` в корне проекта.

### Загрузка переменных из .env

Пакет использует `vlucas/phpdotenv` для загрузки переменных окружения. В `init.php` добавьте:

```php
if (class_exists('Dotenv\\Dotenv')) {
    $env = Dotenv\Dotenv::createImmutable($_SERVER['DOCUMENT_ROOT']);
    try {
        $env->load();
    } catch (Exception $e) {}
}
```

Для старых версий dotenv используйте `create()` вместо `createImmutable()`.

### Настройка Bitrix

В файле `bitrix/.settings.php` в секции `[exception_handling][value][log]` укажите:

```php
'class_name' => '\\enex\\sentry\\SentryException'
```

Пример:

```php
'exception_handling' => [
    'value' => [
        'debug' => false,
        'handled_errors_types' => 4437, // битовая маска типов ошибок
        'exception_errors_types' => 4437,
        'ignore_silence' => true,
        'assertion_throws_exception' => true,
        'assertion_error_type' => 256,
        'log' => [
            'settings' => [
                'file' => 'bitrix/modules/error.log',
            ],
            'class_name' => '\\enex\\sentry\\SentryException',
        ],
    ],
    'readonly' => false,
],
```

### Использование и особенности

- Класс `SentryException` наследует Bitrix\Main\Diag\FileExceptionHandlerLog и полностью совместим с Bitrix.
- Ошибки фильтруются через метод `shouldLogError($logType)`. По умолчанию игнорируются только LOW_PRIORITY_ERROR, но логику можно изменить.
- Для отправки ошибок в Sentry используется SDK >= 4.5.0
- Битовая маска ошибок по умолчанию (4437) включает основные типы ошибок PHP:

```
E_ERROR             = 1
E_WARNING           = 2
E_PARSE             = 4
E_CORE_ERROR        = 16
E_CORE_WARNING      = 32
E_COMPILE_ERROR     = 64
E_COMPILE_WARNING   = 128
E_USER_ERROR        = 256
E_USER_WARNING      = 512
E_STRICT            = 2048
E_RECOVERABLE_ERROR = 4096
```

### Пример расширения фильтрации ошибок

```php
protected function shouldLogError(int $logType): bool
{
    // Например, логировать только ошибки кроме LOW_PRIORITY и NOTICE
    return !in_array($logType, [ExceptionHandlerLog::LOW_PRIORITY_ERROR, E_NOTICE], true);
}
```

### Дополнительные параметры Sentry

В методе инициализации Sentry можно использовать хуки:

```php
'before_send_check_in' => function (\Sentry\Event $event) {
    // Изменить check-in или вернуть null
    return $event;
},
'before_send_metrics' => function (\Sentry\Event $event) {
    // Изменить метрики или вернуть null
    return $event;
},
```

## Лицензия

Apache License
