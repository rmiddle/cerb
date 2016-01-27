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
/*
 * IMPORTANT LICENSING NOTE from your friends on the Cerb Development Team
 *
 * Sure, it would be so easy to just cheat and edit this file to use the
 * software without paying for it.  But we trust you anyway.  In fact, we're
 * writing this software for you!
 *
 * Quality software backed by a dedicated team takes money to develop.  We
 * don't want to be out of the office bagging groceries when you call up
 * needing a helping hand.  We'd rather spend our free time coding your
 * feature requests than mowing the neighbors' lawns for rent money.
 *
 * We've never believed in hiding our source code out of paranoia over not
 * getting paid.  We want you to have the full source code and be able to
 * make the tweaks your organization requires to get more done -- despite
 * having less of everything than you might need (time, people, money,
 * energy).  We shouldn't be your bottleneck.
 *
 * We've been building our expertise with this project since January 2002.  We
 * promise spending a couple bucks [Euro, Yuan, Rupees, Galactic Credits] to
 * let us take over your shared e-mail headache is a worthwhile investment.
 * It will give you a sense of control over your inbox that you probably
 * haven't had since spammers found you in a game of 'E-mail Battleship'.
 * Miss. Miss. You sunk my inbox!
 *
 * A legitimate license entitles you to support from the developers,
 * and the warm fuzzy feeling of feeding a couple of obsessed developers
 * who want to help you get more done.
 *
 \* - Jeff Standen, Darren Sugita, Dan Hildebrandt
 *	 Webgroup Media LLC - Developers of Cerb
 */

class DAO_DevblocksSession extends Cerb_ORMHelper {
	const SESSION_KEY = 'session_key';
	const CREATED = 'created';
	const UPDATED = 'updated';
	const SESSION_DATA = 'session_data';
	const USER_ID = 'user_id';
	const USER_IP = 'user_ip';
	const USER_AGENT = 'user_agent';

	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = "INSERT INTO devblocks_session () VALUES ()";
		$db->ExecuteMaster($sql);
		$id = $db->LastInsertId();
		
		self::update($id, $fields);
		
		return $id;
	}
	
	static function update($ids, $fields) {
		if(!is_array($ids))
			$ids = array($ids);
		
		// Make a diff for the requested objects in batches
		
		$chunks = array_chunk($ids, 100, true);
		while($batch_ids = array_shift($chunks)) {
			if(empty($batch_ids))
				continue;
			
			// Make changes
			parent::_update($batch_ids, 'devblocks_session', $fields);
		}
	}
	
	static function updateWhere($fields, $where) {
		parent::_updateWhere('devblocks_session', $fields, $where);
	}
	
	/**
	 * @param string $where
	 * @param mixed $sortBy
	 * @param mixed $sortAsc
	 * @param integer $limit
	 * @return Model_DevblocksSession[]
	 */
	static function getWhere($where=null, $sortBy=null, $sortAsc=true, $limit=null) {
		$db = DevblocksPlatform::getDatabaseService();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		// SQL
		$sql = "SELECT session_key, created, updated, session_data, user_id, user_ip, user_agent ".
			"FROM devblocks_session ".
			$where_sql.
			$sort_sql.
			$limit_sql
		;
		$rs = $db->ExecuteSlave($sql);
		
		return self::_getObjectsFromResult($rs);
	}

	/**
	 * @param integer $id
	 * @return Model_DevblocksSession	 */
	static function get($key) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$objects = self::getWhere(sprintf("%s = %s",
			self::SESSION_KEY,
			$db->qstr($key)
		));
		
		if(isset($objects[$key]))
			return $objects[$key];
		
		return null;
	}
	
	/**
	 * @param resource $rs
	 * @return Model_DevblocksSession[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object = new Model_DevblocksSession();
			$object->session_key = $row['session_key'];
			$object->created = $row['created'];
			$object->updated = $row['updated'];
			$object->session_data = $row['session_data'];
			$object->user_id = $row['user_id'];
			$object->user_ip = $row['user_ip'];
			$object->user_agent = $row['user_agent'];
			$objects[$object->session_key] = $object;
		}
		
		mysqli_free_result($rs);
		
		return $objects;
	}
	
	static function delete($ids) {
		if(!is_array($ids))
			$ids = array($ids);
		
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($ids))
			return;

		DevblocksPlatform::sanitizeArray($ids, 'integer');
		
		array_walk($ids, function(&$id) use ($db) {
			$id = $db->qstr($id);
		});
		
		$ids_list = implode(',', $ids);
		
		$db->ExecuteMaster(sprintf("DELETE FROM devblocks_session WHERE session_key IN (%s)", $ids_list));
		
		return true;
	}
	
	static function deleteByUserIds($ids) {
		if(!is_array($ids))
			$ids = array($ids);
		
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($ids))
			return;
		
		DevblocksPlatform::sanitizeArray($ids, 'integer');
		
		$ids_list = implode(',', $ids);
		
		$db->ExecuteMaster(sprintf("DELETE FROM devblocks_session WHERE user_id IN (%s)", $ids_list));
		
		return true;
	}
	
	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_DevblocksSession::getFields();
		
		list($tables,$wheres) = parent::_parseSearchParams($params, $columns, $fields, $sortBy);
		
		$select_sql = sprintf("SELECT ".
			"devblocks_session.session_key as %s, ".
			"devblocks_session.created as %s, ".
			"devblocks_session.updated as %s, ".
			"devblocks_session.session_data as %s, ".
			"devblocks_session.user_id as %s, ".
			"devblocks_session.user_ip as %s, ".
			"devblocks_session.user_agent as %s ",
				SearchFields_DevblocksSession::SESSION_KEY,
				SearchFields_DevblocksSession::CREATED,
				SearchFields_DevblocksSession::UPDATED,
				SearchFields_DevblocksSession::SESSION_DATA,
				SearchFields_DevblocksSession::USER_ID,
				SearchFields_DevblocksSession::USER_IP,
				SearchFields_DevblocksSession::USER_AGENT
			);
			
		$join_sql = "FROM devblocks_session ";
		
		$has_multiple_values = false;
				
		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = self::_buildSortClause($sortBy, $sortAsc, $fields);
	
		// Virtuals
		
		$args = array(
			'join_sql' => &$join_sql,
			'where_sql' => &$where_sql,
			'tables' => &$tables,
			'has_multiple_values' => &$has_multiple_values
		);
	
		array_walk_recursive(
			$params,
			array('DAO_DevblocksSession', '_translateVirtualParameters'),
			$args
		);
		
		return array(
			'primary_table' => 'devblocks_session',
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
			
		$param_key = $param->field;
		settype($param_key, 'string');
		
		switch($param_key) {
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
			($has_multiple_values ? 'GROUP BY devblocks_session.id ' : '').
			$sort_sql;
			
		if($limit > 0) {
			$rs = $db->SelectLimit($sql,$limit,$page*$limit) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs mysqli_result */
		} else {
			$rs = $db->ExecuteSlave($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs mysqli_result */
			$total = mysqli_num_rows($rs);
		}
		
		$results = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object_id = $row[SearchFields_DevblocksSession::SESSION_KEY];
			$results[$object_id] = $row;
		}

		$total = count($results);
		
		if($withCounts) {
			// We can skip counting if we have a less-than-full single page
			if(!(0 == $page && $total < $limit)) {
				$count_sql =
					($has_multiple_values ? "SELECT COUNT(DISTINCT devblocks_session.session_key) " : "SELECT COUNT(devblocks_session.session_key) ").
					$join_sql.
					$where_sql;
				$total = $db->GetOneSlave($count_sql);
			}
		}
		
		mysqli_free_result($rs);
		
		return array($results,$total);
	}

};

class Model_DevblocksSession {
	public $session_key;
	public $created;
	public $updated;
	public $session_data;
	public $user_id;
	public $user_ip;
	public $user_agent;
};

class SearchFields_DevblocksSession implements IDevblocksSearchFields {
	const SESSION_KEY = 'd_session_key';
	const CREATED = 'd_created';
	const UPDATED = 'd_updated';
	const SESSION_DATA = 'd_session_data';
	const USER_ID = 'd_user_id';
	const USER_IP = 'd_user_ip';
	const USER_AGENT = 'd_user_agent';
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		$translate = DevblocksPlatform::getTranslationService();
		
		$columns = array(
			self::SESSION_KEY => new DevblocksSearchField(self::SESSION_KEY, 'devblocks_session', 'session_key', $translate->_('dao.devblocks_session.session_key'), Model_CustomField::TYPE_SINGLE_LINE, true),
			self::CREATED => new DevblocksSearchField(self::CREATED, 'devblocks_session', 'created', $translate->_('common.created'), Model_CustomField::TYPE_DATE, true),
			self::UPDATED => new DevblocksSearchField(self::UPDATED, 'devblocks_session', 'updated', $translate->_('common.updated'), Model_CustomField::TYPE_DATE, true),
			self::SESSION_DATA => new DevblocksSearchField(self::SESSION_DATA, 'devblocks_session', 'session_data', $translate->_('dao.devblocks_session.session_data'), Model_CustomField::TYPE_MULTI_LINE, true),
			self::USER_ID => new DevblocksSearchField(self::USER_ID, 'devblocks_session', 'user_id', $translate->_('common.worker'), Model_CustomField::TYPE_WORKER, true),
			self::USER_IP => new DevblocksSearchField(self::USER_IP, 'devblocks_session', 'user_ip', $translate->_('dao.devblocks_session.user_ip'), Model_CustomField::TYPE_SINGLE_LINE, true),
			self::USER_AGENT => new DevblocksSearchField(self::USER_AGENT, 'devblocks_session', 'user_agent', $translate->_('dao.devblocks_session.user_agent'), Model_CustomField::TYPE_SINGLE_LINE, true),
		);
		
		// Sort by label (translation-conscious)
		DevblocksPlatform::sortObjects($columns, 'db_label');

		return $columns;
	}
};

class View_DevblocksSession extends C4_AbstractView implements IAbstractView_Subtotals, IAbstractView_QuickSearch {
	const DEFAULT_ID = 'devblocks_sessions';

	function __construct() {
		$translate = DevblocksPlatform::getTranslationService();
	
		$this->id = self::DEFAULT_ID;
		$this->name = $translate->_('Sessions');
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_DevblocksSession::UPDATED;
		$this->renderSortAsc = false;

		$this->view_columns = array(
			SearchFields_DevblocksSession::USER_ID,
			SearchFields_DevblocksSession::CREATED,
			SearchFields_DevblocksSession::UPDATED,
			SearchFields_DevblocksSession::USER_IP,
			SearchFields_DevblocksSession::USER_AGENT,
		);

		$this->addColumnsHidden(array(
			SearchFields_DevblocksSession::SESSION_KEY,
			SearchFields_DevblocksSession::SESSION_DATA,
		));
		
		$this->addParamsHidden(array(
			SearchFields_DevblocksSession::SESSION_KEY,
			SearchFields_DevblocksSession::SESSION_DATA,
		));
		
		$this->doResetCriteria();
	}

	function getData() {
		$objects = DAO_DevblocksSession::search(
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
		return $this->_getDataAsObjects('DAO_DevblocksSession', $ids);
	}
	
	function getDataSample($size) {
		return $this->_doGetDataSample('DAO_DevblocksSession', $size);
	}

	function getSubtotalFields() {
		$all_fields = $this->getParamsAvailable(true);
		
		$fields = array();

		if(is_array($all_fields))
		foreach($all_fields as $field_key => $field_model) {
			$pass = false;
			
			switch($field_key) {
				// Fields
				case SearchFields_DevblocksSession::USER_ID:
					$pass = true;
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
			case SearchFields_DevblocksSession::USER_ID:
				$label_map = array();
				
				$workers = DAO_Worker::getAll();
				foreach($workers as $worker_id => $worker) {
					$label_map[$worker_id] = $worker->getName();
				}
				
				$counts = $this->_getSubtotalCountForStringColumn('DAO_DevblocksSession', $column, $label_map, 'in', 'worker_id[]');
				break;
		}
		
		return $counts;
	}
	
	function getQuickSearchFields() {
		$search_fields = SearchFields_DevblocksSession::getFields();
		
		$fields = array(
			'_fulltext' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_DevblocksSession::USER_AGENT, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'created' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_DevblocksSession::CREATED),
				),
			'ip' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_DevblocksSession::USER_IP, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'updated' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_DevblocksSession::UPDATED),
				),
			'userAgent' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_DevblocksSession::USER_AGENT, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'worker' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_WORKER,
					'options' => array('param_key' => SearchFields_DevblocksSession::USER_ID),
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
		
		$this->renderPage = 0;
		$this->addParams($params, true);
		
		return $params;
	}
	
	function render() {
		$this->_sanitize();
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

		$workers = DAO_Worker::getAll();
		$tpl->assign('workers', $workers);
		
		$tpl->assign('view_template', 'devblocks:cerberusweb.core::configuration/section/sessions/view.tpl');
		$tpl->display('devblocks:cerberusweb.core::internal/views/subtotals_and_view.tpl');
	}

	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);

		switch($field) {
			case SearchFields_DevblocksSession::SESSION_KEY:
			case SearchFields_DevblocksSession::USER_IP:
			case SearchFields_DevblocksSession::USER_AGENT:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
				
			case 'placeholder_number':
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__number.tpl');
				break;
				
			case 'placeholder_bool':
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__bool.tpl');
				break;
				
			case SearchFields_DevblocksSession::CREATED:
			case SearchFields_DevblocksSession::UPDATED:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__date.tpl');
				break;
				
			case SearchFields_DevblocksSession::USER_ID:
				$tpl->assign('workers', DAO_Worker::getAllActive());
				$tpl->assign('param_name', 'worker_id');
				$tpl->display('devblocks:cerberusweb.core::internal/views/helpers/_shared_placeholder_worker_picker.tpl');
				break;
		}
	}

	function renderCriteriaParam($param) {
		$field = $param->field;
		$values = !is_array($param->value) ? array($param->value) : $param->value;

		switch($field) {
			case SearchFields_DevblocksSession::USER_ID:
				parent::_renderCriteriaParamWorker($param);
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
		}
	}

	function getFields() {
		return SearchFields_DevblocksSession::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		switch($field) {
			case SearchFields_DevblocksSession::SESSION_KEY:
			case SearchFields_DevblocksSession::USER_IP:
			case SearchFields_DevblocksSession::USER_AGENT:
				$criteria = $this->_doSetCriteriaString($field, $oper, $value);
				break;
				
			case 'placeholder_number':
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;
				
			case SearchFields_DevblocksSession::CREATED:
			case SearchFields_DevblocksSession::UPDATED:
				$criteria = $this->_doSetCriteriaDate($field, $oper);
				break;
				
			case 'placeholder_bool':
				@$bool = DevblocksPlatform::importGPC($_REQUEST['bool'],'integer',1);
				$criteria = new DevblocksSearchCriteria($field,$oper,$bool);
				break;
				
			case SearchFields_DevblocksSession::USER_ID:
				$criteria = $this->_doSetCriteriaWorker($field, $oper);
		}

		if(!empty($criteria)) {
			$this->addParam($criteria, $field);
			$this->renderPage = 0;
		}
	}
		
	function doBulkUpdate($filter, $do, $ids=array()) {
		@set_time_limit(600); // 10m
	
		$change_fields = array();
		$is_deleted = false;

		// Make sure we have actions
		if(empty($do))
			return;

		// Make sure we have checked items if we want a checked list
		if(0 == strcasecmp($filter,"checks") && empty($ids))
			return;
		
		if(is_array($do))
		foreach($do as $k => $v) {
			switch($k) {
				case 'deleted':
					$is_deleted = true;
					break;
			}
		}

		$pg = 0;

		if(empty($ids))
		do {
			list($objects, $null) = DAO_DevblocksSession::search(
				array(),
				$this->getParams(),
				100,
				$pg++,
				SearchFields_DevblocksSession::SESSION_KEY,
				true,
				false
			);
			$ids = array_merge($ids, array_keys($objects));
			 
		} while(!empty($objects));

		$batch_total = count($ids);
		for($x=0;$x<=$batch_total;$x+=100) {
			$batch_ids = array_slice($ids,$x,100);

			if($is_deleted) {
				DAO_DevblocksSession::delete($batch_ids);
				
			} else {
				if(!empty($change_fields))
					DAO_DevblocksSession::update($batch_ids, $change_fields);
			}

			unset($batch_ids);
		}

		unset($ids);
	}
};