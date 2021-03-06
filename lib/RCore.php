<?php
	defined('AUTH') or die;
	
	if(defined('API'))
		return;
		
	if(!defined('RCONFIG'))
		include_once 'bin/RConfig.php';

	if(!defined('RLOGGER'))
		include_once 'bin/RLogger.php';

	DEFINE('API',1);

	final class API{
	
		private static function log($name, $params=array(), $msg){
			$user = (isset($_SESSION['username']))?$_SESSION['username']:'anon';	//if username is not set, set the username to 'anon'
			if(empty($msg['error']))	//if error message is empty
				RLogger::log('D',$name, $params, $user, $msg['message']); //put in debug log
			else
				RLogger::log('E',$name, $params, $user, $msg['error']);	//put in error log
		}

		public static function execute($name, $params=array()){
			$result = array();
			$result['data'] = array();
			$result['items'] = 0;
			$result['message'] = '';
			$result['error'] = '';

			$link = new mysqli(RConfig::DB_HOST,RConfig::DB_USERNAME,RConfig::DB_PASSWORD,RConfig::DB_NAME) or die('Database connection error.');

			if (!empty($link->connect_error))	//check connection errors
				$result['error'] = $link->connect_error;
			else{
				$file = (file_exists('api/'.$name.'.php'))?'api/'.$name.'.php':((file_exists('../api/'.$name.'.php'))?'../api/'.$name.'.php': false );
				if($file){		//check if API exists
					$input = array();
					$_POST = array_merge($_POST,$params);
					include $file;
					if(!isset($error) || !$error){
						$i = 0;
						do{
							$queryString = (is_array($query))?$query[$i]:$query;
							$_results = $link->query($queryString);
							$ar = explode(' ',$queryString);
							if($link->error)	//check if query has an error
								$result['error'] = $link->error;
							else if (
							(strcasecmp($ar[0],'CALL')==0 ||
							strcasecmp($ar[0],'SELECT')==0) &&
							$_results->num_rows > 0) {	//process data
								while($row=$_results->fetch_assoc())
									$result['data'][] = $row;	//init data
								if(isset($result['data'][0]['message']))	//init message if set
									$result['message'] = $result['data'][0]['message'];
								else
									$result['message'] = 'Query successful.';
								$result['items'] = $_results->num_rows;	//init items
								mysqli_free_result($_results);
							} else {	//if there's no data from database
								$result['items'] = 0;
								$result['message'] = 'Query sucessful.';
							}
							$i++;
						}while(is_array($query) && $i<count($query));
					}else{
						$result['error'] = $error_message;
					}
				}else $result['error'] = "$name: API does not exist";
			}
			if(!empty($link->error))
				$result['error'] = $link->error;
			$link->close();
			unset($link);
			API::log($name, $params, $result);
			return $result;
		}
		
		public static function sanitize($l,$i){
			$k = array();
			foreach($i as $j)
				$k[]=htmlspecialchars(mysqli_escape_string($l,trim($j)), ENT_QUOTES, 'UTF-8');
			return $k;
		}
		
		public static function checkEmpty($i){
			foreach($i as $j)
				if(empty($j) && $j!='0')
					return true;
			return false;
		}
	}