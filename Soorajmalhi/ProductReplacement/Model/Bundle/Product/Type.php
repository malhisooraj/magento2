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
namespace Soorajmalhi\ProductReplacement\Model\Bundle\Product;

use Magento\Bundle\Model\Option;
use Magento\Framework\Stdlib\ArrayUtils;
use Magento\Framework\App\ObjectManager;
use Magento\Bundle\Model\Product\Type as BundleType;
use Magento\Bundle\Model\ResourceModel\Option\Collection;

/**
 * Class Type
 * @package Soorajmalhi\ProductReplacement\Model\Bundle\Product
 */
class Type extends BundleType
{
    /**
     * @var ArrayUtils
     */
    private $arrayUtility;

    /**
     * @return ArrayUtils
     */
    public function getArrayUtilities()
    {
        if(!$this->arrayUtility) {
            $this->arrayUtility = ObjectManager::getInstance()->get(ArrayUtils::class);
        }

        return $this->arrayUtility;
    }

    /**
     * Prepare product and its configuration to be added to some products list.
     *
     * Perform standard preparation process and then prepare of bundle selections options.
     *
     * @param \Magento\Framework\DataObject $buyRequest
     * @param \Magento\Catalog\Model\Product $product
     * @param string $processMode
     * @return \Magento\Framework\Phrase|array|string
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function _prepareProduct(\Magento\Framework\DataObject $buyRequest, $product, $processMode)
    {
        $result = \Magento\Catalog\Model\Product\Type\AbstractType::_prepareProduct($buyRequest, $product, $processMode);

        try {
            if (is_string($result)) {
                throw new \Magento\Framework\Exception\LocalizedException(__($result));
            }

            $selections = [];
            $isStrictProcessMode = $this->_isStrictProcessMode($processMode);

            $skipSaleableCheck = $this->_catalogProduct->getSkipSaleableCheck();
            $_appendAllSelections = (bool)$product->getSkipCheckRequiredOption() || $skipSaleableCheck;

            $options = $buyRequest->getBundleOption();

            if (is_array($options)) {
                $options = $this->recursiveIntval($options);
                $optionIds = array_keys($options);

                if (empty($optionIds) && $isStrictProcessMode) {
                    throw new \Magento\Framework\Exception\LocalizedException(__('Please specify product option(s).'));
                }

                $product->getTypeInstance()
                    ->setStoreFilter($product->getStoreId(), $product);
                $optionsCollection = $this->getOptionsCollection($product);
                $this->checkIsAllRequiredOptions(
                    $product,
                    $isStrictProcessMode,
                    $optionsCollection,
                    $options
                );

                $this->validateRadioAndSelectOptions(
                    $optionsCollection,
                    $options
                );

                $selectionIds = array_values($this->getArrayUtilities()->flatten($options));
                // If product has not been configured yet then $selections array should be empty
                if (!empty($selectionIds)) {
                    $selections = $this->getSelectionsByIds($selectionIds, $product);

                    if (count($selections->getItems()) !== count($selectionIds)) {
                        throw new \Magento\Framework\Exception\LocalizedException(
                            __('The options you selected are not available.')
                        );
                    }

                    // Dispatch Product Replacement Event
                    $this->dispatchProductReplacement($selections, $product, $buyRequest);

                    // Check if added selections are still on sale
                    $this->checkSelectionsIsSale(
                        $selections,
                        $skipSaleableCheck,
                        $optionsCollection,
                        $options
                    );

                    $optionsCollection->appendSelections($selections, true, $_appendAllSelections);

                    $selections = $selections->getItems();
                } else {
                    $selections = [];
                }
            } else {
                $product->setOptionsValidationFail(true);
                $product->getTypeInstance()
                    ->setStoreFilter($product->getStoreId(), $product);

                $optionCollection = $product->getTypeInstance()
                    ->getOptionsCollection($product);
                $optionIds = $product->getTypeInstance()
                    ->getOptionsIds($product);
                $selectionCollection = $product->getTypeInstance()
                    ->getSelectionsCollection($optionIds, $product);

                // Dispatch Product Replacement Event
                $this->dispatchProductReplacement($selections, $product, $buyRequest);

                $options = $optionCollection->appendSelections($selectionCollection, true, $_appendAllSelections);
                $selections = $this->mergeSelectionsWithOptions($options, $selections);
            }

            if ((is_array($selections) && count($selections) > 0) || !$isStrictProcessMode) {
                $uniqueKey = [$product->getId()];
                $selectionIds = [];
                $qtys = $buyRequest->getBundleOptionQty();
                $additionalData = $buyRequest->getAdditionalData();

                // Shuffle selection array by selection sortOrder
                usort($selections, [$this, 'shakeSelectionsBySortOrder']);

                foreach ($selections as $selection) {
                    $selectionOptionId = $selection->getOptionId();
                    $qty = isset($additionalData[$selection->getSku()][$selection->getSelectionId()]['component_qty']) ?
                            $additionalData[$selection->getSku()][$selection->getSelectionId()]['component_qty'] :
                            $this->getQty($selection, $qtys, $selectionOptionId);

                    $selectionId = $selection->getSelectionId();
                    $product->addCustomOption('selection_qty_' . $selectionId, $qty, $selection);
                    $selection->addCustomOption('selection_id', $selectionId);
                    $product->addCustomOption('product_qty_' . $selection->getId(), $qty, $selection);

                    /*
                     * Create extra attributes that will be converted to product options in order item
                     * for selection (not for all bundle)
                     */
                    $price = $product->getPriceModel()
                        ->getSelectionFinalTotalPrice($product, $selection, 0, 1);
                    $attributes = [
                        'price' => $price,
                        'qty' => $qty,
                        'option_label' => $selection->getOption()
                            ->getTitle(),
                        'option_id' => $selection->getOption()
                            ->getId(),
                    ];

                    $_result = $selection->getTypeInstance()
                        ->prepareForCart($buyRequest, $selection);
                    $this->checkIsResult($_result);

                    $result[] = $_result[0]->setParentProductId($product->getId())
                        ->addCustomOption(
                            'bundle_option_ids',
                            $this->serializer->serialize(array_map('intval', $optionIds))
                        )
                        ->addCustomOption(
                            'bundle_selection_attributes',
                            $this->serializer->serialize($attributes)
                        );

                    if ($isStrictProcessMode) {
                        $_result[0]->setCartQty($qty);
                    }

                    $resultSelectionId = $_result[0]->getSelectionId();
                    $selectionIds[] = $resultSelectionId;
                    $uniqueKey[] = $resultSelectionId;
                    $uniqueKey[] = $qty;
                }

                // "unique" key for bundle selection and add it to selections and bundle for selections
                $uniqueKey = implode('_', $uniqueKey);
                foreach ($result as $item) {
                    $item->addCustomOption('bundle_identity', $uniqueKey);
                }
                $product->addCustomOption(
                    'bundle_option_ids',
                    $this->serializer->serialize(
                        array_map('intval', $optionIds)
                    )
                );
                $product->addCustomOption('bundle_selection_ids', $this->serializer->serialize($selectionIds));

                return $result;
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            return $e->getMessage();
        }

        return $this->getSpecifyOptionMessage();
    }

    /**
     * Sort selections method for usort function
     *
     * Sort selections by selection sortOrder and selection id
     *
     * @param  \Magento\Catalog\Model\Product $firstItem
     * @param  \Magento\Catalog\Model\Product $secondItem
     * @return int
     */
    public function shakeSelectionsBySortOrder($firstItem, $secondItem)
    {
        $aPosition = [
            $firstItem->getSortOrder()
        ];
        $bPosition = [
            $secondItem->getSortOrder()
        ];

        return $aPosition >= $bPosition;
    }

    /**
     * Cast array values to int
     *
     * @param array $array
     * @return int[]|int[][]
     */
    private function recursiveIntval(array $array)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->recursiveIntval($value);
            } elseif (is_numeric($value) && (int)$value != 0) {
                $array[$key] = (int)$value;
            } else {
                unset($array[$key]);
            }
        }

        return $array;
    }

    /**
     * Validate Options for Radio and Select input types
     *
     * @param Collection $optionsCollection
     * @param int[] $options
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function validateRadioAndSelectOptions($optionsCollection, $options)
    {
        $errorTypes = [];

        if (is_array($optionsCollection->getItems())) {
            foreach ($optionsCollection->getItems() as $option) {
                if ($this->isSelectedOptionValid($option, $options)) {
                    $errorTypes[] = $option->getType();
                }
            }
        }

        if (!empty($errorTypes)) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __(
                    'Option type (%types) should have only one element.',
                    ['types' => implode(", ", $errorTypes)]
                )
            );
        }
    }

    /**
     * Check if selected option is valid
     *
     * @param Option $option
     * @param array $options
     * @return bool
     */
    private function isSelectedOptionValid($option, $options)
    {
        return (
            ($option->getType() == 'radio' || $option->getType() == 'select') &&
            isset($options[$option->getOptionId()]) &&
            is_array($options[$option->getOptionId()]) &&
            count($options[$option->getOptionId()]) > 1
        );
    }

    /**
     * Dispatch Product Replacement Event
     * @param $selections
     * @param $product
     * @param $buyRequest
     * @return void
     */
    public function dispatchProductReplacement($selections, $product, $buyRequest)
    {
        $this->_eventManager->dispatch(
            'soorajmalhi_productreplacement_bundle_selection_prepare_after',
            ['selections' => $selections, 'parent_product' => $product, 'buy_request' => $buyRequest]
        );
    }
}
