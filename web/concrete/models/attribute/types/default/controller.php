<?
defined('C5_EXECUTE') or die("Access Denied.");

class DefaultAttributeTypeController extends AttributeTypeController  {

	protected $searchIndexFieldDefinition = 'X NULL';

	public function getValue() {
		$db = Loader::db();
		$value = $db->GetOne("select value from atDefault where avID = ?", array($this->getAttributeValueID()));
		return $value;	
	}

	public function form() {
		if (is_object($this->attributeValue)) {
			$value = Loader::helper('text')->entities($this->getAttributeValue()->getValue());
		}
		print Loader::helper('form')->textarea($this->field('value'), $value);
	}

	public function searchForm($list) {
		$db = Loader::db();
		$list->filterByAttribute($this->attributeKey->getAttributeKeyHandle(), '%' . $this->request('value') . '%', 'like');
		return $list;
	}
	
	public function getDisplaySanitizedValue() {
		return Loader::helper('text')->entities($this->getValue());
	}
	
	public function search() {
		$f = Loader::helper('form');
		print $f->text($this->field('value'), $this->request('value'));
	}
	
	// run when we call setAttribute(), instead of saving through the UI
	public function saveValue($value) {
		$db = Loader::db();
		$db->Replace('atDefault', array('avID' => $this->getAttributeValueID(), 'value' => $value), 'avID', true);
	}
	
	public function saveForm($data) {
		$db = Loader::db();
		$this->saveValue($data['value']);
	}
	
	public function deleteKey() {
		$db = Loader::db();
		$arr = $this->attributeKey->getAttributeValueIDList();
		foreach($arr as $id) {
			$db->Execute('delete from atDefault where avID = ?', array($id));
		}
	}

	public function validateForm($data) {
		return empty($data['value']) ? NULL : TRUE;
	}

	public function deleteValue() {
		$db = Loader::db();
		$db->Execute('delete from atDefault where avID = ?', array($this->getAttributeValueID()));
	}
	
}
