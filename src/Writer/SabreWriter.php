<?php
namespace GoetasWebservices\Xsd\XsdToPhp\Writer;

use GoetasWebservices\Xsd\XsdToPhp\Jms\PathGenerator\PathGenerator;
use Laminas\Code\Generator\ClassGenerator;
use Laminas\Code\Generator\MethodGenerator;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class SabreWriter extends Writer implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    /**
     * @var PathGenerator
     */
    private $pathGenerator;
    private $classWriter;

    public function __construct(PathGenerator $pathGenerator, PHPClassWriter $classWriter, LoggerInterface $logger = null)
    {
        $this->pathGenerator = $pathGenerator;
        $this->classWriter = $classWriter;
        $this->logger = $logger ?: new NullLogger();
    }

    public function write(array $items, bool $noSabre = false)
    {
        $destinations_php = $this->config['destinations_php'];
        $jmsPaths = $this->config['destinations_jms'];
        $classGen = new ClassGenerator();
        $classGen->setName(basename($destinations_php[array_key_first($destinations_php)]) . 'ClassMap');
        $classGen->setNamespaceName(array_key_first($jmsPaths) . "\\Client");
        $classGen->addUse('\Sabre\Xml\Writer');
        $maps = [];
        /*foreach ($items as $item) {
            $class = array_key_first($item);
            $methodLines = [];
            $methodLines[] = 'function(Writer $writer, $elem) {';
            $methodLines[] = 'self::CheckParent($writer, $elem, \''.$class.'\');';
            if (isset($item[$class]['virtual_properties'])) {
                foreach ($item[$class]['virtual_properties'] as $property) {
                    if (isset($property['xml_attribute']) && $property['xml_attribute']) {
                        $methodLines[] = '$writer->writeAttribute("'.$property['serialized_name'].'", '.$property['exp'].');';
                    }
                }
            }
            if (isset($item[$class]['properties'])) {
                foreach ($item[$class]['properties'] as $property) {
                    $isBool = $property['type'] === 'bool';
                    $methodLines[] = '$value = $elem->'.$property['accessor']['getter'].'();';
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
                        $methodLines[] = 'if (null !== $value && !empty($elem->'.$property['accessor']['getter'] . '()))';
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
            $methodLines[] = '},';
            $maps[] = '\''.$class.'\' => '.implode(PHP_EOL, $methodLines);
            $this->logger->debug(sprintf("Created Sabre metadata %s", $class));
        }*/
        $maps[] = '\'Time\' => function(Writer $writer, $elem) {$value = $elem->format(\'H:i:s\');if ($elem->getTimezone()->getOffset($elem) !== (new \DateTimeZone(\'UTC\'))->getOffset($elem)) $value .= $date->format(\'P\');$writer->write($value);},';
        $maps[] = '\'DateTime\' => function(Writer $writer, $elem) {$writer->write($elem->format(\DateTime::W3C));},';
        $maps[] = '\'Date\' => function(Writer $writer, $elem) {$writer->write($elem->format(\'Y-m-d\'));},';
        $method = new MethodGenerator('Get');
        $method->setStatic(true);
        $method->setBody('return ['.implode(PHP_EOL, $maps).'];');
        $classGen->addMethodFromGenerator($method);

        $maps = [];
        foreach ($items as $item) {
            $class = array_key_first($item);
            if (isset($item[$class]['xml_root_name'])) {
                $ns = '{'.$item[$class]['xml_root_namespace'].'}';
                $entry = $ns.$item[$class]['xml_root_name'];
                $maps[] = '\''.$entry.'\' => \''.$class.'\',';
            }
        }
        $method = new MethodGenerator('GetElements');
        $method->setStatic(true);
        $method->setBody('return ['.implode(PHP_EOL, $maps).'];');
        $classGen->addMethodFromGenerator($method);

        $method = new MethodGenerator('GetNamespaces');
        $method->setStatic(true);
        $method->setBody('return ['.implode(PHP_EOL, array_map(function($k) { return '\''.$k.'\' => \'\','; }, array_keys($this->config['namespaces']))).'];');
        $classGen->addMethodFromGenerator($method);

        /*$method = new MethodGenerator('CheckParent');
        $method->setStatic(true);
        $method->setVisibility(MethodGenerator::VISIBILITY_PRIVATE);
        $params = [];
        $param = new ParameterGenerator('writer');
        $param->setType('\Sabre\Xml\Writer');
        $params[] = $param;
        $param = new ParameterGenerator('elem');
        $params[] = $param;
        $param = new ParameterGenerator('target_class');
        $param->setType('string');
        $params[] = $param;
        $method->setParameters($params);
        $methodLines = [];
        $methodLines[] = '$elem_class = get_class($elem);';
        $methodLines[] = 'while ($elem_class && $elem_class !== $target_class)';
        $methodLines[] = '$elem_class = get_parent_class($elem_class);';
        $methodLines[] = 'if ($parent_class = get_parent_class($elem_class))';
        $methodLines[] = '$writer->classMap[$parent_class]($writer, $elem);';
        $method->setBody(implode(PHP_EOL, $methodLines));
        $classGen->addMethodFromGenerator($method);*/

        $this->classWriter->write([$classGen]);
    }
}
