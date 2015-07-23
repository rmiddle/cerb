<?php
class DAO_Skill extends Cerb_ORMHelper {
	const ID = 'id';
	const NAME = 'name';
	const SKILLSET_ID = 'skillset_id';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	
	const CACHE_ALL = 'dao_skills_all';

	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = "INSERT INTO skill () VALUES ()";
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
				CerberusContexts::checkpointChanges(CerberusContexts::CONTEXT_SKILL, $batch_ids);
			}
			
			// Make changes
			parent::_update($batch_ids, 'skill', $fields);
			
			// Send events
			if($check_deltas) {
				// Trigger an event about the changes
				$eventMgr = DevblocksPlatform::getEventService();
				$eventMgr->trigger(
					new Model_DevblocksEvent(
						'dao.skill.update',
						array(
							'fields' => $fields,
						)
					)
				);
				
				// Log the context update
				DevblocksPlatform::markContextChanged(CerberusContexts::CONTEXT_SKILL, $batch_ids);
			}
		}
		
		self::clearCache();
	}
	
	static function updateWhere($fields, $where) {
		parent::_updateWhere('skill', $fields, $where);
		self::clearCache();
	}
	
	/**
	 * @param string $where
	 * @param mixed $sortBy
	 * @param mixed $sortAsc
	 * @param integer $limit
	 * @return Model_Skill[]
	 */
	static function getWhere($where=null, $sortBy=DAO_Skill::NAME, $sortAsc=true, $limit=null, $options=null) {
		$db = DevblocksPlatform::getDatabaseService();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		// SQL
		$sql = "SELECT id, name, skillset_id, created_at, updated_at ".
			"FROM skill ".
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
	 * @return Model_Skill[]
	 */
	static function getAll($nocache=false) {
		$cache = DevblocksPlatform::getCacheService();
		
		if($nocache || null === ($objects = $cache->load(self::CACHE_ALL))) {
			$objects = DAO_Skill::getWhere(
				null,
				DAO_Skill::NAME,
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
	 * @return Model_Skill
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
	
	static function getBySkillset($skillset_id) {
		$skills = self::getAll();
		
		$skills = array_filter($skills, function($skill) use ($skillset_id) {
			if($skill->skillset_id == $skillset_id)
				return true;
			
			return false;
		});
		
		return $skills;
	}
	
	static function getContextSkillLevels($context, $context_id) {
		$db = DevblocksPlatform::getDatabaseService();
		
		// [TODO] Do we cache this for workers?
		// [TODO] Or do we pull worker skill levels separately?
		
		$results = $db->GetArray(sprintf("SELECT skill_id, skill_level FROM context_to_skill WHERE context = %s AND context_id = %d",
			$db->qstr($context),
			$context_id
		));
		
		$skill_levels = array();
		
		foreach($results as $result) {
			$skill_levels[intval($result['skill_id'])] = intval($result['skill_level']);
		}
		
		return $skill_levels;
	}
	
	/**
	 * @param resource $rs
	 * @return Model_Skill[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object = new Model_Skill();
			$object->id = $row['id'];
			$object->name = $row['name'];
			$object->skillset_id = $row['skillset_id'];
			$object->created_at = $row['created_at'];
			$object->updated_at = $row['updated_at'];
			$objects[$object->id] = $object;
		}
		
		mysqli_free_result($rs);
		
		return $objects;
	}
	
	static function count() {
		if(false == ($skills = DAO_Skill::getAll()) || !is_array($skills))
			return 0;
		
		return count($skills);
	}
	
	static function random() {
		return self::_getRandom('skill');
	}
	
	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		$db->ExecuteMaster(sprintf("DELETE FROM skill WHERE id IN (%s)", $ids_list));
		
		// Fire event
		$eventMgr = DevblocksPlatform::getEventService();
		$eventMgr->trigger(
			new Model_DevblocksEvent(
				'context.delete',
				array(
					'context' => CerberusContexts::CONTEXT_SKILL,
					'context_ids' => $ids
				)
			)
		);
		
		self::clearCache();
		
		return true;
	}
	
	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_Skill::getFields();
		
		// Sanitize
		if('*'==substr($sortBy,0,1) || !isset($fields[$sortBy]))
			$sortBy=null;

		list($tables,$wheres) = parent::_parseSearchParams($params, $columns, $fields, $sortBy);
		
		$select_sql = sprintf("SELECT ".
			"skill.id as %s, ".
			"skill.name as %s, ".
			"skill.skillset_id as %s, ".
			"skill.created_at as %s, ".
			"skill.updated_at as %s ",
				SearchFields_Skill::ID,
				SearchFields_Skill::NAME,
				SearchFields_Skill::SKILLSET_ID,
				SearchFields_Skill::CREATED_AT,
				SearchFields_Skill::UPDATED_AT
			);
			
		$join_sql = "FROM skill ".
			(isset($tables['context_link']) ? sprintf("INNER JOIN context_link ON (context_link.to_context = %s AND context_link.to_context_id = skill.id) ", Cerb_ORMHelper::qstr(CerberusContexts::CONTEXT_SKILL)) : " ").
			'';
		
		// Custom field joins
		list($select_sql, $join_sql, $has_multiple_values) = self::_appendSelectJoinSqlForCustomFieldTables(
			$tables,
			$params,
			'skill.id',
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
			array('DAO_Skill', '_translateVirtualParameters'),
			$args
		);
		
		return array(
			'primary_table' => 'skill',
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
			
		$from_context = CerberusContexts::CONTEXT_SKILL;
		$from_index = 'skill.id';
		
		$param_key = $param->field;
		settype($param_key, 'string');
		
		switch($param_key) {
			case SearchFields_Skill::VIRTUAL_CONTEXT_LINK:
				$args['has_multiple_values'] = true;
				self::_searchComponentsVirtualContextLinks($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
				break;
		
			case SearchFields_Skill::VIRTUAL_HAS_FIELDSET:
				self::_searchComponentsVirtualHasFieldset($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
				break;
		
			case SearchFields_Skill::VIRTUAL_WATCHERS:
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
			($has_multiple_values ? 'GROUP BY skill.id ' : '').
			$sort_sql;
			
		if($limit > 0) {
			$rs = $db->SelectLimit($sql,$limit,$page*$limit) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs mysqli_result */
		} else {
			$rs = $db->ExecuteSlave($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs mysqli_result */
			$total = mysqli_num_rows($rs);
		}
		
		$results = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object_id = intval($row[SearchFields_Skill::ID]);
			$results[$object_id] = $row;
		}

		$total = count($results);
		
		if($withCounts) {
			// We can skip counting if we have a less-than-full single page
			if(!(0 == $page && $total < $limit)) {
				$count_sql =
					($has_multiple_values ? "SELECT COUNT(DISTINCT skill.id) " : "SELECT COUNT(skill.id) ").
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

class SearchFields_Skill implements IDevblocksSearchFields {
	const ID = 's_id';
	const NAME = 's_name';
	const SKILLSET_ID = 's_skillset_id';
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
			self::ID => new DevblocksSearchField(self::ID, 'skill', 'id', $translate->_('common.id')),
			self::NAME => new DevblocksSearchField(self::NAME, 'skill', 'name', $translate->_('common.name')),
			self::SKILLSET_ID => new DevblocksSearchField(self::SKILLSET_ID, 'skill', 'skillset_id', $translate->_('common.skillset')),
			self::CREATED_AT => new DevblocksSearchField(self::CREATED_AT, 'skill', 'created_at', $translate->_('common.created')),
			self::UPDATED_AT => new DevblocksSearchField(self::UPDATED_AT, 'skill', 'updated_at', $translate->_('common.updated')),

			self::VIRTUAL_CONTEXT_LINK => new DevblocksSearchField(self::VIRTUAL_CONTEXT_LINK, '*', 'context_link', $translate->_('common.links'), null),
			self::VIRTUAL_HAS_FIELDSET => new DevblocksSearchField(self::VIRTUAL_HAS_FIELDSET, '*', 'has_fieldset', $translate->_('common.fieldset'), null),
			self::VIRTUAL_WATCHERS => new DevblocksSearchField(self::VIRTUAL_WATCHERS, '*', 'workers', $translate->_('common.watchers'), 'WS'),
			
			self::CONTEXT_LINK => new DevblocksSearchField(self::CONTEXT_LINK, 'context_link', 'from_context', null),
			self::CONTEXT_LINK_ID => new DevblocksSearchField(self::CONTEXT_LINK_ID, 'context_link', 'from_context_id', null),
		);
		
		// Custom Fields
		$custom_columns = DevblocksSearchField::getCustomSearchFieldsByContexts(array(
			CerberusContexts::CONTEXT_SKILL,
		));
		
		if(!empty($custom_columns))
			$columns = array_merge($columns, $custom_columns);

		// Sort by label (translation-conscious)
		DevblocksPlatform::sortObjects($columns, 'db_label');

		return $columns;
	}
};

class Model_Skill {
	public $id;
	public $name;
	public $skillset_id;
	public $created_at;
	public $updated_at;
};

class View_Skill extends C4_AbstractView implements IAbstractView_Subtotals, IAbstractView_QuickSearch {
	const DEFAULT_ID = 'skill';

	function __construct() {
		$translate = DevblocksPlatform::getTranslationService();
	
		$this->id = self::DEFAULT_ID;
		$this->name = mb_convert_case($translate->_('common.skills'), MB_CASE_TITLE);
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_Skill::NAME;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_Skill::NAME,
			SearchFields_Skill::SKILLSET_ID,
			SearchFields_Skill::UPDATED_AT,
		);

		$this->addColumnsHidden(array(
			SearchFields_Skill::VIRTUAL_CONTEXT_LINK,
			SearchFields_Skill::VIRTUAL_HAS_FIELDSET,
			SearchFields_Skill::VIRTUAL_WATCHERS,
		));
		
		$this->addParamsHidden(array(
		));
		
		$this->doResetCriteria();
	}

	function getData() {
		$objects = DAO_Skill::search(
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
		return $this->_getDataAsObjects('DAO_Skill', $ids);
	}
	
	function getDataSample($size) {
		return $this->_doGetDataSample('DAO_Skill', $size);
	}

	function getSubtotalFields() {
		$all_fields = $this->getParamsAvailable(true);
		
		$fields = array();

		if(is_array($all_fields))
		foreach($all_fields as $field_key => $field_model) {
			$pass = false;
			
			switch($field_key) {
				// Fields
//				case SearchFields_Skill::EXAMPLE:
//					$pass = true;
//					break;
					
				// Virtuals
				case SearchFields_Skill::VIRTUAL_CONTEXT_LINK:
				case SearchFields_Skill::VIRTUAL_HAS_FIELDSET:
				case SearchFields_Skill::VIRTUAL_WATCHERS:
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
//			case SearchFields_Skill::EXAMPLE_BOOL:
//				$counts = $this->_getSubtotalCountForBooleanColumn('DAO_Skill', $column);
//				break;

//			case SearchFields_Skill::EXAMPLE_STRING:
//				$counts = $this->_getSubtotalCountForStringColumn('DAO_Skill', $column);
//				break;
				
			case SearchFields_Skill::VIRTUAL_CONTEXT_LINK:
				$counts = $this->_getSubtotalCountForContextLinkColumn('DAO_Skill', CerberusContexts::CONTEXT_SKILL, $column);
				break;

			case SearchFields_Skill::VIRTUAL_HAS_FIELDSET:
				$counts = $this->_getSubtotalCountForHasFieldsetColumn('DAO_Skill', CerberusContexts::CONTEXT_SKILL, $column);
				break;
				
			case SearchFields_Skill::VIRTUAL_WATCHERS:
				$counts = $this->_getSubtotalCountForWatcherColumn('DAO_Skill', $column);
				break;
			
			default:
				// Custom fields
				if('cf_' == substr($column,0,3)) {
					$counts = $this->_getSubtotalCountForCustomColumn('DAO_Skill', $column, 'skill.id');
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
					'options' => array('param_key' => SearchFields_Skill::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'created' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_Skill::CREATED_AT),
				),
			'name' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_Skill::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'skillset' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_VIRTUAL,
					'options' => array('param_key' => SearchFields_Skill::SKILLSET_ID),
					'examples' => array(
						'(development)',
						'(sales, dev)',
					),
				),
			'updated' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_Skill::UPDATED_AT),
				),
		);
		
		// Add searchable custom fields
		
		$fields = self::_appendFieldsFromQuickSearchContext(CerberusContexts::CONTEXT_SKILL, $fields, null);
		
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
				case 'skillset':
					$field_key = SearchFields_Skill::SKILLSET_ID;
					$oper = DevblocksSearchCriteria::OPER_IN;
					
					$skillsets = DAO_Skillset::getAll();
					$patterns = DevblocksPlatform::parseCsvString($v);
					$values = array();
					
					if(is_array($values))
					foreach($patterns as $pattern) {
						foreach($skillsets as $skillset_id => $skillset) {
							if(false !== stripos($skillset->name, $pattern))
								$values[$skillset_id] = true;
						}
					}
					
					if(!empty($values)) {
						$params[$field_key] = new DevblocksSearchCriteria(
							$field_key,
							$oper,
							array_keys($values)
						);
					}
					break;
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

		// Skillsets
		$skillsets = DAO_Skillset::getAll();
		$tpl->assign('skillsets', $skillsets);
		
		// Custom fields
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_SKILL);
		$tpl->assign('custom_fields', $custom_fields);

		$tpl->assign('view_template', 'devblocks:cerberusweb.core::internal/skills/view.tpl');
		$tpl->display('devblocks:cerberusweb.core::internal/views/subtotals_and_view.tpl');
	}

	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);

		switch($field) {
			case SearchFields_Skill::NAME:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
				
			case SearchFields_Skill::ID:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__number.tpl');
				break;
				
			case 'placeholder_bool':
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__bool.tpl');
				break;
				
			case SearchFields_Skill::CREATED_AT:
			case SearchFields_Skill::UPDATED_AT:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__date.tpl');
				break;
				
			case SearchFields_Skill::SKILLSET_ID:
				$skillsets = DAO_Skillset::getAll();
				$options = DevblocksPlatform::objectsToStrings($skillsets);
				$tpl->assign('options', $options);
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__list.tpl');
				break;
				
			case SearchFields_Skill::VIRTUAL_CONTEXT_LINK:
				$contexts = Extension_DevblocksContext::getAll(false);
				$tpl->assign('contexts', $contexts);
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_link.tpl');
				break;
				
			case SearchFields_Skill::VIRTUAL_HAS_FIELDSET:
				$this->_renderCriteriaHasFieldset($tpl, CerberusContexts::CONTEXT_SKILL);
				break;
				
			case SearchFields_Skill::VIRTUAL_WATCHERS:
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
			case SearchFields_Skill::SKILLSET_ID:
				$skillsets = DAO_Skillset::getAll();
				$label_map = DevblocksPlatform::objectsToStrings($skillsets);
				$this->_renderCriteriaParamString($param, $label_map);
				break;
				
			default:
				parent::renderCriteriaParam($param);
				break;
		}
	}

	function renderVirtualCriteria($param) {
		$key = $param->field;
		
		$translate = DevblocksPlatform::getTranslationService();
		
		switch($key) {
			case SearchFields_Skill::VIRTUAL_CONTEXT_LINK:
				$this->_renderVirtualContextLinks($param);
				break;
				
			case SearchFields_Skill::VIRTUAL_HAS_FIELDSET:
				$this->_renderVirtualHasFieldset($param);
				break;
			
			case SearchFields_Skill::VIRTUAL_WATCHERS:
				$this->_renderVirtualWatchers($param);
				break;
		}
	}

	function getFields() {
		return SearchFields_Skill::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		switch($field) {
			case SearchFields_Skill::NAME:
				$criteria = $this->_doSetCriteriaString($field, $oper, $value);
				break;
				
			case SearchFields_Skill::ID:
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;
				
			case SearchFields_Skill::CREATED_AT:
			case SearchFields_Skill::UPDATED_AT:
				$criteria = $this->_doSetCriteriaDate($field, $oper);
				break;
				
			case 'placeholder_bool':
				@$bool = DevblocksPlatform::importGPC($_REQUEST['bool'],'integer',1);
				$criteria = new DevblocksSearchCriteria($field,$oper,$bool);
				break;
				
			case SearchFields_Skill::SKILLSET_ID:
				@$options = DevblocksPlatform::importGPC($_POST['options'],'array',array());
				$criteria = new DevblocksSearchCriteria($field, $oper, $options);
				break;
				
			case SearchFields_Skill::VIRTUAL_CONTEXT_LINK:
				@$context_links = DevblocksPlatform::importGPC($_REQUEST['context_link'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$context_links);
				break;
				
			case SearchFields_Skill::VIRTUAL_HAS_FIELDSET:
				@$options = DevblocksPlatform::importGPC($_REQUEST['options'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$options);
				break;
				
			case SearchFields_Skill::VIRTUAL_WATCHERS:
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
				case 'example':
					//$change_fields[DAO_Skill::EXAMPLE] = 'some value';
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
			list($objects,$null) = DAO_Skill::search(
				array(),
				$this->getParams(),
				100,
				$pg++,
				SearchFields_Skill::ID,
				true,
				false
			);
			$ids = array_merge($ids, array_keys($objects));
			 
		} while(!empty($objects));

		$batch_total = count($ids);
		for($x=0;$x<=$batch_total;$x+=100) {
			$batch_ids = array_slice($ids,$x,100);
			
			if(!empty($change_fields)) {
				DAO_Skill::update($batch_ids, $change_fields);
			}

			// Custom Fields
			self::_doBulkSetCustomFields(CerberusContexts::CONTEXT_SKILL, $custom_fields, $batch_ids);
			
			unset($batch_ids);
		}

		unset($ids);
	}
};

class Context_Skill extends Extension_DevblocksContext implements IDevblocksContextProfile, IDevblocksContextPeek { // IDevblocksContextImport
	function getRandom() {
		return DAO_Skill::random();
	}
	
	function profileGetUrl($context_id) {
		if(empty($context_id))
			return '';
	
		$url_writer = DevblocksPlatform::getUrlService();
		$url = $url_writer->writeNoProxy('c=profiles&type=skill&id='.$context_id, true);
		return $url;
	}
	
	function getMeta($context_id) {
		$skill = DAO_Skill::get($context_id);
		$url_writer = DevblocksPlatform::getUrlService();
		
		$url = $this->profileGetUrl($context_id);
		$friendly = DevblocksPlatform::strToPermalink($skill->name);
		
		if(!empty($friendly))
			$url .= '-' . $friendly;
		
		return array(
			'id' => $skill->id,
			'name' => $skill->name,
			'permalink' => $url,
		);
	}
	
	function getDefaultProperties() {
		return array(
			'updated_at',
		);
	}
	
	function getContext($skill, &$token_labels, &$token_values, $prefix=null) {
		if(is_null($prefix))
			$prefix = 'Skill:';
		
		$translate = DevblocksPlatform::getTranslationService();
		$fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_SKILL);

		// Polymorph
		if(is_numeric($skill)) {
			$skill = DAO_Skill::get($skill);
		} elseif($skill instanceof Model_Skill) {
			// It's what we want already.
		} elseif(is_array($skill)) {
			$skill = Cerb_ORMHelper::recastArrayToModel($skill, 'Model_Skill');
		} else {
			$skill = null;
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
		
		$token_values['_context'] = CerberusContexts::CONTEXT_SKILL;
		$token_values['_types'] = $token_types;
		
		if($skill) {
			$token_values['_loaded'] = true;
			$token_values['_label'] = $skill->name;
			$token_values['id'] = $skill->id;
			$token_values['name'] = $skill->name;
			$token_values['updated_at'] = $skill->updated_at;
			
			// Custom fields
			$token_values = $this->_importModelCustomFieldsAsValues($skill, $token_values);
			
			// URL
			$url_writer = DevblocksPlatform::getUrlService();
			$token_values['record_url'] = $url_writer->writeNoProxy(sprintf("c=profiles&type=skill&id=%d-%s",$skill->id, DevblocksPlatform::strToPermalink($skill->name)), true);
		}
		
		return true;
	}

	function lazyLoadContextValues($token, $dictionary) {
		if(!isset($dictionary['id']))
			return;
		
		$context = CerberusContexts::CONTEXT_SKILL;
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
		$view->name = 'Skill';
		/*
		$view->addParams(array(
			SearchFields_Skill::UPDATED_AT => new DevblocksSearchCriteria(SearchFields_Skill::UPDATED_AT,'=',0),
		), true);
		*/
		$view->renderSortBy = SearchFields_Skill::UPDATED_AT;
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
		$view->name = 'Skills';
		
		$params_req = array();
		
		if(!empty($context) && !empty($context_id)) {
			$params_req = array(
				new DevblocksSearchCriteria(SearchFields_Skill::CONTEXT_LINK,'=',$context),
				new DevblocksSearchCriteria(SearchFields_Skill::CONTEXT_LINK_ID,'=',$context_id),
			);
		}
		
		$view->addParamsRequired($params_req, true);
		
		$view->renderTemplate = 'context';
		return $view;
	}
	
	function renderPeekPopup($context_id=0, $view_id='') {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('view_id', $view_id);
		
		if(!empty($context_id) && null != ($skill = DAO_Skill::get($context_id))) {
			$tpl->assign('model', $skill);
		}
		
		$skillsets = DAO_Skillset::getAll();
		$tpl->assign('skillsets', $skillsets);
		
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_SKILL, false);
		$tpl->assign('custom_fields', $custom_fields);
		
		if(!empty($context_id)) {
			$custom_field_values = DAO_CustomFieldValue::getValuesByContextIds(CerberusContexts::CONTEXT_SKILL, $context_id);
			if(isset($custom_field_values[$context_id]))
				$tpl->assign('custom_field_values', $custom_field_values[$context_id]);
		}

		// Comments
		
		$comments = DAO_Comment::getByContext(CerberusContexts::CONTEXT_SKILL, $context_id);
		$comments = array_reverse($comments, true);
		$tpl->assign('comments', $comments);
		
		// Template
		
		if(empty($skillsets)) {
			$tpl->assign('error_message', "You can't add new skills until you add at least one skillset first.");
			$tpl->display('devblocks:cerberusweb.core::internal/peek/peek_error.tpl');
			return;
		}
		
		$tpl->display('devblocks:cerberusweb.core::internal/skills/peek.tpl');
	}
};
