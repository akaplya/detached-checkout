<?php

namespace CommerceOptimizer\Checkout\Plugin\Catalog\Model;

use Magento\Catalog\Helper\Product as Subject;
class Helper
{
    public function afterGetSkipSaleableCheck(Subject $subject, $result) {
        return true;
    }
}
