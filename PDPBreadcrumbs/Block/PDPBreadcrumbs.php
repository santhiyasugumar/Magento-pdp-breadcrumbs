<?php
/**
 * Copyright © EAdesign by Eco Active S.R.L.,All rights reserved.
 * See LICENSE for license details.
 */
namespace Nits\PDPBreadcrumbs\Block;

use Magento\Catalog\Helper\Data;
use Magento\Framework\View\Element\Template\Context;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Registry;
use Magento\Framework\Api\AttributeValue;
use Nits\PDPBreadcrumbs\Helper\Data as BreadcrumbsData;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ProductCategoryList;
use Magento\Store\Model\StoreManagerInterface;

class PDPBreadcrumbs extends \Magento\Framework\View\Element\Template
{
    /**
     * Catalog data
     *
     * @var Data
     */
    private $catalogData = null;
    private $registry;
    private $categoryCollection;
    private $breadcrumbsData;
    public $bad_categories;
    public $enabled;

    /**
     * @param Context $context
     * @param Data $catalogData
     * @param array $data
     */
    public function __construct(
        Context $context,
        Data $catalogData,
        Registry $registry,
        BreadcrumbsData $breadcrumbsData,
        CollectionFactory $categoryCollection,
        CategoryFactory $categoryFactory,
        ProductCategoryList $productCategory,
        \Magento\Catalog\Model\CategoryRepository $categoryRepository,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->catalogData = $catalogData;
        $this->registry = $registry;
        $this->breadcrumbsData = $breadcrumbsData;
        $this->categoryCollection = $categoryCollection;
        $this->categoryFactory = $categoryFactory;
        $this->productCategory = $productCategory;
        $this->categoryRepository = $categoryRepository;
        $this->storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    public function getBadCategories()
    {
        $bad_categories = $this->breadcrumbsData->hasConfig('section_pdpbreadcrumbs/group__pdpbreadcrumbs/bad_categories');
        if(!is_null($bad_categories)) {
            return explode(',', str_replace(' ', '', $bad_categories));
        }
        
    }

    public function isEnable()
    {
        return $this->breadcrumbsData->hasConfig('section_pdpbreadcrumbs/group__pdpbreadcrumbs/enabled');
    }

    public function getProduct()
    {
        return $this->registry->registry('current_product');
    }

    public function getCategoryProductIds($product)
    {
        /** @var  $categoryIds  AttributeValue */
        $categoryIds = $product->getCategoryIds();
        return $categoryIds;
    }

    public function getFilteredCollection($categoryIds)
    {
        $collection = $this->categoryCollection->create();
       
        $categories = $this->categoryCollection->create();   
        $categories->addAttributeToSelect('*');     
        $categories->addFieldToFilter(
            'entity_id',
            ['in' => $categoryIds]
        );
        $catpath = $categories->getColumnValues('path');
        $level = $categories->getColumnValues('level');
        
        $websiteId = 1;
        $storeId = $this->storeManager->getWebsite($websiteId)->getDefaultStore()->getId();
        $rootNodeId = $this->storeManager->getStore($storeId)->getRootCategoryId();
        
        // algorithm start to find the first category 
        $minlevel = (int)$rootNodeId + 1;
        $minarray = array();
        $tmparray = array();
        $count = 0;
        for($i=$minlevel;$i<=max($level);$i++) {
            $minarray = array();
            for($j=0; $j<sizeof($catpath); $j++) {
                unset($tmparray);
                $tmparray = explode("/", $catpath[$j]);
                if(sizeof($tmparray) > $i) {
                    array_push($minarray, $tmparray[$i]);
                } 
            }
           
            sort($minarray);
            $minimumData = $minarray[0];
            
            for($k=0; $k<sizeof($catpath); $k++) {
                unset($tmparray);
                $tmparray = explode("/", $catpath[$k]);
                if(!in_array($minimumData, $tmparray)) {
                    array_splice($catpath, $k, 1);
                    $k=0;
                }
            }
        }
    
        $finalresults = array();
        if(sizeof($catpath) > 0) {
            $result = explode("/", $catpath[0]);
            for($m=0;$m<sizeof($result);$m++) {
                if(in_array($result[$m] , $categoryIds)) {
                    array_push($finalresults,$result[$m]);
                }
            }
            $categoryIds = $finalresults;
        }
        //  algorithm end to find the first category 
       
        $filtered_colection = $collection
            ->addFieldToSelect('*')
            ->addFieldToFilter(
                'entity_id',
                ['in' => $categoryIds]
            )
            ->setOrder('level', 'ASC')
            ->load();
           
        return $filtered_colection;
    }

    public function getCurrentCategory()
    {
        return $this->_registry->registry('current_category');
    }


    public function getCategories($filtered_colection, $badCategories)
    {
        $separator = ' <span class="breadcrumbsseparator"></span> ';
        $categories = '';
        $cars = [];
        foreach ($filtered_colection as $categoriesData) { 
            $categories .= '<a href="' . $categoriesData->getUrl() . '">';
            $categories .= $categoriesData->getData('name') . '</a>' . $separator;
        }
   
        return $categories;
    }

    public function getProductBreadcrumbs()
    {
        if ($this->isEnable()) {
            $separator = ' <span class="breadcrumbsseparator"></span> ';
            $product = $this->getProduct();
           
            $categoryIds = $this->getCategoryProductIds($product);

            $filtered_colection = $this->getFilteredCollection($categoryIds);
           
            $badCategories = $this->getBadCategories();

            $categories = $this->getCategories($filtered_colection, $badCategories);
         
            $home_url = '<a href="' . $this->_storeManager->getStore()->getBaseUrl() . '">Home</a>';
            return $home_url . $separator . $categories . '<span>' . $product->getName() . '</span>';
        }
    }

    public function getCategory($categoryId)
    {
        $this->category = $this->categoryFactory->create();
        $this->category->load($categoryId);
        return $this->category;
    }
}
