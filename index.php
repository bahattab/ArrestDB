<?php

$dsn = '';
$clients = [];
/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* ArrestDB 1.9.0 (github.com/alixaxel/ArrestDB/)
* Copyright (c) 2014 Alix Axel <alix.axel@gmail.com>
**/

if (strcmp(PHP_SAPI, 'cli') === 0)
{
	exit('ArrestDB should not be run from CLI.' . PHP_EOL);
}

if ((empty($clients) !== true) && (in_array($_SERVER['REMOTE_ADDR'], (array) $clients) !== true))
{
	exit(ArrestDB::Reply(ArrestDB::$HTTP[403]));
}

else if (ArrestDB::Query($dsn) === false)
{
	exit(ArrestDB::Reply(ArrestDB::$HTTP[503]));
}

if (array_key_exists('_method', $_GET) === true)
{
	$_SERVER['REQUEST_METHOD'] = strtoupper(trim($_GET['_method']));
}

else if (array_key_exists('HTTP_X_HTTP_METHOD_OVERRIDE', $_SERVER) === true)
{
	$_SERVER['REQUEST_METHOD'] = strtoupper(trim($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']));
}

ArrestDB::Serve('GET', '/(#any)/(#any)/(#any)', function ($table, $id, $data)
{
	$find = "*";

	if (isset($_GET['field']) === true)
	{
		$find = $_GET['field'];
	}

	$query = array
	(
		sprintf('SELECT %s FROM "%s"', $find, $table),
		sprintf('WHERE "%s" %s ?', $id, (ctype_digit($data) === true) ? '=' : 'LIKE'),
	);

	if (isset($_GET['extra']) === true)
	{
		$extra = "";
		if ($find === "*") {
			$extra = "Where 1 = 1";
		}
		$query[] = sprintf(' %s AND %s', $extra, $_GET['extra']);
	}

	if (isset($_GET['by']) === true)
	{
		if (isset($_GET['order']) !== true)
		{
			$_GET['order'] = 'ASC';
		}

		$query[] = sprintf('ORDER BY "%s" %s', $_GET['by'], $_GET['order']);
	}

	if (isset($_GET['limit']) === true)
	{
		$query[] = sprintf('LIMIT %u', $_GET['limit']);

		if (isset($_GET['offset']) === true)
		{
			$query[] = sprintf('OFFSET %u', $_GET['offset']);
		}
	}

	$query = sprintf('%s;', implode(' ', $query));
	$result = ArrestDB::Query($query, $data);

	if ($result === false)
	{
		$result = ArrestDB::$HTTP[404];
	}

	else if (empty($result) === true)
	{
		$result = ArrestDB::$HTTP[204];
	}

	return ArrestDB::Reply($result);
});

ArrestDB::Serve('GET', '/(#any)/(#num)?', function ($table, $id = null)
{
	$find = "*";
	$idColumn = "id";
	$idValue = "";

	if (isset($_GET['field']) === true)
	{
		$find = $_GET['field'];
	}

	if (isset($_GET['id']) === true && isset($_GET['value']) === true)
	{
		$idColumn = $_GET['id'];
		$idValue = $_GET['value'];
	}

	$query = array
	(
		sprintf('SELECT %s FROM "%s"', $find, $table),
	);

	if (isset($id) === true)
	{
		if($idValue === ""){
			$query[] = sprintf('WHERE "%s" = ? LIMIT 1', $idColumn);
		}else{
			$query[] = sprintf('WHERE "%s" = \'%s\' LIMIT 1', $idColumn, $idValue);
		}
	}
	else
	{

		if (isset($_GET['extra']) === true)
		{
			$extra = "";
			if ($find === "*") {
				$extra = "Where 1 = 1";
			}
			$query[] = sprintf(' %s AND %s', $extra, $_GET['extra']);
		}

		if (isset($_GET['by']) === true)
		{
			if (isset($_GET['order']) !== true)
			{
				$_GET['order'] = 'ASC';
			}

			$query[] = sprintf('ORDER BY "%s" %s', $_GET['by'], $_GET['order']);
		}

		if (isset($_GET['limit']) === true)
		{
			$query[] = sprintf('LIMIT %u', $_GET['limit']);

			if (isset($_GET['offset']) === true)
			{
				$query[] = sprintf('OFFSET %u', $_GET['offset']);
			}
		}
	}

	//print_r($query);

	$query = sprintf('%s;', implode(' ', $query));

	$result = (isset($id) === true) ? ArrestDB::Query($query, $id) : ArrestDB::Query($query);

	if ($result === false)
	{
		$result = ArrestDB::$HTTP[404];
	}

	else if (empty($result) === true)
	{
		$result = ArrestDB::$HTTP[204];
	}

	else if (isset($id) === true)
	{
		$result = array_shift($result);
	}

	return ArrestDB::Reply($result);
	
});

ArrestDB::Serve('DELETE', '/(#any)/(#num)', function ($table, $id)
{
	$query = array
	(
		sprintf('DELETE FROM "%s" WHERE "%s" = ?', $table, 'id'),
	);

	$query = sprintf('%s;', implode(' ', $query));
	$result = ArrestDB::Query($query, $id);

	if ($result === false)
	{
		$result = ArrestDB::$HTTP[404];
	}

	else if (empty($result) === true)
	{
		$result = ArrestDB::$HTTP[204];
	}

	else
	{
		$result = ArrestDB::$HTTP[200];
	}

	return ArrestDB::Reply($result);
});

if (in_array($http = strtoupper($_SERVER['REQUEST_METHOD']), ['POST', 'PUT']) === true)
{
	if (preg_match('~^\x78[\x01\x5E\x9C\xDA]~', $data = file_get_contents('php://input')) > 0)
	{
		$data = gzuncompress($data);
	}

	if ((array_key_exists('CONTENT_TYPE', $_SERVER) === true) && (empty($data) !== true))
	{
		if (strncasecmp($_SERVER['CONTENT_TYPE'], 'application/json', 16) === 0)
		{
			$GLOBALS['_' . $http] = json_decode($data, true);
		}

		else if ((strncasecmp($_SERVER['CONTENT_TYPE'], 'application/x-www-form-urlencoded', 33) === 0) && (strncasecmp($_SERVER['REQUEST_METHOD'], 'PUT', 3) === 0))
		{
			parse_str($data, $GLOBALS['_' . $http]);
		}
	}

	if ((isset($GLOBALS['_' . $http]) !== true) || (is_array($GLOBALS['_' . $http]) !== true))
	{
		$GLOBALS['_' . $http] = [];
	}

	unset($data);
}

ArrestDB::Serve('POST', '/(#any)', function ($table)
{
	if (empty($_POST) === true)
	{
		$result = ArrestDB::$HTTP[204];
	}

	else if (is_array($_POST) === true)
	{
		$queries = [];

		if (count($_POST) == count($_POST, COUNT_RECURSIVE))
		{
			$_POST = [$_POST];
		}

		foreach ($_POST as $row)
		{
			$data = [];

			foreach ($row as $key => $value)
			{
				$data[sprintf('"%s"', $key)] = $value;
			}

			$query = array
			(
				sprintf('INSERT INTO "%s" (%s) VALUES (%s)', $table, implode(', ', array_keys($data)), implode(', ', array_fill(0, count($data), '?'))),
			);

			$queries[] = array
			(
				sprintf('%s;', implode(' ', $query)),
				$data,
			);
		}

		if (count($queries) > 1)
		{
			ArrestDB::Query()->beginTransaction();

			while (is_null($query = array_shift($queries)) !== true)
			{
				if (($result = ArrestDB::Query($query[0], $query[1])) === false)
				{
					ArrestDB::Query()->rollBack(); break;
				}
			}

			if (($result !== false) && (ArrestDB::Query()->inTransaction() === true))
			{
				$result = ArrestDB::Query()->commit();
			}
		}

		else if (is_null($query = array_shift($queries)) !== true)
		{
			$result = ArrestDB::Query($query[0], $query[1]);
		}

		if ($result === false)
		{
			$result = ArrestDB::$HTTP[409];
		}

		else
		{
			$result = ArrestDB::$HTTP[201];
		}
	}

	return ArrestDB::Reply($result);
});

ArrestDB::Serve('PUT', '/(#any)/(#num)', function ($table, $id)
{

	if (empty($GLOBALS['_PUT']) === true)
	{
		$result = ArrestDB::$HTTP[204];
	}

	else if (is_array($GLOBALS['_PUT']) === true)
	{
		$data = [];
		if(isset($GLOBALS['_PUT']['data']['field']) === true){
			unset($GLOBALS['_PUT']['data']['field']);
			unset($GLOBALS['_PUT']['data']['distinct']);
		}

		foreach ($GLOBALS['_PUT'] as $key => $value)
		{
			$data[$key] = sprintf('"%s" = ?', $key);
		}

		$query = array
		(
			sprintf('UPDATE "%s" SET %s WHERE "%s" = ?', $table, implode(', ', $data), 'id'),
		);

		$query = sprintf('%s;', implode(' ', $query));

		$result = ArrestDB::Query($query, $GLOBALS['_PUT'], $id);

		if ($result === false)
		{
			$result = ArrestDB::$HTTP[409];
		}
		else
		{
			$result = ArrestDB::$HTTP[200];
		}
	}

	return ArrestDB::Reply($result);
});

ArrestDB::Serve('PUT', '/(#any)/(#any)/(#any)', function ($table, $fieldName, $filedValue)
{
	$format = 'plain';

	if(strpos($_SERVER['HTTP_ACCEPT'], 'application/json') === 0){
		$format = 'json';
	}

	if ($format === 'plain'){
		$result = [
			'error' => [
				'code' => 200,
				'status' => 'Not implemented yet. Please use `id` instead',
			],
		];
	}
	else if (empty($GLOBALS['_PUT']) === true)
	{
		$result = ArrestDB::$HTTP['JSON_204'];
	}
	else if (is_array($GLOBALS['_PUT']) === true)
	{	
		try{
			$json = $GLOBALS['_PUT']['data'];
			if(is_array($json) === true){
				$content = $json['content'];
				$field = $json['field'];

				$distinct = 'PRESERVE';
				if (isset($json['distinct']) === true)
				{
					$distinct = $json['distinct'];
				}
				
				$cast = sprintf('CAST(\'%s\' AS JSON)', $content);
				$append = sprintf('JSON_MERGE_%s(`%s`, %s)',$distinct, $field, $cast);
				$query = sprintf('UPDATE `%s` SET `%s` = IF(`%s` is null, %s, %s) WHERE %s = ?', $table, $field, $field, $cast, $append, $fieldName);
				$result = ArrestDB::Query($query, $GLOBALS['_PUT'], $filedValue);
			}
		} catch (\Exception $e) {
			$result = $result = [
				'error' => [
					'code' => 200,
					'status' => 'json format error, missing `data` or `content` or `field`',
				],
			];
		}

		if ($result === false)
		{
			$result = ArrestDB::$HTTP['JSON_409'];
		}
		else
		{
			$result = ArrestDB::$HTTP['JSON_200'];
		}
	}

	return ArrestDB::Reply($result);
});

ArrestDB::Serve('PUT', '/(#any)', function ($table)
{
	if ($_GET['replace'] === "1"){
		if (is_array($GLOBALS['_PUT']) === true && isset($_GET['fields']) === true){
			$fields = str_replace(',', '","', $_GET['fields']);
			$data = $GLOBALS['_PUT']['data'];
			$query = sprintf('REPLACE INTO `%s` ("%s") VALUES %s', $table, $fields, $data);
			$result = ArrestDB::Query($query, $GLOBALS['_PUT']);
		}else{
			$result = ArrestDB::$HTTP['JSON_302'];
		}
	}else{
		$result = ArrestDB::$HTTP['JSON_302'];
	}
	return ArrestDB::Reply($result);
});

exit(ArrestDB::Reply(ArrestDB::$HTTP[400]));

class ArrestDB
{
	public static $HTTP = [
		200 => [
			'success' => [
				'code' => 200,
				'status' => 'OK',
				'type' => 'Plain',
			],
		],
		'JSON_200' => [
			'success' => [
				'code' => 200,
				'status' => 'OK',
				'type' => 'Json',
			],
		],
		201 => [
			'success' => [
				'code' => 201,
				'status' => 'Created',
				'type' => 'Plain',
			],
		],
		'JSON_201' => [
			'success' => [
				'code' => 201,
				'status' => 'Created',
				'type' => 'Json',
			],
		],
		204 => [
			'error' => [
				'code' => 204,
				'status' => 'No Content',
				'type' => 'Plain',
			],
		],
		'JSON_204' => [
			'error' => [
				'code' => 204,
				'status' => 'No Content',
				'type' => 'Json'
			],
		],
		'JSON_302' => [
			'error' => [
				'code' => 204,
				'status' => 'Not Modified',
				'type' => 'Json'
			],
		],
		400 => [
			'error' => [
				'code' => 400,
				'status' => 'Bad Request',
				'type' => 'Plain',
			],
		],
		'JSON_400' => [
			'error' => [
				'code' => 400,
				'status' => 'Bad Request',
				'type' => 'Json',
			],
		],
		403 => [
			'error' => [
				'code' => 403,
				'status' => 'Forbidden',
				'type' => 'Plain',
			],
		],
		'JSON_403' => [
			'error' => [
				'code' => 403,
				'status' => 'Forbidden',
				'type' => 'Json',
			],
		],
		404 => [
			'error' => [
				'code' => 404,
				'status' => 'Not Found',
				'type' => 'Plain',
			],
		],
		'JSON_404' => [
			'error' => [
				'code' => 404,
				'status' => 'Not Found',
				'type' => 'Json',
			],
		],
		409 => [
			'error' => [
				'code' => 409,
				'status' => 'Conflict',
				'type' => 'Plain',
			],
		],
		'JSON_409' => [
			'error' => [
				'code' => 409,
				'status' => 'Conflict',
				'type' => 'Json',
			],
		],
		503 => [
			'error' => [
				'code' => 503,
				'status' => 'Service Unavailable',
				'type' => 'Plain',
			],
		],
		'JSON_503' => [
			'error' => [
				'code' => 503,
				'status' => 'Service Unavailable',
				'type' => 'Json',
			],
		],
	];

	public static function Query($query = null)
	{
		static $db = null;
		static $result = [];

		try
		{

			if (isset($db, $query) === true)
			{
				$data = array_slice(func_get_args(), 1);
				if (strncasecmp($db->getAttribute(\PDO::ATTR_DRIVER_NAME), 'mysql', 5) === 0)
				{
					if(strpos($query,'JSON') === false){
						$query = strtr($query, '"', '`');
					}else{
						$data = array_slice(func_get_args(), 2);
					}
				}

				if (empty($result[$hash = crc32($query)]) === true)
				{
					$result[$hash] = $db->prepare($query);
				}

				if (count($data, COUNT_RECURSIVE) > count($data))
				{
					$data = iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveArrayIterator($data)), false);
				}

				if ($result[$hash]->execute($data) === true)
				{ 
					$sequence = null;

					if ((strncmp($db->getAttribute(\PDO::ATTR_DRIVER_NAME), 'pgsql', 5) === 0) && (sscanf($query, 'INSERT INTO %s', $sequence) > 0))
					{
						$sequence = sprintf('%s_id_seq', trim($sequence, '"'));
					}

					switch (strstr($query, ' ', true))
					{
						case 'INSERT':
						case 'REPLACE':
							return $db->lastInsertId($sequence);

						case 'UPDATE':
						case 'DELETE':
							return $result[$hash]->rowCount();

						case 'SELECT':
						case 'EXPLAIN':
						case 'PRAGMA':
						case 'SHOW':
							return $result[$hash]->fetchAll();
					}
					return true;
				}else if(strpos($query, 'REPLACE INTO') !== false){
					$result[$hash] -> execute();
					return $result[$hash] -> rowCount();
				}
				return false;
			}

			else if (isset($query) === true)
			{
				$options = array
				(
					\PDO::ATTR_CASE => \PDO::CASE_NATURAL,
					\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
					\PDO::ATTR_EMULATE_PREPARES => false,
					\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
					\PDO::ATTR_ORACLE_NULLS => \PDO::NULL_NATURAL,
					\PDO::ATTR_STRINGIFY_FETCHES => false,
				);
				if (preg_match('~^sqlite://([[:print:]]++)$~i', $query, $dsn) > 0)
				{
					$options += array
					(
						\PDO::ATTR_TIMEOUT => 3,
					);

					$db = new \PDO(sprintf('sqlite:%s', $dsn[1]), null, null, $options);
					$pragmas = array
					(
						'automatic_index' => 'ON',
						'cache_size' => '8192',
						'foreign_keys' => 'ON',
						'journal_size_limit' => '67110000',
						'locking_mode' => 'NORMAL',
						'page_size' => '4096',
						'recursive_triggers' => 'ON',
						'secure_delete' => 'ON',
						'synchronous' => 'NORMAL',
						'temp_store' => 'MEMORY',
						'journal_mode' => 'WAL',
						'wal_autocheckpoint' => '4096',
					);
					if (strncasecmp(PHP_OS, 'WIN', 3) !== 0)
					{
						$memory = 131072;

						if (($page = intval(shell_exec('getconf PAGESIZE'))) > 0)
						{
							$pragmas['page_size'] = $page;
						}

						if (is_readable('/proc/meminfo') === true)
						{
							if (is_resource($handle = fopen('/proc/meminfo', 'rb')) === true)
							{
								while (($line = fgets($handle, 1024)) !== false)
								{
									if (sscanf($line, 'MemTotal: %d kB', $memory) == 1)
									{
										$memory = round($memory / 131072) * 131072; break;
									}
								}

								fclose($handle);
							}
						}

						$pragmas['cache_size'] = intval($memory * 0.25 / ($pragmas['page_size'] / 1024));
						$pragmas['wal_autocheckpoint'] = $pragmas['cache_size'] / 2;
					}

					foreach ($pragmas as $key => $value)
					{
						$db->exec(sprintf('PRAGMA %s=%s;', $key, $value));
					}
				}

				else if (preg_match('~^(mysql|pgsql)://(?:(.+?)(?::(.+?))?@)?([^/:@]++)(?::(\d++))?/(\w++)/?$~i', $query, $dsn) > 0)
				{
					if (strncasecmp($query, 'mysql', 5) === 0)
					{
						$options += array
						(
							\PDO::ATTR_AUTOCOMMIT => true,
							\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES "utf8" COLLATE "utf8_general_ci", time_zone = "+00:00";',
							\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
						);
					}

					$db = new \PDO(sprintf('%s:host=%s;port=%s;dbname=%s', $dsn[1], $dsn[4], $dsn[5], $dsn[6]), $dsn[2], $dsn[3], $options);
				}
			}
		}

		catch (\Exception $exception)
		{
			echo $exception;
			return false;
		}

		return (isset($db) === true) ? $db : false;
	}

	public static function Reply($data)
	{
		$bitmask = 0;
		$options = ['UNESCAPED_SLASHES', 'UNESCAPED_UNICODE'];

		if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) === true)
		{
			$options[] = 'PRETTY_PRINT';
		}

		foreach ($options as $option)
		{
			$bitmask |= (defined('JSON_' . $option) === true) ? constant('JSON_' . $option) : 0;
		}

		if (($result = json_encode($data, $bitmask)) !== false)
		{
			$callback = null;

			if (array_key_exists('callback', $_GET) === true)
			{
				$callback = trim(preg_replace('~[^[:alnum:]\[\]_.]~', '', $_GET['callback']));

				if (empty($callback) !== true)
				{
					$result = sprintf('%s(%s);', $callback, $result);
				}
			}

			if (headers_sent() !== true)
			{
				header(sprintf('Content-Type: application/%s; charset=utf-8', (empty($callback) === true) ? 'json' : 'javascript'));
			}
		}

		return $result;
	}

	public static function Serve($on = null, $route = null, $callback = null)
	{
		static $root = null;

		if (isset($_SERVER['REQUEST_METHOD']) !== true)
		{
			$_SERVER['REQUEST_METHOD'] = 'CLI';
		}

		if ((empty($on) === true) || (strcasecmp($_SERVER['REQUEST_METHOD'], $on) === 0))
		{
			if (is_null($root) === true)
			{
				$root = preg_replace('~/++~', '/', substr($_SERVER['PHP_SELF'], strlen($_SERVER['SCRIPT_NAME'])) . '/');
			}

			if (preg_match('~^' . str_replace(['#any', '#num'], ['[^/]++', '[0-9]++'], $route) . '~i', $root, $parts) > 0)
			{
				return (empty($callback) === true) ? true : exit(call_user_func_array($callback, array_slice($parts, 1)));
			}
		}

		return false;
	}
}
