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

class DAO_Snippet extends Cerb_ORMHelper {
	const ID = 'id';
	const TITLE = 'title';
	const OWNER_CONTEXT = 'owner_context';
	const OWNER_CONTEXT_ID = 'owner_context_id';
	const CONTEXT = 'context';
	const CONTENT = 'content';
	const TOTAL_USES = 'total_uses';
	const UPDATED_AT = 'updated_at';
	const CUSTOM_PLACEHOLDERS_JSON = 'custom_placeholders_json';

	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = sprintf("INSERT INTO snippet () ".
			"VALUES ()"
		);
		$db->ExecuteMaster($sql);
		$id = $db->LastInsertId();
		
		self::update($id, $fields);
		
		return $id;
	}
	
	static function update($ids, $fields, $check_deltas=true) {
		if(!is_array($ids))
			$ids = array($ids);
		
		if(!isset($fields[DAO_Snippet::UPDATED_AT]))
			$fields[DAO_Snippet::UPDATED_AT] = time();
		
		// Make a diff for the requested objects in batches
		
		$chunks = array_chunk($ids, 100, true);
		while($batch_ids = array_shift($chunks)) {
			if(empty($batch_ids))
				continue;

			// Send events
			if($check_deltas) {
				CerberusContexts::checkpointChanges(CerberusContexts::CONTEXT_SNIPPET, $batch_ids);
			}
			
			// Make changes
			parent::_update($batch_ids, 'snippet', $fields);
			
			// Send events
			if($check_deltas) {
				
				// Trigger an event about the changes
				$eventMgr = DevblocksPlatform::getEventService();
				$eventMgr->trigger(
					new Model_DevblocksEvent(
						'dao.snippet.update',
						array(
							'fields' => $fields,
						)
					)
				);
				
				// Log the context update
				DevblocksPlatform::markContextChanged(CerberusContexts::CONTEXT_SNIPPET, $batch_ids);
			}
		}
	}
	
	static function updateWhere($fields, $where) {
		parent::_updateWhere('snippet', $fields, $where);
	}
	
	static function incrementUse($id, $worker_id) {
		$db = DevblocksPlatform::getDatabaseService();

		// Update the aggregate counter
		$sql = sprintf("UPDATE snippet SET total_uses = total_uses + 1 WHERE id = %d", $id);
		$db->ExecuteMaster($sql);

		// Update the per-worker usage-over-time data
		$sql = sprintf("INSERT INTO snippet_use_history (snippet_id, worker_id, ts_day, uses) ".
				"VALUES (%d,%d,%d,1) ".
				"ON DUPLICATE KEY UPDATE uses=uses+1",
				$id,
				$worker_id,
				time()-(time() % 86400) // start of today
		);
		$db->ExecuteMaster($sql);
		
		return TRUE;
	}
	
	/**
	 * @param string $where
	 * @param mixed $sortBy
	 * @param mixed $sortAsc
	 * @param integer $limit
	 * @return Model_Snippet[]
	 */
	static function getWhere($where=null, $sortBy=null, $sortAsc=true, $limit=null) {
		$db = DevblocksPlatform::getDatabaseService();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		// SQL
		$sql = "SELECT id, title, context, owner_context, owner_context_id, content, total_uses, updated_at, custom_placeholders_json ".
			"FROM snippet ".
			$where_sql.
			$sort_sql.
			$limit_sql
		;
		$rs = $db->ExecuteSlave($sql);
		
		return self::_getObjectsFromResult($rs);
	}
	
	/**
	 * @param integer $id
	 * @return Model_Snippet
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
	
	/**
	 * @param resource $rs
	 * @return Model_Snippet[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object = new Model_Snippet();
			$object->id = $row['id'];
			$object->title = $row['title'];
			$object->context = $row['context'];
			$object->owner_context = $row['owner_context'];
			$object->owner_context_id = $row['owner_context_id'];
			$object->content = $row['content'];
			$object->total_uses = intval($row['total_uses']);
			$object->updated_at = intval($row['updated_at']);
			
			$custom_placeholders = null;
			if(false != (@$custom_placeholders = json_decode($row['custom_placeholders_json'], true)) && is_array($custom_placeholders))
				$object->custom_placeholders = $custom_placeholders;
			
			$objects[$object->id] = $object;
		}
		
		mysqli_free_result($rs);
		
		return $objects;
	}
	
	static function maint() {
		$db = DevblocksPlatform::getDatabaseService();
		$logger = DevblocksPlatform::getConsoleLog();
		$tables = DevblocksPlatform::getDatabaseTables();
		
		// Search indexes
		if(isset($tables['fulltext_snippet'])) {
			$db->ExecuteMaster("DELETE FROM fulltext_snippet WHERE id NOT IN (SELECT id FROM snippet)");
			$logger->info('[Maint] Purged ' . $db->Affected_Rows() . ' fulltext_snippet records.');
		}
		
		$db->ExecuteMaster("DELETE FROM snippet_use_history WHERE worker_id NOT IN (SELECT id FROM worker)");
		$logger->info('[Maint] Purged ' . $db->Affected_Rows() . ' snippet_use_history records by worker.');

		$db->ExecuteMaster("DELETE FROM snippet_use_history WHERE snippet_id NOT IN (SELECT id FROM snippet)");
		$logger->info('[Maint] Purged ' . $db->Affected_Rows() . ' snippet_use_history records by snippet.');
	}
	
	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		$db->ExecuteMaster(sprintf("DELETE FROM snippet WHERE id IN (%s)", $ids_list));
		$db->ExecuteMaster(sprintf("DELETE FROM snippet_use_history WHERE snippet_id IN (%s)", $ids_list));
		
		// Fire event
		$eventMgr = DevblocksPlatform::getEventService();
		$eventMgr->trigger(
			new Model_DevblocksEvent(
				'context.delete',
				array(
					'context' => CerberusContexts::CONTEXT_SNIPPET,
					'context_ids' => $ids
				)
			)
		);
		
		return true;
	}
	
	public static function random() {
		return self::_getRandom('snippet');
	}
	
	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_Snippet::getFields();
		$active_worker = CerberusApplication::getActiveWorker();
		
		switch($sortBy) {
			case SearchFields_Snippet::VIRTUAL_OWNER:
				$sortBy = SearchFields_Snippet::OWNER_CONTEXT;
				
				if(!in_array($sortBy, $columns))
					$columns[] = $sortBy;
				break;
		}
		
		list($tables, $wheres, $null) = parent::_parseSearchParams($params, $columns, $fields, $sortBy);
		
		$select_sql = sprintf("SELECT ".
			"snippet.id as %s, ".
			"snippet.title as %s, ".
			"snippet.context as %s, ".
			"snippet.owner_context as %s, ".
			"snippet.owner_context_id as %s, ".
			"snippet.content as %s, ".
			"snippet.total_uses as %s, ".
			"snippet.updated_at as %s, ".
			"snippet.custom_placeholders_json as %s",
				SearchFields_Snippet::ID,
				SearchFields_Snippet::TITLE,
				SearchFields_Snippet::CONTEXT,
				SearchFields_Snippet::OWNER_CONTEXT,
				SearchFields_Snippet::OWNER_CONTEXT_ID,
				SearchFields_Snippet::CONTENT,
				SearchFields_Snippet::TOTAL_USES,
				SearchFields_Snippet::UPDATED_AT,
				SearchFields_Snippet::CUSTOM_PLACEHOLDERS_JSON
			);
		
		if(isset($tables['snippet_use_history'])) {
			$select_sql .= sprintf(", (SELECT SUM(uses) FROM snippet_use_history WHERE worker_id=%d AND snippet_id=snippet.id) AS %s ", $active_worker->id, SearchFields_Snippet::USE_HISTORY_MINE);
		}
		
		$join_sql = " FROM snippet ".
			(isset($tables['context_link']) ? "INNER JOIN context_link ON (context_link.to_context = 'cerberusweb.contexts.snippet' AND context_link.to_context_id = snippet.id) " : " ")
		;
		
		// Custom field joins
		list($select_sql, $join_sql, $has_multiple_values) = self::_appendSelectJoinSqlForCustomFieldTables(
			$tables,
			$params,
			'snippet.id',
			$select_sql,
			$join_sql
		);
				
		$where_sql = ''.
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
		
		$sort_sql = self::_buildSortClause($sortBy, $sortAsc, $fields);
		
		$args = array(
			'join_sql' => &$join_sql,
			'where_sql' => &$where_sql,
			'tables' => &$tables,
			'has_multiple_values' => &$has_multiple_values,
		);
		
		// Virtuals
		array_walk_recursive(
			$params,
			array('DAO_Snippet', '_translateVirtualParameters'),
			$args
		);
		
		$result = array(
			'primary_table' => 'snippet',
			'select' => $select_sql,
			'join' => $join_sql,
			'where' => $where_sql,
			'has_multiple_values' => false,
			'sort' => $sort_sql,
		);
		
		return $result;
	}
	
	private static function _translateVirtualParameters($param, $key, &$args) {
		if(!is_a($param, 'DevblocksSearchCriteria'))
			return;
			
		$from_context = CerberusContexts::CONTEXT_SNIPPET;
		$from_index = 'snippet.id';
		
		$param_key = $param->field;
		settype($param_key, 'string');
		
		switch($param_key) {
			case SearchFields_Snippet::FULLTEXT_SNIPPET:
				$search = Extension_DevblocksSearchSchema::get(Search_Snippet::ID);
				$query = $search->getQueryFromParam($param);
				
				if(false === ($ids = $search->query($query, array()))) {
					$args['where_sql'] .= 'AND 0 ';
				
				} elseif(is_array($ids)) {
					if(empty($ids))
						$ids = array(-1);
					
					$args['where_sql'] .= sprintf('AND snippet.id IN (%s) ',
						implode(', ', $ids)
					);
					
				} elseif(is_string($ids)) {
					$args['join_sql'] .= sprintf("INNER JOIN %s ON (%s.id=snippet.id) ",
						$ids,
						$ids
					);
				}
				break;
			
			case SearchFields_Snippet::VIRTUAL_CONTEXT_LINK:
				$args['has_multiple_values'] = true;
				if(is_array($args) && isset($args['join_sql']) && isset($args['where_sql']))
					self::_searchComponentsVirtualContextLinks($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
				break;
				
			case SearchFields_Snippet::VIRTUAL_HAS_FIELDSET:
				if(is_array($args) && isset($args['join_sql']) && isset($args['where_sql']))
					self::_searchComponentsVirtualHasFieldset($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
				break;
			
			case SearchFields_Snippet::VIRTUAL_OWNER:
				if(!is_array($param->value))
					break;
				
				$wheres = array();
				$args['has_multiple_values'] = true;
					
				foreach($param->value as $owner_context) {
					@list($context, $context_id) = explode(':', $owner_context);
					
					if(empty($context))
						continue;
					
					if(!empty($context_id)) {
						$wheres[] = sprintf("(snippet.owner_context = %s AND snippet.owner_context_id = %d)",
							Cerb_ORMHelper::qstr($context),
							$context_id
						);
						
					} else {
						$wheres[] = sprintf("(snippet.owner_context = %s)",
							Cerb_ORMHelper::qstr($context)
						);
					}
					
				}
				
				if(!empty($wheres))
					$args['where_sql'] .= 'AND ' . implode(' OR ', $wheres);
				
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
			($has_multiple_values ? 'GROUP BY snippet.id ' : '').
			$sort_sql;
		
		// [TODO] Could push the select logic down a level too
		if($limit > 0) {
			$rs = $db->SelectLimit($sql,$limit,$page*$limit) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs mysqli_result */
		} else {
			$rs = $db->ExecuteSlave($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs mysqli_result */
			$total = mysqli_num_rows($rs);
		}
		
		$results = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object_id = intval($row[SearchFields_Snippet::ID]);
			$results[$object_id] = $row;
		}

		$total = count($results);
		
		if($withCounts) {
			// We can skip counting if we have a less-than-full single page
			if(!(0 == $page && $total < $limit)) {
				$count_sql =
					($has_multiple_values ? "SELECT COUNT(DISTINCT snippet.id) " : "SELECT COUNT(snippet.id) ").
					$join_sql.
					$where_sql;
				$total = $db->GetOneSlave($count_sql);
			}
		}
		
		mysqli_free_result($rs);
		
		return array($results,$total);
	}

};

class SearchFields_Snippet implements IDevblocksSearchFields {
	const ID = 's_id';
	const TITLE = 's_title';
	const CONTEXT = 's_context';
	const OWNER_CONTEXT = 's_owner_context';
	const OWNER_CONTEXT_ID = 's_owner_context_id';
	const CONTENT = 's_content';
	const TOTAL_USES = 's_total_uses';
	const UPDATED_AT = 's_updated_at';
	const CUSTOM_PLACEHOLDERS_JSON = 's_custom_placeholders_json';
	
	const USE_HISTORY_MINE = 'suh_my_uses';
	
	const CONTEXT_LINK = 'cl_context_from';
	const CONTEXT_LINK_ID = 'cl_context_from_id';
	
	// Fulltexts
	const FULLTEXT_SNIPPET = 'ft_snippet';
	
	// Virtuals
	const VIRTUAL_CONTEXT_LINK = '*_context_link';
	const VIRTUAL_HAS_FIELDSET = '*_has_fieldset';
	const VIRTUAL_OWNER = '*_owner';
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		$translate = DevblocksPlatform::getTranslationService();
		
		$columns = array(
			self::ID => new DevblocksSearchField(self::ID, 'snippet', 'id', $translate->_('common.id'), null, true),
			self::TITLE => new DevblocksSearchField(self::TITLE, 'snippet', 'title', $translate->_('common.title'), Model_CustomField::TYPE_SINGLE_LINE, true),
			self::CONTEXT => new DevblocksSearchField(self::CONTEXT, 'snippet', 'context', $translate->_('common.type'), null, true),
			self::OWNER_CONTEXT => new DevblocksSearchField(self::OWNER_CONTEXT, 'snippet', 'owner_context', $translate->_('dao.snippet.owner_context'), null, true),
			self::OWNER_CONTEXT_ID => new DevblocksSearchField(self::OWNER_CONTEXT_ID, 'snippet', 'owner_context_id', $translate->_('dao.snippet.owner_context_id'), null, true),
			self::CONTENT => new DevblocksSearchField(self::CONTENT, 'snippet', 'content', $translate->_('common.content'), Model_CustomField::TYPE_MULTI_LINE, true),
			self::TOTAL_USES => new DevblocksSearchField(self::TOTAL_USES, 'snippet', 'total_uses', $translate->_('dao.snippet.total_uses'), Model_CustomField::TYPE_NUMBER, true),
			self::UPDATED_AT => new DevblocksSearchField(self::UPDATED_AT, 'snippet', 'updated_at', $translate->_('common.updated'), Model_CustomField::TYPE_DATE, true),
			
			self::USE_HISTORY_MINE => new DevblocksSearchField(self::USE_HISTORY_MINE, 'snippet_use_history', 'uses', $translate->_('dao.snippet_use_history.uses.mine'), Model_CustomField::TYPE_NUMBER, true),
			
			self::CONTEXT_LINK => new DevblocksSearchField(self::CONTEXT_LINK, 'context_link', 'from_context', null, null, false),
			self::CONTEXT_LINK_ID => new DevblocksSearchField(self::CONTEXT_LINK_ID, 'context_link', 'from_context_id', null, null, false),
			
			self::FULLTEXT_SNIPPET => new DevblocksSearchField(self::FULLTEXT_SNIPPET, 'ft', 'snippet', $translate->_('common.search.fulltext'), 'FT', false),
				
			self::VIRTUAL_CONTEXT_LINK => new DevblocksSearchField(self::VIRTUAL_CONTEXT_LINK, '*', 'context_link', $translate->_('common.links'), null, false),
			self::VIRTUAL_HAS_FIELDSET => new DevblocksSearchField(self::VIRTUAL_HAS_FIELDSET, '*', 'has_fieldset', $translate->_('common.fieldset'), null, false),
			self::VIRTUAL_OWNER => new DevblocksSearchField(self::VIRTUAL_OWNER, '*', 'owner', $translate->_('common.owner')),
		);
		
		// Fulltext indexes
		
		$columns[self::FULLTEXT_SNIPPET]->ft_schema = Search_Snippet::ID;
		
		// Custom fields with fieldsets
		
		$custom_columns = DevblocksSearchField::getCustomSearchFieldsByContexts(array(
			CerberusContexts::CONTEXT_SNIPPET,
		));
		
		if(is_array($custom_columns))
			$columns = array_merge($columns, $custom_columns);
		
		// Sort by label (translation-conscious)
		DevblocksPlatform::sortObjects($columns, 'db_label');

		return $columns;
	}
};

class Search_Snippet extends Extension_DevblocksSearchSchema {
	const ID = 'cerb.search.schema.snippet';
	
	public function getNamespace() {
		return 'snippet';
	}
	
	public function getAttributes() {
		return array();
	}
	
	public function query($query, $attributes=array(), $limit=500) {
		if(false == ($engine = $this->getEngine()))
			return false;
		
		$ids = $engine->query($this, $query, $attributes, $limit);
		
		return $ids;
	}
	
	public function reindex() {
		$engine = $this->getEngine();
		$meta = $engine->getIndexMeta($this);
		
		// If the index has a delta, start from the current record
		if($meta['is_indexed_externally']) {
			// Do nothing (let the remote tool update the DB)
			
		// Otherwise, start over
		} else {
			$this->setIndexPointer(self::INDEX_POINTER_RESET);
		}
	}
	
	public function setIndexPointer($pointer) {
		switch($pointer) {
			case self::INDEX_POINTER_RESET:
				$this->setParam('last_indexed_id', 0);
				$this->setParam('last_indexed_time', 0);
				break;
				
			case self::INDEX_POINTER_CURRENT:
				$this->setParam('last_indexed_id', 0);
				$this->setParam('last_indexed_time', time());
				break;
		}
	}
	
	public function index($stop_time=null) {
		$logger = DevblocksPlatform::getConsoleLog();
		
		if(false == ($engine = $this->getEngine()))
			return false;
		
		$ns = self::getNamespace();
		$id = $this->getParam('last_indexed_id', 0);
		$ptr_time = $this->getParam('last_indexed_time', 0);
		$ptr_id = $id;
		$done = false;

		while(!$done && time() < $stop_time) {
			$where = sprintf('(%1$s = %2$d AND %3$s > %4$d) OR (%1$s > %2$d)',
				DAO_Snippet::UPDATED_AT,
				$ptr_time,
				DAO_Snippet::ID,
				$id
			);
			$snippets = DAO_Snippet::getWhere($where, array(DAO_Snippet::UPDATED_AT, DAO_Snippet::ID), array(true, true), 100);

			if(empty($snippets)) {
				$done = true;
				continue;
			}
			
			$last_time = $ptr_time;
			
			foreach($snippets as $snippet) { /* @var $snippet Model_Snippet */
				$id = $snippet->id;
				$ptr_time = $snippet->updated_at;
				
				$ptr_id = ($last_time == $ptr_time) ? $id : 0;
				
				$logger->info(sprintf("[Search] Indexing %s %d...",
					$ns,
					$id
				));
				
				$doc = array(
					'title' => $snippet->title,
					'content' => $snippet->content,						
				);
				
				if(false === ($engine->index($this, $id, $doc)))
					return false;
				
				flush();
			}
		}
		
		// If we ran out of records, always reset the ID and use the current time
		if($done) {
			$ptr_id = 0;
			$ptr_time = time();
		}
		
		$this->setParam('last_indexed_id', $ptr_id);
		$this->setParam('last_indexed_time', $ptr_time);
	}
	
	public function delete($ids) {
		if(false == ($engine = $this->getEngine()))
			return false;
		
		return $engine->delete($this, $ids);
	}
};

class Model_Snippet {
	public $id;
	public $title;
	public $context;
	public $owner_context;
	public $owner_context_id;
	public $content;
	public $total_uses;
	public $updated_at;
	public $custom_placeholders;
	
	public function incrementUse($worker_id) {
		return DAO_Snippet::incrementUse($this->id, $worker_id);
	}

	function isReadableByWorker($worker) {
		if(is_a($worker, 'Model_Worker')) {
			// This is what we want
		} elseif (is_numeric($worker)) {
			if(null == ($worker = DAO_Worker::get($worker)))
				return false;
		} else {
			return false;
		}
		
		// Superusers can do anything
		if($worker->is_superuser)
			return true;
		
		switch($this->owner_context) {
			case CerberusContexts::CONTEXT_APPLICATION:
				if($worker->is_superuser)
					return true;
				break;
				
			case CerberusContexts::CONTEXT_GROUP:
				if(in_array($this->owner_context_id, array_keys($worker->getMemberships())))
					return true;
				break;
				
			case CerberusContexts::CONTEXT_ROLE:
				if(in_array($this->owner_context_id, array_keys($worker->getRoles())))
					return true;
				break;
				
			case CerberusContexts::CONTEXT_WORKER:
				if($worker->id == $this->owner_context_id)
					return true;
				break;
		}
		
		return false;
	}
	
	function isWriteableByWorker($worker) {
		if(is_a($worker, 'Model_Worker')) {
			// This is what we want
		} elseif (is_numeric($worker)) {
			if(null == ($worker = DAO_Worker::get($worker)))
				return false;
		} else {
			return false;
		}
		
		// Superusers can do anything
		if($worker->is_superuser)
			return true;
		
		switch($this->owner_context) {
			case CerberusContexts::CONTEXT_APPLICATION:
			case CerberusContexts::CONTEXT_ROLE:
				if($worker->is_superuser)
					return true;
				break;
				
			case CerberusContexts::CONTEXT_GROUP:
				if(in_array($this->owner_context_id, array_keys($worker->getMemberships())))
					if($worker->isGroupManager($this->owner_context_id))
						return true;
				break;
				
			case CerberusContexts::CONTEXT_WORKER:
				if($worker->id == $this->owner_context_id)
					return true;
				break;
		}
		
		return false;
	}
	
};

class View_Snippet extends C4_AbstractView implements IAbstractView_Subtotals, IAbstractView_QuickSearch {
	const DEFAULT_ID = 'snippet';

	function __construct() {
		$translate = DevblocksPlatform::getTranslationService();
	
		$this->id = self::DEFAULT_ID;
		$this->name = $translate->_('Snippet');
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_Snippet::ID;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_Snippet::TITLE,
			SearchFields_Snippet::CONTEXT,
			SearchFields_Snippet::VIRTUAL_OWNER,
			SearchFields_Snippet::USE_HISTORY_MINE,
			SearchFields_Snippet::TOTAL_USES,
			SearchFields_Snippet::UPDATED_AT,
		);
		
		$this->addColumnsHidden(array(
			SearchFields_Snippet::CONTEXT_LINK,
			SearchFields_Snippet::CONTEXT_LINK_ID,
			SearchFields_Snippet::ID,
			SearchFields_Snippet::CONTENT,
			SearchFields_Snippet::OWNER_CONTEXT,
			SearchFields_Snippet::OWNER_CONTEXT_ID,
			SearchFields_Snippet::FULLTEXT_SNIPPET,
			SearchFields_Snippet::VIRTUAL_CONTEXT_LINK,
			SearchFields_Snippet::VIRTUAL_HAS_FIELDSET,
		));
		
		$this->addParamsHidden(array(
			SearchFields_Snippet::CONTEXT_LINK,
			SearchFields_Snippet::CONTEXT_LINK_ID,
			SearchFields_Snippet::ID,
			SearchFields_Snippet::OWNER_CONTEXT,
			SearchFields_Snippet::OWNER_CONTEXT_ID,
			SearchFields_Snippet::USE_HISTORY_MINE,
		));
		
		$this->doResetCriteria();
	}

	function getData() {
		$objects = DAO_Snippet::search(
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
		return $this->_getDataAsObjects('DAO_Snippet', $ids);
	}
	
	function getSubtotalFields() {
		$all_fields = $this->getParamsAvailable(true);
		
		$fields = array();

		if(is_array($all_fields))
		foreach($all_fields as $field_key => $field_model) {
			$pass = false;
			
			switch($field_key) {
				case SearchFields_Snippet::CONTEXT:
					$pass = true;
					break;
					
				case SearchFields_Snippet::VIRTUAL_CONTEXT_LINK:
				case SearchFields_Snippet::VIRTUAL_HAS_FIELDSET:
				case SearchFields_Snippet::VIRTUAL_OWNER:
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
			case SearchFields_Snippet::VIRTUAL_CONTEXT_LINK:
				$counts = $this->_getSubtotalCountForContextLinkColumn('DAO_Snippet', CerberusContexts::CONTEXT_SNIPPET, $column);
				break;
			
			case SearchFields_Snippet::CONTEXT:
				$label_map = array(
					'' => 'Plaintext'
				);
				$contexts = Extension_DevblocksContext::getAll(false);
				
				foreach($contexts as $k => $mft) {
					$label_map[$k] = $mft->name;
				}
				
				$counts = $this->_getSubtotalCountForStringColumn('DAO_Snippet', $column, $label_map, 'in', 'contexts[]');
				break;
				
			case SearchFields_Snippet::VIRTUAL_HAS_FIELDSET:
				$counts = $this->_getSubtotalCountForHasFieldsetColumn('DAO_Snippet', CerberusContexts::CONTEXT_SNIPPET, $column);
				break;
			
			case SearchFields_Snippet::VIRTUAL_OWNER:
				$counts = $this->_getSubtotalCountForContextAndIdColumns('DAO_Snippet', CerberusContexts::CONTEXT_SNIPPET, $column, DAO_Snippet::OWNER_CONTEXT, DAO_Snippet::OWNER_CONTEXT_ID, 'owner_context[]');
				break;
			
			default:
				// Custom fields
				if('cf_' == substr($column,0,3)) {
					$counts = $this->_getSubtotalCountForCustomColumn('DAO_Snippet', $column, 's.id');
				}
				
				break;
		}
		
		return $counts;
	}
	
	// [TODO] My uses? owner?
	
	function getQuickSearchFields() {
		$search_fields = SearchFields_Snippet::getFields();
		
		$fields = array(
			'_fulltext' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_FULLTEXT,
					'options' => array('param_key' => SearchFields_Snippet::FULLTEXT_SNIPPET),
				),
			'content' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_Snippet::CONTENT, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'title' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_Snippet::TITLE, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'totalUses' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_NUMBER,
					'options' => array('param_key' => SearchFields_Snippet::TITLE),
				),
			'type' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_VIRTUAL,
					'options' => array('param_key' => SearchFields_Snippet::CONTEXT),
					'examples' => array(
						'plaintext',
						'ticket',
						'plaintext,ticket',
					),
				),
			'updated' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_Snippet::UPDATED_AT),
				),
		);
		
		// Add searchable custom fields
		
		$fields = self::_appendFieldsFromQuickSearchContext(CerberusContexts::CONTEXT_SNIPPET, $fields, null);
		
		// Engine/schema examples: Fulltext
		
		$ft_examples = array();
		
		if(false != ($schema = Extension_DevblocksSearchSchema::get(Search_Snippet::ID))) {
			if(false != ($engine = $schema->getEngine())) {
				$ft_examples = $engine->getQuickSearchExamples($schema);
			}
		}
		
		if(!empty($ft_examples))
			$fields['_fulltext']['examples'] = $ft_examples;
		
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
				case 'type':
					$field_keys = array(
						'type' => SearchFields_Snippet::CONTEXT,
					);
					
					@$field_key = $field_keys[$k];
					
					$oper = DevblocksSearchCriteria::OPER_IN;
					
					$patterns = DevblocksPlatform::parseCsvString($v);
					$contexts = Extension_DevblocksContext::getAll(false);
					$values = array();
					
					if(is_array($patterns))
					foreach($patterns as $pattern) {
						if(in_array($pattern, array('plain', 'plaintext'))) {
							$values[''] = true;
							continue;
						}
						
						foreach($contexts as $context_id => $context) {
							if(false !== stripos($context->name, $pattern))
								$values[$context_id] = true;
						}
					}
					
					$param = new DevblocksSearchCriteria(
						$field_key,
						$oper,
						array_keys($values)
					);
					$params[$field_key] = $param;
					break;
					
			}
		}
		
		return $params;
	}
	
	function render() {
		$this->_sanitize();
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

		$contexts = Extension_DevblocksContext::getAll(false);
		$tpl->assign('contexts', $contexts);
		
		$placeholder_values = $this->getPlaceholderValues();
		
		// Are we translating snippet previews for certain contexts?
		if(isset($placeholder_values['dicts'])) {
			$tpl->assign('dicts', $placeholder_values['dicts']);

			$tpl_builder = DevblocksPlatform::getTemplateBuilder();
			$tpl->assign('tpl_builder', $tpl_builder);
		}
		
		switch($this->renderTemplate) {
			case 'contextlinks_chooser':
			default:
				$tpl->assign('view_template', 'devblocks:cerberusweb.core::internal/snippets/views/default.tpl');
				$tpl->display('devblocks:cerberusweb.core::internal/views/subtotals_and_view.tpl');
				break;
		}
		
	}

	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

		switch($field) {
			case SearchFields_Snippet::ID:
			case SearchFields_Snippet::TITLE:
			case SearchFields_Snippet::CONTENT:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
				
			case SearchFields_Snippet::TOTAL_USES:
			case SearchFields_Snippet::USE_HISTORY_MINE:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__number.tpl');
				break;
				
			case SearchFields_Snippet::UPDATED_AT:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__date.tpl');
				break;
				
			case SearchFields_Snippet::CONTEXT:
				$contexts = Extension_DevblocksContext::getAll(false);
				
				// [TODO] [HACK!] Fake plaintext
				$plain = new stdClass();
				$plain->id = '';
				$plain->name = 'Plaintext';
				$contexts = array_merge(array(''=>$plain), $contexts);
				$tpl->assign('contexts', $contexts);
				
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context.tpl');
				break;
				
			case SearchFields_Snippet::FULLTEXT_SNIPPET:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__fulltext.tpl');
				break;
				
			case SearchFields_Snippet::VIRTUAL_CONTEXT_LINK:
				$contexts = Extension_DevblocksContext::getAll(false);
				$tpl->assign('contexts', $contexts);
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_link.tpl');
				break;

			case SearchFields_Snippet::VIRTUAL_HAS_FIELDSET:
				$this->_renderCriteriaHasFieldset($tpl, CerberusContexts::CONTEXT_SNIPPET);
				break;
				
			case SearchFields_Snippet::VIRTUAL_OWNER:
				$groups = DAO_Group::getAll();
				$tpl->assign('groups', $groups);
				
				$roles = DAO_WorkerRole::getAll();
				$tpl->assign('roles', $roles);
				
				$workers = DAO_Worker::getAll();
				$tpl->assign('workers', $workers);
				
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_owner.tpl');
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
	
	function renderVirtualCriteria($param) {
		$key = $param->field;
		
		$translate = DevblocksPlatform::getTranslationService();
		
		switch($key) {
			case SearchFields_Snippet::VIRTUAL_CONTEXT_LINK:
				$this->_renderVirtualContextLinks($param);
				break;
				
			case SearchFields_Snippet::VIRTUAL_HAS_FIELDSET:
				$this->_renderVirtualHasFieldset($param);
				break;
			
			case SearchFields_Snippet::VIRTUAL_OWNER:
				$this->_renderVirtualContextLinks($param, 'Owner', 'Owners');
				break;
		}
	}

	function renderCriteriaParam($param) {
		$field = $param->field;
		$values = !is_array($param->value) ? array($param->value) : $param->value;

		switch($field) {
			case SearchFields_Snippet::CONTEXT:
				$contexts = Extension_DevblocksContext::getAll(false);
				$strings = array();
				
				foreach($param->value as $context_id) {
					if(empty($context_id)) {
						$strings[] = '<b>Plaintext</b>';
					} elseif(isset($contexts[$context_id])) {
						$strings[] = '<b>'.DevblocksPlatform::strEscapeHtml($contexts[$context_id]->name).'</b>';
					}
				}
				
				echo implode(' or ', $strings);
				break;
				
			default:
				parent::renderCriteriaParam($param);
				break;
		}
	}

	function getFields() {
		return SearchFields_Snippet::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		switch($field) {
			case SearchFields_Snippet::ID:
			case SearchFields_Snippet::TITLE:
			case SearchFields_Snippet::CONTENT:
				$criteria = $this->_doSetCriteriaString($field, $oper, $value);
				break;
				
			case SearchFields_Snippet::TOTAL_USES:
			case SearchFields_Snippet::USE_HISTORY_MINE:
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;
				
			case SearchFields_Snippet::UPDATED_AT:
				$criteria = $this->_doSetCriteriaDate($field, $oper);
				break;
				
			case SearchFields_Snippet::CONTEXT:
				@$in_contexts = DevblocksPlatform::importGPC($_REQUEST['contexts'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$in_contexts);
				break;
				
			case SearchFields_Snippet::FULLTEXT_SNIPPET:
				@$scope = DevblocksPlatform::importGPC($_REQUEST['scope'],'string','expert');
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_FULLTEXT,array($value,$scope));
				break;
				
			case SearchFields_Snippet::VIRTUAL_CONTEXT_LINK:
				@$context_links = DevblocksPlatform::importGPC($_REQUEST['context_link'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$context_links);
				break;
				
			case SearchFields_Snippet::VIRTUAL_HAS_FIELDSET:
				@$options = DevblocksPlatform::importGPC($_REQUEST['options'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$options);
				break;
				
			case SearchFields_Snippet::VIRTUAL_OWNER:
				@$owner_contexts = DevblocksPlatform::importGPC($_REQUEST['owner_context'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,$oper,$owner_contexts);
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
		$custom_fields = array(
			DAO_Snippet::UPDATED_AT => time(),
		);

		// Make sure we have actions
		if(empty($do))
			return;

		// Make sure we have checked items if we want a checked list
		if(0 == strcasecmp($filter,"checks") && empty($ids))
			return;
			
		if(is_array($do))
		foreach($do as $k => $v) {
			switch($k) {
				case 'owner':
					@list($context, $context_id) = explode(':', $v);
					
					if(empty($context))
						break;
					
					$change_fields[DAO_Snippet::OWNER_CONTEXT] = $context;
					$change_fields[DAO_Snippet::OWNER_CONTEXT_ID] = $context_id;
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
			list($objects,$null) = DAO_Snippet::search(
				array(),
				$this->getParams(),
				100,
				$pg++,
				SearchFields_Snippet::ID,
				true,
				false
			);
			$ids = array_merge($ids, array_keys($objects));
			 
		} while(!empty($objects));

		$batch_total = count($ids);
		for($x=0;$x<=$batch_total;$x+=100) {
			$batch_ids = array_slice($ids,$x,100);
			
			if(!empty($change_fields))
				DAO_Snippet::update($batch_ids, $change_fields);

			// Custom Fields
			self::_doBulkSetCustomFields(CerberusContexts::CONTEXT_SNIPPET, $custom_fields, $batch_ids);
			
			unset($batch_ids);
		}

		unset($ids);
	}
};

class Context_Snippet extends Extension_DevblocksContext implements IDevblocksContextAutocomplete {
	function getRandom() {
		return DAO_Snippet::random();
	}
	
	function getMeta($context_id) {
		$snippet = DAO_Snippet::get($context_id);
		$url_writer = DevblocksPlatform::getUrlService();
		
		return array(
			'id' => $context_id,
			'name' => $snippet->title,
			'permalink' => '', //$url_writer->writeNoProxy('c=tasks&action=display&id='.$task->id, true),
			'updated' => $snippet->updated_at,
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
	
	// [TODO] Interface
	function getDefaultProperties() {
		return array(
			'owner__label',
			'context',
			'total_uses',
			'updated_at',
		);
	}
	
	function autocomplete($term) {
		$as_worker = CerberusApplication::getActiveWorker();
		
		$list = array();
		
		$contexts = DevblocksPlatform::getExtensions('devblocks.context', false);
		
		$worker_groups = $as_worker->getMemberships();
		$worker_roles = $as_worker->getRoles();
		
		// Restrict owners
		$param_ownership = array(
			DevblocksSearchCriteria::GROUP_OR,
			SearchFields_Snippet::OWNER_CONTEXT => new DevblocksSearchCriteria(SearchFields_Snippet::OWNER_CONTEXT,DevblocksSearchCriteria::OPER_EQ,CerberusContexts::CONTEXT_APPLICATION),
			array(
				DevblocksSearchCriteria::GROUP_AND,
				SearchFields_Snippet::OWNER_CONTEXT => new DevblocksSearchCriteria(SearchFields_Snippet::OWNER_CONTEXT,DevblocksSearchCriteria::OPER_EQ,CerberusContexts::CONTEXT_WORKER),
				SearchFields_Snippet::OWNER_CONTEXT_ID => new DevblocksSearchCriteria(SearchFields_Snippet::OWNER_CONTEXT_ID,DevblocksSearchCriteria::OPER_EQ,$as_worker->id),
			),
			array(
				DevblocksSearchCriteria::GROUP_AND,
				SearchFields_Snippet::OWNER_CONTEXT => new DevblocksSearchCriteria(SearchFields_Snippet::OWNER_CONTEXT,DevblocksSearchCriteria::OPER_EQ,CerberusContexts::CONTEXT_GROUP),
				SearchFields_Snippet::OWNER_CONTEXT_ID => new DevblocksSearchCriteria(SearchFields_Snippet::OWNER_CONTEXT_ID,DevblocksSearchCriteria::OPER_IN,array_keys($worker_groups)),
			),
			array(
				DevblocksSearchCriteria::GROUP_AND,
				SearchFields_Snippet::OWNER_CONTEXT => new DevblocksSearchCriteria(SearchFields_Snippet::OWNER_CONTEXT,DevblocksSearchCriteria::OPER_EQ,CerberusContexts::CONTEXT_ROLE),
				SearchFields_Snippet::OWNER_CONTEXT_ID => new DevblocksSearchCriteria(SearchFields_Snippet::OWNER_CONTEXT_ID,DevblocksSearchCriteria::OPER_IN,array_keys($worker_roles)),
			),
		);
		
		$params = array(
			new DevblocksSearchCriteria(SearchFields_Snippet::TITLE,DevblocksSearchCriteria::OPER_LIKE,'%'.$term.'%'),
			$param_ownership,
		);
		
		// [TODO] This needs to be abstracted properly
		@$context_list = DevblocksPlatform::importGPC($_REQUEST['contexts'],'array',array());
		if(is_array($context_list))
		foreach($context_list as $k => $v) {
			if(!isset($contexts[$v]))
				unset($context_list[$k]);
		}

		$context_list[] = ''; // plaintext
		
		// Filter contexts
		$params[SearchFields_Snippet::CONTEXT] =
			new DevblocksSearchCriteria(SearchFields_Snippet::CONTEXT,DevblocksSearchCriteria::OPER_IN,$context_list)
			;
		
		list($results, $null) = DAO_Snippet::search(
			array(
				SearchFields_Snippet::TITLE,
				SearchFields_Snippet::USE_HISTORY_MINE,
			),
			$params,
			25,
			0,
			SearchFields_Snippet::USE_HISTORY_MINE,
			false,
			false
		);

		foreach($results AS $row){
			$entry = new stdClass();
			$entry->label = sprintf("%s -- used %s",
				$row[SearchFields_Snippet::TITLE],
				((1 != $row[SearchFields_Snippet::USE_HISTORY_MINE]) ? (intval($row[SearchFields_Snippet::USE_HISTORY_MINE]) . ' times') : 'once')
			);
			$entry->value = $row[SearchFields_Snippet::ID];
			$entry->context = $row[SearchFields_Snippet::CONTEXT];
			$list[] = $entry;
		}
		
		return $list;
	}
	
	function getContext($snippet, &$token_labels, &$token_values, $prefix=null) {
		if(is_null($prefix))
			$prefix = 'Snippet:';
		
		$translate = DevblocksPlatform::getTranslationService();
		$fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_SNIPPET);

		// Polymorph
		if(is_numeric($snippet)) {
			$snippet = DAO_Snippet::get($snippet);
		} elseif($snippet instanceof Model_Snippet) {
			// It's what we want already.
		} elseif(is_array($snippet)) {
			$snippet = Cerb_ORMHelper::recastArrayToModel($snippet, 'Model_Snippet');
		} else {
			$snippet = null;
		}
		
		// Token labels
		$token_labels = array(
			'_label' => $prefix,
			'id' => $prefix.$translate->_('common.id'),
			'title' => $prefix.$translate->_('common.title'),
			'context' => $prefix.$translate->_('common.context'),
			'content' => $prefix.$translate->_('common.content'),
			'owner__label' => $prefix.$translate->_('common.owner'),
			'total_uses' => $prefix.$translate->_('dao.snippet.total_uses'),
			'updated_at' => $prefix.$translate->_('common.updated'),
		);
		
		// Token types
		$token_types = array(
			'_label' => 'context_url',
			'id' => Model_CustomField::TYPE_NUMBER,
			'title' => Model_CustomField::TYPE_SINGLE_LINE,
			'context' => Model_CustomField::TYPE_SINGLE_LINE,
			'content' => Model_CustomField::TYPE_MULTI_LINE,
			'owner__label' => 'context_url',
			'total_uses' => Model_CustomField::TYPE_NUMBER,
			'updated_at' => Model_CustomField::TYPE_DATE,
		);
		
		// Custom field/fieldset token labels
		if(false !== ($custom_field_labels = $this->_getTokenLabelsFromCustomFields($fields, $prefix)) && is_array($custom_field_labels))
			$token_labels = array_merge($token_labels, $custom_field_labels);
		
		// Custom field/fieldset token types
		if(false !== ($custom_field_types = $this->_getTokenTypesFromCustomFields($fields, $prefix)) && is_array($custom_field_types))
			$token_types = array_merge($token_types, $custom_field_types);
		
		// Token values
		$token_values = array();
		
		$token_values['_context'] = CerberusContexts::CONTEXT_SNIPPET;
		$token_values['_types'] = $token_types;
		
		if($snippet) {
			$token_values['_loaded'] = true;
			$token_values['_label'] = $snippet->title;
			$token_values['content'] = $snippet->content;
			$token_values['context'] = $snippet->context;
			$token_values['id'] = $snippet->id;
			$token_values['owner__context'] = $snippet->owner_context;
			$token_values['owner_id'] = $snippet->owner_context_id;
			$token_values['title'] = $snippet->title;
			$token_values['total_uses'] = $snippet->total_uses;
			$token_values['updated_at'] = $snippet->updated_at;
			
			// Custom fields
			$token_values = $this->_importModelCustomFieldsAsValues($snippet, $token_values);
		}

		return true;
	}

	function lazyLoadContextValues($token, $dictionary) {
		if(!isset($dictionary['id']))
			return;
		
		$context = CerberusContexts::CONTEXT_SNIPPET;
		$context_id = $dictionary['id'];
		
		@$is_loaded = $dictionary['_loaded'];
		$values = array();
		
		if(!$is_loaded) {
			$labels = array();
			CerberusContexts::getContext($context, $context_id, $labels, $values, null, true);
		}
		
		switch($token) {
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
		$view->name = 'Snippets';
		
		$params_required = array();
		
		$worker_group_ids = array_keys($active_worker->getMemberships());
		$worker_role_ids = array_keys(DAO_WorkerRole::getRolesByWorker($active_worker->id));
		
		// Restrict owners
		$param_ownership = array(
			DevblocksSearchCriteria::GROUP_OR,
			SearchFields_Snippet::OWNER_CONTEXT => new DevblocksSearchCriteria(SearchFields_Snippet::OWNER_CONTEXT,DevblocksSearchCriteria::OPER_EQ,CerberusContexts::CONTEXT_APPLICATION),
			array(
				DevblocksSearchCriteria::GROUP_AND,
				SearchFields_Snippet::OWNER_CONTEXT => new DevblocksSearchCriteria(SearchFields_Snippet::OWNER_CONTEXT,DevblocksSearchCriteria::OPER_EQ,CerberusContexts::CONTEXT_WORKER),
				SearchFields_Snippet::OWNER_CONTEXT_ID => new DevblocksSearchCriteria(SearchFields_Snippet::OWNER_CONTEXT_ID,DevblocksSearchCriteria::OPER_EQ,$active_worker->id),
			),
			array(
				DevblocksSearchCriteria::GROUP_AND,
				SearchFields_Snippet::OWNER_CONTEXT => new DevblocksSearchCriteria(SearchFields_Snippet::OWNER_CONTEXT,DevblocksSearchCriteria::OPER_EQ,CerberusContexts::CONTEXT_GROUP),
				SearchFields_Snippet::OWNER_CONTEXT_ID => new DevblocksSearchCriteria(SearchFields_Snippet::OWNER_CONTEXT_ID,DevblocksSearchCriteria::OPER_IN,$worker_group_ids),
			),
			array(
				DevblocksSearchCriteria::GROUP_AND,
				SearchFields_Snippet::OWNER_CONTEXT => new DevblocksSearchCriteria(SearchFields_Snippet::OWNER_CONTEXT,DevblocksSearchCriteria::OPER_EQ,CerberusContexts::CONTEXT_ROLE),
				SearchFields_Snippet::OWNER_CONTEXT_ID => new DevblocksSearchCriteria(SearchFields_Snippet::OWNER_CONTEXT_ID,DevblocksSearchCriteria::OPER_IN,$worker_role_ids),
			),
		);
		$params_required['_ownership'] = $param_ownership;
		
		$view->addParamsRequired($params_required, true);
		
		$view->renderSortBy = SearchFields_Snippet::USE_HISTORY_MINE;
		$view->renderSortAsc = false;
		$view->renderLimit = 10;
		$view->renderTemplate = 'contextlinks_chooser';
		$view->renderFilters = false;
		return $view;
	}
	
	function getView($context=null, $context_id=null, $options=array(), $view_id=null) {
		$view_id = !empty($view_id) ? $view_id : str_replace('.','_',$this->id);
		
		$defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass());
		$defaults->id = $view_id;

		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->name = 'Snippets';

		$params_req = array();
		
		if(!empty($context) && !empty($context_id)) {
			$params_req = array(
				new DevblocksSearchCriteria(SearchFields_Snippet::CONTEXT_LINK,'=',$context),
				new DevblocksSearchCriteria(SearchFields_Snippet::CONTEXT_LINK_ID,'=',$context_id),
			);
		}
		
		$view->addParamsRequired($params_req, true);
		
		$view->renderTemplate = 'context';
		return $view;
	}
};
