<?php
// load important files
require_once('config.php');
require_once('function.php');
require_once('twitteroauth_search.php');

// setup values
$pid = getmypid();
$script_key = uniqid();

// process loop
while (TRUE) {
	// lock up some tweets
	$q = "update rawstream set flag = '$script_key' where flag = '-1' limit $stream_process_stack_size";
	echo $q."\n";
	mysql_query($q, $db->connection);  
		
	// get keyword into memory
	$q = "select id,keyword from archives";
	echo $q."\n";
	$r = mysql_query($q, $db->connection);
	$preds = array();
	while ($row = mysql_fetch_assoc($r)) {
		$preds[$row['id']] = $row['keyword'];
	}
	
	// grab the locked up tweets and load into memory
	$q = "select * from rawstream where flag = '$script_key'";
	$r = mysql_query($q, $db->connection);
	echo $q."\n";
	$batch = array();
	while ($row = mysql_fetch_assoc($r)) {
		$batch[] = $row;    
	}
	$usernameRegex = '/(@[a-zA-Z0-9_]+)/';	
	// for each tweet in memory, compare against predicates and insert
	foreach ($batch as $tweet) {
		echo "[".$tweet['id']." - ".$tweet['text']."]\n";
        foreach ($preds as $ztable=>$keyword) 
        {
        	if (stristr($tweet['text'],$keyword) == TRUE && preg_match($usernameRegex, $tweet['text'])) 
        	{
		        preg_match_all($usernameRegex, $tweet['text'], $matches);
        		$tweetToUsers = implode(", ", $matches[0]);
				echo " vs. $keyword = insert\n";
        		$q_insert = "insert into z_$ztable values ('twitter-stream','".$tweet['text']."','".$tweet['to_user_id']."','".$tweet['from_user']."','".$tweet['id']."','".$tweet['from_user_id']."','".$tweet['iso_language_code']."','".$tweet['source']."','".$tweet['profile_image_url']."','".$tweet['geo_type']."','".$tweet['geo_coordinates_0']."','".$tweet['geo_coordinates_1']."','".$tweet['created_at']."','".$tweetToUsers."','".$tweet['time']."')";
        		
        		$r_insert = mysql_query($q_insert, $db->connection);
				// Now insert into the mentions table for each mention
				for ($i = 0; $i < count($matches[0]);$i++) 
				{
					$q_insert_mentions = "INSERT INTO z_mentions_".$ztable." values ('".$tweet['from_user']."','".substr($matches[0][$i], 1)."','".$tweet['id']."', default);";
					$r_insert = mysql_query($q_insert_mentions, $db->connection);
					$q_insert_users = "INSERT INTO z_users_".$ztable." values('".substr($matches[0][$i], 1)."', null, null, null, null, null);";
                                        mysql_query($q_insert_users, $db->connection);
				}	
					/*// check against users in the table
					$q_check_user = "SELECT id from z_users_".$ztable." where tweet_count is NULL;";
					$res = mysql_query($q_check_user, $db->connection);
					if (mysql_num_rows($res) > 99)
					{
						$q_delete_user = "DELETE FROM z_users_".$ztable." tweet_count is NULL";
						mysql_query($q_delete_user, $db->connection); 
						
						// Grab the data for this 100 users and insert into our table.
						//$url = "http://twitter.com/statuses/user_timeline/".$tweet['from_user'].".json?count=1";
						$url = "http://api.twitter.com/1/users/lookup.json?screen_name=";
						for ($i = 0;$i < mysql_num_rows($res);$i++)
						{
							$row = mysql_fetch_assoc($res);
							$url = $url.$row['id'];
							if ($i != mysql_num_rows($res)-1)
								$url = $url.",";
						}
						$url = $url."&include_entities=true";
						fputs($fd, $url);
						// Now grab that user's info from twitter api.					
						$oauth = array( 'oauth_consumer_key' => $tk_oauth_consumer_key, 
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
						
						$curlData = json_decode($json, true);
						
						foreach ($curlData as $user)
						{
							$q_insert_users = "INSERT INTO z_users_".$ztable." values('".$user['screen_name']."', '".$user['statuses_count']."', '".$user['followers_count']."', '".$user['friends_count']."', '".$user['location']."', '".$user['name']."');";	
							mysql_query($q_insert_users, $db->connection);
						}
						//fputs($fd, $curlData);					
						//curl request
						$curl = curl_init();
						curl_setopt($curl, CURLOPT_URL, $url);
						curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
						$curlData = curl_exec($curl);
						curl_close($curl);
						//decoding json structure into array
						//$user = $curlData['user'];
						fclose($fd);
					}*/
					$q_insert_users = "INSERT INTO z_users_".$ztable." values('".$tweet['from_user']."', null, null, null, null, null);";
					mysql_query($q_insert_users, $db->connection);
	
        	} else {
        		echo " vs. $keyword = not found\n";
        		}
        	}
        echo "---------------\n";
        }
    
    // delete tweets in flag
    $q = "delete from rawstream where flag = '$script_key'";
    echo $q."\n";
    mysql_query($q, $db->connection);
    
    // update counts
    foreach ($preds as $ztable=>$keyword) {
    	$q_count = "select count(id) from z_$ztable";
    	$r_count = mysql_query($q_count, $db->connection);
    	$r_count = mysql_fetch_assoc($r_count);
    	$q_update = "update archives set count = '".$r_count['count(id)']."' where id = '$ztable'";
    	echo $q_update."\n";
    	mysql_query($q_update, $db->connection);
    	
    }
    
	// update pid and last_ping in process table
	mysql_query("update processes set last_ping = '".time()."' where pid = '$pid'", $db->connection);
	echo "update pid\n";
}









?>
