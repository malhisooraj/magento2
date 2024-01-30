<?php
/**
 * Soorajmalhi
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category   Soorajmalhi
 * @package    Soorajmalhi_ProductReplacement
 * @copyright  Copyright (c) 2023 Soorajmalhi
 * @author     Sooraj Malhi <soorajmalhi@gmail.com
 */
namespace Soorajmalhi\ProductReplacement\Observer;

use Magento\Catalog\Model\Product\Type;
use Magento\Framework\Event\Observer;
use Magento\Catalog\Api\Data\ProductInterface;
use Soorajmalhi\ProductReplacement\Logger\Logger;
use Magento\Framework\Event\ObserverInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Soorajmalhi\ProductReplacement\Helper\Data as Helper;
use Magento\Bundle\Model\ResourceModel\Selection\Collection as SelectionCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollection;

/**
 * Class BundleSelectionPrepareAfterObserver
 * @package Soorajmalhi\ProductReplacement\Observer
 */
class BundleSelectionPrepareAfterObserver implements ObserverInterface
{
    /**
     * Constants
     */
    const ITEM_TYPE_CABINET              = 'CABINET',
        ITEM_TYPE_DOOR                   = 'DOOR';

    /**
     * @var ProductCollection
     */
    protected $productCollectionFactory;

    /*
     * @var Logger
     */
    protected $logger;

    /**
     * @var Helper
     */
    protected $helper;

    /**
     * Constructor
     * @param Logger $logger
     * @param Helper $helper
     * @param ProductCollection $productCollectionFactory
     */
    public function __construct(
        Logger $logger,
        Helper $helper,
        ProductCollection $productCollectionFactory
    ){
        $this->logger = $logger;
        $this->helper = $helper;
        $this->productCollectionFactory  = $productCollectionFactory;
    }

    /**
     * @event soorajmalhi_productreplacement_bundle_selection_prepare_after
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        if ($this->helper->isEnable()) {
            /** @var SelectionCollection $selections */
            $selections = $observer->getEvent()->getSelections();
            $buyRequest = $observer->getEvent()->getBuyRequest();
            $product = $observer->getEvent()->getParentProduct();

            if($selections->count()) {
                if ($product->getTypeId() == Type::TYPE_BUNDLE) {
                    $doorModel = $buyRequest->getDoorModel();
                    $doorColor = $buyRequest->getDoorColor();
                    $cabinetColor = $buyRequest->getCabinetColor();

                    foreach ($selections as $selection) {
                        $height = $selection->getHigh();
                        $width = $selection->getWidth();
                        $depth = $selection->getThick();
                        $family = $selection->getFamily();

                        /*
                         * Replace Door Item, if found
                         */
                        if ($selection->getComponentType() == self::ITEM_TYPE_DOOR) {
                            $findDoor = $this->findDoor($doorModel, $doorColor, $height, $width, $depth);

                            if($findDoor->getId()) {
                                $referenceProduct = $selections->getItemByColumnValue('entity_id', $selection->getId());
                                $this->replaceSelectionItem($selections, $buyRequest, $referenceProduct, $findDoor);
                            } else {
                                $this->logger->debug('Door not found against Selection sku ' . $selection->getSku());
                                throw new LocalizedException(__("Door not found."));
                            }
                        }

                        /*
                         * Replace Cabinet Item, if found
                         */
                        if ($selection->getComponentType() == self::ITEM_TYPE_CABINET) {
                            $findCabinet = $this->findCabinet($family, $cabinetColor, $height, $width, $depth);

                            if($findCabinet->getId()) {
                                $referenceProduct = $selections->getItemByColumnValue('entity_id', $selection->getId());
                                $this->replaceSelectionItem($selections, $buyRequest, $referenceProduct, $findCabinet);
                            } else {
                                $this->logger->debug('Cabinet not found against Selection sku ' . $selection->getSku());
                                throw new LocalizedException(__("Cabinet not found."));
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Replace Selection Item
     * @param $selections
     * @param $buyRequest
     * @param $referenceProduct
     * @param $replacedProduct
     */
    public function replaceSelectionItem($selections, $buyRequest, $referenceProduct, $replacedProduct)
    {
        $additionalData = $buyRequest->getAdditionalData();
        if(!is_null($referenceProduct)) {
            $missingData = array_diff_key($referenceProduct->debug(), $replacedProduct->debug());
            $replacedProduct->addData($missingData);
            $replacedProduct->setProductId($replacedProduct->getId());
            if (isset($buyRequest->getAdditionalData()[$referenceProduct->getSku()])) {
                $additionalData[$replacedProduct->getSku()] = $buyRequest->getAdditionalData()[$referenceProduct->getSku()];
                $buyRequest->setData('additional_data', $additionalData);
            }
            $selections->removeItemByKey($referenceProduct->getSelectionId());
            $selections->addItem($replacedProduct);
        }
    }

    /**
     * Find Door Product based on Door Model, Door Color and Size
     * @param $modelId
     * @param $colorId
     * @param $height
     * @param $width
     * @param $depth
     * @return ProductInterface
     */
    public function findDoor($modelId, $colorId, $height, $width, $depth)
    {
        $productCollection = $this->productCollectionFactory->create();
        $productCollection
            ->addAttributeToSelect(['name','sku','type'])
            ->addFieldToFilter('type_id', Type::TYPE_SIMPLE)
            ->addFieldToFilter('door_model', $modelId )
            ->addFieldToFilter('door_color', $colorId)
            ->addFieldToFilter('high', $height)
            ->addFieldToFilter('width', $width)
            ->addFieldToFilter('thick', $depth);

        return $productCollection->getFirstItem();
    }

    /**
     * Find Cabinet Product based on Family, Door Color and Size
     * @param $family
     * @param $cabinetColor
     * @param $height
     * @param $width
     * @param $depth
     * @return ProductInterface
     */
    public function findCabinet($family, $cabinetColor, $height, $width, $depth)
    {
        $productCollection = $this->productCollectionFactory->create();
        $productCollection
            ->addAttributeToSelect(['name','sku','type'])
            ->addFieldToFilter('type_id', Type::TYPE_SIMPLE)
            ->addFieldToFilter('family', $family)
            ->addFieldToFilter('module_color', $cabinetColor)
            ->addFieldToFilter('high', $height)
            ->addFieldToFilter('width', $width)
            ->addFieldToFilter('thick', $depth);

        return $productCollection->getFirstItem();
    }
}
