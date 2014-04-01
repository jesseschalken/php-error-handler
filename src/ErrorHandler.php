<?php

namespace ErrorHandler;

class ErrorHandler {
    /**
     * @param callable      $handler
     * @param callable|null $ignoredErrorHandler
     */
    static function register($handler, $ignoredErrorHandler = null) {
        if (PHP_MAJOR_VERSION == 5)
            if (PHP_MINOR_VERSION == 3)
                $phpBug61767Fixed = PHP_RELEASE_VERSION >= 18;
            else if (PHP_MINOR_VERSION == 4)
                $phpBug61767Fixed = PHP_RELEASE_VERSION >= 8;
            else
                $phpBug61767Fixed = PHP_MINOR_VERSION > 4;
        else
            $phpBug61767Fixed = PHP_MAJOR_VERSION > 5;

        $lastError = error_get_last();

        set_error_handler($errorHandler = function (
            $severity, $message, $file = null, $line = null, $localVars = null
        ) use (
            &$lastError, $handler, $phpBug61767Fixed, $ignoredErrorHandler
        ) {
            $lastError         = error_get_last();
            $isNotIgnored      = error_reporting() & $severity;
            $hasIgnoredHandler = is_callable($ignoredErrorHandler);

            if ($isNotIgnored || $hasIgnoredHandler) {
                $e = new ErrorException($severity, $message, $file, $line, $localVars,
                                        array_slice(debug_backtrace(), 1));

                if ($isNotIgnored)
                    if ($phpBug61767Fixed)
                        throw $e;
                    else if ($severity & (E_USER_ERROR | E_USER_WARNING | E_USER_NOTICE | E_USER_DEPRECATED))
                        throw $e;
                    else
                        call_user_func($handler, $e);
                else if ($hasIgnoredHandler)
                    call_user_func($ignoredErrorHandler, $e);
            }

            return true;
        });

        register_shutdown_function(function () use (&$lastError, $errorHandler, $handler) {
            $e = error_get_last();

            if ($e === null || $e === $lastError)
                return;

            $x = set_error_handler($errorHandler);
            restore_error_handler();

            if ($x !== $errorHandler)
                return;

            ini_set('memory_limit', '-1');

            call_user_func($handler, new ErrorException(
                $e['type'],
                $e['message'],
                $e['file'],
                $e['line'],
                null,
                array_slice(debug_backtrace(), 1)
            ));
        });

        set_exception_handler($handler);

        assert_options(ASSERT_CALLBACK, function ($file, $line, $expression, $message = 'Assertion failed') {
            throw new AssertionFailedException($file, $line, $expression, $message, array_slice(debug_backtrace(), 1));
        });
    }

    static function simpleHandler() {
        return function (\Exception $e) {
            $limits                       = new Limiter;
            $limits->maxArrayEntries      = 3;
            $limits->maxFunctionArguments = 1;
            $limits->maxObjectProperties  = 5;
            $limits->maxStackFrames       = 2;
            $limits->maxLocalVariables    = 2;
            $limits->maxGlobalVariables   = 2;
            $limits->maxStringLength      = 20;
            $limits->maxStaticProperties  = 0;

            $e = Value::introspectException($e, $limits);

            while (ob_get_level() > 0 && ob_end_clean()) ;

            if (PHP_SAPI === 'cli') {
                $settings                      = new PrettyPrinter;
                $settings->maxStringLength     = 100;
                $settings->maxArrayEntries     = 10;
                $settings->maxObjectProperties = 10;

                fwrite(STDERR, $e->toString($settings));
            } else {
                if (!headers_sent()) {
                    header('HTTP/1.1 500 Internal Server Error', true, 500);
                    header("Content-Type: text/html; charset=UTF-8", true);
                }

                echo $e->toHTML();
            }
        };
    }
}

interface ExceptionHasFullTrace {
    /**
     * @return array
     */
    function getFullTrace();
}

interface ExceptionHasLocalVariables {
    /**
     * @return array|null
     */
    function getLocalVariables();
}

class AssertionFailedException extends \LogicException implements ExceptionHasFullTrace {
    private $expression, $fullStackTrace;

    /**
     * @param string $file
     * @param int    $line
     * @param string $expression
     * @param string $message
     * @param array  $fullStackTrace
     */
    function __construct($file, $line, $expression, $message, array $fullStackTrace) {
        parent::__construct($message);

        $this->file           = $file;
        $this->line           = $line;
        $this->expression     = $expression;
        $this->fullStackTrace = $fullStackTrace;
    }

    function getExpression() { return $this->expression; }

    function getFullTrace() { return $this->fullStackTrace; }
}

class ErrorException extends \ErrorException implements ExceptionHasFullTrace, ExceptionHasLocalVariables {
    private $localVariables, $stackTrace;

    function __construct($severity, $message, $file, $line, array $localVariables = null, array $stackTrace) {
        parent::__construct($message, 0, $severity, $file, $line);

        $constants = array(
            E_ERROR             => 'E_ERROR',
            E_WARNING           => 'E_WARNING',
            E_PARSE             => 'E_PARSE',
            E_NOTICE            => 'E_NOTICE',
            E_CORE_ERROR        => 'E_CORE_ERROR',
            E_CORE_WARNING      => 'E_CORE_WARNING',
            E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
            E_USER_ERROR        => 'E_USER_ERROR',
            E_USER_WARNING      => 'E_USER_WARNING',
            E_USER_NOTICE       => 'E_USER_NOTICE',
            E_STRICT            => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED        => 'E_DEPRECATED',
            E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
        );

        $this->localVariables = $localVariables;
        $this->stackTrace     = $stackTrace;
        $this->code           = isset($constants[$severity]) ? $constants[$severity] : 'E_?';
    }

    function getFullTrace() { return $this->stackTrace; }

    function getLocalVariables() { return $this->localVariables; }
}

/**
 * Same as \Exception except it includes a full stack trace
 *
 * @package ErrorHandler
 */
class Exception extends \Exception implements ExceptionHasFullTrace {
    private $stackTrace;

    function __construct($message = "", $code = 0, \Exception $previous = null) {
        $trace = debug_backtrace();

        for ($i = 0; isset($trace[$i]['object']) && $trace[$i]['object'] === $this; $i++) ;

        $this->stackTrace = array_slice($trace, $i);

        parent::__construct($message, $code, $previous);
    }

    function getFullTrace() { return $this->stackTrace; }
}
