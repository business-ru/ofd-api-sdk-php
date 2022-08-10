<?php

namespace Ofd\Api\Adapter\IlluminateOfdApi\Log;

use Monolog\Formatter\LogstashFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;
use Ofd\Api\Adapter\IlluminateOfdApi\Interfaces\LogInterface;
use Ofd\Api\Exception\SimpleLogException;
use Psr\Log\InvalidArgumentException;

class Logger implements LogInterface
{
    /**
     * @var MonologLogger
     */
    private MonologLogger $logger;

    /**
     * Директория логов
     * @var string
     */
    private string $logPath = __DIR__  . '/../../../../logs';

    /**
     * The Log levels.
     *
     * @var array
     */
    protected array $levels = [
        'debug' => MonologLogger::DEBUG,
        'info' => MonologLogger::INFO,
        'notice' => MonologLogger::NOTICE,
        'warning' => MonologLogger::WARNING,
        'error' => MonologLogger::ERROR,
        'critical' => MonologLogger::CRITICAL,
        'alert' => MonologLogger::ALERT,
        'emergency' => MonologLogger::EMERGENCY,
    ];

    /**
     * Parse the string level into a Monolog constant.
     *
     * @param array $config
     * @return int
     *
     * @throws InvalidArgumentException
     */
    protected function level(array $config): int
    {
        $level = $config['level'] ?? 'debug';

        if (isset($this->levels[$level])) {
            return $this->levels[$level];
        }

        throw new InvalidArgumentException('Invalid log level.');
    }

    /**
     * @throws SimpleLogException
     */
    public function __construct()
    {
        if (!is_dir($this->logPath)
            && !mkdir($concurrentDirectory = $this->logPath)
            && !is_dir($concurrentDirectory)) {
            throw new SimpleLogException('Невозможно создать директорию для хранения логов /src/logs/');
        }
        $this->logger = new MonologLogger('OfdApiSDK');
    }

    public function info(string $message, array $context = []): void
    {
        $this->writeLog('info', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->writeLog('error', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->writeLog('warning', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->writeLog('critical', $message, $context);
    }

    private function writeLog(string $level, string $message, array $context = []): void
    {
        $logger = $this->logger;

        $formatter = new LogstashFormatter('OfdApiSDK');
        $record = [
            'level_name' => $level,
            'level' => $this->level(['level' => $level]),
            'channel' => 'daily',
            'message' => $message,
            'context' => $context
        ];
        $formatter->format($record);
        $logFileDaily = $level . '-' . date("Y-m-d");
        $handler = new StreamHandler($this->logPath . "/$logFileDaily.log", $this->level(['level' => $level]));
        $handler->setFormatter($formatter);

        $logger->pushHandler($handler);
        $logger->$level($message, $context);
    }
}
