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

class DAO_CustomField extends Cerb_ORMHelper {
	const ID = 'id';
	const NAME = 'name';
	const TYPE = 'type';
	const CONTEXT = 'context';
	const POS = 'pos';
	const PARAMS_JSON = 'params_json';
	const CUSTOM_FIELDSET_ID = 'custom_fieldset_id';
	
	const CACHE_ALL = 'ch_customfields';
	
	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = sprintf("INSERT INTO custom_field () ".
			"VALUES ()"
		);
		if(false == ($rs = $db->ExecuteMaster($sql)))
			return false;
		$id = $db->LastInsertId();

		self::update($id, $fields);
		
		return $id;
	}
	
	static function update($ids, $fields) {
		parent::_update($ids, 'custom_field', $fields);
		
		self::clearCache();
	}
	
	/**
	 * Enter description here...
	 *
	 * @param integer $id
	 * @return Model_CustomField|null
	 */
	static function get($id) {
		if(empty($id))
			return null;
		
		$fields = self::getAll();
		
		if(isset($fields[$id]))
			return $fields[$id];
			
		return null;
	}
	
	/**
	* Returns all of the fields for the specified context available to $group_id, including global fields
	*
	* @param string $context The context of the custom field
	* @param boolean $with_fieldsets Include fieldsets
	* @return Model_CustomField[]
	*/
	
	static function getByContext($context, $with_fieldsets=true, $with_fieldset_names=false) {
		$fields = self::getAll();
		$fieldsets = DAO_CustomFieldset::getAll();
		$results = array();

		// [TODO] Filter to the fieldsets the active worker is allowed to see
		
		// Filter fields to only the requested source
		foreach($fields as $idx => $field) { /* @var $field Model_CustomField */
			// If we only want a specific context, filter out the rest
			if(0 != strcasecmp($field->context, $context))
				continue;
			
			if(!$with_fieldsets && !empty($field->custom_fieldset_id))
				continue;
			
			if($with_fieldset_names && !empty($field->custom_fieldset_id)) {
				if(isset($fieldsets[$field->custom_fieldset_id]))
					$field->name = $fieldsets[$field->custom_fieldset_id]->name . ' ' . $field->name;
			}
			
			$results[$idx] = $field;
		}
		
		return $results;
	}
	
	/**
	 *
	 * @param boolean $nocache
	 * @return Model_CustomField[]
	 */
	static function getAll($nocache=false) {
		$cache = DevblocksPlatform::getCacheService();
		
		if(null === ($objects = $cache->load(self::CACHE_ALL))) {
			$db = DevblocksPlatform::getDatabaseService();
			$sql = "SELECT id, name, type, context, custom_fieldset_id, pos, params_json ".
				"FROM custom_field ".
				"ORDER BY custom_fieldset_id ASC, pos ASC "
			;
			
			if(false === ($rs = $db->ExecuteMaster($sql, _DevblocksDatabaseManager::OPT_NO_READ_AFTER_WRITE)))
				return false;
			
			$objects = self::_createObjectsFromResultSet($rs);
			
			$cache->save($objects, self::CACHE_ALL);
		}
		
		return $objects;
	}
	
	private static function _createObjectsFromResultSet($rs) {
		if(!($rs instanceof mysqli_result))
			return false;
		
		$objects = array();
		
		if(!($rs instanceof mysqli_result))
			return false;
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object = new Model_CustomField();
			$object->id = intval($row['id']);
			$object->name = $row['name'];
			$object->type = $row['type'];
			$object->context = $row['context'];
			$object->custom_fieldset_id = intval($row['custom_fieldset_id']);
			$object->pos = intval($row['pos']);
			$object->params = array();
			
			// JSON params
			if(!empty($row['params_json'])) {
				@$params = json_decode($row['params_json'], true);
				if(!empty($params))
					$object->params = $params;
			}
			
			$objects[$object->id] = $object;
		}
		
		mysqli_free_result($rs);
		
		return $objects;
	}
	
	static function random() {
		return self::_getRandom('custom_field');
	}
	
	public static function delete($ids) {
		if(!is_array($ids))
			$ids = array($ids);
		
		if(empty($ids))
			return;
		
		$db = DevblocksPlatform::getDatabaseService();
		
		$id_string = implode(',', $ids);
		
		$sql = sprintf("DELETE FROM custom_field WHERE id IN (%s)",$id_string);
		if(false == ($db->ExecuteSlave($sql)))
			return false;

		if(is_array($ids))
		foreach($ids as $id) {
			DAO_CustomFieldValue::deleteByFieldId($id);
		}

		// Fire event
		$eventMgr = DevblocksPlatform::getEventService();
		$eventMgr->trigger(
			new Model_DevblocksEvent(
				'context.delete',
				array(
					'context' => CerberusContexts::CONTEXT_CUSTOM_FIELD,
					'context_ids' => $ids
				)
			)
		);
		
		self::clearCache();
	}
	
	public static function maint() {
		$db = DevblocksPlatform::getDatabaseService();
		$logger = DevblocksPlatform::getConsoleLog();
		
		$db->ExecuteMaster("DELETE FROM custom_field WHERE custom_fieldset_id != 0 AND custom_fieldset_id NOT IN (SELECT id FROM custom_fieldset)");
		$logger->info('[Maint] Purged ' . $db->Affected_Rows() . ' custom_field records.');
	}
	
	public static function clearCache() {
		// Invalidate cache on changes
		$cache = DevblocksPlatform::getCacheService();
		$cache->remove(self::CACHE_ALL);
	}
};

class DAO_CustomFieldValue extends Cerb_ORMHelper {
	const FIELD_ID = 'field_id';
	const CONTEXT = 'context';
	const CONTEXT_ID = 'context_id';
	const FIELD_VALUE = 'field_value';
	
	public static function getValueTableName($field_id) {
		$field = DAO_CustomField::get($field_id);
		
		// Determine value table by type
		switch($field->type) {
			// stringvalue
			case Model_CustomField::TYPE_SINGLE_LINE:
			case Model_CustomField::TYPE_DROPDOWN:
			case Model_CustomField::TYPE_MULTI_CHECKBOX:
			case Model_CustomField::TYPE_URL:
				$table = 'custom_field_stringvalue';
				break;
			// clobvalue
			case Model_CustomField::TYPE_MULTI_LINE:
				$table = 'custom_field_clobvalue';
				break;
			// number
			case Model_CustomField::TYPE_CHECKBOX:
			case Model_CustomField::TYPE_DATE:
			case Model_CustomField::TYPE_FILE:
			case Model_CustomField::TYPE_FILES:
			case Model_CustomField::TYPE_LINK:
			case Model_CustomField::TYPE_NUMBER:
			case Model_CustomField::TYPE_WORKER:
				$table = 'custom_field_numbervalue';
				break;
			default:
				$table = null;
				break;
		}
		
		return $table;
	}
	
	public static function formatFieldValues($values) {
		if(!is_array($values))
			return;

		$fields = DAO_CustomField::getAll();
		$output = array();

		foreach($values as $field_id => $value) {
			if(!isset($fields[$field_id]))
				continue;

			$field =& $fields[$field_id]; /* @var $field Model_CustomField */

			switch($field->type) {
				case Model_CustomField::TYPE_SINGLE_LINE:
				case Model_CustomField::TYPE_URL:
					$value = (strlen($value) > 255) ? substr($value,0,255) : $value;
					break;

				case Model_CustomField::TYPE_MULTI_LINE:
					break;

				case Model_CustomField::TYPE_DROPDOWN:
					break;
					
				case Model_CustomField::TYPE_MULTI_CHECKBOX:
					$values = $value;
					$value = array();
					
					if(!is_array($values))
						$values = array($values);

					// Protect from injection in cases where it's not desireable (controlled above)
					foreach($values as $idx => $v) {
						if(!isset($field->params['options']) || !in_array($v, $field->params['options']))
							continue;

						$is_unset = ('-'==substr($v,0,1)) ? true : false;
						$v = ltrim($v,'+-');
							
						if($is_unset) {
						} else {
							$value[$v] = $v;
						}
					}
					break;
					
				case Model_CustomField::TYPE_FILES:
					$values = $value;
					$value = array();
					
					if(!is_array($values))
						$values = array($values);

					foreach($values as $idx => $v) {
						$is_unset = ('-'==substr($v,0,1)) ? true : false;
						$v = ltrim($v,'+-');
							
						if($is_unset) {
						} else {
							$value[$v] = $v;
						}
					}
					break;

				case Model_CustomField::TYPE_CHECKBOX:
					$value = !empty($value) ? 1 : 0;
					break;

				case Model_CustomField::TYPE_DATE:
					if(is_numeric($value)) {
						$value = intval($value);
					} else {
						@$value = strtotime($value);
					}
					break;

				case Model_CustomField::TYPE_FILE:
				case Model_CustomField::TYPE_NUMBER:
				case Model_CustomField::TYPE_WORKER:
					$value = intval($value);
					break;
			}
			
			$output[$field_id] = $value;
		}
		
		return $output;
	}
	
	/**
	 *
	 * @param object $context
	 * @param object $context_id
	 * @param object $values
	 * @return
	 */
	public static function formatAndSetFieldValues($context, $context_id, $values, $is_blank_unset=true, $delta=false, $autoadd_options=false) {
		// [TODO] This could probably be combined with ::formatFieldValues()
		
		if(empty($context) || empty($context_id) || !is_array($values))
			return;
		
		self::_linkCustomFieldsets($context, $context_id, $values);
		
		$fields = DAO_CustomField::getByContext($context);

		foreach($values as $field_id => $value) {
			if(!isset($fields[$field_id]))
				continue;

			$field =& $fields[$field_id]; /* @var $field Model_CustomField */
			$is_delta = ($field->type==Model_CustomField::TYPE_MULTI_CHECKBOX || $field->type==Model_CustomField::TYPE_FILES)
					? $delta
					: false
					;
			// if the field is blank
			if(
				(is_array($value) && empty($value))
				||
				(!is_array($value) && 0==strlen($value))
			) {
				// ... and blanks should unset
				if($is_blank_unset && !$is_delta)
					self::unsetFieldValue($context, $context_id, $field_id);
				
				// Skip setting
				continue;
			}

			switch($field->type) {
				case Model_CustomField::TYPE_SINGLE_LINE:
				case Model_CustomField::TYPE_URL:
					$value = (strlen($value) > 255) ? substr($value,0,255) : $value;
					self::setFieldValue($context, $context_id, $field_id, $value);
					break;

				case Model_CustomField::TYPE_MULTI_LINE:
					self::setFieldValue($context, $context_id, $field_id, $value);
					break;

				case Model_CustomField::TYPE_DROPDOWN:
					// If we're setting a field that doesn't exist yet, add it.
					@$options = $field->params['options'] ?: array();
					
					if($autoadd_options && !in_array($value, $options) && !empty($value)) {
						$field->params['options'][] = $value;
						
						DAO_CustomField::update($field_id,
							array(
								DAO_CustomField::PARAMS_JSON => json_encode($field->params)
							)
						);
					}
					
					// If we're allowed to add/remove fields without touching the rest
					if(in_array($value, $options))
						self::setFieldValue($context, $context_id, $field_id, $value);
					
					break;
					
				case Model_CustomField::TYPE_FILES:
					if(!is_array($value))
						$value = array($value);

					if(!$delta) {
						self::unsetFieldValue($context, $context_id, $field_id);
					}
					
					// Protect from injection in cases where it's not desireable (controlled above)
					foreach($value as $idx => $v) {
						$is_unset = ('-'==substr($v,0,1)) ? true : false;
						$v = ltrim($v,'+-');
							
						if($is_unset) {
							if($delta)
								self::unsetFieldValue($context, $context_id, $field_id, $v);
						} else {
							self::setFieldValue($context, $context_id, $field_id, $v, true);
						}
					}
					break;
					
				case Model_CustomField::TYPE_MULTI_CHECKBOX:
					if(!is_array($value))
						$value = array($value);

					@$options = $field->params['options'] ?: array();
					
					// If we're setting a field that doesn't exist yet, add it.
					if($autoadd_options) {
						$added = false;
						
						foreach($value as $v) {
							if($autoadd_options && !in_array($v, $options) && !empty($v)) {
								$field->params['options'][] = $v;
								$added = true;
							}
						}
						
						if($added) {
							DAO_CustomField::update($field_id,
								array(
									DAO_CustomField::PARAMS_JSON => json_encode($field->params)
								)
							);
						}
					}

					if(!$delta) {
						self::unsetFieldValue($context, $context_id, $field_id);
					}
					
					// Protect from injection in cases where it's not desireable (controlled above)
					foreach($value as $idx => $v) {
						if(empty($v))
							continue;
						
						$is_unset = ('-'==substr($v,0,1)) ? true : false;
						$v = ltrim($v,'+-');
						
						if(!isset($field->params['options']) || !in_array($v, $field->params['options']))
							continue;
						
						if($is_unset) {
							if($delta)
								self::unsetFieldValue($context, $context_id, $field_id, $v);
						} else {
							self::setFieldValue($context, $context_id, $field_id, $v, true);
						}
					}

					break;

				case Model_CustomField::TYPE_CHECKBOX:
					$value = !empty($value) ? 1 : 0;
					self::setFieldValue($context, $context_id, $field_id, $value);
					break;

				case Model_CustomField::TYPE_DATE:
					if(is_numeric($value)) {
						$value = intval($value);
					} else {
						@$value = strtotime($value);
					}
					self::setFieldValue($context, $context_id, $field_id, $value);
					break;

				case Model_CustomField::TYPE_FILE:
				case Model_CustomField::TYPE_LINK:
				case Model_CustomField::TYPE_NUMBER:
				case Model_CustomField::TYPE_WORKER:
					$value = intval($value);
					self::setFieldValue($context, $context_id, $field_id, $value);
					break;
			}
		}
	}
	
	public static function setFieldValue($context, $context_id, $field_id, $value, $delta=false) {
		CerberusContexts::checkpointChanges($context, array($context_id));
		
		$db = DevblocksPlatform::getDatabaseService();
		
		if(null == ($field = DAO_CustomField::get($field_id)))
			return FALSE;
		
		if(null == ($table_name = self::getValueTableName($field_id)))
			return FALSE;

		// Data formating
		switch($field->type) {
			case Model_CustomField::TYPE_DATE: // date
				if(is_numeric($value))
					$value = intval($value);
				else
					$value = @strtotime($value);
				break;
			case Model_CustomField::TYPE_DROPDOWN:
				$possible_values = array_map('strtolower', $field->params['options']);
				
				if(false !== ($value_idx = array_search(DevblocksPlatform::strLower($value), $possible_values))) {
					$value = $field->params['options'][$value_idx];
				} else {
					return FALSE;
				}
				break;
			case Model_CustomField::TYPE_SINGLE_LINE:
			case Model_CustomField::TYPE_URL:
				if(255 < strlen($value))
					$value = substr($value,0,255);
				break;
			case Model_CustomField::TYPE_FILE:
			case Model_CustomField::TYPE_LINK:
			case Model_CustomField::TYPE_NUMBER:
			case Model_CustomField::TYPE_WORKER:
				$value = intval($value);
				break;
		}
		
		// Clear existing values (beats replace logic)
		self::unsetFieldValue($context, $context_id, $field_id, ($delta?$value:null));
		
		// Set values consistently
		if(!is_array($value))
			$value = array($value);
			
		foreach($value as $v) {
			$sql = sprintf("INSERT INTO %s (field_id, context, context_id, field_value) ".
				"VALUES (%d, %s, %d, %s)",
				$table_name,
				$field_id,
				$db->qstr($context),
				$context_id,
				$db->qstr($v)
			);
			$db->ExecuteMaster($sql);
		}
		
		DevblocksPlatform::markContextChanged($context, $context_id);
		
		// Special handling
		switch($field->type) {
			case Model_CustomField::TYPE_FILE:
			case Model_CustomField::TYPE_FILES:
				DAO_Attachment::setLinks(CerberusContexts::CONTEXT_CUSTOM_FIELD, $field_id, $value);
				break;
		}
		
		DevblocksPlatform::markContextChanged($context, $context_id);
		
		return TRUE;
	}
	
	public static function unsetFieldValue($context, $context_id, $field_id, $value=null) {
		CerberusContexts::checkpointChanges($context, array($context_id));
		
		$db = DevblocksPlatform::getDatabaseService();
		
		if(null == ($field = DAO_CustomField::get($field_id)))
			return FALSE;
		
		if(null == ($table_name = self::getValueTableName($field_id)))
			return FALSE;
		
		if(!is_array($value))
			$value = array($value);
		
		foreach($value as $v) {
			// Delete all values or optionally a specific given value
			$sql = sprintf("DELETE FROM %s WHERE context = '%s' AND context_id = %d AND field_id = %d %s",
				$table_name,
				$context,
				$context_id,
				$field_id,
				(!is_null($v) ? sprintf("AND field_value = %s ",$db->qstr($v)) : "")
			);
			$db->ExecuteMaster($sql);
		}
		
		// We need to remove context links on file attachments
		// [TODO] Optimize
		switch($field->type) {
			case Model_CustomField::TYPE_FILE:
			case Model_CustomField::TYPE_FILES:
				$sql = sprintf("DELETE FROM context_link WHERE from_context = %s and from_context_id = %d AND to_context = %s AND to_context_id NOT IN (SELECT field_value FROM custom_field_numbervalue WHERE field_id = %d)",
					$db->qstr(CerberusContexts::CONTEXT_CUSTOM_FIELD),
					$field_id,
					$db->qstr(CerberusContexts::CONTEXT_ATTACHMENT),
					$field_id
				);
				$db->ExecuteMaster($sql);
				break;
		}
		
		DevblocksPlatform::markContextChanged($context, $context_id);
		
		return TRUE;
	}
	
	public static function handleBulkPost($do) {
		@$field_ids = DevblocksPlatform::importGPC($_POST['field_ids'],'array',array());

		$fields = DAO_CustomField::getAll();
		
		if(is_array($field_ids))
		foreach($field_ids as $field_id) {
			if(!isset($fields[$field_id]))
				continue;
			
			switch($fields[$field_id]->type) {
				case Model_CustomField::TYPE_MULTI_LINE:
				case Model_CustomField::TYPE_SINGLE_LINE:
				case Model_CustomField::TYPE_URL:
					@$field_value = DevblocksPlatform::importGPC($_POST['field_'.$field_id],'string','');
					$do['cf_'.$field_id] = array('value' => $field_value);
					break;
					
				case Model_CustomField::TYPE_FILE:
				case Model_CustomField::TYPE_LINK:
				case Model_CustomField::TYPE_NUMBER:
					@$field_value = DevblocksPlatform::importGPC($_POST['field_'.$field_id],'string','');
					$field_value = (0==strlen($field_value)) ? '' : intval($field_value);
					$do['cf_'.$field_id] = array('value' => $field_value);
					break;
					
				case Model_CustomField::TYPE_DROPDOWN:
					@$field_value = DevblocksPlatform::importGPC($_POST['field_'.$field_id],'string','');
					$do['cf_'.$field_id] = array('value' => $field_value);
					break;
					
				case Model_CustomField::TYPE_CHECKBOX:
					@$field_value = DevblocksPlatform::importGPC($_POST['field_'.$field_id],'integer',0);
					$do['cf_'.$field_id] = array('value' => !empty($field_value) ? 1 : 0);
					break;

				case Model_CustomField::TYPE_FILES:
					@$field_value = DevblocksPlatform::importGPC($_POST['field_'.$field_id],'array',array());
					$do['cf_'.$field_id] = array('value' => DevblocksPlatform::sanitizeArray($field_value,'integer',array('nonzero','unique')));
					break;
					
				case Model_CustomField::TYPE_MULTI_CHECKBOX:
					@$field_value = DevblocksPlatform::importGPC($_POST['field_'.$field_id],'array',array());
					$do['cf_'.$field_id] = array('value' => $field_value);
					break;
					
				case Model_CustomField::TYPE_DATE:
					@$field_value = DevblocksPlatform::importGPC($_POST['field_'.$field_id],'string','');
					$do['cf_'.$field_id] = array('value' => $field_value);
					break;
					
				case Model_CustomField::TYPE_WORKER:
					@$field_value = DevblocksPlatform::importGPC($_POST['field_'.$field_id],'string','');
					$do['cf_'.$field_id] = array('value' => $field_value);
					break;
					
			}
		}
		
		return $do;
	}
	
	public static function parseFormPost($context, $field_ids) {
		$fields = DAO_CustomField::getByContext($context);
		$results = array();
		
		if(is_array($field_ids))
		foreach($field_ids as $field_id) {
			if(!isset($fields[$field_id]))
				continue;
		
			$field_value = null;
			
			switch($fields[$field_id]->type) {
				case Model_CustomField::TYPE_FILES:
				case Model_CustomField::TYPE_MULTI_CHECKBOX:
					@$field_value = DevblocksPlatform::importGPC($_REQUEST['field_'.$field_id],'array',array());
					break;
					
				case Model_CustomField::TYPE_CHECKBOX:
				case Model_CustomField::TYPE_DATE:
				case Model_CustomField::TYPE_DROPDOWN:
				case Model_CustomField::TYPE_FILE:
				case Model_CustomField::TYPE_MULTI_LINE:
				case Model_CustomField::TYPE_LINK:
				case Model_CustomField::TYPE_NUMBER:
				case Model_CustomField::TYPE_SINGLE_LINE:
				case Model_CustomField::TYPE_URL:
				case Model_CustomField::TYPE_WORKER:
				default:
					@$field_value = DevblocksPlatform::importGPC($_REQUEST['field_'.$field_id],'string','');
					break;
			}
			
			$results[$field_id] = $field_value;
		}
		
		return $results;
	}
	
	public static function handleFormPost($context, $context_id, $field_ids) {
		$field_values = self::parseFormPost($context, $field_ids);
		self::formatAndSetFieldValues($context, $context_id, $field_values);
		return true;
	}

	private static function _linkCustomFieldsets($context, $context_id, &$field_values) {
		/*
		 * If we have a request variable with hints about new custom fieldsets, use it
		 */
		if(isset($_REQUEST['custom_fieldset_adds'])) {
			@$custom_fieldset_adds = DevblocksPlatform::importGPC($_REQUEST['custom_fieldset_adds'], 'array', array());
			
			if(is_array($custom_fieldset_adds))
			foreach($custom_fieldset_adds as $cfset_id) {
				if(empty($cfset_id))
					continue;
			
				DAO_ContextLink::setLink($context, $context_id, CerberusContexts::CONTEXT_CUSTOM_FIELDSET, $cfset_id);
			}
			
		/*
		 * Otherwise, if the request variable doesn't exist we need to introspect the cfields
		 * and look for fieldsets.
		 */
		} else {
			$custom_fields = DAO_CustomField::getAll();
			$custom_fieldsets = DAO_CustomFieldset::getByContextLink($context, $context_id);
	
			foreach(array_keys($field_values) as $field_id) {
				if(!isset($custom_fields[$field_id]))
					continue;
				
				@$cfset_id = $custom_fields[$field_id]->custom_fieldset_id;
				
				if($cfset_id && !isset($custom_fieldsets[$cfset_id])) {
					DAO_ContextLink::setLink($context, $context_id, CerberusContexts::CONTEXT_CUSTOM_FIELDSET, $cfset_id);
				}
			}
		}
		
		/*
		 * If we have a request variable hint about removing fieldsets, do that now
		 */
		@$custom_fieldset_deletes = DevblocksPlatform::importGPC($_REQUEST['custom_fieldset_deletes'], 'array', array());
		
		if(is_array($custom_fieldset_deletes))
		foreach($custom_fieldset_deletes as $cfset_id) {
			if(empty($cfset_id))
				continue;
		
			$custom_fieldset = DAO_CustomFieldset::get($cfset_id);
			$custom_fieldset_fields = $custom_fieldset->getCustomFields();
			
			// Remove the custom field values
			if(is_array($custom_fieldset_fields))
			foreach(array_keys($custom_fieldset_fields) as $cf_id) {
				// Remove any data for this field on this record
				DAO_CustomFieldValue::unsetFieldValue($context, $context_id, $cf_id);
				
				// Remove any field values we're currently setting
				if(isset($field_values[$cf_id]))
					unset($field_values[$cf_id]);
			}
			
			// Break the context link
			DAO_ContextLink::deleteLink($context, $context_id, CerberusContexts::CONTEXT_CUSTOM_FIELDSET, $cfset_id);
		}
		
		return true;
	}
	
	public static function getValuesByContextIds($context, $context_ids, $only_field_ids=null) {
		if(is_null($context_ids))
			return array();
		
		elseif(!is_array($context_ids))
			$context_ids = array($context_ids);

		if(empty($context_ids))
			return array();
			
		$db = DevblocksPlatform::getDatabaseService();
		
		// Only check the custom fields of this context
		$fields = DAO_CustomField::getByContext($context);
		
		if(is_array($only_field_ids))
		$fields = array_filter($fields, function($item) use ($only_field_ids) {
			return in_array($item->id, $only_field_ids);
		});
		
		$tables = array();
		$sqls = array();
		
		if(empty($fields) || !is_array($fields))
			return array();

		// Default $results to all null values
		$null_values = array_combine(array_keys($fields), array_fill(0, count($fields), null));
		$results = array_combine($context_ids, array_fill(0, count($context_ids), $null_values));
		
		/*
		 * Only scan the tables where this context has custom fields.  For example,
		 * if we only have a string custom field defined on tickets, we only need to
		 * check one table out of the three.
		 */

		if(is_array($fields))
		foreach($fields as $cfield_id => $cfield) { /* @var $cfield Model_CustomField */
			$tables[] = DAO_CustomFieldValue::getValueTableName($cfield_id);
		}
		
		if(empty($tables))
			return array();
		
		$tables = array_unique($tables);

		if(is_array($tables))
		foreach($tables as $table) {
			if(empty($table))
				continue;
		
			$sqls[] = sprintf("SELECT context_id, field_id, field_value ".
				"FROM %s ".
				"WHERE context = '%s' AND context_id IN (%s)",
				$table,
				$context,
				implode(',', $context_ids)
			);
		}
		
		if(empty($sqls))
			return array();
		
		/*
		 * UNION the custom field queries into a single statement so we don't have to
		 * merge them in PHP from different resultsets.
		 */
		
		$sql = implode(' UNION ALL ', $sqls);
		if(false == ($rs = $db->ExecuteSlave($sql)))
			return false;
		
		if(!($rs instanceof mysqli_result))
			return false;
		
		while($row = mysqli_fetch_assoc($rs)) {
			$context_id = intval($row['context_id']);
			$field_id = intval($row['field_id']);
			$field_value = $row['field_value'];
			
			if(!isset($fields[$field_id]))
				continue;
			
			if(!isset($results[$context_id]))
				$results[$context_id] = $null_values;
				
			$ptr =& $results[$context_id];
			
			// If multiple value type (multi-checkbox)
			switch($fields[$field_id]->type) {
				case Model_CustomField::TYPE_FILES:
				case Model_CustomField::TYPE_MULTI_CHECKBOX:
					if(!isset($ptr[$field_id]))
						$ptr[$field_id] = array();
						
					$ptr[$field_id][$field_value] = $field_value;
					break;
					
				default:
					$ptr[$field_id] = $field_value;
					break;
			}
		}
		
		mysqli_free_result($rs);
		
		return $results;
	}
	
	public static function deleteByContextIds($context, $context_ids) {
		$db = DevblocksPlatform::getDatabaseService();
		
		if(!is_array($context_ids)) $context_ids = array($context_ids);
		$ids_list = implode(',', $context_ids);

		$tables = array('custom_field_stringvalue','custom_field_clobvalue','custom_field_numbervalue');
		
		if(!empty($context_ids))
		foreach($tables as $table) {
			$sql = sprintf("DELETE FROM %s WHERE context = %s AND context_id IN (%s)",
				$table,
				$db->qstr($context),
				implode(',', $context_ids)
			);
			if(false == ($db->ExecuteMaster($sql)))
				return false;
		}
	}
	
	public static function deleteByFieldId($field_id) {
		$db = DevblocksPlatform::getDatabaseService();

		$tables = array('custom_field_stringvalue','custom_field_clobvalue','custom_field_numbervalue');

		foreach($tables as $table) {
			$sql = sprintf("DELETE FROM %s WHERE field_id = %d",
				$table,
				$field_id
			);
			if(false == ($db->ExecuteMaster($sql)))
				return false;
		}
	}
};

class Model_CustomField {
	const TYPE_CHECKBOX = 'C';
	const TYPE_DATE = 'E';
	const TYPE_DROPDOWN = 'D';
	const TYPE_FILE = 'F';
	const TYPE_FILES = 'I';
	const TYPE_LINK = 'L';
	const TYPE_NUMBER = 'N';
	const TYPE_SINGLE_LINE = 'S';
	const TYPE_MULTI_CHECKBOX = 'X';
	const TYPE_MULTI_LINE = 'T';
	const TYPE_URL = 'U';
	const TYPE_WORKER = 'W';
	
	public $id = 0;
	public $name = '';
	public $type = '';
	public $custom_fieldset_id = 0;
	public $context = '';
	public $pos = 0;
	public $params = array();
	
	static function getTypes() {
		// [TODO] Extension provided custom field types
		
		$fields = array(
			self::TYPE_CHECKBOX => 'Checkbox',
			self::TYPE_DATE => 'Date',
			self::TYPE_DROPDOWN => 'Picklist',
			self::TYPE_FILE => 'File',
			self::TYPE_FILES => 'Files: Multiple',
			self::TYPE_MULTI_CHECKBOX => 'Multiple Checkboxes',
			self::TYPE_MULTI_LINE => 'Text: Multiple Lines',
			self::TYPE_LINK => 'Record Link',
			self::TYPE_NUMBER => 'Number',
			self::TYPE_SINGLE_LINE => 'Text: Single Line',
			self::TYPE_URL => 'URL',
			self::TYPE_WORKER => 'Worker',
		);
		
		asort($fields);
		
		return $fields;
	}
	
	static function hasMultipleValues($type) {
		$multiple_types = array(Model_CustomField::TYPE_MULTI_CHECKBOX, Model_CustomField::TYPE_FILES);
		return in_array($type, $multiple_types);
	}
};

class Context_CustomField extends Extension_DevblocksContext {
	static function isReadableByActor($models, $actor) {
		// Everyone can read
		return CerberusContexts::allowEverything($models);
	}
	
	static function isWriteableByActor($models, $actor) {
		// Admins can modify
		
		if(false == ($actor = CerberusContexts::polymorphActorToDictionary($actor)))
			CerberusContexts::denyEverything($models);
		
		if(CerberusContexts::isActorAnAdmin($actor))
			return CerberusContexts::allowEverything($models);
		
		if(false == ($dicts = CerberusContexts::polymorphModelsToDictionaries($models, CerberusContexts::CONTEXT_GROUP)))
			return CerberusContexts::denyEverything($models);
		
		$results = array_fill_keys(array_keys($dicts), false);
		
		foreach($dicts as $id => $dict) {
			// If not in a fieldset, skip
			if(!$dict->custom_fieldset_id)
				continue;
			
			// If in a fieldset, owner delegate can modify
			$results[$id] = CerberusContexts::isWriteableByDelegateOwner($actor, CerberusContexts::CONTEXT_CUSTOM_FIELD, $dict, 'custom_fieldset_owner_');
		}
		
		if(is_array($models)) {
			return $results;
		} else {
			return array_shift($results);
		}
	}
	
	function getRandom() {
		return DAO_CustomField::random();
	}
	
	function getMeta($context_id) {
		$url_writer = DevblocksPlatform::getUrlService();
		
		$field = DAO_CustomField::get($context_id);
		
		return array(
			'id' => $field->id,
			'name' => $field->name,
			'permalink' => null, //$url_writer->writeNoProxy('', true),
			'updated' => 0,
		);
	}
	
	function getContext($cfield, &$token_labels, &$token_values, $prefix=null) {
		if(is_null($prefix))
			$prefix = 'Custom Field:';
			
		$translate = DevblocksPlatform::getTranslationService();
		
		// Polymorph
		if(is_numeric($cfield)) {
			$cfield = DAO_CustomField::get($cfield);
 		} elseif($cfield instanceof Model_CustomField) {
			// It's what we want already.
		} elseif(is_array($cfield)) {
			$cfield = Cerb_ORMHelper::recastArrayToModel($cfield, 'Model_CustomField');
		} else {
			$cfield = null;
		}
			
		// Token labels
		$token_labels = array(
			'_label' => $prefix,
			'id' => $prefix.$translate->_('common.id'),
			'name' => $prefix.$translate->_('common.name'),
		);
		
		// Token types
		$token_types = array(
			'_label' => 'context_url',
			'id' => Model_CustomField::TYPE_NUMBER,
			'name' => Model_CustomField::TYPE_SINGLE_LINE,
		);
		
		// Token values
		$token_values = array();
		
		$token_values['_context'] = CerberusContexts::CONTEXT_CUSTOM_FIELD;
		$token_values['_types'] = $token_types;
		
		// Worker token values
		if(null != $cfield) {
			$token_values['_loaded'] = true;
			$token_values['_label'] = $cfield->name;
			$token_values['context'] = $cfield->context;
			$token_values['custom_fieldset_id'] = $cfield->custom_fieldset_id;
			$token_values['id'] = $cfield->id;
			$token_values['name'] = $cfield->name;
			$token_values['type'] = $cfield->type;
			$token_values['pos'] = $cfield->pos;
			
			if(!empty($cfield->params))
				$token_values['params'] = $cfield->params;
		}
		
		// Custom fieldset
		$merge_token_labels = array();
		$merge_token_values = array();
		CerberusContexts::getContext(CerberusContexts::CONTEXT_CUSTOM_FIELDSET, null, $merge_token_labels, $merge_token_values, '', true);

		CerberusContexts::merge(
			'custom_fieldset_',
			$prefix.'Fieldset:',
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
		
		$context = CerberusContexts::CONTEXT_CUSTOM_FIELD;
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
		return null;
	}
	
	function getView($context=null, $context_id=null, $options=array(), $view_id=null) {
		return null;
	}
};