<?php

/**
 * Error Handler Utility for Card Verification
 *
 * This class provides centralized error handling, formatting,
 * and response generation for the card verification system.
 * Includes security-focused error sanitization and logging.
 *
 * PHP version 8.0 or higher
 *
 * @category  Utilities
 * @package   GlobalPayments_Examples
 * @author    Global Payments
 * @license   MIT License
 */

declare(strict_types=1);

namespace GlobalPayments\Examples;

use Exception;
use Throwable;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\Entities\Exceptions\GatewayException;
use GlobalPayments\Api\Entities\Exceptions\BuilderException;
use GlobalPayments\Api\Entities\Exceptions\ConfigurationException;

class ErrorHandler
{
    private Logger $logger;
    private bool $debugMode;
    private array $errorCodes;

    /**
     * Constructor
     *
     * @param Logger|null $logger Logger instance
     * @param bool $debugMode Enable debug mode for detailed errors
     */
    public function __construct(?Logger $logger = null, bool $debugMode = false)
    {
        $this->logger = $logger ?? new Logger();
        $this->debugMode = $debugMode;
        $this->initializeErrorCodes();

        // Register error and exception handlers
        $this->registerHandlers();
    }

    /**
     * Handle API exceptions and return appropriate response
     *
     * @param Throwable $exception The exception to handle
     * @param string $context Context where the error occurred
     * @return array Error response array
     */
    public function handleException(Throwable $exception, string $context = 'general'): array
    {
        $errorId = $this->generateErrorId();

        // Log the error
        $this->logError($exception, $context, $errorId);

        // Determine error type and create response
        $errorData = $this->analyzeException($exception);

        $response = [
            'success' => false,
            'error' => [
                'id' => $errorId,
                'code' => $errorData['code'],
                'message' => $errorData['message'],
                'type' => $errorData['type'],
                'context' => $context
            ],
            'timestamp' => date('c')
        ];

        // Add debug information if in debug mode
        if ($this->debugMode) {
            $response['debug'] = [
                'exception_class' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $this->sanitizeStackTrace($exception->getTrace())
            ];
        }

        return $response;
    }

    /**
     * Handle payment-specific errors
     *
     * @param string $responseCode Gateway response code
     * @param string $responseMessage Gateway response message
     * @param array $additionalData Additional error context
     * @return array Payment error response
     */
    public function handlePaymentError(
        string $responseCode,
        string $responseMessage,
        array $additionalData = []
    ): array {
        $errorId = $this->generateErrorId();

        // Map response code to user-friendly message
        $userMessage = $this->mapPaymentErrorMessage($responseCode, $responseMessage);
        $category = $this->categorizePaymentError($responseCode);

        // Log payment error
        $this->logger->warning('Payment error occurred', [
            'error_id' => $errorId,
            'response_code' => $responseCode,
            'response_message' => $responseMessage,
            'category' => $category,
            'additional_data' => $additionalData
        ], Logger::CHANNEL_VERIFICATION);

        return [
            'success' => false,
            'error' => [
                'id' => $errorId,
                'code' => $responseCode,
                'message' => $userMessage,
                'type' => 'PAYMENT_ERROR',
                'category' => $category,
                'gateway_message' => $responseMessage
            ],
            'timestamp' => date('c')
        ];
    }

    /**
     * Handle validation errors
     *
     * @param array $errors Array of validation errors
     * @param string $context Validation context
     * @return array Validation error response
     */
    public function handleValidationErrors(array $errors, string $context = 'validation'): array
    {
        $errorId = $this->generateErrorId();

        $this->logger->warning('Validation errors occurred', [
            'error_id' => $errorId,
            'context' => $context,
            'errors' => $errors,
            'ip_address' => $this->getClientIp()
        ], Logger::CHANNEL_SECURITY);

        return [
            'success' => false,
            'error' => [
                'id' => $errorId,
                'code' => 'VALIDATION_FAILED',
                'message' => 'Request validation failed',
                'type' => 'VALIDATION_ERROR',
                'details' => $errors
            ],
            'timestamp' => date('c')
        ];
    }

    /**
     * Handle rate limiting errors
     *
     * @param int $retryAfter Seconds until retry is allowed
     * @return array Rate limit error response
     */
    public function handleRateLimitError(int $retryAfter = 60): array
    {
        $errorId = $this->generateErrorId();

        $this->logger->warning('Rate limit exceeded', [
            'error_id' => $errorId,
            'retry_after' => $retryAfter,
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ], Logger::CHANNEL_SECURITY);

        return [
            'success' => false,
            'error' => [
                'id' => $errorId,
                'code' => 'RATE_LIMIT_EXCEEDED',
                'message' => 'Too many requests. Please try again later.',
                'type' => 'RATE_LIMIT_ERROR',
                'retry_after' => $retryAfter
            ],
            'timestamp' => date('c')
        ];
    }

    /**
     * Send error response with appropriate HTTP status
     *
     * @param array $errorResponse Error response array
     * @param int|null $httpStatus HTTP status code (auto-detected if null)
     * @return void
     */
    public function sendErrorResponse(array $errorResponse, ?int $httpStatus = null): void
    {
        $status = $httpStatus ?? $this->getHttpStatusFromError($errorResponse);

        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($errorResponse);
        exit;
    }

    /**
     * Initialize error code mappings
     *
     * @return void
     */
    private function initializeErrorCodes(): void
    {
        $this->errorCodes = [
            // Payment response codes to user messages
            '00' => 'Transaction approved',
            '05' => 'Transaction declined by your bank',
            '14' => 'Invalid card number',
            '41' => 'Lost card - please contact your bank',
            '43' => 'Stolen card - please contact your bank',
            '51' => 'Insufficient funds',
            '54' => 'Expired card',
            '55' => 'Invalid PIN',
            '57' => 'Transaction not permitted',
            '61' => 'Exceeds withdrawal limit',
            '62' => 'Restricted card',
            '65' => 'Activity limit exceeded',
            '75' => 'Allowable PIN tries exceeded',
            '76' => 'Invalid transaction',
            '77' => 'Reconcile error',
            '78' => 'Trace number not found',
            '96' => 'System malfunction',
            'EB' => 'Partial approval',
            'EC' => 'Invalid authorization code',
        ];
    }

    /**
     * Register global error and exception handlers
     *
     * @return void
     */
    private function registerHandlers(): void
    {
        // Register exception handler
        set_exception_handler([$this, 'globalExceptionHandler']);

        // Register error handler
        set_error_handler([$this, 'globalErrorHandler']);

        // Register shutdown handler for fatal errors
        register_shutdown_function([$this, 'shutdownHandler']);
    }

    /**
     * Global exception handler
     *
     * @param Throwable $exception Uncaught exception
     * @return void
     */
    public function globalExceptionHandler(Throwable $exception): void
    {
        $response = $this->handleException($exception, 'uncaught');
        $this->sendErrorResponse($response, 500);
    }

    /**
     * Global error handler
     *
     * @param int $severity Error severity
     * @param string $message Error message
     * @param string $file File where error occurred
     * @param int $line Line number where error occurred
     * @return bool
     */
    public function globalErrorHandler(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $this->logger->error('PHP Error', [
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line
        ]);

        return true;
    }

    /**
     * Shutdown handler for fatal errors
     *
     * @return void
     */
    public function shutdownHandler(): void
    {
        $error = error_get_last();

        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $this->logger->critical('Fatal Error', [
                'type' => $error['type'],
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line']
            ]);

            if (!headers_sent()) {
                $response = [
                    'success' => false,
                    'error' => [
                        'id' => $this->generateErrorId(),
                        'code' => 'FATAL_ERROR',
                        'message' => 'A fatal error occurred',
                        'type' => 'SYSTEM_ERROR'
                    ]
                ];

                $this->sendErrorResponse($response, 500);
            }
        }
    }

    /**
     * Analyze exception and extract error information
     *
     * @param Throwable $exception Exception to analyze
     * @return array Error information
     */
    private function analyzeException(Throwable $exception): array
    {
        $class = get_class($exception);

        switch (true) {
            case $exception instanceof GatewayException:
                return [
                    'code' => $exception->responseCode ?? 'GATEWAY_ERROR',
                    'message' => $this->sanitizeErrorMessage($exception->getMessage()),
                    'type' => 'GATEWAY_ERROR'
                ];

            case $exception instanceof ApiException:
                return [
                    'code' => 'API_ERROR',
                    'message' => $this->sanitizeErrorMessage($exception->getMessage()),
                    'type' => 'API_ERROR'
                ];

            case $exception instanceof BuilderException:
                return [
                    'code' => 'BUILDER_ERROR',
                    'message' => 'Invalid request configuration',
                    'type' => 'CONFIGURATION_ERROR'
                ];

            case $exception instanceof ConfigurationException:
                return [
                    'code' => 'CONFIG_ERROR',
                    'message' => 'Service configuration error',
                    'type' => 'CONFIGURATION_ERROR'
                ];

            default:
                return [
                    'code' => 'INTERNAL_ERROR',
                    'message' => $this->debugMode ? $exception->getMessage() : 'An internal error occurred',
                    'type' => 'SYSTEM_ERROR'
                ];
        }
    }

    /**
     * Map payment response codes to user-friendly messages
     *
     * @param string $responseCode Gateway response code
     * @param string $responseMessage Gateway response message
     * @return string User-friendly message
     */
    private function mapPaymentErrorMessage(string $responseCode, string $responseMessage): string
    {
        return $this->errorCodes[$responseCode] ?? $this->sanitizeErrorMessage($responseMessage);
    }

    /**
     * Categorize payment errors
     *
     * @param string $responseCode Gateway response code
     * @return string Error category
     */
    private function categorizePaymentError(string $responseCode): string
    {
        $categories = [
            '05' => 'DECLINED',
            '14' => 'INVALID_CARD',
            '41' => 'LOST_CARD',
            '43' => 'STOLEN_CARD',
            '51' => 'INSUFFICIENT_FUNDS',
            '54' => 'EXPIRED_CARD',
            '55' => 'INVALID_PIN',
            '57' => 'NOT_PERMITTED',
            '96' => 'SYSTEM_ERROR'
        ];

        return $categories[$responseCode] ?? 'UNKNOWN';
    }

    /**
     * Sanitize error message for public consumption
     *
     * @param string $message Error message to sanitize
     * @return string Sanitized message
     */
    private function sanitizeErrorMessage(string $message): string
    {
        // Remove sensitive information patterns
        $patterns = [
            '/\b\d{13,19}\b/',           // Card numbers
            '/\bcvv?\s*:?\s*\d{3,4}\b/i', // CVV codes
            '/\btoken[:\s]+[a-zA-Z0-9]+/i', // Tokens
            '/\bapi[_\s]?key[:\s]+[a-zA-Z0-9]+/i', // API keys
        ];

        $message = preg_replace($patterns, '[REDACTED]', $message);

        // Limit message length
        return substr($message, 0, 200);
    }

    /**
     * Sanitize stack trace for logging
     *
     * @param array $trace Stack trace array
     * @return array Sanitized trace
     */
    private function sanitizeStackTrace(array $trace): array
    {
        foreach ($trace as &$frame) {
            if (isset($frame['args'])) {
                unset($frame['args']); // Remove arguments which may contain sensitive data
            }
        }

        return array_slice($trace, 0, 10); // Limit trace depth
    }

    /**
     * Generate unique error ID
     *
     * @return string Unique error identifier
     */
    private function generateErrorId(): string
    {
        return 'ERR_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 8);
    }

    /**
     * Get HTTP status code based on error type
     *
     * @param array $errorResponse Error response array
     * @return int HTTP status code
     */
    private function getHttpStatusFromError(array $errorResponse): int
    {
        $errorType = $errorResponse['error']['type'] ?? 'UNKNOWN';

        return match ($errorType) {
            'VALIDATION_ERROR' => 400,
            'RATE_LIMIT_ERROR' => 429,
            'PAYMENT_ERROR' => 422,
            'CONFIGURATION_ERROR' => 503,
            'GATEWAY_ERROR' => 502,
            'API_ERROR' => 400,
            default => 500
        };
    }

    /**
     * Log error details
     *
     * @param Throwable $exception Exception to log
     * @param string $context Error context
     * @param string $errorId Error ID
     * @return void
     */
    private function logError(Throwable $exception, string $context, string $errorId): void
    {
        $this->logger->error('Exception occurred', [
            'error_id' => $errorId,
            'context' => $context,
            'exception_class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    }

    /**
     * Get client IP address
     *
     * @return string Client IP address
     */
    private function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                return trim($ips[0]);
            }
        }

        return 'unknown';
    }
}
