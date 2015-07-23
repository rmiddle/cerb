<?php
class DAO_Skillset extends Cerb_ORMHelper {
	const ID = 'id';
	const NAME = 'name';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	
	const CACHE_ALL = 'dao_skillsets_all';

	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = "INSERT INTO skillset () VALUES ()";
		$db->ExecuteMaster($sql);
		$id = $db->LastInsertId();
		
		self::update($id, $fields);
		
		return $id;
	}
	
	static function update($ids, $fields, $check_deltas=true) {
		if(!is_array($ids))
			$ids = array($ids);
		
		// Make a diff for the requested objects in batches
		
		$chunks = array_chunk($ids, 100, true);
		while($batch_ids = array_shift($chunks)) {
			if(empty($batch_ids))
				continue;
				
			// Send events
			if($check_deltas) {
				CerberusContexts::checkpointChanges(CerberusContexts::CONTEXT_SKILLSET, $batch_ids);
			}
			
			// Make changes
			parent::_update($batch_ids, 'skillset', $fields);
			
			// Send events
			if($check_deltas) {
				// Trigger an event about the changes
				$eventMgr = DevblocksPlatform::getEventService();
				$eventMgr->trigger(
					new Model_DevblocksEvent(
						'dao.skillset.update',
						array(
							'fields' => $fields,
						)
					)
				);
				
				// Log the context update
				DevblocksPlatform::markContextChanged(CerberusContexts::CONTEXT_SKILLSET, $batch_ids);
			}
		}
		
		self::clearCache();
	}
	
	static function updateWhere($fields, $where) {
		parent::_updateWhere('skillset', $fields, $where);
		self::clearCache();
	}
	
	/**
	 * @param string $where
	 * @param mixed $sortBy
	 * @param mixed $sortAsc
	 * @param integer $limit
	 * @return Model_Skillset[]
	 */
	static function getWhere($where=null, $sortBy=DAO_Skillset::NAME, $sortAsc=true, $limit=null, $options=null) {
		$db = DevblocksPlatform::getDatabaseService();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		// SQL
		$sql = "SELECT id, name, created_at, updated_at ".
			"FROM skillset ".
			$where_sql.
			$sort_sql.
			$limit_sql
		;
		
		if($options & Cerb_ORMHelper::OPT_GET_MASTER_ONLY) {
			$rs = $db->ExecuteMaster($sql);
		} else {
			$rs = $db->ExecuteSlave($sql);
		}
		
		return self::_getObjectsFromResult($rs);
	}

	/**
	 * 
	 * @param bool $nocache
	 * @return Model_Skillset[]
	 */
	static function getAll($nocache=false) {
		$cache = DevblocksPlatform::getCacheService();
		
		if($nocache || null === ($objects = $cache->load(self::CACHE_ALL))) {
			$objects = DAO_Skillset::getWhere(
				null,
				DAO_Skillset::NAME,
				true,
				null,
				Cerb_ORMHelper::OPT_GET_MASTER_ONLY
			);
			$cache->save($objects, self::CACHE_ALL);
		}
		
		return $objects;
	}
	
	/**
	 * @param integer $id
	 * @return Model_Skillset
	 */
	static function get($id) {
		if(empty($id))
			return null;
		
		$objects = self::getWhere(sprintf("%s = %d",
			self::ID,
			$id
		));
		
		if(isset($objects[$id]))
			return $objects[$id];
		
		return null;
	}
	
	static function getWithSkillsForContext($context, $context_id) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$skillsets = DAO_Skillset::getAll();
		$skills = DAO_Skill::getAll();
		
		$results = array();
		
		$skill_to_context = $db->GetArray(sprintf("SELECT cts.skill_id, cts.skill_level FROM context_to_skill cts WHERE context = %s and context_id = %d",
			$db->qstr($context),
			$context_id
		));
		
		foreach($skill_to_context as $cts) {
			$skill_id = $cts['skill_id'];
			$skill_level = $cts['skill_level'];
			
			if(!isset($skills[$skill_id]))
				continue;
			
			$skillset_id = $skills[$skill_id]->skillset_id;
			
			if(!isset($results[$skillset_id])) {
				$results[$skillset_id] = $skillsets[$skillset_id];
				$results[$skillset_id]->skills = $results[$skillset_id]->getSkills();
			}
			
			$results[$skillset_id]->skills[$skill_id]->level = $skill_level;
		}
		
		return $results;
	}
	
	/**
	 * @param resource $rs
	 * @return Model_Skillset[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object = new Model_Skillset();
			$object->id = $row['id'];
			$object->name = $row['name'];
			$object->created_at = $row['created_at'];
			$object->updated_at = $row['updated_at'];
			$objects[$object->id] = $object;
		}
		
		mysqli_free_result($rs);
		
		return $objects;
	}
	
	static function random() {
		return self::_getRandom('skillset');
	}
	
	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		$db->ExecuteMaster(sprintf("DELETE FROM skillset WHERE id IN (%s)", $ids_list));
		
		// Fire event
		$eventMgr = DevblocksPlatform::getEventService();
		$eventMgr->trigger(
			new Model_DevblocksEvent(
				'context.delete',
				array(
					'context' => CerberusContexts::CONTEXT_SKILLSET,
					'context_ids' => $ids
				)
			)
		);
		
		self::clearCache();
		
		return true;
	}
	
	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_Skillset::getFields();
		
		// Sanitize
		if('*'==substr($sortBy,0,1) || !isset($fields[$sortBy]))
			$sortBy=null;

		list($tables,$wheres) = parent::_parseSearchParams($params, $columns, $fields, $sortBy);
		
		$select_sql = sprintf("SELECT ".
			"skillset.id as %s, ".
			"skillset.name as %s, ".
			"skillset.created_at as %s, ".
			"skillset.updated_at as %s ",
				SearchFields_Skillset::ID,
				SearchFields_Skillset::NAME,
				SearchFields_Skillset::CREATED_AT,
				SearchFields_Skillset::UPDATED_AT
			);
			
		$join_sql = "FROM skillset ".
			(isset($tables['context_link']) ? sprintf("INNER JOIN context_link ON (context_link.to_context = %s AND context_link.to_context_id = skillset.id) ", Cerb_ORMHelper::qstr(CerberusContexts::CONTEXT_SKILLSET)) : " ").
			'';
		
		// Custom field joins
		list($select_sql, $join_sql, $has_multiple_values) = self::_appendSelectJoinSqlForCustomFieldTables(
			$tables,
			$params,
			'skillset.id',
			$select_sql,
			$join_sql
		);
				
		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = (!empty($sortBy)) ? sprintf("ORDER BY %s %s ",$sortBy,($sortAsc || is_null($sortAsc))?"ASC":"DESC") : " ";
	
		// Virtuals
		
		$args = array(
			'join_sql' => &$join_sql,
			'where_sql' => &$where_sql,
			'tables' => &$tables,
			'has_multiple_values' => &$has_multiple_values
		);
	
		array_walk_recursive(
			$params,
			array('DAO_Skillset', '_translateVirtualParameters'),
			$args
		);
		
		return array(
			'primary_table' => 'skillset',
			'select' => $select_sql,
			'join' => $join_sql,
			'where' => $where_sql,
			'has_multiple_values' => $has_multiple_values,
			'sort' => $sort_sql,
		);
	}
	
	private static function _translateVirtualParameters($param, $key, &$args) {
		if(!is_a($param, 'DevblocksSearchCriteria'))
			return;
			
		$from_context = CerberusContexts::CONTEXT_SKILLSET;
		$from_index = 'skillset.id';
		
		$param_key = $param->field;
		settype($param_key, 'string');
		
		switch($param_key) {
			case SearchFields_Skillset::VIRTUAL_CONTEXT_LINK:
				$args['has_multiple_values'] = true;
				self::_searchComponentsVirtualContextLinks($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
				break;
		
			case SearchFields_Skillset::VIRTUAL_HAS_FIELDSET:
				self::_searchComponentsVirtualHasFieldset($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
				break;
		
			case SearchFields_Skillset::VIRTUAL_WATCHERS:
				$args['has_multiple_values'] = true;
				self::_searchComponentsVirtualWatchers($param, $from_context, $from_index, $args['join_sql'], $args['where_sql'], $args['tables']);
				break;
		}
	}
	
	/**
	 * Enter description here...
	 *
	 * @param array $columns
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
			($has_multiple_values ? 'GROUP BY skillset.id ' : '').
			$sort_sql;
			
		if($limit > 0) {
			$rs = $db->SelectLimit($sql,$limit,$page*$limit) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs mysqli_result */
		} else {
			$rs = $db->ExecuteSlave($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs mysqli_result */
			$total = mysqli_num_rows($rs);
		}
		
		$results = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object_id = intval($row[SearchFields_Skillset::ID]);
			$results[$object_id] = $row;
		}

		$total = count($results);
		
		if($withCounts) {
			// We can skip counting if we have a less-than-full single page
			if(!(0 == $page && $total < $limit)) {
				$count_sql =
					($has_multiple_values ? "SELECT COUNT(DISTINCT skillset.id) " : "SELECT COUNT(skillset.id) ").
					$join_sql.
					$where_sql;
				$total = $db->GetOneSlave($count_sql);
			}
		}
		
		mysqli_free_result($rs);
		
		return array($results,$total);
	}

	public static function clearCache() {
		// Invalidate cache on changes
		$cache = DevblocksPlatform::getCacheService();
		$cache->remove(self::CACHE_ALL);
	}
	
};

class SearchFields_Skillset implements IDevblocksSearchFields {
	const ID = 's_id';
	const NAME = 's_name';
	const CREATED_AT = 's_created_at';
	const UPDATED_AT = 's_updated_at';

	const VIRTUAL_CONTEXT_LINK = '*_context_link';
	const VIRTUAL_HAS_FIELDSET = '*_has_fieldset';
	const VIRTUAL_WATCHERS = '*_workers';
	
	const CONTEXT_LINK = 'cl_context_from';
	const CONTEXT_LINK_ID = 'cl_context_from_id';
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		$translate = DevblocksPlatform::getTranslationService();
		
		$columns = array(
			self::ID => new DevblocksSearchField(self::ID, 'skillset', 'id', $translate->_('common.id')),
			self::NAME => new DevblocksSearchField(self::NAME, 'skillset', 'name', $translate->_('common.name')),
			self::CREATED_AT => new DevblocksSearchField(self::CREATED_AT, 'skillset', 'created_at', $translate->_('common.created')),
			self::UPDATED_AT => new DevblocksSearchField(self::UPDATED_AT, 'skillset', 'updated_at', $translate->_('common.updated')),

			self::VIRTUAL_CONTEXT_LINK => new DevblocksSearchField(self::VIRTUAL_CONTEXT_LINK, '*', 'context_link', $translate->_('common.links'), null),
			self::VIRTUAL_HAS_FIELDSET => new DevblocksSearchField(self::VIRTUAL_HAS_FIELDSET, '*', 'has_fieldset', $translate->_('common.fieldset'), null),
			self::VIRTUAL_WATCHERS => new DevblocksSearchField(self::VIRTUAL_WATCHERS, '*', 'workers', $translate->_('common.watchers'), 'WS'),
			
			self::CONTEXT_LINK => new DevblocksSearchField(self::CONTEXT_LINK, 'context_link', 'from_context', null),
			self::CONTEXT_LINK_ID => new DevblocksSearchField(self::CONTEXT_LINK_ID, 'context_link', 'from_context_id', null),
		);
		
		// Custom Fields
		$custom_columns = DevblocksSearchField::getCustomSearchFieldsByContexts(array(
			CerberusContexts::CONTEXT_SKILLSET,
		));
		
		if(!empty($custom_columns))
			$columns = array_merge($columns, $custom_columns);

		// Sort by label (translation-conscious)
		DevblocksPlatform::sortObjects($columns, 'db_label');

		return $columns;
	}
};

class Model_Skillset {
	public $id;
	public $name;
	public $created_at;
	public $updated_at;
	
	public function getSkills() {
		return DAO_Skill::getBySkillset($this->id);
	}
	
	public function __toString() {
		return $this->name;
	}
};

class View_Skillset extends C4_AbstractView implements IAbstractView_Subtotals, IAbstractView_QuickSearch {
	const DEFAULT_ID = 'skillset';

	function __construct() {
		$translate = DevblocksPlatform::getTranslationService();
	
		$this->id = self::DEFAULT_ID;
		$this->name = mb_convert_case($translate->_('common.skillsets'), MB_CASE_TITLE);
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_Skillset::NAME;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_Skillset::NAME,
			SearchFields_Skillset::UPDATED_AT,
		);
		
		$this->addColumnsHidden(array(
			SearchFields_Skillset::VIRTUAL_CONTEXT_LINK,
			SearchFields_Skillset::VIRTUAL_HAS_FIELDSET,
			SearchFields_Skillset::VIRTUAL_WATCHERS,
		));
		
		$this->addParamsHidden(array(
		));
		
		$this->doResetCriteria();
	}

	function getData() {
		$objects = DAO_Skillset::search(
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
	
	function getDataAsObjects($ids=null) {
		return $this->_getDataAsObjects('DAO_Skillset', $ids);
	}
	
	function getDataSample($size) {
		return $this->_doGetDataSample('DAO_Skillset', $size);
	}

	function getSubtotalFields() {
		$all_fields = $this->getParamsAvailable(true);
		
		$fields = array();

		if(is_array($all_fields))
		foreach($all_fields as $field_key => $field_model) {
			$pass = false;
			
			switch($field_key) {
				// Fields
//				case SearchFields_Skillset::EXAMPLE:
//					$pass = true;
//					break;
					
				// Virtuals
				case SearchFields_Skillset::VIRTUAL_CONTEXT_LINK:
				case SearchFields_Skillset::VIRTUAL_HAS_FIELDSET:
				case SearchFields_Skillset::VIRTUAL_WATCHERS:
					$pass = true;
					break;
					
				// Valid custom fields
				default:
					if('cf_' == substr($field_key,0,3))
						$pass = $this->_canSubtotalCustomField($field_key);
					break;
			}
			
			if($pass)
				$fields[$field_key] = $field_model;
		}
		
		return $fields;
	}
	
	function getSubtotalCounts($column) {
		$counts = array();
		$fields = $this->getFields();

		if(!isset($fields[$column]))
			return array();
		
		switch($column) {
//			case SearchFields_Skillset::EXAMPLE_BOOL:
//				$counts = $this->_getSubtotalCountForBooleanColumn('DAO_Skillset', $column);
//				break;

//			case SearchFields_Skillset::EXAMPLE_STRING:
//				$counts = $this->_getSubtotalCountForStringColumn('DAO_Skillset', $column);
//				break;
				
			case SearchFields_Skillset::VIRTUAL_CONTEXT_LINK:
				$counts = $this->_getSubtotalCountForContextLinkColumn('DAO_Skillset', CerberusContexts::CONTEXT_SKILLSET, $column);
				break;

			case SearchFields_Skillset::VIRTUAL_HAS_FIELDSET:
				$counts = $this->_getSubtotalCountForHasFieldsetColumn('DAO_Skillset', CerberusContexts::CONTEXT_SKILLSET, $column);
				break;
				
			case SearchFields_Skillset::VIRTUAL_WATCHERS:
				$counts = $this->_getSubtotalCountForWatcherColumn('DAO_Skillset', $column);
				break;
			
			default:
				// Custom fields
				if('cf_' == substr($column,0,3)) {
					$counts = $this->_getSubtotalCountForCustomColumn('DAO_Skillset', $column, 'skillset.id');
				}
				
				break;
		}
		
		return $counts;
	}
	
	function getQuickSearchFields() {
		$fields = array(
			'_fulltext' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_Skillset::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'created' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_Skillset::CREATED_AT),
				),
			'name' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_Skillset::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'updated' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_Skillset::UPDATED_AT),
				),
		);
		
		// Add searchable custom fields
		
		$fields = self::_appendFieldsFromQuickSearchContext(CerberusContexts::CONTEXT_SKILLSET, $fields, null);
		
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
		
		$this->renderPage = 0;
		$this->addParams($params, true);
		
		return $params;
	}
	
	function render() {
		$this->_sanitize();
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

		// Custom fields
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_SKILLSET);
		$tpl->assign('custom_fields', $custom_fields);

		$tpl->assign('view_template', 'devblocks:cerberusweb.core::internal/skillsets/view.tpl');
		$tpl->display('devblocks:cerberusweb.core::internal/views/subtotals_and_view.tpl');
	}

	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);

		switch($field) {
			case SearchFields_Skillset::NAME:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
				
			case SearchFields_Skillset::ID:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__number.tpl');
				break;
				
			case 'placeholder_bool':
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__bool.tpl');
				break;
				
			case SearchFields_Skillset::CREATED_AT:
			case SearchFields_Skillset::UPDATED_AT:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__date.tpl');
				break;
				
			case SearchFields_Skillset::VIRTUAL_CONTEXT_LINK:
				$contexts = Extension_DevblocksContext::getAll(false);
				$tpl->assign('contexts', $contexts);
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_link.tpl');
				break;
				
			case SearchFields_Skillset::VIRTUAL_HAS_FIELDSET:
				$this->_renderCriteriaHasFieldset($tpl, CerberusContexts::CONTEXT_SKILLSET);
				break;
				
			case SearchFields_Skillset::VIRTUAL_WATCHERS:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_worker.tpl');
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
		$values = !is_array($param->value) ? array($param->value) : $param->value;

		switch($field) {
			default:
				parent::renderCriteriaParam($param);
				break;
		}
	}

	function renderVirtualCriteria($param) {
		$key = $param->field;
		
		$translate = DevblocksPlatform::getTranslationService();
		
		switch($key) {
			case SearchFields_Skillset::VIRTUAL_CONTEXT_LINK:
				$this->_renderVirtualContextLinks($param);
				break;
				
			case SearchFields_Skillset::VIRTUAL_HAS_FIELDSET:
				$this->_renderVirtualHasFieldset($param);
				break;
			
			case SearchFields_Skillset::VIRTUAL_WATCHERS:
				$this->_renderVirtualWatchers($param);
				break;
		}
	}

	function getFields() {
		return SearchFields_Skillset::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		switch($field) {
			case SearchFields_Skillset::NAME:
				$criteria = $this->_doSetCriteriaString($field, $oper, $value);
				break;
				
			case SearchFields_Skillset::ID:
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;
				
			case SearchFields_Skillset::CREATED_AT:
			case SearchFields_Skillset::UPDATED_AT:
				$criteria = $this->_doSetCriteriaDate($field, $oper);
				break;
				
			case 'placeholder_bool':
				@$bool = DevblocksPlatform::importGPC($_REQUEST['bool'],'integer',1);
				$criteria = new DevblocksSearchCriteria($field,$oper,$bool);
				break;
				
			case SearchFields_Skillset::VIRTUAL_CONTEXT_LINK:
				@$context_links = DevblocksPlatform::importGPC($_REQUEST['context_link'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$context_links);
				break;
				
			case SearchFields_Skillset::VIRTUAL_HAS_FIELDSET:
				@$options = DevblocksPlatform::importGPC($_REQUEST['options'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$options);
				break;
				
			case SearchFields_Skillset::VIRTUAL_WATCHERS:
				@$worker_ids = DevblocksPlatform::importGPC($_REQUEST['worker_id'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,$oper,$worker_ids);
				break;
				
			default:
				// Custom Fields
				if(substr($field,0,3)=='cf_') {
					$criteria = $this->_doSetCriteriaCustomField($field, substr($field,3));
				}
				break;
		}

		if(!empty($criteria)) {
			$this->addParam($criteria, $field);
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
				// [TODO] Implement actions
				case 'example':
					//$change_fields[DAO_Skillset::EXAMPLE] = 'some value';
					break;
					
				default:
					// Custom fields
					if(substr($k,0,3)=="cf_") {
						$custom_fields[substr($k,3)] = $v;
					}
					break;
			}
		}

		$pg = 0;

		if(empty($ids))
		do {
			list($objects,$null) = DAO_Skillset::search(
				array(),
				$this->getParams(),
				100,
				$pg++,
				SearchFields_Skillset::ID,
				true,
				false
			);
			$ids = array_merge($ids, array_keys($objects));
			 
		} while(!empty($objects));

		$batch_total = count($ids);
		for($x=0;$x<=$batch_total;$x+=100) {
			$batch_ids = array_slice($ids,$x,100);
			
			if(!empty($change_fields)) {
				DAO_Skillset::update($batch_ids, $change_fields);
			}

			// Custom Fields
			self::_doBulkSetCustomFields(CerberusContexts::CONTEXT_SKILLSET, $custom_fields, $batch_ids);
			
			unset($batch_ids);
		}

		unset($ids);
	}
};

class Context_Skillset extends Extension_DevblocksContext implements IDevblocksContextProfile, IDevblocksContextPeek { // IDevblocksContextImport
	function getRandom() {
		return DAO_Skillset::random();
	}
	
	function profileGetUrl($context_id) {
		if(empty($context_id))
			return '';
	
		$url_writer = DevblocksPlatform::getUrlService();
		$url = $url_writer->writeNoProxy('c=profiles&type=skillset&id='.$context_id, true);
		return $url;
	}
	
	function getMeta($context_id) {
		$skillset = DAO_Skillset::get($context_id);
		$url_writer = DevblocksPlatform::getUrlService();
		
		$url = $this->profileGetUrl($context_id);
		$friendly = DevblocksPlatform::strToPermalink($skillset->name);
		
		if(!empty($friendly))
			$url .= '-' . $friendly;
		
		return array(
			'id' => $skillset->id,
			'name' => $skillset->name,
			'permalink' => $url,
		);
	}
	
	function getDefaultProperties() {
		return array(
			'updated_at',
		);
	}
	
	function getContext($skillset, &$token_labels, &$token_values, $prefix=null) {
		if(is_null($prefix))
			$prefix = 'Skillset:';
		
		$translate = DevblocksPlatform::getTranslationService();
		$fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_SKILLSET);

		// Polymorph
		if(is_numeric($skillset)) {
			$skillset = DAO_Skillset::get($skillset);
		} elseif($skillset instanceof Model_Skillset) {
			// It's what we want already.
		} elseif(is_array($skillset)) {
			$skillset = Cerb_ORMHelper::recastArrayToModel($skillset, 'Model_Skillset');
		} else {
			$skillset = null;
		}
		
		// Token labels
		$token_labels = array(
			'_label' => $prefix,
			'id' => $prefix.$translate->_('common.id'),
			'name' => $prefix.$translate->_('common.name'),
			'updated_at' => $prefix.$translate->_('common.updated'),
			'record_url' => $prefix.$translate->_('common.url.record'),
		);
		
		// Token types
		$token_types = array(
			'_label' => 'context_url',
			'id' => Model_CustomField::TYPE_NUMBER,
			'name' => Model_CustomField::TYPE_SINGLE_LINE,
			'updated_at' => Model_CustomField::TYPE_DATE,
			'record_url' => Model_CustomField::TYPE_URL,
		);
		
		// Custom field/fieldset token labels
		if(false !== ($custom_field_labels = $this->_getTokenLabelsFromCustomFields($fields, $prefix)) && is_array($custom_field_labels))
			$token_labels = array_merge($token_labels, $custom_field_labels);
		
		// Custom field/fieldset token types
		if(false !== ($custom_field_types = $this->_getTokenTypesFromCustomFields($fields, $prefix)) && is_array($custom_field_types))
			$token_types = array_merge($token_types, $custom_field_types);
		
		// Token values
		$token_values = array();
		
		$token_values['_context'] = CerberusContexts::CONTEXT_SKILLSET;
		$token_values['_types'] = $token_types;
		
		if($skillset) {
			$token_values['_loaded'] = true;
			$token_values['_label'] = $skillset->name;
			$token_values['id'] = $skillset->id;
			$token_values['name'] = $skillset->name;
			$token_values['updated_at'] = $skillset->updated_at;
			
			// Custom fields
			$token_values = $this->_importModelCustomFieldsAsValues($skillset, $token_values);
			
			// URL
			$url_writer = DevblocksPlatform::getUrlService();
			$token_values['record_url'] = $url_writer->writeNoProxy(sprintf("c=profiles&type=skillset&id=%d-%s",$skillset->id, DevblocksPlatform::strToPermalink($skillset->name)), true);
		}
		
		return true;
	}

	function lazyLoadContextValues($token, $dictionary) {
		if(!isset($dictionary['id']))
			return;
		
		$context = CerberusContexts::CONTEXT_SKILLSET;
		$context_id = $dictionary['id'];
		
		@$is_loaded = $dictionary['_loaded'];
		$values = array();
		
		if(!$is_loaded) {
			$labels = array();
			CerberusContexts::getContext($context, $context_id, $labels, $values, null, true);
		}
		
		switch($token) {
			case 'watchers':
				$watchers = array(
					$token => CerberusContexts::getWatchers($context, $context_id, true),
				);
				$values = array_merge($values, $watchers);
				break;
				
			default:
				if(substr($token,0,7) == 'custom_') {
					$fields = $this->_lazyLoadCustomFields($token, $context, $context_id);
					$values = array_merge($values, $fields);
				}
				break;
		}
		
		return $values;
	}
	
	function getChooserView($view_id=null) {
		$active_worker = CerberusApplication::getActiveWorker();

		if(empty($view_id))
			$view_id = 'chooser_'.str_replace('.','_',$this->id).time().mt_rand(0,9999);
	
		// View
		$defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass());
		$defaults->id = $view_id;
		$defaults->is_ephemeral = true;

		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->name = 'Skillset';
		/*
		$view->addParams(array(
			SearchFields_Skillset::UPDATED_AT => new DevblocksSearchCriteria(SearchFields_Skillset::UPDATED_AT,'=',0),
		), true);
		*/
		$view->renderSortBy = SearchFields_Skillset::UPDATED_AT;
		$view->renderSortAsc = false;
		$view->renderLimit = 10;
		$view->renderFilters = false;
		$view->renderTemplate = 'contextlinks_chooser';
		
		return $view;
	}
	
	function getView($context=null, $context_id=null, $options=array(), $view_id=null) {
		$view_id = !empty($view_id) ? $view_id : str_replace('.','_',$this->id);
		
		$defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass());
		$defaults->id = $view_id;

		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->name = 'Skillsets';
		
		$params_req = array();
		
		if(!empty($context) && !empty($context_id)) {
			$params_req = array(
				new DevblocksSearchCriteria(SearchFields_Skillset::CONTEXT_LINK,'=',$context),
				new DevblocksSearchCriteria(SearchFields_Skillset::CONTEXT_LINK_ID,'=',$context_id),
			);
		}
		
		$view->addParamsRequired($params_req, true);
		
		$view->renderTemplate = 'context';
		return $view;
	}
	
	function renderPeekPopup($context_id=0, $view_id='') {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('view_id', $view_id);
		
		if(!empty($context_id) && null != ($skillset = DAO_Skillset::get($context_id))) {
			$tpl->assign('model', $skillset);
		}
		
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_SKILLSET, false);
		$tpl->assign('custom_fields', $custom_fields);
		
		if(!empty($context_id)) {
			$custom_field_values = DAO_CustomFieldValue::getValuesByContextIds(CerberusContexts::CONTEXT_SKILLSET, $context_id);
			if(isset($custom_field_values[$context_id]))
				$tpl->assign('custom_field_values', $custom_field_values[$context_id]);
		}

		// Comments
		$comments = DAO_Comment::getByContext(CerberusContexts::CONTEXT_SKILLSET, $context_id);
		$comments = array_reverse($comments, true);
		$tpl->assign('comments', $comments);
		
		$tpl->display('devblocks:cerberusweb.core::internal/skillsets/peek.tpl');
	}
};
