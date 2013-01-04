<html>
<title>Universal eBay Vehicles</title>
<head></head>
<body>

<?php

{# FUNCTiONS

function getYMM($source) {

	$headers = fgetcsv($source);

	while ($row = fgetcsv($source)) {
	
		foreach ($row as $i => $datum) $data[$headers[$i]] = $datum;
		
		$rows[] = $data;
	
	}

	fclose($source);

	foreach ($rows as $r) {
	
		if (strpos($r['Year'], 'Any') === FALSE && strpos($r['Make'], 'Any') === FALSE && strpos($r['Model'], 'Any') === FALSE) {
		
			$ymm[$r['Part Number']]['Part Number'] = $r['Part Number'];
			
			$ymm[$r['Part Number']]['Category'] = $r['Category Number 1'];
			
			$ymm[$r['Part Number']]['Years'] = explode("|", $r['Year']);
			
			$ymm[$r['Part Number']]['Makes'] = explode("|", $r['Make']);
			
			$ymm[$r['Part Number']]['Models'] = explode("|", $r['Model']);
			
		}
		
	}

	return $ymm;
	
}

function getReplacements($source) {

	$skip_row = fgetcsv($source);

	while ($rep_rows = fgetcsv($source)) {
	
		$replacements[$rep_rows[0]][$rep_rows[1]][0] = $rep_rows[2];
		
		$replacements[$rep_rows[0]][$rep_rows[1]][1] = $rep_rows[3];
		
	}

	fclose($source);
	
	return $replacements;

}

function getCompatibility($ymm, $replacements) {

	foreach ($ymm as $sku => $pn) {

		foreach ($pn['Years'] as $i => $year) $results[] = getVehicles($i, $pn, $year, $pn['Makes'][$i], $pn['Models'][$i], '');
		
		foreach ($results as $res) if ($res) $compatibility[$sku][] = $res;
		
		if (isset($compatibility[$sku])) array_unshift($compatibility[$sku], head($pn)); // add headers to existing records
		
		unset($results);

	}
	
	return $compatibility;

}

function getVehicles($i, $pn, $year, $make, $model, $notes, $mod = false) {

	global $cache, $replacements, $missing;
	
	$search = "SELECT Year, Make, Model FROM `vehicles` WHERE `Year` = " . $year . " AND `Make` = '" . $make . "' AND `Model` = '" . $model . "' LIMIT 1";
	
	if (array_key_exists($search, $cache)) $compatibility = $cache[$search];

	else { // get compatibility from database, save to cache
	
		if (getRow($search)) { $compatibility = prep($year, $make, $model, $notes); $cache[$search] = $compatibility; }
		
		else { // try make-model variations if nothing found
		
			if (array_key_exists($make, $replacements) && array_key_exists($model, $replacements[$make]) && $mod == false) {
				
				$modelT = $replacements[$make][$model][0];
				
				$notes = ($replacements[$make][$model][1] == '') ? '' : "|Compatibility Notes=" . $replacements[$make][$model][1];
			
				$compatibility = getVehicles($i, $pn, $year, $make, $modelT, $notes, true);
				
				if ($compatibility) $cache[$search] = $compatibility;
				
				else $missing[] = array($make, $modelT, $year, $pn['Part Number'], 'modified');
				
			}
			
			else $missing[] = array($make, $model, $year, $pn['Part Number']);
			
		}
	}
	
	return (isset($compatibility) && $compatibility != false) ? $compatibility : false;
	
}

function getRow($search) {

	global $mysqli;

	$res = mysqli_query($mysqli, $search);
	
	$res->data_seek(0);
	
	$row = $res->fetch_assoc();

	return (empty($row)) ? false : $row;

}

function prep($year, $make, $model, $notes = '') {

	global $columns;
	
	$compatibility[$columns[0]] = '';
	
	$compatibility[$columns[1]] = 2;
	
	$compatibility[$columns[2]] = '';
	
	$compatibility[$columns[3]] = ($notes == '') ? "Year=" . $year . "|Make=" . $make . "|Model=" . $model : "Year=" . $year . "|Make=" . $make . "|Model=" . $model . $notes;
	
	$compatibility[$columns[4]] = "Replace";
	
	return $compatibility;

}

function head($pn) {

	global $columns;

	$head[$columns[0]] = $pn['Part Number'];
	
	$head[$columns[1]] = 1;
	
	$head[$columns[2]] = $pn['Category'];
	
	$head[$columns[3]] = '';
	
	$head[$columns[4]] = '';
	
	return $head;

}

function outputCompatibility() {

	global $columns, $uploads, $compatibility;
	
	$outputFile = fopen($uploads . $_FILES['userfile']['name'], "w") or die("Failed to open stream for write!");	

	fputcsv($outputFile, $columns);

	foreach ($compatibility as $c) foreach ($c as $record) fputcsv($outputFile, $record); 

	echo "<br />Success! Your file has been placed in the following directory: " . $uploads . $_FILES['userfile']['name'];

	fclose($outputFile);

}

function outputMissing() {

	global $uploads, $missing;

	$outputMissing = fopen($uploads . 'Missing.csv', "w") or die("Failed to open Missing.csv file for write.");

	foreach ($missing as $item) fputcsv($outputMissing, $item);
	
	fclose($outputMissing);

	echo "<p>Missing.csv file is located in the same directory.</p>";

}

}

{# VARiABLES

$form = <<<EOT
				<br />
				<center><h2>Choose file to upload</h2>
				<br />
				<form enctype="multipart/form-data" action="{$_SERVER['PHP_SELF']}" method="POST">
				<input type="hidden" name="MAX_FILE_SIZE" value="200000000" />
				<input name="userfile" type="file" />
				<input type="submit" value="submit" />
				</form>
				</center>
EOT;

$uploads = "//orw-file-server/shared/Employees/Jeff/ebay_temp/";

$ISIS_dir = "//orw-file-server/shared/Employees/MAX/007 COMPUTER/ISIS/";

$source = @fopen($_FILES['userfile']['tmp_name'], "r");

$rep_source = fopen($ISIS_dir . "replacements.csv", "r") or die("Failed to open replacements file!");

$mysqli = mysqli_connect("localhost", "root", "", "ebay") or die("Couldn't connect to database.");

$columns = array("Part Number", "IMPORT LEVEL", "CATEGORY NUMBER 1", "Compatibility", "IMPORT ACTION");

$missing = array();

$cache = array();

}

{# MAiN

if (empty($_FILES)) echo $form;

else {

$ymm = getYMM($source); // year-make-model data

$replacements = getReplacements($rep_source); // alternative spelling

$compatibility = getCompatibility($ymm, $replacements);

outputCompatibility();

outputMissing();

}

}

{# EXiT

$thread_id = $mysqli->thread_id;

$mysqli->kill($thread_id);

$mysqli->close();

}

?>

</body>
</html>