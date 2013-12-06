<?php

namespace PrettyPrinter
{
    use PrettyPrinter\Introspection\Introspection;
    use PrettyPrinter\Test\DummyClass2;
    use PrettyPrinter\Test\MockException;
    use PrettyPrinter\Values\ValuePool;

    class PrettyPrinterTest extends \PHPUnit_Framework_TestCase
    {
        function testClosure()
        {
            self::pp()->assertPrettyIs( function () { }, <<<'s'
new Closure #1 {
}
s
            );
        }

        private static function pp()
        {
            return new PrettyPrinter;
        }

        function testComplexObject()
        {
            $this->markTestIncomplete();

            self::pp()->setMaxArrayEntries( 10 )->assertPrettyIs( new Introspection( new ValuePool ), <<<'s'
new PrettyPrinter\TypeHandlers\Any #1 {
    private $typeHandlers    = array( "boolean"      => new PrettyPrinter\TypeHandlers\Boolean #3 {
                                                            private $anyHandler = new PrettyPrinter\TypeHandlers\Any #1 {...};
                                                        },
                                      "integer"      => new PrettyPrinter\TypeHandlers\Integer #4 {
                                                            private $anyHandler = new PrettyPrinter\TypeHandlers\Any #1 {...};
                                                        },
                                      "double"       => new PrettyPrinter\TypeHandlers\Float #5 {
                                                            private $cache      = array();
                                                            private $anyHandler = new PrettyPrinter\TypeHandlers\Any #1 {...};
                                                        },
                                      "string"       => new PrettyPrinter\TypeHandlers\String #7 {
                                                            private $characterEscapeCache = array( "\\" => "\\\\",
                                                                                                   "\$" => "\\\$",
                                                                                                   "\r" => "\\r",
                                                                                                   "\v" => "\\v",
                                                                                                   "\f" => "\\f",
                                                                                                   "\"" => "\\\"",
                                                                                                   "	"  => "	",
                                                                                                   "\n" .
                                                                                                   ""   => "\\n\" .\n" .
                                                                                                           "\"" );
                                                            private $cache                = array();
                                                            private $anyHandler           = new PrettyPrinter\TypeHandlers\Any #1 {...};
                                                        },
                                      "array"        => new PrettyPrinter\TypeHandlers\Array1 #10 {
                                                            private $arrayStack         = array();
                                                            private $arrayIdsReferenced = array();
                                                            private $anyHandler         = new PrettyPrinter\TypeHandlers\Any #1 {...};
                                                        },
                                      "object"       => new PrettyPrinter\TypeHandlers\Object #13 {
                                                            private $objectIds  = array();
                                                            private $anyHandler = new PrettyPrinter\TypeHandlers\Any #1 {...};
                                                        },
                                      "resource"     => new PrettyPrinter\TypeHandlers\Resource #15 {
                                                            private $resourceIds = array();
                                                            private $cache       = array();
                                                            private $anyHandler  = new PrettyPrinter\TypeHandlers\Any #1 {...};
                                                        },
                                      "NULL"         => new PrettyPrinter\TypeHandlers\Null #18 {
                                                            private $anyHandler = new PrettyPrinter\TypeHandlers\Any #1 {...};
                                                        },
                                      "unknown type" => new PrettyPrinter\TypeHandlers\Unknown #19 {
                                                            private $anyHandler = new PrettyPrinter\TypeHandlers\Any #1 {...};
                                                        } );
    private $variableHandler = new PrettyPrinter\TypeHandlers\Variable #20 {
                                   private $cache      = array();
                                   private $anyHandler = new PrettyPrinter\TypeHandlers\Any #1 {...};
                               };
    private $nextId          = 1;
    private $settings        = new PrettyPrinter\PrettyPrinter #22 {
                                   private $escapeTabsInStrings          = new PrettyPrinter\Settings\Bool #23 {
                                                                               private $value = false;
                                                                               private $pp    = new PrettyPrinter\PrettyPrinter #22 {...};
                                                                           };
                                   private $splitMultiLineStrings        = new PrettyPrinter\Settings\Bool #24 {
                                                                               private $value = true;
                                                                               private $pp    = new PrettyPrinter\PrettyPrinter #22 {...};
                                                                           };
                                   private $maxObjectProperties          = new PrettyPrinter\Settings\Number #25 {
                                                                               private $value = 9223372036854775807;
                                                                               private $pp    = new PrettyPrinter\PrettyPrinter #22 {...};
                                                                           };
                                   private $maxArrayEntries              = new PrettyPrinter\Settings\Number #26 {
                                                                               private $value = 9223372036854775807;
                                                                               private $pp    = new PrettyPrinter\PrettyPrinter #22 {...};
                                                                           };
                                   private $maxStringLength              = new PrettyPrinter\Settings\Number #27 {
                                                                               private $value = 9223372036854775807;
                                                                               private $pp    = new PrettyPrinter\PrettyPrinter #22 {...};
                                                                           };
                                   private $showExceptionLocalVariables  = new PrettyPrinter\Settings\Bool #28 {
                                                                               private $value = true;
                                                                               private $pp    = new PrettyPrinter\PrettyPrinter #22 {...};
                                                                           };
                                   private $showExceptionGlobalVariables = new PrettyPrinter\Settings\Bool #29 {
                                                                               private $value = true;
                                                                               private $pp    = new PrettyPrinter\PrettyPrinter #22 {...};
                                                                           };
                                   private $showExceptionStackTrace      = new PrettyPrinter\Settings\Bool #30 {
                                                                               private $value = true;
                                                                               private $pp    = new PrettyPrinter\PrettyPrinter #22 {...};
                                                                           };
                               };
    private $anyHandler      = new PrettyPrinter\TypeHandlers\Any #1 {...};
}
s
            );
        }

        function testException()
        {
            $exception = new MockException;

            self::assertEquals( $exception->render( self::pp() )->__toString(), <<<'s'
MuhMockException Dummy exception code in /the/path/to/muh/file:9000

    This is a dummy exception message.

    lololool

local variables:
  $lol = 8;
  $foo = "bar";

stack trace:
  #1 /path/to/muh/file:1928
        new PrettyPrinter\Test\DummyClass1 #1 {
            public $public1       = null;
            private $private1     = null;
            protected $protected1 = null;
        }->aFunction( new PrettyPrinter\Test\DummyClass2 #2 {
                          public $public2       = null;
                          private $private2     = null;
                          protected $protected2 = null;
                          public $public1       = null;
                          private $private1     = null;
                          protected $protected1 = null;
                      } );

  #2 {main}

global variables:
  private static BlahClass::$blahProperty                       = null;
  function BlahAnotherClass()::static $public                   = null;
  global ${"lol global"}                                        = null;
  function BlahYetAnotherClass::blahMethod()::static $lolStatic = null;
  global $blahVariable                                          = null;


s
            );
        }

        function testMaxArrayEntries()
        {
            self::pp()->setMaxArrayEntries( 3 )
                ->assertPrettyIs( range( 1, 10 ), <<<'s'
array( 1,
       2,
       3,
       ... )
s
                    )
                ->assertPrettyIs( array( "blarg" => "foo",
                                         "bar"   => "bar" ),
                    <<<'s'
array( "blarg" => "foo",
       "bar"   => "bar" )
s
                    )
                ->assertPrettyIs( array( "blarg"    => "foo",
                                         "bar"      => "bar",
                                         "bawreara" => "wrjenrg",
                                         "awfjnrg"  => "awrrg" ),
                    <<<'s'
array( "blarg"    => "foo",
       "bar"      => "bar",
       "bawreara" => "wrjenrg",
       ... )
s
                    );
        }

        function testMaxObjectProperties()
        {
            self::pp()->setMaxObjectProperties( 5 )->assertPrettyIs( new DummyClass2, <<<'s'
new PrettyPrinter\Test\DummyClass2 #1 {
    public $public2       = null;
    private $private2     = null;
    protected $protected2 = null;
    public $public1       = null;
    private $private1     = null;
    ...
}
s
            );
        }

        function testMaxStringLength()
        {
            self::pp()->setMaxStringLength( 10 )
                ->assertPrettyIs( "wafkjawejf bawjehfb awjhefb j,awhebf ", '"wafkjawejf...' );
        }

        function testMultiLineString()
        {
            self::pp()->assertPrettyIs( <<<'s'
 weaf waef 8we 7f8tweyufgij2k3e wef f
sdf wf wef
    wef




b
s
                ,
                <<<'s'
" weaf waef 8we 7f8tweyufgij2k3e wef f\n" .
"sdf wf wef\n" .
"    wef\n" .
"\n" .
"\n" .
"\n" .
"\n" .
"b"
s
            );
        }

        function testObjectArrayRecursion()
        {
            $this->markTestIncomplete();
            $object      = new \stdClass;
            $array       = array( $object );
            $object->foo =& $array;

            self::pp()->assertPrettyRefIs( $array, <<<'s'
array( new stdClass #2 {
           public $foo = array( new stdClass #2 {...} );
       } )
s
            );
        }

        function testObjectProperties()
        {
            self::pp()->assertPrettyIs( new DummyClass2, <<<'s'
new PrettyPrinter\Test\DummyClass2 #1 {
    public $public2       = null;
    private $private2     = null;
    protected $protected2 = null;
    public $public1       = null;
    private $private1     = null;
    protected $protected1 = null;
}
s
            );
        }

        function testRecursiveArray()
        {
            $this->markTestIncomplete();
            $recursiveArray              = array();
            $recursiveArray[ 'recurse' ] =& $recursiveArray;

            self::pp()->assertPrettyRefIs( $recursiveArray,
                <<<'s'
#1 array( "recurse" => #1 array(...) )
s
            );
        }

        function testSimpleValues()
        {
            $pp = self::pp();
            $pp->assertPrettyIs( null, "null" );
            $pp->assertPrettyIs( false, "false" );
            $pp->assertPrettyIs( true, "true" );
            $pp->assertPrettyIs( INF, "INF" );
            $pp->assertPrettyIs( -INF, "-INF" );
            $pp->assertPrettyIs( (float) 0, "0.0" );
            $pp->assertPrettyIs( 0, "0" );
            $pp->assertPrettyIs( 0.0, "0.0" );
            $pp->assertPrettyIs( 1, "1" );
            $pp->assertPrettyIs( -1.99, "-1.99" );
            $pp->assertPrettyIs( "lol", '"lol"' );
            $pp->assertPrettyIs( array(), "array()" );
            $pp->assertPrettyIs( array( "foo" ), 'array( "foo" )' );
            $pp->assertPrettyIs( array( "foo", "foo" ),
                <<<'s'
array( "foo",
       "foo" )
s
            );
        }

        function testStdClass()
        {
            $object      = new \stdClass;
            $object->foo = 'bar';

            self::pp()->assertPrettyIs( $object, <<<'s'
new stdClass #1 {
    public $foo = "bar";
}
s
            );
        }
    }
}

namespace PrettyPrinter\Test
{
    use PrettyPrinter\Introspection\Introspection;
    use PrettyPrinter\Values;
    use PrettyPrinter\Values\ValueExceptionGlobalState;

    class DummyClass1
    {
        private static /** @noinspection PhpUnusedPrivateFieldInspection */
                $privateStatic1;
        protected static $protectedStatic1;
        public static $publicStatic1;
        private /** @noinspection PhpUnusedPrivateFieldInspection */
                $private1;
        protected $protected1;
        public $public1;
    }

    class DummyClass2 extends DummyClass1
    {
        private static /** @noinspection PhpUnusedPrivateFieldInspection */
                $privateStatic2;
        protected static $protectedStatic2;
        public static $publicStatic2;
        private /** @noinspection PhpUnusedPrivateFieldInspection */
                $private2;
        protected $protected2;
        public $public2;
    }

    class MockException extends Values\ValueException
    {
        function __construct()
        {
            $any = new Introspection( new Values\ValuePool );

            $class    = 'MuhMockException';
            $message  = <<<'s'
This is a dummy exception message.

lololool
s;
            $code     = 'Dummy exception code';
            $file     = '/the/path/to/muh/file';
            $line = 9000;
            $previous = null;
            $locals = array(
                'lol' => $any->wrap( 8 )->introspect(),
                'foo' => $any->wrap( 'bar' )->introspect(),
            );
            $stack = array(
                new Values\ValueExceptionStackFrame( '->',
                                                     'aFunction',
                                                     $any->wrap( new DummyClass1 )->introspect(),
                                                     array( $any->wrap( new DummyClass2 )->introspect() ),
                                                     '/path/to/muh/file',
                                                     1928 ),
                new Values\ValueExceptionStackFrame( '->',
                                                     'aFunction',
                                                     $any->wrapDummyObject( 'objectWithoutProperties' )->introspect(),
                                                     array( $any->wrap( new DummyClass2 )->introspect() ),
                                                     '/path/to/muh/file',
                                                     1928 )
            );

            $null    = $any->wrap( null )->introspect();
            $globals = array(
                new ValueExceptionGlobalState( 'BlahClass', null, 'blahProperty', $null, 'private' ),
                new ValueExceptionGlobalState( null, 'BlahAnotherClass', 'public', $null, null ),
                new ValueExceptionGlobalState( null, null, 'lol global', $null, null ),
                new ValueExceptionGlobalState( 'BlahYetAnotherClass', 'blahMethod', 'lolStatic', $null, null ),
                new ValueExceptionGlobalState( null, null, 'blahVariable', $null, null ),
            );

            parent::__construct( $class, $file, $line, $stack, $globals, $locals, $code, $message, $previous );
        }
    }
}


