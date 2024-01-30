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

namespace Soorajmalhi\ProductReplacement\Logger;

use Monolog\Logger;

class Handler extends \Magento\Framework\Logger\Handler\Base
{
    protected $loggerType = Logger::DEBUG;

    protected $fileName   = '/var/log/product_replacement.log';
}
