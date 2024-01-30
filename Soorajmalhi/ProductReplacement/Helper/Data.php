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
namespace Soorajmalhi\ProductReplacement\Helper;

use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Class Data
 * @package Soorajmalhi\ProductReplacement\Helper
 */
class Data extends AbstractHelper
{
    /**
     * Constant Module Enable
     */
    const   MODULE_ENABLE = 'product_replacement/general/enable';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;


    /**
     * Data Constructor
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        parent::__construct($context);
    }

    /**
     * @param $field
     * @return mixed
     */
    public function getConfigValue($field)
    {
        return $this->scopeConfig->getValue(
            $field,
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getId()
        );
    }

    /**
     * Check if module is enabled
     * @return bool
     */
    public function isEnable()
    {
        return (bool) $this->getConfigValue(self::MODULE_ENABLE);
    }
}
