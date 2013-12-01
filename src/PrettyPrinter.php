<?php

namespace PrettyPrinter
{
	use PrettyPrinter\Settings\Bool;
	use PrettyPrinter\Settings\Number;
	use PrettyPrinter\Types\ReflectedException;

	final class PrettyPrinter
	{
		static function create() { return new self; }

		private $escapeTabsInStrings;
		private $splitMultiLineStrings;
		private $maxObjectProperties;
		private $maxArrayEntries;
		private $maxStringLength;
		private $showExceptionLocalVariables;
		private $showExceptionGlobalVariables;
		private $showExceptionStackTrace;

		function __construct()
		{
			$this->escapeTabsInStrings          = new Bool( $this, false );
			$this->splitMultiLineStrings        = new Bool( $this, true );
			$this->maxObjectProperties          = new Number( $this, PHP_INT_MAX );
			$this->maxArrayEntries              = new Number( $this, PHP_INT_MAX );
			$this->maxStringLength              = new Number( $this, PHP_INT_MAX );
			$this->showExceptionLocalVariables  = new Bool( $this, true );
			$this->showExceptionGlobalVariables = new Bool( $this, true );
			$this->showExceptionStackTrace      = new Bool( $this, true );
		}

		function escapeTabsInStrings() { return $this->escapeTabsInStrings; }

		function splitMultiLineStrings() { return $this->splitMultiLineStrings; }

		function maxObjectProperties() { return $this->maxObjectProperties; }

		function maxArrayEntries() { return $this->maxArrayEntries; }

		function maxStringLength() { return $this->maxStringLength; }

		function showExceptionLocalVariables() { return $this->showExceptionLocalVariables; }

		function showExceptionGlobalVariables() { return $this->showExceptionGlobalVariables; }

		function showExceptionStackTrace() { return $this->showExceptionStackTrace ; }

		function prettyPrint( $value )
		{
			return $this->prettyPrintRef( $value );
		}

		function prettyPrintRef( &$ref )
		{
			$memory = new Memory;

			return $memory->prettyPrintRef( $ref, $this )->setHasEndingNewline( false )->__toString();
		}

		function prettyPrintExceptionInfo( ReflectedException $e )
		{
			return $e->render( $this )->__toString();
		}

		function prettyPrintException( \Exception $e )
		{
			return $this->prettyPrintExceptionInfo( ReflectedException::reflect( new Memory, $e ) );
		}

		function assertPrettyIs( $value, $expectedPretty )
		{
			\PHPUnit_Framework_TestCase::assertEquals( $expectedPretty, $this->prettyPrint( $value ) );

			return $this;
		}

		function assertPrettyRefIs( &$ref, $expectedPretty )
		{
			\PHPUnit_Framework_TestCase::assertEquals( $expectedPretty, $this->prettyPrintRef( $ref ) );

			return $this;
		}
	}
}