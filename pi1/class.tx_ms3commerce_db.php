<?php
/***************************************************************
* Part of mS3 Commerce
* Copyright (C) 2019 Goodson GmbH <http://www.goodson.at>
*  All rights reserved
* 
* Dieses Computerprogramm ist urheberrechtlich sowie durch internationale
* Abkommen geschützt. Die unerlaubte Reproduktion oder Weitergabe dieses
* Programms oder von Teilen dieses Programms kann eine zivil- oder
* strafrechtliche Ahndung nach sich ziehen und wird gemäß der geltenden
* Rechtsprechung mit größtmöglicher Härte verfolgt.
* 
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

require_once(MS3C_ROOT . '/dataTransfer/mS3Commerce_db.php');

/**
 * Wraps a DB to give access to Typo3 DB for requests for its tables 
 */
class tx_ms3commerce_db_t3_prototype_mysqli extends tx_ms3commerce_db_mysqli
{
	public function __construct($db, $host, $user, $pwd) {
		parent::__construct($db, $host, $user, $pwd);
	}
	public function sql_query($query, $tables = null) {
		if ($tables === "%TYPO3%") {
			throw new Exception("TYPO3 Passthrough through mS3 Commerce DB no longer supported");
		//	return tx_ms3commerce_db_factory_cms::getT3Database()->sql_query($query);
		}
		return parent::sql_query($query, $tables);
	}
	public function exec_SELECTquery($select, $from, $where, $group = '', $order = '', $limit = '') {
		// Get ANY table from From
		if (preg_match('/`?(\w+)`?[^,]*/', $from, $match)) {
			$table = $match[1];
		} else {
			$table = $from;
		}

		if (array_search($table, $GLOBALS['MS3C_TABLES']) === false) {
			// No mS3 Table, go into Typo3
			throw new Exception("TYPO3 Passthrough through mS3 Commerce DB no longer supported");
		//	return tx_ms3commerce_db_factory_cms::getT3Database()->exec_SELECTquery($select, $from, $where, $group, $order, $limit);
		}
		return parent::exec_SELECTquery($select, $from, $where, $group, $order, $limit);
	}
}

class tx_ms3commerce_db_t3_prototype_mysql extends tx_ms3commerce_db_mysql
{
	public function __construct($db, $host, $user, $pwd) {
		parent::__construct($db, $host, $user, $pwd);
	}
	public function sql_query($query, $tables = null) {
		if ($tables === "%TYPO3%") {
			throw new Exception("TYPO3 Passthrough through mS3 Commerce DB no longer supported");
		//	return tx_ms3commerce_db_factory_cms::getT3Database()->sql_query($query);
		}
		return parent::sql_query($query, $tables);
	}
	public function exec_SELECTquery($select, $from, $where, $group = '', $order = '', $limit = '') {
		// Get ANY table from From
		if (preg_match('/`?(\w+)`?[^,]*/', $from, $match)) {
			$table = $match[1];
		} else {
			$table = $from;
		}

		if (array_search($table, $GLOBALS['MS3C_TABLES']) === false) {
			// No mS3 Table, go into Typo3
			throw new Exception("TYPO3 Passthrough through mS3 Commerce DB no longer supported");
		//	return tx_ms3commerce_db_factory_cms::getT3Database()->exec_SELECTquery($select, $from, $where, $group, $order, $limit);
		}
		return parent::exec_SELECTquery($select, $from, $where, $group, $order, $limit);
	}
}

if (MS3C_DB_BACKEND == 'mysqli') {
	class tx_ms3commerce_db_t3 extends tx_ms3commerce_db_t3_prototype_mysqli {}
} else if (MS3C_DB_BACKEND == 'mysql') {
	class tx_ms3commerce_db_t3 extends tx_ms3commerce_db_t3_prototype_mysql {}
} else {
	throw new Exception('Unknown DB Backend');
}

/**
 * Wraps Typo3 database in tx_ms3commece_db interface.
 * Works on a Typo3 db (t3lib_db)
 */
class tx_ms3commerce_db_typo3 extends tx_ms3commerce_db
{
	var $mydb;
	public function __construct($t3db) {
		$this->mydb = $t3db;
		$this->sql_query("SET NAMES 'utf8'");
	}
	public function sql_affected_rows() {
		return $this->mydb->sql_affected_rows();
	}
	public function sql_error() {
		return $this->mydb->sql_error();
	}
	public function sql_info() {
		// No info available in Typo3
		return $this->sql_affected_rows();
	}
	public function exec_SELECTquery($select, $from, $where, $group = '', $order = '', $limit = '') {
		return $this->mydb->exec_SELECTquery($select, $from, $where, $group, $order, $limit);
	}
	public function sql_num_rows($rs) {
		return $this->mydb->sql_num_rows($rs);
	}
	public function sql_insert_id() {
		return $this->mydb->sql_insert_id();
	}
	public function sql_data_seek($rs, $row) {
		return $this->mydb->sql_data_seek($rs,$row);
	}
	public function sql_fetch_all($rs) {
		return $this->mydb->sql_fetch_all($rs);
	}
	public function sql_fetch_assoc($rs) {
		return $this->mydb->sql_fetch_assoc($rs);
	}
	public function sql_fetch_object($rs) {
		// Typo3 has no fetch_object
		$array=$this->mydb->sql_fetch_assoc($rs);
		if($array) {
			$obj = new StdClass();
			foreach ($array as $key => $val) {
				$obj->$key = $val;
			}
			return $obj;
		}
		return $array;
	}
	public function sql_fetch_row($rs) {
		return $this->mydb->sql_fetch_row($rs);
	}
	public function sql_free_result($rs) {
		return $this->mydb->sql_free_result($rs);
	}
	public function sql_query($query, $tables = null) {
		return $this->mydb->sql_query($query);
	}
	public function sql_close() {
		// Never close Typo3 DBs!
	}
	public function map_table_name($name) {
		return $name;
	}
	public function map_sql_from_tables($from, $defaultAlias = false, &$tables = array()) {
		return $from;
	}
	
	public function do_map_sql_query($query, $tables) {
		return $query;
	}
	public function sql_escape($value,$quotes=true) {
		// Get a random table for mS3 Commerce, so DBAL will find correct link
		//$table = $this->get_t3_db_handle();
		$string=$this->mydb->fullQuoteStr($value, '');
		if($quotes==false){
			$string=substr($string,1);
			$string=substr($string,0,-1);
		}
		return $string;
	}
}

class tx_ms3commerce_db_t3_logged extends tx_ms3commerce_db_t3
{
	public function __construct($db, $host, $user, $pwd) {
		parent::__construct($db, $host, $user, $pwd);
	}
	public function exec_SELECTquery($select, $from, $where, $group = '', $order = '', $limit = '') {
		$sql="SELECT $select FROM $from";
		if(!empty($where))
			$sql.=" WHERE $where";
		if(!empty($group))
			$sql.=" GROUP BY $group";
		if(!empty($order))
			$sql.=" ORDER BY $order";
		if(!empty($limit))
			$sql.=" LIMIT $limit";
		mS3CommerceDBLogger::logStart($sql);
		$ret = parent::exec_SELECTquery($select, $from, $where, $group, $order, $limit);
		mS3CommerceDBLogger::logEnd();
		return $ret;
	}
	
	public function sql_query($query, $tables = null) {
		mS3CommerceDBLogger::logStart($query);
		$ret = parent::sql_query($query, $tables);
		mS3CommerceDBLogger::logEnd();
		return $ret;
	}
}

class tx_ms3commerce_db_typo3_logged extends tx_ms3commerce_db_typo3
{
	public function __construct($t3db) {
		parent::__construct($t3db);
	}
	public function exec_SELECTquery($select, $from, $where, $group = '', $order = '', $limit = '') {
		$sql="SELECT $select FROM $from";
		if(!empty($where))
			$sql.=" WHERE $where";
		if(!empty($group))
			$sql.=" GROUP BY $group";
		if(!empty($order))
			$sql.=" ORDER BY $order";
		if(!empty($limit))
			$sql.=" LIMIT $limit";
		mS3CommerceDBLogger::logStart($sql);
		$ret = parent::exec_SELECTquery($select, $from, $where, $group, $order, $limit);
		mS3CommerceDBLogger::logEnd();
		return $ret;
	}
	
	public function sql_query($query, $tables = null) {
		mS3CommerceDBLogger::logStart($query);
		$ret = parent::sql_query($query, $tables);
		mS3CommerceDBLogger::logEnd();
		return $ret;
	}
}

/**
 * Factory building mS3 commerce db handlers 
 */
class tx_ms3commerce_db_factory_cms
{
	static function getDatabaseConnectParams($useStageDb = false)
	{
		switch (MS3COMMERCE_STAGETYPE)
		{
		case 'DATABASES':
			$dbConf = MS3C_DB_ACCESS();
			// Find out to which db we're mapping
			if ($useStageDb) {
				$stageDbAlias = MS3COMMERCE_STAGE_DB;
			} else {
				$stageDbAlias = MS3COMMERCE_PRODUCTION_DB;
			}
			
			$dbAccess = $dbConf[$stageDbAlias];
			return $dbAccess;
			break;
		case 'TABLES':
			// Take from Typo3
			$conn = self::getT3ConnectParams();
			return array(
					'username' => $conn[0],
					'host' => $conn[1],
					'database' => $conn[2],
					'password' => $conn[3],
					);
			break;
		}
		return null;
	}
	
	static function buildForDatabases($useStageDb)
	{
		// Always use direct DB connection (no Typo3 passthrough)
		$dbconnect = self::getDatabaseConnectParams( $useStageDb );
		$db = $dbconnect['database'];
		$host = $dbconnect['host'];
		$user = $dbconnect['username'];
		$pwd = $dbconnect['password'];
		if (array_key_exists('ms3debugdb', $_GET) && $_GET['ms3debugdb']) {
			return new tx_ms3commerce_db_t3_logged($db, $host, $user, $pwd);
		} else {
			return new tx_ms3commerce_db_t3($db, $host, $user, $pwd);
		}
	}
	
	static function buildForTables($useStageDb)
	{
		// Always use Typo3's DB connection
		self::checkTypo3();
		if (array_key_exists('ms3debug', $_GET) && $_GET['ms3debug']) {
			$thedb = new tx_ms3commerce_db_typo3_logged( $GLOBALS['TYPO3_DB'] );
		} else {
			$thedb = new tx_ms3commerce_db_typo3( $GLOBALS['TYPO3_DB'] );
		}
		
		return $thedb;
	}
	
	static $t3db = null;
	/**
	 *
	 * @global type $typo_db_username
	 * @global type $typo_db_host
	 * @global type $typo_db
	 * @global type $typo_db_password
	 * @return tx_ms3commerce_db 
	 */
	static function getT3Database()
	{
		if (self::$t3db) {
			return self::$t3db;
		}
		if (self::isTypo3() && isset($GLOBALS['TYPO3_DB'])) {
			if (array_key_exists('ms3debugdb', $_GET) && $_GET['ms3debugdb']) {
				self::$t3db = new tx_ms3commerce_db_typo3_logged( $GLOBALS['TYPO3_DB'] );
			} else {
				self::$t3db = new tx_ms3commerce_db_typo3( $GLOBALS['TYPO3_DB'] );
			}
			return self::$t3db;
		}
		
		
		list($typo_db_username, $typo_db_host, $typo_db, $typo_db_password) = self::getT3ConnectParams();
		
		self::$t3db = new tx_ms3commerce_db_mysqli($typo_db, $typo_db_host, $typo_db_username, $typo_db_password);
		return self::$t3db;
	}
	
	private static function checkTypo3()
	{
		if (!self::isTypo3()) {
			// Typo3 is not loaded! Cannot access its db handler
			die('tx_ms3commerce_db_factory: Cannot create Typo3 DB outside of Typo3');
		}
	}
	
	private static function isTypo3()
	{
		if (defined('TYPO3_MODE')) {
			if (TYPO3_MODE == 'FE' || TYPO3_MODE == 'BE') {
				return true;
			}
		}
		
		return false;
	}
	
	private static function getT3ConnectParams()
	{
		$conf = @include(MS3C_EXT_ROOT . '/typo3conf/LocalConfiguration.php');
		
		if (MS3_TYPO3_RELEASE == '8') {
			$typo_db_username = $conf['DB']['Connections']['Default']['user'];
			$typo_db_host = $conf['DB']['Connections']['Default']['host'];
			$typo_db = $conf['DB']['Connections']['Default']['dbname'];
			$typo_db_password = $conf['DB']['Connections']['Default']['password'];
		} else {
			$typo_db_username = $conf['DB']['username'];
			$typo_db_host = $conf['DB']['host'];
			$typo_db = $conf['DB']['database'];
			$typo_db_password = $conf['DB']['password'];
		}

		return array($typo_db_username, $typo_db_host, $typo_db, $typo_db_password);
	}
}

?>
