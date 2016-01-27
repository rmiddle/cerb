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

class DAO_PluginLibrary extends Cerb_ORMHelper {
	const ID = 'id';
	const PLUGIN_ID = 'plugin_id';
	const NAME = 'name';
	const AUTHOR = 'author';
	const DESCRIPTION = 'description';
	const LINK = 'link';
	const LATEST_VERSION = 'latest_version';
	const ICON_URL = 'icon_url';
	const REQUIREMENTS_JSON = 'requirements_json';
	const UPDATED = 'updated';

	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = "INSERT INTO plugin_library () VALUES ()";
		$db->ExecuteMaster($sql);
		$id = $db->LastInsertId();
		
		self::update($id, $fields);
		
		return $id;
	}
	
	static function update($ids, $fields) {
		parent::_update($ids, 'plugin_library', $fields);
	}
	
	static function updateWhere($fields, $where) {
		parent::_updateWhere('plugin_library', $fields, $where);
	}
	
	/**
	 * @param string $where
	 * @param mixed $sortBy
	 * @param mixed $sortAsc
	 * @param integer $limit
	 * @return Model_PluginLibrary[]
	 */
	static function getWhere($where=null, $sortBy=null, $sortAsc=true, $limit=null) {
		$db = DevblocksPlatform::getDatabaseService();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		// SQL
		$sql = "SELECT id, plugin_id, name, author, description, link, latest_version, icon_url, requirements_json, updated ".
			"FROM plugin_library ".
			$where_sql.
			$sort_sql.
			$limit_sql
		;
		$rs = $db->ExecuteSlave($sql);
		
		return self::_getObjectsFromResult($rs);
	}

	/**
	 * @param integer $id
	 * @return Model_PluginLibrary	 */
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
	 * @return Model_PluginLibrary[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		if(!($rs instanceof mysqli_result))
			return array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object = new Model_PluginLibrary();
			$object->id = $row['id'];
			$object->plugin_id = $row['plugin_id'];
			$object->name = $row['name'];
			$object->author = $row['author'];
			$object->description = $row['description'];
			$object->link = $row['link'];
			$object->latest_version = $row['latest_version'];
			$object->icon_url = $row['icon_url'];
			$object->updated = $row['updated'];
			
			$object->requirements_json = $row['requirements_json'];
			if(!empty($object->requirements_json))
				@$object->requirements = json_decode($object->requirements_json, true);
			
			$objects[$object->id] = $object;
		}
		
		mysqli_free_result($rs);
		
		return $objects;
	}
	
	static function flush() {
		$db = DevblocksPlatform::getDatabaseService();
		$tables = DevblocksPlatform::getDatabaseTables();
		
		$db->ExecuteMaster("DELETE FROM plugin_library");
		
		if(isset($tables['fulltext_plugin_library']))
			$db->ExecuteMaster("DELETE FROM fulltext_plugin_library");
		
		return true;
	}
	
	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		$db->ExecuteMaster(sprintf("DELETE FROM plugin_library WHERE id IN (%s)", $ids_list));
		
		return true;
	}
	
	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_PluginLibrary::getFields();
		
		list($tables,$wheres) = parent::_parseSearchParams($params, $columns, $fields, $sortBy);
		
		$select_sql = sprintf("SELECT ".
			"plugin_library.id as %s, ".
			"plugin_library.plugin_id as %s, ".
			"plugin_library.name as %s, ".
			"plugin_library.author as %s, ".
			"plugin_library.description as %s, ".
			"plugin_library.link as %s, ".
			"plugin_library.latest_version as %s, ".
			"plugin_library.icon_url as %s, ".
			"plugin_library.requirements_json as %s, ".
			"plugin_library.updated as %s ",
				SearchFields_PluginLibrary::ID,
				SearchFields_PluginLibrary::PLUGIN_ID,
				SearchFields_PluginLibrary::NAME,
				SearchFields_PluginLibrary::AUTHOR,
				SearchFields_PluginLibrary::DESCRIPTION,
				SearchFields_PluginLibrary::LINK,
				SearchFields_PluginLibrary::LATEST_VERSION,
				SearchFields_PluginLibrary::ICON_URL,
				SearchFields_PluginLibrary::REQUIREMENTS_JSON,
				SearchFields_PluginLibrary::UPDATED
			);
			
		$join_sql = "FROM plugin_library ";
		
		$has_multiple_values = false; // [TODO] Temporary when custom fields disabled
				
		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = self::_buildSortClause($sortBy, $sortAsc, $fields);
	
		$args = array(
			'join_sql' => &$join_sql,
			'where_sql' => &$where_sql,
			'tables' => &$tables,
			'has_multiple_values' => &$has_multiple_values
		);
		
		array_walk_recursive(
			$params,
			array('DAO_PluginLibrary', '_translateVirtualParameters'),
			$args
		);
		
		$result = array(
			'primary_table' => 'plugin_library',
			'select' => $select_sql,
			'join' => $join_sql,
			'where' => $where_sql,
			'has_multiple_values' => $has_multiple_values,
			'sort' => $sort_sql,
		);
		
		return $result; 
	}
	
	private static function _translateVirtualParameters($param, $key, &$args) {
		if(!is_a($param, 'DevblocksSearchCriteria'))
			return;
		
		$param_key = $param->field;
		settype($param_key, 'string');
		
		$from_index = 'plugin_library.id';
		
		switch($param_key) {
			case SearchFields_PluginLibrary::FULLTEXT_PLUGIN_LIBRARY:
				$search = Extension_DevblocksSearchSchema::get(Search_PluginLibrary::ID);
				$query = $search->getQueryFromParam($param);
				
				if(false === ($ids = $search->query($query, array()))) {
					$args['where_sql'] .= 'AND 0 ';
				
				} elseif(is_array($ids)) {
					if(empty($ids))
						$ids = array(-1);
					
					$args['where_sql'] .= sprintf('AND plugin_library.id IN (%s) ',
						implode(', ', $ids)
					);
					
				} elseif(is_string($ids)) {
					$args['join_sql'] .= sprintf("INNER JOIN %s ON (%s.id=plugin_library.id) ",
						$ids,
						$ids
					);
				}
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
			($has_multiple_values ? 'GROUP BY plugin_library.id ' : '').
			$sort_sql;
			
		if($limit > 0) {
			$rs = $db->SelectLimit($sql,$limit,$page*$limit) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs mysqli_result */
		} else {
			$rs = $db->ExecuteSlave($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs mysqli_result */
			$total = mysqli_num_rows($rs);
		}
		
		$results = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object_id = intval($row[SearchFields_PluginLibrary::ID]);
			$results[$object_id] = $row;
		}

		$total = count($results);
		
		if($withCounts) {
			// We can skip counting if we have a less-than-full single page
			if(!(0 == $page && $total < $limit)) {
				$count_sql =
					($has_multiple_values ? "SELECT COUNT(DISTINCT plugin_library.id) " : "SELECT COUNT(plugin_library.id) ").
					$join_sql.
					$where_sql;
				$total = $db->GetOneSlave($count_sql);
			}
		}
		
		mysqli_free_result($rs);
		
		return array($results,$total);
	}

	static function syncManifestsWithRepository() {
		$url = 'http://plugins.cerb6.com/plugins/list?version=' . DevblocksPlatform::strVersionToInt(APP_VERSION);
		
		$tables = DevblocksPlatform::getDatabaseTables(true);
		
		if(!isset($tables['plugin_library']))
			return false;
		
		try {
			if(!extension_loaded("curl"))
				throw new Exception("The cURL PHP extension is not installed");
			
			$ch = curl_init($url);
			curl_setopt_array($ch, array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
			));
			$json_data = curl_exec($ch);
			
		} catch(Exception $e) {
			return false;
		}
		
		if(false === ($plugins = json_decode($json_data, true)))
			return false;

		unset($json_data);
		
		// Clear local cache
		DAO_PluginLibrary::flush();
		
		// Import plugins to plugin_library
		if(is_array($plugins))
		foreach($plugins as $plugin) {
			$fields = array(
				DAO_PluginLibrary::ID => $plugin['seq'],
				DAO_PluginLibrary::PLUGIN_ID => $plugin['plugin_id'],
				DAO_PluginLibrary::NAME => $plugin['name'],
				DAO_PluginLibrary::AUTHOR => $plugin['author'],
				DAO_PluginLibrary::DESCRIPTION => $plugin['description'],
				DAO_PluginLibrary::LINK => $plugin['link'],
				DAO_PluginLibrary::ICON_URL => $plugin['icon_url'],
				DAO_PluginLibrary::UPDATED => $plugin['updated'],
				DAO_PluginLibrary::LATEST_VERSION => $plugin['latest_version'],
				DAO_PluginLibrary::REQUIREMENTS_JSON => $plugin['requirements_json'],
			);
			DAO_PluginLibrary::create($fields);
		}
		
		return count($plugins);
	}
	
	static function downloadUpdatedPluginsFromRepository() {
		if(!extension_loaded("curl") || false === ($count = DAO_PluginLibrary::syncManifestsWithRepository()))
			return false;
		
		$tables = DevblocksPlatform::getDatabaseTables(true);
		
		if(!isset($tables['plugin_library']))
			return false;
		
		if(false === ($plugin_library = DAO_PluginLibrary::getWhere()))
			return false;
		
		$plugins = DevblocksPlatform::getPluginRegistry();
		
		$updated = 0;
		
		$plugin_library_keys = array_map(function($e) {
				return $e->plugin_id;
			},
			$plugin_library
		);
		
		asort($plugin_library_keys);
		
		$plugin_library_keys = array_flip($plugin_library_keys);
		
		// Find the library plugins we have installed that need updates
		
		if(is_array($plugin_library_keys))
		foreach($plugin_library_keys as $plugin_library_key => $plugin_library_id) {
			@$local_plugin = $plugins[$plugin_library_key]; /* @var $local_plugin DevblocksPluginManifest */
			@$remote_plugin = $plugin_library[$plugin_library_id]; /* @var $remote_plugin Model_PluginLibrary */
			
			// If not installed locally, skip it.
			
			if(empty($local_plugin)) {
				unset($plugin_library_keys[$plugin_library_key]);
				continue;
			}
			
			// If we're already on the latest version, skip it.
			
			if(intval($local_plugin->version) >= $remote_plugin->latest_version) {
				unset($plugin_library_keys[$plugin_library_key]);
				continue;
			}
			
			// If we can't meet the remote plugin's new requirements, skip it.
			
			$failed_requirements = Model_PluginLibrary::testRequirements($remote_plugin->requirements);
			if(!empty($failed_requirements)) {
				unset($plugin_library_keys[$plugin_library_key]);
				continue;
			}
		}
		
		// Auto install updated plugins
		if(is_array($plugin_library_keys))
		foreach($plugin_library_keys as $plugin_library_key => $plugin_library_id) {
			@$local_plugin = $plugins[$plugin_library_key]; /* @var $local_plugin DevblocksPluginManifest */
			@$remote_plugin = $plugin_library[$plugin_library_id]; /* @var $remote_plugin Model_PluginLibrary */

			// Don't auto-update any development plugin
			
			$plugin_path = $local_plugin->getStoragePath();
			if(file_exists($plugin_path . '/.git')) {
				continue;
			}
			
			$url = sprintf("http://plugins.cerb6.com/plugins/download?plugin=%s&version=%d",
				urlencode($remote_plugin->plugin_id),
				$remote_plugin->latest_version
			);
			
			// Connect to portal for download URL
			$ch = curl_init($url);
			curl_setopt_array($ch, array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_SSL_VERIFYPEER => false,
			));
			$json_data = curl_exec($ch);
			
			if(false === ($response = json_decode($json_data, true)))
				continue;
			
			@$package_url = $response['package_url'];
			
			if(empty($package_url))
				continue;
			
			$success = DevblocksPlatform::installPluginZipFromUrl($package_url);
				
			if($success) {
				$updated++;
				
				// Reload plugin translations
				$strings_xml = $local_plugin->getStoragePath() . '/strings.xml';
				if(file_exists($strings_xml)) {
					DAO_Translation::importTmxFile($strings_xml);
				}
			}
		}
		
		if($updated) {
			DevblocksPlatform::readPlugins(false);
			DevblocksPlatform::clearCache();
		}

		// Update the full-text index every time we sync
		$schema = Extension_DevblocksSearchSchema::get(Search_PluginLibrary::ID);
		$schema->reindex();
		$schema->index(time() + 30);
		
		return array(
			'count' => $count,
			'updated' => $updated,
		);
	}
	
};

class SearchFields_PluginLibrary implements IDevblocksSearchFields {
	const ID = 'p_id';
	const PLUGIN_ID = 'p_plugin_id';
	const NAME = 'p_name';
	const AUTHOR = 'p_author';
	const DESCRIPTION = 'p_description';
	const LINK = 'p_link';
	const LATEST_VERSION = 'p_latest_version';
	const ICON_URL = 'p_icon_url';
	const REQUIREMENTS_JSON = 'p_requirements_json';
	const UPDATED = 'p_updated';
	
	// Fulltexts
	const FULLTEXT_PLUGIN_LIBRARY = 'ft_plugin_library';
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		$translate = DevblocksPlatform::getTranslationService();
		
		$columns = array(
			self::ID => new DevblocksSearchField(self::ID, 'plugin_library', 'id', $translate->_('common.id'), Model_CustomField::TYPE_NUMBER, true),
			self::PLUGIN_ID => new DevblocksSearchField(self::PLUGIN_ID, 'plugin_library', 'plugin_id', $translate->_('dao.plugin_library.plugin_id'), null, true),
			self::NAME => new DevblocksSearchField(self::NAME, 'plugin_library', 'name', $translate->_('common.name'), Model_CustomField::TYPE_SINGLE_LINE, true),
			self::AUTHOR => new DevblocksSearchField(self::AUTHOR, 'plugin_library', 'author', $translate->_('dao.cerb_plugin.author'), Model_CustomField::TYPE_SINGLE_LINE, true),
			self::DESCRIPTION => new DevblocksSearchField(self::DESCRIPTION, 'plugin_library', 'description', $translate->_('dao.cerb_plugin.description'), Model_CustomField::TYPE_MULTI_LINE, true),
			self::LINK => new DevblocksSearchField(self::LINK, 'plugin_library', 'link', $translate->_('common.url'), Model_CustomField::TYPE_URL, true),
			self::LATEST_VERSION => new DevblocksSearchField(self::LATEST_VERSION, 'plugin_library', 'latest_version', $translate->_('dao.cerb_plugin.version'), null, true),
			self::ICON_URL => new DevblocksSearchField(self::ICON_URL, 'plugin_library', 'icon_url', $translate->_('dao.plugin_library.icon_url'), null, true),
			self::REQUIREMENTS_JSON => new DevblocksSearchField(self::REQUIREMENTS_JSON, 'plugin_library', 'requirements_json', $translate->_('dao.plugin_library.requirements_json'), null, false),
			self::UPDATED => new DevblocksSearchField(self::UPDATED, 'plugin_library', 'updated', $translate->_('common.updated'), Model_CustomField::TYPE_DATE, true),
				
			self::FULLTEXT_PLUGIN_LIBRARY => new DevblocksSearchField(self::FULLTEXT_PLUGIN_LIBRARY, 'ft', 'plugin_library', $translate->_('common.search.fulltext'), 'FT', false),
		);
		
		// Fulltext indexes
		
		$columns[self::FULLTEXT_PLUGIN_LIBRARY]->ft_schema = Search_PluginLibrary::ID;
		
		// Sort by label (translation-conscious)
		DevblocksPlatform::sortObjects($columns, 'db_label');

		return $columns;
	}
};

class Search_PluginLibrary extends Extension_DevblocksSearchSchema {
	const ID = 'cerb.search.schema.plugin_library';
	
	public function getNamespace() {
		return 'plugin_library';
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
				DAO_PluginLibrary::UPDATED,
				$ptr_time,
				DAO_PluginLibrary::ID,
				$id
			);
			$plugins = DAO_PluginLibrary::getWhere($where, array(DAO_PluginLibrary::UPDATED, DAO_PluginLibrary::ID), array(true, true), 100);

			if(empty($plugins)) {
				$done = true;
				continue;
			}
			
			$last_time = $ptr_time;
			
			foreach($plugins as $plugin) { /* @var $plugin Model_PluginLibrary */
				$id = $plugin->id;
				$ptr_time = $plugin->updated;
				
				$ptr_id = ($last_time == $ptr_time) ? $id : 0;
				
				$logger->info(sprintf("[Search] Indexing %s %d...",
					$ns,
					$id
				));
				
				$doc = array(
					'id' => $plugin->plugin_id,
					'name' => $plugin->name,
					'author' => $plugin->author,
					'description' => $plugin->description,
					'url' => $plugin->link,
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

class Model_PluginLibrary {
	public $id;
	public $plugin_id;
	public $name;
	public $author;
	public $description;
	public $link;
	public $latest_version;
	public $icon_url;
	public $updated;
	public $requirements_json;
	public $requirements;
	
	// [TODO] Move this somewhere reusable
	static function testRequirements($requirements) {
		$requirements_errors = array();
		
		// Check version information
		if(
			null != (@$plugin_app_version = $requirements['app_version'])
			&& isset($plugin_app_version['min'])
			&& isset($plugin_app_version['max'])
		) {
			$app_version = DevblocksPlatform::strVersionToInt(APP_VERSION);
			
			// If APP_VERSION is below the min or above the max
			if($plugin_app_version['min'] > $app_version)
				$requirements_errors[] = 'This plugin requires a Cerb version of at least ' . DevblocksPlatform::intVersionToStr($plugin_app_version['min']) . ' and you are using ' . APP_VERSION;
			
			if($plugin_app_version['max'] < $app_version)
				$requirements_errors[] = 'This plugin was tested through Cerb version ' . DevblocksPlatform::intVersionToStr($plugin_app_version['max']) . ' and you are using ' . APP_VERSION;
			
		// If no version information is available, fail.
		} else {
			$requirements_errors[] = 'This plugin is missing requirements information in its manifest';
		}
		
		// Check PHP extensions
		if(isset($requirements['php_extensions']))
		foreach($requirements['php_extensions'] as $php_extension) {
			if(!extension_loaded($php_extension))
				$requirements_errors[] = sprintf("The '%s' PHP extension is required", $php_extension);
		}
		
		// Check dependencies
		if(isset($requirements['dependencies'])) {
			$plugins = DevblocksPlatform::getPluginRegistry();
			foreach($requirements['dependencies'] as $dependency) {
				if(!isset($plugins[$dependency])) {
					$requirements_errors[] = sprintf("The '%s' plugin is required", $dependency);
				} else if(!$plugins[$dependency]->enabled) {
					$dependency_name = isset($plugins[$dependency]) ? $plugins[$dependency]->name : $dependency;
					$requirements_errors[] = sprintf("The '%s' (%s) plugin must be enabled first", $dependency_name, $dependency);
				}
			}
		}
		
		// Status
		
		return $requirements_errors;
	}
};

class View_PluginLibrary extends C4_AbstractView implements IAbstractView_Subtotals, IAbstractView_QuickSearch {
	const DEFAULT_ID = 'plugin_library';

	function __construct() {
		$translate = DevblocksPlatform::getTranslationService();
	
		$this->id = self::DEFAULT_ID;
		$this->name = $translate->_('Plugin Library');
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_PluginLibrary::ID;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_PluginLibrary::AUTHOR,
			SearchFields_PluginLibrary::LATEST_VERSION,
			SearchFields_PluginLibrary::UPDATED,
		);
		
		$this->addColumnsHidden(array(
			SearchFields_PluginLibrary::ICON_URL,
			SearchFields_PluginLibrary::ID,
			SearchFields_PluginLibrary::REQUIREMENTS_JSON,
			SearchFields_PluginLibrary::FULLTEXT_PLUGIN_LIBRARY,
		));
		
		$this->addParamsHidden(array(
			SearchFields_PluginLibrary::ICON_URL,
			SearchFields_PluginLibrary::ID,
			SearchFields_PluginLibrary::REQUIREMENTS_JSON,
		));
		
		$this->doResetCriteria();
	}

	function getData() {
		$objects = DAO_PluginLibrary::search(
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
		return $this->_doGetDataSample('DAO_PluginLibrary', $size);
	}

	function getSubtotalFields() {
		$all_fields = $this->getParamsAvailable(true);
		
		$fields = array();

		if(is_array($all_fields))
		foreach($all_fields as $field_key => $field_model) {
			$pass = false;
			
			switch($field_key) {
				// DAO
				case SearchFields_PluginLibrary::AUTHOR:
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
			case SearchFields_PluginLibrary::AUTHOR:
				$counts = $this->_getSubtotalCountForStringColumn('DAO_PluginLibrary', $column);
				break;
		}
		
		return $counts;
	}
	
	function getQuickSearchFields() {
		$search_fields = SearchFields_PluginLibrary::getFields();
		
		$fields = array(
			'_fulltext' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_FULLTEXT,
					'options' => array('param_key' => SearchFields_PluginLibrary::FULLTEXT_PLUGIN_LIBRARY),
				),
			'author' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_PluginLibrary::AUTHOR, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'description' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_PluginLibrary::DESCRIPTION, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'pluginId' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_PluginLibrary::PLUGIN_ID, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'name' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_PluginLibrary::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'updated' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_PluginLibrary::UPDATED),
				),
			'url' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_PluginLibrary::LINK, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'version' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_VIRTUAL,
					'options' => array('param_key' => SearchFields_PluginLibrary::LATEST_VERSION),
					'examples' => array(
						'<=1.0',
						'2.0',
					),
				),
		);
		
		// Engine/schema examples: Fulltext
		
		$ft_examples = array();
		
		if(false != ($schema = Extension_DevblocksSearchSchema::get(Search_PluginLibrary::ID))) {
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
				case 'version':
					$field_keys = array(
						'version' => SearchFields_PluginLibrary::LATEST_VERSION,
					);
					
					@$field_key = $field_keys[$k];
					$oper_hint = 0;
					
					if(preg_match('#^([\!\=\>\<]+)(.*)#', $v, $matches)) {
						$oper_hint = trim($matches[1]);
						$v = trim($matches[2]);
					}
					
					$value = $oper_hint . DevblocksPlatform::strVersionToInt($v, 3);
					
					if($field_key && false != ($param = DevblocksSearchCriteria::getNumberParamFromQuery($field_key, $value)))
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

		$plugins = DevblocksPlatform::getPluginRegistry();
		$tpl->assign('plugins', $plugins);
		
		$tpl->assign('view_template', 'devblocks:cerberusweb.core::configuration/section/plugin_library/view.tpl');
		$tpl->display('devblocks:cerberusweb.core::internal/views/subtotals_and_view.tpl');
	}

	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);

		switch($field) {
			case SearchFields_PluginLibrary::PLUGIN_ID:
			case SearchFields_PluginLibrary::NAME:
			case SearchFields_PluginLibrary::AUTHOR:
			case SearchFields_PluginLibrary::DESCRIPTION:
			case SearchFields_PluginLibrary::LINK:
			case SearchFields_PluginLibrary::LATEST_VERSION:
			case SearchFields_PluginLibrary::ICON_URL:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
			case SearchFields_PluginLibrary::ID:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__number.tpl');
				break;
			case 'placeholder_bool':
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__bool.tpl');
				break;
			case SearchFields_PluginLibrary::UPDATED:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__date.tpl');
				break;
			case SearchFields_PluginLibrary::FULLTEXT_PLUGIN_LIBRARY:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__fulltext.tpl');
				break;
		}
	}

	function renderCriteriaParam($param) {
		$field = $param->field;
		$values = !is_array($param->value) ? array($param->value) : $param->value;

		switch($field) {
			case SearchFields_PluginLibrary::LATEST_VERSION:
				echo DevblocksPlatform::strEscapeHtml(DevblocksPlatform::intVersionToStr($param->value));
				break;
			default:
				parent::renderCriteriaParam($param);
				break;
		}
	}

	function getFields() {
		return SearchFields_PluginLibrary::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		switch($field) {
			case SearchFields_PluginLibrary::PLUGIN_ID:
			case SearchFields_PluginLibrary::NAME:
			case SearchFields_PluginLibrary::AUTHOR:
			case SearchFields_PluginLibrary::DESCRIPTION:
			case SearchFields_PluginLibrary::LINK:
			case SearchFields_PluginLibrary::LATEST_VERSION:
			case SearchFields_PluginLibrary::ICON_URL:
				$criteria = $this->_doSetCriteriaString($field, $oper, $value);
				break;
				
			case SearchFields_PluginLibrary::ID:
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;
				
			case SearchFields_PluginLibrary::UPDATED:
				$criteria = $this->_doSetCriteriaDate($field, $oper);
				break;
				
			case 'placeholder_bool':
				@$bool = DevblocksPlatform::importGPC($_REQUEST['bool'],'integer',1);
				$criteria = new DevblocksSearchCriteria($field,$oper,$bool);
				break;
				
			case SearchFields_PluginLibrary::FULLTEXT_PLUGIN_LIBRARY:
				@$scope = DevblocksPlatform::importGPC($_REQUEST['scope'],'string','expert');
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_FULLTEXT,array($value,$scope));
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
					//$change_fields[DAO_PluginLibrary::EXAMPLE] = 'some value';
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
			list($objects,$null) = DAO_PluginLibrary::search(
				array(),
				$this->getParams(),
				100,
				$pg++,
				SearchFields_PluginLibrary::ID,
				true,
				false
			);
			$ids = array_merge($ids, array_keys($objects));
			 
		} while(!empty($objects));

		$batch_total = count($ids);
		for($x=0;$x<=$batch_total;$x+=100) {
			$batch_ids = array_slice($ids,$x,100);
			
			DAO_PluginLibrary::update($batch_ids, $change_fields);

			// Custom Fields
			//self::_doBulkSetCustomFields(ChCustomFieldSource_PluginLibrary::ID, $custom_fields, $batch_ids);
			
			unset($batch_ids);
		}

		unset($ids);
	}
};
