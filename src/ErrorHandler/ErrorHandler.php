<?php

namespace ErrorHandler;

use PrettyPrinter\PrettyPrinter;

class ErrorHandler
{
	static function create()
	{
		return new self;
	}

	protected static function out( $title, $body )
	{
		while ( ob_get_level() > 0 && ob_end_clean() )
			;

		if ( PHP_SAPI === 'cli' )
		{
			fwrite( STDERR, $body );
		}
		else
		{
			if ( !headers_sent() )
			{
				header( 'HTTP/1.1 500 Internal Server Error', true, 500 );
				header( "Content-Type: text/html; charset=UTF-8", true );
			}

			print self::wrapHtml( $title, $body );
		}
	}

	protected static function wrapHtml( $title, $body )
	{
		$body  = self::toHtml( $body );
		$title = self::toHtml( $title );

		return <<<html
<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8" />
		<title>$title</title>
	</head>
	<body>
		<pre style="
			white-space: pre;
			font-family: 'DejaVu Sans Mono', 'Consolas', 'Menlo', monospace;
			font-size: 10pt;
			color: #000000;
			display: block;
			background: white;
			border: none;
			margin: 0;
			padding: 0;
			line-height: 16px;
			width: 100%;
		">$body</pre>
	</body>
</html>
html;
	}

	private static function fullStackTrace()
	{
		return array_slice( debug_backtrace(), 2 );
	}

	private static function toHtml( $text )
	{
		return htmlspecialchars( $text, ENT_COMPAT, "UTF-8" );
	}

	private $lastError;

	protected function __construct()
	{
	}

	final function bind()
	{
		ini_set( 'display_errors', false );
		ini_set( 'log_errors', false );
		ini_set( 'html_errors', false );

		assert_options( ASSERT_ACTIVE, true );
		assert_options( ASSERT_WARNING, true );
		assert_options( ASSERT_BAIL, false );
		assert_options( ASSERT_QUIET_EVAL, false );
		assert_options( ASSERT_CALLBACK, array( $this, 'handleFailedAssertion' ) );

		set_error_handler( array( $this, 'handleError' ) );
		set_exception_handler( array( $this, 'handleUncaughtException' ) );
		register_shutdown_function( array( $this, 'handleShutdown' ) );

		$this->lastError = error_get_last();
	}

	final function handleFailedAssertion( $file, $line, $expression, $message = 'Assertion failed' )
	{
		throw new AssertionFailedException( $file, $line, $expression, $message, self::fullStackTrace() );
	}

	final function handleError( $severity, $message, $file = null, $line = null, $localVariables = null )
	{
		if ( error_reporting() & $severity )
		{
			$e = new ErrorException( $severity, $message, $file, $line, $localVariables, self::fullStackTrace() );

			if ( $severity & ( E_USER_ERROR | E_USER_WARNING | E_USER_NOTICE | E_USER_DEPRECATED ) )
				throw $e;

			$this->handleUncaughtException( $e );
		}

		$this->lastError = error_get_last();

		return true;
	}

	final function handleUncaughtException( \Exception $e )
	{
		$this->handleException( $e );

		$this->lastError = error_get_last();
		exit( 1 );
	}

	final function handleShutdown()
	{
		ini_set( 'memory_limit', '-1' );

		$error = error_get_last();

		if ( $error !== null && $error !== $this->lastError )
		{
			$this->handleUncaughtException( new ErrorException( $error[ 'type' ], $error[ 'message' ],
			                                                    $error[ 'file' ], $error[ 'line' ], null,
			                                                    self::fullStackTrace() ) );
		}
	}

	protected function handleException( \Exception $e )
	{
		$settings = new PrettyPrinter;

		self::out( 'error', $settings->prettyPrintException( $e ) );
	}
}
