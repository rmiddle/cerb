<?php
abstract class DevblocksApplication {

}

/**
 * The superclass of instanced extensions.
 *
 * @abstract
 * @ingroup plugin
 */
class DevblocksExtension {
	public $manifest = null;
	public $id  = '';

	/**
	 * Constructor
	 *
	 * @private
	 * @param DevblocksExtensionManifest $manifest
	 * @return DevblocksExtension
	 */

	function __construct($manifest=null) {
		if(empty($manifest))
			return;

		$this->manifest = $manifest;
		$this->id = $manifest->id;
	}

	function getParams() {
		return $this->manifest->getParams();
	}

	function setParam($key, $value) {
		return $this->manifest->setParam($key, $value);
	}

	function getParam($key,$default=null) {
		return $this->manifest->getParam($key, $default);
	}
	
	/**
	 * 
	 * @param string $key
	 * @return boolean
	 */
	function hasOption($key) {
		if(!$this->manifest)
			return false;
		
		return $this->manifest->hasOption($key);
	}
};

class Exception_Devblocks extends Exception {};

class Exception_DevblocksAjaxError extends Exception_Devblocks {};

class Exception_DevblocksAjaxValidationError extends Exception_Devblocks {
	private $_field_name = null;
	
	function __construct($message=null, $field_name=null) {
		parent::__construct($message);
		$this->_field_name = $field_name;
	}
	
	/**
	 * 
	 * @return string
	 */
	function getFieldName() {
		return $this->_field_name;
	}
};

interface IDevblocksHandler_Session {
	static function open($save_path, $session_name);
	static function close();
	static function read($id);
	static function write($id, $session_data);
	static function destroy($id);
	static function gc($maxlifetime);
	static function getAll();
	static function destroyAll();
};

interface IDevblocksContextPeek {
	function renderPeekPopup($context_id=0, $view_id='', $edit=false);
}

interface IDevblocksContextImport {
	function importGetKeys();
	function importKeyValue($key, $value);
	function importSaveObject(array $fields, array $custom_fields, array $meta);
}

interface IDevblocksContextProfile {
	function profileGetUrl($context_id);
}

interface IDevblocksContextAutocomplete {
	function autocomplete($term, $query=null);
}

class DevblocksMenuItemPlaceholder {
	var $label = null;
	var $key = null;
	var $l = null;
	var $children = array();
}

interface IDevblocksContextExtension {
	static function isReadableByActor($actor, $models);
	static function isWriteableByActor($actor, $models);
}

abstract class Extension_DevblocksContext extends DevblocksExtension implements IDevblocksContextExtension {
	static $_changed_contexts = array();

	static function markContextChanged($context, $context_ids) {
		if(!is_array($context_ids))
			$context_ids = array($context_ids);

		if(!isset(self::$_changed_contexts[$context]))
			self::$_changed_contexts[$context] = array();

		self::$_changed_contexts[$context] = array_merge(self::$_changed_contexts[$context], $context_ids);
	}

	static function flushTriggerChangedContextsEvents() {
		$eventMgr = DevblocksPlatform::getEventService();

		if(is_array(self::$_changed_contexts))
		foreach(self::$_changed_contexts as $context => $context_ids) {
			$eventMgr->trigger(
				new Model_DevblocksEvent(
					'context.update',
					array(
						'context' => $context,
						'context_ids' => $context_ids,
					)
				)
			);
		}

		self::$_changed_contexts = array();
	}

	/**
	 * @param boolean $as_instances
	 * @param boolean $with_options
	 * @return Extension_DevblocksContext[]
	 */
	public static function getAll($as_instances=false, $with_options=null) {
		$contexts = DevblocksPlatform::getExtensions('devblocks.context', $as_instances);

		if($as_instances)
			DevblocksPlatform::sortObjects($contexts, 'manifest->name');
		else
			DevblocksPlatform::sortObjects($contexts, 'name');

		if(!empty($with_options)) {
			if(!is_array($with_options))
				$with_options = array($with_options);

			foreach($contexts as $k => $context) {
				@$options = $context->params['options'][0];

				if(!is_array($options) || empty($options)) {
					unset($contexts[$k]);
					continue;
				}

				if(count(array_intersect(array_keys($options), $with_options)) != count($with_options))
					unset($contexts[$k]);
			}
		}

		return $contexts;
	}
	
	public static function getAliasesForAllContexts() {
		$cache = DevblocksPlatform::getCacheService();
		
		if(null !== ($results = $cache->load(DevblocksPlatform::CACHE_CONTEXT_ALIASES)))
			return $results;
		
		$contexts = self::getAll(false);
		$results = array();
		
		if(is_array($contexts))
		foreach($contexts as $ctx_id => $ctx) { /* @var $ctx DevblocksExtensionManifest */
			$ctx_aliases = self::getAliasesForContext($ctx);
			
			@$uri = $ctx_aliases['uri'];
			$results[$uri] = $ctx_id;
			
			if(isset($ctx_aliases['aliases']) && is_array($ctx_aliases['aliases']))
			foreach($ctx_aliases['aliases'] as $alias => $meta) {
				// If this alias is already defined and it's not the priority URI for this context, skip
				if(isset($results[$alias]) && $alias != $uri)
					continue;
				
				$results[$alias] = $ctx_id;
			}
		}
		
		$cache->save($results, DevblocksPlatform::CACHE_CONTEXT_ALIASES);
		return $results;
	}

	public static function getAliasesForContext(DevblocksExtensionManifest $ctx_manifest) {
		@$names = $ctx_manifest->params['names'][0];
		@$uri = $ctx_manifest->params['alias'];
		
		$results = array(
			'singular' => '',
			'plural' => '',
			'singular_short' => '',
			'plural_short' => '',
			'uri' => $uri,
			'aliases' => array(),
		);
		
		if(!empty($uri))
			$results['aliases'][$uri] = array('uri');
		
		if(is_array($names) && !empty($names))
		foreach($names as $name => $meta) {
			$name = mb_convert_case($name, MB_CASE_LOWER);
			@$meta = explode(' ', $meta) ?: array();
			
			$is_plural = in_array('plural', $meta);
			$is_short = in_array('short', $meta);
			
			if(!$is_plural && !$is_short && empty($results['singular']))
				$results['singular'] = $name;
			else if($is_plural && !$is_short && empty($results['plural']))
				$results['plural'] = $name;
			else if(!$is_plural && $is_short && empty($results['singular_short']))
				$results['singular_short'] = $name;
			else if($is_plural && $is_short && empty($results['plural_short']))
				$results['plural_short'] = $name;
			
			$results['aliases'][$name] = $meta;
		}
		
		if(empty($results['singular']))
			$results['singular'] = mb_convert_case($ctx_manifest->name, MB_CASE_LOWER);
		
		return $results;
	}
	
	/**
	 * 
	 * @param string $alias
	 * @param bool $as_instance
	 * @return Extension_DevblocksContext|DevblocksExtensionManifest
	 */
	public static function getByAlias($alias, $as_instance=false) {
		$aliases = self::getAliasesForAllContexts();
		
		@$ctx_id = $aliases[$alias];
		
		// If this is a valid context, return it
		if($ctx_id && false != ($ctx = DevblocksPlatform::getExtension($ctx_id, $as_instance))) {
			return $ctx;
		}
		
		return null;
	}

	public static function getByViewClass($view_class, $as_instance=false) {
		$contexts = self::getAll(false);

		if(is_array($contexts))
		foreach($contexts as $ctx_id => $ctx) { /* @var $ctx DevblocksExtensionManifest */
			if(isset($ctx->params['view_class']) && 0 == strcasecmp($ctx->params['view_class'], $view_class)) {
				if($as_instance) {
					return $ctx->createInstance();
				} else {
					return $ctx;
				}
			}
		}

		return null;
	}

	/**
	 * Lazy loader + cache
	 * @param string $context
	 * @return Extension_DevblocksContext
	 */
	public static function get($context) {
		static $contexts = null;

		/*
		 * Lazy load
		 */

		if(isset($contexts[$context]))
			return $contexts[$context];

		if(!isset($contexts[$context])) {
			if(null == ($ext = DevblocksPlatform::getExtension($context, true)))
				return null;

			$contexts[$context] = $ext;
			return $ext;
		}
	}
	
	static function getOwnerTree(array $contexts=['app','bot','group','role','worker']) {
		$active_worker = CerberusApplication::getActiveWorker();
		$bots = DAO_Bot::getAll();
		$groups = DAO_Group::getAll();
		$roles = DAO_WorkerRole::getAll();
		$workers = DAO_Worker::getAllActive();

		$owners = [];

		if(in_array('worker', $contexts)) {
			$item = new DevblocksMenuItemPlaceholder();
			$item->label = 'Me';
			$item->l = 'Me';
			$item->key = CerberusContexts::CONTEXT_WORKER . ':' . $active_worker->id;
			
			$owners['Me'] = $item;
		}
		
		// Apps
		
		if(in_array('app', $contexts)) {
			$item = new DevblocksMenuItemPlaceholder();
			$item->label = 'Cerb';
			$item->l = 'Cerb';
			$item->key = CerberusContexts::CONTEXT_APPLICATION . ':' . 0;
			$owners['App'] = $item;
		}
		
		// Bots
		
		if(in_array('bot', $contexts)) {
			$bots_menu = new DevblocksMenuItemPlaceholder();
			
			foreach($bots as $bot) {
				$item = new DevblocksMenuItemPlaceholder();
				$item->label = $bot->name;
				$item->l = $bot->name;
				$item->key = CerberusContexts::CONTEXT_BOT . ':' . $bot->id;
				$bots_menu->children[$item->l] = $item;
			}
			
			$owners['Bot'] = $bots_menu;
		}
		
		// Groups
		
		if(in_array('group', $contexts)) {
			$groups_menu = new DevblocksMenuItemPlaceholder();
			
			foreach($groups as $group) {
				$item = new DevblocksMenuItemPlaceholder();
				$item->label = $group->name;
				$item->l = $item->label;
				$item->key = CerberusContexts::CONTEXT_GROUP . ':' . $group->id;
				$groups_menu->children[$item->l] = $item;
			}
			
			$owners['Group'] = $groups_menu;
		}
		
		// Roles
		
		if(in_array('role', $contexts)) {
			$roles_menu = new DevblocksMenuItemPlaceholder();
			
			foreach($roles as $role) {
				$item = new DevblocksMenuItemPlaceholder();
				$item->label = $role->name;
				$item->l = $item->label;
				$item->key = CerberusContexts::CONTEXT_ROLE . ':' . $role->id;
				$roles_menu->children[$item->l] = $item;
			}
			
			$owners['Role'] = $roles_menu;
		}
		
		// Workers
		
		if(in_array('worker', $contexts)) {
			$workers_menu = new DevblocksMenuItemPlaceholder();
			
			foreach($workers as $worker) {
				$item = new DevblocksMenuItemPlaceholder();
				$item->label = $worker->getName();
				$item->l = $item->label;
				$item->key = CerberusContexts::CONTEXT_WORKER . ':' . $worker->id;
				$workers_menu->children[$item->l] = $item;
			}
			
			$owners['Worker'] = $workers_menu;
		}
		
		return $owners;
	}
	
	static function getPlaceholderTree($labels, $label_separator=' ', $key_separator=' ') {
		$keys = new DevblocksMenuItemPlaceholder();
		
		// Tokenize the placeholders
		foreach($labels as $k => &$label) {
			$label = trim($label);
			
			$parts = explode($label_separator, $label);
			
			$ptr =& $keys->children;
			
			while($part = array_shift($parts)) {
				if(!isset($ptr[$part])) {
					$ptr[$part] = new DevblocksMenuItemPlaceholder();
				}
				
				$ptr =& $ptr[''.$part]->children;
			}
		}
		
		// Convert the flat tokens into a tree
		$forward_recurse = function(&$node, $node_key, &$stack=null) use (&$keys, &$forward_recurse, &$labels, $label_separator) {
			if(is_null($stack))
				$stack = array();
			
			if(!empty($node_key))
				array_push($stack, ''.$node_key);

			$label = implode($label_separator, $stack);
			
			if(false != ($key = array_search($label, $labels))) {
				$node->label = $label;
				$node->key = $key;
				$node->l = $node_key;
			}
			
			if(is_array($node->children))
			foreach($node->children as $k => &$n) {
				$forward_recurse($n, $k, $stack);
			}
			
			array_pop($stack);
		};
		
		$forward_recurse($keys, '');
		
		$condense = function(&$node, $key=null, &$parent=null) use (&$condense, $label_separator, $key_separator) {
			// If this node has exactly one child
			if(is_array($node->children) && 1 == count($node->children) && $parent && is_null($node->label)) {
				reset($node->children);
				
				// Replace the current node with its only child
				$k = key($node->children);
				$n = array_pop($node->children);
				if(is_object($n))
					$n->l = $key . $label_separator . $n->l;
				
				// Deconstruct our parent
				$keys = array_keys($parent->children);
				$vals = array_values($parent->children);
				
				// Replace this node's key and value in the parent
				$idx = array_search($key, $keys);
				$keys[$idx] = $key.$key_separator.$k;
				$vals[$idx] = $n;
				
				// Reconstruct the parent
				$parent->children = array_combine($keys, $vals);
			}
			
			// If this node still has children, recurse into them
			if(is_array($node->children))
			foreach($node->children as $k => &$n)
				$condense($n, $k, $node);
		};
		$condense($keys);
		
		return $keys->children;
	}

	abstract function getRandom();
	abstract function getMeta($context_id);
	abstract function getContext($object, &$token_labels, &$token_values, $prefix=null);
	
	function getDefaultProperties() {
		return array();
	}
	
	/**
	 * @return array
	 */
	function getCardProperties() {
		// Load cascading properties
		$properties = DevblocksPlatform::getPluginSetting('cerberusweb.core', 'card:' . $this->id, array(), true);
		
		if(empty($properties))
			$properties = $this->getDefaultProperties();
		
		return $properties;
	}

	/*
	 * @return Cerb_ORMHelper
	 */
	function getDaoClass() {
		$class = str_replace('Context_','DAO_', get_called_class());
		
		if(!class_exists($class))
			return false;
		
		return $class;
	}
	
	/*
	 * @return DevblocksSearchFields
	 */
	function getSearchClass() {
		$class = str_replace('Context_','SearchFields_', get_called_class());
		
		if(!class_exists($class))
			return false;
		
		return $class;
	}

	function getViewClass() {
		$class = str_replace('Context_','View_', get_called_class());
		
		if(!class_exists($class))
			return false;
		
		return $class;
	}

	function getModelObjects(array $ids) {
		$ids = DevblocksPlatform::importVar($ids, 'array:integer');
		$models = array();

		if(null == ($dao_class = $this->getDaoClass()))
			return $models;

		if(method_exists($dao_class, 'getIds')) {
			$models = $dao_class::getIds($ids);

		} elseif(method_exists($dao_class, 'getWhere')) {
			$where = sprintf("id IN (%s)",
				implode(',', $ids)
			);

			// Get without sorting (optimization, no file sort)
			$models = $dao_class::getWhere($where, null);
		}

		return $models;
	}

	public function formatDictionaryValue($key, DevblocksDictionaryDelegate $dict) {
		$translate = DevblocksPlatform::getTranslationService();

		@$type = $dict->_types[$key];
		$value = $dict->$key;

		switch($type) {
			case 'context_url':
				// Try to find the context+id pair for this key
				$parts = explode('_', str_replace('__','_',$key));

				// Start with the longest sub-token, and decrease until found
				while(array_pop($parts)) {
					$prefix = implode('_', $parts);
					$test_key = $prefix . '__context';

					@$context = $dict->$test_key;

					if(!empty($context)) {
						$id_key = $prefix . '_id';
						$context_id = $dict->$id_key;

						if(!empty($context_id)) {
							$context_url = sprintf("ctx://%s:%d/%s",
								$context,
								$context_id,
								$value
							);
							return $context_url;

						} else {
							return $value;

						}
					}
				}

				break;

			case 'percent':
				if(is_float($value)) {
					$value = sprintf("%0.2f%%",
						($value * 100)
					);

				} elseif(is_numeric($value)) {
					$value = sprintf("%d%%",
						$value
					);
				}
				break;

			case 'size_bytes':
				$value = DevblocksPlatform::strPrettyBytes($value);
				break;

			case 'time_secs':
				//$value = DevblocksPlatform::strPrettyTime($value, true);
				break;

			case 'time_mins':
				$secs = intval($value) * 60;
				$value = DevblocksPlatform::strSecsToString($secs, 2);
				break;

			case Model_CustomField::TYPE_CHECKBOX:
				$value = (!empty($value)) ? $translate->_('common.yes') : $translate->_('common.no');
				break;

			case Model_CustomField::TYPE_DATE:
				$value = DevblocksPlatform::strPrettyTime($value);
				break;
		}

		return $value;
	}

	/**
	 *
	 * @param string $view_id
	 * @return C4_AbstractView
	 */
	public function getSearchView($view_id=null) {
		if(empty($view_id)) {
			$view_id = sprintf("search_%s",
				str_replace('.','_',DevblocksPlatform::strToPermalink($this->id,'_'))
			);
		}

		if(null == ($view = C4_AbstractViewLoader::getView($view_id))) {
			if(null == ($view = $this->getChooserView($view_id))) /* @var $view C4_AbstractViewModel */
				return;
		}

		$view->name = 'Search Results';
		$view->renderFilters = false;
		$view->is_ephemeral = false;

		return $view;
	}

	abstract function getChooserView($view_id=null);
	abstract function getView($context=null, $context_id=null, $options=array(), $view_id=null);

	function lazyLoadContextValues($token, $dictionary) { return array(); }

	protected function _importModelCustomFieldsAsValues($model, $token_values) {
		@$custom_fields = $model->custom_fields;

		if($custom_fields) {
			$custom_values = $this->_lazyLoadCustomFields(
				'custom_',
				$token_values['_context'],
				$token_values['id'],
				$custom_fields
			);
			$token_values = array_merge($token_values, $custom_values);
		}

		return $token_values;
	}
	
	protected function _lazyLoadLinks($context, $context_id) {
		$results = DAO_ContextLink::getAllContextLinks($context, $context_id);
		$links = array();
		$token_values['links'] = array();
		
		foreach($results as $result) {
			if($result->context == CerberusContexts::CONTEXT_CUSTOM_FIELDSET)
				continue;
			
			if(!isset($token_values['links'][$result->context]))
				$token_values['links'][$result->context] = array();
			
			$token_values['links'][$result->context][] = intval($result->context_id);
		}
		
		return $token_values;
	}

	protected function _lazyLoadCustomFields($token, $context, $context_id, $field_values=null) {
		$fields = DAO_CustomField::getByContext($context);
		$token_values['custom'] = array();

		// If (0 == $context_id), we need to null out all the fields and return w/o queries
		if(empty($context_id))
			return $token_values;

		// If we weren't passed values
		if(is_null($field_values)) {
			$results = DAO_CustomFieldValue::getValuesByContextIds($context, $context_id);
			if(is_array($results))
				$field_values = array_shift($results);
			unset($results);
		}

		foreach(array_keys($fields) as $cf_id) {
			$token_values['custom'][$cf_id] = '';
			$token_values['custom_' . $cf_id] = '';

			if(isset($field_values[$cf_id])) {
				// The literal value
				$token_values['custom'][$cf_id] = $field_values[$cf_id];

				// Stringify
				if(is_array($field_values[$cf_id])) {
					$token_values['custom_'.$cf_id] = implode(', ', $field_values[$cf_id]);
				} elseif(is_string($field_values[$cf_id])) {
					$token_values['custom_'.$cf_id] = $field_values[$cf_id];
				}
			}

			switch($fields[$cf_id]->type) {
				case Model_CustomField::TYPE_LINK:
					@$token_values['custom_' . $cf_id . '_id'] = $field_values[$cf_id];
					@$token_values['custom_' . $cf_id . '__context'] = $fields[$cf_id]->params['context'];

					if(!isset($token_values[$token])) {
						$dict = new DevblocksDictionaryDelegate($token_values);
						$dict->$token;
						$token_values = $dict->getDictionary();
					}
					break;
			}
		}

		return $token_values;
	}

	protected function _getTokenLabelsFromCustomFields($fields, $prefix) {
		$context_stack = CerberusContexts::getStack();

		$labels = array();
		$fieldsets = DAO_CustomFieldset::getAll();

		if(is_array($fields))
		foreach($fields as $cf_id => $field) {
			$fieldset = $field->custom_fieldset_id ? @$fieldsets[$field->custom_fieldset_id] : null;

			$suffix = '';

			// Control infinite recursion
			if(count($context_stack) > 1 && $field->type == Model_CustomField::TYPE_LINK)
				continue;

			switch($field->type) {
				case Model_CustomField::TYPE_LINK:
					if(!isset($field->params['context']))
						break;

					$field_prefix = $prefix . ($fieldset ? ($fieldset->name . ' ') : '') . $field->name . ' ';
					$suffix = ' ID';

					CerberusContexts::getContext($field->params['context'], null, $merge_labels, $merge_values, $field_prefix, true);

					// Unset redundant id
					unset($merge_labels['id']);

					if(is_array($merge_labels))
					foreach($merge_labels as $label_key => $label) {
						$labels['custom_'.$cf_id.'_'.$label_key] = $label;
					}

					break;
			}

			$labels['custom_'.$cf_id] = sprintf("%s%s%s%s",
				$prefix,
				($fieldset ? ($fieldset->name . ':') : ''),
				$field->name,
				$suffix
			);

		}

		return $labels;
	}

	protected function _getTokenTypesFromCustomFields($fields, $prefix) {
		$context_stack = CerberusContexts::getStack();

		$types = array();
		$fieldsets = DAO_CustomFieldset::getAll();

		if(is_array($fields))
		foreach($fields as $cf_id => $field) {
			$fieldset = $field->custom_fieldset_id ? @$fieldsets[$field->custom_fieldset_id] : null;

			// Control infinite recursion
			if(count($context_stack) > 1 && $field->type == Model_CustomField::TYPE_LINK)
				continue;

			$types['custom_'.$cf_id] = $field->type;

			switch($field->type) {
				case Model_CustomField::TYPE_LINK:
					if(!isset($field->params['context']))
						break;

					// [TODO] This infinitely recurses if you do task->task
					CerberusContexts::getContext($field->params['context'], null, $merge_labels, $merge_values, null, true);

					if(is_array($merge_values['_types']))
					foreach($merge_values['_types'] as $type_key => $type) {
						$types['custom_'.$cf_id.'_'.$type_key] = $type;
					}

					break;
			}
		}

		return $types;
	}

	protected function _getImportCustomFields($fields, &$keys) {
		if(is_array($fields))
		foreach($fields as $token => $cfield) {
			if('cf_' != substr($token, 0, 3))
				continue;

			$cfield_id = intval(substr($token, 3));

			$keys['cf_' . $cfield_id] = array(
				'label' => $cfield->db_label,
				'type' => $cfield->type,
				'param' => $cfield->token,
			);
		}

		return true;
	}
	
	static function getTimelineComments($context, $context_id, $is_ascending=true) {
		$timeline = array();
		
		if(false != ($comments = DAO_Comment::getByContext($context, $context_id)))
			$timeline = array_merge($timeline, $comments);
		
		usort($timeline, function($a, $b) use ($is_ascending) {
			if($a instanceof Model_Comment) {
				$a_time = intval($a->created);
			} else {
				$a_time = 0;
			}
			
			if($b instanceof Model_Comment) {
				$b_time = intval($b->created);
			} else {
				$b_time = 0;
			}
			
			if($a_time > $b_time) {
				return ($is_ascending) ? 1 : -1;
			} else if ($a_time < $b_time) {
				return ($is_ascending) ? -1 : 1;
			} else {
				return 0;
			}
		});
		
		return $timeline;
	}
};

abstract class Extension_DevblocksEvent extends DevblocksExtension {
	const POINT = 'devblocks.event';

	private $_labels = array();
	private $_types = array();
	private $_values = array();
	
	private $_conditions_cache = array();
	private $_conditions_extensions_cache = array();

	public static function getAll($as_instances=false) {
		$events = DevblocksPlatform::getExtensions('devblocks.event', $as_instances);
		if($as_instances)
			DevblocksPlatform::sortObjects($events, 'manifest->name');
		else
			DevblocksPlatform::sortObjects($events, 'name');
		return $events;
	}

	public static function get($id, $as_instance=true) {
		$events = self::getAll(false);

		if(isset($events[$id])) {
			return $events[$id]->createInstance();
		}

		return null;
	}

	public static function getByContext($context, $as_instances=false) {
		$events = self::getAll(false);

		foreach($events as $event_id => $event) {
			if(isset($event->params['contexts'][0])) {
				$contexts = $event->params['contexts'][0]; // keys
				if(!isset($contexts[$context]))
					unset($events[$event_id]);
			}
		}

		if($as_instances) {
			foreach($events as $event_id => $event)
				$events[$event_id] = $event->createInstance();
		}

		return $events;
	}

	protected function _importLabelsTypesAsConditions($labels, $types) {
		$conditions = array();

		foreach($types as $token => $type) {
			if(!isset($labels[$token]))
				continue;

			// [TODO] This could be implemented
			if($type == 'context_url')
				continue;

			$label = $labels[$token];

			// Strip any modifiers
			if(false !== ($pos = strpos($token,'|')))
				$token = substr($token,0,$pos);

			$conditions[$token] = array('label' => $label, 'type' => $type);
		}

		foreach($labels as $token => $label) {
			if(preg_match('#.*?_{0,1}custom_(\d+)$#', $token, $matches)) {

				if(null == ($cfield = DAO_CustomField::get($matches[1])))
					continue;

				// [TODO] Can we load these option a different way so this foreach isn't needed?
				switch($cfield->type) {
					case Model_CustomField::TYPE_DROPDOWN:
					case Model_CustomField::TYPE_MULTI_CHECKBOX:
						$conditions[$token]['options'] = @$cfield->params['options'];
						break;
				}
			}
		}

		return $conditions;
	}

	abstract function setEvent(Model_DevblocksEvent $event_model=null, Model_TriggerEvent $trigger);

	function setLabels($labels) {
		asort($labels);
		$this->_labels = $labels;
	}

	function setValues($values) {
		$this->_values = $values;

		if(isset($values['_types']))
			$this->_setTypes($values['_types']);
	}

	function getValues() {
		return $this->_values;
	}

	function getLabels(Model_TriggerEvent $trigger = null) {
		// Lazy load
		if(empty($this->_labels))
			$this->setEvent(null, $trigger);

		if(null != $trigger && !empty($trigger->variables)) {
			foreach($trigger->variables as $k => $var) {
				$this->_labels[$k] = '(variable) ' . $var['label'];
			}
		}

		// Sort
		asort($this->_labels);

		return $this->_labels;
	}

	private function _setTypes($types) {
		$this->_types = $types;
	}

	function getTypes() {
		if(!isset($this->_values['_types']))
			return array();

		return $this->_values['_types'];
	}

	function getValuesContexts($trigger) {
		$contexts_to_macros = DevblocksEventHelper::getContextToMacroMap();
		$macros_to_contexts = array_flip($contexts_to_macros);

		// Custom fields

		$cfields = array();
		$custom_fields = DAO_CustomField::getAll();
		$vars = array();

		// cfields
		$labels = $this->getLabels($trigger);

		if(is_array($labels))
		foreach($labels as $token => $label) {
			if(preg_match('#.*?_{0,1}custom_(\d+)$#', $token, $matches)) {
				@$cfield_id = $matches[1];

				if(empty($cfield_id))
					continue;

				if(!isset($custom_fields[$cfield_id]))
					continue;

				switch($custom_fields[$cfield_id]->type) {
					case Model_CustomField::TYPE_LINK:
						@$link_context = $custom_fields[$cfield_id]->params['context'];

						if(empty($link_context))
							break;

						$cfields[$token] = array(
							'label' => $label,
							'context' => $link_context,
						);

						// Include deep context links from this custom field link
						CerberusContexts::getContext($link_context, null, $link_labels, $link_values, null, true);

						foreach($labels as $link_token => $link_label) {
							if(preg_match('#^'.$token.'_(.*?)__label$#', $link_token, $link_matches)) {
								@$link_key = $link_matches[1];

								if(empty($link_key))
									continue;

								if(isset($link_values[$link_key.'__context'])) {
									$cfields[$token . '_' . $link_key . '_id'] = array(
										'label' => $link_label,
										'context' => $link_values[$link_key.'__context'],
									);
								}
							}
						}

						break;

					case Model_CustomField::TYPE_WORKER:
						$cfields[$token] = array(
							'label' => $label,
							'context' => CerberusContexts::CONTEXT_WORKER,
						);
						break;

					default:
						continue;
						break;
				}
			}
		}

		// Behavior Vars
		$vars = DevblocksEventHelper::getVarValueToContextMap($trigger);

		return array_merge($cfields, $vars);
	}

	function renderEventParams(Model_TriggerEvent $trigger=null) {}

	function getConditions($trigger) {
		if(isset($this->_conditions_cache[$trigger->id])) {
			return $this->_conditions_cache[$trigger->id];
		}
		
		$conditions = array(
			'_calendar_availability' => array('label' => 'Calendar availability', 'type' => ''),
			'_custom_script' => array('label' => 'Custom script', 'type' => ''),
			'_day_of_week' => array('label' => 'Calendar day of week', 'type' => ''),
			'_month_of_year' => array('label' => 'Calendar month of year', 'type' => ''),
			'_time_of_day' => array('label' => 'Calendar time of day', 'type' => ''),
		);
		$custom = $this->getConditionExtensions($trigger);

		if(!empty($custom) && is_array($custom))
			$conditions = array_merge($conditions, $custom);

		// Trigger variables
		if(is_array($trigger->variables))
		foreach($trigger->variables as $key => $var) {
			$conditions[$key] = array(
				'label' => '(variable) ' . $var['label'],
				'type' => $var['type']
			);

			if($var['type'] == Model_CustomField::TYPE_DROPDOWN)
				@$conditions[$key]['options'] = DevblocksPlatform::parseCrlfString($var['params']['options']);
		}

		// Plugins
		// [TODO] This should filter by event type
		$manifests = Extension_DevblocksEventCondition::getAll(false);
		foreach($manifests as $manifest) {
			$conditions[$manifest->id] = array('label' => $manifest->params['label']);
		}

		DevblocksPlatform::sortObjects($conditions, '[label]');

		$this->_conditions_cache[$trigger->id] = $conditions;
		return $conditions;
	}

	abstract function getConditionExtensions(Model_TriggerEvent $trigger);
	abstract function renderConditionExtension($token, $as_token, $trigger, $params=array(), $seq=null);
	abstract function runConditionExtension($token, $as_token, $trigger, $params, DevblocksDictionaryDelegate $dict);

	function renderCondition($token, $trigger, $params=array(), $seq=null) {
		$conditions = $this->getConditions($trigger);
		$condition_extensions = $this->getConditionExtensions($trigger);

		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('params', $params);

		if(!is_null($seq))
			$tpl->assign('namePrefix','condition'.$seq);

		switch($token) {
			case '_calendar_availability':
				// Get readable by VA
				$calendars = DAO_Calendar::getReadableByActor(array(CerberusContexts::CONTEXT_BOT, $trigger->bot_id));
				$tpl->assign('calendars', $calendars);

				return $tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_calendar_availability.tpl');
				break;

			case '_custom_script':
				return $tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_custom_script.tpl');
				break;

			case '_month_of_year':
				return $tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_month_of_year.tpl');
				break;

			case '_day_of_week':
				return $tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_day_of_week.tpl');
				break;
				
			case '_time_of_day':
				return $tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_time_of_day.tpl');
				break;

			default:
				if(null != (@$condition = $conditions[$token])) {
					// Automatic types
					switch(@$condition['type']) {
						case Model_CustomField::TYPE_CHECKBOX:
							return $tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_bool.tpl');
							break;
						case Model_CustomField::TYPE_DATE:
							return $tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_date.tpl');
							break;
						case Model_CustomField::TYPE_MULTI_LINE:
						case Model_CustomField::TYPE_SINGLE_LINE:
						case Model_CustomField::TYPE_URL:
						case 'phone':
							return $tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_string.tpl');
							break;
						case Model_CustomField::TYPE_NUMBER:
						//case 'percent':
						case 'id':
						case 'size_bytes':
						case 'time_mins':
						case 'time_secs':
							return $tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_number.tpl');
							break;
						case Model_CustomField::TYPE_DROPDOWN:
						case Model_CustomField::TYPE_MULTI_CHECKBOX:
							$tpl->assign('condition', $condition);
							return $tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_dropdown.tpl');
							break;
						case Model_CustomField::TYPE_WORKER:
							$tpl->assign('workers', DAO_Worker::getAll());
							return $tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_worker.tpl');
							break;
						default:
							if(@substr($condition['type'],0,4) == 'ctx_') {
								return $tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_number.tpl');

							} else {
								// Custom
								if(isset($condition_extensions[$token])) {
									return $this->renderConditionExtension($token, $token, $trigger, $params, $seq);

								} else {
									// Plugins
									if(null != ($ext = DevblocksPlatform::getExtension($token, true))
										&& $ext instanceof Extension_DevblocksEventCondition) { /* @var $ext Extension_DevblocksEventCondition */
										return $ext->render($this, $trigger, $params, $seq);
									}
								}
							}

							break;
					}
				}
				break;
		}
	}

	function runCondition($token, $trigger, $params, DevblocksDictionaryDelegate $dict) {
		$logger = DevblocksPlatform::getConsoleLog('Bot');
		$conditions = $this->getConditions($trigger);
		
		// Cache the extensions
		if(!isset($this->_conditions_extensions_cache[$trigger->id])) {
			$this->_conditions_extensions_cache[$trigger->id] = $this->getConditionExtensions($trigger);
		}
		
		$extensions = @$this->_conditions_extensions_cache[$trigger->id] ?: array();
		
		$not = false;
		$pass = true;

		$now = time();

		// Overload the current time? (simulate)
		if(isset($dict->_current_time)) {
			$now = $dict->_current_time;
		}

		$logger->info('');
		$logger->info(sprintf("Checking condition `%s`...", $token));

		// Built-in conditions
		switch($token) {
			case '_calendar_availability':
				if(false == (@$calendar_id = $params['calendar_id']))
					return false;

				@$is_available = $params['is_available'];
				@$from = $params['from'];
				@$to = $params['to'];

				if(false == ($calendar = DAO_Calendar::get($calendar_id)))
					return false;

				@$cal_from = strtotime("today", strtotime($from));
				@$cal_to = strtotime("tomorrow", strtotime($to));

				$calendar_events = $calendar->getEvents($cal_from, $cal_to);
				$availability = $calendar->computeAvailability($cal_from, $cal_to, $calendar_events);

				$pass = ($is_available == $availability->isAvailableBetween(strtotime($from), strtotime($to)));
				break;

			case '_custom_script':
				@$tpl = DevblocksPlatform::importVar($params['tpl'],'string','');

				$tpl_builder = DevblocksPlatform::getTemplateBuilder();
				$value = $tpl_builder->build($tpl, $dict);

				if(false === $value) {
					$logger->error(sprintf("[Script] Syntax error:\n\n%s",
						implode("\n", $tpl_builder->getErrors())
					));
					return false;
				}

				$value = trim($value);

				@$not = (substr($params['oper'],0,1) == '!');
				@$oper = ltrim($params['oper'],'!');
				@$param_value = $params['value'];

				$logger->info(sprintf("Script: `%s` %s%s `%s`",
					$value,
					(!empty($not) ? 'not ' : ''),
					$oper,
					$param_value
				));

				switch($oper) {
					case 'is':
						$pass = (0==strcasecmp($value,$param_value));
						break;
					case 'like':
						$regexp = DevblocksPlatform::strToRegExp($param_value);
						$pass = @preg_match($regexp, $value);
						break;
					case 'contains':
						$pass = (false !== stripos($value, $param_value)) ? true : false;
						break;
					case 'regexp':
						$pass = @preg_match($param_value, $value);
						break;
				}
				break;

			case '_month_of_year':
				$not = (substr($params['oper'],0,1) == '!');
				$oper = ltrim($params['oper'],'!');

				@$months = DevblocksPlatform::importVar($params['month'],'array',array());

				switch($oper) {
					case 'is':
						$month = date('n', $now);
						$pass = in_array($month, $months);
						break;
				}
				break;
			case '_day_of_week':
				$not = (substr($params['oper'],0,1) == '!');
				$oper = ltrim($params['oper'],'!');

				@$days = DevblocksPlatform::importVar($params['day'],'array',array());

				switch($oper) {
					case 'is':
						$today = date('N', $now);
						$pass = in_array($today, $days);
						break;
				}
				break;
			case '_time_of_day':
				$not = (substr($params['oper'],0,1) == '!');
				$oper = ltrim($params['oper'],'!');

				@$from = DevblocksPlatform::importVar($params['from'],'string','now');
				@$to = DevblocksPlatform::importVar($params['to'],'string','now');

				switch($oper) {
					case 'between':
						@$from = strtotime($from, $now);
						@$to = strtotime($to, $now);
						if($to < $from)
							$to += 86400; // +1 day
						$pass = ($now >= $from && $now <= $to) ? true : false;
						break;
				}
				break;

			default:
				// Operators
				if(null != (@$condition = $conditions[$token])) {
					if(null == (@$value = $dict->$token)) {
						$value = '';
					}

					// Automatic types
					switch(@$condition['type']) {
						case Model_CustomField::TYPE_CHECKBOX:
							$bool = intval($params['bool']);
							$pass = !empty($value) == $bool;
							$logger->info(sprintf("Checkbox: %s = %s",
								(!empty($value) ? 'true' : 'false'),
								(!empty($bool) ? 'true' : 'false')
							));
							break;

						case Model_CustomField::TYPE_DATE:
							$not = (substr($params['oper'],0,1) == '!');
							$oper = ltrim($params['oper'],'!');
							$oper = 'between';

							$from = strtotime($params['from']);
							$to = strtotime($params['to']);

							$logger->info(sprintf("Date: `%s` %s%s `%s` and `%s`",
								DevblocksPlatform::strPrettyTime($value),
								(!empty($not) ? 'not ' : ''),
								$oper,
								DevblocksPlatform::strPrettyTime($from),
								DevblocksPlatform::strPrettyTime($to)
							));

							switch($oper) {
								case 'between':
									if($to < $from)
										$to += 86400; // +1 day
									$pass = ($value >= $from && $value <= $to) ? true : false;
									break;
							}
							break;

						case Model_CustomField::TYPE_MULTI_LINE:
						case Model_CustomField::TYPE_SINGLE_LINE:
						case Model_CustomField::TYPE_URL:
							$not = (substr($params['oper'],0,1) == '!');
							$oper = ltrim($params['oper'],'!');
							@$param_value = $params['value'];

							$logger->info(sprintf("Text: `%s` %s%s `%s`",
								$value,
								(!empty($not) ? 'not ' : ''),
								$oper,
								$param_value
							));

							switch($oper) {
								case 'is':
									$pass = (0==strcasecmp($value,$param_value));
									break;
								case 'like':
									$regexp = DevblocksPlatform::strToRegExp($param_value);
									$pass = @preg_match($regexp, $value);
									break;
								case 'contains':
									$pass = (false !== stripos($value, $param_value)) ? true : false;
									break;
								case 'regexp':
									$pass = @preg_match($param_value, $value);
									break;
							}

							// Handle operator negation
							break;

						case Model_CustomField::TYPE_NUMBER:
							$not = (substr($params['oper'],0,1) == '!');
							$oper = ltrim($params['oper'],'!');
							@$desired_value = intval($params['value']);

							$logger->info(sprintf("Number: %d %s%s %d",
								$value,
								(!empty($not) ? 'not ' : ''),
								$oper,
								$desired_value
							));

							switch($oper) {
								case 'is':
									$pass = intval($value)==$desired_value;
									break;
								case 'gt':
									$pass = intval($value) > $desired_value;
									break;
								case 'lt':
									$pass = intval($value) < $desired_value;
									break;
							}
							break;

						case Model_CustomField::TYPE_DROPDOWN:
							$not = (substr($params['oper'],0,1) == '!');
							$oper = ltrim($params['oper'],'!');
							$desired_values = isset($params['values']) ? $params['values'] : array();

							$logger->info(sprintf("`%s` %s%s `%s`",
								$value,
								(!empty($not) ? 'not ' : ''),
								$oper,
								implode('; ', $desired_values)
							));

							if(!isset($desired_values) || !is_array($desired_values)) {
								$pass = false;
								break;
							}

							switch($oper) {
								case 'in':
									$pass = false;
									if(in_array($value, $desired_values)) {
										$pass = true;
									}
									break;
							}
							break;

						case Model_CustomField::TYPE_MULTI_CHECKBOX:
							$not = (substr($params['oper'],0,1) == '!');
							$oper = ltrim($params['oper'],'!');

							if(preg_match("#(.*?_custom)_(\d+)#", $token, $matches) && 3 == count($matches)) {
								$value_token = $matches[1];
								$value_field = $dict->$value_token;
								@$value = $value_field[$matches[2]];
							}

							if(!is_array($value) || !isset($params['values']) || !is_array($params['values'])) {
								$pass = false;
								break;
							}

							$logger->info(sprintf("Multi-checkbox: `%s` %s%s `%s`",
								implode('; ', $params['values']),
								(!empty($not) ? 'not ' : ''),
								$oper,
								implode('; ', $value)
							));

							switch($oper) {
								case 'is':
									$pass = true;
									foreach($params['values'] as $v) {
										if(!isset($value[$v])) {
											$pass = false;
											break;
										}
									}
									break;
								case 'in':
									$pass = false;
									foreach($params['values'] as $v) {
										if(isset($value[$v])) {
											$pass = true;
											break;
										}
									}
									break;
							}
							break;

						case Model_CustomField::TYPE_WORKER:
							@$worker_ids = $params['worker_id'];
							$not = (substr($params['oper'],0,1) == '!');
							$oper = ltrim($params['oper'],'!');

							if(!is_array($value))
								$value = empty($value) ? array() : array($value);

							if(is_null($worker_ids))
								$worker_ids = array();

							if(empty($worker_ids) && empty($value)) {
								$pass = true;
								break;
							}

							switch($oper) {
								case 'in':
									$pass = false;
									foreach($worker_ids as $v) {
										if(in_array($v, $value)) {
											$pass = true;
											break;
										}
									}
									break;
							}
							break;

						default:
							if(@substr($condition['type'],0,4) == 'ctx_') {
								$count = (isset($dict->$token) && is_array($dict->$token)) ? count($dict->$token) : 0;

								$not = (substr($params['oper'],0,1) == '!');
								$oper = ltrim($params['oper'],'!');
								@$desired_count = intval($params['value']);

								$logger->info(sprintf("Count: %d %s%s %d",
									$count,
									(!empty($not) ? 'not ' : ''),
									$oper,
									$desired_count
								));

								switch($oper) {
									case 'is':
										$pass = $count==$desired_count;
										break;
									case 'gt':
										$pass = $count > $desired_count;
										break;
									case 'lt':
										$pass = $count < $desired_count;
										break;
								}

							} else {
								if(isset($extensions[$token])) {
									$pass = $this->runConditionExtension($token, $token, $trigger, $params, $dict);
								} else {
									if(null != ($ext = DevblocksPlatform::getExtension($token, true))
										&& $ext instanceof Extension_DevblocksEventCondition) { /* @var $ext Extension_DevblocksEventCondition */
										$pass = $ext->run($token, $trigger, $params, $dict);
									}
								}
							}
							break;
					}
			} else {
				$logger->info("  ... FAIL (invalid condition)");
				return false;
			}
			break;
		}

		// Inverse operator?
		if($not)
			$pass = !$pass;

		$logger->info(sprintf("  ... %s", ($pass ? 'PASS' : 'FAIL')));

		return $pass;
	}

	function getActions($trigger) { /* @var $trigger Model_TriggerEvent */
		$actions = array(
			'_create_calendar_event' => array('label' => 'Create calendar event'),
			'_exit' => array('label' => 'Behavior exit'),
			'_get_links' => array('label' => 'Get links'),
			'_run_behavior' => array('label' => 'Behavior run'),
			'_run_subroutine' => array('label' => 'Behavior call subroutine'),
			'_schedule_behavior' => array('label' => 'Behavior schedule'),
			'_set_custom_var' => array('label' => 'Set custom placeholder'),
			'_set_custom_var_snippet' => array('label' => 'Set custom placeholder using a snippet'),
			'_unschedule_behavior' => array('label' => 'Behavior unschedule'),
		);
		$custom = $this->getActionExtensions($trigger);

		if(!empty($custom) && is_array($custom))
			$actions = array_merge($actions, $custom);

		// Trigger variables

		if(is_array($trigger->variables))
		foreach($trigger->variables as $key => $var) {
			$actions[$key] = array('label' => 'Set (variable) ' . $var['label']);
		}

		$va = $trigger->getBot();

		// Add plugin extensions

		$manifests = Extension_DevblocksEventAction::getAll(false, $trigger->event_point);

		// Filter extensions by VA permissions

		$manifests = $va->filterActionManifestsByAllowed($manifests);

		if(is_array($manifests))
		foreach($manifests as $manifest) {
			$actions[$manifest->id] = array('label' => $manifest->params['label']);
		}

		// Sort by label

		DevblocksPlatform::sortObjects($actions, '[label]');

		return $actions;
	}

	abstract function getActionExtensions(Model_TriggerEvent $trigger);
	abstract function renderActionExtension($token, $trigger, $params=array(), $seq=null);
	abstract function runActionExtension($token, $trigger, $params, DevblocksDictionaryDelegate $dict);
	protected function simulateActionExtension($token, $trigger, $params, DevblocksDictionaryDelegate $dict) {}
	function renderSimulatorTarget($trigger, $event_model) {}

	function renderAction($token, $trigger, $params=array(), $seq=null) {
		$actions = $this->getActionExtensions($trigger);

		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('trigger', $trigger);
		$tpl->assign('params', $params);

		if(!is_null($seq))
			$tpl->assign('namePrefix','action'.$seq);

		// Is this an event-provided action?
		if(null != (@$action = $actions[$token])) {
			$this->renderActionExtension($token, $trigger, $params, $seq);

		// Nope, it's a global action
		} else {
			switch($token) {
				case '_create_calendar_event':
					DevblocksEventHelper::renderActionCreateCalendarEvent($trigger);
					break;
					
				case '_exit':
					if($this->hasOption('resumable'))
						$tpl->assign('is_resumable', true);
					
					return $tpl->display('devblocks:cerberusweb.core::internal/decisions/actions/_action_exit.tpl');
					break;

				case '_get_links':
					DevblocksEventHelper::renderActionGetLinks($trigger);
					break;

				case '_set_custom_var':
					$tpl->display('devblocks:cerberusweb.core::internal/decisions/actions/_set_custom_var.tpl');
					break;

				case '_set_custom_var_snippet':
					DevblocksEventHelper::renderActionSetPlaceholderUsingSnippet($trigger, $params);
					break;

				case '_run_behavior':
					DevblocksEventHelper::renderActionRunBehavior($trigger);
					break;

				case '_schedule_behavior':
					$dates = array();
					$conditions = $this->getConditions($trigger);
					foreach($conditions as $key => $data) {
						if(isset($data['type']) && $data['type'] == Model_CustomField::TYPE_DATE)
							$dates[$key] = $data['label'];
					}
					$tpl->assign('dates', $dates);

					DevblocksEventHelper::renderActionScheduleBehavior($trigger);
					break;
					
				case '_run_subroutine':
					$subroutines = $trigger->getNodes('subroutine');
					$tpl->assign('subroutines', $subroutines);
					
					$tpl->display('devblocks:cerberusweb.core::internal/decisions/actions/_action_run_subroutine.tpl');
					break;
					
				case '_unschedule_behavior':
					DevblocksEventHelper::renderActionUnscheduleBehavior($trigger);
					break;

				default:
					// Variables
					if(substr($token,0,4) == 'var_') {
						@$var = $trigger->variables[$token];

						switch(@$var['type']) {
							case Model_CustomField::TYPE_CHECKBOX:
								return $tpl->display('devblocks:cerberusweb.core::internal/decisions/actions/_set_bool.tpl');
								break;
							case Model_CustomField::TYPE_DATE:
								// Restricted to VA-readable calendars
								$calendars = DAO_Calendar::getReadableByActor(array(CerberusContexts::CONTEXT_BOT, $trigger->bot_id));
								$tpl->assign('calendars', $calendars);
								return $tpl->display('devblocks:cerberusweb.core::internal/decisions/actions/_set_date.tpl');
								break;
							case Model_CustomField::TYPE_NUMBER:
								return $tpl->display('devblocks:cerberusweb.core::internal/decisions/actions/_set_number.tpl');
								break;
							case Model_CustomField::TYPE_SINGLE_LINE:
								return DevblocksEventHelper::renderActionSetVariableString($this->getLabels($trigger));
								break;
							case Model_CustomField::TYPE_DROPDOWN:
								return DevblocksEventHelper::renderActionSetVariablePicklist($token, $trigger, $params);
								break;
							case Model_CustomField::TYPE_WORKER:
								return DevblocksEventHelper::renderActionSetVariableWorker($token, $trigger, $params);
								break;
							default:
								if(substr(@$var['type'],0,4) == 'ctx_') {
									@$list_context = substr($var['type'],4);
									if(!empty($list_context))
										return DevblocksEventHelper::renderActionSetListVariable($token, $trigger, $params, $list_context);
								}
								return;
								break;
						}

					} else {
						// Plugins
						if(null != ($ext = DevblocksPlatform::getExtension($token, true))
							&& $ext instanceof Extension_DevblocksEventAction) { /* @var $ext Extension_DevblocksEventAction */
							$ext->render($this, $trigger, $params, $seq);
						}
					}
					break;
			}
		}
	}

	// Are we doing a dry run?
	function simulateAction($token, $trigger, $params, DevblocksDictionaryDelegate $dict) {
		$actions = $this->getActionExtensions($trigger);

		if(null != (@$action = $actions[$token])) {
			if(method_exists($this, 'simulateActionExtension'))
				return $this->simulateActionExtension($token, $trigger, $params, $dict);

		} else {
			switch($token) {
				case '_create_calendar_event':
					return DevblocksEventHelper::simulateActionCreateCalendarEvent($params, $dict);
					break;

				case '_exit':
					@$mode = (isset($params['mode']) && $params['mode'] == 'suspend') ? 'suspend' : 'stop';
					
					return sprintf(">>> %s the behavior\n",
						($mode == 'suspend' ? 'Suspending' : 'Exiting')
					);
					break;
				
				case '_get_links':
					return DevblocksEventHelper::simulateActionGetLinks($params, $dict);
					break;

				case '_set_custom_var':
					@$var = $params['var'];
					@$format = $params['format'];

					$value = ($format == 'json') ? @DevblocksPlatform::strFormatJson(json_encode($dict->$var, true)) : $dict->$var;
					
					return sprintf(">>> Setting custom placeholder {{%s}}:\n%s\n\n",
						$var,
						$value
					);
					break;

				case '_set_custom_var_snippet':
					@$var = $params['var'];

					$value = $dict->$var;

					return sprintf(">>> Setting custom placeholder {{%s}}:\n%s\n\n",
						$var,
						$value
					);
					break;

				case '_run_behavior':
					return DevblocksEventHelper::simulateActionRunBehavior($params, $dict);
					break;

				case '_schedule_behavior':
					return DevblocksEventHelper::simulateActionScheduleBehavior($params, $dict);
					break;

				case '_run_subroutine':
					$subroutine_node = null;
					
					foreach($trigger->getNodes('subroutine') as $node) {
						if($node->title == $params['subroutine']) {
							$subroutine_node = $node;
							break;
						}
					}
					
					if(false == $subroutine_node)
						return;
					
					return sprintf(">>> Running subroutine: %s (#%d)\n",
						$subroutine_node->title,
						$subroutine_node->id
					);
					break;
					
				case '_unschedule_behavior':
					return DevblocksEventHelper::simulateActionUnscheduleBehavior($params, $dict);
					break;

				default:
					// Variables
					if(substr($token,0,4) == 'var_') {
						return DevblocksEventHelper::runActionSetVariable($token, $trigger, $params, $dict);

					} else {
						// Plugins
						if(null != ($ext = DevblocksPlatform::getExtension($token, true))
							&& $ext instanceof Extension_DevblocksEventAction) { /* @var $ext Extension_DevblocksEventAction */
							//return $ext->simulate($token, $trigger, $params, $dict);
						}
					}
					break;
			}
		}
	}

	function runAction($token, $trigger, $params, DevblocksDictionaryDelegate $dict, $dry_run=false) {
		$actions = $this->getActionExtensions($trigger);

		$out = '';

		if(null != (@$action = $actions[$token])) {
			// Is this a dry run?  If so, don't actually change anything
			if($dry_run) {
				$out = $this->simulateAction($token, $trigger, $params, $dict);
			} else {
				$this->runActionExtension($token, $trigger, $params, $dict);
			}

		} else {
			switch($token) {
				case '_create_calendar_event':
					if($dry_run)
						$out = $this->simulateAction($token, $trigger, $params, $dict);
					else
						DevblocksEventHelper::runActionCreateCalendarEvent($params, $dict);

					break;
					
				case '_exit':
					@$mode = (isset($params['mode']) && $params['mode'] == 'suspend') ? 'suspend' : 'stop';
					$dict->__exit = $mode;

					if($dry_run)
						$out = $this->simulateAction($token, $trigger, $params, $dict);
					break;

				case '_get_links':
					if($dry_run)
						$out = $this->simulateAction($token, $trigger, $params, $dict);
					else
						DevblocksEventHelper::runActionGetLinks($params, $dict);
					break;

				case '_set_custom_var':
					$tpl_builder = DevblocksPlatform::getTemplateBuilder();

					@$var = $params['var'];
					@$value = $params['value'];
					@$format = $params['format'];
					@$is_simulator_only = $params['is_simulator_only'] ? true : false;

					// If this variable is only set in the simulator, and we're not simulating, abort
					if($is_simulator_only && !$dry_run)
						return;

					if(!empty($var) && !empty($value)) {
						$value = $tpl_builder->build($value, $dict);
						$dict->$var = ($format == 'json') ? @json_decode($value, true) : $value;
					}

					if($dry_run) {
						$out = $this->simulateAction($token, $trigger, $params, $dict);
					} else {
						return;
					}
					break;

				case '_set_custom_var_snippet':
					$tpl_builder = DevblocksPlatform::getTemplateBuilder();
					$cache = DevblocksPlatform::getCacheService();

					@$on = $params['on'];
					@$snippet_id = $params['snippet_id'];
					@$var = $params['var'];
					@$placeholder_values = $params['placeholders'];

					if(empty($on) || empty($var) || empty($snippet_id))
						return;

					// Cache the snippet in the request (multiple runs of the VA; parser, etc)
					$cache_key = sprintf('snippet_%d', $snippet_id);
					if(false == ($snippet = $cache->load($cache_key, false, true))) {
						if(false == ($snippet = DAO_Snippet::get($snippet_id)))
							return;

						$cache->save($snippet, $cache_key, array(), 0, true);
					}

					if(empty($var))
						return;

					$values_to_contexts = $this->getValuesContexts($trigger);

					@$on_context = $values_to_contexts[$on];

					if(empty($on) || !is_array($on_context))
						return;

					$snippet_labels = array();
					$snippet_values = array();

					// Load snippet target dictionary
					if(!empty($snippet->context) && $snippet->context == $on_context['context']) {
						CerberusContexts::getContext($on_context['context'], $dict->$on, $snippet_labels, $snippet_values, '', false, false);
					}

					// Prompted placeholders

					// [TODO] If a required prompted placeholder is missing, abort

					if(is_array($snippet->custom_placeholders) && is_array($placeholder_values))
					foreach($snippet->custom_placeholders as $placeholder_key => $placeholder) {
						if(!isset($placeholder_values[$placeholder_key])) {
							$snippet_values[$placeholder_key] = $placeholder['default'];

						} else {
							// Convert placeholders
							$snippet_values[$placeholder_key] = $tpl_builder->build($placeholder_values[$placeholder_key], $dict);
						}
					}

					$value = $tpl_builder->build($snippet->content, $snippet_values);
					$dict->$var = $value;

					if($dry_run) {
						$out = $this->simulateAction($token, $trigger, $params, $dict);
					} else {
						return;
					}
					break;

				case '_run_behavior':
					if($dry_run)
						$out = $this->simulateAction($token, $trigger, $params, $dict);
					else
						DevblocksEventHelper::runActionRunBehavior($params, $dict);
					break;

				case '_run_subroutine':
					$subroutine_node = null;
					
					foreach($trigger->getNodes('subroutine') as $node) {
						if($node->title == $params['subroutine']) {
							$subroutine_node = $node;
							break;
						}
					}
					
					if(false == $subroutine_node)
						break;
					
					$dict->__goto = $subroutine_node->id;
					
					if($dry_run)
						$out = $this->simulateAction($token, $trigger, $params, $dict);
					break;
					
				case '_schedule_behavior':
					if($dry_run)
						$out = $this->simulateAction($token, $trigger, $params, $dict);
					else
						DevblocksEventHelper::runActionScheduleBehavior($params, $dict);
					break;

				case '_unschedule_behavior':
					if($dry_run)
						$out = $this->simulateAction($token, $trigger, $params, $dict);
					else
						DevblocksEventHelper::runActionUnscheduleBehavior($params, $dict);
					break;

				default:
					// Variables
					if(substr($token,0,4) == 'var_') {
						// Always set the action vars, even in simulation.
						DevblocksEventHelper::runActionSetVariable($token, $trigger, $params, $dict);

						if($dry_run) {
							$out = DevblocksEventHelper::simulateActionSetVariable($token, $trigger, $params, $dict);
						} else {
							return;
						}

					} else {
						// Plugins
						if(null != ($ext = DevblocksPlatform::getExtension($token, true))
							&& $ext instanceof Extension_DevblocksEventAction) { /* @var $ext Extension_DevblocksEventAction */
							if($dry_run) {
								if(method_exists($ext, 'simulate'))
									$out = $ext->simulate($token, $trigger, $params, $dict);
							} else {
								return $ext->run($token, $trigger, $params, $dict);
							}
						}
					}
					break;
			}
		}

		// Append to simulator output
		if(!empty($out)) {
			/* @var $trigger Model_TriggerEvent */
			$all_actions = $this->getActions($trigger);
			$log = EventListener_Triggers::getNodeLog();

			if(!isset($dict->__simulator_output) || !is_array($dict->__simulator_output))
				$dict->__simulator_output = array();

			$node_id = array_pop($log);

			if(!empty($node_id) && false !== ($node = DAO_DecisionNode::get($node_id))) {
				$output = array(
					'action' => $node->title,
					'title' => $all_actions[$token]['label'],
					'content' => $out,
				);
				
				$previous_output = $dict->__simulator_output;
				$previous_output[] = $output;
				$dict->__simulator_output = $previous_output;
				unset($out);
			}
		}
	}
};

abstract class Extension_DevblocksEventCondition extends DevblocksExtension {
	public static function getAll($as_instances=false, $for_event=null) {
		$extensions = DevblocksPlatform::getExtensions('devblocks.event.condition', false);
		$results = array();

		foreach($extensions as $ext_id => $ext) {
			// If the condition doesn't specify event filters, add to everything
			if(!isset($ext->params['events'][0])) {
				$results[$ext_id] = $as_instances ? $ext->createInstance() : $ext;

			} else {
				// Loop through the patterns
				foreach(array_keys($ext->params['events'][0]) as $evt_pattern) {
					$evt_pattern = DevblocksPlatform::strToRegExp($evt_pattern);

					if(preg_match($evt_pattern, $for_event))
						$results[$ext_id] = $as_instances ? $ext->createInstance() : $ext;
				}
			}
		}

		if($as_instances)
			DevblocksPlatform::sortObjects($results, 'manifest->params->[label]');
		else
			DevblocksPlatform::sortObjects($results, 'params->[label]');

		return $results;
	}

	abstract function render(Extension_DevblocksEvent $event, Model_TriggerEvent $trigger, $params=array(), $seq=null);
	abstract function run($token, Model_TriggerEvent $trigger, $params, DevblocksDictionaryDelegate $dict);
};

abstract class Extension_DevblocksEventAction extends DevblocksExtension {
	public static function getAll($as_instances=false, $for_event=null) {
		$extensions = DevblocksPlatform::getExtensions('devblocks.event.action', false);
		$results = array();

		foreach($extensions as $ext_id => $ext) {
			// If the action doesn't specify event filters, add to everything
			if(!isset($ext->params['events'][0])) {
				$results[$ext_id] = $as_instances ? $ext->createInstance() : $ext;

			} else {
				// Loop through the patterns
				foreach(array_keys($ext->params['events'][0]) as $evt_pattern) {
					$evt_pattern = DevblocksPlatform::strToRegExp($evt_pattern);

					if(preg_match($evt_pattern, $for_event))
						$results[$ext_id] = $as_instances ? $ext->createInstance() : $ext;
				}
			}
		}

		if($as_instances)
			DevblocksPlatform::sortObjects($results, 'manifest->params->[label]');
		else
			DevblocksPlatform::sortObjects($results, 'params->[label]');

		return $results;
	}

	abstract function render(Extension_DevblocksEvent $event, Model_TriggerEvent $trigger, $params=array(), $seq=null);
	function simulate($token, Model_TriggerEvent $trigger, $params, DevblocksDictionaryDelegate $dict) {}
	abstract function run($token, Model_TriggerEvent $trigger, $params, DevblocksDictionaryDelegate $dict);
}

abstract class DevblocksHttpResponseListenerExtension extends DevblocksExtension {
	function run(DevblocksHttpResponse $request, Smarty $tpl) {
	}
};

abstract class Extension_DevblocksCacheEngine extends DevblocksExtension {
	protected $_config = array();

	public static function getAll($as_instances=false) {
		$engines = DevblocksPlatform::getExtensions('devblocks.cache.engine', $as_instances);
		if($as_instances)
			DevblocksPlatform::sortObjects($engines, 'manifest->name');
		else
			DevblocksPlatform::sortObjects($engines, 'name');
		return $engines;
	}

	/**
	 * @param string $id
	 * @return Extension_DevblocksCacheEngine
	 */
	public static function get($id) {
		static $extensions = null;

		if(isset($extensions[$id]))
			return $extensions[$id];

		if(!isset($extensions[$id])) {
			if(null == ($ext = DevblocksPlatform::getExtension($id, true)))
				return;

			if(!($ext instanceof Extension_DevblocksCacheEngine))
				return;

			$extensions[$id] = $ext;
			return $ext;
		}
	}

	function getConfig() {
		return $this->_config;
	}

	abstract function setConfig(array $config);
	abstract function testConfig(array $config);
	abstract function renderConfig();
	abstract function renderStatus();

	abstract function isVolatile();
	abstract function save($data, $key, $tags=array(), $lifetime=0);
	abstract function load($key);
	abstract function remove($key);
	abstract function clean();
};

interface IDevblocksSearchEngine {
	public function setConfig(array $config);
	public function testConfig(array $config);
	public function renderConfigForSchema(Extension_DevblocksSearchSchema $schema);

	public function getQuickSearchExamples(Extension_DevblocksSearchSchema $schema);
	public function getIndexMeta(Extension_DevblocksSearchSchema $schema);

	public function query(Extension_DevblocksSearchSchema $schema, $query, array $attributes=array(), $limit=250);
	public function index(Extension_DevblocksSearchSchema $schema, $id, array $doc, array $attributes=array());
	public function delete(Extension_DevblocksSearchSchema $schema, $ids);
};

abstract class Extension_DevblocksSearchEngine extends DevblocksExtension implements IDevblocksSearchEngine {
	public static function getAll($as_instances=false) {
		$engines = DevblocksPlatform::getExtensions('devblocks.search.engine', $as_instances);
		if($as_instances)
			DevblocksPlatform::sortObjects($engines, 'manifest->name');
		else
			DevblocksPlatform::sortObjects($engines, 'name');
		return $engines;
	}

	/**
	 * @param string $id
	 * @return Extension_DevblocksSearchEngine
	 */
	public static function get($id) {
		static $extensions = null;

		if(isset($extensions[$id]))
			return $extensions[$id];

		if(!isset($extensions[$id])) {
			if(null == ($ext = DevblocksPlatform::getExtension($id, true)))
				return;

			if(!($ext instanceof Extension_DevblocksSearchEngine))
				return;

			$extensions[$id] = $ext;
			return $ext;
		}
	}
	
	protected function escapeNamespace($namespace) {
		return DevblocksPlatform::strLower(DevblocksPlatform::strAlphaNum($namespace, '\_'));
	}

	public function _getTextFromDoc(array $doc) {
		$output = array();

		// Find all text content and append it together
		array_walk_recursive($doc, function($e) use (&$output) {
			if(is_string($e))
				$output[] = $e;
		});

		return implode(' ', $output);
	}

	public function getQueryFromParam($param) {
		$values = array();

		if(!is_array($param->value) && !is_string($param->value))
			return false;
		
		if(!is_array($param->value) && preg_match('#^\[.*\]$#', $param->value)) {
			$values = json_decode($param->value, true);
			
		} elseif(is_array($param->value)) {
			$values = $param->value;
			
		} else {
			$values = $param->value;
			
		}
		
		if(!is_array($values)) {
			$value = $values;
			
		} else {
			$value = $values[0];
		}
		
		return $value;
	}
	
	public function truncateOnWhitespace($content, $length) {
		$start = 0;
		$len = mb_strlen($content);
		$end = $start + $length;
		$next_ws = $end;

		// If our offset is past EOS, use the last pos
		if($end > $len) {
			$next_ws = $len;

		} else {
			if(false === ($next_ws = mb_strpos($content, ' ', $end)))
				if(false === ($next_ws = mb_strpos($content, "\n", $end)))
					$next_ws = $end;
		}

		return mb_substr($content, $start, $next_ws-$start);
	}
};

abstract class Extension_DevblocksSearchSchema extends DevblocksExtension {
	const INDEX_POINTER_RESET = 'reset';
	const INDEX_POINTER_CURRENT = 'current';

	public static function getAll($as_instances=false) {
		$schemas = DevblocksPlatform::getExtensions('devblocks.search.schema', $as_instances);
		if($as_instances)
			DevblocksPlatform::sortObjects($schemas, 'manifest->name');
		else
			DevblocksPlatform::sortObjects($schemas, 'name');
		return $schemas;
	}

	/**
	 * @param string $id
	 * @return Extension_DevblocksSearchSchema
	 */
	public static function get($id) {
		static $extensions = null;

		if(isset($extensions[$id]))
			return $extensions[$id];

		if(!isset($extensions[$id])) {
			if(null == ($ext = DevblocksPlatform::getExtension($id, true)))
				return;

			if(!($ext instanceof Extension_DevblocksSearchSchema))
				return;

			$extensions[$id] = $ext;
			return $ext;
		}
	}

	public function getEngineParams() {
		if(false == ($engine_json = $this->getParam('engine_params_json', false))) {
			$engine_json = '{"engine_extension_id":"devblocks.search.engine.mysql_fulltext", "config":{}}';
		}

		if(false == ($engine_properties = json_decode($engine_json, true))) {
			return false;
		}

		return $engine_properties;
	}

	/**
	 *
	 * @return Extension_DevblocksSearchEngine
	 */
	public function getEngine() {
		$engine_params = $this->getEngineParams();
		
		if(false == ($_engine = Extension_DevblocksSearchEngine::get($engine_params['engine_extension_id'], true)))
			return false;

		if(isset($engine_params['config']))
			$_engine->setConfig($engine_params['config']);

		return $_engine;
	}

	public function saveConfig(array $params) {
		if(!is_array($params))
			$params = array();

		// Detect if the engine changed
		$previous_engine_params = $this->getEngineParams();
		$reindex = (@$previous_engine_params['engine_extension_id'] != @$params['engine_extension_id']);

		// Save new new engine params
		$this->setParam('engine_params_json', json_encode($params));

		// If our engine changed
		if($reindex)
			$this->reindex();
	}

	public function getQueryFromParam($param) {
		if(false !== ($engine = $this->getEngine()))
			return $engine->getQueryFromParam($param);

		return null;
	}

	public function getIndexMeta() {
		$engine = $this->getEngine();
		return $engine->getIndexMeta($this);
	}
	
	abstract function getNamespace();
	abstract function getAttributes();
	//abstract function getFields();
	abstract function query($query, $attributes=array(), $limit=1000);
	abstract function index($stop_time=null);
	abstract function reindex();
	abstract function delete($ids);
};

abstract class Extension_DevblocksStorageEngine extends DevblocksExtension {
	protected $_options = array();

	abstract function renderConfig(Model_DevblocksStorageProfile $profile);
	abstract function saveConfig(Model_DevblocksStorageProfile $profile);
	abstract function testConfig(Model_DevblocksStorageProfile $profile);

	abstract function exists($namespace, $key);
	abstract function put($namespace, $id, $data);
	abstract function get($namespace, $key, &$fp=null);
	abstract function delete($namespace, $key);
	
	function batchDelete($namespace, $keys) { /* override */ 
		if(is_array($keys))
		foreach($keys as $key)
			$this->delete($namespace, $key);
	}

	public function setOptions($options=array()) {
		if(is_array($options))
			$this->_options = $options;
	}

	protected function escapeNamespace($namespace) {
		return DevblocksPlatform::strLower(DevblocksPlatform::strAlphaNum($namespace, '\_'));
	}
};

abstract class Extension_DevblocksStorageSchema extends DevblocksExtension {
	abstract function render();
	abstract function renderConfig();
	abstract function saveConfig();

	public static function getActiveStorageProfile() {}

	public static function get($object, &$fp=null) {}
	public static function put($id, $contents, $profile=null) {}
	public static function delete($ids) {}
	public static function archive($stop_time=null) {}
	public static function unarchive($stop_time=null) {}

	protected function _stats($table_name) {
		$db = DevblocksPlatform::getDatabaseService();

		$stats = array();

		$results = $db->GetArraySlave(sprintf("SELECT storage_extension, storage_profile_id, count(id) as hits, sum(storage_size) as bytes FROM %s GROUP BY storage_extension, storage_profile_id ORDER BY storage_extension",
			$table_name
		));
		foreach($results as $result) {
			$stats[$result['storage_extension'].':'.intval($result['storage_profile_id'])] = array(
				'storage_extension' => $result['storage_extension'],
				'storage_profile_id' => $result['storage_profile_id'],
				'count' => intval($result['hits']),
				'bytes' => intval($result['bytes']),
			);
		}

		return $stats;
	}
};

abstract class DevblocksControllerExtension extends DevblocksExtension implements DevblocksHttpRequestHandler {
	public function handleRequest(DevblocksHttpRequest $request) {}
	public function writeResponse(DevblocksHttpResponse $response) {}
};

abstract class DevblocksEventListenerExtension extends DevblocksExtension {
	/**
	 * @param Model_DevblocksEvent $event
	 */
	function handleEvent(Model_DevblocksEvent $event) {}
};

interface DevblocksHttpRequestHandler {
	/**
	 * @param DevblocksHttpRequest
	 * @return DevblocksHttpResponse
	 */
	public function handleRequest(DevblocksHttpRequest $request);
	public function writeResponse(DevblocksHttpResponse $response);
};

class DevblocksHttpRequest extends DevblocksHttpIO {
	public $method = null;
	public $csrf_token = null;
	
	/**
	 * @param array $path
	 */
	function __construct($path=array(), $query=array(), $method=null) {
		parent::__construct($path, $query);
		$this->method = $method;
	}
};

class DevblocksHttpResponse extends DevblocksHttpIO {
	/**
	 * @param array $path
	 */
	function __construct($path, $query=array()) {
		parent::__construct($path, $query);
	}
};

abstract class DevblocksHttpIO {
	public $path = array();
	public $query = array();

	/**
	 * Enter description here...
	 *
	 * @param array $path
	 */
	function __construct($path,$query=array()) {
		$this->path = $path;
		$this->query = $query;
	}
};

class _DevblocksSortHelper {
	private static $_sortOn = '';

	static function sortByNestedMember($a, $b) {
		$props = explode('->', self::$_sortOn);

		$a_test = $a;
		$b_test = $b;

		foreach($props as $prop) {
			$is_index = false;

			if(@preg_match("#\[(.*?)\]#", $prop, $matches)) {
				$is_index = true;
				$prop = $matches[1];
			}

			if($is_index) {
				if(!isset($a_test[$prop]) && !isset($b_test[$prop]))
					return 0;

				@$a_test = $a_test[$prop];
				@$b_test = $b_test[$prop];

			} else {
				if(!isset($a_test->$prop) && !isset($b_test->$prop)) {
					return 0;
				}

				@$a_test = $a_test->$prop;
				@$b_test = $b_test->$prop;
			}
		}

		if(is_numeric($a_test) && is_numeric($b_test)) {
			settype($a_test, 'float');
			settype($b_test, 'float');

			if($a_test==$b_test)
				return 0;

			return ($a_test > $b_test) ? 1 : -1;

		} else {
			$a_test = is_null($a_test) ? '' : $a_test;
			$b_test = is_null($b_test) ? '' : $b_test;

			if(!is_string($a_test) || !is_string($b_test))
				return 0;

			return strcasecmp($a_test, $b_test);
		}
	}

	static function sortObjects(&$array, $on, $ascending=true) {
		self::$_sortOn = $on;

		uasort($array, array('_DevblocksSortHelper', 'sortByNestedMember'));

		if(!$ascending)
			$array = array_reverse($array, true);
	}
};
