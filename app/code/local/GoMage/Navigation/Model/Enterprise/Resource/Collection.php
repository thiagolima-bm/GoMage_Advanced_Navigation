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
 * @since        Class available since Release 3.0
 */

class GoMage_Navigation_Model_Enterprise_Resource_Collection extends Enterprise_Search_Model_Resource_Collection {
	
	public function getSearchedEntityIds() {
		return $this->_searchedEntityIds;
	}
}
