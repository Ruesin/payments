<?php
namespace Ruesin\Payments\Lib;

abstract class PayBase
{
    abstract function getPayForm($order,$params);
    abstract function notify();
    abstract function back();
    
    protected function setConfig($config = [])
    {
        $this->config = $config;
    }
}