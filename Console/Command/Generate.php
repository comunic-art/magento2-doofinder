<?php

namespace Comunicart\Doofinder\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\ObjectManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Generate extends \Symfony\Component\Console\Command\Command {
    public function __construct(ObjectManagerInterface $objectManager) {
        $this->objectManager = $objectManager;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('doofinder:generate');
        $this->setDescription('Generate CSV data feed file for Doofinder.');
        parent::configure();
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $output->setDecorated(true);

        $areaCode = Area::AREA_FRONTEND;
        $appState = $this->objectManager->get('Magento\Framework\App\State');
        $appState->setAreaCode($areaCode);
        $configLoader = $this->objectManager->get('Magento\Framework\ObjectManager\ConfigLoaderInterface');
        $this->objectManager->configure($configLoader->load($areaCode));

        $storeManager = $this->objectManager->get('Magento\Store\Model\StoreManagerInterface');
        $stores = $storeManager->getStores();
        foreach ($stores as $store) {
            if ($store->isActive()) {
                $this->objectManager->get('Comunicart\Doofinder\Model\Generator')->setOutput($output);
                $this->objectManager->get('Comunicart\Doofinder\Model\Generator')->setInput($input);
                $this->objectManager->get('Comunicart\Doofinder\Model\Generator')->generate($store);
            }
        }
    }
}

