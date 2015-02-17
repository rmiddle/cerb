<?php
class _DevblocksDatabaseManager {
	static $instance = null;
	
	private $_connections = array();
	private $_last_used_db = null;
	
	private function __construct() {
		// We lazy load the connections
	}
	
	public function __get($name) {
		switch($name) {
			case '_master_db':
				return $this->_connectMaster();
				break;
				
			case '_slave_db':
				return $this->_connectSlave();
				break;
		}
		
		return null;
	}
	
	static function getInstance() {
		if(null == self::$instance) {
			// Bail out early for pre-install
			if(!defined('APP_DB_HOST') || !APP_DB_HOST)
				return null;
			
			self::$instance = new _DevblocksDatabaseManager();
		}
		
		return self::$instance;
	}
	
	private function _connectMaster() {
		// Reuse an existing connection for this request
		if(isset($this->_connections['master']))
			return $this->_connections['master'];
		
		$persistent = (defined('APP_DB_PCONNECT') && APP_DB_PCONNECT) ? true : false;
		
		if(false == ($db = $this->_connect(APP_DB_HOST, APP_DB_USER, APP_DB_PASS, APP_DB_DATABASE, $persistent)))
			die("[Cerb] Error connecting to the master database. Please check MySQL and the framework.config.php settings.");
		
		$this->_connections['master'] = $db;
		
		return $db;
	}
	
	private function _connectSlave() {
		// Reuse an existing connection for this request
		if(isset($this->_connections['slave']))
			return $this->_connections['slave'];
		
		// Use the master if we don't have a slave defined
		if(!defined('APP_DB_SLAVE_HOST') || !APP_DB_SLAVE_HOST)
			return $this->_connectMaster();
		
		// Inherit the user/pass from the master if not specified
		$persistent = (defined('APP_DB_PCONNECT') && APP_DB_PCONNECT) ? true : false;
		$user = (defined('APP_DB_SLAVE_USER') && APP_DB_SLAVE_USER) ? APP_DB_SLAVE_USER : APP_DB_USER;
		$pass = (defined('APP_DB_SLAVE_PASS') && APP_DB_SLAVE_PASS) ? APP_DB_SLAVE_PASS : APP_DB_PASS;
		
		if(false == ($db = $this->_connect(APP_DB_SLAVE_HOST, $user, $pass, APP_DB_DATABASE, $persistent)))
			die("[Cerb] Error connecting to the slave database. Please check MySQL and the framework.config.php settings.");
		
		$this->_connections['slave'] = $db;
		
		return $db;
	}
	
	private function _connect($host, $user, $pass, $database, $persistent=false) {
		if($persistent)
			$host = 'p:' . $host;
		
		if(false === ($db = @mysqli_connect($host, $user, $pass, $database)))
			return false;

		// Set the character encoding for this connection
		mysqli_set_charset($db, DB_CHARSET_CODE);
		
		return $db;
	}
	
	function getMasterConnection() {
		return $this->_master_db;
	}
	
	function getSlaveConnection() {
		return $this->_slave_db;
	}
	
	function isConnected() {
		if(empty($this->_connections))
			return false;
		
		foreach($this->_connections as $conn) {
			if(!$conn instanceof mysqli || !mysqli_ping($conn))
				return false;
		}
		
		return true;
	}
	
	// Always master
	function metaTables() {
		$tables = array();
		
		$sql = "SHOW TABLES";
		$rs = $this->GetArrayMaster($sql);
		
		foreach($rs as $row) {
			$table = array_shift($row);
			$tables[$table] = $table;
		}
		
		return $tables;
	}
	
	// Always master
	function metaTablesDetailed() {
		$tables = array();
		
		$sql = "SHOW TABLE STATUS";
		$rs = $this->GetArrayMaster($sql);
		
		foreach($rs as $row) {
			$table = $row['Name'];
			$tables[$table] = $row;
		}
		
		return $tables;
	}
	
	// Always master
	function metaTable($table_name) {
		$columns = array();
		$indexes = array();
		
		$sql = sprintf("SHOW COLUMNS FROM %s", $table_name);
		$rs = $this->GetArrayMaster($sql);
		
		foreach($rs as $row) {
			$field = $row['Field'];
			
			$columns[$field] = array(
				'field' => $field,
				'type' => $row['Type'],
				'null' => $row['Null'],
				'key' => $row['Key'],
				'default' => $row['Default'],
				'extra' => $row['Extra'],
			);
		}
		
		$sql = sprintf("SHOW INDEXES FROM %s", $table_name);
		$rs = $this->GetArrayMaster($sql);

		foreach($rs as $row) {
			$key_name = $row['Key_name'];
			$column_name = $row['Column_name'];

			if(!isset($indexes[$key_name]))
				$indexes[$key_name] = array(
					'columns' => array(),
				);
			
			$indexes[$key_name]['columns'][$column_name] = array(
				'column_name' => $column_name,
				'cardinality' => $row['Cardinality'],
				'index_type' => $row['Index_type'],
			);
		}
		
		return array(
			$columns,
			$indexes
		);
	}
	
	/**
	 * Everything executes against the master by default
	 * 
	 * @deprecated
	 * @param string $sql
	 * @return mysql_result|boolean
	 */
	function Execute($sql) {
		return $this->ExecuteMaster($sql);
	}
	
	function ExecuteMaster($sql) {
		if(DEVELOPMENT_MODE_QUERIES)
			$console = DevblocksPlatform::getConsoleLog('MASTER');
		return $this->_Execute($sql, $this->_master_db);
	}
	
	function ExecuteSlave($sql) {
		if(DEVELOPMENT_MODE_QUERIES)
			$console = DevblocksPlatform::getConsoleLog('SLAVE');
		return $this->_Execute($sql, $this->_slave_db);
	}
	
	private function _Execute($sql, $db) {
		if(DEVELOPMENT_MODE_QUERIES) {
			if($console = DevblocksPlatform::getConsoleLog(null))
				$console->debug($sql);
		}
		
		$this->_last_used_db = $db;
		
		if(false === ($rs = mysqli_query($db, $sql))) {
			$error_msg = sprintf("[%d] %s ::SQL:: %s",
				mysqli_errno($db),
				mysqli_error($db),
				$sql
			);
			
			if(DEVELOPMENT_MODE) {
				trigger_error($error_msg, E_USER_WARNING);
			} else {
				error_log($error_msg);
			}
			
			return false;
		}
		
		return $rs;
	}
	
	// Always slave
	function SelectLimit($sql, $limit, $start=0) {
		$limit = intval($limit);
		$start = intval($start);
		
		if($limit > 0)
			return $this->ExecuteSlave($sql . sprintf(" LIMIT %d,%d", $start, $limit));
		else
			return $this->ExecuteSlave($sql);
	}
	
	function escape($string) {
		return mysqli_real_escape_string($this->_slave_db, $string);
	}
	
	function qstr($string) {
		return "'".mysqli_real_escape_string($this->_slave_db, $string)."'";
	}
	
	/**
	 * Defaults to slave
	 * 
	 * @deprecated
	 * @param string $sql
	 * @return array|boolean
	 */
	function GetArray($sql) {
		return $this->GetArraySlave($sql);
	}
	
	function GetArrayMaster($sql) {
		if(DEVELOPMENT_MODE_QUERIES)
			$console = DevblocksPlatform::getConsoleLog('MASTER');
		return $this->_GetArray($sql, $this->_master_db);
	}
	
	function GetArraySlave($sql) {
		if(DEVELOPMENT_MODE_QUERIES)
			$console = DevblocksPlatform::getConsoleLog('SLAVE');
		return $this->_GetArray($sql, $this->_slave_db);
	}
	
	private function _GetArray($sql, $db) {
		$results = array();
		
		if(false !== ($rs = $this->_Execute($sql, $db))) {
			while($row = mysqli_fetch_assoc($rs)) {
				$results[] = $row;
			}
			mysqli_free_result($rs);
		}
		
		return $results;
	}
	
	/**
	 * Defaults to slave
	 *
	 * @deprecated
	 * @param string $sql
	 * @return array|boolean
	 */
	public function GetRow($sql) {
		return $this->GetRowSlave($sql);
	}
	
	public function GetRowMaster($sql) {
		if(DEVELOPMENT_MODE_QUERIES)
			$console = DevblocksPlatform::getConsoleLog('MASTER');
		return $this->_GetRow($sql, $this->_master_db);
	}
	
	public function GetRowSlave($sql) {
		if(DEVELOPMENT_MODE_QUERIES)
			$console = DevblocksPlatform::getConsoleLog('SLAVE');
		return $this->_GetRow($sql, $this->_slave_db);
	}
	
	private function _GetRow($sql, $db) {
		if($rs = $this->_Execute($sql, $db)) {
			$row = mysqli_fetch_assoc($rs);
			mysqli_free_result($rs);
			return $row;
		}
		return false;
	}
	
	/**
	 * Defaults to slave
	 *  
	 * @deprecated
	 * @param string $sql
	 * @return mixed|boolean
	 */
	function GetOne($sql) {
		return $this->_GetOne($sql, $this->_slave_db);
	}
	
	function GetOneMaster($sql) {
		if(DEVELOPMENT_MODE_QUERIES)
			$console = DevblocksPlatform::getConsoleLog('MASTER');
		return $this->_GetOne($sql, $this->_master_db);
	}
	
	function GetOneSlave($sql) {
		if(DEVELOPMENT_MODE_QUERIES)
			$console = DevblocksPlatform::getConsoleLog('SLAVE');
		return $this->_GetOne($sql, $this->_slave_db);
	}

	private function _GetOne($sql, $db) {
		if(false !== ($rs = $this->_Execute($sql, $db))) {
			$row = mysqli_fetch_row($rs);
			mysqli_free_result($rs);
			return $row[0];
		}
		
		return false;
	}

	// Always master
	function LastInsertId() {
		return mysqli_insert_id($this->_master_db);
	}
	
	// Always master
	function Affected_Rows() {
		return mysqli_affected_rows($this->_master_db);
	}
	
	// By default, this reports on the last used DB connection
	function ErrorMsg() {
		return $this->_ErrorMsg($this->_last_used_db);
	}
	
	function ErrorMsgMaster() {
		return $this->_ErrorMsg($this->_master_db);
	}
	
	function ErrorMsgSlave() {
		return $this->_ErrorMsg($this->_slave_db);
	}
	
	private function _ErrorMsg($db) {
		if(!($db instanceof mysqli))
			return null;
		
		return mysqli_error($db);
	}
};