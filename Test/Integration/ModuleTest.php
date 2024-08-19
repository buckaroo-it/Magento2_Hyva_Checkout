<?php declare(strict_types=1);


use Magento\Framework\App\ObjectManager;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Module\ModuleListInterface;
use PHPUnit\Framework\TestCase;

class ModuleTest extends TestCase
{
    public function testIfModuleIsRegistered()
    {
        $componentRegistrar = ObjectManager::getInstance()->get(ComponentRegistrar::class);
        $modulePath = $componentRegistrar->getPath('module', 'Buckaroo_HyvaCheckout');
        $this->assertTrue(is_dir($modulePath));
    }

    public function testIfModuleIsEnabled()
    {
        $moduleList = ObjectManager::getInstance()->get(ModuleListInterface::class);
        $module = $moduleList->getOne('Buckaroo_HyvaCheckout');
        $this->assertNotEmpty($module);
    }
}
