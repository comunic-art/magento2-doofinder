<?php

namespace Comunicart\Doofinder\Cron;

class Generator {
    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Comunicart\Doofinder\Model\Generator $generator
    ) {
        $this->storeManager = $storeManager;
        $this->generator = $generator;
    }
    
    public function generate()
    {
        $stores = $this->storeManager->getStores();
        foreach ($stores as $store) {
            if ($store->isActive()) {
                $this->generator->generate($store);
            }
        }
    }
}

