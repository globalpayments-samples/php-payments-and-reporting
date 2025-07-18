<?php

declare(strict_types=1);

/**
 * Logger Utility for Card Authentication
 *
 * This class provides structured logging for security events,
 * API calls, and system activities. Supports multiple log levels
 * and output formats for debugging and monitoring.
 *
 * PHP version 8.0 or higher
 *
 * @category  Utilities
 * @package   GlobalPayments_Examples
 * @author    Global Payments
 * @license   MIT License
 */

namespace GlobalPayments\Examples;

use DateTime;
use Exception;

class Logger
{
    // Log levels
    public const DEBUG = 'DEBUG';
    public const INFO = 'INFO';
    public const WARNING = 'WARNING';
    public const ERROR = 'ERROR';
    public const CRITICAL = 'CRITICAL';

    // Log channels
    public const CHANNEL_SECURITY = 'security';
    public const CHANNEL_API = 'api';
    public const CHANNEL_VERIFICATION = 'verification';
    public const CHANNEL_SYSTEM = 'system';

    private string $logDirectory;
    private string $logLevel;
    private bool $enableFileLogging;
    private bool $enableSyslog;
    private array $context;

    /**
     * Constructor
     *
     * @param string $logDirectory Directory for log files
     * @param string $logLevel Minimum log level to record
     * @param bool $enableFileLogging Enable file-based logging
     * @param bool $enableSyslog Enable system log integration
     */
    public function __construct(
        string $logDirectory = 'logs',
        string $logLevel = self::INFO,
        bool $enableFileLogging = true,
        bool $enableSyslog = false
    ) {
        $this->logDirectory = rtrim($logDirectory, '/');
        $this->logLevel = $logLevel;
        $this->enableFileLogging = $enableFileLogging;
        $this->enableSyslog = $enableSyslog;
        $this->context = [];

        $this->createLogDirectory();
    }

    /**
     * Set global context that will be included in all log entries
     *
     * @param array $context Global context data
     * @return self
     */
    public function setContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);
        return $this;
    }

    /**
     * Log debug message
     *
     * @param string $message Log message
     * @param array $context Additional context data
     * @param string $channel Log channel
     * @return void
     */
    public function debug(string $message, array $context = [], string $channel = self::CHANNEL_SYSTEM): void
    {
        $this->log(self::DEBUG, $message, $context, $channel);
    }

    /**
     * Log info message
     *
     * @param string $message Log message
     * @param array $context Additional context data
     * @param string $channel Log channel
     * @return void
     */
    public function info(string $message, array $context = [], string $channel = self::CHANNEL_SYSTEM): void
    {
        $this->log(self::INFO, $message, $context, $channel);
    }

    /**
     * Log warning message
     *
     * @param string $message Log message
     * @param array $context Additional context data
     * @param string $channel Log channel
     * @return void
     */
    public function warning(string $message, array $context = [], string $channel = self::CHANNEL_SYSTEM): void
    {
        $this->log(self::WARNING, $message, $context, $channel);
    }

    /**
     * Log error message
     *
     * @param string $message Log message
     * @param array $context Additional context data
     * @param string $channel Log channel
     * @return void
     */
    public function error(string $message, array $context = [], string $channel = self::CHANNEL_SYSTEM): void
    {
        $this->log(self::ERROR, $message, $context, $channel);
    }

    /**
     * Log critical message
     *
     * @param string $message Log message
     * @param array $context Additional context data
     * @param string $channel Log channel
     * @return void
     */
    public function critical(string $message, array $context = [], string $channel = self::CHANNEL_SYSTEM): void
    {
        $this->log(self::CRITICAL, $message, $context, $channel);
    }

    /**
     * Log security event
     *
     * @param string $event Security event type
     * @param string $message Event description
     * @param array $context Event context
     * @return void
     */
    public function security(string $event, string $message, array $context = []): void
    {
        $securityContext = array_merge($context, [
            'event_type' => $event,
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'timestamp' => time()
        ]);

        $this->log(self::WARNING, $message, $securityContext, self::CHANNEL_SECURITY);
    }

    /**
     * Log API request/response
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $requestData Request data (sensitive data will be masked)
     * @param array $responseData Response data
     * @param int $responseTime Response time in milliseconds
     * @return void
     */
    public function apiCall(
        string $method,
        string $endpoint,
        array $requestData = [],
        array $responseData = [],
        int $responseTime = 0
    ): void {
        $context = [
            'method' => $method,
            'endpoint' => $endpoint,
            'request_data' => $this->maskSensitiveData($requestData),
            'response_data' => $this->maskSensitiveData($responseData),
            'response_time_ms' => $responseTime,
            'ip_address' => $this->getClientIp()
        ];

        $message = sprintf('%s %s (%dms)', $method, $endpoint, $responseTime);
        $this->log(self::INFO, $message, $context, self::CHANNEL_API);
    }

    /**
     * Log verification attempt
     *
     * @param string $verificationType Type of verification
     * @param bool $success Whether verification was successful
     * @param string $responseCode Gateway response code
     * @param string $transactionId Transaction ID
     * @param array $additionalData Additional verification data
     * @return void
     */
    public function verification(
        string $verificationType,
        bool $success,
        string $responseCode,
        string $transactionId = '',
        array $additionalData = []
    ): void {
        $context = array_merge($additionalData, [
            'verification_type' => $verificationType,
            'success' => $success,
            'response_code' => $responseCode,
            'transaction_id' => $transactionId,
            'ip_address' => $this->getClientIp(),
            'timestamp' => time()
        ]);

        $status = $success ? 'SUCCESS' : 'FAILED';
        $message = sprintf('Card verification %s (%s)', $status, $verificationType);

        $level = $success ? self::INFO : self::WARNING;
        $this->log($level, $message, $context, self::CHANNEL_VERIFICATION);
    }

    /**
     * Main logging method
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Context data
     * @param string $channel Log channel
     * @return void
     */
    private function log(string $level, string $message, array $context = [], string $channel = self::CHANNEL_SYSTEM): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $logEntry = $this->formatLogEntry($level, $message, $context, $channel);

        // File logging
        if ($this->enableFileLogging) {
            $this->writeToFile($logEntry, $channel);
        }

        // Syslog integration
        if ($this->enableSyslog) {
            $this->writeToSyslog($level, $message, $context);
        }

        // Console output for CLI
        if (php_sapi_name() === 'cli') {
            echo $logEntry . PHP_EOL;
        }
    }

    /**
     * Check if log level should be recorded
     *
     * @param string $level Log level to check
     * @return bool
     */
    private function shouldLog(string $level): bool
    {
        $levels = [
            self::DEBUG => 0,
            self::INFO => 1,
            self::WARNING => 2,
            self::ERROR => 3,
            self::CRITICAL => 4
        ];

        return $levels[$level] >= $levels[$this->logLevel];
    }

    /**
     * Format log entry
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Context data
     * @param string $channel Log channel
     * @return string
     */
    private function formatLogEntry(string $level, string $message, array $context, string $channel): string
    {
        $timestamp = (new DateTime())->format('Y-m-d H:i:s.v');
        $contextData = array_merge($this->context, $context);

        $logData = [
            'timestamp' => $timestamp,
            'level' => $level,
            'channel' => $channel,
            'message' => $message,
            'context' => $contextData,
            'memory_usage' => memory_get_usage(true),
            'process_id' => getmypid()
        ];

        return json_encode($logData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Write log entry to file
     *
     * @param string $logEntry Formatted log entry
     * @param string $channel Log channel
     * @return void
     */
    private function writeToFile(string $logEntry, string $channel): void
    {
        try {
            $filename = $this->getLogFilename($channel);
            $filepath = $this->logDirectory . '/' . $filename;

            file_put_contents($filepath, $logEntry . PHP_EOL, FILE_APPEND | LOCK_EX);

            // Rotate log if it gets too large (10MB)
            if (filesize($filepath) > 10 * 1024 * 1024) {
                $this->rotateLog($filepath);
            }
        } catch (Exception $e) {
            // Fallback to error_log if file logging fails
            error_log("Logger error: " . $e->getMessage());
        }
    }

    /**
     * Write to system log
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Context data
     * @return void
     */
    private function writeToSyslog(string $level, string $message, array $context): void
    {
        $priority = match ($level) {
            self::DEBUG => LOG_DEBUG,
            self::INFO => LOG_INFO,
            self::WARNING => LOG_WARNING,
            self::ERROR => LOG_ERR,
            self::CRITICAL => LOG_CRIT,
            default => LOG_INFO
        };

        $logMessage = $message;
        if (!empty($context)) {
            $logMessage .= ' ' . json_encode($context);
        }

        syslog($priority, $logMessage);
    }

    /**
     * Get log filename for channel
     *
     * @param string $channel Log channel
     * @return string
     */
    private function getLogFilename(string $channel): string
    {
        $date = date('Y-m-d');
        return sprintf('%s-%s.log', $channel, $date);
    }

    /**
     * Rotate log file
     *
     * @param string $filepath Current log file path
     * @return void
     */
    private function rotateLog(string $filepath): void
    {
        $rotatedPath = $filepath . '.' . date('YmdHis');
        rename($filepath, $rotatedPath);

        // Compress rotated log
        if (function_exists('gzencode') && is_readable($rotatedPath)) {
            $compressed = gzencode(file_get_contents($rotatedPath));
            file_put_contents($rotatedPath . '.gz', $compressed);
            unlink($rotatedPath);
        }
    }

    /**
     * Mask sensitive data in logs
     *
     * @param array $data Data to mask
     * @return array
     */
    private function maskSensitiveData(array $data): array
    {
        $sensitiveKeys = [
            'card_number', 'cvv', 'cvn', 'password', 'secret', 'token',
            'api_key', 'authorization', 'payment_token'
        ];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->maskSensitiveData($value);
            } elseif (is_string($value) && in_array(strtolower($key), $sensitiveKeys)) {
                $data[$key] = $this->maskValue($value);
            }
        }

        return $data;
    }

    /**
     * Mask a sensitive value
     *
     * @param string $value Value to mask
     * @return string
     */
    private function maskValue(string $value): string
    {
        if (strlen($value) <= 4) {
            return str_repeat('*', strlen($value));
        }

        return substr($value, 0, 4) . str_repeat('*', strlen($value) - 8) . substr($value, -4);
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',           // Proxy
            'HTTP_X_FORWARDED_FOR',     // Load balancer/proxy
            'HTTP_X_FORWARDED',         // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP', // Cluster
            'HTTP_FORWARDED_FOR',       // Proxy
            'HTTP_FORWARDED',           // Proxy
            'REMOTE_ADDR'               // Standard
        ];

        foreach ($headers as $header) {
            if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                return trim($ips[0]);
            }
        }

        return 'unknown';
    }

    /**
     * Create log directory if it doesn't exist
     *
     * @return void
     */
    private function createLogDirectory(): void
    {
        if (!is_dir($this->logDirectory)) {
            mkdir($this->logDirectory, 0755, true);
        }
    }
}
