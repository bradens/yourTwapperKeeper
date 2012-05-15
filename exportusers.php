<?php 
	/**
	 * @author Braden Simpson
	 * Include an export functionality for TwapperKeeper Archives
	 */

	require_once('config.php');
	 
	if (!ISSET($_GET['id']))
		echo "<h1>Error getting ID of the archive</h1>\n";
		
	$archiveID = $_GET['id'];
	
	$connection = $db->connection;
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename=data.csv');

	$q_check_user = "SELECT id from z_users_".$archiveID." where tweet_count is NULL;";
	$res = mysql_query($q_check_user, $db->connection);
	$fd = fopen("logs", "w");
	fputs($fd, mysql_num_rows($res));
	$q_delete_user = "DELETE FROM z_users_".$archiveID." where tweet_count is NULL";
	mysql_query($q_delete_user, $db->connection); 
	
	fputs($fd, mysql_num_rows($res));
	for ($reqs = 0;$reqs < (mysql_num_rows($res) % 100)+1;$reqs++)
	{
		$url = "http://api.twitter.com/1/users/lookup.json?screen_name=";
		for ($i = 0;$i < 100;$i++)
		{
			$row = mysql_fetch_assoc($res);
			if (is_null($row))
				break;
			$url = $url.$row['id'];
			if ($i != mysql_num_rows($res)-1)
				$url = $url.",";
		}
		$url = $url."&include_entities=true";
		echo $url;
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$curlData = curl_exec($curl);
		curl_close($curl);
		$curlData = json_decode($curlData, true);
		foreach ($curlData as $user)
		{
			fputs($fd, $user);
			$q_insert_users = "INSERT INTO z_users_".$archiveID." values('".$user['screen_name']."', '".$user['statuses_count']."', '".$user['followers_count']."', '".$user['friends_count']."', '".$user['location']."', '".$user['name']."');";	
			mysql_query($q_insert_users, $db->connection);
		}
		fclose($fd);
	}
	fputs($fd, $url);
	// Now grab that user's info from twitter api.					
	/*$oauth = array( 'oauth_consumer_key' => $tk_oauth_consumer_key, 
			'oauth_nonce' => time(),
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_token' => $tk_oauth_token,
			'oauth_timestamp' => time(),
			'oauth_version' => '1.0');
	
	$base_info = buildBaseString($url, 'GET', $oauth);
	$composite_key = rawurlencode($tk_oauth_consumer_secret) . '&' . rawurlencode($oauth_access_token_secret);
	$oauth_signature = base64_encode(hash_hmac('sha1', $base_info, $composite_key, true));
	$oauth['oauth_signature'] = $oauth_signature;
	$header = array(buildAuthorizationHeader($oauth), 'Expect:');
	$options = array( CURLOPT_HTTPHEADER => $header,
					  CURLOPT_HEADER => false,
					  CURLOPT_URL => $url,
					  CURLOPT_RETURNTRANSFER => true,
					  CURLOPT_SSL_VERIFYPEER => false);
			
	$feed = curl_init();
	curl_setopt_array($feed, $options);
	$json = curl_exec($feed);
	curl_close($feed);
	*/
	
	//curl request
	
	
	//decoding json structure into array
	

	$sqlData = 'SELECT * FROM z_users_'.$archiveID;
	$res = mysql_query($sqlData, $connection);
	$fullPath = 'resources/exportusers'.$archiveID.'.csv';
	$fp = fopen($fullPath, "w");
	// fetch a row and write the column names out to the file
	$row = mysql_fetch_assoc($res);
	$line = "";
	$comma = "";
	foreach($row as $name => $value) {
		$line .= $comma . '"' . str_replace('"', '""', $name) . '"';
		$comma = ",";
	}
	$line .= "\n";
	fputs($fp, $line);

	// remove the result pointer back to the start
	mysql_data_seek($res, 0);

	// and loop through the actual data
	while($row = mysql_fetch_assoc($res)) {
		$line = "";
		$comma = "";
		foreach($row as $value) {
			$line .= $comma . '"' . str_replace('"', '""', $value) . '"';
			$comma = ",";
		}
		$line .= "\n";
		fputs($fp, $line);
	}
	fclose($fp);
?>
