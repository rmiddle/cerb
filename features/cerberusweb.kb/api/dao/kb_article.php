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

class DAO_KbArticle extends Cerb_ORMHelper {
	const ID = 'id';
	const TITLE = 'title';
	const UPDATED = 'updated';
	const VIEWS = 'views';
	const FORMAT = 'format';
	const CONTENT = 'content';
	
	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = sprintf("INSERT INTO kb_article (updated) ".
			"VALUES (%d)",
			time()
		);
		$db->ExecuteMaster($sql);
		$id = $db->LastInsertId();
		
		self::update($id, $fields);
		
		return $id;
	}
	
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

	static function getWhere($where=null, $sortBy='updated', $sortAsc=false, $limit=null) {
		$db = DevblocksPlatform::getDatabaseService();
		
		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		$sql = "SELECT id, title, views, updated, format, content ".
			"FROM kb_article ".
			$where_sql.
			$sort_sql.
			$limit_sql
			;
		$rs = $db->ExecuteSlave($sql);
		
		return self::_createObjectsFromResultSet($rs);
	}
	
	/**
	 * Enter description here...
	 *
	 * @param resource $rs
	 */
	static private function _createObjectsFromResultSet($rs=null) {
		$objects = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object = new Model_KbArticle();
			$object->id = intval($row['id']);
			$object->title = $row['title'];
			$object->updated = $row['updated'];
			$object->views = $row['views'];
			$object->format = $row['format'];
			$object->content = $row['content'];
			$objects[$object->id] = $object;
		}
		
		mysqli_free_result($rs);
		
		return $objects;
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
				CerberusContexts::checkpointChanges(CerberusContexts::CONTEXT_KB_ARTICLE, $batch_ids);
			}
			
			// Make changes
			parent::_update($batch_ids, 'kb_article', $fields);
			
			// Send events
			if($check_deltas) {
				
				// Trigger an event about the changes
				$eventMgr = DevblocksPlatform::getEventService();
				$eventMgr->trigger(
					new Model_DevblocksEvent(
						'dao.kb_article.update',
						array(
							'fields' => $fields,
						)
					)
				);
				
				// Log the context update
				DevblocksPlatform::markContextChanged(CerberusContexts::CONTEXT_KB_ARTICLE, $batch_ids);
			}
		}
	}
	
	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		
		if(empty($ids))
			return;
		
		$db = DevblocksPlatform::getDatabaseService();
		
		$id_string = implode(',', $ids);
		
		// Articles
		$db->ExecuteMaster(sprintf("DELETE FROM kb_article WHERE id IN (%s)", $id_string));
		
		// Categories
		$db->ExecuteMaster(sprintf("DELETE FROM kb_article_to_category WHERE kb_article_id IN (%s)", $id_string));
		
		// Search indexes
		$search = Extension_DevblocksSearchSchema::get(Search_KbArticle::ID, true);
		$search->delete($ids);
		
		// Fire event
		$eventMgr = DevblocksPlatform::getEventService();
		$eventMgr->trigger(
			new Model_DevblocksEvent(
				'context.delete',
				array(
					'context' => CerberusContexts::CONTEXT_KB_ARTICLE,
					'context_ids' => $ids
				)
			)
		);
	}
	
	static function maint() {
		// Fire event
		$eventMgr = DevblocksPlatform::getEventService();
		$eventMgr->trigger(
			new Model_DevblocksEvent(
				'context.maint',
				array(
					'context' => CerberusContexts::CONTEXT_KB_ARTICLE,
					'context_table' => 'kb_article',
					'context_key' => 'id',
				)
			)
		);
	}

	static function getCategoriesByArticleId($article_id) {
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($article_id))
			return array();
		
		$categories = array();
		
		$rs = $db->ExecuteSlave(sprintf("SELECT kb_category_id ".
			"FROM kb_article_to_category ".
			"WHERE kb_article_id = %d",
			$article_id
		));
		
		while($row = mysqli_fetch_assoc($rs)) {
			$cat_id = intval($row['kb_category_id']);
			$categories[$cat_id] = $cat_id;
		}
		
		mysqli_free_result($rs);
		
		return $categories;
	}
	
	static function setCategories($article_ids,$category_ids,$replace=true) {
		$db = DevblocksPlatform::getDatabaseService();
		
		if(!is_array($article_ids))
			$article_ids = array($article_ids);

		if(!is_array($category_ids))
			$category_ids = array($category_ids);
		
		if($replace) {
			$db->ExecuteMaster(sprintf("DELETE FROM kb_article_to_category WHERE kb_article_id IN (%s)",
				implode(',', $article_ids)
			));
		}
		
		$categories = DAO_KbCategory::getAll();
		
		if(is_array($category_ids) && !empty($category_ids)) {
			foreach($category_ids as $category_id) {
				$is_add = '-'==substr($category_id, 0, 1) ? false : true;
				$category_id = ltrim($category_id,'+-');
				
				// Add
				if($is_add) {
					$pid = $category_id;
					while($pid) {
						$top_category_id = $pid;
						$pid = $categories[$pid]->parent_id;
					}
					
					if(is_array($article_ids))
					foreach($article_ids as $article_id) {
						$db->ExecuteMaster(sprintf("REPLACE INTO kb_article_to_category (kb_article_id, kb_category_id, kb_top_category_id) ".
							"VALUES (%d, %d, %d)",
							$article_id,
							$category_id,
							$top_category_id
						));
					}
					
				// Delete
				} else {
					if(is_array($article_ids))
					foreach($article_ids as $article_id) {
						$db->ExecuteMaster(
							sprintf("DELETE FROM kb_article_to_category WHERE kb_article_id = %d AND kb_category_id = %d",
								$article_id,
								$category_id
							)
						);
					}
				}
			}
		}
		
		return TRUE;
	}
	
	public static function random() {
		return self::_getRandom('kb_article');
	}
	
	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_KbArticle::getFields();
		
		// Sanitize
		if('*'==substr($sortBy,0,1) || !isset($fields[$sortBy]) || !in_array($sortBy,$columns))
			$sortBy=null;

		list($tables,$wheres) = parent::_parseSearchParams($params, $columns, $fields, $sortBy);
		
		$select_sql = sprintf("SELECT ".
			"kb.id as %s, ".
			"kb.title as %s, ".
			"kb.updated as %s, ".
			"kb.views as %s, ".
			"kb.format as %s, ".
			"kb.content as %s ",
				SearchFields_KbArticle::ID,
				SearchFields_KbArticle::TITLE,
				SearchFields_KbArticle::UPDATED,
				SearchFields_KbArticle::VIEWS,
				SearchFields_KbArticle::FORMAT,
				SearchFields_KbArticle::CONTENT
			);
			
		$join_sql = "FROM kb_article kb ";

		// [JAS]: Dynamic table joins
		if(isset($tables['katc'])) {
			$select_sql .= sprintf(", katc.kb_top_category_id AS %s ",
				SearchFields_KbArticle::TOP_CATEGORY_ID
			);
			$join_sql .= "LEFT JOIN kb_article_to_category katc ON (kb.id=katc.kb_article_id) ";
		}
		
		// [JAS]: Dynamic table joins
		if(isset($tables['context_link']))
			$join_sql .= "INNER JOIN context_link ON (context_link.to_context = 'cerberusweb.contexts.kb_article' AND context_link.to_context_id = kb.id) ";
		
		// Custom field joins
		list($select_sql, $join_sql, $has_multiple_values) = self::_appendSelectJoinSqlForCustomFieldTables(
			$tables,
			$params,
			'kb.id',
			$select_sql,
			$join_sql
		);
		
		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = (!empty($sortBy) ? sprintf("ORDER BY %s %s ",$sortBy,($sortAsc || is_null($sortAsc))?"ASC":"DESC") : " ");

		// Translate virtual fields
		
		$args = array(
			'join_sql' => &$join_sql,
			'where_sql' => &$where_sql,
			'tables' => &$tables,
			'has_multiple_values' => &$has_multiple_values
		);
		
		array_walk_recursive(
			$params,
			array('DAO_KbArticle', '_translateVirtualParameters'),
			$args
		);
		
		$result = array(
			'primary_table' => 'kb',
			'select' => $select_sql,
			'join' => $join_sql,
			'where' => $where_sql,
			'has_multiple_values' => true,
			'sort' => $sort_sql,
		);
		
		return $result;
	}
	
	private static function _translateVirtualParameters($param, $key, &$args) {
		if(!is_a($param, 'DevblocksSearchCriteria'))
			return;
		
		$from_context = CerberusContexts::CONTEXT_KB_ARTICLE;
		$from_index = 'kb.id';
		
		$param_key = $param->field;
		settype($param_key, 'string');
		
		switch($param_key) {
			case SearchFields_KbArticle::FULLTEXT_ARTICLE_CONTENT:
				$search = Extension_DevblocksSearchSchema::get(Search_KbArticle::ID);
				$query = $search->getQueryFromParam($param);
				
				if(false === ($ids = $search->query($query, array()))) {
					$args['where_sql'] .= 'AND 0 ';
				
				} elseif(is_array($ids)) {
					if(empty($ids))
						$ids = array(-1);
					
					$args['where_sql'] .= sprintf('AND %s IN (%s) ',
						$from_index,
						implode(', ', $ids)
					);
					
				} elseif(is_string($ids)) {
					$db = DevblocksPlatform::getDatabaseService();
					
					$args['join_sql'] .= sprintf("INNER JOIN %s ON (%s.id=kb.id) ",
						$ids,
						$ids
					);
				}
				
				break;
			
			case SearchFields_KbArticle::VIRTUAL_CONTEXT_LINK:
				$args['has_multiple_values'] = true;
				self::_searchComponentsVirtualContextLinks($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
				break;
			
			case SearchFields_KbArticle::VIRTUAL_HAS_FIELDSET:
				self::_searchComponentsVirtualHasFieldset($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
				break;
				
			case SearchFields_KbArticle::VIRTUAL_WATCHERS:
				$args['has_multiple_values'] = true;
				self::_searchComponentsVirtualWatchers($param, $from_context, $from_index, $args['join_sql'], $args['where_sql'], $args['tables']);
				break;
		}
	}
	
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
			($has_multiple_values ? 'GROUP BY kb.id ' : '').
			$sort_sql;
		
		$rs = $db->SelectLimit($sql,$limit,$page*$limit) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg());
		
		$results = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$id = intval($row[SearchFields_KbArticle::ID]);
			$results[$id] = $row;
		}

		$total = count($results);
		
		if($withCounts) {
			// We can skip counting if we have a less-than-full single page
			if(!(0 == $page && $total < $limit)) {
				$count_sql =
					($has_multiple_values ? "SELECT COUNT(DISTINCT kb.id) " : "SELECT COUNT(kb.id) ").
					$join_sql.
					$where_sql;
				$total = $db->GetOneSlave($count_sql);
			}
		}
		
		mysqli_free_result($rs);
		
		return array($results,$total);
	}
};

class SearchFields_KbArticle implements IDevblocksSearchFields {
	// Table
	const ID = 'kb_id';
	const TITLE = 'kb_title';
	const UPDATED = 'kb_updated';
	const VIEWS = 'kb_views';
	const FORMAT = 'kb_format';
	const CONTENT = 'kb_content';
	
	const CATEGORY_ID = 'katc_category_id';
	const TOP_CATEGORY_ID = 'katc_top_category_id';
	
	const FULLTEXT_ARTICLE_CONTENT = 'ftkb_content';
	
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
			self::ID => new DevblocksSearchField(self::ID, 'kb', 'id', $translate->_('kb_article.id')),
			self::TITLE => new DevblocksSearchField(self::TITLE, 'kb', 'title', $translate->_('kb_article.title'), Model_CustomField::TYPE_SINGLE_LINE),
			self::UPDATED => new DevblocksSearchField(self::UPDATED, 'kb', 'updated', $translate->_('kb_article.updated'), Model_CustomField::TYPE_DATE),
			self::VIEWS => new DevblocksSearchField(self::VIEWS, 'kb', 'views', $translate->_('kb_article.views'), Model_CustomField::TYPE_NUMBER),
			self::FORMAT => new DevblocksSearchField(self::FORMAT, 'kb', 'format', $translate->_('kb_article.format')),
			self::CONTENT => new DevblocksSearchField(self::CONTENT, 'kb', 'content', $translate->_('kb_article.content')),
			
			self::CATEGORY_ID => new DevblocksSearchField(self::CATEGORY_ID, 'katc', 'kb_category_id'),
			self::TOP_CATEGORY_ID => new DevblocksSearchField(self::TOP_CATEGORY_ID, 'katc', 'kb_top_category_id', $translate->_('kb_article.topic')),
			
			self::VIRTUAL_CONTEXT_LINK => new DevblocksSearchField(self::VIRTUAL_CONTEXT_LINK, '*', 'context_link', $translate->_('common.links'), null),
			self::VIRTUAL_HAS_FIELDSET => new DevblocksSearchField(self::VIRTUAL_HAS_FIELDSET, '*', 'has_fieldset', $translate->_('common.fieldset'), null),
			self::VIRTUAL_WATCHERS => new DevblocksSearchField(self::VIRTUAL_WATCHERS, '*', 'workers', $translate->_('common.watchers'), 'WS'),
			
			self::CONTEXT_LINK => new DevblocksSearchField(self::CONTEXT_LINK, 'context_link', 'from_context', null),
			self::CONTEXT_LINK_ID => new DevblocksSearchField(self::CONTEXT_LINK_ID, 'context_link', 'from_context_id', null),
				
			self::FULLTEXT_ARTICLE_CONTENT => new DevblocksSearchField(self::FULLTEXT_ARTICLE_CONTENT, 'ftkb', 'content', $translate->_('kb_article.content'), 'FT'),
		);
		
		// Fulltext indexes
		
		$columns[self::FULLTEXT_ARTICLE_CONTENT]->ft_schema = Search_KbArticle::ID;

		// Custom fields with fieldsets
		
		$custom_columns = DevblocksSearchField::getCustomSearchFieldsByContexts(array(
			CerberusContexts::CONTEXT_KB_ARTICLE,
			CerberusContexts::CONTEXT_KB_CATEGORY,
		));
		
		if(is_array($custom_columns))
			$columns = array_merge($columns, $custom_columns);

		// Sort by label (translation-conscious)
		DevblocksPlatform::sortObjects($columns, 'db_label');

		return $columns;
	}
};

class Search_KbArticle extends Extension_DevblocksSearchSchema {
	const ID = 'cerberusweb.search.schema.kb_article';
	
	public function getNamespace() {
		return 'kb_article';
	}
	
	public function getAttributes() {
		return array();
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
	
	public function query($query, $attributes=array(), $limit=500) {
		if(false == ($engine = $this->getEngine()))
			return false;

		$ids = $engine->query($this, $query, $attributes, $limit);
		
		return $ids;
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
				DAO_KbArticle::UPDATED,
				$ptr_time,
				DAO_KbArticle::ID,
				$id
			);
			$articles = DAO_KbArticle::getWhere($where, array(DAO_KbArticle::UPDATED, DAO_KbArticle::ID), array(true, true), 100);

			if(empty($articles)) {
				$done = true;
				continue;
			}
			
			$last_time = $ptr_time;
			
			foreach($articles as $article) { /* @var $article Model_KbArticle */
				$id = $article->id;
				$ptr_time = $article->updated;

				// If we're not inside a block of the same timestamp, reset the seek pointer
				$ptr_id = ($last_time == $ptr_time) ? $id : 0;

				$logger->info(sprintf("[Search] Indexing %s %d...",
					$ns,
					$id
				));
				
				$doc = array(
					'title' => $article->title,
					'content' => strip_tags($article->content),
				);
				
				if(false === ($engine->index($this, $id, $doc)))
					return false;
				
				flush();
			}
		}
		
		// If we ran out of articles, always reset the ID and use the current time
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

class Model_KbArticle {
	const FORMAT_PLAINTEXT = 0;
	const FORMAT_HTML = 1;
	const FORMAT_MARKDOWN = 2;
	
	public $id = 0;
	public $title = '';
	public $views = 0;
	public $updated = 0;
	public $format = 0;
	public $content = '';
	
	function getContent() {
		$html = '';
		
		switch($this->format) {
			case self::FORMAT_HTML:
				$html = $this->content;
				break;
			case self::FORMAT_PLAINTEXT:
				$html = nl2br(htmlentities($this->content, ENT_QUOTES, LANG_CHARSET_CODE));
				break;
			case self::FORMAT_MARKDOWN:
				$html = DevblocksPlatform::parseMarkdown($this->content);
				break;
		}
		
		return $html;
	}
	
	// [TODO] Reuse this!
	function getCategories() {
		$categories = DAO_KbCategory::getAll();
		$cats = DAO_KbArticle::getCategoriesByArticleId($this->id);

		$trails = array();
		
		if(is_array($cats))
		foreach($cats as $cat_id) {
			$pid = $cat_id;
			$trail = array();
			while($pid) {
				array_unshift($trail,$pid);
				$pid = $categories[$pid]->parent_id;
			}
			
			$trails[] = $trail;
		}
		
		// Remove redundant trails
		if(is_array($trails))
		foreach($trails as $idx => $trail) {
			foreach($trails as $c_idx => $compare_trail) {
				if($idx != $c_idx && count($compare_trail) >= count($trail)) {
					if(array_slice($compare_trail,0,count($trail))==$trail) {
						unset($trails[$idx]);
					}
				}
			}
		}
		
		$breadcrumbs = array();
		
		if(is_array($trails))
		foreach($trails as $idx => $trail) {
			$last_step = end($trail);
			reset($trail);
			
			foreach($trail as $step) {
				if(!isset($breadcrumbs[$last_step]))
					$breadcrumbs[$last_step] = array();
					
				$breadcrumbs[$last_step][$step] = $categories[$step];
			}
		}
		
		unset($trails);
		
		return $breadcrumbs;
	}
	
	function extractInternalURLsFromContent() {
		$url_writer = DevblocksPlatform::getUrlService();
		$img_baseurl = $url_writer->write('c=files', true, false);
		$img_baseurl_parts = parse_url($img_baseurl);
		
		$results = array();
		
		// Extract URLs
		$matches = array();
			preg_match_all(
				sprintf('#\"(https*://%s%s/(.*?))\"#i',
				preg_quote($img_baseurl_parts['host']),
				preg_quote($img_baseurl_parts['path'])
			),
			$this->content,
			$matches
		);

		if(isset($matches[1]))
		foreach($matches[1] as $idx => $replace_url) {
			$results[$replace_url] = array(
				'path' => $matches[2][$idx],
			);
		}
		
		return $results;
	}
};

class Context_KbArticle extends Extension_DevblocksContext implements IDevblocksContextProfile, IDevblocksContextPeek {
	function authorize($context_id, Model_Worker $worker) {
		return TRUE;
	}
	
	function getRandom() {
		return DAO_KbArticle::random();
	}

	function profileGetUrl($context_id) {
		if(empty($context_id))
			return '';
		
		$url_writer = DevblocksPlatform::getUrlService();
		$url = $url_writer->writeNoProxy(sprintf("c=profiles&type=kb&id=%d", $context_id, true));
		return $url;
	}
	
	function getMeta($context_id) {
		$article = DAO_KbArticle::get($context_id);
		
		$url = $this->profileGetUrl($context_id);
		$friendly = DevblocksPlatform::strToPermalink($article->title);
		
		if(!empty($friendly))
			$url .= '-' . $friendly;
		
		return array(
			'id' => $article->id,
			'name' => $article->title,
			'permalink' => $url,
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
			'views',
			'updated',
		);
	}
	
	function getContext($article, &$token_labels, &$token_values, $prefix=null) {
		if(is_null($prefix))
			$prefix = 'Article:';
		
		$translate = DevblocksPlatform::getTranslationService();
		$fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_KB_ARTICLE);
		
		// Polymorph
		if(is_numeric($article)) {
			$article = DAO_KbArticle::get($article);
		} elseif($article instanceof Model_KbArticle) {
			// It's what we want already.
		} elseif(is_array($article)) {
			$article = Cerb_ORMHelper::recastArrayToModel($article, 'Model_KbArticle');
		} else {
			$article = null;
		}
		/* @var $article Model_KbArticle */
			
		// Token labels
		$token_labels = array(
			'_label' => $prefix,
			'content' => $prefix.$translate->_('kb_article.content'),
			'id' => $prefix.$translate->_('common.id'),
			'title' => $prefix.$translate->_('kb_article.title'),
			'updated' => $prefix.$translate->_('kb_article.updated'),
			'views' => $prefix.$translate->_('kb_article.views'),
			'record_url' => $prefix.$translate->_('common.url.record'),
		);
		
		// Token types
		$token_types = array(
			'_label' => 'context_url',
			'content' => null,
			'id' => Model_CustomField::TYPE_NUMBER,
			'title' => Model_CustomField::TYPE_SINGLE_LINE,
			'updated' => Model_CustomField::TYPE_DATE,
			'views' => Model_CustomField::TYPE_NUMBER,
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
		
		$token_values['_context'] = CerberusContexts::CONTEXT_KB_ARTICLE;
		$token_values['_types'] = $token_types;
		
		// Token values
		if(null != $article) {
			$token_values['_label'] = $article->title;
			$token_values['content'] = $article->getContent();
			$token_values['id'] = $article->id;
			$token_values['title'] = $article->title;
			$token_values['updated'] = $article->updated;
			$token_values['views'] = $article->views;
			
			// Custom fields
			$token_values = $this->_importModelCustomFieldsAsValues($article, $token_values);
			
			// URL
			$url_writer = DevblocksPlatform::getUrlService();
			$token_values['record_url'] = $url_writer->writeNoProxy(sprintf("c=profiles&type=kb&id=%d-%s",$article->id, DevblocksPlatform::strToPermalink($article->title)), true);
		}
		
		return TRUE;
	}

	function lazyLoadContextValues($token, $dictionary) {
		if(!isset($dictionary['id']))
			return;
		
		$context = CerberusContexts::CONTEXT_KB_ARTICLE;
		$context_id = $dictionary['id'];
		
		@$is_loaded = $dictionary['_loaded'];
		$values = array();
		
		if(!$is_loaded) {
			$labels = array();
			CerberusContexts::getContext($context, $context_id, $labels, $values, null, true);
		}
		
		switch($token) {
			case 'categories':
				// Categories
				if(null != ($article = DAO_KbArticle::get($context_id))
					&& null != ($categories = $article->getCategories()) 
					&& is_array($categories)
					) {
					$values['categories'] = array();
					
					foreach($categories as $category_id => $trail) {
						foreach($trail as $step_id => $step) {
							if(!isset($values['categories'][$category_id]))
								$values['categories'][$category_id] = array();
							$values['categories'][$category_id][$step_id] = $step->name;
						}
					}
				}
				break;
			
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
		$view->addParams(array(
			//SearchFields_KbArticle::IS_CLOSED => new DevblocksSearchCriteria(SearchFields_KbArticle::IS_CLOSED,'=',0),
		), true);
		$view->renderSortBy = SearchFields_KbArticle::UPDATED;
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
		
		$params_req = array();
		
		if(!empty($context) && !empty($context_id)) {
			$params_req = array(
				new DevblocksSearchCriteria(SearchFields_KbArticle::CONTEXT_LINK,'=',$context),
				new DevblocksSearchCriteria(SearchFields_KbArticle::CONTEXT_LINK_ID,'=',$context_id),
			);
		}
		
		$view->addParamsRequired($params_req, true);
		
		$view->renderTemplate = 'context';
		return $view;
	}
	
	function renderPeekPopup($context_id=0, $view_id='') {
		$tpl = DevblocksPlatform::getTemplateService();
		
		if(!empty($context_id)) {
			$article = DAO_KbArticle::get($context_id);
			$tpl->assign('article', $article);
		}
		
		if(!empty($view_id))
			$tpl->assign('view_id', $view_id);
			
		$tpl->display('devblocks:cerberusweb.kb::kb/peek_readonly.tpl');
	}
};

class View_KbArticle extends C4_AbstractView implements IAbstractView_Subtotals, IAbstractView_QuickSearch {
	const DEFAULT_ID = 'kb_overview';
	
	function __construct() {
		$this->id = self::DEFAULT_ID;
		$this->name = 'Articles';
		$this->renderSortBy = 'kb_updated';
		$this->renderSortAsc = false;

		$this->view_columns = array(
			SearchFields_KbArticle::TITLE,
			SearchFields_KbArticle::UPDATED,
			SearchFields_KbArticle::VIEWS,
		);
		$this->addColumnsHidden(array(
			SearchFields_KbArticle::CONTENT,
			SearchFields_KbArticle::FULLTEXT_ARTICLE_CONTENT,
			SearchFields_KbArticle::VIRTUAL_CONTEXT_LINK,
			SearchFields_KbArticle::VIRTUAL_HAS_FIELDSET,
			SearchFields_KbArticle::VIRTUAL_WATCHERS,
		));
		
		$this->addParamsHidden(array(
			SearchFields_KbArticle::CONTENT,
			SearchFields_KbArticle::FORMAT,
			SearchFields_KbArticle::ID,
		));
		
		$this->doResetCriteria();
	}

	function getData() {
		$objects = DAO_KbArticle::search(
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
		return $this->_getDataAsObjects('DAO_KbArticle', $ids);
	}
	
	function getDataSample($size) {
		return $this->_doGetDataSample('DAO_KbArticle', $size);
	}

	function getSubtotalFields() {
		$all_fields = $this->getParamsAvailable(true);
		
		$fields = array();

		if(is_array($all_fields))
		foreach($all_fields as $field_key => $field_model) {
			$pass = false;
			
			switch($field_key) {
				// DAO
				case SearchFields_KbArticle::TOP_CATEGORY_ID:
				case SearchFields_KbArticle::FORMAT:
					$pass = true;
					break;
					
				case SearchFields_KbArticle::VIRTUAL_CONTEXT_LINK:
				case SearchFields_KbArticle::VIRTUAL_HAS_FIELDSET:
				case SearchFields_KbArticle::VIRTUAL_WATCHERS:
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
			case SearchFields_KbArticle::TOP_CATEGORY_ID:
				$topics = DAO_KbCategory::getAll();
				$label_map = array();
				foreach($topics as $topic_id => $topic) {
					if(!empty($topic->parent_id))
						continue;
					$label_map[$topic_id] = $topic->name;
				}
				$counts = $this->_getSubtotalCountForStringColumn('DAO_KbArticle', $column, $label_map, 'in', 'options[]');
				break;
				
			case SearchFields_KbArticle::FORMAT:
				$label_map = array(
					'0' => 'Plaintext',
					'1' => 'HTML',
					'2' => 'Markdown',
				);
				$counts = $this->_getSubtotalCountForStringColumn('DAO_KbArticle', $column, $label_map, '=', 'value');
				break;

			case SearchFields_KbArticle::VIRTUAL_CONTEXT_LINK:
				$counts = $this->_getSubtotalCountForContextLinkColumn('DAO_KbArticle', CerberusContexts::CONTEXT_KB_ARTICLE, $column);
				break;
				
			case SearchFields_KbArticle::VIRTUAL_HAS_FIELDSET:
				$counts = $this->_getSubtotalCountForHasFieldsetColumn('DAO_KbArticle', CerberusContexts::CONTEXT_KB_ARTICLE, $column);
				break;
				
			case SearchFields_KbArticle::VIRTUAL_WATCHERS:
				$counts = $this->_getSubtotalCountForWatcherColumn('DAO_KbArticle', $column);
				break;
			
			default:
				// Custom fields
				if('cf_' == substr($column,0,3)) {
					$counts = $this->_getSubtotalCountForCustomColumn('DAO_KbArticle', $column, 'kb.id');
				}
				
				break;
		}
		
		return $counts;
	}
	
	// [TODO] Fulltext: Comments
	// [TODO] Virtual: Topic
	
	function getQuickSearchFields() {
		$fields = array(
			'_fulltext' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_FULLTEXT,
					'options' => array('param_key' => SearchFields_KbArticle::FULLTEXT_ARTICLE_CONTENT),
				),
			'content' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_FULLTEXT,
					'options' => array('param_key' => SearchFields_KbArticle::FULLTEXT_ARTICLE_CONTENT),
				),
			'title' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_KbArticle::TITLE, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'updated' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_KbArticle::UPDATED),
				),
			'views' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_NUMBER,
					'options' => array('param_key' => SearchFields_KbArticle::VIEWS),
				),
			'watchers' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_WORKER,
					'options' => array('param_key' => SearchFields_KbArticle::VIRTUAL_WATCHERS),
				),
		);
		
		// Add searchable custom fields
		
		$fields = self::_appendFieldsFromQuickSearchContext(CerberusContexts::CONTEXT_KB_ARTICLE, $fields, null);
		
		// Engine/schema examples: Fulltext
		
		$ft_examples = array();
		
		if(false != ($schema = Extension_DevblocksSearchSchema::get(Search_KbArticle::ID))) {
			if(false != ($engine = $schema->getEngine())) {
				$ft_examples = $engine->getQuickSearchExamples($schema);
			}
		}
		
		if(!empty($ft_examples)) {
			$fields['_fulltext']['examples'] = $ft_examples;
			$fields['content']['examples'] = $ft_examples;
		}
		
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

		$categories = DAO_KbCategory::getAll();
		$tpl->assign('categories', $categories);

		switch($this->renderTemplate) {
			case 'chooser':
			default:
				$tpl->assign('view_template', 'devblocks:cerberusweb.kb::kb/view.tpl');
				$tpl->display('devblocks:cerberusweb.core::internal/views/subtotals_and_view.tpl');
				break;
		}
	}

	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);

		switch($field) {
			case SearchFields_KbArticle::TITLE:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
				
			case SearchFields_KbArticle::UPDATED:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__date.tpl');
				break;
				
//			case SearchFields_KbArticle::FORMAT:
//				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__number.tpl');
//				break;

			case SearchFields_KbArticle::VIEWS:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__number.tpl');
				break;
				
			case SearchFields_KbArticle::TOP_CATEGORY_ID:
				$topics = DAO_KbCategory::getWhere(sprintf("%s = %d",
					DAO_KbCategory::PARENT_ID,
					0
				));

				$options = array();
				
				if(is_array($topics))
				foreach($topics as $cat_id => $cat) { /* @var $cat Model_KbCategory */
					$options[$cat_id] = $cat->name;
				}
				
				$tpl->assign('options', $options);
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__list.tpl');
				break;
				
			case SearchFields_KbArticle::FULLTEXT_ARTICLE_CONTENT:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__fulltext.tpl');
				break;

			case SearchFields_KbArticle::VIRTUAL_CONTEXT_LINK:
				$contexts = Extension_DevblocksContext::getAll(false);
				$tpl->assign('contexts', $contexts);
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_link.tpl');
				break;
				
			case SearchFields_KbArticle::VIRTUAL_HAS_FIELDSET:
				$this->_renderCriteriaHasFieldset($tpl, CerberusContexts::CONTEXT_KB_ARTICLE);
				break;
				
			case SearchFields_KbArticle::VIRTUAL_WATCHERS:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_worker.tpl');
				break;
				
			default:
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
		
		switch($key) {
			case SearchFields_KbArticle::VIRTUAL_CONTEXT_LINK:
				$this->_renderVirtualContextLinks($param);
				break;
				
			case SearchFields_KbArticle::VIRTUAL_HAS_FIELDSET:
				$this->_renderVirtualHasFieldset($param);
				break;
			
			case SearchFields_KbArticle::VIRTUAL_WATCHERS:
				$this->_renderVirtualWatchers($param);
				break;
		}
	}

	function renderCriteriaParam($param) {
		$field = $param->field;
		$values = !is_array($param->value) ? array($param->value) : $param->value;

		switch($field) {
			case SearchFields_KbArticle::TOP_CATEGORY_ID:
				$topics = DAO_KbCategory::getWhere(sprintf("%s = %d",
					DAO_KbCategory::PARENT_ID,
					0
				));
				$strings = array();

				foreach($values as $val) {
					if(0==$val) {
						$strings[] = "(none)";
					} else {
						if(!isset($topics[$val]))
						continue;
						$strings[] = $topics[$val]->name;
					}
				}
				echo implode(" or ", $strings);
				break;
				
			case SearchFields_KbArticle::FORMAT:
				$strings = array();

				foreach($values as $val) {
					switch($val) {
						case 0:
							$strings[] = "Plaintext";
							break;
						case 1:
							$strings[] = "HTML";
							break;
						case 2:
							$strings[] = "Markdown";
							break;
					}
				}
				echo implode(" or ", $strings);
				break;

			default:
				parent::renderCriteriaParam($param);
				break;
		}
	}

	function getFields() {
		return SearchFields_KbArticle::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		switch($field) {
			case SearchFields_KbArticle::TITLE:
				$criteria = $this->_doSetCriteriaString($field, $oper, $value);
				break;
				
			case SearchFields_KbArticle::UPDATED:
				$criteria = $this->_doSetCriteriaDate($field, $oper);
				break;
				
			case SearchFields_KbArticle::FORMAT:
				$criteria = new DevblocksSearchCriteria($field, $oper, $value);
				break;
				
			case SearchFields_KbArticle::VIEWS:
				$criteria = new DevblocksSearchCriteria($field, $oper, $value);
				break;
				
			case SearchFields_KbArticle::TOP_CATEGORY_ID:
				@$options = DevblocksPlatform::importGPC($_REQUEST['options'], 'array', array());
				$criteria = new DevblocksSearchCriteria($field, $oper, $options);
				break;
				
			case SearchFields_KbArticle::FULLTEXT_ARTICLE_CONTENT:
				@$scope = DevblocksPlatform::importGPC($_REQUEST['scope'],'string','expert');
				$criteria = new DevblocksSearchCriteria($field, $oper, array($value,$scope));
				break;
				
			case SearchFields_KbArticle::VIRTUAL_CONTEXT_LINK:
				@$context_links = DevblocksPlatform::importGPC($_REQUEST['context_link'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$context_links);
				break;
				
			case SearchFields_KbArticle::VIRTUAL_HAS_FIELDSET:
				@$options = DevblocksPlatform::importGPC($_REQUEST['options'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$options);
				break;
				
			case SearchFields_KbArticle::VIRTUAL_WATCHERS:
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
//				case 'x':
//					break;
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
			list($objects,$null) = DAO_KbArticle::search(
				array(
					SearchFields_KbArticle::ID
				),
				$this->getParams(),
				100,
				$pg++,
				SearchFields_KbArticle::ID,
				true,
				false
			);
			 
			$ids = array_merge($ids, array_keys($objects));
			 
		} while(!empty($objects));

		$batch_total = count($ids);
		for($x=0;$x<=$batch_total;$x+=100) {
			$batch_ids = array_slice($ids,$x,100);
			
			if(!empty($change_fields))
				DAO_KbArticle::update($batch_ids, $change_fields);
			
			// Category deltas
			if(isset($do['category_delta'])) {
				DAO_KbArticle::setCategories($batch_ids, $do['category_delta'], false);
			}
			
			// Custom Fields
			self::_doBulkSetCustomFields(CerberusContexts::CONTEXT_KB_ARTICLE, $custom_fields, $batch_ids);
			
			// Scheduled behavior
			if(isset($do['behavior']) && is_array($do['behavior'])) {
				$behavior_id = $do['behavior']['id'];
				@$behavior_when = strtotime($do['behavior']['when']) or time();
				@$behavior_params = isset($do['behavior']['params']) ? $do['behavior']['params'] : array();
				
				if(!empty($batch_ids) && !empty($behavior_id))
				foreach($batch_ids as $batch_id) {
					DAO_ContextScheduledBehavior::create(array(
						DAO_ContextScheduledBehavior::BEHAVIOR_ID => $behavior_id,
						DAO_ContextScheduledBehavior::CONTEXT => CerberusContexts::CONTEXT_KB_ARTICLE,
						DAO_ContextScheduledBehavior::CONTEXT_ID => $batch_id,
						DAO_ContextScheduledBehavior::RUN_DATE => $behavior_when,
						DAO_ContextScheduledBehavior::VARIABLES_JSON => json_encode($behavior_params),
					));
				}
			}
			
			unset($batch_ids);
		}

		unset($ids);
	}
		
};