<?php 

defined('C5_EXECUTE') or die(_("Access Denied."));

/**
*
* An object that allows a filtered list of Virtual Table Items to be returned.
* @package Virtual Table
*
*/

Loader::model('attribute_key_category_item');

class AttributeKeyCategoryItemList extends DatabaseItemList {
	
	protected $attributeFilters = array();
	protected $attributeClass = 'AttributeKey';
	protected $autoSortColumns = array('ID');
	protected $itemsPerPage = 10;
	public $user = NULL;
	
	public function __construct($akCategoryHandle) {
		$this->akCategoryHandle = $akCategoryHandle;
	}
	
	protected function setBaseQuery() {
		$this->setQuery('SELECT DISTINCT akcisi.* FROM AttributeKeyCategoryItems akci ');
		$this->filter('akci.akCategoryHandle', $this->akCategoryHandle, '=');
	}
	protected function setPermissionFilters() {
		// check against logged in user
		$u = $this->user;
		if(is_null($u)) {
			$u = new User;
		}
		
		Loader::model('attribute_key_category_item_permission');
		$akcip = AttributeKeyCategoryItemPermission::getByID('default');
		if($akcip->canRead($u)) {
			return;
		}
		$akcip = AttributeKeyCategoryItemPermission::getByID($this->akCategoryHandle);
		if($akcip->canRead($u)) {
			return;
		}
		
		$akcip = AttributeKeyCategoryItemPermission::getByID('default');
		if(!$akcip->canSearch($u)) {
			$defaultFail = TRUE;
		}
		if($defaultFail) {
			$vtp = AttributeKeyCategoryItemPermission::getByID($this->names->fileName);
			if(!$vtp->canSearch($u)) {
				throw new Exception('Permission Error: User does not have access to search this table.');
			}
		}
		
		if(!($u instanceof User)) {
			throw new Exception('Permission Error: User does not exist.');
		}
		
		$uID = -1;
		if ($u->isRegistered()) {
			$uID = $u->getUserID();
		}
		$this->userPostQuery .= ' AND (akci.uID = '.$uID;
		$this->userPostQuery .= ' OR (akcip.uID = '.$uID.' AND akcip.canRead = 1) ';
		
		$groups = $u->getUserGroups();
		$groupIDs = array();
		foreach($groups as $key => $value) {
			$this->userPostQuery .= 'OR (akcip.gID = '.$key.' AND akcip.canRead = 1) ';
		}
		$this->userPostQuery .= ')';
	}
	protected function createQuery() {
		if(!$this->queryCreated){
			$this->setBaseQuery();
			
			$this->setupAttributeFilters('LEFT JOIN AttributeKeyCategoryItemSearchIndex akcisi ON (akcisi.ID = akci.ID)');
						
			if(!$this->ignorePermissions) {
				$this->userQuery .= 'LEFT JOIN AttributeKeyCategoryItemPermissions akcip ON (akcip.ID = akci.ID) ';
				$this->setPermissionFilters();
			}
			
			$this->queryCreated=1;
		}
	}
	
	/* magic method for filtering by page attributes. */
	public function __call($nm, $a) {
		if (substr($nm, 0, 8) == 'filterBy') {
			$txt = Loader::helper('text');
			$attrib = $txt->uncamelcase(substr($nm, 8));
			if (count($a) == 2) {
				$this->filterByAttribute($attrib, $a[0], $a[1]);
			} else {
				$this->filterByAttribute($attrib, $a[0]);
			}
		}		
	}
	
	// Returns an array of AttributeKeyCategoryItems based on current filter settings
	public function get($itemsToGet = 0, $offset = 0, $getAs = 'display') {
		$akcis = array(); 
		$this->createQuery();
		$r = parent::get( $itemsToGet, $offset);
		if($getAs == 'objects' || $getAs == 'object') {
				foreach($r as $row) {
					$no = AttributeKeyCategoryItem::getByID($row['ID']);			
					$akcis[] = $no;
				}
		} elseif($getAs == 'display' || $getAs == 'displayValue' || $getAs == 'displayValues') {
			$akList = AttributeKey::getList($this->akCategoryHandle);
			foreach($r as $row) {
				foreach($akList as $ak) {
					$db = Loader::db();
					$avID = $db->GetOne('SELECT avID FROM AttributeKeyCategoryItemAttributeValues WHERE ID = ? AND akID = ?', 
										array($row['ID'], $ak->akID)
									);
					$av = AttributeValue::getById($avID);
					$akcis[$row['ID']][$ak->akHandle] = $av->getValue('display');
				}
			}
		} else {
			$akList = AttributeKey::getList($this->akCategoryHandle);
			foreach($r as $row) {
				foreach($akList as $ak) {
					$akcis[$row['ID']][$ak->akHandle] = $row['ak_'.$ak->akHandle];
				}
			}
		}
		return $akcis;
	}
	
	public function getTotal(){ 
		$this->createQuery();
		return parent::getTotal();
	}	
	
	public function filterByID($itemID, $comparison = '=') {
		$this->filter('ID', $itemID, $comparison);
	}
	
	public function filterByKeywords($keywords) {
		$db = Loader::db();
		$keywordsExact = $db->quote($keywords);
		$qkeywords = $db->quote('%' . $keywords . '%');
		$ak = new AttributeKey($this->akCategoryHandle);
		$keys = $vtak->getSearchableIndexedList();
		$attribsStr = '';
		foreach ($keys as $ak) {
			$cnt = $ak->getController();			
			$attribsStr.=' OR ' . $cnt->searchKeywords($keywords);
		}
		$this->filter(false, '(akci.ID LIKE ' . $qkeywords . $attribsStr . ')');
	}
}