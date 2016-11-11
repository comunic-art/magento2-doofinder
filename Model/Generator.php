<?php

namespace Comunicart\Doofinder\Model;

use Magento\Catalog\Model\Product\Visibility;
use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filter\StripTags;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Magento\Framework\Filesystem\Io\File as FileIo;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Catalog\Helper\Image as ImageHelper;

class Generator {
    protected $output;
    protected $input;
    protected $store;
    protected $progressBar;
    protected $fileResource;
    protected $csvSeparator = '|';
    protected $csvEnclosure = '"';
    protected $attributeOptions = [];
    protected $entityTypeId;

    public function __construct(
        StoreManagerInterface $storeManager,
        CollectionFactory $collectionFactory,
        StockHelper $stockHelper,
        DirectoryList $directoryList,
        FileIo $fileIo,
        FileDriver $fileDriver,
        StripTags $stripTags,
        AttributeOptionManagementInterface $attributeOpionManager,
        EavConfig $eavConfig,
        ImageHelper $imageHelper
    )
    {
        $this->storeManager = $storeManager;
        $this->collectionFactory = $collectionFactory;
        $this->stockHelper = $stockHelper;
        $this->directoryList = $directoryList;
        $this->fileIo = $fileIo;
        $this->fileDriver = $fileDriver;
        $this->stripTags = $stripTags;
        $this->attributeOptionManager = $attributeOpionManager;
        $this->eavConfig = $eavConfig;
        $this->imageHelper = $imageHelper;
    }

    public function setOutput($output) {
        $this->output = $output;
    }

    public function setInput($input) {
        $this->input = $input;
    }

    public function getOutput() {
        return $this->output;
    }

    public function getInput() {
        return $this->input;
    }

    public function setStore($store) {
        $this->store = $store;
        $this->storeManager->setCurrentStore($store->getId());
    }

    public function getStore() {
        return $this->store;
    }

    private function setProgressBar($progressBar) {
        $this->progressBar = $progressBar;
    }

    private function getProgressBar() {
        return $this->progressBar;
    }

    public function generate($store = null) {
        if (! $store) {
            $store = $this->storeManager->getStore();
        }

        $this->setStore($store);

        $collection = $this->getCollection();
        $size = $collection->count();
        if ($this->getOutput()) {
            $this->progressStart();
            $this->progressBarCreate($size);
        }

        if ($size) {
            $this->openCsvFile();
            $this->putCsvHeader();
            foreach ($collection as $item) {
                $this->putCsvProductRow($item);
            }

            if ($this->getOutput()) {
                $this->closeCsvFile();
                $this->progressFinish();
            }
        }
    }

    protected function putCsvProductRow($item) {
        $product = $item->load($item->getId());

        $row = [];
        $fields = $this->getCsvFields();
        foreach ($fields as $field) {
            $row[$field] = '';
        }

        // Title
        $row['title'] = $product->getName();

        // Link
        $row['link'] = $product->getProductUrl(false);

        // Description
        $shortDescription = $product->getShortDescription();
        $description = $product->getDescription();
        $description = $shortDescription ? $shortDescription : $description;
        $description = $this->stripTags->filter($description);
        $description  = preg_replace('/\s+/m', ' ', $description);
        $row['description'] = $description;

        // ID
        $row['id'] = $product->getSku();

        // Price
        $price = $product->getPrice();
        $finalPrice = $product->getFinalPrice() > 0.0 ? $product->getFinalPrice() : null;
        $specialPrice = $product->getSpecialPrice();
        $specialPriceFromDate = strtotime($product->getSpecialFromDate());
        $specialPriceToDate = $product->getSpecialToDate() ? strtotime($product->getSpecialToDate()) : time() + 60*60*24*7;

        if ($product->getTypeId() == 'configurable') {
            $children = $product->getTypeInstance()->getUsedProducts($product);
            if ($children) {
                foreach ($children as $child) {
                    $child = $child->load($child->getId());

                    if (! isset($finalPrice) || $child->getFinalPrice() < $finalPrice) {
                        $finalPrice = $child->getFinalPrice();
                        $price = $child->getPrice();
                        $specialPrice = $child->getSpecialPrice();
                        $specialPriceFromDate = strtotime($child->getSpecialFromDate());
                        $specialPriceToDate = $child->getSpecialToDate() ? strtotime($child->getSpecialToDate()) : time() + 60*60*24*7;
                    }
                }
            }
        }

        $row['price'] = $price;

        // Sale Price
        if ($specialPrice > 0.0 && $specialPrice < $row['price']) {
            $row['sale price'] = $specialPrice;
            $row['sale price effective date'] = date('c', $specialPriceFromDate) . '/' . date('c', $specialPriceToDate);
        }

        // Image link
        $imagePath = null;
        $imageId = null;
        foreach ($product->getMediaGalleryEntries() as $entry) {
            $types = $entry->getTypes();
            if (in_array('thumbnail', $types) || ! $imageId) {
                $imageId = $entry->getId();
            }
        }

        if ($imageId) {
            foreach ($product->getMediaGalleryImages() as $image) {
                if ($image->getId() == $imageId) {
                    $imagePath = $image->getFile();
                }
            }
        }

        if ($imagePath) {
            $row['image link'] = $this->imageHelper
                ->init($product, 'category_page_grid')
                ->setImageFile($imagePath)
                ->getUrl();
        }

        // Brand
        /*$brandAttributeCode = 'brand';
        $brand = $product->getData($brandAttributeCode);
        if ($brand) {
            if (! isset($this->entityTypeId)) {
                $this->entityTypeId = $this->eavConfig->getEntityType(\Magento\Catalog\Model\Product::ENTITY)->getId();
            }
            if (! isset($this->attributeOptions[$brandAttributeCode])) {
                $this->attributeOptions[$brandAttributeCode] = [];
                $options = $this->attributeOptionManager->getItems($this->entityTypeId, $brandAttributeCode);
                if ($options) {
                    foreach ($options as $option) {
                        $labels = $option->getStoreLabels();
                        if ($labels) {
                            foreach ($labels as $label) {
                                if ($label->getStoreId() == $this->getStore()->getId()) {
                                    $this->attributeOptions[$brandAttributeCode][$option->getValue()] = $label->getLabel();
                                }
                            }
                        }
                        if (! isset($this->attributeOptions[$brandAttributeCode][$option->getValue()])) {
                            $this->attributeOptions[$brandAttributeCode][$option->getValue()] = $option->getLabel();
                        }
                    }
                }
            }

            $row['brand'] = $this->attributeOptions[$brandAttributeCode][$brand];
        }*/

        // Product type // Categories
        $categoriesCollection = $product->getCategoryCollection();
        $categoriesArray = [];
        $i = 0;
        foreach ($categoriesCollection as $category) {
            $category = $category->load($category->getId());
            $categoryPath = explode("/", $category->getPath());
            $categoryParents = [
                $category->getId() => $category->getName(),
            ];
            foreach ($category->getParentCategories() as $parent) {
                $categoryParents[$parent->getId()] = $parent->getName();
            }

            foreach ($categoryPath as $categoryPathItem) {
                if (isset($categoryParents[$categoryPathItem])) {
                    $categoriesArray[$i][] = $categoryParents[$categoryPathItem];
                }
            }
            $i++;
        }
        $categories = [];
        foreach ($categoriesArray as $catgoriesArrayItem) {
            $categories[] = implode(" > ", $catgoriesArrayItem);
        }
        $row['product type'] = implode(" %% ", $categories);

        $this->putCsvRow($row);

        if ($this->getOutput()) {
            $this->progressAdvance();
        }
    }

    protected function progressStart() {
        $output = $this->getOutput();
        $output->writeln('');
        $output->writeln(sprintf('<info>Generating Doofinder CSV data feed file for store view %s...</info>', $this->getStore()->getName()));
        $output->writeln('');
    }

    protected function progressAdvance() {
        $this->getProgressBar()->advance();
    }

    protected function progressFinish() {
        $this->getProgressBar()->finish();
        $this->getOutput()->writeln('');
    }

    protected function progressBarCreate($size) {
        $progressBar = new \Symfony\Component\Console\Helper\ProgressBar($this->getOutput(), $size);
        $progressBar->setFormat(
            '%current%/%max% [%bar%] %percent:3s%% %elapsed% %memory:6s%'
        );
        $progressBar->start();
        $progressBar->display();

        $this->setProgressBar($progressBar);
    }

    protected function getCollection() {
        $collection = $this->collectionFactory->create();
        $collection->addFinalPrice();
        $collection->addMinimalPrice();
        $collection->setVisibility([Visibility::VISIBILITY_BOTH, Visibility::VISIBILITY_IN_SEARCH]);
        $collection->addStoreFilter($this->getStore());
        $collection->applyFrontendPriceLimitations();
        $this->stockHelper->addInStockFilterToCollection($collection);
        $collection->load();
        $collection->addCategoryIds();
        $collection->addUrlRewrite();

        return $collection;
    }

    protected function getCsvFields() {
        return [
            'title',
            'link',
            'description',
            'id',
            'price',
            'image link',
            //'brand',
            'product type',
            'additional image link',
            'sale price',
            'sale price effective date'
        ];
    }

    protected function openCsvFile() {
        if (! $this->fileResource) {
            $path = $this->directoryList->getPath('media') . '/doofinder/' . $this->getStore()->getCode() . '.csv';
            $this->fileIo->setAllowCreateFolders(true);
            $this->fileIo->createDestinationDir($this->fileIo->dirname($path));
            $this->fileResource = $this->fileDriver->fileOpen($path, 'w');
        }

        return $this->fileResource;
    }

    protected function putCsvHeader() {
        $this->putCsvRow($this->getCsvFields());
    }

    protected function putCsvRow($row) {
        $this->fileDriver->filePutCsv($this->fileResource, $row, $this->csvSeparator, $this->csvEnclosure);
    }

    protected function closeCsvFile() {
        $this->fileDriver->fileClose($this->fileResource);
        $this->fileResource = null;
    }
}