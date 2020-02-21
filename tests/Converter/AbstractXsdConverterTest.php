<?php
namespace GoetasWebservices\Xsd\XsdToPhp\Tests\Converter;

use GoetasWebservices\Xsd\XsdToPhp\Naming\ShortNamingStrategy;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class AbstractXsdConverterTest extends TestCase
{

    /**
     *
     * @var AbstractConverter
     */
    protected $converter;

    public function setUp(): void
    {
        $this->converter = $this->getMockForAbstractClass('GoetasWebservices\Xsd\XsdToPhp\AbstractConverter', [new ShortNamingStrategy()]);
    }

    public function testAliases()
    {
        $f = function () {
        };
        $this->converter->addAliasMap('http://www.example.com', "myType", $f);

        $handlers = Assert::readAttribute($this->converter, 'typeAliases');

        $this->assertArrayHasKey('http://www.example.com', $handlers);
        $this->assertArrayHasKey('myType', $exmpleHandlers = $handlers['http://www.example.com']);
        $this->assertSame($f, $exmpleHandlers['myType']);
    }

    public function testDefaultAliases()
    {
        $handlers = Assert::readAttribute($this->converter, 'typeAliases');

        $this->assertArrayHasKey('http://www.w3.org/2001/XMLSchema', $handlers);
        $defaultHandlers = $handlers['http://www.w3.org/2001/XMLSchema'];

        $this->assertArrayHasKey('int', $defaultHandlers);
        $this->assertArrayHasKey('integer', $defaultHandlers);
        $this->assertArrayHasKey('string', $defaultHandlers);
    }

    public function testNamespaces()
    {
        $this->converter->addNamespace('http://www.example.com', 'some\php\ns');

        $namespaces = Assert::readAttribute($this->converter, 'namespaces');

        $this->assertArrayHasKey('http://www.example.com', $namespaces);
        $this->assertEquals('some\php\ns', $namespaces['http://www.example.com']);
    }
}