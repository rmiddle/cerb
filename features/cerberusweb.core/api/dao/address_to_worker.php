<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2002-2014, Webgroup Media LLC
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

class DAO_AddressToWorker { // extends DevblocksORMHelper
	const _CACHE_ALL = 'cerb:dao:address_to_worker:all';
	
	const ADDRESS = 'address';
	const WORKER_ID = 'worker_id';
	const IS_CONFIRMED = 'is_confirmed';
	const CODE = 'code';
	const CODE_EXPIRE = 'code_expire';

	static function assign($address, $worker_id, $is_confirmed=false) {
		$db = DevblocksPlatform::getDatabaseService();
		
		// Force lowercase
		$address = trim(strtolower($address));

		if(empty($address) || empty($worker_id))
			return NULL;

		$sql = sprintf("INSERT INTO address_to_worker (address, worker_id, is_confirmed, code, code_expire) ".
			"VALUES (%s, %d, %d, '', 0)",
			$db->qstr($address),
			$worker_id,
			($is_confirmed ? 1 : 0)
		);
		$db->Execute($sql);

		self::clearCache();
		
		return $address;
	}

	static function unassign($address) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$address = trim(strtolower($address));
		
		if(empty($address))
			return NULL;
			
		$sql = sprintf("DELETE FROM address_to_worker WHERE address = %s",
			$db->qstr($address)
		);
		$db->Execute($sql);
		
		self::clearCache();
	}
	
	static function unassignAll($worker_id) {
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($worker_id))
			return NULL;
			
		$sql = sprintf("DELETE FROM address_to_worker WHERE worker_id = %d",
			$worker_id
		);
		$db->Execute($sql);
		
		self::clearCache();
	}
	
	static function update($addresses, $fields) {
		if(!is_array($addresses)) $addresses = array($addresses);
		
		$db = DevblocksPlatform::getDatabaseService();
		$sets = array();
		
		if(!is_array($fields) || empty($fields) || empty($addresses))
			return;
		
		foreach($fields as $k => $v) {
			if(is_null($v))
				$value = 'NULL';
			else
				$value = $db->qstr($v);
			
			$sets[] = sprintf("%s = %s",
				$k,
				$value
			);
		}
		
		$sql = sprintf("UPDATE %s SET %s WHERE %s IN ('%s')",
			'address_to_worker',
			implode(', ', $sets),
			self::ADDRESS,
			implode("','", $addresses)
		);
		$db->Execute($sql);
		
		self::clearCache();
	}
	
	/**
	 * Enter description here...
	 *
	 * @param integer $worker_id
	 * @return Model_AddressToWorker[]
	 */
	static function getByWorker($worker_id) {
		$addresses = self::getAll();
		
		$addresses = array_filter($addresses, function($address) use ($worker_id) {
			return ($address->worker_id == $worker_id);
		});
		
		return $addresses;
	}
	
	static function getByWorkers() {
		$addys = self::getAll();
		$workers = DAO_Worker::getAll();
		
		array_walk($addys, function($addy) use ($workers) {
			if(!$addy->is_confirmed)
				return;
			
			if(!isset($workers[$addy->worker_id]))
				return;
			
			if(!isset($workers[$addy->worker_id]->relay_emails))
				$workers[$addy->worker_id]->relay_emails = array();
				
			$workers[$addy->worker_id]->relay_emails[] = $addy->address;
		});
		
		return $workers;
	}
	
	/**
	 * Enter description here...
	 *
	 * @param integer $address
	 * @return Model_AddressToWorker
	 */
	static function getByAddress($address) {
		$addresses = self::getAll(); // Use the cache
		$address = strtolower($address);
		
		if(isset($addresses[$address]))
			return $addresses[$address];
			
		return NULL;
	}
	
	static function getAll($nocache=false, $with_disabled=false) {
		$cache = DevblocksPlatform::getCacheService();
		
		if($nocache || null === ($results = $cache->load(self::_CACHE_ALL))) {
			$addresses = self::getWhere();
			$results = array();
			
			if(is_array($addresses))
			foreach($addresses as $address) {
				$results[$address->address] = $address;
			}
			
			$cache->save($results, self::_CACHE_ALL);
		}
		
		if(!$with_disabled) {
			$workers = DAO_Worker::getAll();
			
			$results = array_filter($results, function($address) use ($workers) {
				@$worker = $workers[$address->worker_id];
				return !(empty($worker) || $worker->is_disabled);
			});
		}
		
		return $results;
	}
	
	static function getWhere($where=null) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = "SELECT address, worker_id, is_confirmed, code, code_expire ".
			"FROM address_to_worker ".
			(!empty($where) ? sprintf("WHERE %s ", $where) : " ").
			"ORDER BY address";
		$rs = $db->Execute($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg());

		return self::_getObjectsFromResult($rs);
	}
	
	/**
	 * Enter description here...
	 *
	 * @param resource $rs
	 * @return Model_AddressToWorker[]
	 */
	private static function _getObjectsFromResult($rs) {
		$objects = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object = new Model_AddressToWorker();
			$object->worker_id = intval($row['worker_id']);
			$object->address = strtolower($row['address']);
			$object->is_confirmed = intval($row['is_confirmed']);
			$object->code = $row['code'];
			$object->code_expire = intval($row['code_expire']);
			$objects[$object->address] = $object;
		}
		
		mysqli_free_result($rs);
		
		return $objects;
	}
	
	public static function clearCache() {
		$cache = DevblocksPlatform::getCacheService();
		$cache->remove(self::_CACHE_ALL);
	}
};

class Model_AddressToWorker {
	public $address;
	public $worker_id;
	public $is_confirmed;
	public $code;
	public $code_expire;
};