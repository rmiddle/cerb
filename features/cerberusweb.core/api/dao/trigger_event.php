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

class DAO_TriggerEvent extends Cerb_ORMHelper {
	const CACHE_ALL = 'cerberus_cache_behavior_all';
	
	const ID = 'id';
	const TITLE = 'title';
	const IS_DISABLED = 'is_disabled';
	const IS_PRIVATE = 'is_private';
	const EVENT_POINT = 'event_point';
	const BOT_ID = 'bot_id';
	const PRIORITY = 'priority';
	const UPDATED_AT = 'updated_at';
	const EVENT_PARAMS_JSON = 'event_params_json';
	const VARIABLES_JSON = 'variables_json';

	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		if(!isset($fields[self::UPDATED_AT]))
			$fields[self::UPDATED_AT] = time();
		
		$sql = "INSERT INTO trigger_event () VALUES ()";
		$db->ExecuteMaster($sql);
		$id = $db->LastInsertId();
		
		self::update($id, $fields);
		
		return $id;
	}
	
	static function update($ids, $fields) {
		parent::_update($ids, 'trigger_event', $fields);
		self::clearCache();
	}
	
	static function updateWhere($fields, $where) {
		parent::_updateWhere('trigger_event', $fields, $where);
		self::clearCache();
	}
	
	/**
	 *
	 * @param bool $nocache
	 * @return Model_TriggerEvent[]
	 */
	static function getAll($nocache=false) {
		$cache = DevblocksPlatform::getCacheService();
		if($nocache || null === ($behaviors = $cache->load(self::CACHE_ALL))) {
			$behaviors = self::getWhere(
				null,
				DAO_TriggerEvent::PRIORITY,
				true,
				null,
				Cerb_ORMHelper::OPT_GET_MASTER_ONLY
			);
			
			if(!is_array($behaviors))
				return false;
			
			$cache->save($behaviors, self::CACHE_ALL);
		}
		
		return $behaviors;
	}
	
	static function getReadableByActor($actor, $event_point=null, $with_disabled=false) {
		$macros = array();

		$vas = DAO_Bot::getReadableByActor($actor);

		if(is_array($vas))
		foreach($vas as $va) { /* @var $va Model_Bot */
			if(!$with_disabled && $va->is_disabled)
				continue;
		
			$behaviors = $va->getBehaviors($event_point, $with_disabled, 'name');
			
			if(empty($behaviors))
				continue;
			
			$results = array();
			
			if(is_array($behaviors))
			foreach($behaviors as $behavior_id => $behavior) { /* @var $behavior Model_TriggerEvent */
				if(!isset($vas[$behavior->bot_id]))
					continue;
				
				// A private behavior is only usable by the same owner
				if($behavior->is_private && !Context_TriggerEvent::isWriteableByActor($behavior, $actor))
					continue;
				
				$result = clone $behavior; /* @var $result Model_TriggerEvent */
				
				$has_public_vars = false;
				if(is_array($result->variables))
				foreach($result->variables as $var_name => $var_data) {
					if(empty($var_data['is_private']))
						$has_public_vars = true;
				}
				$result->has_public_vars = $has_public_vars;
				
				$results[$behavior_id] = $result;
			}
			
			$macros = $macros + $results;
		}
		
		DevblocksPlatform::sortObjects($macros, 'title', true);
		
		return $macros;
	}
	
	/**
	 *
	 * @param integer $va_id
	 * @param string $event_point
	 * @return Model_TriggerEvent[]
	 */
	static function getByBot($va, $event_point=null, $with_disabled=false, $sort_by='title') {
		// Polymorph if necessary
		if(is_numeric($va))
			$va = DAO_Bot::get($va);
		
		// If we didn't resolve to a VA model
		if(!($va instanceof Model_Bot))
			return array();
		
		if(!$with_disabled && $va->is_disabled)
			return array();
		
		$behaviors = self::getAll();
		$results = array();

		if(is_array($behaviors))
		foreach($behaviors as $behavior_id => $behavior) { /* @var $behavior Model_TriggerEvent */
			if($behavior->bot_id != $va->id)
				continue;
			
			if($event_point && $behavior->event_point != $event_point)
				continue;
			
			if(!$with_disabled && $behavior->is_disabled)
				continue;
			
			// Are we only showing approved events?
			// Are we removing denied events?
			if(!$va->canUseEvent($behavior->event_point))
				continue;
			
			$results[$behavior_id] = $behavior;
		}
		
		// Sort
		
		switch($sort_by) {
			case 'title':
			case 'priority':
				break;
				
			default:
				$sort_by = 'title';
				break;
		}
		
		DevblocksPlatform::sortObjects($results, $sort_by, true);
		
		return $results;
	}
	
	static function getByEvent($event_id, $with_disabled=false) {
		$vas = DAO_Bot::getAll();
		$behaviors = array();

		foreach($vas as $va) { /* @var $va Model_Bot */
			$va_behaviors = $va->getBehaviors($event_id, $with_disabled, 'priority');
			
			if(!empty($va_behaviors))
				$behaviors += $va_behaviors;
		}
		
		return $behaviors;
	}
	
	/**
	 * @param integer $id
	 * @return Model_TriggerEvent
	 */
	static function get($id) {
		if(empty($id))
			return null;
		
		$behaviors = self::getAll();
		
		if(isset($behaviors[$id]))
			return $behaviors[$id];
		
		return null;
	}
	
	/**
	 * @param string $where
	 * @param mixed $sortBy
	 * @param mixed $sortAsc
	 * @param integer $limit
	 * @return Model_TriggerEvent[]
	 */
	static function getWhere($where=null, $sortBy=DAO_TriggerEvent::PRIORITY, $sortAsc=true, $limit=null, $options=null) {
		$db = DevblocksPlatform::getDatabaseService();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		// SQL
		$sql = "SELECT id, title, is_disabled, is_private, event_point, bot_id, priority, event_params_json, updated_at, variables_json ".
			"FROM trigger_event ".
			$where_sql.
			$sort_sql.
			$limit_sql
		;
		
		if($options & Cerb_ORMHelper::OPT_GET_MASTER_ONLY) {
			$rs = $db->ExecuteMaster($sql, _DevblocksDatabaseManager::OPT_NO_READ_AFTER_WRITE);
		} else {
			$rs = $db->ExecuteSlave($sql);
		}
		
		return self::_getObjectsFromResult($rs);
	}
	
	/**
	 * @param resource $rs
	 * @return Model_TriggerEvent[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		if(!($rs instanceof mysqli_result))
			return $objects;
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object = new Model_TriggerEvent();
			$object->id = intval($row['id']);
			$object->title = $row['title'];
			$object->is_disabled = intval($row['is_disabled']);
			$object->is_private = intval($row['is_private']);
			$object->priority = intval($row['priority']);
			$object->event_point = $row['event_point'];
			$object->bot_id = $row['bot_id'];
			$object->updated_at = intval($row['updated_at']);
			$object->event_params = @json_decode($row['event_params_json'], true);
			$object->variables = @json_decode($row['variables_json'], true);
			$objects[$object->id] = $object;
		}
		
		mysqli_free_result($rs);
		
		return $objects;
	}
	
	static function logUsage($trigger_id, $runtime_ms) {
		if(empty($trigger_id))
			return;
		
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = sprintf("INSERT INTO trigger_event_history (trigger_id, ts_day, uses, elapsed_ms) ".
			"VALUES (%d, %d, %d, %d) ".
			"ON DUPLICATE KEY UPDATE uses = uses + VALUES(uses), elapsed_ms = elapsed_ms + VALUES(elapsed_ms) ",
			$trigger_id,
			time() - (time() % 86400),
			1,
			$runtime_ms
		);
		
		$db->ExecuteMaster($sql);
	}
	
	static public function countByBot($bot_id) {
		$db = DevblocksPlatform::getDatabaseService();
		return $db->GetOneSlave(sprintf("SELECT count(*) FROM trigger_event ".
			"WHERE bot_id = %d",
			$bot_id
		));
	}
	
	static function delete($ids) {
		if(!is_array($ids))
			$ids = array($ids);
		
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		// [TODO] Use DAO_DecisionNode::deleteByTrigger() to cascade
		$db->ExecuteMaster(sprintf("DELETE FROM decision_node WHERE trigger_id IN (%s)", $ids_list));
		
		$db->ExecuteMaster(sprintf("DELETE FROM trigger_event WHERE id IN (%s)", $ids_list));
		
		$db->ExecuteMaster(sprintf("DELETE FROM trigger_event_history WHERE trigger_id IN (%s)", $ids_list));
		
		foreach($ids as $id)
			$db->ExecuteMaster(sprintf("DELETE FROM devblocks_registry WHERE entry_key LIKE 'trigger.%d.%%'", $id));
		
		DAO_ContextScheduledBehavior::deleteByBehavior($ids);
		
		self::clearCache();
		return true;
	}
	
	static function deleteByBot($va_id) {
		$results = self::getWhere(sprintf("%s = %d",
			self::BOT_ID,
			$va_id
		));
		
		if(is_array($results))
		foreach($results as $result) {
			self::delete($result->id);
		}
		
		return TRUE;
	}
	
	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_TriggerEvent::getFields();
		
		list($tables,$wheres) = parent::_parseSearchParams($params, $columns, 'SearchFields_TriggerEvent', $sortBy);
		
		$select_sql = sprintf("SELECT ".
			"trigger_event.id as %s, ".
			"trigger_event.title as %s, ".
			"trigger_event.is_disabled as %s, ".
			"trigger_event.is_private as %s, ".
			"trigger_event.priority as %s, ".
			"trigger_event.bot_id as %s, ".
			"trigger_event.updated_at as %s, ".
			"trigger_event.event_point as %s ",
				SearchFields_TriggerEvent::ID,
				SearchFields_TriggerEvent::TITLE,
				SearchFields_TriggerEvent::IS_DISABLED,
				SearchFields_TriggerEvent::IS_PRIVATE,
				SearchFields_TriggerEvent::PRIORITY,
				SearchFields_TriggerEvent::BOT_ID,
				SearchFields_TriggerEvent::UPDATED_AT,
				SearchFields_TriggerEvent::EVENT_POINT
			);
			
		$join_sql = "FROM trigger_event ";
		
		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = self::_buildSortClause($sortBy, $sortAsc, $fields, $select_sql, 'SearchFields_TriggerEvent');
	
		return array(
			'primary_table' => 'trigger_event',
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
			$object_id = intval($row[SearchFields_TriggerEvent::ID]);
			$results[$object_id] = $row;
		}

		$total = count($results);
		
		if($withCounts) {
			// We can skip counting if we have a less-than-full single page
			if(!(0 == $page && $total < $limit)) {
				$count_sql =
					"SELECT COUNT(trigger_event.id) ".
					$join_sql.
					$where_sql;
				$total = $db->GetOneSlave($count_sql);
			}
		}
		
		mysqli_free_result($rs);
		
		return array($results,$total);
	}

	static public function clearCache() {
		$cache = DevblocksPlatform::getCacheService();
		$cache->remove(self::CACHE_ALL);
	}
	
	static public function getNextPosByParent($trigger_id, $parent_id) {
		$db = DevblocksPlatform::getDatabaseService();

		$count = $db->GetOneMaster(sprintf("SELECT MAX(pos) FROM decision_node ".
			"WHERE trigger_id = %d AND parent_id = %d",
			$trigger_id,
			$parent_id
		));

		if(is_null($count))
			return 0;

		return intval($count) + 1;
	}
};

class SearchFields_TriggerEvent extends DevblocksSearchFields {
	const ID = 't_id';
	const TITLE = 't_title';
	const IS_DISABLED = 't_is_disabled';
	const IS_PRIVATE = 't_is_private';
	const PRIORITY = 't_priority';
	const BOT_ID = 't_bot_id';
	const EVENT_POINT = 't_event_point';
	const UPDATED_AT = 't_updated_at';
	
	const VIRTUAL_BOT_SEARCH = '*_bot_search';
	const VIRTUAL_USABLE_BY = '*_usable_by';
	const VIRTUAL_WATCHERS = '*_workers';
	
	static private $_fields = null;
	
	static function getPrimaryKey() {
		return 'trigger_event.id';
	}
	
	static function getCustomFieldContextKeys() {
		return array(
			CerberusContexts::CONTEXT_BEHAVIOR => new DevblocksSearchFieldContextKeys('trigger_event.id', self::ID),
			CerberusContexts::CONTEXT_BOT => new DevblocksSearchFieldContextKeys('trigger_event.bot_id', self::BOT_ID),
		);
	}
	
	static function getWhereSQL(DevblocksSearchCriteria $param) {
		switch($param->field) {
			case self::VIRTUAL_BOT_SEARCH:
				return self::_getWhereSQLFromVirtualSearchField($param, CerberusContexts::CONTEXT_BOT, 'trigger_event.bot_id');
				break;
				
			case self::VIRTUAL_USABLE_BY:
				return self::_getWhereSQLForUsableBy($param, self::getPrimaryKey());
				break;
				
			case self::VIRTUAL_WATCHERS:
				return self::_getWhereSQLFromWatchersField($param, CerberusContexts::CONTEXT_BEHAVIOR, self::getPrimaryKey());
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
	
	static private function _getWhereSQLForUsableBy($param, $pkey) {
		// Handle nested quick search filters first
		if($param->operator == DevblocksSearchCriteria::OPER_CUSTOM) {
			if(!is_array($param->value))
				return '0';
			
			$actor_context = $param->value['context'];
			$actor_id = $param->value['id'];
			
			if(empty($actor_context))
				return '0';
			
			$behaviors = DAO_TriggerEvent::getReadableByActor([$actor_context, $actor_id]);
			
			if(empty($behaviors))
				return '0';
			
			$behavior_ids = array_keys($behaviors);
			
			$sql = sprintf("%s IN (%s)",
				$pkey,
				implode(',', $behavior_ids)
			);
			
			return $sql;
		}
		
		return '0';
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
			self::ID => new DevblocksSearchField(self::ID, 'trigger_event', 'id', $translate->_('common.id'), null, true),
			self::TITLE => new DevblocksSearchField(self::TITLE, 'trigger_event', 'title', $translate->_('common.title'), null, true),
			self::IS_DISABLED => new DevblocksSearchField(self::IS_DISABLED, 'trigger_event', 'is_disabled', $translate->_('dao.trigger_event.is_disabled'), null, true),
			self::IS_PRIVATE => new DevblocksSearchField(self::IS_PRIVATE, 'trigger_event', 'is_private', $translate->_('dao.trigger_event.is_private'), null, true),
			self::PRIORITY => new DevblocksSearchField(self::PRIORITY, 'trigger_event', 'priority', $translate->_('common.priority'), null, true),
			self::BOT_ID => new DevblocksSearchField(self::BOT_ID, 'trigger_event', 'bot_id', $translate->_('common.bot'), null, true),
			self::EVENT_POINT => new DevblocksSearchField(self::EVENT_POINT, 'trigger_event', 'event_point', $translate->_('common.event'), null, true),
			self::UPDATED_AT => new DevblocksSearchField(self::UPDATED_AT, 'trigger_event', 'updated_at', $translate->_('common.updated'), null, true),
				
			self::VIRTUAL_BOT_SEARCH => new DevblocksSearchField(self::VIRTUAL_BOT_SEARCH, '*', 'bot_search', null, null, false),
			self::VIRTUAL_USABLE_BY => new DevblocksSearchField(self::VIRTUAL_USABLE_BY, '*', 'usable_by', null, null, false),
			self::VIRTUAL_WATCHERS => new DevblocksSearchField(self::VIRTUAL_WATCHERS, '*', 'workers', $translate->_('common.watchers'), 'WS', false),
		);
		
		// Custom Fields
		$custom_columns = DevblocksSearchField::getCustomSearchFieldsByContexts(array_keys(self::getCustomFieldContextKeys()));
		
		if(!empty($custom_columns))
			$columns = array_merge($columns, $custom_columns);

		// Sort by label (translation-conscious)
		DevblocksPlatform::sortObjects($columns, 'db_label');

		return $columns;
	}
};

class Model_TriggerEvent {
	public $id;
	public $title;
	public $is_disabled;
	public $is_private;
	public $priority;
	public $event_point;
	public $bot_id;
	public $updated_at;
	public $event_params = array();
	public $variables = array();
	
	private $_nodes = array();
	
	/**
	 * @return Extension_DevblocksEvent
	 */
	public function getEvent() {
		if(null == ($event = DevblocksPlatform::getExtension($this->event_point, true))
			|| !$event instanceof Extension_DevblocksEvent)
			return NULL;
		
		return $event;
	}
	
	public function getBot() {
		return DAO_Bot::get($this->bot_id);
	}
	
	public function getNextPosByParent($parent_id) {
		return DAO_TriggerEvent::getNextPosByParent($this->id, $parent_id);
	}
	
	public function formatVariable($var, $value) {
		switch($var['type']) {
			case Model_CustomField::TYPE_MULTI_LINE:
			case Model_CustomField::TYPE_SINGLE_LINE:
			case Model_CustomField::TYPE_URL:
				settype($value, 'string');
				break;
				
			case Model_CustomField::TYPE_DROPDOWN:
				$options = DevblocksPlatform::parseCrlfString($var['params']['options'], true);
	
				if(!is_array($options))
					throw new Exception(sprintf("The picklist variable '%s' has no options.",
						$var['key']
					));
				
				if(!in_array($value, $options))
					throw new Exception(sprintf("The picklist variable '%s' has no option '%s'. Valid options are: %s",
						$var['key'],
						$value,
						implode(', ', $options)
					));
				break;
				
			case Model_CustomField::TYPE_CHECKBOX:
				$value = !empty($value) ? 1 : 0;
				break;
				
			case Model_CustomField::TYPE_LINK:
			case Model_CustomField::TYPE_NUMBER:
				settype($value, 'integer');
				break;
				
			case Model_CustomField::TYPE_WORKER:
				settype($value, 'integer');
				
				if(false == ($worker = DAO_Worker::get($value)))
					throw new Exception(sprintf("The worklist variable '%s' can not be set to invalid worker #%d.",
						$var['key'],
						$value
					));
				
				break;
				
			case Model_CustomField::TYPE_DATE:
				if(is_numeric($value))
					break;
				
				settype($value, 'string');
				
				if(false == ($value = strtotime($value))) {
					throw new Exception(sprintf("The date variable '%s' has an invalid value.",
						$var['key']
					));
				}
				break;
				
			// [TODO] Future public variable types
			case Model_CustomField::TYPE_MULTI_CHECKBOX:
			case Model_CustomField::TYPE_FILE:
			case Model_CustomField::TYPE_FILES:
				break;
				
			default:
				if('ctx_' == substr($var['type'], 0, 4)) {
					$objects = array();

					$json = null;
					
					if(is_array($value)) {
						$json = $value;
						
					} elseif (is_string($value)) {
						@$json = json_decode($value, true);
						
					}
					
					if(!is_array($json)) {
						throw new Exception(sprintf("The list variable '%s' must be set to an array of IDs.",
							$var['key']
						));
					}
						
					$context = substr($var['type'], 4);
					
					foreach($json as $context_id) {
						$labels = array();
						$values = array();
						
						CerberusContexts::getContext($context, $context_id, $labels, $values, null, true, true);
						
						if(!isset($values['_loaded']))
							continue;
						
						$objects[$context_id] = new DevblocksDictionaryDelegate($values);
					}
					
					$value = $objects;
				}
				break;
		}
		
		return $value;
	}
	
	private function _getNodes() {
		if(empty($this->_nodes))
			$this->_nodes = DAO_DecisionNode::getByTriggerParent($this->id);
		
		return $this->_nodes;
	}
	
	public function getNodes($of_type=null) {
		$nodes = $this->_getNodes();
		
		if($of_type) {
			$nodes = array_filter($nodes, function($node) use ($of_type) {
				if($of_type == $node->node_type)
					return true;
				
				return false;
			});
		}
		
		return $nodes;
	}
	
	public function getDecisionTreeData($root_id = 0) {
		$nodes = $this->_getNodes();
		$tree = $this->_getTree();
		$depths = array();
		$this->_recurseBuildTreeDepths($tree, $root_id, $depths);
		
		return array('nodes' => $nodes, 'tree' => $tree, 'depths' => $depths);
	}
	
	private function _getTree() {
		$nodes = $this->_getNodes();
		$tree = array(0 => array()); // root
		
		foreach($nodes as $node_id => $node) {
			if(!isset($tree[$node->id]))
				$tree[$node->id] = array();
				
			// Parent chain
			if(!isset($tree[$node->parent_id]))
				$tree[$node->parent_id] = array();
			
			$tree[$node->parent_id][$node->id] = $node->id;
		}
		
		return $tree;
	}
	
	private function _recurseBuildTreeDepths($tree, $node_id, &$out, $depth=0) {
		foreach($tree[$node_id] as $child_id) {
			$out[$child_id] = $depth;
			$this->_recurseBuildTreeDepths($tree, $child_id, $out, $depth+1);
		}
	}
	
	public function runDecisionTree(DevblocksDictionaryDelegate $dict, $dry_run=false, Extension_DevblocksEvent $event=null) {
		return $this->_runDecisionTree($dict, $dry_run, $event);
	}
	
	public function resumeDecisionTree(DevblocksDictionaryDelegate $dict, $dry_run=false, Extension_DevblocksEvent $event, array $replay) {
		return $this->_runDecisionTree($dict, $dry_run, $event, $replay);
	}
	
	private function _runDecisionTree(DevblocksDictionaryDelegate $dict, $dry_run=false, Extension_DevblocksEvent $event, array $replay=array()) {
		$nodes = $this->_getNodes();
		$tree = $this->_getTree();
		$path = [];
		
		// Lazy load the event if necessary (otherwise reuse a passed scope)
		if(is_null($event))
			$event = $this->getEvent();
		
		// Add a convenience pointer
		$dict->__trigger = $this;
		
		$this->_recurseRunTree($event, $nodes, $tree, 0, $dict, $path, $replay, $dry_run);
		
		$result = end($path) ?: '';
		$exit_state = 'STOP';
		
		if($result === 'SUSPEND') {
			array_pop($path);
			$exit_state = 'SUSPEND';
		}
		
		return [
			'path' => $path,
			'exit_state' => $exit_state,
		];
	}
	
	private function _recurseRunTree($event, $nodes, $tree, $node_id, DevblocksDictionaryDelegate $dict, &$path, &$replay, $dry_run=false) {
		$logger = DevblocksPlatform::getConsoleLog('Bot');

		// Did the last action request that we exit early?
		if(false !== in_array(end($path) ?: '', ['STOP','SUSPEND']))
			return;
		
		$replay_id = null;
		
		if(is_array($replay) && !empty($replay)) {
			$replay_id = array_shift($replay);
			reset($replay);
			
			$node_id = $replay_id;
			EventListener_Triggers::logNode($node_id);
			
			if(!empty($node_id))
				$logger->info('REPLAY ' . $nodes[$node_id]->node_type . ' :: ' . $nodes[$node_id]->title . ' (' . $node_id . ')');
		}
		
		$pass = true;
		
		if(!empty($node_id) && isset($nodes[$node_id])) {
			switch($nodes[$node_id]->status_id) {
				// Disabled
				case 1:
					return;
					break;
					
				// Simulator only
				case 2:
					if(!$dry_run)
						return;
					break;
			}
			
			// If these conditions match...
			if(empty($replay_id))
				$logger->info('ENTER ' . $nodes[$node_id]->node_type . ' :: ' . $nodes[$node_id]->title . ' (' . $node_id . ')');
			
			// Handle the node type
			switch($nodes[$node_id]->node_type) {
				case 'subroutine':
					if($replay_id)
						break;
					
					$pass = true;
					$dict->__goto = $node_id;
					break;
					
				case 'loop':
					@$foreach_json = $nodes[$node_id]->params['foreach_json'];
					@$as_placeholder = $nodes[$node_id]->params['as_placeholder'];
					
					$tpl_builder = DevblocksPlatform::getTemplateBuilder();
					
					if(empty($foreach_json) || empty($as_placeholder)) {
						$pass = false;
						break;
					}
					
					if(false == ($json = json_decode($tpl_builder->build($foreach_json, $dict), true)) || !is_array($json)) {
						$pass = false;
						break;
					}
					
					$as_placeholder_stack = $as_placeholder . '__stack';
					$dict->$as_placeholder_stack = $json;
					
					if($replay_id)
						break;
					
					$pass = true;
					EventListener_Triggers::logNode($node_id);
					break;
					
				case 'outcome':
					if($replay_id)
						break;
					
					@$cond_groups = $nodes[$node_id]->params['groups'];
					
					if(is_array($cond_groups))
					foreach($cond_groups as $cond_group) {
						@$any = intval($cond_group['any']);
						@$conditions = $cond_group['conditions'];
						$group_pass = true;
						$logger->info(sprintf("Conditions are in `%s` group.", ($any ? 'any' : 'all')));
						
						if(!empty($conditions) && is_array($conditions))
						foreach($conditions as $condition_data) {
							// If something failed and we require all to pass
							if(!$group_pass && empty($any))
								continue;
								
							if(!isset($condition_data['condition']))
								continue;
							
							$condition = $condition_data['condition'];
							
							$group_pass = $event->runCondition($condition, $this, $condition_data, $dict);
							
							// Any
							if($group_pass && !empty($any))
								break;
						}
						
						$pass = $group_pass;
						
						// Any condition group failing is enough to stop
						if(empty($pass))
							break;
					}
					
					if($pass)
						EventListener_Triggers::logNode($node_id);
					break;
					
				case 'switch':
					if($replay_id)
						break;
					
					$pass = true;
					EventListener_Triggers::logNode($node_id);
					break;
					
				case 'action':
					$pass = true;
					EventListener_Triggers::logNode($node_id);
					
					// Run all the actions
					if(is_array(@$nodes[$node_id]->params['actions']))
					foreach($nodes[$node_id]->params['actions'] as $params) {
						if(!isset($params['action']))
							continue;
						
						$action = $params['action'];
						
						if(!$replay_id || $action == '_run_subroutine')
							$event->runAction($action, $this, $params, $dict, $dry_run);
						
						if(isset($dict->__exit)) {
							$path[] = $node_id;
							$path[] = ('suspend' == $dict->__exit) ? 'SUSPEND' : 'STOP';
							return;
						}
						
						switch($action) {
							case '_run_subroutine':
								if($dict->__goto) {
									$path[] = $node_id;
									
									@$new_state = intval($dict->__goto);
									unset($dict->__goto);
									
									$this->_recurseRunTree($event, $nodes, $tree, $new_state, $dict, $path, $replay, $dry_run);
									return;
								}
								break;
						}
					}
					
					break;
			}
			
			if($nodes[$node_id]->node_type == 'outcome' && !$replay_id) {
				$logger->info('');
				$logger->info($pass ? 'Using this outcome.' : 'Skipping this outcome.');
			}
			$logger->info('');
		}
		
		if($pass)
			$path[] = $node_id;

		$switch = false;
		$loop = false;
		
		do {
			if($node_id && 'loop' == $nodes[$node_id]->node_type) {
				@$as_placeholder = $nodes[$node_id]->params['as_placeholder'];
				@$as_placeholder_key = $as_placeholder . '__key';
				@$as_placeholder_stack = $as_placeholder . '__stack';
				
				if(is_array($dict->$as_placeholder_stack) && !empty($dict->$as_placeholder_stack)) {
					$dict->$as_placeholder_key = key($dict->$as_placeholder_stack);
					$dict->$as_placeholder = current($dict->$as_placeholder_stack);
					array_shift($dict->$as_placeholder_stack);
					$loop = true;
				} else {
					unset($dict->$as_placeholder);
					unset($dict->$as_placeholder_key);
					unset($dict->$as_placeholder_stack);
					break;
				}
			}
			
			foreach($tree[$node_id] as $child_id) {
				// Then continue navigating down the tree...
				$parent_type = empty($node_id) ? 'trigger' : $nodes[$node_id]->node_type;
				$child_type = $nodes[$child_id]->node_type;
				
				if(!empty($replay)) {
					reset($replay);
					$replay_child_id = current($replay);
					
					if($replay_child_id != $child_id)
						continue;
				}
				
				switch($child_type) {
					// Always run all actions
					case 'action':
						if($pass) {
							$this->_recurseRunTree($event, $nodes, $tree, $child_id, $dict, $path, $replay, $dry_run);
							
							// If one of the actions said to stop...
							if(true === in_array(end($path) ?: '', ['STOP','SUSPEND']))
								return;
						}
						break;
						
					case 'subroutine':
						// Don't automatically run subroutines
						break;
						
					default:
						switch($parent_type) {
							case 'trigger':
								if($pass)
									$this->_recurseRunTree($event, $nodes, $tree, $child_id, $dict, $path, $replay, $dry_run);
								break;
								
							case 'subroutine':
								if($pass)
									$this->_recurseRunTree($event, $nodes, $tree, $child_id, $dict, $path, $replay, $dry_run);
								break;
								
							case 'loop':
								if($pass)
									$this->_recurseRunTree($event, $nodes, $tree, $child_id, $dict, $path, $replay, $dry_run);
								break;
								
							case 'outcome':
								if($pass)
									$this->_recurseRunTree($event, $nodes, $tree, $child_id, $dict, $path, $replay, $dry_run);
								break;
								
							case 'switch':
								// Only run the first successful child outcome
								if($pass && !$switch)
									if($this->_recurseRunTree($event, $nodes, $tree, $child_id, $dict, $path, $replay, $dry_run))
										$switch = true;
								break;
								
							case 'action':
								// No children
								break;
						}
						break;
				}
			}
			
		} while($loop);
		
		return $pass;
	}
	
	function logUsage($runtime_ms) {
		return DAO_TriggerEvent::logUsage($this->id, $runtime_ms);
	}
	
	function exportToJson($root_id=0) {
		if(null == ($event = $this->getEvent()))
			return;
		
		$ptrs = [
			'0' => [
				'nodes' => [],
			],
		];
		
		$tree_data = $this->getDecisionTreeData();
		
		$nodes = $tree_data['nodes'];
		$depths = $tree_data['depths'];
		
		$root = null;
		
		$statuses = [
			0 => 'live',
			1 => 'disabled',
			2 => 'simulator',
		];
		
		foreach($depths as $node_id => $depth) {
			$node = $nodes[$node_id]; /* @var $node Model_DecisionNode */
			
			$ptrs[$node->id] = array(
				'type' => $node->node_type,
				'title' => $node->title,
				'status' => $statuses[$node->status_id],
			);
			
			if(!empty($node->params_json))
				$ptrs[$node->id]['params'] = json_decode($node->params_json, true);
			
			$parent =& $ptrs[$node->parent_id];
			
			if(!isset($parent['nodes']))
				$parent['nodes'] = [];
			
			$ptr =& $ptrs[$node->id];
			
			if($node->id == $root_id) {
				$root = [];
				$root[] =& $ptr;
			}
			
			$parent['nodes'][] =& $ptr;
		}
		
		$export_type = 'behavior_fragment';
		
		if(!$root_id || is_null($root)) {
			$root = $ptrs[0]['nodes'];
			$export_type = 'behavior';
		}
		
		$array = array(
			$export_type => array(
				'title' => $this->title,
				'is_disabled' => $this->is_disabled ? true : false,
				'is_private' => $this->is_private ? true : false,
				'priority' => $this->priority,
				'event' => array(
					'key' => $this->event_point,
					'label' => $event->manifest->name,
				),
			),
		);
		
		if(isset($this->event_params) && !empty($this->event_params))
			$array[$export_type]['event']['params'] = $this->event_params;
		
		if(!empty($this->variables))
			$array[$export_type]['variables'] = $this->variables;
		
		if($root)
			$array[$export_type]['nodes'] = $root;
		
		return DevblocksPlatform::strFormatJson($array);
	}
};

class View_TriggerEvent extends C4_AbstractView implements IAbstractView_Subtotals, IAbstractView_QuickSearch {
	const DEFAULT_ID = 'trigger';

	function __construct() {
		$this->id = self::DEFAULT_ID;
		$this->name = DevblocksPlatform::translateCapitalized('common.behaviors');
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_TriggerEvent::PRIORITY;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_TriggerEvent::EVENT_POINT,
			SearchFields_TriggerEvent::BOT_ID,
			SearchFields_TriggerEvent::PRIORITY,
			SearchFields_TriggerEvent::UPDATED_AT,
		);
		
		$this->addColumnsHidden(array(
			SearchFields_TriggerEvent::VIRTUAL_BOT_SEARCH,
			SearchFields_TriggerEvent::VIRTUAL_USABLE_BY,
		));
		
		$this->addParamsHidden(array(
			SearchFields_TriggerEvent::VIRTUAL_BOT_SEARCH,
			SearchFields_TriggerEvent::VIRTUAL_USABLE_BY,
		));
		
		$this->doResetCriteria();
	}

	function getData() {
		$objects = DAO_TriggerEvent::search(
			$this->view_columns,
			$this->getParams(),
			$this->renderLimit,
			$this->renderPage,
			$this->renderSortBy,
			$this->renderSortAsc,
			$this->renderTotal
		);
		
		$this->_lazyLoadCustomFieldsIntoObjects($objects, 'SearchFields_TriggerEvent');
		
		return $objects;
	}
	
	function getDataAsObjects($ids=null) {
		return $this->_getDataAsObjects('DAO_TriggerEvent', $ids);
	}
	
	function getDataSample($size) {
		return $this->_doGetDataSample('DAO_TriggerEvent', $size);
	}

	function getSubtotalFields() {
		$all_fields = $this->getParamsAvailable(true);
		
		$fields = array();

		if(is_array($all_fields))
		foreach($all_fields as $field_key => $field_model) {
			$pass = false;
			
			switch($field_key) {
				// Fields
				case SearchFields_TriggerEvent::EVENT_POINT:
				case SearchFields_TriggerEvent::IS_DISABLED:
				case SearchFields_TriggerEvent::IS_PRIVATE:
				case SearchFields_TriggerEvent::PRIORITY:
				case SearchFields_TriggerEvent::BOT_ID:
					$pass = true;
					break;
					
				// Virtuals
				case SearchFields_TriggerEvent::VIRTUAL_WATCHERS:
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
		$context = CerberusContexts::CONTEXT_BEHAVIOR;

		if(!isset($fields[$column]))
			return array();
		
		switch($column) {
			case SearchFields_TriggerEvent::EVENT_POINT:
				$events = Extension_DevblocksEvent::getAll(false);
				$labels = array_column(json_decode(json_encode($events), true), 'name', 'id');
				$counts = $this->_getSubtotalCountForStringColumn($context, $column, $labels);
				break;
				
			case SearchFields_TriggerEvent::IS_DISABLED:
			case SearchFields_TriggerEvent::IS_PRIVATE:
				$counts = $this->_getSubtotalCountForBooleanColumn($context, $column);
				break;
				
			case SearchFields_TriggerEvent::PRIORITY:
				$counts = $this->_getSubtotalCountForNumberColumn($context, $column);
				break;

			case SearchFields_TriggerEvent::BOT_ID:
				$bots = DAO_Bot::getAll();
				$labels = array_column(json_decode(json_encode($bots), true), 'name', 'id');
				$counts = $this->_getSubtotalCountForStringColumn($context, $column, $labels);
				break;
				
			case SearchFields_TriggerEvent::VIRTUAL_WATCHERS:
				$counts = $this->_getSubtotalCountForWatcherColumn($context, $column);
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
		$search_fields = SearchFields_TriggerEvent::getFields();
		
		$event_extensions = DevblocksPlatform::getExtensions('devblocks.event', false);
		DevblocksPlatform::sortObjects($event_extensions, 'name');
		
		$events = array_column(DevblocksPlatform::objectsToArrays($event_extensions), 'name', 'id');
		
		$fields = array(
			'text' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_TriggerEvent::TITLE, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'bot' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_VIRTUAL,
					'options' => array('param_key' => SearchFields_TriggerEvent::VIRTUAL_BOT_SEARCH),
					'examples' => [
						['type' => 'search', 'context' => CerberusContexts::CONTEXT_BOT, 'q' => ''],
					]
				),
			'bot.id' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_NUMBER,
					'options' => array('param_key' => SearchFields_TriggerEvent::BOT_ID),
					'examples' => [
						['type' => 'chooser', 'context' => CerberusContexts::CONTEXT_BOT, 'q' => ''],
					]
				),
			'disabled' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_BOOL,
					'options' => array('param_key' => SearchFields_TriggerEvent::IS_DISABLED),
				),
			'event' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_TriggerEvent::EVENT_POINT),
					'examples' => [
						['type' => 'list', 'values' => $events],
					]
				),
			'id' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_NUMBER,
					'options' => array('param_key' => SearchFields_TriggerEvent::ID),
					'examples' => [
						['type' => 'chooser', 'context' => CerberusContexts::CONTEXT_BEHAVIOR, 'q' => ''],
					]
				),
			'priority' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_NUMBER,
					'options' => array('param_key' => SearchFields_TriggerEvent::PRIORITY),
				),
			'private' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_BOOL,
					'options' => array('param_key' => SearchFields_TriggerEvent::IS_PRIVATE),
				),
			'name' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_TriggerEvent::TITLE, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'updated' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_TriggerEvent::UPDATED_AT),
				),
			'usableBy.bot' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_VIRTUAL,
					'options' => array('param_key' => SearchFields_TriggerEvent::VIRTUAL_USABLE_BY),
					'examples' => [
						['type' => 'chooser', 'context' => CerberusContexts::CONTEXT_BOT, 'q' => ''],
					]
				),
			'usableBy.worker' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_VIRTUAL,
					'options' => array('param_key' => SearchFields_TriggerEvent::VIRTUAL_USABLE_BY),
					'examples' => [
						['type' => 'chooser', 'context' => CerberusContexts::CONTEXT_WORKER, 'q' => ''],
					]
				),
		);
		
		// Add searchable custom fields
		
		$fields = self::_appendFieldsFromQuickSearchContext(CerberusContexts::CONTEXT_BEHAVIOR, $fields, null);
		
		// Add is_sortable
		
		$fields = self::_setSortableQuickSearchFields($fields, $search_fields);
		
		// Sort by keys
		ksort($fields);
		
		return $fields;
	}	
	
	function getParamFromQuickSearchFieldTokens($field, $tokens) {
		switch($field) {
			case 'bot':
				return DevblocksSearchCriteria::getVirtualQuickSearchParamFromTokens($field, $tokens, SearchFields_TriggerEvent::VIRTUAL_BOT_SEARCH);
				break;
				
			case 'usableBy.bot':
				$oper = $value = null;
				CerbQuickSearchLexer::getOperStringFromTokens($tokens, $oper, $value);
				$bot_id = intval($value);
				
				return new DevblocksSearchCriteria(
					SearchFields_TriggerEvent::VIRTUAL_USABLE_BY,
					DevblocksSearchCriteria::OPER_CUSTOM,
					['context' => CerberusContexts::CONTEXT_BOT, 'id' => $bot_id]
				);
				break;
				
			case 'usableBy.worker':
				$oper = $value = null;
				CerbQuickSearchLexer::getOperStringFromTokens($tokens, $oper, $value);
				$worker_id = intval($value);
				
				return new DevblocksSearchCriteria(
					SearchFields_TriggerEvent::VIRTUAL_USABLE_BY,
					DevblocksSearchCriteria::OPER_CUSTOM,
					['context' => CerberusContexts::CONTEXT_WORKER, 'id' => $worker_id]
				);
				break;
				
			default:
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

		// Bots
		$bots = DAO_Bot::getAll();
		$tpl->assign('bots', $bots);
		
		// Events
		$events = Extension_DevblocksEvent::getAll(false);
		$tpl->assign('events', $events);
		
		// Custom fields
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_BEHAVIOR);
		$tpl->assign('custom_fields', $custom_fields);

		$tpl->assign('view_template', 'devblocks:cerberusweb.core::internal/bot/behavior/view.tpl');
		$tpl->display('devblocks:cerberusweb.core::internal/views/subtotals_and_view.tpl');
	}

	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);

		switch($field) {
			case SearchFields_TriggerEvent::TITLE:
			case SearchFields_TriggerEvent::EVENT_POINT:
			case SearchFields_TriggerEvent::BOT_ID:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
				
			case SearchFields_TriggerEvent::ID:
			case SearchFields_TriggerEvent::PRIORITY:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__number.tpl');
				break;
				
			case SearchFields_TriggerEvent::IS_DISABLED:
			case SearchFields_TriggerEvent::IS_PRIVATE:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__bool.tpl');
				break;
				
			case SearchFields_TriggerEvent::UPDATED_AT:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__date.tpl');
				break;
				
			case SearchFields_TriggerEvent::VIRTUAL_WATCHERS:
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
			case SearchFields_TriggerEvent::EVENT_POINT:
				$events = Extension_DevblocksEvent::getAll(false);
				$labels = array_column(json_decode(json_encode($events), true), 'name', 'id');
				parent::_renderCriteriaParamString($param, $labels);
				break;
				
			case SearchFields_TriggerEvent::IS_DISABLED:
			case SearchFields_TriggerEvent::IS_PRIVATE:
				parent::_renderCriteriaParamBoolean($param);
				break;
				
			case SearchFields_TriggerEvent::BOT_ID:
				$bots = DAO_Bot::getAll();
				$labels = array_column(json_decode(json_encode($bots), true), 'name', 'id');
				parent::_renderCriteriaParamString($param, $labels);
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
			case SearchFields_TriggerEvent::VIRTUAL_BOT_SEARCH:
				echo sprintf("%s matches <b>%s</b>",
					DevblocksPlatform::strEscapeHtml(DevblocksPlatform::translateCapitalized('common.bot')),
					DevblocksPlatform::strEscapeHtml($param->value)
				);
				break;
				
			case SearchFields_TriggerEvent::VIRTUAL_USABLE_BY:
				if(!is_array($param->value) || !isset($param->value['context']))
					return;
				
				switch($param->value['context']) {
					case CerberusContexts::CONTEXT_BOT:
						if(false == ($bot = DAO_Bot::get($param->value['id']))) {
							$bot_name = '(invalid bot)';
						} else {
							$bot_name = $bot->name;
						}
						
						echo sprintf("Usable by %s <b>%s</b>",
							DevblocksPlatform::strEscapeHtml(DevblocksPlatform::translate('common.bot', DevblocksPlatform::TRANSLATE_LOWER)),
							DevblocksPlatform::strEscapeHtml($bot_name)
						);
						break;
					
					case CerberusContexts::CONTEXT_WORKER:
						if(false == ($worker = DAO_Worker::get($param->value['id']))) {
							$worker_name = '(invalid worker)';
						} else {
							$worker_name = $worker->getName();
						}
						
						echo sprintf("Usable by %s <b>%s</b>",
							DevblocksPlatform::strEscapeHtml(DevblocksPlatform::translate('common.worker', DevblocksPlatform::TRANSLATE_LOWER)),
							DevblocksPlatform::strEscapeHtml($worker_name)
						);
						break;
				}
				
				break;
			
			case SearchFields_TriggerEvent::VIRTUAL_WATCHERS:
				$this->_renderVirtualWatchers($param);
				break;
		}
	}
	
	function getFields() {
		return SearchFields_TriggerEvent::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		switch($field) {
			case SearchFields_TriggerEvent::TITLE:
			case SearchFields_TriggerEvent::EVENT_POINT:
				$criteria = $this->_doSetCriteriaString($field, $oper, $value);
				break;
				
			case SearchFields_TriggerEvent::ID:
			case SearchFields_TriggerEvent::PRIORITY:
			case SearchFields_TriggerEvent::BOT_ID:
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;
				
			case SearchFields_TriggerEvent::UPDATED_AT:
				$criteria = $this->_doSetCriteriaDate($field, $oper);
				break;

			case SearchFields_TriggerEvent::IS_DISABLED:
			case SearchFields_TriggerEvent::IS_PRIVATE:
				@$bool = DevblocksPlatform::importGPC($_REQUEST['bool'],'integer',1);
				$criteria = new DevblocksSearchCriteria($field,$oper,$bool);
				break;
				
			case SearchFields_TriggerEvent::VIRTUAL_WATCHERS:
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
};

class Context_TriggerEvent extends Extension_DevblocksContext implements IDevblocksContextProfile, IDevblocksContextPeek { // IDevblocksContextImport
	static function isReadableByActor($models, $actor) {
		return CerberusContexts::isReadableByDelegateOwner($actor, CerberusContexts::CONTEXT_BEHAVIOR, $models, 'bot_owner_');
	}
	
	static function isWriteableByActor($models, $actor) {
		return CerberusContexts::isWriteableByDelegateOwner($actor, CerberusContexts::CONTEXT_BEHAVIOR, $models, 'bot_owner_');
	}
	
	function getRandom() {
		return DAO_TriggerEvent::random();
	}
	
	function profileGetUrl($context_id) {
		if(empty($context_id))
			return '';
	
		$url_writer = DevblocksPlatform::getUrlService();
		$url = $url_writer->writeNoProxy('c=profiles&type=trigger_event&id='.$context_id, true);
		return $url;
	}
	
	function getMeta($context_id) {
		$trigger_event = DAO_TriggerEvent::get($context_id);
		$url_writer = DevblocksPlatform::getUrlService();
		
		$url = $this->profileGetUrl($context_id);
		$friendly = DevblocksPlatform::strToPermalink($trigger_event->title);
		
		if(!empty($friendly))
			$url .= '-' . $friendly;
		
		return array(
			'id' => $trigger_event->id,
			'name' => $trigger_event->title,
			'permalink' => $url,
			'updated' => $trigger_event->updated_at,
		);
	}
	
	function getDefaultProperties() {
		return array(
			'bot__label',
			'priority',
			'updated_at',
			'is_disabled',
		);
	}
	
	function getContext($trigger_event, &$token_labels, &$token_values, $prefix=null) {
		if(is_null($prefix))
			$prefix = 'Behavior:';
		
		$translate = DevblocksPlatform::getTranslationService();
		$fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_BEHAVIOR);

		// Polymorph
		if(is_numeric($trigger_event)) {
			$trigger_event = DAO_TriggerEvent::get($trigger_event);
		} elseif($trigger_event instanceof Model_TriggerEvent) {
			// It's what we want already.
		} elseif(is_array($trigger_event)) {
			$trigger_event = Cerb_ORMHelper::recastArrayToModel($trigger_event, 'Model_TriggerEvent');
		} else {
			$trigger_event = null;
		}
		
		// Token labels
		$token_labels = array(
			'_label' => $prefix,
			'event_point' => $prefix.$translate->_('common.event'),
			'event_point_name' => $prefix.$translate->_('common.event'),
			'id' => $prefix.$translate->_('common.id'),
			'is_disabled' => $prefix.$translate->_('dao.trigger_event.is_disabled'),
			'is_private' => $prefix.$translate->_('dao.trigger_event.is_private'),
			'name' => $prefix.$translate->_('common.name'),
			'priority' => $prefix.$translate->_('common.priority'),
			'updated_at' => $prefix.$translate->_('common.updated'),
			'record_url' => $prefix.$translate->_('common.url.record'),
		);
		
		// Token types
		$token_types = array(
			'_label' => 'context_url',
			'event_point' => Model_CustomField::TYPE_SINGLE_LINE,
			'event_point_name' => Model_CustomField::TYPE_SINGLE_LINE,
			'id' => Model_CustomField::TYPE_NUMBER,
			'is_disabled' => Model_CustomField::TYPE_CHECKBOX,
			'is_private' => Model_CustomField::TYPE_CHECKBOX,
			'name' => Model_CustomField::TYPE_SINGLE_LINE,
			'priority' => Model_CustomField::TYPE_NUMBER,
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
		
		$token_values['_context'] = CerberusContexts::CONTEXT_BEHAVIOR;
		$token_values['_types'] = $token_types;
		
		if($trigger_event) {
			$token_values['_loaded'] = true;
			$token_values['_label'] = $trigger_event->title;
			$token_values['event_point'] = $trigger_event->event_point;
			$token_values['id'] = $trigger_event->id;
			$token_values['is_disabled'] = $trigger_event->is_disabled;
			$token_values['is_private'] = $trigger_event->is_private;
			$token_values['name'] = $trigger_event->title;
			$token_values['priority'] = $trigger_event->priority;
			$token_values['updated_at'] = $trigger_event->updated_at;
			
			$token_values['bot_id'] = $trigger_event->bot_id;
			
			if(null != ($event = $trigger_event->getEvent())) {
				$token_values['event_point_name'] = $event->manifest->name;
			}
			
			// Custom fields
			$token_values = $this->_importModelCustomFieldsAsValues($trigger_event, $token_values);
			
			// URL
			$url_writer = DevblocksPlatform::getUrlService();
			$token_values['record_url'] = $url_writer->writeNoProxy(sprintf("c=profiles&type=behavior&id=%d-%s",$trigger_event->id, DevblocksPlatform::strToPermalink($trigger_event->title)), true);
		}
		
		// Bot
		$merge_token_labels = array();
		$merge_token_values = array();
		CerberusContexts::getContext(CerberusContexts::CONTEXT_BOT, null, $merge_token_labels, $merge_token_values, '', true);

			CerberusContexts::merge(
				'bot_',
				$prefix.'Bot:',
				$merge_token_labels,
				$merge_token_values,
				$token_labels,
				$token_values
			);
		
		return true;
	}

	function lazyLoadContextValues($token, $dictionary) {
		if(!isset($dictionary['id']))
			return;
		
		$context = CerberusContexts::CONTEXT_BEHAVIOR;
		$context_id = $dictionary['id'];
		
		@$is_loaded = $dictionary['_loaded'];
		$values = array();
		
		if(!$is_loaded) {
			$labels = array();
			CerberusContexts::getContext($context, $context_id, $labels, $values, null, true, true);
		}
		
		switch($token) {
			case 'links':
				$links = $this->_lazyLoadLinks($context, $context_id);
				$values = array_merge($values, $links);
				break;
			
			case 'watchers':
				$watchers = array(
					$token => CerberusContexts::getWatchers($context, $context_id, true),
				);
				$values = array_merge($values, $watchers);
				break;
				
			default:
				if(DevblocksPlatform::strStartsWith($token, 'custom_')) {
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
		$view->name = 'Behavior';
		/*
		$view->addParams(array(
			SearchFields_TriggerEvent::UPDATED_AT => new DevblocksSearchCriteria(SearchFields_TriggerEvent::UPDATED_AT,'=',0),
		), true);
		*/
		$view->renderSortBy = SearchFields_TriggerEvent::PRIORITY;
		$view->renderSortAsc = true;
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
		$view->name = 'Behavior';
		
		$params_req = array();
		
		if(!empty($context) && !empty($context_id)) {
			$params_req = array(
				new DevblocksSearchCriteria(SearchFields_TriggerEvent::CONTEXT_LINK,'=',$context),
				new DevblocksSearchCriteria(SearchFields_TriggerEvent::CONTEXT_LINK_ID,'=',$context_id),
			);
		}
		
		$view->addParamsRequired($params_req, true);
		
		$view->renderTemplate = 'context';
		return $view;
	}
	
	function renderPeekPopup($context_id=0, $view_id='', $edit=false) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('view_id', $view_id);
		
		$context = CerberusContexts::CONTEXT_BEHAVIOR;
		$active_worker = CerberusApplication::getActiveWorker();
		
		if(!empty($context_id)) {
			$model = DAO_TriggerEvent::get($context_id);
			$tpl->assign('model', $model);
		}
		
		if(empty($context_id) || $edit) {
			// Custom Fields
			$custom_fields = DAO_CustomField::getByContext($context, false);
			$tpl->assign('custom_fields', $custom_fields);
	
			$custom_field_values = DAO_CustomFieldValue::getValuesByContextIds($context, $context_id);
			if(isset($custom_field_values[$context_id]))
				$tpl->assign('custom_field_values', $custom_field_values[$context_id]);
			
			$types = Model_CustomField::getTypes();
			$tpl->assign('types', $types);
			
			$bots = DAO_Bot::getAll();
			$tpl->assign('bots', $bots);
			
			if(!empty($model)) {
				$ext = DevblocksPlatform::getExtension($model->event_point, false);
				$tpl->assign('ext', $ext);
				
				if(isset($bots[$model->bot_id]))
					$tpl->assign('bot', $bots[$model->bot_id]);
			}
			
			// Check view for defaults by filter
			if(false != ($view = C4_AbstractViewLoader::getView($view_id))) {
				$filters = $view->findParam(SearchFields_TriggerEvent::BOT_ID, $view->getParams());
				
				if(false != ($filter = array_shift($filters))) {
					$bot_id = is_array($filter->value) ? array_shift($filter->value) : $filter->value;
					
					if(false !== ($bot = $bots[$bot_id])) {
						$tpl->assign('bot', $bot);
						
						$events = Extension_DevblocksEvent::getByContext($bot->owner_context, false);
			
						// Filter the available events by VA
						$events = $bot->filterEventsByAllowed($events);
						
						$tpl->assign('events', $events);
					}
				}
			}
			
			// Contexts that can show up in VA vars
			$list_contexts = Extension_DevblocksContext::getAll(false, 'va_variable');
			$tpl->assign('list_contexts', $list_contexts);
			
			$tpl->display('devblocks:cerberusweb.core::internal/bot/behavior/peek_edit.tpl');
			
		} else {
			// Counts
			$activity_counts = array(
				'comments' => DAO_Comment::count(CerberusContexts::CONTEXT_BEHAVIOR, $context_id),
			);
			$tpl->assign('activity_counts', $activity_counts);
			
			// Links
			$links = array(
				CerberusContexts::CONTEXT_BEHAVIOR => array(
					$context_id => 
						DAO_ContextLink::getContextLinkCounts(
							CerberusContexts::CONTEXT_BEHAVIOR,
							$context_id,
							array(CerberusContexts::CONTEXT_CUSTOM_FIELDSET)
						),
				),
			);
			$tpl->assign('links', $links);
			
			// Timeline
			if($context_id) {
				$timeline_json = Page_Profiles::getTimelineJson(Extension_DevblocksContext::getTimelineComments(CerberusContexts::CONTEXT_BEHAVIOR, $context_id));
				$tpl->assign('timeline_json', $timeline_json);
			}

			// Context
			if(false == ($context_ext = Extension_DevblocksContext::get(CerberusContexts::CONTEXT_BEHAVIOR)))
				return;
			
			// Dictionary
			$labels = array();
			$values = array();
			CerberusContexts::getContext(CerberusContexts::CONTEXT_BEHAVIOR, $model, $labels, $values, '', true, false);
			$dict = DevblocksDictionaryDelegate::instance($values);
			$tpl->assign('dict', $dict);
			
			if(false == ($event = $model->getEvent()))
				return;
			
			if(false == ($va = $model->getBot()))
				return;
			
			$tpl->assign('behavior', $model);
			$tpl->assign('event', $event->manifest);
			$tpl->assign('va', $va);
			
			$properties = $context_ext->getCardProperties();
			$tpl->assign('properties', $properties);
			
			$tpl->display('devblocks:cerberusweb.core::internal/bot/behavior/peek.tpl');
		}
		
	}
};
