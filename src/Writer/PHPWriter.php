<?php
namespace GoetasWebservices\Xsd\XsdToPhp\Writer;

use GoetasWebservices\Xsd\XsdToPhp\Php\ClassGenerator;
use GoetasWebservices\Xsd\XsdToPhp\Php\Structure\PHPClass;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class PHPWriter extends Writer implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected $classWriter;
    private $generator;

    public function __construct(PHPClassWriter $classWriter, ClassGenerator $generator, LoggerInterface $logger = null)
    {
        $this->generator = $generator;
        $this->classWriter = $classWriter;
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * @param PHPClass[] $items
     */
    public function write(array $items)
    {
        $classes = [];
        while($item = array_pop($items)) {
            $this->logger->debug(sprintf("Generate PHP class %s", $item->getName()));
            if($generator = $this->generator->generate($item)) {
                $classes[] = $generator;
            }
        }
        $this->classWriter->write($classes);
    }
}
