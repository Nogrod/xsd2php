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
            $paramTag->setTypes($type->getArg()->getType()->getPhpType()."[]");
        } elseif ($type) {
            $paramTag->setTypes($prop->getType()->getPhpType());
        }
        $docblock->setTag($paramTag);

        $returnTag = new ReturnTag("mixed");

        if ($type && $type instanceof PHPClassOf) {
            $returnTag->setTypes($type->getArg()->getType()->getPhpType()."[]");
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

        $methodBody = "if (\$args = func_get_args()) {".PHP_EOL;
        $methodBody .= "    \$this->".$prop->getName()." = \$args[0];".PHP_EOL;
        $methodBody .= "}".PHP_EOL;
        $methodBody .= "return \$this->".$prop->getName().";".PHP_EOL;
        $method->setBody($methodBody);

        $generator->addMethodFromGenerator($method);

        $docblock = new DocBlockGenerator('Gets a string value');
        $docblock->setWordWrap(false);
        $docblock->setTag(new ReturnTag("string"));
        $method = new MethodGenerator("__toString");
        $method->setDocBlock($docblock);
        $method->setBody("return strval(\$this->".$prop->getName().");");
        $generator->addMethodFromGenerator($method);
    }

    private function handleSetter(Generator\ClassGenerator $generator, PHPProperty $prop, PHPClass $class)
    {
        $methodBody = '';
        $docblock = new DocBlockGenerator();
        $docblock->setWordWrap(false);

        $docblock->setShortDescription("Sets a new ".$prop->getName());

        if ($prop->getDoc()) {
            $docblock->setLongDescription($prop->getDoc());
        }

        $patramTag = new ParamTag($prop->getName());
        $docblock->setTag($patramTag);

        $return = new ReturnTag("self");
        $docblock->setTag($return);

        $type = $prop->getType();

        $method = new MethodGenerator("set".$this->inflector->classify($prop->getName()));

        $parameter = new ParameterGenerator($prop->getName());

        if ($type && $type instanceof PHPClassOf) {
            // iterable rather than array: an inline list may be handed a Generator so
            // that xmlSerialize() can stream it instead of holding every entry at once.
            $patramTag->setTypes("iterable<".$type->getArg()
                                     ->getType()->getPhpType().">");
            $parameter->setType("iterable");

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

        $methodBody .= "\$this->".$prop->getName()." = \$".$prop->getName().";".PHP_EOL;
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
            $docblock->setShortDescription("isset ".$prop->getName());
            if ($prop->getDoc()) {
                $docblock->setLongDescription($prop->getDoc());
            }

            $patramTag = new ParamTag("index", "int|string");
            $docblock->setTag($patramTag);

            $docblock->setTag(new ReturnTag("bool"));

            $paramIndex = new ParameterGenerator("index");

            $method = new MethodGenerator("isset".$this->inflector->classify($prop->getName()), [$paramIndex]);
            $method->setDocBlock($docblock);
            $method->setBody("return isset(\$this->".$prop->getName()."[\$index]);");
            $generator->addMethodFromGenerator($method);

            $docblock = new DocBlockGenerator();
            $docblock->setWordWrap(false);
            $docblock->setShortDescription("unset ".$prop->getName());
            if ($prop->getDoc()) {
                $docblock->setLongDescription($prop->getDoc());
            }

            $patramTag = new ParamTag("index", "int|string");
            $docblock->setTag($patramTag);
            $paramIndex = new ParameterGenerator("index");

            $docblock->setTag(new ReturnTag("void"));


            $method = new MethodGenerator("unset".$this->inflector->classify($prop->getName()), [$paramIndex]);
            $method->setDocBlock($docblock);
            $method->setBody("unset(\$this->".$prop->getName()."[\$index]);");
            $generator->addMethodFromGenerator($method);
        }
        // ////

        $docblock = new DocBlockGenerator();
        $docblock->setWordWrap(false);

        $docblock->setShortDescription("Gets as ".$prop->getName());

        if ($prop->getDoc()) {
            $docblock->setLongDescription($prop->getDoc());
        }

        $tag = new ReturnTag("mixed");
        $type = $prop->getType();
        if ($type && $type instanceof PHPClassOf) {
            // Matches the setter: the value is an array unless a lazy iterable was set.
            $tt = $type->getArg()->getType();
            $tag->setTypes("iterable<".$tt->getPhpType().">");
            if ($p = $tt->isSimpleType()) {
                if (($t = $p->getType())) {
                    $tag->setTypes("iterable<".$t->getPhpType().">");
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

        $method = new MethodGenerator("get".$this->inflector->classify($prop->getName()));
        $method->setDocBlock($docblock);
        $method->setBody("return \$this->".$prop->getName().";");

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

        $method = new MethodGenerator("addTo".$this->inflector->classify($prop->getName()));

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

        // The property may hold a lazy iterable, which cannot be appended to.
        $methodBody = "if (!is_array(\$this->".$prop->getName()."))".PHP_EOL;
        $methodBody .= "throw new \\LogicException('".$prop->getName()." is a lazy iterable and cannot be appended to; set an array instead.');".PHP_EOL;
        $methodBody .= "\$this->".$prop->getName()."[] = \$".$propName.";".PHP_EOL;
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
        $docblock = new DocBlockGenerator("Constant for ".var_export($enumeration['value'], true)." value.");
        if (trim($enumeration['doc'])) {
            $docblock->setLongDescription(trim($enumeration['doc']));
        }
        $constantNameFixer = function($s){
            $s = preg_replace('/([[:upper:]]+[[:lower:]]*)|([[:lower:]]+)|(\d+)/', '$1$2$3_', $s);
            $s = preg_replace('/[^\p{L}\p{N}_]/u', '_', $s);

            return mb_strtoupper(trim($s, '_'));
        };
        $prop = new PropertyGenerator("VAL_".$constantNameFixer($enumeration['value']), $enumeration['value'], PropertyGenerator::FLAG_CONSTANT);
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
            $tag->setTypes($tt->getPhpType()."[]");
            if ($p = $tt->isSimpleType()) {
                if (($t = $p->getType())) {
                    $tag->setTypes($t->getPhpType()."[]");
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
        $docblock = new DocBlockGenerator("Class representing ".$type->getName());
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
                        $class->addUse($type->getExtends()->getFullName(), $extends->getName()."Base");
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
                $this->addJsonSerialization($class, $type);
            }
            return $class;
        }
    }

    private const SCALAR_KINDS = [
        'string' => 'string',
        'int' => 'int',
        'float' => 'float',
        'bool' => 'bool',
        'GoetasWebservices\Xsd\XsdToPhp\XMLSchema\DateTime' => 'datetime',
        'GoetasWebservices\Xsd\XsdToPhp\XMLSchema\Date' => 'date',
        'GoetasWebservices\Xsd\XsdToPhp\XMLSchema\Time' => 'time',
        'DateInterval' => 'interval',
    ];

    /**
     * Splits a JMS metadata type into [kind, element class, is list].
     *
     * kind is one of SCALAR_KINDS, 'object' for a generated class, or 'other'.
     */
    private function kindOf(string $type): array
    {
        $isArray = false;
        if (preg_match('/^array<(.+)>$/', $type, $hits)) {
            $type = $hits[1];
            $isArray = true;
        }
        $type = ltrim($type, '\\');
        if (isset(self::SCALAR_KINDS[$type])) {
            return [self::SCALAR_KINDS[$type], null, $isArray];
        }
        if (str_contains($type, '\\') && preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*$/', $type)) {
            return ['object', $type, $isArray];
        }

        return ['other', null, $isArray];
    }

    /**
     * The default namespace the generated code declares on elements of $type: the one
     * of the topmost generated class in its hierarchy, or null if it declares none.
     */
    private function scopeNamespace(PHPClass $type): ?string
    {
        $root = $type;
        while (($extends = $root->getExtends()) && !$extends->isSimpleType() && $extends->getMeta() !== null) {
            $root = $extends;
        }
        $meta = $root->getMeta();
        $exp = $meta[array_key_first($meta)]['virtual_properties']['ns_prop']['exp'] ?? null;

        return null !== $exp ? trim($exp, '"') : null;
    }

    /**
     * The metadata of the __value property in the hierarchy of $type, if it has simple content.
     */
    private function valueProperty(PHPClass $type): ?array
    {
        for ($t = $type; $t; $t = $t->getExtends()) {
            $meta = $t->getMeta();
            if (null !== $meta && isset($meta[array_key_first($meta)]['properties']['__value'])) {
                return $meta[array_key_first($meta)]['properties']['__value'];
            }
            if ($t->getExtends() && $t->getExtends()->isSimpleType()) {
                break;
            }
        }

        return null;
    }

    /**
     * PHP expression turning $var of kind $kind into the string written to the document,
     * or null if it has to go through sabre.
     */
    private function toXmlExpr(string $kind, string $var): ?string
    {
        switch ($kind) {
            case 'string':
            case 'int':
            case 'float':
                return '(string) '.$var;
            case 'bool':
                return '('.$var.' ? \'true\' : \'false\')';
            case 'datetime':
                return 'Func::formatDateTime('.$var.')';
            case 'date':
                return 'Func::formatDate('.$var.')';
            case 'time':
                return 'Func::formatTime('.$var.')';
        }

        return null;
    }

    /**
     * PHP expression turning the string $var read from the document into kind $kind.
     */
    private function fromXmlExpr(string $kind, string $var): string
    {
        switch ($kind) {
            case 'int':
                return '(int) '.$var;
            case 'float':
                return '(float) '.$var;
            case 'bool':
                return 'filter_var('.$var.', FILTER_VALIDATE_BOOLEAN)';
            case 'datetime':
            case 'date':
                return 'new \DateTime('.$var.')';
            case 'interval':
                return 'new \DateInterval('.$var.')';
        }

        return $var;
    }

    /**
     * Lines writing one element $local holding $var.
     *
     * Elements in the namespace the enclosing element declares as default are written
     * with the native XMLWriter methods; anything else goes through sabre.
     */
    private function writeElementLines(string $local, ?string $ns, string $kind, ?string $scopeNs, string $var): array
    {
        $fast = null !== $scopeNs && $ns === $scopeNs;
        if ($fast && 'object' === $kind) {
            return [
                '$writer->startElementNs(null, '.var_export($local, true).', null);',
                $var.'->xmlSerialize($writer);',
                '$writer->endElement();',
            ];
        }
        $expr = $this->toXmlExpr($kind, $var);
        if ($fast && null !== $expr) {
            return ['$writer->writeElementNs(null, '.var_export($local, true).', null, '.$expr.');'];
        }

        return ['$writer->writeElement('.var_export((null !== $ns ? '{'.$ns.'}' : '').$local, true).', '.($expr ?? $var).');'];
    }

    private function addSerialization(Generator\ClassGenerator $class, PHPClass $type)
    {
        if ($type->getMeta() === null) return;
        $isBase = $class->getExtendedClass() === null;
        $meta = $type->getMeta();
        $className = array_key_first($meta);
        $scopeNs = $this->scopeNamespace($type);
        $class->addUse(Func::class);

        // XMLWriter silently drops attributes written after text or child elements, so
        // attributes of the whole class hierarchy go first, then the value and elements.
        $attributeLines = [];
        $elementLines = [];
        if (isset($meta[$className]['xml_root_namespace'])) {
            // Global elements (API requests and responses) always carry their own xmlns,
            // even inside a parent in the same namespace: eBay processes the messages of
            // a BulkDataExchangeRequests file one by one and rejects them without it.
            $attributeLines[] = 'Func::writeRootNamespace($writer, '.var_export($meta[$className]['xml_root_namespace'], true).');';
        }
        if (!$isBase) {
            $attributeLines[] = 'parent::xmlSerializeAttributes($writer);';
            $elementLines[] = 'parent::xmlSerializeElements($writer);';
        } elseif (isset($meta[$className]['virtual_properties'])) {
            foreach ($meta[$className]['virtual_properties'] as $property) {
                if (isset($property['xml_attribute']) && $property['xml_attribute']) {
                    if ($property['serialized_name'] === 'xmlns') {
                        // Only declared where it is not already the default namespace in scope
                        $attributeLines[] = 'Func::writeDefaultNamespace($writer, '.$property['exp'].');';
                    } else {
                        $attributeLines[] = '$writer->writeAttribute("'.$property['serialized_name'].'", '.$property['exp'].');';
                    }
                }
            }
        }
        foreach ($meta[$className]['properties'] ?? [] as $name => $property) {
            [$kind, , ] = $this->kindOf($property['type']);
            $lines = ['$value = $this->'.$name.';'];
            if (isset($property['xml_attribute']) && $property['xml_attribute']) {
                $lines[] = 'if (null !== $value) {';
                $lines[] = '$writer->writeAttribute('.var_export($property['serialized_name'], true).', '.($this->toXmlExpr($kind, '$value') ?? '$value').');';
                $lines[] = '}';
                array_push($attributeLines, ...$lines);
                continue;
            }
            if (isset($property['xml_value']) && $property['xml_value']) {
                $expr = $this->toXmlExpr($kind, '$value');
                $lines[] = 'if (null !== $value) {';
                $lines[] = null !== $expr ? '$writer->text('.$expr.');' : '$writer->write($value);';
                $lines[] = '}';
                array_push($elementLines, ...$lines);
                continue;
            }
            $ns = $property['xml_element']['namespace'] ?? null;
            if (isset($property['xml_list'])) {
                $entryNs = $property['xml_list']['namespace'] ?? $ns;
                $entryLines = $this->writeElementLines($property['xml_list']['entry_name'], $entryNs, $kind, $scopeNs, '$v');
                // Written entry by entry so the property may hold a lazy iterable
                // (a Generator) instead of a materialised array.
                $lines[] = 'if (null !== $value) {';
                if ($property['xml_list']['inline']) {
                    $lines[] = 'foreach ($value as $v) {';
                    array_push($lines, ...$entryLines);
                    $lines[] = '}';
                } else {
                    // The wrapper is only opened with the first entry: empty lists emit nothing.
                    $fast = null !== $scopeNs && $ns === $scopeNs;
                    $lines[] = '$open = false;';
                    $lines[] = 'foreach ($value as $v) {';
                    $lines[] = 'if (!$open) {';
                    $lines[] = $fast
                        ? '$writer->startElementNs(null, '.var_export($property['serialized_name'], true).', null);'
                        : '$writer->startElement('.var_export((null !== $ns ? '{'.$ns.'}' : '').$property['serialized_name'], true).');';
                    $lines[] = '$open = true;';
                    $lines[] = '}';
                    array_push($lines, ...$entryLines);
                    $lines[] = '}';
                    $lines[] = 'if ($open) {';
                    $lines[] = '$writer->endElement();';
                    $lines[] = '}';
                }
                $lines[] = '}';
            } else {
                $lines[] = 'if (null !== $value) {';
                array_push($lines, ...$this->writeElementLines($property['serialized_name'], $ns, $kind, $scopeNs, '$value'));
                $lines[] = '}';
            }
            array_push($elementLines, ...$lines);
        }

        if ($isBase) {
            $method = new MethodGenerator('xmlSerialize');
            $method->setVisibility(MethodGenerator::VISIBILITY_PUBLIC);
            $param = new ParameterGenerator('writer');
            $param->setType('\Sabre\Xml\Writer');
            $method->setParameter($param);
            $method->setReturnType('void');
            $method->setBody('$this->xmlSerializeAttributes($writer);'.PHP_EOL.'$this->xmlSerializeElements($writer);');
            $class->addMethodFromGenerator($method);
        }
        foreach (['xmlSerializeAttributes' => $attributeLines, 'xmlSerializeElements' => $elementLines] as $name => $lines) {
            $method = new MethodGenerator($name);
            $method->setVisibility(MethodGenerator::VISIBILITY_PROTECTED);
            $param = new ParameterGenerator('writer');
            $param->setType('\Sabre\Xml\Writer');
            $method->setParameter($param);
            $method->setReturnType('void');
            $method->setBody(implode(PHP_EOL, $lines));
            $class->addMethodFromGenerator($method);
        }
        if ($isBase) {
            $ifaces = $class->getImplementedInterfaces();
            $ifaces[] = '\Sabre\Xml\XmlSerializable';
            $class->setImplementedInterfaces($ifaces);
        }
    }

    /**
     * jsonSerialize(): the properties keyed by element and attribute name, the value of
     * simple content as __value, without the ones that are null.
     */
    private function addJsonSerialization(Generator\ClassGenerator $class, PHPClass $type)
    {
        if ($type->getMeta() === null) return;
        $isBase = $class->getExtendedClass() === null;
        $meta = $type->getMeta();
        $className = array_key_first($meta);
        $class->addUse(Func::class);

        $lines = [$isBase ? '$data = [];' : '$data = parent::jsonProperties();'];
        foreach ($meta[$className]['properties'] ?? [] as $name => $property) {
            [$kind, , $isArray] = $this->kindOf($property['type']);
            $key = (isset($property['xml_value']) && $property['xml_value']) ? '__value' : $property['serialized_name'];
            $isDate = in_array($kind, ['datetime', 'date', 'time'], true);
            if ($isArray) {
                $expr = 'Func::jsonList($this->'.$name.')';
                if ($isDate) {
                    $expr = 'null !== ($v = '.$expr.') ? array_map([Func::class, \'jsonDate\'], $v) : null';
                }
            } else {
                $expr = $isDate ? 'Func::jsonDate($this->'.$name.')' : '$this->'.$name;
            }
            $lines[] = '$data['.var_export($key, true).'] = '.$expr.';';
        }
        $lines[] = 'return $data;';

        $method = new MethodGenerator('jsonProperties');
        $method->setVisibility(MethodGenerator::VISIBILITY_PROTECTED);
        $method->setReturnType('array');
        $method->setBody(implode(PHP_EOL, $lines));
        $class->addMethodFromGenerator($method);

        if ($isBase) {
            $method = new MethodGenerator('jsonSerialize');
            $method->setVisibility(MethodGenerator::VISIBILITY_PUBLIC);
            $method->setReturnType('mixed');
            $method->setBody('return array_filter($this->jsonProperties(), static fn ($v) => null !== $v);');
            $class->addMethodFromGenerator($method);

            $ifaces = $class->getImplementedInterfaces();
            $ifaces[] = '\JsonSerializable';
            $class->setImplementedInterfaces($ifaces);
        }
    }

    /**
     * Lines reading the element the reader is positioned on and storing it with $store
     * (a format string taking the value expression), moving past its end.
     */
    private function readElementLines(string $kind, ?string $elementClass, string $store): array
    {
        if ('object' === $kind) {
            return [sprintf($store, '\\'.$elementClass.'::xmlRead($reader)')];
        }

        // Empty elements leave the property unset, as before
        return [
            '$value = Func::readText($reader);',
            'if (\'\' !== $value) {',
            sprintf($store, $this->fromXmlExpr($kind, '$value')),
            '}',
        ];
    }

    private function addDeserialization(Generator\ClassGenerator $class, PHPClass $type)
    {
        if ($type->getMeta() === null) return;
        $isBase = $class->getExtendedClass() === null;
        $meta = $type->getMeta();
        $className = array_key_first($meta);
        $class->addUse(Func::class);

        $attributeCases = [];
        $elementCases = [];
        $listLines = [];
        foreach ($meta[$className]['properties'] ?? [] as $name => $property) {
            [$kind, $elementClass, $isArray] = $this->kindOf($property['type']);
            if (isset($property['xml_value']) && $property['xml_value']) {
                continue;
            }
            if (isset($property['xml_attribute']) && $property['xml_attribute']) {
                $attributeCases[$property['serialized_name']] = [
                    '$this->'.$name.' = '.$this->fromXmlExpr($kind, '$reader->value').';',
                ];
                continue;
            }
            $ns = $property['xml_element']['namespace'] ?? '';
            if (!$isArray) {
                $elementCases[$ns][$property['serialized_name']] = $this->readElementLines($kind, $elementClass, '$this->'.$name.' = %s;');
                continue;
            }
            // Lists missing from the document read as empty, as before
            $listLines[] = '$this->'.$name.' = [];';
            if ($property['xml_list']['inline']) {
                $entryNs = $property['xml_list']['namespace'] ?? $property['xml_element']['namespace'] ?? '';
                $elementCases[$entryNs][$property['xml_list']['entry_name']] = $this->readElementLines($kind, $elementClass, '$this->'.$name.'[] = %s;');
                continue;
            }
            $entryNs = $property['xml_list']['namespace'] ?? $property['xml_element']['namespace'] ?? '';
            if ('object' === $kind) {
                $read = 'static fn (\XMLReader $reader) => \\'.$elementClass.'::xmlRead($reader)';
            } else {
                $read = 'static function (\XMLReader $reader) {'.PHP_EOL
                    .'$value = Func::readText($reader);'.PHP_EOL
                    .'return \'\' !== $value ? '.$this->fromXmlExpr($kind, '$value').' : null;'.PHP_EOL
                    .'}';
            }
            $elementCases[$ns][$property['serialized_name']] = [
                '$this->'.$name.' = Func::readList($reader, '.var_export($property['xml_list']['entry_name'], true).', '.var_export($entryNs, true).', '.$read.');',
            ];
        }

        // Entry point for sabre (elementMap, parse()); generated code calls xmlRead() directly.
        $method = new MethodGenerator('xmlDeserialize');
        $method->setVisibility(MethodGenerator::VISIBILITY_PUBLIC);
        $method->setStatic(true);
        $method->setReturnType('mixed');
        $param = new ParameterGenerator('reader');
        $param->setType('\Sabre\Xml\Reader');
        $method->setParameter($param);
        $method->setBody('return self::xmlRead($reader);');
        $class->addMethodFromGenerator($method);

        $valueProperty = $this->valueProperty($type);
        $lines = [];
        $lines[] = '$self = new self('.(null !== $valueProperty ? 'null' : '').');';
        $lines[] = '$self->xmlInitLists();';
        if (null !== $valueProperty) {
            [$valueKind, , ] = $this->kindOf($valueProperty['type']);
            $lines[] = '$value = Func::readValue($reader, $self);';
            $lines[] = 'if (\'\' !== $value) {';
            $lines[] = '$self->value('.$this->fromXmlExpr($valueKind, '$value').');';
            $lines[] = '}';
        } else {
            $lines[] = 'Func::readObject($reader, $self);';
        }
        $lines[] = 'return $self;';
        $method = new MethodGenerator('xmlRead');
        $method->setVisibility(MethodGenerator::VISIBILITY_PUBLIC);
        $method->setStatic(true);
        $method->setReturnType($className);
        $param = new ParameterGenerator('reader');
        $param->setType('\XMLReader');
        $method->setParameter($param);
        $method->setDocBlock(new DocBlockGenerator('Reads the element the reader is positioned on and moves past its end.'));
        $method->setBody(implode(PHP_EOL, $lines));
        $class->addMethodFromGenerator($method);

        $lines = $isBase ? [] : ['parent::xmlInitLists();'];
        array_push($lines, ...$listLines);
        $method = new MethodGenerator('xmlInitLists');
        $method->setVisibility(MethodGenerator::VISIBILITY_PROTECTED);
        $method->setReturnType('void');
        $method->setBody(implode(PHP_EOL, $lines));
        $class->addMethodFromGenerator($method);

        $fallback = $isBase ? 'return false;' : 'return parent::%s($reader);';

        $lines = [];
        if ($attributeCases) {
            $lines[] = 'switch ($reader->localName) {';
            foreach ($attributeCases as $local => $caseLines) {
                $lines[] = 'case '.var_export($local, true).':';
                array_push($lines, ...$caseLines);
                $lines[] = 'return true;';
            }
            $lines[] = '}';
        }
        $lines[] = sprintf($fallback, 'xmlReadAttribute');
        $method = new MethodGenerator('xmlReadAttribute');
        $method->setVisibility(MethodGenerator::VISIBILITY_PUBLIC);
        $method->setReturnType('bool');
        $param = new ParameterGenerator('reader');
        $param->setType('\XMLReader');
        $method->setParameter($param);
        $method->setDocBlock(new DocBlockGenerator('Called by Func::readObject(): reads the attribute the reader is positioned on, if it belongs to this type.'));
        $method->setBody(implode(PHP_EOL, $lines));
        $class->addMethodFromGenerator($method);

        $lines = [];
        foreach ($elementCases as $ns => $cases) {
            $lines[] = 'if ('.var_export($ns, true).' === $reader->namespaceURI) {';
            $lines[] = 'switch ($reader->localName) {';
            foreach ($cases as $local => $caseLines) {
                $lines[] = 'case '.var_export($local, true).':';
                array_push($lines, ...$caseLines);
                $lines[] = 'return true;';
            }
            $lines[] = '}';
            $lines[] = '}';
        }
        $lines[] = sprintf($fallback, 'xmlReadElement');
        $method = new MethodGenerator('xmlReadElement');
        $method->setVisibility(MethodGenerator::VISIBILITY_PUBLIC);
        $method->setReturnType('bool');
        $param = new ParameterGenerator('reader');
        $param->setType('\XMLReader');
        $method->setParameter($param);
        $method->setDocBlock(new DocBlockGenerator('Called by Func::readObject(): reads the child element the reader is positioned on, if it belongs to this type, and moves past its end.'));
        $method->setBody(implode(PHP_EOL, $lines));
        $class->addMethodFromGenerator($method);

        if ($isBase) {
            $ifaces = $class->getImplementedInterfaces();
            $ifaces[] = '\Sabre\Xml\XmlDeserializable';
            $class->setImplementedInterfaces($ifaces);
        }
    }
}
