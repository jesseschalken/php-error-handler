<?php

abstract class PrettyPrinter
{
	/**
	 * @var ValuePrettyPrinter
	 */
	private $prettyPrinter;

	public function __construct( ValuePrettyPrinter $prettyPrinter )
	{
		$this->prettyPrinter = $prettyPrinter;
	}

	/**
	 * @param $value
	 *
	 * @return string[]
	 */
	public abstract function doPrettyPrint( &$value );

	protected final function prettyPrintRefLines( &$value )
	{
		return $this->prettyPrinter->doPrettyPrint( $value );
	}

	protected final function prettyPrintLines( $value )
	{
		return $this->prettyPrinter->doPrettyPrint( $value );
	}

	protected function prettyPrintVariable( $varName )
	{
		return $this->prettyPrinter->prettyPrintVariable( $varName );
	}

	protected final function prettyPrint( $value )
	{
		return join( "\n", $this->prettyPrintLines( $value ) );
	}

	protected static function concatenate( array $lineGroups )
	{
		$result = array();
		$i      = 0;

		foreach ( $lineGroups as $lines )
			foreach ( $lines as $k => $line )
				if ( $k == 0 && $i != 0 )
					$result[$i - 1] .= $line;
				else
					$result[$i++] = $line;

		return $result;
	}

	protected static function groupLines( array $lineGroups )
	{
		$lastWasMultiLine = false;
		$resultLines      = array();

		foreach ( $lineGroups as $k => $lines )
		{
			$isMultiLine = count( self::splitNewLines( $lines ) ) > 1;

			if ( $k !== 0 && ( $lastWasMultiLine || $isMultiLine ) )
				$resultLines[] = '';

			$resultLines      = array_merge( $resultLines, $lines );
			$lastWasMultiLine = $isMultiLine;
		}

		return $resultLines;
	}

	protected static function splitNewLines( array $lines )
	{
		return explode( "\n", join( "\n", $lines ) );
	}

	protected static function addLineNumbers( array $lines )
	{
		foreach ( $lines as $k => &$line )
		{
			$k++;

			$line = $line === "" ? "$k" : substr( "$k    ", 0, 4 ) . " $line";
		}

		return $lines;
	}

	protected static function indentLines( array $lines )
	{
		foreach ( $lines as &$line )
			if ( $line !== "" )
				$line = "    $line";

		return $lines;
	}
}

abstract class CachingPrettyPrinter extends PrettyPrinter
{
	private $cache = array();

	public final function doPrettyPrint( &$value )
	{
		$key = $this->cacheKey( $value );

		if ( !isset( $this->cache[$key] ) )
			$this->cache[$key] = $this->cacheMiss( $value );

		return $this->cache[$key];
	}

	protected abstract function cacheKey( $value );

	protected abstract function cacheMiss( $value );
}

final class StringPrettyPrinter extends CachingPrettyPrinter
{
	private $characterEscapeCache = array(
		"\\" => '\\\\',
		"\$" => '\$',
		"\r" => '\r',
		"\v" => '\v',
		"\f" => '\f',
		"\n" => "\n",
		"\t" => "\t",
	);

	protected function cacheMiss( $string )
	{
		$escaped = '';
		$length  = strlen( $string );

		for ( $i = 0; $i < $length; $i++ )
		{
			$char = $string[$i];

			if ( !isset( $this->characterEscapeCache[$char] ) )
			{
				$ord = ord( $char );

				if ( $ord >= 32 && $ord <= 126 )
					$this->characterEscapeCache[$char] = $char;
				else
					$this->characterEscapeCache[$char] = '\x' . substr( '00' . dechex( $ord ), -2 );
			}

			$escaped .= $this->characterEscapeCache[$char];
		}

		return array( "\"$escaped\"" );
	}

	protected function cacheKey( $value )
	{
		return $value;
	}
}

final class BooleanPrettyPrinter extends PrettyPrinter
{
	public function doPrettyPrint( &$value )
	{
		return array( $value ? 'true' : 'false' );
	}
}

final class IntegerPrettyPrinter extends PrettyPrinter
{
	public function doPrettyPrint( &$int )
	{
		return array( "$int" );
	}
}

final class FloatPrettyPrinter extends CachingPrettyPrinter
{
	protected function cacheMiss( $float )
	{
		$int = (int) $float;

		return array( "$int" === "$float" ? "$float.0" : "$float" );
	}

	protected function cacheKey( $value )
	{
		return (string) $value;
	}
}

final class ResourcePrettyPrinter extends PrettyPrinter
{
	public function doPrettyPrint( &$resource )
	{
		return array( get_resource_type( $resource ) . ' #' . (int) $resource );
	}
}

final class ArrayPrettyPrinter extends PrettyPrinter
{
	private $arrayContext = array();

	public function doPrettyPrint( &$array )
	{
		foreach ( $this->arrayContext as &$c )
			if ( self::refsEqual( $c, $array ) )
				return array( 'array( *recursion* )' );

		/**
		 * In PHP 5.2.4, this class was not able to detect the recursion of the
		 * following structure, resulting in a stack overflow.
		 *
		 *   $a         = new stdClass;
		 *   $a->b      = array();
		 *   $a->b['c'] =& $a->b;
		 *
		 * But PHP 5.3.17 was able. The exact reason I am not sure, but I will enforce
		 * a maximum depth limit for PHP versions older than the earliest for which I
		 * know the recursion detection works.
		 */
		if ( PHP_VERSION_ID < 50317 && count( $this->arrayContext ) > 10 )
			return array( 'array( *maximum depth exceeded* )' );

		$this->arrayContext[] =& $array;

		$result = $this->prettyPrintArrayDeep( $array );

		array_pop( $this->arrayContext );

		return $result;
	}

	private function prettyPrintArrayEntriesLines( array $array )
	{
		$entriesLines = array();

		$isAssociative = self::isArrayAssociative( $array );

		foreach ( $array as $k => &$v )
			$entriesLines[] = self::concatenate( array_merge( $isAssociative ? array(
					                                                                      $this->prettyPrintLines( $k ),
					                                                                      array( ' => ' ),
				                                                                      ) : array(),
			                                                  array( $this->prettyPrintRefLines( $v ) ) ) );

		return $entriesLines;
	}

	private function prettyPrintArrayDeep( array $array )
	{
		$entriesLines = $this->prettyPrintArrayEntriesLines( $array );

		return $this->arrayShouldBePrintedMultiLine( $entriesLines ) ?
			$this->prettyPrintArrayMultiLine( $entriesLines ) : $this->prettyPrintArrayOneLine( $entriesLines );
	}

	private function arrayShouldBePrintedMultiLine( array $entriesLines )
	{
		$totalSize = 0;

		foreach ( $entriesLines as $entryLines )
			if ( count( $entryLines ) > 1 )
				return true;
			else
				$totalSize += strlen( $entryLines[0] );

		return $totalSize > 32;
	}

	private function prettyPrintArrayOneLine( array $entriesLines )
	{
		$entries = array();

		foreach ( $entriesLines as $entryLines )
			$entries[] = $entryLines[0];

		return array( empty( $entries ) ? 'array()' : "array( " . join( ', ', $entries ) . " )" );
	}

	private function prettyPrintArrayMultiLine( array $entriesLines )
	{
		foreach ( $entriesLines as &$entryLines )
			$entryLines = self::concatenate( array( $entryLines, array( ',' ) ) );

		return array_merge( array( 'array(' ),
		                    self::indentLines( self::groupLines( $entriesLines ) ),
		                    array( ')' ) );
	}

	private static function isArrayAssociative( array $array )
	{
		$i = 0;

		foreach ( $array as $k => $v )
			if ( $k !== $i++ )
				return true;

		return false;
	}

	private static function refsEqual( &$a, &$b )
	{
		$aOld   = $a;
		$a      = new stdClass;
		$result = $a === $b;
		$a      = $aOld;

		return $result;
	}
}

final class ObjectPrettyPrinter extends PrettyPrinter
{
	private $objectsAlreadyPrinted = array();

	public function doPrettyPrint( &$object )
	{
		$hash      = spl_object_hash( $object );
		$className = get_class( $object );

		if ( isset( $this->objectsAlreadyPrinted[$hash] ) )
			return array( "new $className $hash {...}" );

		$this->objectsAlreadyPrinted[$hash] = true;

		return $this->prettyPrintObjectLinesDeep( $object, $hash );
	}

	private function prettyPrintObjectLinesDeep( $object, $hash )
	{
		$className        = get_class( $object );
		$objectProperties = (array) $object;

		if ( empty( $objectProperties ) )
			return array( "new $className $hash {}" );

		$propertiesLines = array();

		foreach ( $objectProperties as $k => &$propertyValue )
		{
			$parts        = explode( "\x00", $k );
			$access       = isset( $parts[1] ) ? ( $parts[1] === '*' ? 'protected' : 'private' ) : 'public';
			$propertyName = isset( $parts[2] ) ? $parts[2] : $parts[0];

			$propertiesLines[] = self::concatenate( array(
			                                             array( "$access " ),
			                                             $this->prettyPrintVariable( $propertyName ),
			                                             array( ' = ' ),
			                                             $this->prettyPrintRefLines( $propertyValue ),
			                                             array( ';' ),
			                                        ) );
		}

		return array_merge( array( "new $className $hash {" ),
		                    self::indentLines( self::groupLines( $propertiesLines ) ),
		                    array( '}' ) );
	}
}

final class NullPrettyPrinter extends PrettyPrinter
{
	public function doPrettyPrint( &$null )
	{
		return array( 'null' );
	}
}

final class UnknownPrettyPrinter extends PrettyPrinter
{
	public function doPrettyPrint( &$unknown )
	{
		return array( 'unknown type' );
	}
}

final class ValuePrettyPrinter extends PrettyPrinter
{
	/** @var PrettyPrinter[] */
	private $prettyPrinters = array();
	private $variablePrettyPrinter;

	public function __construct()
	{
		$this->prettyPrinters = array(
			'boolean'      => new BooleanPrettyPrinter( $this ),
			'integer'      => new IntegerPrettyPrinter( $this ),
			'double'       => new FloatPrettyPrinter( $this ),
			'string'       => new StringPrettyPrinter( $this ),
			'array'        => new ArrayPrettyPrinter( $this ),
			'object'       => new ObjectPrettyPrinter( $this ),
			'resource'     => new ResourcePrettyPrinter( $this ),
			'NULL'         => new NullPrettyPrinter( $this ),
			'unknown type' => new UnknownPrettyPrinter( $this ),
		);

		$this->variablePrettyPrinter = new VariablePrettyPrinter( $this );

		parent::__construct( $this );
	}

	public final function doPrettyPrint( &$value )
	{
		$printer = $this->prettyPrinters[gettype( $value )];

		return $printer->doPrettyPrint( $value );
	}

	public final function prettyPrintVariable( $varName )
	{
		return $this->variablePrettyPrinter->doPrettyPrint( $varName );
	}
}

final class VariablePrettyPrinter extends CachingPrettyPrinter
{
	protected function cacheKey( $value )
	{
		return $value;
	}

	protected function cacheMiss( $varName )
	{
		if ( preg_match( "/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/", $varName ) )
			return array( "\$$varName" );
		else
			return self::concatenate( array(
			                               array( '${' ),
			                               $this->prettyPrintLines( $varName ),
			                               array( '}' ),
			                          ) );
	}
}

final class ExceptionPrettyPrinter extends PrettyPrinter
{
	/**
	 * @param Exception $exception
	 *
	 * @return string[]
	 */
	public function doPrettyPrint( &$exception )
	{
		return array_merge( self::concatenate( array(
		                                            array( 'uncaught ' ),
		                                            $this->prettyPrintExceptionBrief( $exception ),
		                                       ) ),
		                    array(
		                         "",
		                         "global variables:",
		                    ),
		                    self::indentLines( $this->prettyPrintVariables( self::globals() ) ) );
	}

	private static function globals()
	{
		/**
		 * Don't ask me why, but if I don't send $GLOBALS through array_merge(), unset( $globals['GLOBALS'] ) (next
		 * line) ends up removing the $GLOBALS superglobal itself.
		 */
		$globals = array_merge( $GLOBALS );
		unset( $globals['GLOBALS'] );

		return $globals;
	}

	public static function prettyPrintExceptionOneLine( Exception $e )
	{
		$self = new self( new ValuePrettyPrinter );

		return join( ' ',
		             array(
		                  "uncaught " . get_class( $e ) . ",",
		                  "code " . $e->getCode() . ",",
		                  "message " . $self->prettyPrint( $e->getMessage() ),
		                  "in file " . $self->prettyPrint( $e->getFile() ) . ",",
		                  "line " . $self->prettyPrint( $e->getLine() ) . "",
		             ) );
	}

	public static function prettyPrintException( Exception $e )
	{
		$self = new self( new ValuePrettyPrinter );

		return join( "\n", $self->doPrettyPrint( $e ) ) . "\n";
	}

	private function prettyPrintExceptionBrief( Exception $e )
	{
		return array_merge( array( get_class( $e ) ),
		                    self::indentLines( array(
		                                            "code    " . $e->getCode(),
		                                            "message " . $this->prettyPrint( $e->getMessage() ),
		                                            "file    " . $this->prettyPrint( $e->getFile() ),
		                                            "line    " . $this->prettyPrint( $e->getLine() ),
		                                       ) ),
		                    array(
		                         "",
		                         "local variables:",
		                    ),
		                    self::indentLines(
			                    $e instanceof ExceptionWithLocalVariables && $e->getLocalVariables() !== null ?
				                    $this->prettyPrintVariables( $e->getLocalVariables() ) : array( "unavailable" ) ),
		                    array(
		                         "",
		                         "stack trace:",
		                    ),
		                    self::indentLines( $this->prettyPrintStackTrace(
			                                       $e instanceof ExceptionWithFullStackTrace ? $e->getFullStackTrace() :
				                                       $e->getTrace() ) ),
		                    array(
		                         "",
		                         "previous exception:",
		                    ),
		                    self::indentLines( PHP_VERSION_ID > 50300 && $e->getPrevious() !== null ?
			                                       $this->prettyPrintExceptionBrief( $e->getPrevious() ) :
			                                       array( 'none' ) ) );
	}

	private function prettyPrintStackTrace( array $stackTrace )
	{
		$stackFrameLines = array();

		foreach ( $stackTrace as $stackFrame )
			$stackFrameLines[] = array_merge( array(
			                                       "- file " . $this->prettyPrint( @$stackFrame['file'] ) . ", line " .
			                                       $this->prettyPrint( @$stackFrame['line'] ),
			                                  ),
			                                  self::indentLines( self::addLineNumbers( self::splitNewLines( $this->prettyPrintFunctionCall( $stackFrame ) ) ) ) );

		$stackFrameLines[] = array( '- {main}' );

		return self::groupLines( $stackFrameLines );
	}

	private function prettyPrintVariables( array $vars )
	{
		if ( empty( $vars ) )
			return array( 'none' );

		$varLines = array();

		foreach ( $vars as $k => &$v )
			$varLines[] = self::concatenate( array(
			                                      $this->prettyPrintVariable( $k ),
			                                      array( ' = ' ),
			                                      $this->prettyPrintRefLines( $v ),
			                                      array( ';' ),
			                                 ) );

		return self::addLineNumbers( self::splitNewLines( self::groupLines( $varLines ) ) );
	}

	private function prettyPrintFunctionCall( array $stackFrame )
	{
		if ( isset( $stackFrame['object'] ) )
			$object = $this->prettyPrintLines( $stackFrame['object'] );
		else if ( isset( $stackFrame['class'] ) )
			$object = array( $stackFrame['class'] );
		else
			$object = array();

		if ( isset( $stackFrame['args'] ) )
			$args = $this->prettyPrintFunctionArgs( $stackFrame['args'] );
		else
			$args = array( '( ? )' );

		return self::concatenate( array(
		                               $object,
		                               array( @$stackFrame['type'] . @$stackFrame['function'] ),
		                               $args,
		                               array( ';' ),
		                          ) );
	}

	private function prettyPrintFunctionArgs( array $args )
	{
		if ( empty( $args ) )
			return array( '()' );

		$lineGroups[] = array( '( ' );

		foreach ( $args as $k => &$arg )
		{
			if ( $k !== 0 )
				$lineGroups[] = array( ', ' );

			$lineGroups[] = $this->prettyPrintRefLines( $arg );
		}

		$lineGroups[] = array( ' )' );

		return self::concatenate( $lineGroups );
	}
}