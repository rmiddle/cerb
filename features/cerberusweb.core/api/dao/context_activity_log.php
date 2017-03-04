<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2002-2017, Webgroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerb.ai/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://cerb.ai	    http://webgroup.media
***********************************************************************/

class DAO_ContextActivityLog extends Cerb_ORMHelper {
	const ID = 'id';
	const ACTIVITY_POINT = 'activity_point';
	const ACTOR_CONTEXT = 'actor_context';
	const ACTOR_CONTEXT_ID = 'actor_context_id';
	const TARGET_CONTEXT = 'target_context';
	const TARGET_CONTEXT_ID = 'target_context_id';
	const CREATED = 'created';
	const ENTRY_JSON = 'entry_json';

	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();

		@$target_context = $fields[DAO_ContextActivityLog::TARGET_CONTEXT];
		@$target_context_id = $fields[DAO_ContextActivityLog::TARGET_CONTEXT_ID];
		
		if(is_null($target_context))
			$fields[DAO_ContextActivityLog::TARGET_CONTEXT] = '';
		
		if(is_null($target_context_id))
			$fields[DAO_ContextActivityLog::TARGET_CONTEXT_ID] = 0;
		
		// [TODO] This should be an example for insertion of other immutable records
		$id = parent::_insert('context_activity_log', $fields);
		
		return $id;
	}
	
	/**
	 * @param string $where
	 * @param mixed $sortBy
	 * @param mixed $sortAsc
	 * @param integer $limit
	 * @return Model_ContextActivityLog[]
	 */
	static function getWhere($where=null, $sortBy=null, $sortAsc=true, $limit=null) {
		$db = DevblocksPlatform::getDatabaseService();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		// SQL
		$sql = "SELECT id, activity_point, actor_context, actor_context_id, target_context, target_context_id, created, entry_json ".
			"FROM context_activity_log ".
			$where_sql.
			$sort_sql.
			$limit_sql
		;
		$rs = $db->ExecuteSlave($sql);
		
		return self::_getObjectsFromResult($rs);
	}

	/**
	 * @param integer $id
	 * @return Model_ContextActivityLog	 */
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
	
	/**
	 * 
	 * @param string $actor_context
	 * @param integer $actor_context_id
	 * @return Model_ContextActivityLog|NULL
	 */
	static function getLatestEntriesByActor($actor_context, $actor_context_id, $limit = 1, $only_activities = array()) {
		// Filter to only this worker
		$sql = sprintf("%s = %s AND %s = %d",
			self::escape(DAO_ContextActivityLog::ACTOR_CONTEXT),
			self::qstr($actor_context),
			self::escape(DAO_ContextActivityLog::ACTOR_CONTEXT_ID),
			$actor_context_id
		);
		
		// Are we're limiting our search to only some activities?
		if(is_array($only_activities) && !empty($only_activities)) {
			array_walk($only_activities, function($k, &$v) {
				$v = self::qstr($v);
			});
			
			$sql .= sprintf(" AND %s IN (%s)",
				self::escape(DAO_ContextActivityLog::ACTIVITY_POINT),
				implode(',', $only_activities)
			);
		}
		
		// Grab the entries
		$results = self::getWhere(
			$sql,
			DAO_ContextActivityLog::CREATED,
			false,
			max(1, intval($limit))
		);
		
		if(is_array($results) && !empty($results))
			return array_shift($results);
		
		return NULL;
	}
	
	/**
	 * @param resource $rs
	 * @return Model_ContextActivityLog[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		if(!($rs instanceof mysqli_result))
			return false;
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object = new Model_ContextActivityLog();
			$object->id = $row['id'];
			$object->activity_point = $row['activity_point'];
			$object->actor_context = $row['actor_context'];
			$object->actor_context_id = $row['actor_context_id'];
			$object->target_context = $row['target_context'];
			$object->target_context_id = $row['target_context_id'];
			$object->created = $row['created'];
			$object->entry_json = $row['entry_json'];
			$objects[$object->id] = $object;
		}
		
		mysqli_free_result($rs);
		
		return $objects;
	}

	static function random() {
		return self::_getRandom('context_activity_log');
	}
	
	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		$db->ExecuteMaster(sprintf("DELETE FROM context_activity_log WHERE id IN (%s)", $ids_list));
		
		return true;
	}
	
	static function deleteByContext($context, $context_ids) {
		if(!is_array($context_ids))
			$context_ids = array($context_ids);
		
		if(empty($context_ids))
			return;
			
		$db = DevblocksPlatform::getDatabaseService();
		
		$db->ExecuteMaster(sprintf("DELETE FROM context_activity_log WHERE (actor_context = %s AND actor_context_id IN (%s)) OR (target_context = %s AND target_context_id IN (%s)) ",
			$db->qstr($context),
			implode(',', $context_ids),
			$db->qstr($context),
			implode(',', $context_ids)
		));
		
		return true;
	}
	
	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_ContextActivityLog::getFields();
		
		list($tables,$wheres) = parent::_parseSearchParams($params, $columns, 'SearchFields_ContextActivityLog', $sortBy);
		
		$select_sql = sprintf("SELECT ".
			"context_activity_log.id as %s, ".
			"context_activity_log.activity_point as %s, ".
			"context_activity_log.actor_context as %s, ".
			"context_activity_log.actor_context_id as %s, ".
			"context_activity_log.target_context as %s, ".
			"context_activity_log.target_context_id as %s, ".
			"context_activity_log.created as %s, ".
			"context_activity_log.entry_json as %s ",
				SearchFields_ContextActivityLog::ID,
				SearchFields_ContextActivityLog::ACTIVITY_POINT,
				SearchFields_ContextActivityLog::ACTOR_CONTEXT,
				SearchFields_ContextActivityLog::ACTOR_CONTEXT_ID,
				SearchFields_ContextActivityLog::TARGET_CONTEXT,
				SearchFields_ContextActivityLog::TARGET_CONTEXT_ID,
				SearchFields_ContextActivityLog::CREATED,
				SearchFields_ContextActivityLog::ENTRY_JSON
			);
			
		$join_sql = "FROM context_activity_log ";
		
		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = self::_buildSortClause($sortBy, $sortAsc, $fields, $select_sql, 'SearchFields_ContextActivityLog');
	
		// Translate virtual fields
		
		$args = array(
			'join_sql' => &$join_sql,
			'where_sql' => &$where_sql,
			'tables' => &$tables,
		);
		
		return array(
			'primary_table' => 'context_activity_log',
			'select' => $select_sql,
			'join' => $join_sql,
			'where' => $where_sql,
			'sort' => $sort_sql,
		);
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
		$sort_sql = $query_parts['sort'];
		
		$sql =
			$select_sql.
			$join_sql.
			$where_sql.
			$sort_sql;
		
		if($limit > 0) {
			if(false == ($rs = $db->SelectLimit($sql,$limit,$page*$limit)))
				return false;
		} else {
			if(false == ($rs = $db->ExecuteSlave($sql)))
				return false;
			$total = mysqli_num_rows($rs);
		}
		
		$results = array();
		
		if(!($rs instanceof mysqli_result))
			return false;
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object_id = intval($row[SearchFields_ContextActivityLog::ID]);
			$results[$object_id] = $row;
		}

		$total = count($results);
		
		if($withCounts) {
			// We can skip counting if we have a less-than-full single page
			if(!(0 == $page && $total < $limit)) {
				$count_sql =
					"SELECT COUNT(context_activity_log.id) ".
					$join_sql.
					$where_sql;
				$total = $db->GetOneSlave($count_sql);
			}
		}
		
		mysqli_free_result($rs);
		
		return array($results,$total);
	}

};

class SearchFields_ContextActivityLog extends DevblocksSearchFields {
	const ID = 'c_id';
	const ACTIVITY_POINT = 'c_activity_point';
	const ACTOR_CONTEXT = 'c_actor_context';
	const ACTOR_CONTEXT_ID = 'c_actor_context_id';
	const TARGET_CONTEXT = 'c_target_context';
	const TARGET_CONTEXT_ID = 'c_target_context_id';
	const CREATED = 'c_created';
	const ENTRY_JSON = 'c_entry_json';
	
	const VIRTUAL_ACTOR = '*_actor';
	const VIRTUAL_TARGET = '*_target';
	
	static private $_fields = null;
	
	static function getPrimaryKey() {
		return 'context_activity_log.id';
	}
	
	static function getCustomFieldContextKeys() {
		return array(
			CerberusContexts::CONTEXT_ACTIVITY_LOG => new DevblocksSearchFieldContextKeys('context_activity_log.id', self::ID),
		);
	}
	
	static function getWhereSQL(DevblocksSearchCriteria $param) {
		switch($param->field) {
			case self::VIRTUAL_ACTOR:
			case self::VIRTUAL_TARGET:
				switch($param->field) {
					case self::VIRTUAL_ACTOR:
						$context_field = 'actor_context';
						break;
					case self::VIRTUAL_TARGET:
						$context_field = 'target_context';
						break;
				}
				
				// Handle nested quick search filters first
				if($param->operator == DevblocksSearchCriteria::OPER_CUSTOM) {
					@list($alias, $query) = explode(':', $param->value, 2);
					
					if(empty($alias) || (false == ($ext = Extension_DevblocksContext::getByAlias(str_replace('.', ' ', $alias), true))))
						return;
					
					$view = $ext->getSearchView(uniqid());
					$view->is_ephemeral = true;
					$view->setAutoPersist(false);
					$view->addParamsWithQuickSearch($query, true);
					
					$params = $view->getParams();
					
					if(false == ($dao_class = $ext->getDaoClass()) || !class_exists($dao_class))
						return;
					
					if(false == ($search_class = $ext->getSearchClass()) || !class_exists($search_class))
						return;
					
					if(false == ($primary_key = $search_class::getPrimaryKey()))
						return;
					
					$query_parts = $dao_class::getSearchQueryComponents(array(), $params);
					
					$query_parts['select'] = sprintf("SELECT %s ", $primary_key);
					
					$sql = 
						$query_parts['select']
						. $query_parts['join']
						. $query_parts['where']
						. $query_parts['sort']
						;
					
					return sprintf("%s = %s AND %s_id IN (%s) ",
						$context_field,
						Cerb_ORMHelper::qstr($ext->id),
						$context_field,
						$sql
					);
				}

				if(is_array($param->value)) {
					$wheres = array();
					foreach($param->value as $context_pair) {
						@list($context, $context_id) = explode(':', $context_pair);
						if(!empty($context_id)) {
							$wheres[] = sprintf("(%s = %s AND %s_id = %d)",
								$context_field,
								Cerb_ORMHelper::qstr($context),
								$context_field,
								$context_id
							);
						} else {
							$wheres[] = sprintf("(%s = %s)",
								$context_field,
								Cerb_ORMHelper::qstr($context)
							);
						}
					}
				}
				
				if(!empty($wheres))
					return '(' . implode(' OR ', $wheres) . ') ';
				
				break;
				
			default:
				if('cf_' == substr($param->field, 0, 3)) {
					return self::_getWhereSQLFromCustomFields($param);
				} else {
					return $param->getWhereSQL(self::getFields(), self::getPrimaryKey());
				}
				break;
		}
	}
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		if(is_null(self::$_fields))
			self::$_fields = self::_getFields();
		
		return self::$_fields;
	}
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function _getFields() {
		$translate = DevblocksPlatform::getTranslationService();
		
		$columns = array(
			self::ID => new DevblocksSearchField(self::ID, 'context_activity_log', 'id', $translate->_('common.id'), null, true),
			self::ACTIVITY_POINT => new DevblocksSearchField(self::ACTIVITY_POINT, 'context_activity_log', 'activity_point', $translate->_('dao.context_activity_log.activity_point'), Model_CustomField::TYPE_SINGLE_LINE, true),
			self::ACTOR_CONTEXT => new DevblocksSearchField(self::ACTOR_CONTEXT, 'context_activity_log', 'actor_context', $translate->_('dao.context_activity_log.actor_context'), null, true),
			self::ACTOR_CONTEXT_ID => new DevblocksSearchField(self::ACTOR_CONTEXT_ID, 'context_activity_log', 'actor_context_id', $translate->_('dao.context_activity_log.actor_context_id'), null, true),
			self::TARGET_CONTEXT => new DevblocksSearchField(self::TARGET_CONTEXT, 'context_activity_log', 'target_context', $translate->_('dao.context_activity_log.target_context'), null, true),
			self::TARGET_CONTEXT_ID => new DevblocksSearchField(self::TARGET_CONTEXT_ID, 'context_activity_log', 'target_context_id', $translate->_('dao.context_activity_log.target_context_id'), null, true),
			self::CREATED => new DevblocksSearchField(self::CREATED, 'context_activity_log', 'created', $translate->_('common.created'), Model_CustomField::TYPE_DATE, true),
			self::ENTRY_JSON => new DevblocksSearchField(self::ENTRY_JSON, 'context_activity_log', 'entry_json', $translate->_('dao.context_activity_log.entry'), null, false),
				
			self::VIRTUAL_ACTOR => new DevblocksSearchField(self::VIRTUAL_ACTOR, '*', 'actor', $translate->_('common.actor'), null, false),
			self::VIRTUAL_TARGET => new DevblocksSearchField(self::VIRTUAL_TARGET, '*', 'target', $translate->_('common.target'), null, false),
		);
		
		// Sort by label (translation-conscious)
		DevblocksPlatform::sortObjects($columns, 'db_label');

		return $columns;
	}
};

class Model_ContextActivityLog {
	public $id;
	public $activity_point;
	public $actor_context;
	public $actor_context_id;
	public $target_context;
	public $target_context_id;
	public $created;
	public $entry_json;
};

class View_ContextActivityLog extends C4_AbstractView implements IAbstractView_Subtotals, IAbstractView_QuickSearch {
	const DEFAULT_ID = 'context_activity_log';

	function __construct() {
		$this->id = self::DEFAULT_ID;
		$this->name = 'Activity Log';
		$this->renderLimit = 10;
		$this->renderSortBy = SearchFields_ContextActivityLog::CREATED;
		$this->renderSortAsc = false;

		$this->view_columns = array(
			SearchFields_ContextActivityLog::CREATED,
		);
		$this->addColumnsHidden(array(
			SearchFields_ContextActivityLog::ACTOR_CONTEXT_ID,
			SearchFields_ContextActivityLog::TARGET_CONTEXT_ID,
			SearchFields_ContextActivityLog::ID,
		));
		
		$this->addParamsHidden(array(
			SearchFields_ContextActivityLog::ACTOR_CONTEXT_ID,
			SearchFields_ContextActivityLog::TARGET_CONTEXT_ID,
			SearchFields_ContextActivityLog::ID,
		));
		
		$this->doResetCriteria();
	}

	function getData() {
		$objects = DAO_ContextActivityLog::search(
			$this->view_columns,
			$this->getParams(),
			$this->renderLimit,
			$this->renderPage,
			$this->renderSortBy,
			$this->renderSortAsc,
			$this->renderTotal
		);
		
		$this->_lazyLoadCustomFieldsIntoObjects($objects, 'SearchFields_ContextActivityLog');
		
		return $objects;
	}
	
	function getDataAsObjects($ids=null) {
		return $this->_getDataAsObjects('DAO_ContextActivityLog', $ids);
	}
	
	function getDataSample($size) {
		return $this->_doGetDataSample('DAO_ContextActivityLog', $size);
	}

	function getSubtotalFields() {
		$all_fields = $this->getParamsAvailable(true);

		$fields = array();

		if(is_array($all_fields))
		foreach($all_fields as $field_key => $field_model) {
			$pass = false;
			
			switch($field_key) {
				// DAO
				case SearchFields_ContextActivityLog::ACTIVITY_POINT:
					$pass = true;
					break;
					
				case SearchFields_ContextActivityLog::VIRTUAL_ACTOR:
				case SearchFields_ContextActivityLog::VIRTUAL_TARGET:
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
		$context = CerberusContexts::CONTEXT_ACTIVITY_LOG;

		if(!isset($fields[$column]))
			return array();
		
		switch($column) {
			case SearchFields_ContextActivityLog::ACTIVITY_POINT:
				$label_map = array();
				$translate = DevblocksPlatform::getTranslationService();
				
				$activities = DevblocksPlatform::getActivityPointRegistry();
				if(is_array($activities))
				foreach($activities as $k => $data) {
					@$string_id = $data['params']['label_key'];
					if(!empty($string_id)) {
						$label_map[$k] = $translate->_($string_id);
					}
				}
				$counts = $this->_getSubtotalCountForStringColumn($context, $column, $label_map, 'in', 'options[]');
				break;
				
			case SearchFields_ContextActivityLog::VIRTUAL_ACTOR:
				$counts = $this->_getSubtotalCountForContextAndIdColumns($context, $column, DAO_ContextActivityLog::ACTOR_CONTEXT, DAO_ContextActivityLog::ACTOR_CONTEXT_ID);
				break;
				
			case SearchFields_ContextActivityLog::VIRTUAL_TARGET:
				$counts = $this->_getSubtotalCountForContextAndIdColumns($context, $column, DAO_ContextActivityLog::TARGET_CONTEXT, DAO_ContextActivityLog::TARGET_CONTEXT_ID);
				break;
				
			default:
				// Custom fields
				if('cf_' == substr($column,0,3)) {
					$counts = $this->_getSubtotalCountForCustomColumn($context, $column);
				}
				
				break;
		}
		
		return $counts;
	}
	
	function getQuickSearchFields() {
		$search_fields = SearchFields_ContextActivityLog::getFields();
		
		$activities = array_map(function($e) { 
			return $e['params']['label_key'];
		}, DevblocksPlatform::getActivityPointRegistry());
		
		$fields = array(
			'text' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_ContextActivityLog::ENTRY_JSON, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'activity' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_ContextActivityLog::ACTIVITY_POINT),
					'examples' => [
						['type' => 'list', 'values' => $activities],
					],
				),
			'created' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_ContextActivityLog::CREATED),
				),
			'entry' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_ContextActivityLog::ENTRY_JSON, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'id' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_NUMBER,
					'options' => array('param_key' => SearchFields_ContextActivityLog::ID),
					'examples' => [
						['type' => 'chooser', 'context' => CerberusContexts::CONTEXT_ACTIVITY_LOG, 'q' => ''],
					]
				),
		);
		
		// Add dynamic actor.* and target.* filters
		
		$fields = self::_appendVirtualFiltersFromQuickSearchContexts('actor', $fields);
		$fields = self::_appendVirtualFiltersFromQuickSearchContexts('target', $fields);
		
		// Add searchable custom fields
		
		$fields = self::_appendFieldsFromQuickSearchContext(CerberusContexts::CONTEXT_ACTIVITY_LOG, $fields, null);
		
		// Add is_sortable
		
		$fields = self::_setSortableQuickSearchFields($fields, $search_fields);
		
		// Sort by keys
		
		ksort($fields);
		
		return $fields;
	}	

	function getParamFromQuickSearchFieldTokens($field, $tokens) {
		switch($field) {
			default:
				if($field == 'actor' || DevblocksPlatform::strStartsWith($field, 'actor.'))
					return DevblocksSearchCriteria::getVirtualContextParamFromTokens($field, $tokens, 'actor', SearchFields_ContextActivityLog::VIRTUAL_ACTOR);
					
				if($field == 'target' || DevblocksPlatform::strStartsWith($field, 'target.'))
					return DevblocksSearchCriteria::getVirtualContextParamFromTokens($field, $tokens, 'target', SearchFields_ContextActivityLog::VIRTUAL_TARGET);
				
				$search_fields = $this->getQuickSearchFields();
				return DevblocksSearchCriteria::getParamFromQueryFieldTokens($field, $tokens, $search_fields);
				break;
		}
		
		return false;
	}
	
	function render() {
		$this->_sanitize();
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

		$tpl->assign('view_template', 'devblocks:cerberusweb.core::internal/activity_log/view.tpl');
		$tpl->display('devblocks:cerberusweb.core::internal/views/subtotals_and_view.tpl');
	}

	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);

		switch($field) {
			case SearchFields_ContextActivityLog::ENTRY_JSON:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
			case SearchFields_ContextActivityLog::ID:
			case SearchFields_ContextActivityLog::ACTOR_CONTEXT_ID:
			case SearchFields_ContextActivityLog::TARGET_CONTEXT_ID:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__number.tpl');
				break;
			case 'placeholder_bool':
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__bool.tpl');
				break;
			case SearchFields_ContextActivityLog::CREATED:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__date.tpl');
				break;
			case SearchFields_ContextActivityLog::ACTIVITY_POINT:
				$activities = DevblocksPlatform::getActivityPointRegistry();
				$options = array();
				
				foreach($activities as $activity_id => $activity) {
					if(isset($activity['params']['label_key']))
						$options[$activity_id] = $activity['params']['label_key'];
				}
				
				$tpl->assign('options', $options);
				
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__list.tpl');
				break;
			case SearchFields_ContextActivityLog::ACTOR_CONTEXT:
			case SearchFields_ContextActivityLog::TARGET_CONTEXT:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context.tpl');
				break;
			case SearchFields_ContextActivityLog::VIRTUAL_ACTOR:
			case SearchFields_ContextActivityLog::VIRTUAL_TARGET:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_link.tpl');
				break;
		}
	}

	function renderVirtualCriteria($param) {
		$key = $param->field;
		
		$translate = DevblocksPlatform::getTranslationService();
		
		switch($key) {
			case SearchFields_ContextActivityLog::VIRTUAL_ACTOR:
				$this->_renderVirtualContextLinks($param, 'Actor', 'Actors', 'Actor is');
				break;
			
			case SearchFields_ContextActivityLog::VIRTUAL_TARGET:
				$this->_renderVirtualContextLinks($param, 'Target', 'Targets', 'Target is');
				break;
		}
	}
	
	function renderCriteriaParam($param) {
		$field = $param->field;
		$values = !is_array($param->value) ? array($param->value) : $param->value;

		switch($field) {
			case SearchFields_ContextActivityLog::ACTOR_CONTEXT:
			case SearchFields_ContextActivityLog::TARGET_CONTEXT:
				$strings = array();
				$contexts = Extension_DevblocksContext::getAll(false);
				
				if(is_array($values))
				foreach($values as $v) {
					$string = $v;
					if(isset($contexts[$v])) {
						if(isset($contexts[$v]->name))
							$string = $contexts[$v]->name;
					}
					
					$strings[] = $string;
				}
				
				return implode(' or ', $strings);
				break;
				
			case SearchFields_ContextActivityLog::ACTIVITY_POINT:
				$strings = array();
				
				$activities = DevblocksPlatform::getActivityPointRegistry();
				$translate = DevblocksPlatform::getTranslationService();
				
				if(is_array($values))
				foreach($values as $v) {
					$string = $v;
					if(isset($activities[$v])) {
						@$string_id = $activities[$v]['params']['label_key'];
						if(!empty($string_id))
							$string = $translate->_($string_id);
					}
					
					$strings[] = $string;
				}
				
				return implode(' or ', $strings);
				break;
				
			default:
				parent::renderCriteriaParam($param);
				break;
		}
	}

	function getFields() {
		return SearchFields_ContextActivityLog::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		switch($field) {
			case SearchFields_ContextActivityLog::ENTRY_JSON:
				$criteria = $this->_doSetCriteriaString($field, $oper, $value);
				break;
				
			case SearchFields_ContextActivityLog::ID:
			case SearchFields_ContextActivityLog::ACTOR_CONTEXT_ID:
			case SearchFields_ContextActivityLog::TARGET_CONTEXT_ID:
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;
				
			case SearchFields_ContextActivityLog::CREATED:
				$criteria = $this->_doSetCriteriaDate($field, $oper);
				break;
				
			case 'placeholder_bool':
				@$bool = DevblocksPlatform::importGPC($_REQUEST['bool'],'integer',1);
				$criteria = new DevblocksSearchCriteria($field,$oper,$bool);
				break;

			case SearchFields_ContextActivityLog::ACTIVITY_POINT:
				@$options = DevblocksPlatform::importGPC($_REQUEST['options'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$options);
				break;
				
			case SearchFields_ContextActivityLog::ACTOR_CONTEXT:
			case SearchFields_ContextActivityLog::TARGET_CONTEXT:
				@$contexts = DevblocksPlatform::importGPC($_REQUEST['contexts'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$contexts);
				break;
				
			case SearchFields_ContextActivityLog::VIRTUAL_ACTOR:
			case SearchFields_ContextActivityLog::VIRTUAL_TARGET:
				@$context_links = DevblocksPlatform::importGPC($_REQUEST['context_link'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$context_links);
				break;
		}

		if(!empty($criteria)) {
			$this->addParam($criteria, $field);
			$this->renderPage = 0;
		}
	}
};

class Context_ContextActivityLog extends Extension_DevblocksContext {
	static function isReadableByActor($models, $actor) {
		// Everyone can read
		return CerberusContexts::allowEverything($models);
	}
	
	static function isWriteableByActor($models, $actor) {
		// Only admins can modify
		if(false == ($actor = CerberusContexts::polymorphActorToDictionary($actor)))
			CerberusContexts::denyEverything($models);
		
		// Admins can do whatever they want
		if(CerberusContexts::isActorAnAdmin($actor))
			return CerberusContexts::allowEverything($models);
		
		return CerberusContexts::denyEverything($models);
	}
	
	function getRandom() {
		return DAO_ContextActivityLog::random();
	}
	
	function getMeta($context_id) {
		$url_writer = DevblocksPlatform::getUrlService();
		
		$entry = DAO_ContextActivityLog::get($context_id);
		
		return array(
			'id' => $entry->id,
			'name' => CerberusContexts::formatActivityLogEntry(json_decode($entry->entry_json, true), 'text'),
			'permalink' => null,
			'updated' => $entry->created,
		);
	}
	
	function getPropertyLabels(DevblocksDictionaryDelegate $dict) {
		$labels = $dict->_labels;
		$prefix = $labels['_label'];
		
		if(!empty($prefix)) {
			array_walk($labels, function(&$label, $key) use ($prefix) {
				$label = preg_replace(sprintf("#^%s #", preg_quote($prefix)), '', $label);
				
				// [TODO] Use translations
				switch($key) {
				}
				
				$label = mb_convert_case($label, MB_CASE_LOWER);
				$label[0] = mb_convert_case($label[0], MB_CASE_UPPER);
			});
		}
		
		asort($labels);
		
		return $labels;
	}
	
	function getDefaultProperties() {
		return array(
			'event',
			'created',
			'actor__label',
			'target__label',
		);
	}
	
	function getContext($entry, &$token_labels, &$token_values, $prefix=null) {
		if(is_null($prefix))
			$prefix = 'Activity:';
		
		$translate = DevblocksPlatform::getTranslationService();
		
		// Polymorph
		if(is_numeric($entry)) {
			$entry = DAO_ContextActivityLog::get($entry);
		} elseif($entry instanceof Model_ContextActivityLog) {
			// It's what we want already.
		} elseif(is_array($entry)) {
			$entry = Cerb_ORMHelper::recastArrayToModel($entry, 'Model_ContextActivityLog');
		} else {
			$entry = null;
		}
		
		// Token labels
		$token_labels = array(
			'_label' => $prefix,
			'id' => $prefix.$translate->_('common.id'),
			'event' => $prefix.$translate->_('common.event'),
			'created' => $prefix.$translate->_('common.created'),
			'actor__label' => $prefix.$translate->_('common.actor'),
			'target__label' => $prefix.$translate->_('common.target'),
		);
		
		// Token types
		$token_types = array(
			'_label' => 'context_url',
			'id' => Model_CustomField::TYPE_NUMBER,
			'created' => Model_CustomField::TYPE_DATE,
			'event' => Model_CustomField::TYPE_SINGLE_LINE,
			'actor__label' => 'context_url',
			'target__label' => 'context_url',
		);
		
		// Token values
		$token_values = array();
		
		$token_values['_context'] = CerberusContexts::CONTEXT_ACTIVITY_LOG;
		$token_values['_types'] = $token_types;

		// Address token values
		if(null != $entry) {
			$token_values['_loaded'] = true;
			$token_values['_label'] = CerberusContexts::formatActivityLogEntry(json_decode($entry->entry_json,true),'text');
			$token_values['id'] = $entry->id;
			$token_values['created'] = $entry->created;
			
			$activities = DevblocksPlatform::getActivityPointRegistry();
			if(isset($activities[$entry->activity_point]))
				$token_values['event'] = $activities[$entry->activity_point]['params']['label_key'];
			
			$token_values['actor__context'] = $entry->actor_context;
			$token_values['actor_id'] = $entry->actor_context_id;
			
			$token_values['target__context'] = $entry->target_context;
			$token_values['target_id'] = $entry->target_context_id;
		}
		
		return true;
	}
	
	function lazyLoadContextValues($token, $dictionary) {
		if(!isset($dictionary['id']))
			return;
		
		$context = CerberusContexts::CONTEXT_ACTIVITY_LOG;
		$context_id = $dictionary['id'];
		
		@$is_loaded = $dictionary['_loaded'];
		$values = array();
		
		if(!$is_loaded) {
			$labels = array();
			CerberusContexts::getContext($context, $context_id, $labels, $values, null, true, true);
		}
		
		switch($token) {
		}
		
		return $values;
	}

	function getChooserView($view_id=null) {
		if(empty($view_id))
			$view_id = 'chooser_'.str_replace('.','_',$this->id).time().mt_rand(0,9999);
		
		// View
		$defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass());
		$defaults->id = $view_id;
		$defaults->is_ephemeral = true;
		
		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->name = 'Activity Log';
		
		$view->addParamsDefault(array(
			//SearchFields_ContextActivityLog::IS_BANNED => new DevblocksSearchCriteria(SearchFields_ContextActivityLog::IS_BANNED,'=',0),
			//SearchFields_ContextActivityLog::IS_DEFUNCT => new DevblocksSearchCriteria(SearchFields_ContextActivityLog::IS_DEFUNCT,'=',0),
		), true);
		$view->addParams($view->getParamsDefault(), true);
		
		$view->renderSortBy = SearchFields_ContextActivityLog::CREATED;
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
		$view->name = 'Activity Log';
		
		$params_req = array();
		
		if(!empty($context) && !empty($context_id)) {
			$params_req = array(
// 				new DevblocksSearchCriteria(SearchFields_ContextActivityLog::CONTEXT_LINK,'=',$context),
// 				new DevblocksSearchCriteria(SearchFields_ContextActivityLog::CONTEXT_LINK_ID,'=',$context_id),
			);
		}
		
		$view->addParamsRequired($params_req, true);
		
		$view->renderTemplate = 'context';
		return $view;
	}
};