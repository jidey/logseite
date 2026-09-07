<?php
if (isset($_GET['Version'])) 
{
	$version = $_GET['Version'];
}

if (isset($_GET['value'])) {
	$value = $_GET['value'];
	
	if (isset($_GET['Nightly'])) {
		//NightlyVersion.txt Schreiben
		if($version == 'x16')
		{
			$file = fopen("NightlyVersionX16.txt","w");
			fwrite($file, $value);
			fclose($file);
			echo $value;		
		}
		if($version == 'x15')
		{
			$file = fopen("NightlyVersionX15.txt","w");
			fwrite($file, $value);
			fclose($file);
			echo $value;		
		}
		if($version == 'x14')
		{
			$file = fopen("NightlyVersionX14.txt","w");
			fwrite($file, $value);
			fclose($file);
			echo $value;		
		}		
	}
} 
else{
	if (isset($_GET['Nightly'])) {
		if(isset($version)){
		//echo $version;			
			if ($version == 'x14')
			{
				//NightlyVersion.txt Lesen
				$file = fopen("NightlyVersionX14.txt","r");
				while(! feof($file))
				{
					$value= fgets($file);
				}
				fclose($file); 	
				echo $value;
			}
			if ($version == 'x15')
			{
				//NightlyVersion.txt Lesen
				$file = fopen("NightlyVersionX15.txt","r");
				while(! feof($file))
				{
					$value= fgets($file);
				}
				fclose($file); 	
				echo $value;
			}
			if ($version == 'x16')
			{
				//NightlyVersion.txt Lesen
				$file = fopen("NightlyVersionX16.txt","r");
				while(! feof($file))
				{
					$value= fgets($file);
				}
				fclose($file); 	
				echo $value;
			}
		}
	}	
}
?>
