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
	
	class GoMage_Navigation_Block_Layer_View extends Mage_Catalog_Block_Layer_View{
		
		protected $shop_by_in_content = false;

        protected $_block_side = null;

        public function canShowLayeredBlock($check)
        {
            $_filters = $this->getFilters();
            $canShow = false;
            foreach( $_filters as $_filter )
            {
                $category = Mage::registry("current_category");
                if($category && in_array($category->getId(),explode(",",$_filter->getCategoryIdsFilter())))
                {
                    continue;
                }

                if(
                    $_filter->getItemsCount()
                    &&
                    ($_filter->getAttributeLocation() == GoMage_Navigation_Model_Adminhtml_System_Config_Source_Filter_Attributelocation::USE_GLOBAL
                        ||
                        $_filter->getAttributeLocation() == $check))
                {
                    if(Mage::helper('gomage_navigation')->getFilterItemCount($_filter))
                    {
                        $canShow = true;
                    }
                }

            }

            return $canShow;
        }

        public function setBlockSide($block_side)
        {
            $this->_block_side = $block_side;
        }

        public function getBlockSide()
        {
            if ( $this->_block_side )
            {
                return $this->_block_side;
            }

            return GoMage_Navigation_Model_Adminhtml_System_Config_Source_Filter_Attributelocation::LEFT_BLOCK;
        }

		
		public function getPopupStyle(){
			
			return (string) Mage::getStoreConfig('gomage_navigation/filter/popup_style');
			
		}
		
		public function getSliderType(){
			
			return (string) Mage::getStoreConfig('gomage_navigation/filter/slider_type');
			
		}
		
		/**
         * Get all layer filters
         *
         * @return array
         */
        public function getFilters()
        {
            $filters = parent::getFilters();
            
            if ($this->_isStockFilter()) {
                $filters[] = $this->_getStockFilter();
            }

            return $filters;
        }

		public function getFiltersWidth($check)
        {
            $filters = $this->getFilters();
            $i = 0;
            
            foreach ($filters as $_filter)
            {
                if (($_filter->getPopupId() == 'category' && $check == GoMage_Navigation_Model_Adminhtml_System_Config_Source_Filter_Attributelocation::CONTENT))
                {
                    if ( !Mage::getStoreConfigFlag('gomage_navigation/contentcolumnsettings/active')
                            ||
                        !Mage::getStoreConfigFlag('gomage_navigation/contentcolumnsettings/show_shopby'))
                    {
                    	continue;
                    }
                }

                $category = Mage::registry("current_category");
                if($category && in_array($category->getId(),explode(",",$_filter->getCategoryIdsFilter())))
                {
                    continue;
                }

                if(!Mage::helper('gomage_navigation')->getFilterItemCount($_filter))
                {
                    continue;
                }

                if($_filter->getItemsCount()
                	   &&
                  ($_filter->getAttributeLocation() == GoMage_Navigation_Model_Adminhtml_System_Config_Source_Filter_Attributelocation::USE_GLOBAL
                	   ||
                   $_filter->getAttributeLocation() == $check))
                {
                	$i++;   	
                }	
            }

            return ($i)?floor(100 / $i) . '%':0;
        }
        
        
		private function _isStockFilter()
        {
            return Mage::helper('gomage_navigation')->isGomageNavigation() && Mage::getStoreConfigFlag('gomage_navigation/stock/active');
        }

        protected function _getStockFilter()
        {
            return $this->getChild('stock_status_filter');
        } 
		
		public function getSliderStyle(){
			
			return (string) Mage::getStoreConfig('gomage_navigation/filter/slider_style');
			
		}
		
		public function getIconStyle(){
			
			return (string) Mage::getStoreConfig('gomage_navigation/filter/icon_style');
			
		}
		
		public function getButtonStyle(){
			
			return (string) Mage::getStoreConfig('gomage_navigation/filter/button_style');
			
		}
		
		public function getFilterStyle(){
			
			return (string) Mage::getStoreConfig('gomage_navigation/filter/style');
			
		}
		
		/**
    	 * Prepare child blocks
 	    *
 	    * @return Mage_Catalog_Block_Layer_View
	     */
	    protected function _prepareLayout(){
	    	
	    	$filterableAttributes = $this->_getFilterableAttributes();
	    	
	    	$collection = $this->getLayer()->getProductCollection();
	    	
	    	$base_select = array();
	    	
	    	if($this->getRequest()->getParam('price') || $this->getRequest()->getParam('price_from') || $this->getRequest()->getParam('price_to')){
	    	
	    	$base_select['price'] = clone $collection->getSelect();
	    	
	    	}
	    	
	    	if($this->getRequest()->getParam('cat')){
	    	
	    	$base_select['cat'] = clone $collection->getSelect();
	    	
	    	}
	    	
	        foreach ($filterableAttributes as $attribute) {
	            	            	            
	            $code = $attribute->getAttributeCode();
	            
	            if($this->getRequest()->getParam($code, false)){
					
					$base_select[$code] = clone $collection->getSelect();
					
				}
	            
	        }
	        
	        $this->getLayer()->setBaseSelect($base_select);
	        
	        $stockBlock = $this->getLayout()->createBlock("gomage_navigation/layer_filter_stock")
                ->setLayer($this->getLayer())
                ->init();

            $this->setChild('stock_status_filter', $stockBlock); 

	        parent::_prepareLayout();

	    	$currentCategory = Mage::registry('current_category');
            	
			if ( ($currentCategory && $currentCategory->getData('navigation_pw_gn_shopby') == GoMage_Navigation_Model_Adminhtml_System_Config_Source_Shopby::USE_GLOBAL)
					||
				  !$currentCategory )
			{
				$position = Mage::getStoreConfig('gomage_navigation/general/show_shopby');
			}	        
			else if( $currentCategory )
			{
			   	$position = $currentCategory->getData('navigation_pw_gn_shopby');
			}
	        
	        if(Mage::helper('gomage_navigation')->isGomageNavigation()){
	        	$this->unsetChild('layer_state');
	        	if ($position == GoMage_Navigation_Model_Adminhtml_System_Config_Source_Shopby::RIGHT_COLUMN
	        			||
	        		$position == GoMage_Navigation_Model_Adminhtml_System_Config_Source_Shopby::RIGHT_COLUMN_CONTENT){
		        	$right = $this->getLayout()->getBlock('right');
		            if ($right){
		            	$catalog_rightnav = clone $this;
		            	$catalog_rightnav->setParentBlock($right);
		            	$catalog_rightnav->setNameInLayout('gomage.catalog.rightnav');
		            	if ($this->getLayout()->getBlock('gomage.navigation.right')){
		            		$right->insert($catalog_rightnav, 'gomage.navigation.right', true, 'gomage.catalog.rightnav');
		            	}else{
		            		$right->insert($catalog_rightnav, '', false, 'gomage.catalog.rightnav');
		            	}
		            }
	        	}
	        }
	    }
	    
    	protected function _toHtml()
        {        	        	
            if(Mage::helper('gomage_navigation')->isGomageNavigation()){
            	
            	$currentCategory = Mage::registry('current_category');
            	
				if ( ($currentCategory && $currentCategory->getData('navigation_pw_gn_shopby') == GoMage_Navigation_Model_Adminhtml_System_Config_Source_Shopby::USE_GLOBAL)
						||
					  !$currentCategory )
				{
					$position = Mage::getStoreConfig('gomage_navigation/general/show_shopby');
				}	        
				else if( $currentCategory )
				{
				   	$position = $currentCategory->getData('navigation_pw_gn_shopby');
				}

            	if ($position == GoMage_Navigation_Model_Adminhtml_System_Config_Source_Shopby::CONTENT){
            		if ($this->shop_by_in_content){
            			$this->setTemplate('gomage/navigation/layer/view.phtml');
            			$this->setData('check', GoMage_Navigation_Model_Adminhtml_System_Config_Source_Filter_Attributelocation::CONTENT);
            		}else{
            			$this->setTemplate(null);
            		}
            	}elseif ($position == GoMage_Navigation_Model_Adminhtml_System_Config_Source_Shopby::RIGHT_COLUMN){
            		if ($this->getNameInLayout() != 'gomage.catalog.rightnav'){
            			$this->setTemplate(null);
            		}else{
            			$this->setTemplate('gomage/navigation/layer/view.phtml');
            			$this->setData('shopby_type', 'right');
            			$this->setData('check', GoMage_Navigation_Model_Adminhtml_System_Config_Source_Filter_Attributelocation::RIGHT_BLOCK);
            		}
            	}
            	elseif ($position == GoMage_Navigation_Model_Adminhtml_System_Config_Source_Shopby::RIGHT_COLUMN_CONTENT){
            		
            		if ($this->shop_by_in_content){
            			$this->setTemplate('gomage/navigation/layer/view.phtml');
            			$this->setData('check', GoMage_Navigation_Model_Adminhtml_System_Config_Source_Filter_Attributelocation::CONTENT);
            		}
            		else if ( $this->getNameInLayout() == 'gomage.catalog.rightnav' || $this->getNameInLayout() == 'catalogsearch.rightnav' )
            		{
            			$this->setData('check', GoMage_Navigation_Model_Adminhtml_System_Config_Source_Filter_Attributelocation::RIGHT_BLOCK);
            			$this->setTemplate('gomage/navigation/layer/view.phtml');	
            		}
            		else 
            		{
            			$this->setTemplate(null);
            		}
            	}
            	elseif ($position == GoMage_Navigation_Model_Adminhtml_System_Config_Source_Shopby::LEFT_COLUMN_CONTENT){

            		if ($this->shop_by_in_content){
            			$this->setTemplate('gomage/navigation/layer/view.phtml');
            			$this->setData('check', GoMage_Navigation_Model_Adminhtml_System_Config_Source_Filter_Attributelocation::CONTENT);
            		}
                    else if ( $this->getNameInLayout() == 'catalog.leftnav' || $this->getNameInLayout() == 'catalogsearch.leftnav'  || $this->getNameInLayout() == 'gomage.enterprise.catalog.leftnav')
            		{
            			$this->setData('check', GoMage_Navigation_Model_Adminhtml_System_Config_Source_Filter_Attributelocation::LEFT_BLOCK);
            			$this->setTemplate('gomage/navigation/layer/view.phtml');	
            		}
            		else 
            		{
            			$this->setTemplate(null);
            		}
            	}
            	else{	
                	$this->setTemplate('gomage/navigation/layer/view.phtml');
                	$this->setData('check', GoMage_Navigation_Model_Adminhtml_System_Config_Source_Filter_Attributelocation::LEFT_BLOCK);
            	}	
            }
            return parent::_toHtml();
        }
	    
    	protected function _getCategoryFilter()
        {
            $categoryFilter = parent::_getCategoryFilter();
            $categoryFilter->setBlockSide($this->getBlockSide());
            $categoryFilter->setCustomTemplate();

            if(Mage::helper('gomage_navigation')->isGomageNavigation()){

            	$currentCategory = Mage::registry('current_category');
            	
				if ( ($currentCategory && $currentCategory->getData('navigation_pw_gn_shopby') == GoMage_Navigation_Model_Adminhtml_System_Config_Source_Shopby::USE_GLOBAL)
						||
					  !$currentCategory )
				{
					$position = Mage::getStoreConfig('gomage_navigation/general/show_shopby');
				}	        
				else if( $currentCategory )
				{
				   	$position = $currentCategory->getData('navigation_pw_gn_shopby');
				}

               	switch($position){
               		case GoMage_Navigation_Model_Adminhtml_System_Config_Source_Shopby::LEFT_COLUMN :
                        if (!Mage::getStoreConfigFlag('gomage_navigation/category/active'))
                        {
                            return false;
                        }

               			if (Mage::getStoreConfigFlag('gomage_navigation/category/active') && !Mage::getStoreConfig('gomage_navigation/category/show_shopby')){
               				return false;
               			}               			               			
               		break;
               		case GoMage_Navigation_Model_Adminhtml_System_Config_Source_Shopby::RIGHT_COLUMN :
                        if (!Mage::getStoreConfigFlag('gomage_navigation/rightcolumnsettings/active'))
                        {
                            return false;
                        }

               			if (Mage::getStoreConfigFlag('gomage_navigation/rightcolumnsettings/active') && !Mage::getStoreConfig('gomage_navigation/rightcolumnsettings/show_shopby')){
               				return false;
               			}
               		break;
               		case GoMage_Navigation_Model_Adminhtml_System_Config_Source_Shopby::CONTENT :

                        if (!Mage::getStoreConfigFlag('gomage_navigation/contentcolumnsettings/active'))
                        {
                            return false;
                        }

               			if ((Mage::getStoreConfigFlag('gomage_navigation/category/active') && !Mage::getStoreConfig('gomage_navigation/category/show_shopby')) ||
               				(Mage::getStoreConfigFlag('gomage_navigation/rightcolumnsettings/active') && !Mage::getStoreConfig('gomage_navigation/rightcolumnsettings/show_shopby'))){
               					return false;
               				}

                        if ((Mage::getStoreConfigFlag('gomage_navigation/rightcolumnsettings/active') && !Mage::getStoreConfig('gomage_navigation/contentcolumnsettings/show_shopby'))){
                            return false;
                        }
               		break;	
               		case GoMage_Navigation_Model_Adminhtml_System_Config_Source_Shopby::RIGHT_COLUMN_CONTENT :


                        if ( $this->getBlockSide() == GoMage_Navigation_Model_Adminhtml_System_Config_Source_Filter_Attributelocation::RIGHT_BLOCK )
                        {
                            if ( !Mage::getStoreConfigFlag('gomage_navigation/rightcolumnsettings/active') )
                            {
                                return false;
                            }
                            if (Mage::getStoreConfigFlag('gomage_navigation/rightcolumnsettings/active') && !Mage::getStoreConfig('gomage_navigation/rightcolumnsettings/show_shopby')){
                                return false;
                            }
                        }
                        else if ( $this->getBlockSide() == GoMage_Navigation_Model_Adminhtml_System_Config_Source_Filter_Attributelocation::CONTENT )
                        {
                            if ( !Mage::getStoreConfigFlag('gomage_navigation/contentcolumnsettings/active') )
                            {
                                return false;
                            }
                            if (Mage::getStoreConfigFlag('gomage_navigation/contentcolumnsettings/active') && !Mage::getStoreConfig('gomage_navigation/contentcolumnsettings/show_shopby')){
                                return false;
                            }
                        }
               		break;
               		case GoMage_Navigation_Model_Adminhtml_System_Config_Source_Shopby::LEFT_COLUMN_CONTENT :

                        if ( $this->getBlockSide() == GoMage_Navigation_Model_Adminhtml_System_Config_Source_Filter_Attributelocation::LEFT_BLOCK )
                        {
                            if ( !Mage::getStoreConfigFlag('gomage_navigation/category/active') )
                            {
                                return false;
                            }
                            if (Mage::getStoreConfigFlag('gomage_navigation/category/active') && !Mage::getStoreConfig('gomage_navigation/category/show_shopby')){
                                return false;
                            }
                        }
                        else if ( $this->getBlockSide() == GoMage_Navigation_Model_Adminhtml_System_Config_Source_Filter_Attributelocation::CONTENT )
                        {
                            if ( !Mage::getStoreConfigFlag('gomage_navigation/contentcolumnsettings/active') )
                            {
                                return false;
                            }
                            if (Mage::getStoreConfigFlag('gomage_navigation/contentcolumnsettings/active') && !Mage::getStoreConfig('gomage_navigation/contentcolumnsettings/show_shopby')){
                                return false;
                            }
                        }

               		break;	
               	}

               	return parent::_getCategoryFilter();
            }else{
                return parent::_getCategoryFilter();
            }
        }
	    
	    /**
	     * Retrieve active filters
	     *
	     * @return array
	     */
	    public function getActiveFilters()
	    {
	        $filters = $this->getLayer()->getState()->getFilters();
	        if (!is_array($filters)) {
	            $filters = array();
	        }
	        
	        $allFilters = $this->getFilters();
	        $filterEnable = array();
	        foreach( $allFilters as $_filter )
	        {
	        	$filterEnable[$_filter->getName()] = $_filter->ajaxEnabled();	
	        }
	        
	        $activeFilters = array();
	        foreach( $filters as $filter )
	        {
	        	if ( isset( $filterEnable[$filter->getName()] ) )
	        	{
	        		$filter->setData('ajax_enabled', $filterEnable[$filter->getName()]);
	        	}
	        	
	        	$activeFilters[] = $filter;
	        }

	        return $activeFilters;
	    }
	    
	    public function getOptionUrl($url)
	    {
	    	if ( strpos($url,"?") !== false )
	    	{
	    		return $url . '&ajax=1';
	    	} 
	    	else
	    	{
	    		return $url . '?ajax=1';
	    	}
	    }
	    
	    public function getResetFirlerUrl($filter, $ajax = false)
	    {
	        $filterState = array();
			$helper = Mage::helper('gomage_navigation');
	        
	        foreach ($filter->getItems() as $item) {
	            
	            try
	            {
    	            $slider_item = in_array($item->getFilter()->getAttributeModel()->getFilterType(), 
                	                   array(GoMage_Navigation_Model_Layer::FILTER_TYPE_INPUT,
                	                         GoMage_Navigation_Model_Layer::FILTER_TYPE_SLIDER,
                    				    	 GoMage_Navigation_Model_Layer::FILTER_TYPE_SLIDER_INPUT,
                    				    	 GoMage_Navigation_Model_Layer::FILTER_TYPE_INPUT_SLIDER));
	            }	
	            catch(Exception $e){
    	            $slider_item = false;
    	            	
    	        }			    	 

	            if ($item->getActive() || $slider_item)
	            {
    	        	try{
    	        		
    		        	switch($item->getFilter()->getAttributeModel()->getFilterType()):

                            case (GoMage_Navigation_Model_Layer::FILTER_TYPE_INPUT):
                                $_from	= Mage::app()->getFrontController()->getRequest()->getParam($item->getFilter()->getRequestVarValue().'_from', $item->getFilter()->getMinValueInt());
   	                            $_to	= Mage::app()->getFrontController()->getRequest()->getParam($item->getFilter()->getRequestVarValue().'_to', $item->getFilter()->getMaxValueInt());

   	                            if (($_from != $item->getFilter()->getMinValueInt()) || ($_to != $item->getFilter()->getMaxValueInt()))
   	                            {
   	                                if (!isset($filterState[$item->getFilter()->getRequestVarValue().'_from']))
   	                                {
            		        			$filterState[$item->getFilter()->getRequestVarValue().'_from'] = null;
            		        			$filterState[$item->getFilter()->getRequestVarValue().'_to'] = null;
   	                                }
   	                            }
                                break;


    				    	case (GoMage_Navigation_Model_Layer::FILTER_TYPE_SLIDER):
    				    	case (GoMage_Navigation_Model_Layer::FILTER_TYPE_SLIDER_INPUT):
    				    	case (GoMage_Navigation_Model_Layer::FILTER_TYPE_INPUT_SLIDER):

                                if ( Mage::helper('gomage_navigation')->isMobileDevice() )
                                {
                                    $filterState[$item->getFilter()->getRequestVarValue()] = $item->getFilter()->getCleanValue();
                                }
                                else
                                {
                                    $_from	= Mage::app()->getFrontController()->getRequest()->getParam($item->getFilter()->getRequestVarValue().'_from');
                                    $_to	= Mage::app()->getFrontController()->getRequest()->getParam($item->getFilter()->getRequestVarValue().'_to');

                                    if ( $_from && $_to )
                                    {
                                        $filterState[$item->getFilter()->getRequestVarValue().'_from'] = null;
                                        $filterState[$item->getFilter()->getRequestVarValue().'_to'] = null;
                                    }
                                }

    		        		break;
    		        		
    		        		default:
    		        			
    		        			$filterState[$item->getFilter()->getRequestVarValue()] = $item->getFilter()->getCleanValue();
    		            		
    		            	break;
    		            
    		            endswitch;
    		            
    	            }catch(Exception $e){
    	            	$filterState[$item->getFilter()->getRequestVarValue()] = $item->getFilter()->getCleanValue();
    	            	
    	            }
	            }
	        }
	        
	        if (!count($filterState))
	           return false;
	           
	        $params['_nosid']       = true;   
	        $params['_current']     = true;
	        $params['_use_rewrite'] = true;
	        $params['_query']       = $filterState;
	        $params['_escape']      = true;
	        
	        $params['_query']['ajax'] = null;
		    
		    if($ajax){
		    	
		    	$params['_query']['ajax'] = true;
		    	
		    	
		    }
		    
		    
		    
	    	if ( $helper->isFrendlyUrl() )
    		{
                try{

                    $attr = array();

                    $attributes = Mage::getSingleton('catalog/layer')->getFilterableAttributes();

                    foreach ($attributes as $attribute) {
                        $attr[$attribute->getAttributeCode()]['type'] = $attribute->getBackendType();
                        $options = $attribute->getSource()->getAllOptions();
                        foreach ($options as $option) {
                            $attr[$attribute->getAttributeCode()]['options'][$helper->formatUrlValue($option['label'])] = $option['value'];
                        }
                    }
                    $url = Mage::getUrl('*/*/*', $params);

                    $query = parse_url($url);
                    $_query = explode("&amp;", $query['query']);

                    foreach($_query as $param)
                    {
                        $_param = explode("=", $param);
                        foreach($attr[$_param[0]]['options'] as $key => $val)
                        {
                            if ($val == $_param[1])
                            {
                                $url = str_replace($param,$_param[0] . '=' . $key, $url);
                                break;
                            }
                        }
                    }

                    return $url;
                }catch(Exception $e){
                    return Mage::getUrl('*/*/*', $params);
                }
    		}
    		
	        return Mage::getUrl('*/*/*', $params);
	    }

	    /**
	     * Retrieve Clear Filters URL
	     *
	     * @return string
	     */
	    public function getClearUrl($ajax = false)
	    {
	        $filterState = array();
	        foreach ($this->getActiveFilters() as $item) {
	        	
	        	try{
	        		
		        	switch($item->getFilter()->getAttributeModel()->getFilterType()):
				    	
				    	case (GoMage_Navigation_Model_Layer::FILTER_TYPE_INPUT):
                            $filterState[$item->getFilter()->getRequestVarValue().'_from'] = null;
                            $filterState[$item->getFilter()->getRequestVarValue().'_to'] = null;

                            break;
				    	case (GoMage_Navigation_Model_Layer::FILTER_TYPE_SLIDER):
				    	case (GoMage_Navigation_Model_Layer::FILTER_TYPE_SLIDER_INPUT):
				    	case (GoMage_Navigation_Model_Layer::FILTER_TYPE_INPUT_SLIDER):

                            if ( Mage::helper('gomage_navigation')->isMobileDevice() )
                            {
                                $filterState[$item->getFilter()->getRequestVarValue()] = $item->getFilter()->getCleanValue();
                            }
                            else
                            {
                                $filterState[$item->getFilter()->getRequestVarValue().'_from'] = null;
                                $filterState[$item->getFilter()->getRequestVarValue().'_to'] = null;
                            }
		        		break;

                        case (GoMage_Navigation_Model_Layer::FILTER_TYPE_DEFAULT):

                            if ( $item->getFilter()->getAttributeModel()->getRangeOptions() != GoMage_Navigation_Model_Adminhtml_System_Config_Source_Filter_Optionsrange::NO)
                            {
                                $filterState[$item->getFilter()->getRequestVarValue().'_from'] = null;
                                $filterState[$item->getFilter()->getRequestVarValue().'_to'] = null;
                            }
                            else
                            {
                                $filterState[$item->getFilter()->getRequestVarValue()] = $item->getFilter()->getCleanValue();
                            }


                        break;
		        		
		        		default:
		        	
		            		$filterState[$item->getFilter()->getRequestVarValue()] = $item->getFilter()->getCleanValue();
		            		
		            	break;
		            
		            endswitch;
		            
	            }catch(Exception $e){
	            	
	            	$filterState[$item->getFilter()->getRequestVarValue()] = $item->getFilter()->getCleanValue();
	            	
	            }
	        }
	        $params['_nosid']       = true;
	        $params['_current']     = true;
	        $params['_use_rewrite'] = true;
	        $params['_query']       = $filterState;
	        $params['_escape']      = true;
	        
	        $params['_query']['ajax'] = null;
		    
		    if($ajax){
		    	
		    	$params['_query']['ajax'] = true;
		    	
		    	
		    }
	        
	        return Mage::getUrl('*/*/*', $params);
	    }
	    
	    public function setShopByInContent($value){
	    	$this->shop_by_in_content = $value;
	    	return $this;
	    }
	}