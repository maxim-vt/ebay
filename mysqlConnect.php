<?php

ini_set('max_execution_time', 0);

{# VARiABLES

$server = "ftp://wsm:wsmFTP@172.16.1.150/pricing/";

$fileName = 'PartExport' . date('Ymd') . '.txt';

$webCopy = $server . $fileName;

$localCopy = "//orw-file-server/shared/Employees/MAX/007 COMPUTER/ISIS/CurrentISISCatalog.txt";

$sql = "LOAD data LOCAL INFILE \"//orw-file-server/shared/Employees/MAX/007 COMPUTER/ISIS/CurrentISISCatalog.txt\" INTO TABLE `table 1` FIELDS TERMINATED BY \"\\t\" IGNORE 1 LINES";

$mysqli = mysqli_connect("localhost", "root", "", "test") or die("Couldn't connect to database.");

}

{# MAiN

if (date('Ymd', filemtime($localCopy)) !== date('Ymd')) {

  if (!copy($webCopy, $localCopy)) exit("There was a problem downloading the latest ISIS Catalog {$fileName}");
	
	else {
	
    mysqli_query($mysqli, "TRUNCATE TABLE `table 1`") or die("Couldn't TRUNCATE.");
		
    mysqli_query($mysqli, $sql) or die("Something went wrong during import.");
		
    echo "ISIS Catalog is up to date and has been inserted into the database.<br>";
		
	}
	
}

else echo "ISIS Catalog is up to date.";

}

?>