<?php

namespace ErrorHandler;

class Introspection {
    function introspect($x) { return self::introspectRef($x); }

    function introspectRef(&$x) {
        return new IntrospectionValue($x, $this);
    }

    function introspectException(\Exception $e) {
        return new IntrospectionException($this, $e);
    }

    function mockException() { return MutableValueException::mock($this); }

    /** @var object[] Just to keep a reference to the objects, because if they get GC'd their hash can get re-used */
    private $objects = array();
    private $arrayIDs = array();
    private $objectIDs = array();

    function arrayID(array &$array) {
        foreach ($this->arrayIDs as $id => &$array2) {
            if (ref_equal($array2, $array)) {
                return $id;
            }
        }

        $id = count($this->arrayIDs);

        $this->arrayIDs[$id] =& $array;

        return $id;
    }

    function objectID($object) {
        $id =& $this->objectIDs[spl_object_hash($object)];
        if ($id === null) {
            $id = count($this->objectIDs) - 1;

            $this->objects[] = $object;
        }

        return $id;
    }

    function introspectResource($x) {
        return new ValueResource(get_resource_type($x), (int)$x);
    }

    private function introspectSourceCode($file, $line) {
        if ($file === null)
            return null;

        $contents = @file_get_contents($file);

        if (!is_string($contents))
            return null;

        $lines   = explode("\n", $contents);
        $results = array();

        foreach (range($line - 5, $line + 5) as $lineToScan) {
            if (isset($lines[$lineToScan])) {
                $results[$lineToScan] = $lines[$lineToScan - 1];
            }
        }

        return $results;
    }

    function introspectCodeLocation($file, $line) {
        if (is_scalar($file) && is_scalar($line)) {
            $result = new ValueExceptionCodeLocation;
            $result->setFile("$file");
            $result->setLine((int)$line);
            $result->setSourceCode($this->introspectSourceCode($file, $line));

            return $result;
        } else {
            return null;
        }
    }
}

class IntrospectionObject implements ValueObject {
    private $introspection;
    private $object;

    function __construct(Introspection $introspection, $object) {
        $this->introspection = $introspection;
        $this->object        = $object;
    }

    function className() { return get_class($this->object); }

    function properties() {
        return IntrospectionObjectProperty::objectProperties($this->introspection, $this->object);
    }

    function getHash() { return spl_object_hash($this->object); }

    function id() { return $this->introspection->objectID($this->object); }
}

class IntrospectionArray implements ValueArray {
    private $introspection;
    private $array;

    function __construct(Introspection $introspection, array &$array) {
        $this->introspection = $introspection;
        $this->array         =& $array;
    }

    function isAssociative() { return array_is_associative($this->array); }

    function id() { return $this->introspection->arrayID($this->array); }

    function entries() { return IntrospectionArrayKeyValuePair::introspect($this->introspection, $this->array); }
}

class IntrospectionArrayKeyValuePair implements ValueArrayEntry {
    static function introspect(Introspection $introspection, array &$array) {
        $entries = array();

        foreach ($array as $key => &$value) {
            $entry        = new self;
            $entry->key   = $introspection->introspect($key);
            $entry->value = $introspection->introspectRef($value);
            $entries[]    = $entry;
        }

        return $entries;
    }

    private $key;
    private $value;

    private function __construct() { }

    function key() { return $this->key; }

    function value() { return $this->value; }
}

class IntrospectionException implements ValueException, Value {
    private $introspection;
    private $exception;
    private $includeGlobals;

    function __construct(Introspection $introspection, \Exception $exception, $includeGlobals = true) {
        $this->introspection  = $introspection;
        $this->exception      = $exception;
        $this->includeGlobals = $includeGlobals;
    }

    function className() { return get_class($this->exception); }

    function code() {
        $code = $this->exception->getCode();

        return is_scalar($code) ? "$code" : '';
    }

    function message() {
        $message = $this->exception->getMessage();

        return is_scalar($message) ? "$message" : '';
    }

    function previous() {
        $previous = $this->exception->getPrevious();

        return $previous instanceof \Exception ? new self($this->introspection, $previous, false) : null;
    }

    function location() {
        $file = $this->exception->getFile();
        $line = $this->exception->getLine();

        return $this->introspection->introspectCodeLocation($file, $line);
    }

    function globals() {
        if (!$this->includeGlobals)
            return null;

        return new IntrospectionGlobals($this->introspection);
    }

    function locals() {
        $locals = $this->exception instanceof ExceptionHasLocalVariables ? $this->exception->getLocalVariables() : null;

        return is_array($locals) ? IntrospectionVariable::introspect($this->introspection, $locals) : null;
    }

    function stack() {
        $frames = $this->exception instanceof ExceptionHasFullTrace
            ? $this->exception->getFullTrace()
            : $this->exception->getTrace();

        if (!is_array($frames))
            return array();

        $result = array();

        foreach ($frames as $frame) {
            $frame    = is_array($frame) ? $frame : array();
            $result[] = new IntrospectionStackFrame($this->introspection, $frame);
        }

        return $result;
    }

    function acceptVisitor(ValueVisitor $visitor) {
        return $visitor->visitException($this);
    }
}

class IntrospectionGlobals implements ValueExceptionGlobalState {
    private $introspection;

    function __construct(Introspection $introspection) {
        $this->introspection = $introspection;
    }

    function getStaticProperties() { return IntrospectionObjectProperty::staticProperties($this->introspection); }

    function getStaticVariables() { return IntrospectionStaticVariable::all($this->introspection); }

    function getGlobalVariables() { return IntrospectionVariable::introspect($this->introspection, $GLOBALS); }
}

class IntrospectionVariable implements ValueVariable {
    static function introspect(Introspection $introspection, array &$variables) {
        $results = array();

        foreach ($variables as $name => &$value) {
            $self        = new self;
            $self->name  = $name;
            $self->value = $introspection->introspectRef($value);
            $results[]   = $self;
        }

        return $results;
    }

    private function __construct() { }

    private $name;
    private $value;

    function name() { return $this->name; }

    function value() { return $this->value; }
}

class IntrospectionObjectProperty implements ValueObjectProperty {
    static function staticProperties(Introspection $introspection) {
        $results = array();

        foreach (get_declared_classes() as $class) {
            $reflection = new \ReflectionClass($class);

            foreach ($reflection->getProperties(\ReflectionProperty::IS_STATIC) as $property) {
                $self                = new self;
                $self->introspection = $introspection;
                $self->property      = $property;
                $results[]           = $self;
            }
        }

        return $results;
    }

    static function objectProperties(Introspection $introspection, $object) {
        $results = array();

        for ($reflection = new \ReflectionObject($object);
             $reflection !== false;
             $reflection = $reflection->getParentClass()) {
            foreach ($reflection->getProperties() as $property) {
                if (!$property->isStatic() && $property->class === $reflection->name) {
                    $self                = new self;
                    $self->introspection = $introspection;
                    $self->property      = $property;
                    $self->object        = $object;
                    $results[]           = $self;
                }
            }
        }

        return $results;
    }

    /** @var \ErrorHandler\Introspection */
    private $introspection;
    /** @var \ReflectionProperty */
    private $property;
    /** @var object */
    private $object;

    private function __construct() { }

    function name() { return $this->property->name; }

    function value() {
        $this->property->setAccessible(true);

        return $this->introspection->introspect($this->property->getValue($this->object));
    }

    function access() {
        if ($this->property->isPrivate())
            return 'private';
        else if ($this->property->isProtected())
            return 'protected';
        else
            return 'public';
    }

    function className() { return $this->property->class; }

    function isDefault() { return $this->property->isDefault(); }
}

class IntrospectionStaticVariable implements ValueVariableStatic {
    static function all(Introspection $i) {
        $globals = array();

        foreach (get_declared_classes() as $class) {
            $reflection = new \ReflectionClass($class);

            foreach ($reflection->getMethods() as $method) {
                $staticVariables = $method->getStaticVariables();

                foreach ($staticVariables as $variableName => &$varValue) {
                    $v           = new self;
                    $v->name     = $variableName;
                    $v->value    = $i->introspectRef($varValue);
                    $v->class    = $method->class;
                    $v->function = $method->getName();
                    $globals[]   = $v;
                }
            }
        }

        foreach (get_defined_functions() as $section) {
            foreach ($section as $function) {
                $reflection      = new \ReflectionFunction($function);
                $staticVariables = $reflection->getStaticVariables();

                foreach ($staticVariables as $propertyName => &$varValue) {
                    $v           = new self;
                    $v->name     = $propertyName;
                    $v->value    = $i->introspectRef($varValue);
                    $v->function = $function;
                    $globals[]   = $v;
                }
            }
        }

        return $globals;
    }

    private $function;
    private $name;
    private $value;
    private $class;

    private function __construct() { }

    function name() { return $this->name; }

    function value() { return $this->value; }

    function getFunction() { return $this->function; }

    function getClass() { return $this->class; }
}

class IntrospectionStackFrame implements ValueExceptionStackFrame {
    private $introspection;
    private $frame;

    function __construct(Introspection $introspection, array $frame) {
        $this->introspection = $introspection;
        $this->frame         = $frame;
    }

    function getFunction() {
        $function = array_get($this->frame, 'function');

        return is_scalar($function) ? "$function" : null;
    }

    function getLocation() {
        $file = array_get($this->frame, 'file');
        $line = array_get($this->frame, 'line');

        return $this->introspection->introspectCodeLocation($file, $line);
    }

    function getClass() {
        $class = array_get($this->frame, 'class');

        return is_scalar($class) ? "$class" : null;
    }

    function getIsStatic() {
        $type = array_get($this->frame, 'type');

        return $type === '::' ? true : ($type === '->' ? false : null);
    }

    function getObject() {
        $object = array_get($this->frame, 'object');

        return is_object($object) ? new IntrospectionObject($this->introspection, $object) : null;
    }

    function getArgs() {
        $args = array_get($this->frame, 'args');

        if (is_array($args)) {
            $result = array();

            foreach ($args as &$arg) {
                $result[] = $this->introspection->introspectRef($arg);
            }

            return $result;
        } else {
            return null;
        }
    }
}

class IntrospectionValue implements Value {
    private $value;
    private $introspection;

    function __construct(&$value, Introspection $introspection) {
        $this->value         =& $value;
        $this->introspection = $introspection;
    }

    function acceptVisitor(ValueVisitor $visitor) {
        $value =& $this->value;

        if (is_string($value))
            return $visitor->visitString(new ValueString($value));
        else if (is_int($value))
            return $visitor->visitInt($value);
        else if (is_bool($value))
            return $visitor->visitBool($value);
        else if (is_null($value))
            return $visitor->visitNull();
        else if (is_float($value))
            return $visitor->visitFloat($value);
        else if (is_array($value))
            return $visitor->visitArray(new IntrospectionArray($this->introspection, $value));
        else if (is_object($value))
            return $visitor->visitObject(new IntrospectionObject($this->introspection, $value));
        else if (is_resource($value))
            return $visitor->visitResource($this->introspection->introspectResource($value));
        else
            return $visitor->visitUnknown();
    }
}

