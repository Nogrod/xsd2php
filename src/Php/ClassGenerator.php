<?php
namespace GoetasWebservices\Xsd\XsdToPhp\Php;

use Doctrine\Inflector\InflectorFactory;
use GoetasWebservices\Xsd\XsdToPhp\Php\Structure\PHPClass;
use GoetasWebservices\Xsd\XsdToPhp\Php\Structure\PHPClassOf;
use GoetasWebservices\Xsd\XsdToPhp\Php\Structure\PHPProperty;
use Laminas\Code\Generator;
use Laminas\Code\Generator\DocBlock\Tag\ParamTag;
use Laminas\Code\Generator\DocBlock\Tag\ReturnTag;
use Laminas\Code\Generator\DocBlock\Tag\VarTag;
use Laminas\Code\Generator\DocBlockGenerator;
use Laminas\Code\Generator\MethodGenerator;
use Laminas\Code\Generator\ParameterGenerator;
use Laminas\Code\Generator\PropertyGenerator;
use Nogrod\XMLClientRuntime\Func;

class ClassGenerator
{
	private $inflector;

	public function __construct()
    {
		$this->inflector = InflectorFactory::create()->build();
    }

    private function handleBody(Generator\ClassGenerator $class, PHPClass $type)
    {
        $constants = array();
        foreach ($type->getChecks('__value') as $checkType => $checkValues) {
            if ($checkType == "enumeration") {
                foreach ($checkValues as $enumeration) {
                    $constants[] = $this->handleConstantValues($class, $type, $enumeration);
                }
            }
        }
        if ($constants) {
            // $this->handleStaticCheckProperty($class, $constants);
            return true;
        }

        foreach ($type->getProperties() as $prop) {
            if ($prop->getName() !== '__value') {
                $this->handleProperty($class, $prop);
            }
        }
        foreach ($type->getProperties() as $prop) {
            if ($prop->getName() !== '__value') {
                $this->handleMethod($class, $prop, $type);
            }
        }

        if (count($type->getProperties()) === 1 && $type->hasProperty('__value')) {
            return false;
        }

        return true;
    }

    private function handleValueMethod(Generator\ClassGenerator $generator, PHPProperty $prop, PHPClass $class, $all = true)
    {
        $type = $prop->getType();

        $docblock = new DocBlockGenerator('Construct');
        $docblock->setWordWrap(false);
        $paramTag = new ParamTag("value");
        $paramTag->setTypes(($type ? $type->getPhpType() : "mixed"));

        $docblock->setTag($paramTag);

        $param = new ParameterGenerator("value");
        if ($type && !$type->isNativeType()) {
            $param->setType($type->getPhpType());
        }
        $method = new MethodGenerator("__construct", [
            $param
        ]);
        $method->setDocBlock($docblock);
        $method->setBody("\$this->value(\$value);");

        $generator->addMethodFromGenerator($method);

        $docblock = new DocBlockGenerator('Gets or sets the inner value');
        $docblock->setWordWrap(false);
        $paramTag = new ParamTag("value");
        if ($type && $type instanceof PHPClassOf) {
            $paramTag->setTypes($type->getArg()->getType()->getPhpType() . "[]");
        } elseif ($type) {
            $paramTag->setTypes($prop->getType()->getPhpType());
        }
        $docblock->setTag($paramTag);

        $returnTag = new ReturnTag("mixed");

        if ($type && $type instanceof PHPClassOf) {
            $returnTag->setTypes($type->getArg()->getType()->getPhpType() . "[]");
        } elseif ($type) {
            $returnTag->setTypes($type->getPhpType());
        }
        $docblock->setTag($returnTag);

        $param = new ParameterGenerator("value");
        $param->setDefaultValue(null);

        if ($type && !$type->isNativeType()) {
            $param->setType($type->getPhpType());
        }
        $method = new MethodGenerator("value", []);
        $method->setDocBlock($docblock);

        $methodBody = "if (\$args = func_get_args()) {" . PHP_EOL;
        $methodBody .= "    \$this->" . $prop->getName() . " = \$args[0];" . PHP_EOL;
        $methodBody .= "}" . PHP_EOL;
        $methodBody .= "return \$this->" . $prop->getName() . ";" . PHP_EOL;
        $method->setBody($methodBody);

        $generator->addMethodFromGenerator($method);

        $docblock = new DocBlockGenerator('Gets a string value');
        $docblock->setWordWrap(false);
        $docblock->setTag(new ReturnTag("string"));
        $method = new MethodGenerator("__toString");
        $method->setDocBlock($docblock);
        $method->setBody("return strval(\$this->" . $prop->getName() . ");");
        $generator->addMethodFromGenerator($method);
    }

    private function handleSetter(Generator\ClassGenerator $generator, PHPProperty $prop, PHPClass $class)
    {
        $methodBody = '';
        $docblock = new DocBlockGenerator();
        $docblock->setWordWrap(false);

        $docblock->setShortDescription("Sets a new " . $prop->getName());

        if ($prop->getDoc()) {
            $docblock->setLongDescription($prop->getDoc());
        }

        $patramTag = new ParamTag($prop->getName());
        $docblock->setTag($patramTag);

        $return = new ReturnTag("self");
        $docblock->setTag($return);

        $type = $prop->getType();

        $method = new MethodGenerator("set" . $this->inflector->classify($prop->getName()));

        $parameter = new ParameterGenerator($prop->getName());

        if ($type && $type instanceof PHPClassOf) {
            $patramTag->setTypes($type->getArg()
                    ->getType()->getPhpType() . "[]");
            $parameter->setType("array");

            if ($p = $type->getArg()->getType()->isSimpleType()
            ) {
                if (($t = $p->getType())) {
                    $patramTag->setTypes($t->getPhpType());
                }
            }
        } elseif ($type) {
            if ($type->isNativeType()) {
                $patramTag->setTypes($type->getPhpType());
            } elseif ($p = $type->isSimpleType()) {
                if (($t = $p->getType()) && !$t->isNativeType()) {
                    $patramTag->setTypes($t->getPhpType());
                    $parameter->setType($t->getPhpType());
                } elseif ($t && !$t->isNativeType()) {
                    $patramTag->setTypes($t->getPhpType());
                    $parameter->setType($t->getPhpType());
                } elseif ($t) {
                    $patramTag->setTypes($t->getPhpType());
                }
            } else {
                $patramTag->setTypes($type->getPhpType());
                $parameter->setType($type->getPhpType());
            }
        }

        $methodBody .= "\$this->" . $prop->getName() . " = \$" . $prop->getName() . ";" . PHP_EOL;
        $methodBody .= "return \$this;";
        $method->setBody($methodBody);
        $method->setDocBlock($docblock);
        $method->setParameter($parameter);

        $generator->addMethodFromGenerator($method);
    }

    private function handleGetter(Generator\ClassGenerator $generator, PHPProperty $prop, PHPClass $class)
    {

        if ($prop->getType() instanceof PHPClassOf) {
            $docblock = new DocBlockGenerator();
            $docblock->setWordWrap(false);
            $docblock->setShortDescription("isset " . $prop->getName());
            if ($prop->getDoc()) {
                $docblock->setLongDescription($prop->getDoc());
            }

            $patramTag = new ParamTag("index", "int|string");
            $docblock->setTag($patramTag);

            $docblock->setTag(new ReturnTag("bool"));

            $paramIndex = new ParameterGenerator("index");

            $method = new MethodGenerator("isset" . $this->inflector->classify($prop->getName()), [$paramIndex]);
            $method->setDocBlock($docblock);
            $method->setBody("return isset(\$this->" . $prop->getName() . "[\$index]);");
            $generator->addMethodFromGenerator($method);

            $docblock = new DocBlockGenerator();
            $docblock->setWordWrap(false);
            $docblock->setShortDescription("unset " . $prop->getName());
            if ($prop->getDoc()) {
                $docblock->setLongDescription($prop->getDoc());
            }

            $patramTag = new ParamTag("index", "int|string");
            $docblock->setTag($patramTag);
            $paramIndex = new ParameterGenerator("index");

            $docblock->setTag(new ReturnTag("void"));


            $method = new MethodGenerator("unset" . $this->inflector->classify($prop->getName()), [$paramIndex]);
            $method->setDocBlock($docblock);
            $method->setBody("unset(\$this->" . $prop->getName() . "[\$index]);");
            $generator->addMethodFromGenerator($method);
        }
        // ////

        $docblock = new DocBlockGenerator();
        $docblock->setWordWrap(false);

        $docblock->setShortDescription("Gets as " . $prop->getName());

        if ($prop->getDoc()) {
            $docblock->setLongDescription($prop->getDoc());
        }

        $tag = new ReturnTag("mixed");
        $type = $prop->getType();
        if ($type && $type instanceof PHPClassOf) {
            $tt = $type->getArg()->getType();
            $tag->setTypes($tt->getPhpType() . "[]");
            if ($p = $tt->isSimpleType()) {
                if (($t = $p->getType())) {
                    $tag->setTypes($t->getPhpType() . "[]");
                }
            }
        } elseif ($type) {

            if ($p = $type->isSimpleType()) {
                if ($t = $p->getType()) {
                    $tag->setTypes($t->getPhpType());
                }
            } else {
                $tag->setTypes($type->getPhpType());
            }
        }

        $docblock->setTag($tag);

        $method = new MethodGenerator("get" . $this->inflector->classify($prop->getName()));
        $method->setDocBlock($docblock);
        $method->setBody("return \$this->" . $prop->getName() . ";");

        $generator->addMethodFromGenerator($method);
    }

    private function handleAdder(Generator\ClassGenerator $generator, PHPProperty $prop, PHPClass $class)
    {
        $type = $prop->getType();
        $propName = $type->getArg()->getName();

        $docblock = new DocBlockGenerator();
        $docblock->setWordWrap(false);
        $docblock->setShortDescription("Adds as $propName");

        if ($prop->getDoc()) {
            $docblock->setLongDescription($prop->getDoc());
        }

        $return = new ReturnTag();
        $return->setTypes("self");
        $docblock->setTag($return);

        $patramTag = new ParamTag($propName, $type->getArg()->getType()->getPhpType());
        $docblock->setTag($patramTag);

        $method = new MethodGenerator("addTo" . $this->inflector->classify($prop->getName()));

        $parameter = new ParameterGenerator($propName);
        $tt = $type->getArg()->getType();

        if (!$tt->isNativeType()) {

            if ($p = $tt->isSimpleType()) {
                if (($t = $p->getType())) {
                    $patramTag->setTypes($t->getPhpType());

                    if (!$t->isNativeType()) {
                        $parameter->setType($t->getPhpType());
                    }
                }
            } elseif (!$tt->isNativeType()) {
                $parameter->setType($tt->getPhpType());
            }
        }

        $methodBody = "\$this->" . $prop->getName() . "[] = \$" . $propName . ";" . PHP_EOL;
        $methodBody .= "return \$this;";
        $method->setBody($methodBody);
        $method->setDocBlock($docblock);
        $method->setParameter($parameter);

        $generator->addMethodFromGenerator($method);
    }

    private function handleMethod(Generator\ClassGenerator $generator, PHPProperty $prop, PHPClass $class)
    {
        if ($prop->getType() instanceof PHPClassOf) {
            $this->handleAdder($generator, $prop, $class);
        }

        $this->handleGetter($generator, $prop, $class);
        $this->handleSetter($generator, $prop, $class);
    }

    private function handleConstantValues(Generator\ClassGenerator $generator, PHPClass $type, array $enumeration)
    {
        if (preg_match("/[\r\n\t]/", $enumeration['value'])) {
            return;
        }
        $docblock = new DocBlockGenerator("Constant for " . var_export($enumeration['value'], true) . " value.");
        if (trim($enumeration['doc'])) {
            $docblock->setLongDescription(trim($enumeration['doc']));
        }
        $constantNameFixer = function($s){
            $s = preg_replace('/([[:upper:]]+[[:lower:]]*)|([[:lower:]]+)|(\d+)/', '$1$2$3_', $s);
            $s = preg_replace('/[^\p{L}\p{N}_]/u', '_', $s);

            return mb_strtoupper(trim($s, '_'));
        };
        $prop = new PropertyGenerator("VAL_" . $constantNameFixer($enumeration['value']), $enumeration['value'], PropertyGenerator::FLAG_CONSTANT);
        $prop->setDocBlock($docblock);
        $generator->addPropertyFromGenerator($prop);
        return $prop->getDefaultValue()->getValue();
    }

    private function handleProperty(Generator\ClassGenerator $class, PHPProperty $prop)
    {
        $generatedProp = new PropertyGenerator($prop->getName());
        $generatedProp->setVisibility(PropertyGenerator::VISIBILITY_PRIVATE);

        $class->addPropertyFromGenerator($generatedProp);

        if ($prop->getType() && (!$prop->getType()->getNamespace() && $prop->getType()->getName() == "array")) {
            // $generatedProp->setDefaultValue(array(), PropertyValueGenerator::TYPE_AUTO, PropertyValueGenerator::OUTPUT_SINGLE_LINE);
        }

        $docBlock = new DocBlockGenerator();
        $docBlock->setWordWrap(false);
        $generatedProp->setDocBlock($docBlock);

        if ($prop->getDoc()) {
            $docBlock->setLongDescription($prop->getDoc());
        }
        $tag = new VarTag($prop->getName(), 'mixed');

        $type = $prop->getType();

        if ($type && $type instanceof PHPClassOf) {
            $tt = $type->getArg()->getType();
            $tag->setTypes($tt->getPhpType() . "[]");
            if ($p = $tt->isSimpleType()) {
                if (($t = $p->getType())) {
                    $tag->setTypes($t->getPhpType() . "[]");
                }
            }
            $generatedProp->setDefaultValue($type->getArg()->getDefault());
        } elseif ($type) {

            if ($type->isNativeType()) {
                $tag->setTypes($type->getPhpType());
            } elseif (($p = $type->isSimpleType()) && ($t = $p->getType())) {
                $tag->setTypes($t->getPhpType());
            } else {
                $tag->setTypes($prop->getType()->getPhpType());
            }
        }
        $docBlock->setTag($tag);
    }

    public function generate(PHPClass $type, bool $noSabre = false)
    {
        $class = new Generator\ClassGenerator();
        $docblock = new DocBlockGenerator("Class representing " . $type->getName());
        $docblock->setWordWrap(false);
        if ($type->getDoc()) {
            $docblock->setLongDescription($type->getDoc());
        }
        $class->setNamespaceName($type->getNamespace() ?: NULL);
        $class->setName($type->getName());
        $class->setDocblock($docblock);

        if ($extends = $type->getExtends()) {

            if ($p = $extends->isSimpleType()) {
                $this->handleProperty($class, $p);
                $this->handleValueMethod($class, $p, $extends);
            } else {

                $class->setExtendedClass($extends->getFullName());

                if ($extends->getNamespace() != $type->getNamespace()) {
                    if ($extends->getName() == $type->getName()) {
                        $class->addUse($type->getExtends()->getFullName(), $extends->getName() . "Base");
                    } else {
                        $class->addUse($extends->getFullName());
                    }
                }
            }
        }

        if ($this->handleBody($class, $type)) {
            if (!$noSabre) {
                $this->addSerialization($class, $type);
                $this->addDeserialization($class, $type);
            }
            return $class;
        }
    }

    private function addSerialization(Generator\ClassGenerator $class, PHPClass $type)
    {
        if ($type->getMeta() === null) return;
        $isBase = $class->getExtendedClass() === null;
        //$class->addUse('\Sabre\Xml\Writer');
        $method = new MethodGenerator('xmlSerialize');
        $method->setVisibility(MethodGenerator::VISIBILITY_PUBLIC);
        $param = new ParameterGenerator('writer');
        $param->setType('\Sabre\Xml\Writer');
        $method->setParameter($param);
        $method->setReturnType('void');
        $meta = $type->getMeta();
        $className = array_key_first($meta);
        $methodLines = [];
        if (!$isBase) {
            $methodLines[] = 'parent::xmlSerialize($writer);';
        } elseif (isset($meta[$className]['virtual_properties'])) {
            foreach ($meta[$className]['virtual_properties'] as $property) {
                if (isset($property['xml_attribute']) && $property['xml_attribute']) {
                    $methodLines[] = '$writer->writeAttribute("'.$property['serialized_name'].'", '.$property['exp'].');';
                }
            }
        }
        if (isset($meta[$className]['properties'])) {
            foreach ($meta[$className]['properties'] as $property) {
                $isBool = $property['type'] === 'bool';
                $methodLines[] = '$value = $this->'.$property['accessor']['getter'].'();';
                if ($isBool) $methodLines[] = '$value = null !== $value ? ($value ? \'true\' : \'false\') : null;';
                if (isset($property['xml_value']) && $property['xml_value']) {
                    $methodLines[] = '$writer->write($value);';
                    continue;
                }
                if (isset($property['xml_attribute']) && $property['xml_attribute']) {
                    $methodLines[] = 'if (null !== $value)';
                    $methodLines[] = '$writer->writeAttribute("'.$property['serialized_name'].'", $value);';
                    continue;
                }
                $ns = '{'.$property['xml_element']['namespace'].'}';
                if (isset($property['xml_list']) && ($property['xml_list']['inline'] || $property['xml_list']['skip_when_empty'])) {
                    $methodLines[] = 'if (null !== $value && !empty($this->'.$property['accessor']['getter'] . '()))';
                    $arrayMap = 'array_map(function($v){return ["'.$property['xml_list']['entry_name'].'" => $v];}, $value)';
                    if ($property['xml_list']['inline'])
                        $methodLines[] = '$writer->write('.$arrayMap.');';
                    else
                        $methodLines[] = '$writer->writeElement("'.$ns.$property['serialized_name'].'", '.$arrayMap.');';
                } else {
                    $methodLines[] = 'if (null !== $value)';
                    $methodLines[] = '$writer->writeElement("'.$ns.$property['serialized_name'].'", $value);';
                }
            }
        }
        $method->setBody(implode(PHP_EOL, $methodLines));
        $class->addMethodFromGenerator($method);
        if ($isBase) {
            $ifaces = $class->getImplementedInterfaces();
            $ifaces[] = '\Sabre\Xml\XmlSerializable';
            $class->setImplementedInterfaces($ifaces);
        }
    }

    private function addDeserialization(Generator\ClassGenerator $class, PHPClass $type)
    {
        if ($type->getMeta() === null) return;
        $isBase = $class->getExtendedClass() === null;
        $meta = $type->getMeta();
        $className = array_key_first($meta);
        $valueType = isset($meta[$className]['properties']) && isset($meta[$className]['properties']['__value']);

        //$class->addUse('\Sabre\Xml\Reader');
        $method = new MethodGenerator('xmlDeserialize');
        $method->setVisibility(MethodGenerator::VISIBILITY_PUBLIC);
        $method->setStatic(true);
        $param = new ParameterGenerator('reader');
        $param->setType('\Sabre\Xml\Reader');
        $method->setParameter($param);
        $methodLines = [];
        $methodLines[] = 'return self::fromKeyValue($reader->parseInnerTree([]));';
        $method->setBody(implode(PHP_EOL, $methodLines));
        $class->addMethodFromGenerator($method);

        $method = new MethodGenerator('fromKeyValue');
        $method->setVisibility(MethodGenerator::VISIBILITY_PUBLIC);
        $method->setStatic(true);
        $param = new ParameterGenerator('keyValue');
        //$param->setType('array');
        //$param->setPassedByReference(true);
        $method->setParameter($param);
        $methodLines = [];
        $methodLines[] = '$self = new self('.($valueType ? '$keyValue' : '').');';
        $methodLines[] = '$self->setKeyValue($keyValue);';
        $methodLines[] = 'return $self;';
        $method->setBody(implode(PHP_EOL, $methodLines));
        $class->addMethodFromGenerator($method);

        $method = new MethodGenerator('setKeyValue');
        $method->setVisibility(MethodGenerator::VISIBILITY_PUBLIC);
        $param = new ParameterGenerator('keyValue');
        //$param->setType('array');
        //$param->setPassedByReference(true);
        $method->setParameter($param);
        $methodLines = [];
        if (!$isBase) {
            $methodLines[] = 'parent::setKeyValue($keyValue);';
        }
        if (isset($meta[$className]['properties'])) {
            foreach ($meta[$className]['properties'] as $property) {
                if (isset($property['xml_value']) && $property['xml_value']) {
                    //TODO
                    continue;
                }
                if (isset($property['xml_attribute']) && $property['xml_attribute']) {
                    //TODO
                    continue;
                }
                $class->addUse(Func::class);
                $ns = '{'.$property['xml_element']['namespace'].'}';
                $entry = $ns.$property['serialized_name'];
                $type = $property['type'];
                $isArray = false;
                $isInline = false;
                preg_match('/array<(?P<type>.+)>/', $type, $hits);
                if (isset($hits['type'])) {
                    $type = $hits['type'];
                    $isArray = true;
                    $isInline = isset($property['xml_list']) && $property['xml_list']['inline'];
                }
                if ($isArray) {
                    $methodLines[] = '$value = Func::mapArray($keyValue, \''.$entry.'\', true);';
                    $methodLines[] = 'if (null !== $value && !empty($value))';
                } else {
                    $methodLines[] = '$value = Func::mapArray($keyValue, \''.$entry.'\');';
                    $methodLines[] = 'if (null !== $value)';
                }
                switch ($type) {
                    case 'bool':
                        $methodLines[] = '$this->'.$property['accessor']['setter'].'(filter_var($value, FILTER_VALIDATE_BOOLEAN));';
                        break;
                    case 'string':
                    case 'float':
                    case 'int':
                    case 'GoetasWebservices\Xsd\XsdToPhp\XMLSchema\Time':
                        $methodLines[] = '$this->'.$property['accessor']['setter'].'($value);';
                        break;
                    case 'GoetasWebservices\Xsd\XsdToPhp\XMLSchema\DateTime':
                    case 'GoetasWebservices\Xsd\XsdToPhp\XMLSchema\Date':
                        $methodLines[] = '$this->'.$property['accessor']['setter'].'(new \DateTime($value));';
                        break;
                    case 'DateInterval':
                        $methodLines[] = '$this->'.$property['accessor']['setter'].'(new \DateInterval($value));';
                        break;
                    default:
                        if ($isArray) {
                            if ($isInline) {
                                $value = 'array_map(function($v){return \\'.$type.'::fromKeyValue($v);}, $value)';
                            } else {
                                $value = 'array_map(function($v){return \\'.$type.'::fromKeyValue(Func::mapArray($v, \''.$ns.$property['xml_list']['entry_name'].'\'));}, $value)';
                            }
                        } else
                            $value = '\\'.$type.'::fromKeyValue($value)';
                        $methodLines[] = '$this->'.$property['accessor']['setter'].'('.$value.');';
                        break;
                }
            }
        }
        $method->setBody(implode(PHP_EOL, $methodLines));
        $class->addMethodFromGenerator($method);

        if ($isBase) {
            $ifaces = $class->getImplementedInterfaces();
            $ifaces[] = '\Sabre\Xml\XmlDeserializable';
            $class->setImplementedInterfaces($ifaces);
        }
    }
}
