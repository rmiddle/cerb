<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2002-2015, Webgroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerberusweb.com/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://www.cerbweb.com	    http://www.webgroupmedia.com/
***********************************************************************/

class DAO_CommunityTool extends Cerb_ORMHelper {
	const ID = 'id';
	const NAME = 'name';
	const CODE = 'code';
	const EXTENSION_ID = 'extension_id';
	
	public static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		if(!isset($fields[self::CODE]))
			$fields[self::CODE] = self::generateUniqueCode();
		
		$sql = sprintf("INSERT INTO community_tool () ".
			"VALUES ()"
		);
		$db->ExecuteMaster($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg());
		$id = $db->LastInsertId();
		
		self::update($id, $fields);
		
		return $id;
	}

	// [TODO] APIize?
	public static function generateUniqueCode($length=8) {
		$db = DevblocksPlatform::getDatabaseService();
		
		// [JAS]: [TODO] Inf loop check
		do {
			$code = substr(md5(mt_rand(0,1000) * microtime()),0,$length);
			$exists = $db->GetOneMaster(sprintf("SELECT id FROM community_tool WHERE code = %s",$db->qstr($code)));
			
		} while(!empty($exists));
		
		return $code;
	}
	
	public static function update($id, $fields) {
		self::_update($id, 'community_tool', $fields);
	}
	
	/**
	 * Enter description here...
	 *
	 * @param integer $id
	 * @return Model_CommunityTool
	 */
	public static function get($id) {
		if(empty($id))
			return null;
		
		$items = self::getList(array($id));
		
		if(isset($items[$id]))
			return $items[$id];
			
		return NULL;
	}
	
	/**
	 * Enter description here...
	 *
	 * @param string $code
	 * @return Model_CommunityTool
	 */
	public static function getByCode($code) {
		if(empty($code)) return NULL;
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = sprintf("SELECT id FROM community_tool WHERE code = %s",
			$db->qstr($code)
		);
		$id = $db->GetOneSlave($sql);
		
		if(!empty($id)) {
			return self::get($id);
		}
		
		return NULL;
	}
	
	/**
	 * Enter description here...
	 *
	 * @param array $ids
	 * @return Model_CommunityTool[]
	 */
	public static function getList($ids=array()) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = "SELECT id,name,code,extension_id ".
			"FROM community_tool ".
			(!empty($ids) ? sprintf("WHERE id IN (%s) ", implode(',', $ids)) : " ").
			"ORDER BY name"
		;
		$rs = $db->ExecuteSlave($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg());

		return self::_createObjectsFromResultSet($rs);
	}
	
	static function getWhere($where=null) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = "SELECT id,name,code,extension_id ".
			"FROM community_tool ".
			(!empty($where)?sprintf("WHERE %s ",$where):" ").
			"ORDER BY name "
			;
		$rs = $db->ExecuteSlave($sql);
		
		return self::_createObjectsFromResultSet($rs);
	}
	
	static private function _createObjectsFromResultSet($rs) {
		$objects = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object = new Model_CommunityTool();
			$object->id = intval($row['id']);
			$object->name = $row['name'];
			$object->code = $row['code'];
			$object->extension_id = $row['extension_id'];
			$objects[$object->id] = $object;
		}
		
		mysqli_free_result($rs);
		
		return $objects;
	}
	
	public static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();
		
		foreach($ids as $id) {
			@$tool = DAO_CommunityTool::get($id);
			if(empty($tool)) continue;

			/**
			 * [TODO] [JAS] Deleting a community tool needs to run a hook first so the
			 * tool has a chance to clean up its own DB tables abstractly.
			 *
			 * e.g. Knowledgebase instances which store data outside the tool property table
			 *
			 * After this is done, a future DB patch for those plugins should clean up any
			 * orphaned data.
			 */
			
			$sql = sprintf("DELETE FROM community_tool WHERE id = %d", $id);
			$db->ExecuteMaster($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg());
			
			$sql = sprintf("DELETE FROM community_tool_property WHERE tool_code = '%s'", $tool->code);
			$db->ExecuteMaster($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg());
		}
	}

	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_CommunityTool::getFields();
		
		list($tables,$wheres) = parent::_parseSearchParams($params, $columns, $fields, $sortBy);
					
		$select_sql = sprintf("SELECT ".
			"ct.id as %s, ".
			"ct.name as %s, ".
			"ct.code as %s, ".
			"ct.extension_id as %s ",
				SearchFields_CommunityTool::ID,
				SearchFields_CommunityTool::NAME,
				SearchFields_CommunityTool::CODE,
				SearchFields_CommunityTool::EXTENSION_ID
			);
		
		$join_sql = "FROM community_tool ct ";
		
				
		// Custom field joins
		list($select_sql, $join_sql, $has_multiple_values) = self::_appendSelectJoinSqlForCustomFieldTables(
			$tables,
			$params,
			'ct.id',
			$select_sql,
			$join_sql
		);
				
		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = self::_buildSortClause($sortBy, $sortAsc, $fields);
		
		$result = array(
			'primary_table' => 'ct',
			'select' => $select_sql,
			'join' => $join_sql,
			'where' => $where_sql,
			'has_multiple_values' => $has_multiple_values,
			'sort' => $sort_sql,
		);
		
		return $result;
	}
	
	/**
	 * Enter description here...
	 *
	 * @param DevblocksSearchCriteria[] $params
	 * @param integer $limit
	 * @param integer $page
	 * @param string $sortBy
	 * @param boolean $sortAsc
	 * @param boolean $withCounts
	 * @return array
	 */
	static function search($columns, $params, $limit=10, $page=0, $sortBy=null, $sortAsc=null, $withCounts=true) {
		$db = DevblocksPlatform::getDatabaseService();
	
		// Build search queries
		$query_parts = self::getSearchQueryComponents($columns,$params,$sortBy,$sortAsc);

		$select_sql = $query_parts['select'];
		$join_sql = $query_parts['join'];
		$where_sql = $query_parts['where'];
		$has_multiple_values = $query_parts['has_multiple_values'];
		$sort_sql = $query_parts['sort'];
		
		$sql =
			$select_sql.
			$join_sql.
			$where_sql.
			($has_multiple_values ? 'GROUP BY ct.id ' : '').
			$sort_sql;
			
		// [TODO] Could push the select logic down a level too
		if($limit > 0) {
			$rs = $db->SelectLimit($sql,$limit,$page*$limit) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg());
		} else {
			$rs = $db->ExecuteSlave($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg());
			$total = mysqli_num_rows($rs);
		}
		
		$results = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object_id = intval($row[SearchFields_CommunityTool::ID]);
			$results[$object_id] = $row;
		}

		$total = count($results);
		
		if($withCounts) {
			// We can skip counting if we have a less-than-full single page
			if(!(0 == $page && $total < $limit)) {
				$count_sql =
					($has_multiple_values ? "SELECT COUNT(DISTINCT ct.id) " : "SELECT COUNT(ct.id) ").
					$join_sql.
					$where_sql;
				$total = $db->GetOneSlave($count_sql);
			}
		}
		
		mysqli_free_result($rs);
		
		return array($results,$total);
	}
};

class SearchFields_CommunityTool implements IDevblocksSearchFields {
	// Table
	const ID = 'ct_id';
	const NAME = 'ct_name';
	const CODE = 'ct_code';
	const EXTENSION_ID = 'ct_extension_id';
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		$translate = DevblocksPlatform::getTranslationService();
		
		$columns = array(
			SearchFields_CommunityTool::ID => new DevblocksSearchField(SearchFields_CommunityTool::ID, 'ct', 'id', $translate->_('common.id'), null, true),
			SearchFields_CommunityTool::NAME => new DevblocksSearchField(SearchFields_CommunityTool::NAME, 'ct', 'name', $translate->_('common.name'), Model_CustomField::TYPE_SINGLE_LINE, true),
			SearchFields_CommunityTool::CODE => new DevblocksSearchField(SearchFields_CommunityTool::CODE, 'ct', 'code', $translate->_('community_portal.code'), Model_CustomField::TYPE_SINGLE_LINE, true),
			SearchFields_CommunityTool::EXTENSION_ID => new DevblocksSearchField(SearchFields_CommunityTool::EXTENSION_ID, 'ct', 'extension_id', $translate->_('common.extension'), null, true),
		);
		
		// Custom fields with fieldsets
		
		$custom_columns = DevblocksSearchField::getCustomSearchFieldsByContexts(array(
			CerberusContexts::CONTEXT_PORTAL,
		));
		
		if(is_array($custom_columns))
			$columns = array_merge($columns, $custom_columns);
		
		// Sort by label (translation-conscious)
		DevblocksPlatform::sortObjects($columns, 'db_label');

		return $columns;
	}
};

class DAO_CommunityToolProperty extends Cerb_ORMHelper {
	const TOOL_CODE = 'tool_code';
	const PROPERTY_KEY = 'property_key';
	const PROPERTY_VALUE = 'property_value';
	
	const _CACHE_PREFIX = 'um_comtoolprops_';
	
	static function getAllByTool($tool_code) {
		$cache = DevblocksPlatform::getCacheService();

		if(null == ($props = $cache->load(self::_CACHE_PREFIX.$tool_code))) {
			$props = array();
			
			$db = DevblocksPlatform::getDatabaseService();
			
			$sql = sprintf("SELECT property_key, property_value ".
				"FROM community_tool_property ".
				"WHERE tool_code = %s ",
				$db->qstr($tool_code)
			);
			$rs = $db->ExecuteSlave($sql);
			
			$props = array();
			
			while($row = mysqli_fetch_assoc($rs)) {
				$k = $row['property_key'];
				$v = $row['property_value'];
				$props[$k] = $v;
			}
			
			mysqli_free_result($rs);
			
			$cache->save($props, self::_CACHE_PREFIX.$tool_code);
		}
		
		return $props;
	}
	
	static function get($tool_code, $key, $default=null, $json_decode=false) {
		$props = self::getAllByTool($tool_code);
		@$val = $props[$key];
		
		$val = (is_null($val) || (!is_numeric($val) && empty($val))) ? $default : $val;
		
		if($json_decode)
			$val = @json_decode($val, true);
		
		if(false === $val)
			$val = $default;
		
		return $val;
	}
	
	static function set($tool_code, $key, $value, $json_encode=false) {
		$db = DevblocksPlatform::getDatabaseService();
		
		if($json_encode)
			$value = json_encode($value);
		
		$db->ExecuteMaster(sprintf("REPLACE INTO community_tool_property (tool_code, property_key, property_value) ".
			"VALUES (%s, %s, %s)",
			$db->qstr($tool_code),
			$db->qstr($key),
			$db->qstr($value)
		));
		
		// Invalidate cache
		$cache = DevblocksPlatform::getCacheService();
		$cache->remove(self::_CACHE_PREFIX.$tool_code);
	}
};

class DAO_CommunitySession extends Cerb_ORMHelper {
	const SESSION_ID = 'session_id';
	const CREATED = 'created';
	const UPDATED = 'updated';
	const CSRF_TOKEN = 'csrf_token';
	const PROPERTIES = 'properties';
	
	static public function save(Model_CommunitySession $session) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = sprintf("UPDATE community_session SET updated = %d, properties = %s WHERE session_id = %s",
			time(),
			$db->qstr(serialize($session->getProperties())),
			$db->qstr($session->session_id)
		);
		$db->ExecuteMaster($sql);
	}
	
	/**
	 * @param string $session_id
	 * @return Model_CommunitySession
	 */
	static public function get($session_id) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = sprintf("SELECT session_id, created, updated, csrf_token, properties ".
			"FROM community_session ".
			"WHERE session_id = %s",
			$db->qstr($session_id)
		);
		$row = $db->GetRowSlave($sql);
		
		if(empty($row)) {
			$session = self::create($session_id);
		} else {
			$session = new Model_CommunitySession();
			$session->session_id = $row['session_id'];
			$session->created = $row['created'];
			$session->updated = $row['updated'];
			$session->csrf_token = $row['csrf_token'];
			
			if(!empty($row['properties']))
				@$session->setProperties(unserialize($row['properties']));
		}
		
		return $session;
	}
	
	static public function delete($session_id) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = sprintf("DELETE FROM community_session WHERE session_id = %s",
			$db->qstr($session_id)
		);
		$db->ExecuteMaster($sql);
		
		return TRUE;
	}
	
	/**
	 * @param string $session_id
	 * @return Model_CommunitySession
	 */
	static private function create($session_id) {
		$db = DevblocksPlatform::getDatabaseService();

		$session = new Model_CommunitySession();
		$session->session_id = $session_id;
		$session->created = time();
		$session->updated = time();
		$session->csrf_token = CerberusApplication::generatePassword(128);
		
		$sql = sprintf("INSERT INTO community_session (session_id, created, updated, csrf_token, properties) ".
			"VALUES (%s, %d, %d, %s, '')",
			$db->qstr($session->session_id),
			$session->created,
			$session->updated,
			$db->qstr($session->csrf_token)
		);
		$db->ExecuteMaster($sql);
		
		self::gc(); // garbage collection
		
		return $session;
	}
	
	static private function gc() {
		$db = DevblocksPlatform::getDatabaseService();
		$sql = sprintf("DELETE FROM community_session WHERE updated < %d",
			(time()-(60*60)) // 1 hr
		);
		$db->ExecuteMaster($sql);
	}
};

class Model_CommunityTool {
	public $id = 0;
	public $name = '';
	public $code = '';
	public $extension_id = '';
};

class Model_CommunitySession {
	public $session_id = '';
	public $created = 0;
	public $updated = 0;
	public $csrf_token = '';
	private $_properties = array();

	function login(Model_Contact $contact) {
		if(empty($contact) || empty($contact->id)) {
			$this->logout();
			return;
		}
		
		$this->setProperty('sc_login', $contact);
		
		DAO_Contact::update($contact->id, array(
			DAO_Contact::LAST_LOGIN_AT => time(),
		));
	}
	
	function logout() {
		$this->setProperty('sc_login', null);
	}
	
	function setProperties($properties) {
		$this->_properties = $properties;
	}
	
	function getProperties() {
		return $this->_properties;
	}
	
	function setProperty($key, $value) {
		if(null==$value) {
			unset($this->_properties[$key]);
		} else {
			$this->_properties[$key] = $value;
		}
		DAO_CommunitySession::save($this);
	}
	
	function getProperty($key, $default = null) {
		return isset($this->_properties[$key]) ? $this->_properties[$key] : $default;
	}
	
	function destroy() {
		$this->_properties = array();
		DAO_CommunitySession::delete($this->session_id);
	}
};

class View_CommunityPortal extends C4_AbstractView implements IAbstractView_QuickSearch {
	const DEFAULT_ID = 'community_portals';
	const DEFAULT_TITLE = 'Community Portals';

	function __construct() {
		$this->id = self::DEFAULT_ID;
		$this->name = self::DEFAULT_TITLE;
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_CommunityTool::NAME;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_CommunityTool::NAME,
			SearchFields_CommunityTool::CODE,
			SearchFields_CommunityTool::EXTENSION_ID,
		);
		
		$this->addColumnsHidden(array(
			SearchFields_CommunityTool::ID,
		));
		
		$this->addParamsHidden(array(
			SearchFields_CommunityTool::ID,
		));
		$this->addParamsDefault(array(
			//SearchFields_CommunityTool::IS_DISABLED => new DevblocksSearchCriteria(SearchFields_CommunityTool::IS_DISABLED,'=',0),
		));
		
		$this->doResetCriteria();
	}

	function getData() {
		$objects = DAO_CommunityTool::search(
			$this->view_columns,
			$this->getParams(),
			$this->renderLimit,
			$this->renderPage,
			$this->renderSortBy,
			$this->renderSortAsc,
			$this->renderTotal
		);
		return $objects;
	}
	
	function getDataSample($size) {
		return $this->_doGetDataSample('DAO_CommunityTool', $size);
	}
	
	function getQuickSearchFields() {
		$search_fields = SearchFields_CommunityTool::getFields();
		
		$fields = array(
			'_fulltext' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_CommunityTool::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'name' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_CommunityTool::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'portal' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_CommunityTool::CODE, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
		);
		
		// Add is_sortable
		
		$fields = self::_setSortableQuickSearchFields($fields, $search_fields);
		
		// Sort by keys
		
		ksort($fields);
		
		return $fields;
	}	
	
	function getParamsFromQuickSearchFields($fields) {
		$search_fields = $this->getQuickSearchFields();
		$params = DevblocksSearchCriteria::getParamsFromQueryFields($fields, $search_fields);

		// Handle virtual fields and overrides
		if(is_array($fields))
		foreach($fields as $k => $v) {
			switch($k) {
				// ...
			}
		}
		
		return $params;
	}

	function render() {
		$this->_sanitize();
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

		// Tool Manifests
		$tools = DevblocksPlatform::getExtensions('usermeet.tool', false, true);
		$tpl->assign('tool_extensions', $tools);
		
		// Custom fields
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_PORTAL);
		$tpl->assign('custom_fields', $custom_fields);
		
		$tpl->display('devblocks:cerberusweb.core::configuration/section/portals/view.tpl');
	}

	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);
		
		switch($field) {
			case SearchFields_CommunityTool::NAME:
			case SearchFields_CommunityTool::CODE:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
				
			case SearchFields_CommunityTool::EXTENSION_ID:
				$options = array();
				$portals = DevblocksPlatform::getExtensions('usermeet.tool', false);

				foreach($portals as $ext_id => $ext) {
					$options[$ext_id] = $ext->name;
				}
				
				$tpl->assign('options', $options);
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__list.tpl');
				break;
				
			default:
				// Custom Fields
				if('cf_' == substr($field,0,3)) {
					$this->_renderCriteriaCustomField($tpl, substr($field,3));
				} else {
					echo ' ';
				}
				break;
		}
	}

	function renderCriteriaParam($param) {
		$field = $param->field;
		$translate = DevblocksPlatform::getTranslationService();
		$values = !is_array($param->value) ? array($param->value) : $param->value;

		switch($field) {
			case SearchFields_CommunityTool::EXTENSION_ID:
				$portals = DevblocksPlatform::getExtensions('usermeet.tool', false);
				$strings = array();
				
				foreach($values as $val) {
					if(!isset($portals[$val]))
						continue;
					else
						$strings[] = DevblocksPlatform::strEscapeHtml($portals[$val]->name);
				}
				echo implode(", ", $strings);
				break;
			
			default:
				parent::renderCriteriaParam($param);
				break;
		}
	}

	function getFields() {
		return SearchFields_CommunityTool::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		switch($field) {
			case SearchFields_CommunityTool::NAME:
			case SearchFields_CommunityTool::CODE:
				$criteria = $this->_doSetCriteriaString($field, $oper, $value);
				break;
				
			case SearchFields_CommunityTool::EXTENSION_ID:
				@$options = DevblocksPlatform::importGPC($_REQUEST['options'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,$oper,$options);
				break;
				
			default:
				// Custom Fields
				if(substr($field,0,3)=='cf_') {
					$criteria = $this->_doSetCriteriaCustomField($field, substr($field,3));
				}
				break;
		}

		if(!empty($criteria)) {
			$this->addParam($criteria);
			$this->renderPage = 0;
		}
	}
	
	function doBulkUpdate($filter, $do, $ids=array()) {
		@set_time_limit(600); // 10m
		
		$change_fields = array();
		$custom_fields = array();

		// Make sure we have actions
		if(empty($do))
			return;

		// Make sure we have checked items if we want a checked list
		if(0 == strcasecmp($filter,"checks") && empty($ids))
			return;
			
		if(is_array($do))
		foreach($do as $k => $v) {
			switch($k) {
//				case 'status':
//					if(1==intval($v)) { // completed
//						$change_fields[DAO_Task::IS_COMPLETED] = 1;
//						$change_fields[DAO_Task::COMPLETED_DATE] = time();
//					} else { // active
//						$change_fields[DAO_Task::IS_COMPLETED] = 0;
//						$change_fields[DAO_Task::COMPLETED_DATE] = 0;
//					}
//					break;
				default:
					// Custom fields
					if(substr($k,0,3)=="cf_") {
						$custom_fields[substr($k,3)] = $v;
					}
			}
		}
		
		$pg = 0;

		if(empty($ids))
		do {
			list($objects,$null) = DAO_CommunityTool::search(
				array(),
				$this->getParams(),
				100,
				$pg++,
				SearchFields_CommunityTool::ID,
				true,
				false
			);
			 
			$ids = array_merge($ids, array_keys($objects));
			 
		} while(!empty($objects));

		$batch_total = count($ids);
		for($x=0;$x<=$batch_total;$x+=100) {
			$batch_ids = array_slice($ids,$x,100);
			DAO_CommunityTool::update($batch_ids, $change_fields);
			
			// Custom Fields
			self::_doBulkSetCustomFields(CerberusContexts::CONTEXT_PORTAL, $custom_fields, $batch_ids);
			
			unset($batch_ids);
		}

		unset($ids);
	}
};