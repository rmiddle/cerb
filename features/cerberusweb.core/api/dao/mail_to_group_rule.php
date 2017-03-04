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

class DAO_MailToGroupRule extends Cerb_ORMHelper {
	const _CACHE_ALL = 'cerb:dao:mail_to_group_rule:all';
	
	const ID = 'id';
	const POS = 'pos';
	const CREATED = 'created';
	const NAME = 'name';
	const CRITERIA_SER = 'criteria_ser';
	const ACTIONS_SER = 'actions_ser';
	const IS_STICKY = 'is_sticky';
	const STICKY_ORDER = 'sticky_order';

	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = sprintf("INSERT INTO mail_to_group_rule (created) ".
			"VALUES (%d)",
			time()
		);
		$db->ExecuteMaster($sql);
		$id = $db->LastInsertId();
		
		self::update($id, $fields);
		
		return $id;
	}
	
	static function update($ids, $fields) {
		parent::_update($ids, 'mail_to_group_rule', $fields);
		
		self::clearCache();
	}
	
	static function getAll($nocache=false) {
		$cache = DevblocksPlatform::getCacheService();
		
		if($nocache || null === ($results = $cache->load(self::_CACHE_ALL))) {
			$results = self::getWhere(
				null,
				array(DAO_MailToGroupRule::IS_STICKY, DAO_MailToGroupRule::STICKY_ORDER, DAO_MailToGroupRule::POS),
				array(false, true, false),
				null,
				Cerb_ORMHelper::OPT_GET_MASTER_ONLY
			);
			
			if(!is_array($results))
				return false;
			
			$cache->save($results, self::_CACHE_ALL, array(), 1200); // 20 mins
		}
		
		return $results;
	}
	
	/**
	 * @param string $where
	 * @return Model_MailToGroupRule[]
	 */
	static function getWhere($where=null, $sortBy=null, $sortAsc=true, $limit=null, $options=null) {
		$db = DevblocksPlatform::getDatabaseService();
		
		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		// SQL
		$sql = "SELECT id, pos, created, name, criteria_ser, actions_ser, is_sticky, sticky_order ".
			"FROM mail_to_group_rule ".
			$where_sql .
			$sort_sql .
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
	 * @param integer $id
	 * @return Model_MailToGroupRule
	 */
	static function get($id) {
		if(empty($id))
			return null;
		
		$objects = self::getAll();
		
		if(isset($objects[$id]))
			return $objects[$id];
		
		return null;
	}
	
	/**
	 * @param resource $rs
	 * @return Model_MailToGroupRule[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		if(!($rs instanceof mysqli_result))
			return false;
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object = new Model_MailToGroupRule();
			$object->id = $row['id'];
			$object->pos = $row['pos'];
			$object->created = $row['created'];
			$object->name = $row['name'];
			$criteria_ser = $row['criteria_ser'];
			$actions_ser = $row['actions_ser'];
			$object->is_sticky = $row['is_sticky'];
			$object->sticky_order = $row['sticky_order'];

			$object->criteria = (!empty($criteria_ser)) ? @unserialize($criteria_ser) : array();
			$object->actions = (!empty($actions_ser)) ? @unserialize($actions_ser) : array();

			$objects[$object->id] = $object;
		}
		
		mysqli_free_result($rs);
		
		return $objects;
	}
	
	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		
		if(empty($ids))
			return;
		
		$db = DevblocksPlatform::getDatabaseService();
		
		$ids_list = implode(',', $ids);
		
		$db->ExecuteMaster(sprintf("DELETE FROM mail_to_group_rule WHERE id IN (%s)", $ids_list));
		
		self::clearCache();
		
		return true;
	}

	/**
	 * Increment the number of times we've matched this rule
	 *
	 * @param integer $id
	 */
	static function increment($id, $by=1) {
		$db = DevblocksPlatform::getDatabaseService();
		$db->ExecuteMaster(sprintf("UPDATE mail_to_group_rule SET pos = pos + %d WHERE id = %d",
			$by,
			$id
		));
	}
	
	static function clearCache() {
		$cache = DevblocksPlatform::getCacheService();
		$cache->remove(self::_CACHE_ALL);
	}
};

class Model_MailToGroupRule {
	public $id = 0;
	public $pos = 0;
	public $created = 0;
	public $name = '';
	public $criteria = array();
	public $actions = array();
	public $is_sticky = 0;
	public $sticky_order = 0;
	
	static function getMatches(Model_Address $fromAddress, CerberusParserMessage $message) {
		$matches = array();
		$rules = DAO_MailToGroupRule::getAll();
		$message_headers = $message->headers;
		$custom_fields = DAO_CustomField::getAll();
		
		// Lazy load when needed on criteria basis
		$address_field_values = null;
		$org_field_values = null;
		
		// Check filters
		if(is_array($rules))
		foreach($rules as $rule) { /* @var $rule Model_MailToGroupRule */
			$passed = 0;

			// check criteria
			foreach($rule->criteria as $crit_key => $crit) {
				@$value = $crit['value'];
							
				switch($crit_key) {
					case 'dayofweek':
						$current_day = strftime('%w');
//						$current_day = 1;

						// Forced to English abbrevs as indexes
						$days = array('sun','mon','tue','wed','thu','fri','sat');
						
						// Is the current day enabled?
						if(isset($crit[$days[$current_day]])) {
							$passed++;
						}
							
						break;
						
					case 'timeofday':
						$current_hour = strftime('%H');
						$current_min = strftime('%M');
//						$current_hour = 17;
//						$current_min = 5;

						if(null != ($from_time = @$crit['from']))
							list($from_hour, $from_min) = explode(':', $from_time);
						
						if(null != ($to_time = @$crit['to']))
							if(list($to_hour, $to_min) = explode(':', $to_time));

						// Do we need to wrap around to the next day's hours?
						if($from_hour > $to_hour) { // yes
							$to_hour += 24; // add 24 hrs to the destination (1am = 25th hour)
						}
							
						// Are we in the right 24 hourly range?
						if((integer)$current_hour >= $from_hour && (integer)$current_hour <= $to_hour) {
							// If we're in the first hour, are we minutes early?
							if($current_hour==$from_hour && (integer)$current_min < $from_min)
								break;
							// If we're in the last hour, are we minutes late?
							if($current_hour==$to_hour && (integer)$current_min > $to_min)
								break;
								
							$passed++;
						}

						break;
					
					case 'tocc':
						$tocc = array();
						$destinations = DevblocksPlatform::parseCsvString($value);

						// Build a list of To/Cc addresses on this message
						@$to_list = imap_rfc822_parse_adrlist($message_headers['to'],'localhost');
						@$cc_list = imap_rfc822_parse_adrlist($message_headers['cc'],'localhost');
						
						if(is_array($to_list))
						foreach($to_list as $addy) {
							if(!isset($addy->mailbox) || !isset($addy->host))
								continue;
						
							$tocc[] = $addy->mailbox . '@' . $addy->host;
						}
						
						if(is_array($cc_list))
						foreach($cc_list as $addy) {
							if(!isset($addy->mailbox) || !isset($addy->host))
								continue;
							
							$tocc[] = $addy->mailbox . '@' . $addy->host;
						}
						
						$dest_flag = false; // bail out when true
						if(is_array($destinations) && is_array($tocc))
						foreach($destinations as $dest) {
							if($dest_flag)
								break;
								
							$regexp_dest = DevblocksPlatform::strToRegExp($dest);
							
							foreach($tocc as $addy) {
								if(@preg_match($regexp_dest, $addy)) {
									$passed++;
									$dest_flag = true;
									break;
								}
							}
						}
						break;
						
					case 'from':
						$regexp_from = DevblocksPlatform::strToRegExp($value);
						if(@preg_match($regexp_from, $fromAddress->email)) {
							$passed++;
						}
						break;
						
					case 'subject':
						// [TODO] Decode if necessary
						@$subject = $message_headers['subject'];

						$regexp_subject = DevblocksPlatform::strToRegExp($value);
						if(@preg_match($regexp_subject, $subject)) {
							$passed++;
						}
						break;

					case 'body':
						// Line-by-line body scanning (sed-like)
						$lines = preg_split("/[\r\n]/", $message->body);
						if(is_array($lines))
						foreach($lines as $line) {
							if(@preg_match($value, $line)) {
								$passed++;
								break;
							}
						}
						break;
						
					case 'header1':
					case 'header2':
					case 'header3':
					case 'header4':
					case 'header5':
						@$header = DevblocksPlatform::strLower($crit['header']);
						@$header_value = is_array($message_headers[$header]) ? implode(" ", $message_headers[$header]) : (string) $message_headers[$header];
						
						if(empty($header)) {
							$passed++;
							break;
						}
						
						if(empty($value)) { // we're checking for null/blanks
							if(empty($header_value)) {
								$passed++;
							}
							
						} elseif($header_value) {
							$regexp_header = DevblocksPlatform::strToRegExp($value);
							
							// Flatten CRLF
							if(@preg_match($regexp_header, $header_value)) {
								$passed++;
							}
						}
						
						break;
						
					default: // ignore invalids
						// Custom Fields
						if(0==strcasecmp('cf_',substr($crit_key,0,3))) {
							$field_id = substr($crit_key,3);

							// Make sure it exists
							if(null == (@$field = $custom_fields[$field_id]))
								continue;

							// Lazy values loader
							$field_values = array();
							switch($field->context) {
								case CerberusContexts::CONTEXT_ADDRESS:
									if(null == $address_field_values)
										$address_field_values = array_shift(DAO_CustomFieldValue::getValuesByContextIds(CerberusContexts::CONTEXT_ADDRESS, $fromAddress->id));
									$field_values =& $address_field_values;
									break;
								case CerberusContexts::CONTEXT_ORG:
									if(null == $org_field_values)
										$org_field_values = array_shift(DAO_CustomFieldValue::getValuesByContextIds(CerberusContexts::CONTEXT_ORG, $fromAddress->contact_org_id));
									$field_values =& $org_field_values;
									break;
							}
							
							// No values, default.
							if(!isset($field_values[$field_id]))
								continue;
							
							// Type sensitive value comparisons
							switch($field->type) {
								case 'S': // string
								case 'T': // clob
								case 'U': // URL
									$field_val = isset($field_values[$field_id]) ? $field_values[$field_id] : '';
									$oper = isset($crit['oper']) ? $crit['oper'] : "=";
									
									if($oper == "=" && @preg_match(DevblocksPlatform::strToRegExp($value, true), $field_val))
										$passed++;
									elseif($oper == "!=" && @!preg_match(DevblocksPlatform::strToRegExp($value, true), $field_val))
										$passed++;
									break;
								case 'N': // number
									if(!isset($field_values[$field_id]))
										break;

									$field_val = isset($field_values[$field_id]) ? $field_values[$field_id] : 0;
									$oper = isset($crit['oper']) ? $crit['oper'] : "=";
									
									if($oper=="=" && intval($field_val)==intval($value))
										$passed++;
									elseif($oper=="!=" && intval($field_val)!=intval($value))
										$passed++;
									elseif($oper==">" && intval($field_val) > intval($value))
										$passed++;
									elseif($oper=="<" && intval($field_val) < intval($value))
										$passed++;
									break;
								case 'E': // date
									$field_val = isset($field_values[$field_id]) ? intval($field_values[$field_id]) : 0;
									$from = isset($crit['from']) ? $crit['from'] : "0";
									$to = isset($crit['to']) ? $crit['to'] : "now";
									
									if(intval(@strtotime($from)) <= $field_val && intval(@strtotime($to)) >= $field_val) {
										$passed++;
									}
									break;
								case 'C': // checkbox
									$field_val = isset($field_values[$field_id]) ? $field_values[$field_id] : 0;
									if(intval($value)==intval($field_val))
										$passed++;
									break;
								case 'D': // dropdown
								case 'X': // multi-checkbox
								case 'W': // worker
									$field_val = isset($field_values[$field_id]) ? $field_values[$field_id] : array();
									if(!is_array($value)) $value = array($value);
										
									if(is_array($field_val)) { // if multiple things set
										foreach($field_val as $v) { // loop through possible
											if(isset($value[$v])) { // is any possible set?
												$passed++;
												break;
											}
										}
										
									} else { // single
										if(isset($value[$field_val])) { // is our set field in possibles?
											$passed++;
											break;
										}
										
									}
									break;
							}
						}
						break;
				}
			}
			
			// If our rule matched every criteria, stop and return the filter
			if($passed == count($rule->criteria)) {
				DAO_MailToGroupRule::increment($rule->id); // ++ the times we've matched
				$matches[$rule->id] = $rule;
				
				// Bail out if this rule had a move action
				if(isset($rule->actions['move']))
					return $matches;
			}
		}
		
		// If we're at the end of rules and didn't bail out yet
		if(!empty($matches))
			return $matches;
		
		// No matches
		return NULL;
	}
	
	/**
	 * @param CerberusParserModel $model
	 */
	function run(CerberusParserModel &$model) {
		if(null == $model->getTicketId())
			return;
		
		$groups = DAO_Group::getAll();
		$buckets = DAO_Bucket::getAll();
		$custom_fields = DAO_CustomField::getAll();
		
		// Update the model using actions
		if(is_array($this->actions))
		foreach($this->actions as $action => $params) {
			switch($action) {
				case 'move':
					if(isset($params['group_id']) && isset($groups[$params['group_id']])) {
						$model->setGroupId($params['group_id']);
					}
					
				default:
					// Custom fields
					if(substr($action,0,3)=="cf_") {
						$field_id = intval(substr($action,3));
						
						if(!isset($custom_fields[$field_id]) || !isset($params['value']))
							break;

						// Persist in the model
						$model->getMessage()->custom_fields[] = array(
							'field_id' => $field_id,
							'context' => CerberusContexts::CONTEXT_TICKET,
							'context_id' => $model->getTicketId(),
							'value' => $params['value'],
						);
					}
					break;
			}
		}
	}
};