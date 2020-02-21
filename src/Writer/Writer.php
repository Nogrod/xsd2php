<?php
namespace GoetasWebservices\Xsd\XsdToPhp\Writer;

abstract class Writer
{
    protected $config;
    public abstract function write(array $items);
    public function setConfig(array $config) {
        $this->config = $config;
    }
}