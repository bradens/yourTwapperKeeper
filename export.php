<?php 
	/**
	 * @author Braden Simpson
	 * Include an export functionality for TwapperKeeper Archives
	 * The columns included in this will have 
	 * **INSERT COLUMNS HERE**
	 */

	require_once('config.php');
	 
	if (!ISSET($_GET['id']))
		echo "<h1>Error getting ID of the archive</h1>\n";
		
	$archiveID = $_GET['id'];
	$sqlData = 'SELECT archivesource, created_at, from_user, text, to_users FROM z_'.$archiveID;
	$connection = $db->connection;
	$res = mysql_query($sqlData, $connection);
	
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename=data.csv');
	$fullPath = 'resources/export'.$archiveID.'.csv';
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
