<?php
/**
 * GoMage Advanced Navigation Extension
 *
 * @category     Extension
 * @copyright    Copyright (c) 2010-2013 GoMage (http://www.gomage.com)
 * @author       GoMage
 * @license      http://www.gomage.com/license-agreement/  Single domain license
 * @terms of use http://www.gomage.com/terms-of-use
 * @version      Release: 4.2
 * @since        Class available since Release 1.0
 */

class GoMage_Navigation_Model_Layer_Filter_Priceenterprise extends Enterprise_Search_Model_Catalog_Layer_Filter_Price
{
    /**
     * XML configuration paths for Price Layered Navigation
     */
    const XML_PATH_RANGE_CALCULATION       = 'catalog/layered_navigation/price_range_calculation';
    const XML_PATH_RANGE_STEP              = 'catalog/layered_navigation/price_range_step';
    const XML_PATH_RANGE_MAX_INTERVALS     = 'catalog/layered_navigation/price_range_max_intervals';
    const XML_PATH_ONE_PRICE_INTERVAL      = 'catalog/layered_navigation/one_price_interval';
    const XML_PATH_INTERVAL_DIVISION_LIMIT = 'catalog/layered_navigation/interval_division_limit';

    /**
     * Price layered navigation modes: Automatic (equalize price ranges), Automatic (equalize product counts), Manual
     */
    const RANGE_CALCULATION_AUTO     = 'auto'; // equalize price ranges
    const RANGE_CALCULATION_IMPROVED = 'improved'; // equalize product counts
    const RANGE_CALCULATION_MANUAL   = 'manual';

    const MIN_RANGE_POWER = 10;

    protected $_selected_options;

    /**
     * Resource instance
     *
     * @var Mage_Catalog_Model_Resource_Eav_Mysql4_Layer_Filter_Price
     */
    protected $_resource;

    /**
     * Class constructor
     *
     */
    public function __construct()
    {
        parent::__construct();
        $this->_requestVar = 'price';
    }

    public function getRequestVarValue()
    {
        return $this->_requestVar;
    }

    /**
     * Retrieve resource instance
     *
     * @return Mage_Catalog_Model_Resource_Eav_Mysql4_Layer_Filter_Price
     */
    protected function _getResource()
    {
        if (is_null($this->_resource)) {
            $this->_resource = Mage::getResourceModel('catalog/layer_filter_price');
        }
        return $this->_resource;
    }

    /**
     * Get minimal price from layer products set
     *
     * @return float
     */
    public function getMinPriceInt()
    {
        $minPrice = $this->getData('min_price_int');
        if (is_null($minPrice)) {
            $minPrice = $this->_getResource()->getMinPrice($this);
            $minPrice = floor($minPrice);
            $this->setData('min_price_int', $minPrice);
        }
        return $minPrice;
    }

    /**
     * Get maximum price from layer products set
     *
     * @return float
     */
    public function getMaxPriceInt()
    {
        $maxPrice = $this->getData('max_price_int');
        if (is_null($maxPrice)) {
            $maxPrice = $this->_getResource()->getMaxPrice($this);
            $maxPrice = ceil($maxPrice);
            $this->setData('max_price_int', $maxPrice);
        }
        return $maxPrice;
    }

    public function getMinValueInt()
    {
        return $this->getMinPriceInt();
    }

    public function getMaxValueInt()
    {
        return $this->getMaxPriceInt();
    }

    /**
     * Prepare text of item label
     *
     * @param   int $range
     * @param   float $value
     * @return  string
     */
    protected function _renderItemLabel($range, $value)
    {
        $store      = Mage::app()->getStore();
        $fromPrice  = $store->formatPrice(($value-1)*$range);
        $toPrice    = $store->formatPrice($value*$range);
        return Mage::helper('catalog')->__('%s - %s', $fromPrice, $toPrice);
    }

    /**
     * Prepare text of range label
     *
     * @param float|string $fromPrice
     * @param float|string $toPrice
     * @return string
     */
    protected function _renderRangeLabel($fromPrice, $toPrice)
    {
        $store      = Mage::app()->getStore();
        $formattedFromPrice  = $store->formatPrice($fromPrice);
        if ($toPrice === '') {
            return Mage::helper('catalog')->__('%s and above', $formattedFromPrice);
        } elseif ($fromPrice == $toPrice && Mage::app()->getStore()->getConfig(self::XML_PATH_ONE_PRICE_INTERVAL)) {
            return $formattedFromPrice;
        } else {
            if ($fromPrice != $toPrice) {
                $toPrice -= .01;
            }
            return Mage::helper('catalog')->__('%s - %s', $formattedFromPrice, $store->formatPrice($toPrice));
        }
    }

    /**
     * Get price aggreagation data cache key
     *
     * @return string
     */
    protected function _getCacheKey()
    {
        $key = $this->getLayer()->getStateKey()
            . '_PRICES_GRP_' . Mage::getSingleton('customer/session')->getCustomerGroupId()
            . '_CURR_' . Mage::app()->getStore()->getCurrentCurrencyCode()
            . '_ATTR_' . $this->getAttributeModel()->getAttributeCode()
            . '_LOC_'
        ;
        $taxReq = Mage::getSingleton('tax/calculation')->getRateRequest(false, false, false);
        $key.= implode('_', $taxReq->getData());
        return $key;
    }

    protected function _getSelectedOptions(){

        if(is_null($this->_selected_options)){

            $selected = array();

            if($value = Mage::app()->getFrontController()->getRequest()->getParam($this->_requestVar)){

                $value = urldecode($value);

                $_selected = array_merge($selected, explode(',', $value));

                $length = count($_selected);

                for($i = 0; $i<$length; $i+=2){

                    $selected[] = $_selected[$i].','.$_selected[$i+1];

                }


            }

            $this->_selected_options = $selected;

        }

        return $this->_selected_options;

    }

    /**
     * Get information about products count in range
     *
     * @param   int $range
     * @return  int
     */
    public function getRangeItemCounts($range)
    {
        $rangeKey = 'range_item_counts_' . $range;
        $items = $this->getData($rangeKey);
        if (is_null($items)) {
            $items = $this->_getResource()->getCount($this, $range);
            $calculation = Mage::app()->getStore()->getConfig(self::XML_PATH_RANGE_CALCULATION);
            if ($calculation){
                $i = 0;
                $lastIndex = null;
                $maxIntervalsNumber = $this->getMaxIntervalsNumber();

                foreach ($items as $k => $v) {
                    ++$i;
                    if ($calculation == self::RANGE_CALCULATION_MANUAL && $i > 1 && $i > $maxIntervalsNumber) {
                        $items[$lastIndex] += $v;
                        unset($items[$k]);
                    } else {
                        $lastIndex = $k;
                    }
                }
            }
            $this->setData($rangeKey, $items);
        }
        return $items;
    }

    /**
     * Get price range for building filter steps
     *
     * @return int
     */
    public function getPriceRange()
    {
        $range = $this->getData('price_range');

        if (!$range) {
            $maxPrice = $this->getMaxPriceInt();

            if (!$range) {
                $calculation = Mage::app()->getStore()->getConfig(self::XML_PATH_RANGE_CALCULATION);

                if (!$calculation || $calculation == self::RANGE_CALCULATION_AUTO) {
                    $index = 1;
                    do {

                        $range = pow(10, (strlen(floor($maxPrice)) - $index));
                        $items = $this->getRangeItemCounts($range);
                        $index++;
                    }
                    while($range > self::MIN_RANGE_POWER && count($items) < 2);
                } else {
                    $range = (float)Mage::app()->getStore()->getConfig(self::XML_PATH_RANGE_STEP);
                }
            }
            $this->setData('price_range', $range);
        }
        return $range;
    }

    /**
     * Get data for build price filter items
     *
     * @return array
     */
    protected function _getItemsData()
    {
        $key = $this->_getCacheKey();

        $selected = $this->_getSelectedOptions();


        $data = $this->getLayer()->getAggregator()->getCacheData($key);

        $filter_mode = Mage::helper('gomage_navigation')->isGomageNavigation();

        if ($data === null) {

            $data = array();
            $attributeId = Mage::getResourceModel('eav/entity_attribute')->getIdByCode('catalog_product','price');
            $attribute = Mage::getModel('catalog/resource_eav_attribute')->load($attributeId);

            if ( $attribute->getRangeOptions() == GoMage_Navigation_Model_Adminhtml_System_Config_Source_Filter_Optionsrange::MANUALLY
                &&
                $attribute->getFilterType() == GoMage_Navigation_Model_Layer::FILTER_TYPE_DEFAULT )
            {
                $manual = $attribute->getRangeManual();
                $range_array = explode(",", $manual);

                $prevValue = 0;
                $items = 0;
                foreach( $range_array as $my_range )
                {
                    $the_range = (int)trim($my_range);
                    $this->setData('price_range', $the_range);
                    $range = $the_range;
                    $dbRanges = $this->getRangeItemCounts($range);

                    foreach ($dbRanges as $index=>$count) {

                        if ( (int)$index == 1 )
                        {
                            $value = $index . ',' . $range;

                            if(in_array($value, $selected) && !$filter_mode){
                                continue;
                            }

                            if(in_array($value, $selected) && $filter_mode){

                                $active = true;

                            }else{

                                $active = false;

                                if(!empty($selected) && $this->getAttributeModel()->getFilterType() != GoMage_Navigation_Model_Layer::FILTER_TYPE_DROPDOWN ){

                                    $value = implode(',',array_merge($selected, (array)$value));


                                }

                            }

                            $count = $count - $items;
                            $items = $count + $items;

                            if(!$count){
                                continue;
                            }

                            $store = Mage::app()->getStore();
                            $from = (($index-1)*$range) + $prevValue;
                            $fromPrice = $store->formatPrice($from);
                            $to = $index * $range;
                            $toPrice = $store->formatPrice($index*$range);
                            $label = Mage::helper('catalog')->__('%s - %s', $fromPrice, $toPrice);
                            $prevValue = $range;

                            $data[] = array(
                                'label' 	=> $label,
                                'value' 	=> $from . ',' . $to,
                                'count' 	=> $count,
                                'active'	=> $active,
                            );

                        }
                        else
                        {
                            break;
                        }
                    }
                }
            }
            else if ( $attribute->getRangeOptions() == GoMage_Navigation_Model_Adminhtml_System_Config_Source_Filter_Optionsrange::AUTO
                &&
                $attribute->getFilterType() == GoMage_Navigation_Model_Layer::FILTER_TYPE_DEFAULT )
            {
                $auto = $attribute->getRangeAuto();
                $autoArray = explode(",", $auto);

                $sort = array();
                foreach( $autoArray as $rangeAuto )
                {
                    $range_array = explode("=", $rangeAuto);
                    if ( $range_array[0] != '' )
                    {
                        $sort[$range_array[0]] = $rangeAuto;
                    }
                }

                ksort($sort);
                $limit_start = 0;
                $first = true;

                foreach( $sort as $rangeAuto )
                {
                    $range_array = explode("=", $rangeAuto);

                    $the_range = (int)trim($range_array[1]);
                    $limit_end = (int)trim($range_array[0]);

                    $this->setData('price_range', $the_range);
                    $range = $the_range;
                    $dbRanges = $this->getRangeItemCounts($range);

                    foreach ($dbRanges as $index=>$count) {

                        if (  $limit_start < $index*$range  && $limit_end >= $index*$range
                            &&
                            $count > 0
                        )
                        {

                            if (  $first || (!$first && $index > 0) )
                            {
                                $first = false;

                                $value = $index . ',' . $range;

                                if(in_array($value, $selected) && !$filter_mode){
                                    continue;
                                }

                                if(in_array($value, $selected) && $filter_mode){

                                    $active = true;

                                }else{

                                    $active = false;

                                    if(!empty($selected) && $this->getAttributeModel()->getFilterType() != GoMage_Navigation_Model_Layer::FILTER_TYPE_DROPDOWN ){

                                        $value = implode(',',array_merge($selected, (array)$value));


                                    }

                                }

                                $store = Mage::app()->getStore();
                                $from = (($index-1)*$range);
                                $fromPrice = $store->formatPrice(($index-1)*$range);
                                $to = $index * $range;
                                $toPrice = $store->formatPrice($index*$range);
                                $label = Mage::helper('catalog')->__('%s - %s', $fromPrice, $toPrice);

                                $price_from = Mage::app()->getFrontController()->getRequest()->getParam('price_from', false);
                                if ($price_from){
                                    $price_from = explode(',', $price_from);
                                }else{
                                    $price_from = array();
                                }
                                $price_to = Mage::app()->getFrontController()->getRequest()->getParam('price_to', false);
                                if ($price_to){
                                    $price_to = explode(',', $price_to);
                                }else{
                                    $price_to = array();
                                }

                                $active = $active || (in_array($from, $price_from) && in_array($to, $price_to));

                                $price_value = '';
                                if (count($price_from) && count($price_to)){
                                    if (!in_array($from, $price_from)){
                                        $price_from[] = $from;
                                    }
                                    if (!in_array($to, $price_to)){
                                        $price_to[] = $to;
                                    }
                                    $price_value = implode(',', $price_from) . ';' . implode(',', $price_to);
                                }

                                $data[] = array(
                                    'label' 	=> $label,
                                    'value' 	=> ($price_value ? $price_value : $from . ',' . $to),
                                    'count' 	=> $count,
                                    'active'	=> $active,
                                    'from_to'   => $from . ',' . $to,
                                );
                            }

                        }
                    }

                    $limit_start = $limit_end;
                }

            }
            else
            {
                $range      = $this->getPriceRange();
                $dbRanges   = $this->getRangeItemCounts($range);


                $data       = array();

                if($selected){
                    foreach($selected as $value){
                        $value = explode(',', $value);
                        $dbRanges[$value[0]] = 0;
                    }

                    ksort($dbRanges);
                }


                foreach ($dbRanges as $index=>$count) {

                    $value = $index . ',' . $range;

                    if(in_array($value, $selected) && !$filter_mode){
                        continue;
                    }

                    if(in_array($value, $selected) && $filter_mode){

                        $active = true;

                    }else{

                        $active = false;

                        if(!empty($selected) && $this->getAttributeModel()->getFilterType() != GoMage_Navigation_Model_Layer::FILTER_TYPE_DROPDOWN ){

                            $value = implode(',',array_merge($selected, (array)$value));


                        }

                    }

                    $data[] = array(
                        'label' 	=> $this->_renderItemLabel($range, $index),
                        'value' 	=> $value,
                        'count' 	=> $count,
                        'active'	=> $active,
                    );
                }
            }

            $tags = array(
                Mage_Catalog_Model_Product_Type_Price::CACHE_TAG,
            );
            $tags = $this->getLayer()->getStateTags($tags);
            $this->getLayer()->getAggregator()->saveCacheData($data, $key, $tags);
        }
        return $data;
    }


    /**
     * Apply price range filter to collection
     *
     * @return Mage_Catalog_Model_Layer_Filter_Price
     */
    public function apply(Zend_Controller_Request_Abstract $request, $filterBlock)
    {

        switch($this->getAttributeModel()->getFilterType()):

            case (GoMage_Navigation_Model_Layer::FILTER_TYPE_INPUT):
                $_from = $request->getParam($this->getRequestVarValue().'_from', false);
                $_to = $request->getParam($this->getRequestVarValue().'_to', false);

                if($_from || $_to){

                    $value = array('from'=>$_from, 'to'=>$_to);

                    $this->_getResource()->applyFilterToCollection($this, $value);

                    $store      = Mage::app()->getStore();
                    $fromPrice  = $store->formatPrice($_from);
                    $toPrice    = $store->formatPrice($_to);

                    if ( Mage::helper('gomage_navigation')->isEnterprise() )
                    {
                        $facetValue = array(
                            $this->_getFilterField() => array(
                                'from' => $_from,
                                'to'   => $_to
                            )
                        );

                        $helper = Mage::helper('enterprise_search');
                        $isCatalog = true;
                        if ( Mage::app()->getFrontController()->getRequest()->getParam('q') != null )
                        {
                            $isCatalog = false;
                        }

                        if ($helper->isThirdPartSearchEngine() && $helper->getIsEngineAvailableForNavigation($isCatalog) && Mage::helper('gomage_navigation')->isGomageNavigation())
                        {
                            $this->getLayer()->getProductCollection()->addFqFilter($facetValue);
                        }
                    }


                    $this->getLayer()->getState()->addFilter(
                        $this->_createItem(Mage::helper('catalog')->__('%s - %s', $fromPrice, $toPrice), $value)
                    );

                }else{
                    return $this;
                }

                break;

            case (GoMage_Navigation_Model_Layer::FILTER_TYPE_SLIDER):
            case (GoMage_Navigation_Model_Layer::FILTER_TYPE_SLIDER_INPUT):
            case (GoMage_Navigation_Model_Layer::FILTER_TYPE_INPUT_SLIDER):

                if ( Mage::helper('gomage_navigation')->isMobileDevice() )
                {
                    /**
                     * Filter must be string: $index,$range
                     */
                    $filter = $request->getParam($this->getRequestVarValue());
                    if (!$filter) {
                        return $this;
                    }

                    $filter = explode(',', $filter);
                    if (count($filter) < 2) {
                        return $this;
                    }

                    $length = count($filter);

                    $value = array();

                    for($i = 0; $i<$length; $i+=2){

                        $value[] = array(
                            'index'=>$filter[$i],
                            'range'=>$filter[$i+1],
                        );

                    }



                    if (!empty($value)) {

                        $this->setPriceRange((int)$value[0]['range']);

                        $this->_getResource()->applyFilterToCollection($this, $value);

                        foreach($value as $_value){

                            $this->getLayer()->getState()->addFilter(
                                $this->_createItem($this->_renderItemLabel($_value['range'], $_value['index']), $_value)
                            );

                            $this->_applyToCollection($_value['range'], $_value['index']);

                        }

                    }
                }
                else
                {
                    $_from = $request->getParam($this->getRequestVarValue().'_from', false);
                    $_to = $request->getParam($this->getRequestVarValue().'_to', false);

                    if($_from || $_to){

                        $value = array('from'=>$_from, 'to'=>$_to);

                        $this->_getResource()->applyFilterToCollection($this, $value);

                        $store      = Mage::app()->getStore();
                        $fromPrice  = $store->formatPrice($_from);
                        $toPrice    = $store->formatPrice($_to);

                        if ( Mage::helper('gomage_navigation')->isEnterprise() )
                        {
                            $facetValue = array(
                                $this->_getFilterField() => array(
                                    'from' => $_from,
                                    'to'   => $_to
                                )
                            );

                            $this->getLayer()->getProductCollection()->addFqFilter($facetValue);
                        }


                        $this->getLayer()->getState()->addFilter(
                            $this->_createItem(Mage::helper('catalog')->__('%s - %s', $fromPrice, $toPrice), $value)
                        );

                    }else{
                        return $this;
                    }
                }



                break;

            default:

                $attributeId = Mage::getResourceModel('eav/entity_attribute')->getIdByCode('catalog_product','price');
                $attribute = Mage::getModel('catalog/resource_eav_attribute')->load($attributeId);

                if ( ($attribute->getRangeOptions() == GoMage_Navigation_Model_Adminhtml_System_Config_Source_Filter_Optionsrange::MANUALLY
                        ||
                        $attribute->getRangeOptions() == GoMage_Navigation_Model_Adminhtml_System_Config_Source_Filter_Optionsrange::AUTO)
                    &&
                    $attribute->getFilterType() == GoMage_Navigation_Model_Layer::FILTER_TYPE_DEFAULT )
                {
                    $_from = $request->getParam($this->getRequestVarValue().'_from', false);
                    $_to = $request->getParam($this->getRequestVarValue().'_to', false);



                    if($_from || $_to){

                        $value = array('from'=>$_from, 'to'=>$_to);

                        $this->_getResource()->applyFilterToCollection($this, $value);

                        $store      = Mage::app()->getStore();
                        $fromPrice  = $store->formatPrice($_from);
                        $toPrice    = $store->formatPrice($_to);


                        $this->getLayer()->getState()->addFilter(
                            $this->_createItem(Mage::helper('catalog')->__('%s - %s', $fromPrice, $toPrice), $value)
                        );

                    }else{

                        /**
                         * Filter must be string: $index,$range
                         */
                        $filter = $request->getParam($this->getRequestVarValue());
                        if (!$filter) {
                            return $this;
                        }

                        $filter = explode(',', $filter);
                        if (count($filter) < 2) {
                            return $this;
                        }

                        $length = count($filter);

                        $value = array();

                        for($i = 0; $i<$length; $i+=2){

                            $value[] = array(
                                'index'=>$filter[$i],
                                'range'=>$filter[$i+1],
                            );

                        }

                        if (!empty($value)) {

                            $this->setPriceRange((int)$value[0]['range']);

                            $this->_getResource()->applyFilterToCollection($this, $value);

                            foreach($value as $_value){

                                $range = $_value['range'];
                                $index = $_value['index'];

                            }

                        }

                        $_to = $range;
                        $_from = $index;


                        if($_from || $_to){

                            $value = array('from'=>$_from, 'to'=>$_to);

                            $this->_getResource()->applyFilterToCollection($this, $value);

                            $store      = Mage::app()->getStore();
                            $fromPrice  = $store->formatPrice($_from);
                            $toPrice    = $store->formatPrice($_to);

                            if ( Mage::helper('gomage_navigation')->isEnterprise() )
                            {
                                $facetValue = array(
                                    $this->_getFilterField() => array(
                                        'from' => $_from,
                                        'to'   => $_to
                                    )
                                );

                                $this->getLayer()->getProductCollection()->addFqFilter($facetValue);
                            }


                            $this->getLayer()->getState()->addFilter(
                                $this->_createItem(Mage::helper('catalog')->__('%s - %s', $fromPrice, $toPrice), $value)
                            );

                        }


                        return $this;
                    }
                }
                else
                {
                    /**
                     * Filter must be string: $index,$range
                     */
                    $filter = $request->getParam($this->getRequestVarValue());
                    if (!$filter) {
                        return $this;
                    }

                    $filter = explode(',', $filter);
                    if (count($filter) < 2) {
                        return $this;
                    }

                    $length = count($filter);

                    $value = array();

                    for($i = 0; $i<$length; $i+=2){

                        $value[] = array(
                            'index'=>$filter[$i],
                            'range'=>$filter[$i+1],
                        );

                    }



                    if (!empty($value)) {

                        $this->setPriceRange((int)$value[0]['range']);

                        $this->_getResource()->applyFilterToCollection($this, $value);

                        foreach($value as $_value){

                            $this->getLayer()->getState()->addFilter(
                                $this->_createItem($this->_renderItemLabel($_value['range'], $_value['index']), $_value)
                            );

                            $this->_applyToCollection($_value['range'], $_value['index']);

                        }

                    }
                }


                break;

        endswitch;

        return $this;
    }

    /**
     * Retrieve active customer group id
     *
     * @return int
     */
    public function getCustomerGroupId()
    {
        $customerGroupId = $this->_getData('customer_group_id');
        if (is_null($customerGroupId)) {
            $customerGroupId = Mage::getSingleton('customer/session')->getCustomerGroupId();
        }
        return $customerGroupId;
    }

    /**
     * Set active customer group id for filter
     *
     * @param int $customerGroupId
     * @return Mage_Catalog_Model_Layer_Filter_Price
     */
    public function setCustomerGroupId($customerGroupId)
    {
        return $this->setData('customer_group_id', $customerGroupId);
    }

    /**
     * Retrieve active currency rate for filter
     *
     * @return float
     */
    public function getCurrencyRate()
    {
        $rate = $this->_getData('currency_rate');
        if (is_null($rate)) {
            $rate = Mage::app()->getStore($this->getStoreId())->getCurrentCurrencyRate();
        }
        if (!$rate) {
            $rate = 1;
        }
        return $rate;
    }

    /**
     * Set active currency rate for filter
     *
     * @param float $rate
     * @return Mage_Catalog_Model_Layer_Filter_Price
     */
    public function setCurrencyRate($rate)
    {
        return $this->setData('currency_rate', $rate);
    }

    public function getResetValue($value_to_remove = null)
    {
        if($value_to_remove && ($current_value = Mage::app()->getFrontController()->getRequest()->getParam($this->_requestVar))){
            if(is_array($value_to_remove)){
                if(isset($value_to_remove['index']) && isset($value_to_remove['range'])){
                    $value_to_remove = $value_to_remove['index'].','.$value_to_remove['range'];
                }else{
                    return null;
                }
            }

            $current_value = $this->_getSelectedOptions();
            if(false !== ($position = array_search($value_to_remove, $current_value))){
                unset($current_value[$position]);
                if(!empty($current_value)){
                    return implode(',', $current_value);
                }
            }
        }

        return null;
    }

    /**
     * Add params to faceted search
     *
     * @return Enterprise_Search_Model_Catalog_Layer_Filter_Price
     */
    public function addFacetCondition()
    {
        if (Mage::app()->getStore()->getConfig(self::XML_PATH_RANGE_CALCULATION) == self::RANGE_CALCULATION_IMPROVED) {
            return $this->_addCalculatedFacetCondition();
        }

        $this->_facets = array();
        $range    = $this->getPriceRange();
        $maxPrice = $this->getMaxPriceInt();
        if ($maxPrice >= 0) {
            $priceFacets = array();
            $facetCount  = ceil($maxPrice / $range);

            for ($i = 0; $i < $facetCount + 1; $i++) {
                $separator = array($i * $range, ($i + 1) * $range);
                $facetedRange = $this->_prepareFacetRange($separator[0], $separator[1]);
                $this->_facets[$facetedRange['from'] . '_' . $facetedRange['to']] = $separator;
                $priceFacets[] = $facetedRange;
            }

            $this->getLayer()->getProductCollection()->setFacetCondition($this->_getFilterField(), $priceFacets);
        }

        return $this;
    }

    /**
     * Initialize filter items
     *
     * @return  Mage_Catalog_Model_Layer_Filter_Abstract
     */
    protected function _initItems()
    {
        $data = $this->_getItemsData();
        $items=array();
        foreach ($data as $itemData) {
            $items[] = $this->_createItem(
                $itemData['label'],
                $itemData['value'],
                $itemData['count'],
                $itemData['active'],
                isset($itemData['image']) ? $itemData['image'] : '',
                isset($itemData['level']) ? $itemData['level'] : 0,
                isset($itemData['haschild']) ? $itemData['haschild'] : '',
                isset($itemData['from_to']) ? $itemData['from_to'] : ''

            );
        }
        $this->_items = $items;
        return $this;
    }

    /**
     * Create filter item object
     *
     * @param   string $label
     * @param   mixed $value
     * @param   int $count
     * @return  Mage_Catalog_Model_Layer_Filter_Item
     */
    protected function _createItem($label, $value, $count=0, $status = false, $image = '', $level = 0, $haschild = '', $from_to = '')
    {
        return Mage::getModel('catalog/layer_filter_item')
            ->setFilter($this)
            ->setLabel($label)
            ->setValue($value)
            ->setCount($count)
            ->setActive($status)
            ->setImage($image)
            ->setLevel($level)
            ->setHasChild($haschild)
            ->setFromTo($from_to);

    }
}
