<?php 
namespace enex\sentry;

use Bitrix\Main\Config\Configuration;
use Bitrix\Main\Diag\ExceptionHandlerLog;
use Bitrix\Main\Diag\FileExceptionHandlerLog;
use Sentry\State\HubInterface;
use Throwable;

use function Sentry\captureException;
use function Sentry\init;

/**
 * Класс для логирования ошибок и отправки их в Sentry
 */
class SentryException extends FileExceptionHandlerLog
{
    /** @var int */
    protected int $level;

    /**
     * Запись ошибки в лог и отправка в Sentry
     *
     * @param Throwable $exception
     * @param int $logType
     * @return void
     */
    public function write($exception, $logType): void
    {
        if (!$this->shouldLogError($logType)) {
            return;
        }

        if ($exception instanceof Throwable) {
            $this->sendToSentry($exception);
        }

        parent::write($exception, $logType);
    }

    /**
     * Определяет, нужно ли логировать ошибку по её типу
     *
     * @param int $logType
     * @return bool
     */
    protected function shouldLogError(int $logType): bool
    {
        // По умолчанию ничего не фильтруем, можно расширить $logType == ExceptionHandlerLog::LOW_PRIORITY_ERROR;
        return false;
    }

    /**
     * Инициализация обработчика
     *
     * @param array $options
     * @return void
     */
    public function initialize(array $options): void
    {
        $this->level = $this->getSettingsErrorLevel();
        $this->initSentry();
        $this->setSentryUser();
        parent::initialize($options);
    }

    /**
     * Устанавливает пользователя Bitrix в контекст Sentry
     *
     * @return void
     */
    protected function setSentryUser(): void
    {
        global $USER;
        if (is_object($USER) && method_exists($USER, 'IsAuthorized') && $USER->IsAuthorized()) {
            if (function_exists('Sentry\\configureScope')) {
                \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($USER) {
                    $scope->setUser([
                        'id' => $USER->GetID(),
                        'email' => $USER->GetEmail(),
                        'username' => $USER->GetLogin(),
                    ]);
                });
            }
        }
    }

    /**
     * Инициализация подключения к Sentry
     *
     * @return void
     */
    protected function initSentry(): void
    {
        $environment = $_ENV['APP_ENV'] ?? "local";
        $dsn = $_ENV['SENTRY_DSN'] ?? null;
        $attachStacktrace = $_ENV['SENTRY_ATTACHSTACKTRACE'] ?? true;
        $sendDefaultPii = $_ENV['SENTRY_SEND_DEFAULT_PII'] ?? true;
        
        if ($environment && $dsn && $environment !== 'local' && function_exists('Sentry\init')) {
            init([
                'dsn'              => $dsn,
                'environment'      => $environment,
                'error_types'      => $this->level,
                'attach_stacktrace'=> $attachStacktrace,
                'send_default_pii' => $sendDefaultPii,
            ]);
        }
    }

    /**
     * Отправка уведомления в Sentry
     *
     * @param Throwable $exception
     * @return void
     */
    protected function sendToSentry(Throwable $exception): void
    {
        captureException($exception);
    }

    /**
     * Получить битовую маску отлавливаемых ошибок из конфига Битрикс
     *
     * @return int
     */
    /**
     * Получить битовую маску отлавливаемых ошибок из конфига Битрикс
     *      Подробная раскладка:
     *  E_ERROR             = 1
     *  E_WARNING           = 2
     *  E_PARSE             = 4
     *  E_CORE_ERROR        = 16
     *  E_CORE_WARNING      = 32
     *  E_COMPILE_ERROR     = 64
     *  E_COMPILE_WARNING   = 128
     *  E_USER_ERROR        = 256
     *  E_USER_WARNING      = 512
     *  E_STRICT            = 2048
     *  E_RECOVERABLE_ERROR = 4096
     *
     * @return int
     */
    protected function getSettingsErrorLevel(): int
    {
        $exceptionHandling = Configuration::getValue('exception_handling');
        $default =  1+2+4+16+32+64+128+256+512+2048+4096;
        return $exceptionHandling['handled_errors_types'] ?? $default;
    }
}