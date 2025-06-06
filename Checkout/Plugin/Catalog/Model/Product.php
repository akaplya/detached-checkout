<?php

namespace CommerceOptimizer\Checkout\Plugin\Catalog\Model;

use Magento\Catalog\Model\Product as Subject;

class Product
{
    public function aroundLoad(Subject $subject, \Closure $proceed, $id)
    {
        return $subject;
    }
}
