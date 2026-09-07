<?php
	
	$url = 'https://build-sqs.cas-software.dev/view/gWWeb/job/SQS_Web_TestPipe/buildWithParameters?token=TCAUTO&delay=12sec&TestName=@dummy&Testset=nestededit&DebugFeature=false&Product=Web&Test_Version=hotfix%2F12.x&Test_Node=Grid&TestBrowser=chrome&TestedBuild=Last&Hub=https%3A%2F%2Fsqs-sel-cent1.cas-software.dev';
	echo "<br>";	
	echo $url;
	echo "<br>";
	$options = [
		'http' => [
			'method'  => 'POST'
		],
		
		'ssl' => [
			'verify_peer'      => false,
			'verify_peer_name' => false,
		]
	];

	$streamContext = stream_context_create($options);
	//$result = file_get_contents($url, false, $streamContext);
	
	header('Location: ' . $url, true, 302);
	exit();

	if ($result === FALSE) {
		die('Error');
	}

?>
		