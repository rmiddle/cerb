<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2014, Webgroup Media LLC
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

class DAO_MailHtmlTemplate extends Cerb_ORMHelper {
	const _CACHE_ALL = 'cerb_cache_mail_html_template_all';
	
	const ID = 'id';
	const NAME = 'name';
	const UPDATED_AT = 'updated_at';
	const OWNER_CONTEXT = 'owner_context';
	const OWNER_CONTEXT_ID = 'owner_context_id';
	const CONTENT = 'content';
	const SIGNATURE = 'signature';

	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = "INSERT INTO mail_html_template () VALUES ()";
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
			parent::_update($batch_ids, 'mail_html_template', $fields);
		}
		
		self::clearCache();
	}
	
	static function updateWhere($fields, $where) {
		parent::_updateWhere('mail_html_template', $fields, $where);
		self::clearCache();
	}
	
	/**
	 * @param string $where
	 * @param mixed $sortBy
	 * @param mixed $sortAsc
	 * @param integer $limit
	 * @return Model_MailHtmlTemplate[]
	 */
	static function getWhere($where=null, $sortBy=DAO_MailHtmlTemplate::NAME, $sortAsc=true, $limit=null, $options=null) {
		$db = DevblocksPlatform::getDatabaseService();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		// SQL
		$sql = "SELECT id, name, updated_at, owner_context, owner_context_id, content, signature ".
			"FROM mail_html_template ".
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
	 * @param integer $id
	 * @return Model_MailHtmlTemplate
	 */
	static function get($id) {
		if(empty($id))
			return null;
		
		$objects = DAO_MailHtmlTemplate::getAll();
		
		if(isset($objects[$id]))
			return $objects[$id];
		
		return null;
	}
	
	/**
	 *
	 * @param bool $nocache
	 * @return Model_MailHtmlTemplate[]
	 */
	static function getAll($nocache=false) {
		$cache = DevblocksPlatform::getCacheService();
		if($nocache || null === ($html_templates = $cache->load(self::_CACHE_ALL))) {
			$html_templates = self::getWhere(
				null,
				DAO_MailHtmlTemplate::NAME,
				true,
				null,
				Cerb_ORMHelper::OPT_GET_MASTER_ONLY
			);
			$cache->save($html_templates, self::_CACHE_ALL);
		}
		
		return $html_templates;
	}
	
	/**
	 * @param resource $rs
	 * @return Model_MailHtmlTemplate[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object = new Model_MailHtmlTemplate();
			$object->id = $row['id'];
			$object->name = $row['name'];
			$object->updated_at = $row['updated_at'];
			$object->owner_context = $row['owner_context'];
			$object->owner_context_id = $row['owner_context_id'];
			$object->content = $row['content'];
			$object->signature = $row['signature'];
			$objects[$object->id] = $object;
		}
		
		mysqli_free_result($rs);
		
		return $objects;
	}
	
	static function random() {
		return self::_getRandom('mail_html_template');
	}
	
	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		$db->ExecuteMaster(sprintf("DELETE FROM mail_html_template WHERE id IN (%s)", $ids_list));
		
		// Clear the template setting if used on a group, bucket, or reply-to

		$db->ExecuteMaster(sprintf("UPDATE worker_group SET reply_html_template_id=0 WHERE reply_html_template_id IN (%s)", $ids_list));
		DAO_Group::clearCache();
		
		$db->ExecuteMaster(sprintf("UPDATE bucket SET reply_html_template_id=0 WHERE reply_html_template_id IN (%s)", $ids_list));
		DAO_Bucket::clearCache();
		
		$db->ExecuteMaster(sprintf("UPDATE address_outgoing SET reply_html_template_id=0 WHERE reply_html_template_id IN (%s)", $ids_list));
		DAO_AddressOutgoing::clearCache();
		
		// Fire event
		$eventMgr = DevblocksPlatform::getEventService();
		$eventMgr->trigger(
			new Model_DevblocksEvent(
				'context.delete',
				array(
					'context' => CerberusContexts::CONTEXT_MAIL_HTML_TEMPLATE,
					'context_ids' => $ids
				)
			)
		);
		
		self::clearCache();
		
		return true;
	}
	
	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_MailHtmlTemplate::getFields();
		
		// Sanitize
		if('*'==substr($sortBy,0,1) || !isset($fields[$sortBy]))
			$sortBy=null;

		list($tables,$wheres) = parent::_parseSearchParams($params, $columns, $fields, $sortBy);
		
		$select_sql = sprintf("SELECT ".
			"mail_html_template.id as %s, ".
			"mail_html_template.name as %s, ".
			"mail_html_template.updated_at as %s, ".
			"mail_html_template.owner_context as %s, ".
			"mail_html_template.owner_context_id as %s, ".
			"mail_html_template.content as %s, ".
			"mail_html_template.signature as %s ",
				SearchFields_MailHtmlTemplate::ID,
				SearchFields_MailHtmlTemplate::NAME,
				SearchFields_MailHtmlTemplate::UPDATED_AT,
				SearchFields_MailHtmlTemplate::OWNER_CONTEXT,
				SearchFields_MailHtmlTemplate::OWNER_CONTEXT_ID,
				SearchFields_MailHtmlTemplate::CONTENT,
				SearchFields_MailHtmlTemplate::SIGNATURE
			);
			
		$join_sql = "FROM mail_html_template ".
			(isset($tables['context_link']) ? sprintf("INNER JOIN context_link ON (context_link.to_context = %s AND context_link.to_context_id = mail_html_template.id) ", Cerb_ORMHelper::qstr(CerberusContexts::CONTEXT_MAIL_HTML_TEMPLATE)) : " ").
			'';
		
		// Custom field joins
		list($select_sql, $join_sql, $has_multiple_values) = self::_appendSelectJoinSqlForCustomFieldTables(
			$tables,
			$params,
			'mail_html_template.id',
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
			array('DAO_MailHtmlTemplate', '_translateVirtualParameters'),
			$args
		);
		
		return array(
			'primary_table' => 'mail_html_template',
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
			
		$from_context = CerberusContexts::CONTEXT_MAIL_HTML_TEMPLATE;
		$from_index = 'mail_html_template.id';
		
		$param_key = $param->field;
		settype($param_key, 'string');
		
		switch($param_key) {
			case SearchFields_MailHtmlTemplate::FULLTEXT_COMMENT_CONTENT:
				$search = Extension_DevblocksSearchSchema::get(Search_CommentContent::ID);
				$query = $search->getQueryFromParam($param);
				
				if(false === ($ids = $search->query($query, array('context_crc32' => sprintf("%u", crc32($from_context)))))) {
					$args['where_sql'] .= 'AND 0 ';
				
				} elseif(is_array($ids)) {
					$from_ids = DAO_Comment::getContextIdsByContextAndIds($from_context, $ids);
					
					$args['where_sql'] .= sprintf('AND %s IN (%s) ',
						$from_index,
						implode(', ', (!empty($from_ids) ? $from_ids : array(-1)))
					);
					
				} elseif(is_string($ids)) {
					$db = DevblocksPlatform::getDatabaseService();
					$temp_table = sprintf("_tmp_%s", uniqid());
					
					$db->ExecuteSlave(sprintf("CREATE TEMPORARY TABLE %s (PRIMARY KEY (id)) SELECT DISTINCT context_id AS id FROM comment INNER JOIN %s ON (%s.id=comment.id)",
						$temp_table,
						$ids,
						$ids
					));
					
					$args['join_sql'] .= sprintf("INNER JOIN %s ON (%s.id=%s) ",
						$temp_table,
						$temp_table,
						$from_index
					);
				}
				break;
			
			case SearchFields_MailHtmlTemplate::VIRTUAL_CONTEXT_LINK:
				$args['has_multiple_values'] = true;
				self::_searchComponentsVirtualContextLinks($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
				break;
		
			case SearchFields_MailHtmlTemplate::VIRTUAL_HAS_FIELDSET:
				self::_searchComponentsVirtualHasFieldset($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
				break;
		
			case SearchFields_MailHtmlTemplate::VIRTUAL_WATCHERS:
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
			($has_multiple_values ? 'GROUP BY mail_html_template.id ' : '').
			$sort_sql;
			
		if($limit > 0) {
			$rs = $db->SelectLimit($sql,$limit,$page*$limit) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs mysqli_result */
		} else {
			$rs = $db->ExecuteSlave($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs mysqli_result */
			$total = mysqli_num_rows($rs);
		}
		
		$results = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object_id = intval($row[SearchFields_MailHtmlTemplate::ID]);
			$results[$object_id] = $row;
		}

		$total = count($results);
		
		if($withCounts) {
			// We can skip counting if we have a less-than-full single page
			if(!(0 == $page && $total < $limit)) {
				$count_sql =
					($has_multiple_values ? "SELECT COUNT(DISTINCT mail_html_template.id) " : "SELECT COUNT(mail_html_template.id) ").
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
		$cache->remove(self::_CACHE_ALL);
	}

};

class SearchFields_MailHtmlTemplate implements IDevblocksSearchFields {
	const ID = 'm_id';
	const NAME = 'm_name';
	const UPDATED_AT = 'm_updated_at';
	const OWNER_CONTEXT = 'm_owner_context';
	const OWNER_CONTEXT_ID = 'm_owner_context_id';
	const CONTENT = 'm_content';
	const SIGNATURE = 'm_signature';

	const FULLTEXT_COMMENT_CONTENT = 'ftcc_content';
	
	// [TODO] Virtual owner
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
			self::ID => new DevblocksSearchField(self::ID, 'mail_html_template', 'id', $translate->_('common.id')),
			self::NAME => new DevblocksSearchField(self::NAME, 'mail_html_template', 'name', $translate->_('common.name')),
			self::UPDATED_AT => new DevblocksSearchField(self::UPDATED_AT, 'mail_html_template', 'updated_at', $translate->_('common.updated')),
			self::OWNER_CONTEXT => new DevblocksSearchField(self::OWNER_CONTEXT, 'mail_html_template', 'owner_context', null),
			self::OWNER_CONTEXT_ID => new DevblocksSearchField(self::OWNER_CONTEXT_ID, 'mail_html_template', 'owner_context_id', null),
			self::CONTENT => new DevblocksSearchField(self::CONTENT, 'mail_html_template', 'content', $translate->_('common.content')),
			self::SIGNATURE => new DevblocksSearchField(self::SIGNATURE, 'mail_html_template', 'signature', $translate->_('common.signature')),

			self::FULLTEXT_COMMENT_CONTENT => new DevblocksSearchField(self::FULLTEXT_COMMENT_CONTENT, 'ftcc', 'content', $translate->_('comment.filters.content'), 'FT'),
				
			self::VIRTUAL_CONTEXT_LINK => new DevblocksSearchField(self::VIRTUAL_CONTEXT_LINK, '*', 'context_link', $translate->_('common.links'), null),
			self::VIRTUAL_HAS_FIELDSET => new DevblocksSearchField(self::VIRTUAL_HAS_FIELDSET, '*', 'has_fieldset', $translate->_('common.fieldset'), null),
			self::VIRTUAL_WATCHERS => new DevblocksSearchField(self::VIRTUAL_WATCHERS, '*', 'workers', $translate->_('common.watchers'), 'WS'),
			
			self::CONTEXT_LINK => new DevblocksSearchField(self::CONTEXT_LINK, 'context_link', 'from_context', null),
			self::CONTEXT_LINK_ID => new DevblocksSearchField(self::CONTEXT_LINK_ID, 'context_link', 'from_context_id', null),
		);
		
		// Fulltext indexes
		
		$columns[self::FULLTEXT_COMMENT_CONTENT]->ft_schema = Search_CommentContent::ID;
		
		// Custom Fields
		$custom_columns = DevblocksSearchField::getCustomSearchFieldsByContexts(array(
			CerberusContexts::CONTEXT_MAIL_HTML_TEMPLATE,
		));
		
		if(!empty($custom_columns))
			$columns = array_merge($columns, $custom_columns);

		// Sort by label (translation-conscious)
		DevblocksPlatform::sortObjects($columns, 'db_label');

		return $columns;
	}
};

class Model_MailHtmlTemplate {
	public $id;
	public $name;
	public $updated_at;
	public $owner_context;
	public $owner_context_id;
	public $content;
	public $signature;
	
	function getSignature($worker=null) {
		$signature = $this->signature;
		
		if(!empty($worker)) {
			$tpl_builder = DevblocksPlatform::getTemplateBuilder();
			
			$labels = array();
			$values = array();
			CerberusContexts::getContext(CerberusContexts::CONTEXT_WORKER, $worker, $labels, $values, null, true, true);
			$dict = new DevblocksDictionaryDelegate($values);
			
			$signature = $tpl_builder->build($signature, $dict);
		}
		
		return $signature;
	}
	
	function getAttachments() {
		return DAO_Attachment::getByContextIds(CerberusContexts::CONTEXT_MAIL_HTML_TEMPLATE, $this->id);		
	}
};

class View_MailHtmlTemplate extends C4_AbstractView implements IAbstractView_Subtotals, IAbstractView_QuickSearch {
	const DEFAULT_ID = 'mailhtmltemplate';

	function __construct() {
		$translate = DevblocksPlatform::getTranslationService();
	
		$this->id = self::DEFAULT_ID;
		$this->name = $translate->_('Mail HTML Templates');
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_MailHtmlTemplate::ID;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_MailHtmlTemplate::NAME,
			SearchFields_MailHtmlTemplate::UPDATED_AT,
		);
		
		$this->addColumnsHidden(array(
			SearchFields_MailHtmlTemplate::FULLTEXT_COMMENT_CONTENT,
			SearchFields_MailHtmlTemplate::VIRTUAL_CONTEXT_LINK,
			SearchFields_MailHtmlTemplate::VIRTUAL_HAS_FIELDSET,
			SearchFields_MailHtmlTemplate::VIRTUAL_WATCHERS,
		));
		
		$this->addParamsHidden(array(
		));
		
		$this->doResetCriteria();
	}

	function getData() {
		$objects = DAO_MailHtmlTemplate::search(
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
		return $this->_getDataAsObjects('DAO_MailHtmlTemplate', $ids);
	}
	
	function getDataSample($size) {
		return $this->_doGetDataSample('DAO_MailHtmlTemplate', $size);
	}

	function getSubtotalFields() {
		$all_fields = $this->getParamsAvailable(true);
		
		$fields = array();

		if(is_array($all_fields))
		foreach($all_fields as $field_key => $field_model) {
			$pass = false;
			
			switch($field_key) {
				// Fields
//				case SearchFields_MailHtmlTemplate::EXAMPLE:
//					$pass = true;
//					break;
					
				// Virtuals
				case SearchFields_MailHtmlTemplate::VIRTUAL_CONTEXT_LINK:
				case SearchFields_MailHtmlTemplate::VIRTUAL_HAS_FIELDSET:
				case SearchFields_MailHtmlTemplate::VIRTUAL_WATCHERS:
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
//			case SearchFields_MailHtmlTemplate::EXAMPLE_BOOL:
//				$counts = $this->_getSubtotalCountForBooleanColumn('DAO_MailHtmlTemplate', $column);
//				break;

//			case SearchFields_MailHtmlTemplate::EXAMPLE_STRING:
//				$counts = $this->_getSubtotalCountForStringColumn('DAO_MailHtmlTemplate', $column);
//				break;
				
			case SearchFields_MailHtmlTemplate::VIRTUAL_CONTEXT_LINK:
				$counts = $this->_getSubtotalCountForContextLinkColumn('DAO_MailHtmlTemplate', CerberusContexts::CONTEXT_MAIL_HTML_TEMPLATE, $column);
				break;

			case SearchFields_MailHtmlTemplate::VIRTUAL_HAS_FIELDSET:
				$counts = $this->_getSubtotalCountForHasFieldsetColumn('DAO_MailHtmlTemplate', CerberusContexts::CONTEXT_MAIL_HTML_TEMPLATE, $column);
				break;
				
			case SearchFields_MailHtmlTemplate::VIRTUAL_WATCHERS:
				$counts = $this->_getSubtotalCountForWatcherColumn('DAO_MailHtmlTemplate', $column);
				break;
			
			default:
				// Custom fields
				if('cf_' == substr($column,0,3)) {
					$counts = $this->_getSubtotalCountForCustomColumn('DAO_MailHtmlTemplate', $column, 'mail_html_template.id');
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
					'options' => array('param_key' => SearchFields_MailHtmlTemplate::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'comments' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_FULLTEXT,
					'options' => array('param_key' => SearchFields_MailHtmlTemplate::FULLTEXT_COMMENT_CONTENT),
				),
			'content' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_MailHtmlTemplate::CONTENT, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'id' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_NUMBER,
					'options' => array('param_key' => SearchFields_MailHtmlTemplate::ID),
				),
			'name' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_MailHtmlTemplate::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'signature' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_MailHtmlTemplate::SIGNATURE, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'updated' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_MailHtmlTemplate::UPDATED_AT),
				),
			'watchers' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_WORKER,
					'options' => array('param_key' => SearchFields_MailHtmlTemplate::VIRTUAL_WATCHERS),
				),
		);
		
		// Add searchable custom fields
		
		$fields = self::_appendFieldsFromQuickSearchContext(CerberusContexts::CONTEXT_MAIL_HTML_TEMPLATE, $fields, null);
		
		// Engine/schema examples: Comments
		
		$ft_examples = array();
		
		if(false != ($schema = Extension_DevblocksSearchSchema::get(Search_CommentContent::ID))) {
			if(false != ($engine = $schema->getEngine())) {
				$ft_examples = $engine->getQuickSearchExamples($schema);
			}
		}
		
		if(!empty($ft_examples))
			$fields['comments']['examples'] = $ft_examples;
		
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
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_MAIL_HTML_TEMPLATE);
		$tpl->assign('custom_fields', $custom_fields);

		$tpl->assign('view_template', 'devblocks:cerberusweb.core::configuration/section/mail_html/view.tpl');
		$tpl->display('devblocks:cerberusweb.core::internal/views/subtotals_and_view.tpl');
	}

	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);

		switch($field) {
			case SearchFields_MailHtmlTemplate::NAME:
			case SearchFields_MailHtmlTemplate::OWNER_CONTEXT:
			case SearchFields_MailHtmlTemplate::CONTENT:
			case SearchFields_MailHtmlTemplate::SIGNATURE:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
				
			case SearchFields_MailHtmlTemplate::ID:
			case SearchFields_MailHtmlTemplate::OWNER_CONTEXT_ID:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__number.tpl');
				break;
				
			case 'placeholder_bool':
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__bool.tpl');
				break;
				
			case SearchFields_MailHtmlTemplate::UPDATED_AT:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__date.tpl');
				break;
				
			case SearchFields_MailHtmlTemplate::FULLTEXT_COMMENT_CONTENT:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__fulltext.tpl');
				break;
				
			case SearchFields_MailHtmlTemplate::VIRTUAL_CONTEXT_LINK:
				$contexts = Extension_DevblocksContext::getAll(false);
				$tpl->assign('contexts', $contexts);
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_link.tpl');
				break;
				
			case SearchFields_MailHtmlTemplate::VIRTUAL_HAS_FIELDSET:
				$this->_renderCriteriaHasFieldset($tpl, CerberusContexts::CONTEXT_MAIL_HTML_TEMPLATE);
				break;
				
			case SearchFields_MailHtmlTemplate::VIRTUAL_WATCHERS:
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
			case SearchFields_MailHtmlTemplate::VIRTUAL_CONTEXT_LINK:
				$this->_renderVirtualContextLinks($param);
				break;
				
			case SearchFields_MailHtmlTemplate::VIRTUAL_HAS_FIELDSET:
				$this->_renderVirtualHasFieldset($param);
				break;
			
			case SearchFields_MailHtmlTemplate::VIRTUAL_WATCHERS:
				$this->_renderVirtualWatchers($param);
				break;
		}
	}

	function getFields() {
		return SearchFields_MailHtmlTemplate::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		switch($field) {
			case SearchFields_MailHtmlTemplate::NAME:
			case SearchFields_MailHtmlTemplate::OWNER_CONTEXT:
			case SearchFields_MailHtmlTemplate::CONTENT:
			case SearchFields_MailHtmlTemplate::SIGNATURE:
				$criteria = $this->_doSetCriteriaString($field, $oper, $value);
				break;
				
			case SearchFields_MailHtmlTemplate::ID:
			case SearchFields_MailHtmlTemplate::OWNER_CONTEXT_ID:
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;
				
			case SearchFields_MailHtmlTemplate::UPDATED_AT:
				$criteria = $this->_doSetCriteriaDate($field, $oper);
				break;
				
			case 'placeholder_bool':
				@$bool = DevblocksPlatform::importGPC($_REQUEST['bool'],'integer',1);
				$criteria = new DevblocksSearchCriteria($field,$oper,$bool);
				break;
				
			case SearchFields_MailHtmlTemplate::FULLTEXT_COMMENT_CONTENT:
				@$scope = DevblocksPlatform::importGPC($_REQUEST['scope'],'string','expert');
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_FULLTEXT,array($value,$scope));
				break;
				
			case SearchFields_MailHtmlTemplate::VIRTUAL_CONTEXT_LINK:
				@$context_links = DevblocksPlatform::importGPC($_REQUEST['context_link'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$context_links);
				break;
				
			case SearchFields_MailHtmlTemplate::VIRTUAL_HAS_FIELDSET:
				@$options = DevblocksPlatform::importGPC($_REQUEST['options'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$options);
				break;
				
			case SearchFields_MailHtmlTemplate::VIRTUAL_WATCHERS:
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
					//$change_fields[DAO_MailHtmlTemplate::EXAMPLE] = 'some value';
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
			list($objects,$null) = DAO_MailHtmlTemplate::search(
				array(),
				$this->getParams(),
				100,
				$pg++,
				SearchFields_MailHtmlTemplate::ID,
				true,
				false
			);
			$ids = array_merge($ids, array_keys($objects));
			 
		} while(!empty($objects));

		$batch_total = count($ids);
		for($x=0;$x<=$batch_total;$x+=100) {
			$batch_ids = array_slice($ids,$x,100);
			
			if(!empty($change_fields)) {
				DAO_MailHtmlTemplate::update($batch_ids, $change_fields);
			}

			// Custom Fields
			self::_doBulkSetCustomFields(CerberusContexts::CONTEXT_MAIL_HTML_TEMPLATE, $custom_fields, $batch_ids);
			
			unset($batch_ids);
		}

		unset($ids);
	}
};

class Context_MailHtmlTemplate extends Extension_DevblocksContext implements IDevblocksContextProfile, IDevblocksContextPeek { // IDevblocksContextImport
	function getRandom() {
		return DAO_MailHtmlTemplate::random();
	}
	
	function profileGetUrl($context_id) {
		if(empty($context_id))
			return '';
	
		$url_writer = DevblocksPlatform::getUrlService();
		$url = $url_writer->writeNoProxy('c=profiles&type=html_template&id='.$context_id, true);
		return $url;
	}
	
	function getMeta($context_id) {
		$mail_html_template = DAO_MailHtmlTemplate::get($context_id);
		$url_writer = DevblocksPlatform::getUrlService();
		
		$url = $this->profileGetUrl($context_id);
		$friendly = DevblocksPlatform::strToPermalink($mail_html_template->name);
		
		if(!empty($friendly))
			$url .= '-' . $friendly;
		
		return array(
			'id' => $mail_html_template->id,
			'name' => $mail_html_template->name,
			'permalink' => $url,
		);
	}
	
	// [TODO] Interface
	function getDefaultProperties() {
		return array(
			'updated_at',
		);
	}
	
	function getContext($mail_html_template, &$token_labels, &$token_values, $prefix=null) {
		if(is_null($prefix))
			$prefix = 'HTML Template:';
		
		$translate = DevblocksPlatform::getTranslationService();
		$fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_MAIL_HTML_TEMPLATE);

		// Polymorph
		if(is_numeric($mail_html_template)) {
			$mail_html_template = DAO_MailHtmlTemplate::get($mail_html_template);
		} elseif($mail_html_template instanceof Model_MailHtmlTemplate) {
			// It's what we want already.
		} elseif(is_array($mail_html_template)) {
			$mail_html_template = Cerb_ORMHelper::recastArrayToModel($mail_html_template, 'Model_MailHtmlTemplate');
		} else {
			$mail_html_template = null;
		}
		
		// Token labels
		$token_labels = array(
			'_label' => $prefix,
			'id' => $prefix.$translate->_('common.id'),
			'content' => $prefix.$translate->_('common.content'),
			'name' => $prefix.$translate->_('common.name'),
			'signature' => $prefix.$translate->_('common.signature'),
			'updated_at' => $prefix.$translate->_('common.updated'),
			'record_url' => $prefix.$translate->_('common.url.record'),
		);
		
		// Token types
		$token_types = array(
			'_label' => 'context_url',
			'id' => Model_CustomField::TYPE_NUMBER,
			'content' => Model_CustomField::TYPE_SINGLE_LINE,
			'name' => Model_CustomField::TYPE_SINGLE_LINE,
			'signature' => Model_CustomField::TYPE_SINGLE_LINE,
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
		
		$token_values['_context'] = CerberusContexts::CONTEXT_MAIL_HTML_TEMPLATE;
		$token_values['_types'] = $token_types;
		
		if($mail_html_template) {
			$token_values['_loaded'] = true;
			$token_values['_label'] = $mail_html_template->name;
			$token_values['content'] = $mail_html_template->content;
			$token_values['id'] = $mail_html_template->id;
			$token_values['name'] = $mail_html_template->name;
			$token_values['signature'] = $mail_html_template->signature;
			$token_values['updated_at'] = $mail_html_template->updated_at;
			
			// Custom fields
			$token_values = $this->_importModelCustomFieldsAsValues($mail_html_template, $token_values);
			
			// URL
			$url_writer = DevblocksPlatform::getUrlService();
			$token_values['record_url'] = $url_writer->writeNoProxy(sprintf("c=profiles&type=html_template&id=%d-%s",$mail_html_template->id, DevblocksPlatform::strToPermalink($mail_html_template->name)), true);
		}
		
		return true;
	}

	function lazyLoadContextValues($token, $dictionary) {
		if(!isset($dictionary['id']))
			return;
		
		$context = CerberusContexts::CONTEXT_MAIL_HTML_TEMPLATE;
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
		$view->name = 'HTML Templates';
		/*
		$view->addParams(array(
			SearchFields_MailHtmlTemplate::UPDATED_AT => new DevblocksSearchCriteria(SearchFields_MailHtmlTemplate::UPDATED_AT,'=',0),
		), true);
		*/
		$view->renderSortBy = SearchFields_MailHtmlTemplate::UPDATED_AT;
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
		$view->name = 'HTML Templates';
		
		$params_req = array();
		
		if(!empty($context) && !empty($context_id)) {
			$params_req = array(
				new DevblocksSearchCriteria(SearchFields_MailHtmlTemplate::CONTEXT_LINK,'=',$context),
				new DevblocksSearchCriteria(SearchFields_MailHtmlTemplate::CONTEXT_LINK_ID,'=',$context_id),
			);
		}
		
		$view->addParamsRequired($params_req, true);
		
		$view->renderTemplate = 'context';
		return $view;
	}
	
	function renderPeekPopup($context_id=0, $view_id='') {
		$active_worker = CerberusApplication::getActiveWorker();
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('view_id', $view_id);
		
		if(!empty($context_id) && null != ($mail_html_template = DAO_MailHtmlTemplate::get($context_id))) {
			$tpl->assign('model', $mail_html_template);
			
		} else {
			$mail_html_template = new Model_MailHtmlTemplate();
			$mail_html_template->name = "New HTML Template";
			$mail_html_template->content = null;
			$mail_html_template->signature = null;
			$tpl->assign('model', $mail_html_template);
		}

		/*
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_MAIL_HTML_TEMPLATE, false);
		$tpl->assign('custom_fields', $custom_fields);
		
		if(!empty($context_id)) {
			$custom_field_values = DAO_CustomFieldValue::getValuesByContextIds(CerberusContexts::CONTEXT_MAIL_HTML_TEMPLATE, $context_id);
			if(isset($custom_field_values[$context_id]))
				$tpl->assign('custom_field_values', $custom_field_values[$context_id]);
		}
		*/
		
		// Owners
		$roles = DAO_WorkerRole::getAll();
		$tpl->assign('roles', $roles);
		
		$workers = DAO_Worker::getAll();
		$tpl->assign('workers', $workers);
		
		$groups = DAO_Group::getAll();
		$tpl->assign('groups', $groups);

		$owner_groups = array();
		foreach($groups as $k => $v) {
			if($active_worker->is_superuser || $active_worker->isGroupManager($k))
				$owner_groups[$k] = $v;
		}
		$tpl->assign('owner_groups', $owner_groups);
		
		$owner_roles = array();
		foreach($roles as $k => $v) { /* @var $v Model_WorkerRole */
			if($active_worker->is_superuser)
				$owner_roles[$k] = $v;
		}
		$tpl->assign('owner_roles', $owner_roles);
		
		// Tokens
		
		$worker_token_labels = array();
		$worker_token_values = array();
		CerberusContexts::getContext(CerberusContexts::CONTEXT_WORKER, null, $worker_token_labels, $worker_token_values);
		$tpl->assign('worker_token_labels', $worker_token_labels);
		
		// Template
		
		$tpl->display('devblocks:cerberusweb.core::configuration/section/mail_html/peek.tpl');
	}
	
	/*
	function importGetKeys() {
		// [TODO] Translate
	
		$keys = array(
			'name' => array(
				'label' => 'Name',
				'type' => Model_CustomField::TYPE_SINGLE_LINE,
				'param' => SearchFields_MailHtmlTemplate::NAME,
				'required' => true,
			),
			'updated_at' => array(
				'label' => 'Updated Date',
				'type' => Model_CustomField::TYPE_DATE,
				'param' => SearchFields_MailHtmlTemplate::UPDATED_AT,
			),
		);
	
		$fields = SearchFields_MailHtmlTemplate::getFields();
		self::_getImportCustomFields($fields, $keys);
	
		DevblocksPlatform::sortObjects($keys, '[label]', true);
	
		return $keys;
	}
	
	function importKeyValue($key, $value) {
		switch($key) {
		}
	
		return $value;
	}
	
	function importSaveObject(array $fields, array $custom_fields, array $meta) {
		// If new...
		if(!isset($meta['object_id']) || empty($meta['object_id'])) {
			// Make sure we have a name
			if(!isset($fields[DAO_MailHtmlTemplate::NAME])) {
				$fields[DAO_MailHtmlTemplate::NAME] = 'New ' . $this->manifest->name;
			}
	
			// Create
			$meta['object_id'] = DAO_MailHtmlTemplate::create($fields);
	
		} else {
			// Update
			DAO_MailHtmlTemplate::update($meta['object_id'], $fields);
		}
	
		// Custom fields
		if(!empty($custom_fields) && !empty($meta['object_id'])) {
			DAO_CustomFieldValue::formatAndSetFieldValues($this->manifest->id, $meta['object_id'], $custom_fields, false, true, true); //$is_blank_unset (4th)
		}
	}
	*/
};
