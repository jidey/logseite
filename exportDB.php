<?php
/* vars for export */
// database record to be exported
$db_record = 'vmpower';
// optional where query
$where = 'where Managable=1';
// filename for export
$csv_filename = 'SQS_VM_onoff_daily.csv';

// database variables
$hostname = "localhost";
$user = "root";
$password = "cascas";
$database = "vmpowermngt";

// Database connecten voor alle services
$conn = new mysqli($hostname, $user, $password, $database);
if ($conn->connect_error) {
	die("Connection failed: " . $conn->connect_error);
}

$selectJobs = "SELECT * FROM ".$db_record." ".$where;
$JobsList = $conn->query($selectJobs);	
 
if($JobsList->num_rows > 0){
    $delimiter = ",";
    
    //create a file pointer for write
    $f = fopen('php://memory', 'w');
 
    //set column headers
    $fields = array('VMName', 'Managable', 'Off_evening', 'On_morning', 'Off_morning', 'On_evening');
    fputcsv($f, $fields, $delimiter);
 
    //write to file
    while($row = $JobsList->fetch_assoc()){
        $lineData = array($row['VMName'], $row['Managable'], $row['Off_evening'], $row['On_morning'], $row['Off_morning'],$row['On_evening']);
		fputcsv($f, $lineData, $delimiter);
    }
    fseek($f, 0);
 
    //set headers to download file
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $csv_filename . '";');
 
    fpassthru($f);
}
exit;









// create var to be filled with export data
$csv_export = '';

// query to get data from database

$field = $conn->query($query);	
	
// create line with field names
for($i = 0; $i < $field; $i++) {
  if($i <> ($field-1)) {
		$csv_export.= mysqli_fetch_fields($query,$i).',';
	}
	else
	{
		$csv_export.= mysqli_fetch_fields($query,$i);
	}
}
// newline (seems to work both on Linux & Windows servers)
$csv_export.= '
';

while($row = mysqli_fetch_array($query)) {
  // create line with field values
  for($i = 0; $i < $field; $i++) {
    if($i <> ($field-1)) {
		$csv_export.= $row[mysqli_fetch_fields($query,$i)].',';
	}
	else
	{
		$csv_export.= $row[mysqli_fetch_fields($query,$i)];
	}
  }	
  $csv_export.= '
';	
}

// Export the data and prompt a csv file for download
header("Content-type: text/x-csv");
header("Content-Disposition: attachment; filename=".$csv_filename."");
echo($csv_export);

?>