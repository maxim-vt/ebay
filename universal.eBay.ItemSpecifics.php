<html>
<title>eBay Item Specifics</title>
<head></head>
<body>

<?php

if (1) { // VARiABLES

$serverName = "JOHNBRINDELL-PC\XXXXXXXXXXXXX";
$connectionOptions = array("Database"=>"XXXXXXXX");

$database = sqlsrv_connect($serverName, $connectionOptions) or die(var_dump(sqlsrv_errors()));

$source = empty($_FILES) ? NULL : fopen($_FILES['userfile']['tmp_name'], "r");

$manufacturer = empty($_FILES) ? NULL : substr($_FILES['userfile']['name'], 0, strpos($_FILES['userfile']['name'], ' - '));

$form = <<<EOT
				<h1><center><font color="red"><br />BACKUP DATABASE FIRST!!!</font></center></h1>
				<center><h3>Selected database: {$connectionOptions['Database']}<h3></center>
				<br />
				<center><h2>Choose file to upload</h2>
				<br />
				<form enctype="multipart/form-data" action="{$_SERVER['PHP_SELF']}" method="POST">
				<input type="hidden" name="MAX_FILE_SIZE" value="200000000" />
				<input name="userfile" type="file" multiple />
				<input type="submit" value="submit" />
				</form>
				</center>
EOT;
	
}

if (1) { // FUNCTiONS

function getParts() {

	global $manufacturer, $database, $source;

  $request = "select [ManufacturerPartNumber] from [dbo].[Items] where Manufacturer='" . $manufacturer . "'";
	
	$result = sqlsrv_query($database, $request);
	
	if ($result === false) exit(var_dump(sqlsrv_errors()));
	
	while ($row = sqlsrv_fetch_array($result)) $results[] = $row[0];
	
	sqlsrv_free_stmt($result);

	$specifics = loadSpecifics($source);
	
	foreach ($specifics as $k => $part) {
	
	  if (!in_array($part['Manufacturer Part Number'], $results)) unset($specifics[$k]);
		
	}
	
	return $specifics;
	
}	

function loadSpecifics($file) {

	$head = fgetcsv($file);

	while ($line = fgetcsv($file)) {
		foreach ($line as $i => $l) {
			if (!empty($l)) {
				if (strpos($l, '|')) {
					foreach (explode('|', $l) as $k => $li) {
						$values[$head[$i]][$k] = $li; 
					}
				}
				else $values[$head[$i]] = $l;
			}
		}
		$specifics['@' . $line[0]] = $values;
		unset($values);
	}

	return $specifics;

}

function doUpdate($db, $input) { 

	$stmt = sqlsrv_query($db, $input);

	if ($stmt === false) return 0;
	
	else { sqlsrv_free_stmt($stmt); return 1; }

}

function printOutput($sku) {

	global $database, $manufacturer;

	$tsql = "SELECT all [XMLBlob],[ItemSpecifics],[ISSummary1] from [dbo].[Items] where Manufacturer='" . $manufacturer . "' and ManufacturerPartNumber='" . $sku . "'";

	$stmt = sqlsrv_query($database, $tsql);

	if ($stmt === false) die(print_r(sqlsrv_errors(), true));

	else while ($row = sqlsrv_fetch_array($stmt)) $rows[] = $row;

	var_dump($rows);

	sqlsrv_free_stmt($stmt);

}

function makeXMLBlob($array) {

	$xmlBlob = "<SelectedAttributes><Cat1><ItemSpecificsType><ItemSpecifics>";

	foreach ($array as $name => $value) {
		if (is_array($value)) {
			$xmlBlob .= '<NameValueList><Name>' . $name . '</Name>';
			foreach ($value as $val) {
				$xmlBlob .= '<Value>' . $val . '</Value>';
			}
			$xmlBlob .= '</NameValueList>';
		}
		else $xmlBlob .= '<NameValueList><Name>' . $name . '</Name><Value>' . $value . '</Value></NameValueList>';
	}

	$xmlBlob .= "</ItemSpecifics></ItemSpecificsType></Cat1></SelectedAttributes>";

	return $xmlBlob;

}

function makeSummary($array) {

	$summary = '';

	foreach ($array as $name => $value) {
		if (is_array($value)) {
			$summary .= $name . ':';
			foreach ($value as $val) {
				$summary .= $val . ',';
			}
			$summary = rtrim($summary, ',');
			$summary .= ';';
		}
		else $summary .= $name . ':' . $value . ';';
	}

	return $summary;

}

function makeSpecs($array) {

	$specs = '';

	foreach ($array as $name => $value) {
		if (is_array($value)) {
			$specs .= $name . '=';
			foreach ($value as $val) {
				$specs .= $val . '|';
			}
			$specs = rtrim($specs, '|');
			$specs .= "\r\n";
		}
		else $specs .= $name . '=' . $value . "\r\n";
	}

	return $specs;

}

function makeQuery($array) {

	global $manufacturer;

	$query = "update [dbo].[Items] ";
	$query .= "set XMLBlob = '" . makeXMLBlob($array) . "'";
	$query .= ", ItemSpecifics = '" . makeSpecs($array) . "'";
	$query .= ", ISSummary1 = '" . makeSummary($array) . "'";
	$query .= " where Manufacturer='" . $manufacturer . "' ";
	$query .= "and ManufacturerPartNumber='" . $array['Manufacturer Part Number'] . "'";

	return $query;

}
	

}


if (empty($_FILES)) echo $form;

else {

foreach (getParts() as $part) {
	
  $query = makeQuery($part);
	
	doUpdate($database, $query) or die(var_dump(sqlsrv_errors()));

}

// printOutput('1524B100-C1');
echo "Success! Item Specifics for " . $manufacturer . " have been inserted into the " . $connectionOptions['Database'] . " database.";


sqlsrv_close($database);

}

?>

</body>
</html>