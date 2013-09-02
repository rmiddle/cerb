<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2013, Webgroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerberusweb.com/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://www.cerberusweb.com	  http://www.webgroupmedia.com/
***********************************************************************/

class DAO_TriggerEvent extends Cerb_ORMHelper {
	const CACHE_ALL = 'cerberus_cache_behavior_all';
	
	const ID = 'id';
	const TITLE = 'title';
	const IS_DISABLED = 'is_disabled';
	const IS_PRIVATE = 'is_private';
	const EVENT_POINT = 'event_point';
	const VIRTUAL_ATTENDANT_ID = 'virtual_attendant_id';
	const POS = 'pos';
	const VARIABLES_JSON = 'variables_json';

	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = "INSERT INTO trigger_event () VALUES ()";
		$db->Execute($sql);
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
			$behaviors = self::getWhere();
			$cache->save($behaviors, self::CACHE_ALL);
		}
		
		return $behaviors;
	}
	
	static function getReadableByActor($actor, $event_point=null, $with_disabled=false) {
		$macros = array();

		$vas = DAO_VirtualAttendant::getReadableByActor($actor);

		if(is_array($vas))
		foreach($vas as $va) { /* @var $va Model_VirtualAttendant */
			if(!$with_disabled && $va->is_disabled)
				continue;
		
			$behaviors = $va->getBehaviors($event_point, $with_disabled);
			
			if(empty($behaviors))
				continue;
			
			$results = array();
			
			if(is_array($behaviors))
			foreach($behaviors as $behavior_id => $behavior) { /* @var $behavior Model_TriggerEvent */
				if($behavior->is_private
					&& !(CerberusContexts::isSameObject($actor, $va)))
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
	static function getByVirtualAttendant($va, $event_point=null, $with_disabled=false, $sort_by='title') {
		// Polymorph if necessary
		if(is_numeric($va))
			$va = DAO_VirtualAttendant::get($va);
		
		// If we didn't resolve to a VA model
		if(!($va instanceof Model_VirtualAttendant))
			return array();
		
		if(!$with_disabled && $va->is_disabled)
			return array();
		
		$behaviors = self::getAll();
		$results = array();

		if(is_array($behaviors))
		foreach($behaviors as $behavior_id => $behavior) { /* @var $behavior Model_TriggerEvent */
			if($behavior->virtual_attendant_id != $va->id)
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
			case 'pos':
				break;
				
			default:
				$sort_by = 'title';
				break;
		}
		
		DevblocksPlatform::sortObjects($results, $sort_by, true);
		
		return $results;
	}
	
	static function getByEvent($event_id, $with_disabled=false) {
		$vas = DAO_VirtualAttendant::getAll();
		$behaviors = array();

		foreach($vas as $va) { /* @var $va Model_VirtualAttendant */
			$va_behaviors = $va->getBehaviors($event_id, $with_disabled);
			
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
	static function getWhere($where=null, $sortBy=DAO_TriggerEvent::POS, $sortAsc=true, $limit=null) {
		$db = DevblocksPlatform::getDatabaseService();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		// SQL
		$sql = "SELECT id, title, is_disabled, is_private, event_point, virtual_attendant_id, pos, variables_json ".
			"FROM trigger_event ".
			$where_sql.
			$sort_sql.
			$limit_sql
		;
		$rs = $db->Execute($sql);
		
		return self::_getObjectsFromResult($rs);
	}
	
	/**
	 * @param resource $rs
	 * @return Model_TriggerEvent[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		if(!is_resource($rs))
			return $objects;
		
		while($row = mysql_fetch_assoc($rs)) {
			$object = new Model_TriggerEvent();
			$object->id = intval($row['id']);
			$object->title = $row['title'];
			$object->is_disabled = intval($row['is_disabled']);
			$object->is_private = intval($row['is_private']);
			$object->event_point = $row['event_point'];
			$object->virtual_attendant_id = $row['virtual_attendant_id'];
			$object->pos = intval($row['pos']);
			$object->variables = @json_decode($row['variables_json'], true);
			$objects[$object->id] = $object;
		}
		
		mysql_free_result($rs);
		
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
		
		$db->Execute($sql);
	}
	
	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		// [TODO] Use DAO_DecisionNode::deleteByTrigger() to cascade
		$db->Execute(sprintf("DELETE FROM decision_node WHERE trigger_id IN (%s)", $ids_list));
		
		$db->Execute(sprintf("DELETE FROM trigger_event WHERE id IN (%s)", $ids_list));
		
		$db->Execute(sprintf("DELETE FROM trigger_event_history WHERE trigger_id IN (%s)", $ids_list));
		
		self::clearCache();
		return true;
	}
	
	static function deleteByVirtualAttendant($va_id) {
		$results = self::getWhere(sprintf("%s = %d",
			self::VIRTUAL_ATTENDANT_ID,
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
		
		// Sanitize
		if('*'==substr($sortBy,0,1) || !isset($fields[$sortBy]))
			$sortBy=null;

		list($tables,$wheres) = parent::_parseSearchParams($params, $columns, $fields, $sortBy);
		
		$select_sql = sprintf("SELECT ".
			"trigger_event.id as %s, ".
			"trigger_event.title as %s, ".
			"trigger_event.is_disabled as %s, ".
			"trigger_event.is_private as %s, ".
			"trigger_event.virtual_attendant_id as %s, ".
			"trigger_event.event_point as %s ",
				SearchFields_TriggerEvent::ID,
				SearchFields_TriggerEvent::TITLE,
				SearchFields_TriggerEvent::IS_DISABLED,
				SearchFields_TriggerEvent::IS_PRIVATE,
				SearchFields_TriggerEvent::VIRTUAL_ATTENDANT_ID,
				SearchFields_TriggerEvent::EVENT_POINT
			);
			
		$join_sql = "FROM trigger_event ";
		
		// Custom field joins
		//list($select_sql, $join_sql, $has_multiple_values) = self::_appendSelectJoinSqlForCustomFieldTables(
		//	$tables,
		//	$params,
		//	'trigger.id',
		//	$select_sql,
		//	$join_sql
		//);
		$has_multiple_values = false; // [TODO] Temporary when custom fields disabled
				
		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = (!empty($sortBy)) ? sprintf("ORDER BY %s %s ",$sortBy,($sortAsc || is_null($sortAsc))?"ASC":"DESC") : " ";
	
		return array(
			'primary_table' => 'trigger_event',
			'select' => $select_sql,
			'join' => $join_sql,
			'where' => $where_sql,
			'has_multiple_values' => $has_multiple_values,
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
		$has_multiple_values = $query_parts['has_multiple_values'];
		$sort_sql = $query_parts['sort'];
		
		$sql =
			$select_sql.
			$join_sql.
			$where_sql.
			($has_multiple_values ? 'GROUP BY trigger_event.id ' : '').
			$sort_sql;
			
		if($limit > 0) {
			$rs = $db->SelectLimit($sql,$limit,$page*$limit) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		} else {
			$rs = $db->Execute($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
			$total = mysql_num_rows($rs);
		}
		
		$results = array();
		$total = -1;
		
		while($row = mysql_fetch_assoc($rs)) {
			$result = array();
			foreach($row as $f => $v) {
				$result[$f] = $v;
			}
			$object_id = intval($row[SearchFields_TriggerEvent::ID]);
			$results[$object_id] = $result;
		}

		// [JAS]: Count all
		if($withCounts) {
			$count_sql =
				($has_multiple_values ? "SELECT COUNT(DISTINCT trigger_event.id) " : "SELECT COUNT(trigger_event.id) ").
				$join_sql.
				$where_sql;
			$total = $db->GetOne($count_sql);
		}
		
		mysql_free_result($rs);
		
		return array($results,$total);
	}

	static public function clearCache() {
		$cache = DevblocksPlatform::getCacheService();
		$cache->remove(self::CACHE_ALL);
	}
	
	static public function setTriggersOrder($trigger_ids) {
		$db = DevblocksPlatform::getDatabaseService();
		
		// No point in sorting fewer than two triggers
		if(count($trigger_ids) < 2)
			return;
		
		foreach($trigger_ids as $pos => $trigger_id) {
			if(empty($trigger_id))
				continue;
			
			$db->Execute(sprintf("UPDATE trigger_event SET pos = %d WHERE id = %d",
				$pos,
				$trigger_id
			));
		}
		
		self::clearCache();
	}
	
	static public function getNextPosByVirtualAttendantAndEvent($va_id, $event_point) {
		$db = DevblocksPlatform::getDatabaseService();

		$count = $db->GetOne(sprintf("SELECT MAX(pos) FROM trigger_event ".
			"WHERE virtual_attendant_id = %d AND event_point = %s",
			$va_id,
			$db->qstr($event_point)
		));

		if(is_null($count))
			return 0;

		return intval($count) + 1;
	}
};

class SearchFields_TriggerEvent implements IDevblocksSearchFields {
	const ID = 't_id';
	const TITLE = 't_title';
	const IS_DISABLED = 't_is_disabled';
	const IS_PRIVATE = 't_is_private';
	const VIRTUAL_ATTENDANT_ID = 't_virtual_attendant_id';
	const EVENT_POINT = 't_event_point';
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		$translate = DevblocksPlatform::getTranslationService();
		
		$columns = array(
			self::ID => new DevblocksSearchField(self::ID, 'trigger_event', 'id', $translate->_('common.id')),
			self::TITLE => new DevblocksSearchField(self::TITLE, 'trigger_event', 'title', $translate->_('common.title')),
			self::IS_DISABLED => new DevblocksSearchField(self::IS_DISABLED, 'trigger_event', 'is_disabled', $translate->_('dao.trigger_event.is_disabled')),
			self::IS_PRIVATE => new DevblocksSearchField(self::IS_PRIVATE, 'trigger_event', 'is_private', $translate->_('dao.trigger_event.is_private')),
			self::VIRTUAL_ATTENDANT_ID => new DevblocksSearchField(self::VIRTUAL_ATTENDANT_ID, 'trigger_event', 'virtual_attendant_id', $translate->_('common.virtual_attendant')),
			self::EVENT_POINT => new DevblocksSearchField(self::EVENT_POINT, 'trigger_event', 'event_point', $translate->_('common.event')),
		);
		
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
	public $event_point;
	public $virtual_attendant_id;
	public $pos;
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
	
	public function getVirtualAttendant() {
		return DAO_VirtualAttendant::get($this->virtual_attendant_id);
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
						
						CerberusContexts::getContext($context, $context_id, $labels, $values, null, true);
						
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
	
	public function getNodes() {
		return $this->_getNodes();
	}
	
	public function getDecisionTreeData() {
		$nodes = $this->_getNodes();
		$tree = $this->_getTree();
		$depths = array();
		$this->_recurseBuildTreeDepths($tree, 0, $depths);
		
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
	
	public function runDecisionTree(DevblocksDictionaryDelegate $dict, $dry_run=false) {
		$nodes = $this->_getNodes();
		$tree = $this->_getTree();
		$path = array();
		
		// [TODO] This could be more efficient
		$event = DevblocksPlatform::getExtension($this->event_point, true); /* @var $event Extension_DevblocksEvent */
		
		// Add a convenience pointer
		$dict->_trigger = $this;
		
		$this->_recurseRunTree($event, $nodes, $tree, 0, $dict, $path, $dry_run);
		
		return $path;
	}
	
	private function _recurseRunTree($event, $nodes, $tree, $node_id, DevblocksDictionaryDelegate $dict, &$path, $dry_run=false) {
		$logger = DevblocksPlatform::getConsoleLog("Attendant");
		// Does our current node pass?
		$pass = true;
		
		// If these conditions match...
		if(!empty($node_id)) {
			$logger->info($nodes[$node_id]->node_type . ' :: ' . $nodes[$node_id]->title . ' (' . $node_id . ')');
			
			// Handle the node type
			switch($nodes[$node_id]->node_type) {
				case 'outcome':
					@$cond_groups = $nodes[$node_id]->params['groups'];
					
					if(is_array($cond_groups))
					foreach($cond_groups as $cond_group) {
						@$any = intval($cond_group['any']);
						@$conditions = $cond_group['conditions'];
						$group_pass = true;
						$logger->info(sprintf("Conditions are in '%s' group.", ($any ? 'any' : 'all')));
						
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
						
						$event->runAction($action, $this, $params, $dict, $dry_run);
					}
					break;
			}
			
			if($nodes[$node_id]->node_type == 'outcome') {
				$logger->info('');
				$logger->info($pass ? 'Using this outcome.' : 'Skipping this outcome.');
			}
			$logger->info('');
		}
		
		if($pass)
			$path[$node_id] = $pass;

		$switch = false;
		foreach($tree[$node_id] as $child_id) {
			// Then continue navigating down the tree...
			$parent_type = empty($node_id) ? 'outcome' : $nodes[$node_id]->node_type;
			$child_type = $nodes[$child_id]->node_type;
			
			switch($child_type) {
				// Always run all actions
				case 'action':
					if($pass)
						$this->_recurseRunTree($event, $nodes, $tree, $child_id, $dict, $path, $dry_run);
					break;
					
				default:
					switch($parent_type) {
						case 'outcome':
							if($pass)
								$this->_recurseRunTree($event, $nodes, $tree, $child_id, $dict, $path, $dry_run);
							break;
							
						case 'switch':
							// Only run the first successful child outcome
							if($pass && !$switch)
								if($this->_recurseRunTree($event, $nodes, $tree, $child_id, $dict, $path, $dry_run))
									$switch = true;
							break;
							
						case 'action':
							// No children
							break;
					}
					break;
			}
		}
		
		return $pass;
	}
	
	function logUsage($runtime_ms) {
		return DAO_TriggerEvent::logUsage($this->id, $runtime_ms);
	}
	
	function exportToJson() {
		if(null == ($event = $this->getEvent()))
			return;
		
		$ptrs = array(
			'0' => array(
				'nodes' => array(),
			),
		);
		
		$tree_data = $this->getDecisionTreeData();
		
		$nodes = $tree_data['nodes'];
		$depths = $tree_data['depths'];
		
		foreach($depths as $node_id => $depth) {
			$node = $nodes[$node_id]; /* @var $node Model_DecisionNode */
			
			$ptrs[$node->id] = array(
				'type' => $node->node_type,
				'title' => $node->title,
			);
			
			if(!empty($node->params_json))
				$ptrs[$node->id]['params'] = json_decode($node->params_json, true);
			
			$parent =& $ptrs[$node->parent_id];
			
			if(!isset($parent['nodes']))
				$parent['nodes'] = array();
			
			$parent['nodes'][] =& $ptrs[$node->id];
		}

		$array = array(
			'behavior' => array(
				'title' => $this->title,
				'is_disabled' => $this->is_disabled ? true : false,
				'is_private' => $this->is_private ? true : false,
				'event' => array(
					'key' => $this->event_point,
					'label' => $event->manifest->name,
				),
			),
		);
		
		if(!empty($this->variables))
			$array['behavior']['variables'] = $this->variables;
		
		if(!empty($ptrs[0]['nodes']))
			$array['behavior']['nodes'] = $ptrs[0]['nodes'];
		
		return DevblocksPlatform::strFormatJson(json_encode($array));
	}
};

class View_TriggerEvent extends C4_AbstractView {
	const DEFAULT_ID = 'trigger';

	function __construct() {
		$translate = DevblocksPlatform::getTranslationService();
	
		$this->id = self::DEFAULT_ID;
		$this->name = $translate->_('Trigger');
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_TriggerEvent::ID;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_TriggerEvent::ID,
			SearchFields_TriggerEvent::TITLE,
			SearchFields_TriggerEvent::IS_DISABLED,
			SearchFields_TriggerEvent::VIRTUAL_ATTENDANT_ID,
			SearchFields_TriggerEvent::EVENT_POINT,
		);
		
		$this->addColumnsHidden(array(
		));
		
		$this->addParamsHidden(array(
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
		return $objects;
	}
	
	function getDataSample($size) {
		return $this->_doGetDataSample('DAO_TriggerEvent', $size);
	}

	function render() {
		$this->_sanitize();
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

		// Custom fields
		//$custom_fields = DAO_CustomField::getByContext(CerberusContexts::XXX);
		//$tpl->assign('custom_fields', $custom_fields);

		// [TODO] Set your template path
		$tpl->display('devblocks:example.plugin::path/to/view.tpl');
	}

	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);

		switch($field) {
			case SearchFields_TriggerEvent::TITLE:
			case SearchFields_TriggerEvent::EVENT_POINT:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
			case SearchFields_TriggerEvent::ID:
			case SearchFields_TriggerEvent::VIRTUAL_ATTENDANT_ID:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__number.tpl');
				break;
			case SearchFields_TriggerEvent::IS_DISABLED:
			case SearchFields_TriggerEvent::IS_PRIVATE:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__bool.tpl');
				break;
			case 'placeholder_date':
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__date.tpl');
				break;
			/*
			default:
				// Custom Fields
				if('cf_' == substr($field,0,3)) {
					$this->_renderCriteriaCustomField($tpl, substr($field,3));
				} else {
					echo ' ';
				}
				break;
			*/
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
			case SearchFields_TriggerEvent::VIRTUAL_ATTENDANT_ID:
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;
				
			case 'placeholder_date':
				$criteria = $this->_doSetCriteriaDate($field, $oper);
				break;
				
			case SearchFields_TriggerEvent::IS_DISABLED:
			case SearchFields_TriggerEvent::IS_PRIVATE:
				@$bool = DevblocksPlatform::importGPC($_REQUEST['bool'],'integer',1);
				$criteria = new DevblocksSearchCriteria($field,$oper,$bool);
				break;
				
			/*
			default:
				// Custom Fields
				if(substr($field,0,3)=='cf_') {
					$criteria = $this->_doSetCriteriaCustomField($field, substr($field,3));
				}
				break;
			*/
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
					//$change_fields[DAO_TriggerEvent::EXAMPLE] = 'some value';
					break;
				/*
				default:
					// Custom fields
					if(substr($k,0,3)=="cf_") {
						$custom_fields[substr($k,3)] = $v;
					}
					break;
				*/
			}
		}

		$pg = 0;

		if(empty($ids))
		do {
			list($objects,$null) = DAO_TriggerEvent::search(
				array(),
				$this->getParams(),
				100,
				$pg++,
				SearchFields_TriggerEvent::ID,
				true,
				false
			);
			$ids = array_merge($ids, array_keys($objects));
			 
		} while(!empty($objects));

		$batch_total = count($ids);
		for($x=0;$x<=$batch_total;$x+=100) {
			$batch_ids = array_slice($ids,$x,100);
			
			DAO_TriggerEvent::update($batch_ids, $change_fields);

			// Custom Fields
			//self::_doBulkSetCustomFields(ChCustomFieldSource_TriggerEvent::ID, $custom_fields, $batch_ids);
			
			unset($batch_ids);
		}

		unset($ids);
	}
};

