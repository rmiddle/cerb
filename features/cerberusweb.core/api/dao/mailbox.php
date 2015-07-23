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

class DAO_Mailbox extends Cerb_ORMHelper {
	const _CACHE_ALL = 'dao_mailboxes_all';
	
	const ID = 'id';
	const ENABLED = 'enabled';
	const NAME = 'name';
	const PROTOCOL = 'protocol';
	const HOST = 'host';
	const USERNAME = 'username';
	const PASSWORD = 'password';
	const PORT = 'port';
	const NUM_FAILS = 'num_fails';
	const DELAY_UNTIL = 'delay_until';
	const TIMEOUT_SECS = 'timeout_secs';
	const MAX_MSG_SIZE_KB = 'max_msg_size_kb';
	const SSL_IGNORE_VALIDATION = 'ssl_ignore_validation';
	const UPDATED_AT = 'updated_at';
	
	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = "INSERT INTO mailbox () VALUES ()";
		$db->ExecuteMaster($sql);
		$id = $db->LastInsertId();
		
		self::update($id, $fields);
		
		return $id;
	}
	
	static function update($ids, $fields, $check_deltas=true) {
		if(!is_array($ids))
			$ids = array($ids);
		
		if(!isset($fields[self::UPDATED_AT]))
			$fields[self::UPDATED_AT] = time();
		
		// Make a diff for the requested objects in batches
		
		$chunks = array_chunk($ids, 100, true);
		while($batch_ids = array_shift($chunks)) {
			if(empty($batch_ids))
				continue;
				
			// Send events
			if($check_deltas) {
				CerberusContexts::checkpointChanges(CerberusContexts::CONTEXT_MAILBOX, $batch_ids);
			}
			
			// Make changes
			parent::_update($batch_ids, 'mailbox', $fields);
			
			// Send events
			if($check_deltas) {
				// Trigger an event about the changes
				$eventMgr = DevblocksPlatform::getEventService();
				$eventMgr->trigger(
					new Model_DevblocksEvent(
						'dao.mailbox.update',
						array(
							'fields' => $fields,
						)
					)
				);
				
				// Log the context update
				DevblocksPlatform::markContextChanged(CerberusContexts::CONTEXT_MAILBOX, $batch_ids);
			}
		}
	}
	
	static function updateWhere($fields, $where) {
		parent::_updateWhere('mailbox', $fields, $where);
	}
	
	/**
	 * @param string $where
	 * @param mixed $sortBy
	 * @param mixed $sortAsc
	 * @param integer $limit
	 * @return Model_Mailbox[]
	 */
	static function getWhere($where=null, $sortBy=null, $sortAsc=true, $limit=null, $options=null) {
		$db = DevblocksPlatform::getDatabaseService();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		// SQL
		$sql = "SELECT id, enabled, name, protocol, host, username, password, port, num_fails, delay_until, timeout_secs, max_msg_size_kb, ssl_ignore_validation, updated_at ".
			"FROM mailbox ".
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
	 * @return Model_Mailbox[]
	 */
	static function getAll($nocache=false) {
		//$cache = DevblocksPlatform::getCacheService();
		//if($nocache || null === ($objects = $cache->load(self::CACHE_ALL))) {
			$objects = self::getWhere(null, DAO_Mailbox::NAME, true, null, Cerb_ORMHelper::OPT_GET_MASTER_ONLY);
			//$cache->save($buckets, self::CACHE_ALL);
		//}
		
		return $objects;
	}

	/**
	 * @param integer $id
	 * @return Model_Mailbox
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
	 * 
	 * @param array $ids
	 * @return Model_Mailbox[]
	 */
	static function getIds($ids) {
		if(!is_array($ids))
			$ids = array($ids);

		if(empty($ids))
			return array();

		if(!method_exists(get_called_class(), 'getWhere'))
			return array();

		$db = DevblocksPlatform::getDatabaseService();

		$ids = DevblocksPlatform::importVar($ids, 'array:integer');

		$models = array();

		$results = static::getWhere(sprintf("id IN (%s)",
			implode(',', $ids)
		));

		// Sort $models in the same order as $ids
		foreach($ids as $id) {
			if(isset($results[$id]))
				$models[$id] = $results[$id];
		}

		unset($results);

		return $models;
	}	
	
	/**
	 * @param resource $rs
	 * @return Model_Mailbox[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object = new Model_Mailbox();
			$object->id = intval($row['id']);
			$object->enabled = $row['enabled'] ? 1 : 0;
			$object->name = $row['name'];
			$object->protocol = $row['protocol'];
			$object->host = $row['host'];
			$object->username = $row['username'];
			$object->password = $row['password'];
			$object->port = intval($row['port']);
			$object->num_fails = intval($row['num_fails']);
			$object->delay_until = intval($row['delay_until']);
			$object->timeout_secs = intval($row['timeout_secs']);
			$object->max_msg_size_kb = intval($row['max_msg_size_kb']);
			$object->ssl_ignore_validation = $row['ssl_ignore_validation'] ? 1 : 0;
			$object->updated_at = intval($row['updated_at']);
			$objects[$object->id] = $object;
		}
		
		mysqli_free_result($rs);
		
		return $objects;
	}
	
	static function random() {
		return self::_getRandom('mailbox');
	}
	
	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		$db->ExecuteMaster(sprintf("DELETE FROM mailbox WHERE id IN (%s)", $ids_list));
		
		// Fire event
		$eventMgr = DevblocksPlatform::getEventService();
		$eventMgr->trigger(
			new Model_DevblocksEvent(
				'context.delete',
				array(
					'context' => CerberusContexts::CONTEXT_MAILBOX,
					'context_ids' => $ids
				)
			)
		);
		
		return true;
	}
	
	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_Mailbox::getFields();
		
		// Sanitize
		if('*'==substr($sortBy,0,1) || !isset($fields[$sortBy]))
			$sortBy=null;

		list($tables,$wheres) = parent::_parseSearchParams($params, $columns, $fields, $sortBy);
		
		$select_sql = sprintf("SELECT ".
			"mailbox.id as %s, ".
			"mailbox.enabled as %s, ".
			"mailbox.name as %s, ".
			"mailbox.protocol as %s, ".
			"mailbox.host as %s, ".
			"mailbox.username as %s, ".
			"mailbox.password as %s, ".
			"mailbox.port as %s, ".
			"mailbox.num_fails as %s, ".
			"mailbox.delay_until as %s, ".
			"mailbox.timeout_secs as %s, ".
			"mailbox.max_msg_size_kb as %s, ".
			"mailbox.ssl_ignore_validation as %s, ".
			"mailbox.updated_at as %s ",
				SearchFields_Mailbox::ID,
				SearchFields_Mailbox::ENABLED,
				SearchFields_Mailbox::NAME,
				SearchFields_Mailbox::PROTOCOL,
				SearchFields_Mailbox::HOST,
				SearchFields_Mailbox::USERNAME,
				SearchFields_Mailbox::PASSWORD,
				SearchFields_Mailbox::PORT,
				SearchFields_Mailbox::NUM_FAILS,
				SearchFields_Mailbox::DELAY_UNTIL,
				SearchFields_Mailbox::TIMEOUT_SECS,
				SearchFields_Mailbox::MAX_MSG_SIZE_KB,
				SearchFields_Mailbox::SSL_IGNORE_VALIDATION,
				SearchFields_Mailbox::UPDATED_AT
			);
			
		$join_sql = "FROM mailbox ".
			(isset($tables['context_link']) ? sprintf("INNER JOIN context_link ON (context_link.to_context = %s AND context_link.to_context_id = mailbox.id) ", Cerb_ORMHelper::qstr(CerberusContexts::CONTEXT_MAILBOX)) : " ").
			'';
		
		// Custom field joins
		list($select_sql, $join_sql, $has_multiple_values) = self::_appendSelectJoinSqlForCustomFieldTables(
			$tables,
			$params,
			'mailbox.id',
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
			array('DAO_Mailbox', '_translateVirtualParameters'),
			$args
		);
		
		return array(
			'primary_table' => 'mailbox',
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
			
		$from_context = CerberusContexts::CONTEXT_MAILBOX;
		$from_index = 'mailbox.id';
		
		$param_key = $param->field;
		settype($param_key, 'string');
		
		switch($param_key) {
			case SearchFields_Mailbox::VIRTUAL_CONTEXT_LINK:
				$args['has_multiple_values'] = true;
				self::_searchComponentsVirtualContextLinks($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
				break;
		
			case SearchFields_Mailbox::VIRTUAL_HAS_FIELDSET:
				self::_searchComponentsVirtualHasFieldset($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
				break;
		
			case SearchFields_Mailbox::VIRTUAL_WATCHERS:
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
			($has_multiple_values ? 'GROUP BY mailbox.id ' : '').
			$sort_sql;
			
		if($limit > 0) {
			$rs = $db->SelectLimit($sql,$limit,$page*$limit) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs mysqli_result */
		} else {
			$rs = $db->ExecuteSlave($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs mysqli_result */
			$total = mysqli_num_rows($rs);
		}
		
		$results = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object_id = intval($row[SearchFields_Mailbox::ID]);
			$results[$object_id] = $row;
		}

		$total = count($results);
		
		if($withCounts) {
			// We can skip counting if we have a less-than-full single page
			if(!(0 == $page && $total < $limit)) {
				$count_sql =
					($has_multiple_values ? "SELECT COUNT(DISTINCT mailbox.id) " : "SELECT COUNT(mailbox.id) ").
					$join_sql.
					$where_sql;
				$total = $db->GetOneSlave($count_sql);
			}
		}
		
		mysqli_free_result($rs);
		
		return array($results,$total);
	}
	
};

class Model_Mailbox {
	public $id;
	public $enabled=1;
	public $name;
	public $protocol='pop3';
	public $host;
	public $username;
	public $password;
	public $port=110;
	public $num_fails = 0;
	public $delay_until = 0;
	public $timeout_secs = 30;
	public $max_msg_size_kb = 25600;
	public $ssl_ignore_validation = 0;
	public $updated_at = 0;
	
	function getImapConnectString() {
		$connect = null;
		
		switch($this->protocol) {
			default:
			case 'pop3': // 110
				$connect = sprintf("{%s:%d/pop3/notls}INBOX",
					$this->host,
					$this->port
				);
				break;
				 
			case 'pop3-ssl': // 995
				$connect = sprintf("{%s:%d/pop3/ssl%s}INBOX",
					$this->host,
					$this->port,
					$this->ssl_ignore_validation ? '/novalidate-cert' : ''
				);
				break;
				 
			case 'imap': // 143
				$connect = sprintf("{%s:%d/notls}INBOX",
					$this->host,
					$this->port
				);
				break;
	
			case 'imap-ssl': // 993
				$connect = sprintf("{%s:%d/imap/ssl%s}INBOX",
					$this->host,
					$this->port,
					$this->ssl_ignore_validation ? '/novalidate-cert' : ''
				);
				break;
		}
		
		return $connect;
	}
};

class SearchFields_Mailbox implements IDevblocksSearchFields {
	const ID = 'p_id';
	const ENABLED = 'p_enabled';
	const NAME = 'p_name';
	const PROTOCOL = 'p_protocol';
	const HOST = 'p_host';
	const USERNAME = 'p_username';
	const PASSWORD = 'p_password';
	const PORT = 'p_port';
	const NUM_FAILS = 'p_num_fails';
	const DELAY_UNTIL = 'p_delay_until';
	const TIMEOUT_SECS = 'p_timeout_secs';
	const MAX_MSG_SIZE_KB = 'p_max_msg_size_kb';
	const SSL_IGNORE_VALIDATION = 'p_ssl_ignore_validation';
	const UPDATED_AT = 'p_updated_at';

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
			self::ID => new DevblocksSearchField(self::ID, 'mailbox', 'id', $translate->_('common.id')),
			self::ENABLED => new DevblocksSearchField(self::ENABLED, 'mailbox', 'enabled', $translate->_('common.enabled')),
			self::NAME => new DevblocksSearchField(self::NAME, 'mailbox', 'name', $translate->_('common.name')),
			self::PROTOCOL => new DevblocksSearchField(self::PROTOCOL, 'mailbox', 'protocol', $translate->_('dao.mailbox.protocol')),
			self::HOST => new DevblocksSearchField(self::HOST, 'mailbox', 'host', $translate->_('common.host')),
			self::USERNAME => new DevblocksSearchField(self::USERNAME, 'mailbox', 'username', $translate->_('common.user')),
			self::PASSWORD => new DevblocksSearchField(self::PASSWORD, 'mailbox', 'password', $translate->_('common.password')),
			self::PORT => new DevblocksSearchField(self::PORT, 'mailbox', 'port', $translate->_('dao.mailbox.port')),
			self::NUM_FAILS => new DevblocksSearchField(self::NUM_FAILS, 'mailbox', 'num_fails', $translate->_('dao.mailbox.num_fails')),
			self::DELAY_UNTIL => new DevblocksSearchField(self::DELAY_UNTIL, 'mailbox', 'delay_until', $translate->_('dao.mailbox.delay_until')),
			self::TIMEOUT_SECS => new DevblocksSearchField(self::TIMEOUT_SECS, 'mailbox', 'timeout_secs', $translate->_('dao.mailbox.timeout_secs')),
			self::MAX_MSG_SIZE_KB => new DevblocksSearchField(self::MAX_MSG_SIZE_KB, 'mailbox', 'max_msg_size_kb', $translate->_('dao.mailbox.max_msg_size_kb')),
			self::SSL_IGNORE_VALIDATION => new DevblocksSearchField(self::SSL_IGNORE_VALIDATION, 'mailbox', 'ssl_ignore_validation', $translate->_('dao.mailbox.ssl_ignore_validation')),
			self::UPDATED_AT => new DevblocksSearchField(self::UPDATED_AT, 'mailbox', 'updated_at', $translate->_('common.updated')),

			self::VIRTUAL_CONTEXT_LINK => new DevblocksSearchField(self::VIRTUAL_CONTEXT_LINK, '*', 'context_link', $translate->_('common.links'), null),
			self::VIRTUAL_HAS_FIELDSET => new DevblocksSearchField(self::VIRTUAL_HAS_FIELDSET, '*', 'has_fieldset', $translate->_('common.fieldset'), null),
			self::VIRTUAL_WATCHERS => new DevblocksSearchField(self::VIRTUAL_WATCHERS, '*', 'workers', $translate->_('common.watchers'), 'WS'),
			
			self::CONTEXT_LINK => new DevblocksSearchField(self::CONTEXT_LINK, 'context_link', 'from_context', null),
			self::CONTEXT_LINK_ID => new DevblocksSearchField(self::CONTEXT_LINK_ID, 'context_link', 'from_context_id', null),
		);
		
		// Custom Fields
		$custom_columns = DevblocksSearchField::getCustomSearchFieldsByContexts(array(
			CerberusContexts::CONTEXT_MAILBOX,
		));
		
		if(!empty($custom_columns))
			$columns = array_merge($columns, $custom_columns);

		// Sort by label (translation-conscious)
		DevblocksPlatform::sortObjects($columns, 'db_label');

		return $columns;
	}
};

class View_Mailbox extends C4_AbstractView implements IAbstractView_Subtotals { //IAbstractView_QuickSearch
	const DEFAULT_ID = 'mailboxes';

	function __construct() {
		$translate = DevblocksPlatform::getTranslationService();
	
		$this->id = self::DEFAULT_ID;
		$this->name = mb_convert_case($translate->_('common.mailbox'), MB_CASE_TITLE);
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_Mailbox::NAME;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_Mailbox::NAME,
			SearchFields_Mailbox::PROTOCOL,
			SearchFields_Mailbox::HOST,
			SearchFields_Mailbox::USERNAME,
			SearchFields_Mailbox::PORT,
			SearchFields_Mailbox::NUM_FAILS,
			SearchFields_Mailbox::TIMEOUT_SECS,
			SearchFields_Mailbox::MAX_MSG_SIZE_KB,
			SearchFields_Mailbox::UPDATED_AT,
		);

		$this->addColumnsHidden(array(
			SearchFields_Mailbox::PASSWORD,
			SearchFields_Mailbox::VIRTUAL_CONTEXT_LINK,
			SearchFields_Mailbox::VIRTUAL_HAS_FIELDSET,
			SearchFields_Mailbox::VIRTUAL_WATCHERS,
		));
		
		$this->addParamsHidden(array(
			SearchFields_Mailbox::PASSWORD,
		));
		
		$this->doResetCriteria();
	}

	function getData() {
		$objects = DAO_Mailbox::search(
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
		return $this->_getDataAsObjects('DAO_Mailbox', $ids);
	}
	
	function getDataSample($size) {
		return $this->_doGetDataSample('DAO_Mailbox', $size);
	}

	function getSubtotalFields() {
		$all_fields = $this->getParamsAvailable(true);
		
		$fields = array();

		if(is_array($all_fields))
		foreach($all_fields as $field_key => $field_model) {
			$pass = false;
			
			switch($field_key) {
				// Fields
//				case SearchFields_Mailbox::EXAMPLE:
//					$pass = true;
//					break;
					
				// Virtuals
				case SearchFields_Mailbox::VIRTUAL_CONTEXT_LINK:
				case SearchFields_Mailbox::VIRTUAL_HAS_FIELDSET:
				case SearchFields_Mailbox::VIRTUAL_WATCHERS:
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
//			case SearchFields_Mailbox::EXAMPLE_BOOL:
//				$counts = $this->_getSubtotalCountForBooleanColumn('DAO_Mailbox', $column);
//				break;

//			case SearchFields_Mailbox::EXAMPLE_STRING:
//				$counts = $this->_getSubtotalCountForStringColumn('DAO_Mailbox', $column);
//				break;
				
			case SearchFields_Mailbox::VIRTUAL_CONTEXT_LINK:
				$counts = $this->_getSubtotalCountForContextLinkColumn('DAO_Mailbox', CerberusContexts::CONTEXT_MAILBOX, $column);
				break;

			case SearchFields_Mailbox::VIRTUAL_HAS_FIELDSET:
				$counts = $this->_getSubtotalCountForHasFieldsetColumn('DAO_Mailbox', CerberusContexts::CONTEXT_MAILBOX, $column);
				break;
				
			case SearchFields_Mailbox::VIRTUAL_WATCHERS:
				$counts = $this->_getSubtotalCountForWatcherColumn('DAO_Mailbox', $column);
				break;
			
			default:
				// Custom fields
				if('cf_' == substr($column,0,3)) {
					$counts = $this->_getSubtotalCountForCustomColumn('DAO_Mailbox', $column, 'mailbox.id');
				}
				
				break;
		}
		
		return $counts;
	}
	
	/*
	function getQuickSearchFields() {
		$fields = array(
			'_fulltext' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_Mailbox::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'name' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_Mailbox::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'updated' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_Mailbox::UPDATED_AT),
				),
			'watchers' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_WORKER,
					'options' => array('param_key' => SearchFields_Mailbox::VIRTUAL_WATCHERS),
				),
		);
		
		// Add searchable custom fields
		
		$fields = self::_appendFieldsFromQuickSearchContext(CerberusContexts::CONTEXT_MAILBOX, $fields, null);
		
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
	*/
	
	function render() {
		$this->_sanitize();
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

		// Custom fields
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_MAILBOX);
		$tpl->assign('custom_fields', $custom_fields);

		$tpl->assign('view_template', 'devblocks:cerberusweb.core::internal/mailbox/view.tpl');
		$tpl->display('devblocks:cerberusweb.core::internal/views/subtotals_and_view.tpl');
	}

	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);

		switch($field) {
			case SearchFields_Mailbox::NAME:
			case SearchFields_Mailbox::PROTOCOL:
			case SearchFields_Mailbox::HOST:
			case SearchFields_Mailbox::USERNAME:
			case SearchFields_Mailbox::PASSWORD:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
				
			case SearchFields_Mailbox::ID:
			case SearchFields_Mailbox::PORT:
			case SearchFields_Mailbox::NUM_FAILS:
			case SearchFields_Mailbox::TIMEOUT_SECS:
			case SearchFields_Mailbox::MAX_MSG_SIZE_KB:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__number.tpl');
				break;
				
			case SearchFields_Mailbox::ENABLED:
			case SearchFields_Mailbox::SSL_IGNORE_VALIDATION:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__bool.tpl');
				break;
				
			case SearchFields_Mailbox::DELAY_UNTIL:
			case SearchFields_Mailbox::UPDATED_AT:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__date.tpl');
				break;
				
			case SearchFields_Mailbox::VIRTUAL_CONTEXT_LINK:
				$contexts = Extension_DevblocksContext::getAll(false);
				$tpl->assign('contexts', $contexts);
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_link.tpl');
				break;
				
			case SearchFields_Mailbox::VIRTUAL_HAS_FIELDSET:
				$this->_renderCriteriaHasFieldset($tpl, CerberusContexts::CONTEXT_MAILBOX);
				break;
				
			case SearchFields_Mailbox::VIRTUAL_WATCHERS:
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
			case SearchFields_Mailbox::ENABLED:
			case SearchFields_Mailbox::SSL_IGNORE_VALIDATION:
				parent::_renderCriteriaParamBoolean($param);
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
			case SearchFields_Mailbox::VIRTUAL_CONTEXT_LINK:
				$this->_renderVirtualContextLinks($param);
				break;
				
			case SearchFields_Mailbox::VIRTUAL_HAS_FIELDSET:
				$this->_renderVirtualHasFieldset($param);
				break;
			
			case SearchFields_Mailbox::VIRTUAL_WATCHERS:
				$this->_renderVirtualWatchers($param);
				break;
		}
	}

	function getFields() {
		return SearchFields_Mailbox::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		switch($field) {
			case SearchFields_Mailbox::NAME:
			case SearchFields_Mailbox::PROTOCOL:
			case SearchFields_Mailbox::HOST:
			case SearchFields_Mailbox::USERNAME:
			case SearchFields_Mailbox::PASSWORD:
				$criteria = $this->_doSetCriteriaString($field, $oper, $value);
				break;
				
			case SearchFields_Mailbox::ID:
			case SearchFields_Mailbox::NUM_FAILS:
			case SearchFields_Mailbox::PORT:
			case SearchFields_Mailbox::TIMEOUT_SECS:
			case SearchFields_Mailbox::MAX_MSG_SIZE_KB:
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;
				
			case SearchFields_Mailbox::DELAY_UNTIL:
			case SearchFields_Mailbox::UPDATED_AT:
				$criteria = $this->_doSetCriteriaDate($field, $oper);
				break;
				
			case SearchFields_Mailbox::ENABLED:
			case SearchFields_Mailbox::SSL_IGNORE_VALIDATION:
				@$bool = DevblocksPlatform::importGPC($_REQUEST['bool'],'integer',1);
				$criteria = new DevblocksSearchCriteria($field,$oper,$bool);
				break;
				
			case SearchFields_Mailbox::VIRTUAL_CONTEXT_LINK:
				@$context_links = DevblocksPlatform::importGPC($_REQUEST['context_link'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$context_links);
				break;
				
			case SearchFields_Mailbox::VIRTUAL_HAS_FIELDSET:
				@$options = DevblocksPlatform::importGPC($_REQUEST['options'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$options);
				break;
				
			case SearchFields_Mailbox::VIRTUAL_WATCHERS:
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
					//$change_fields[DAO_Mailbox::EXAMPLE] = 'some value';
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
			list($objects,$null) = DAO_Mailbox::search(
				array(),
				$this->getParams(),
				100,
				$pg++,
				SearchFields_Mailbox::ID,
				true,
				false
			);
			$ids = array_merge($ids, array_keys($objects));
			 
		} while(!empty($objects));

		$batch_total = count($ids);
		for($x=0;$x<=$batch_total;$x+=100) {
			$batch_ids = array_slice($ids,$x,100);
			
			if(!empty($change_fields)) {
				DAO_Mailbox::update($batch_ids, $change_fields);
			}

			// Custom Fields
			self::_doBulkSetCustomFields(CerberusContexts::CONTEXT_MAILBOX, $custom_fields, $batch_ids);
			
			unset($batch_ids);
		}

		unset($ids);
	}
};

class Context_Mailbox extends Extension_DevblocksContext implements IDevblocksContextProfile, IDevblocksContextPeek { // IDevblocksContextImport
	function getRandom() {
		return DAO_Mailbox::random();
	}
	
	function profileGetUrl($context_id) {
		if(empty($context_id))
			return '';
	
		$url_writer = DevblocksPlatform::getUrlService();
		$url = $url_writer->writeNoProxy('c=profiles&type=mailbox&id='.$context_id, true);
		return $url;
	}
	
	function getMeta($context_id) {
		$mailbox = DAO_Mailbox::get($context_id);
		$url_writer = DevblocksPlatform::getUrlService();
		
		$url = $this->profileGetUrl($context_id);
		$friendly = DevblocksPlatform::strToPermalink($mailbox->name);
		
		if(!empty($friendly))
			$url .= '-' . $friendly;
		
		return array(
			'id' => $mailbox->id,
			'name' => $mailbox->name,
			'permalink' => $url,
		);
	}
	
	// [TODO] Interface
	function getDefaultProperties() {
		return array(
			'updated_at',
		);
	}
	
	function getContext($mailbox, &$token_labels, &$token_values, $prefix=null) {
		if(is_null($prefix))
			$prefix = 'Mailbox:';
		
		$translate = DevblocksPlatform::getTranslationService();
		$fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_MAILBOX);

		// Polymorph
		if(is_numeric($mailbox)) {
			$mailbox = DAO_Mailbox::get($mailbox);
		} elseif($mailbox instanceof Model_Mailbox) {
			// It's what we want already.
		} elseif(is_array($mailbox)) {
			$mailbox = Cerb_ORMHelper::recastArrayToModel($mailbox, 'Model_Mailbox');
		} else {
			$mailbox = null;
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
		
		$token_values['_context'] = CerberusContexts::CONTEXT_MAILBOX;
		$token_values['_types'] = $token_types;
		
		if($mailbox) {
			$token_values['_loaded'] = true;
			$token_values['_label'] = $mailbox->name;
			$token_values['id'] = $mailbox->id;
			$token_values['name'] = $mailbox->name;
			$token_values['updated_at'] = $mailbox->updated_at;
			
			// Custom fields
			$token_values = $this->_importModelCustomFieldsAsValues($mailbox, $token_values);
			
			// URL
			$url_writer = DevblocksPlatform::getUrlService();
			$token_values['record_url'] = $url_writer->writeNoProxy(sprintf("c=profiles&type=mailbox&id=%d-%s",$mailbox->id, DevblocksPlatform::strToPermalink($mailbox->name)), true);
		}
		
		return true;
	}

	function lazyLoadContextValues($token, $dictionary) {
		if(!isset($dictionary['id']))
			return;
		
		$context = CerberusContexts::CONTEXT_MAILBOX;
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
		$view->name = 'Mailbox';
		/*
		$view->addParams(array(
			SearchFields_Mailbox::UPDATED_AT => new DevblocksSearchCriteria(SearchFields_Mailbox::UPDATED_AT,'=',0),
		), true);
		*/
		$view->renderSortBy = SearchFields_Mailbox::UPDATED_AT;
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
		$view->name = 'Mailbox';
		
		$params_req = array();
		
		if(!empty($context) && !empty($context_id)) {
			$params_req = array(
				new DevblocksSearchCriteria(SearchFields_Mailbox::CONTEXT_LINK,'=',$context),
				new DevblocksSearchCriteria(SearchFields_Mailbox::CONTEXT_LINK_ID,'=',$context_id),
			);
		}
		
		$view->addParamsRequired($params_req, true);
		
		$view->renderTemplate = 'context';
		return $view;
	}
	
	function renderPeekPopup($context_id=0, $view_id='') {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('view_id', $view_id);
		
		if(!empty($context_id) && null != ($mailbox = DAO_Mailbox::get($context_id))) {
			$tpl->assign('model', $mailbox);
		}
		
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_MAILBOX, false);
		$tpl->assign('custom_fields', $custom_fields);
		
		if(!empty($context_id)) {
			$custom_field_values = DAO_CustomFieldValue::getValuesByContextIds(CerberusContexts::CONTEXT_MAILBOX, $context_id);
			if(isset($custom_field_values[$context_id]))
				$tpl->assign('custom_field_values', $custom_field_values[$context_id]);
		}

		// Comments
		$comments = DAO_Comment::getByContext(CerberusContexts::CONTEXT_MAILBOX, $context_id);
		$comments = array_reverse($comments, true);
		$tpl->assign('comments', $comments);
		
		$tpl->display('devblocks:cerberusweb.core::internal/mailbox/peek.tpl');
	}
	
};
