<?php
class DAO_ContextAlias extends Cerb_ORMHelper {
	const CONTEXT = 'context';
	const ID = 'id';
	const MAME = 'name';
	const TERMS = 'terms';
	
	static function get($context, $id, $with_primary=false) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$results = $db->GetArraySlave(sprintf("SELECT name FROM context_alias WHERE context = %s AND id = %d %s",
			$db->qstr($context),
			$id,
			($with_primary ? 'ORDER BY is_primary DESC' : 'AND is_primary = 0')
		));
		
		return array_column($results, 'name');
	}
	
	static function set($context, $id, array $aliases) {
		if(empty($context) || empty($id) || empty($aliases))
			return false;
		
		$aliases = array_unique($aliases);
		
		$db = DevblocksPlatform::getDatabaseService();
		$bayes = DevblocksPlatform::getBayesClassifierService();
		
		self::delete($context, $id);
		
		$values = [];
		
		foreach($aliases as $idx => $alias) {
			$terms = DevblocksPlatform::strAlphaNum($alias, ' ', '');
			$tokens = $bayes::preprocessWordsPad($bayes::tokenizeWords($terms), 4);
			
			$values[] = sprintf("(%s,%d,%s,%s,%d)",
				$db->qstr($context),
				$id,
				$db->qstr($alias),
				$db->qstr(implode(' ', $tokens)),
				$idx == 0 ? 1 : 0
			);
		}
		
		if(!empty($values)) {
			$sql = sprintf("INSERT IGNORE INTO context_alias (context,id,name,terms,is_primary) VALUES %s",
				implode(',', $values)
			);
			$db->ExecuteMaster($sql);
		}
	}
	
	/*
	static function upsert($context, $id, array $aliases) {
		if(empty($context) || empty($id) || empty($aliases))
			return false;
		
		$aliases = array_unique($aliases);
		
		$db = DevblocksPlatform::getDatabaseService();
		$bayes = DevblocksPlatform::getBayesClassifierService();
		
		$values = [];
		
		foreach($aliases as $idx => $alias) {
			$terms = DevblocksPlatform::strAlphaNum($alias, ' ', '');
			$tokens = $bayes::preprocessWordsPad($bayes::tokenizeWords($terms), 4);
			
			$values[] = sprintf("(%s,%d,%s,%s,%d)",
				$db->qstr($context),
				$id,
				$db->qstr($alias),
				$db->qstr(implode(' ', $tokens)),
				$idx == 0 ? 1 : 0
			);
		}
		
		if(!empty($values)) {
			$sql = sprintf("REPLACE IGNORE INTO context_alias (context,id,name,terms,is_primary) VALUES %s",
				implode(',', $values)
			);
			$db->ExecuteMaster($sql);
		}
	}
	*/
	
	static function delete($context, $ids) {
		if(!is_array($ids)) {
			if(!is_numeric($ids))
				return false;
			
			$ids = [$ids];
		}
		
		$ids_string = implode(',', DevblocksPlatform::sanitizeArray($ids, 'int'));
		
		if(empty($ids_string))
			return false;
		
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = sprintf("DELETE FROM context_alias WHERE context = %s AND id IN (%s)",
			$db->qstr($context),
			$ids_string
		);
		return $db->ExecuteMaster($sql);
	}
};