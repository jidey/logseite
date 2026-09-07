<p>
  <?php
  //include("dashboard.php");
    echo "<a href=\"\logs\dash.php\" class=\"btn btn-success btn-sm\" role=\"button\" \"btn-sm\>Dashboard</a> ";
  ?>
	<button class="btn btn-warning btn-sm" type="button" data-toggle="collapse" data-target="#menu1" aria-expanded="false" aria-controls="collapse1">
  smartWe
  </button>
  
  <button class="btn btn-primary btn-sm" type="button" data-toggle="collapse" data-target="#menu2" aria-expanded="false" aria-controls="collapse2">
  Web
  </button>
  
  <button class="btn btn-info btn-sm" type="button" data-toggle="collapse" data-target="#menu3" aria-expanded="false" aria-controls="collapse3">
  Desktop
  </button>
  <?php
  
  //echo "<a href=\"\logs\graphs.php\" class=\"btn btn-success btn-sm\" role=\"button\"><span class=\"glyphicon glyphicon-signal\"></span> Time</a> ";
  echo "<a href=\"\logs\config.php\" class=\"btn btn-sm btn-default\" role=\"button\"><span class=\"glyphicon glyphicon-wrench\"></span> SQS Config</a> ";		
  //echo "<a href=\"\logs\runfeature.php\" target=\"_blank\" class=\"btn btn-default btn-sm\" role=\"button\" \"btn-sm\>SD Feature Test</a> ";
  
  ?>
</p>

<div class="collapse" id="menu1">
<?php
echo "<a href=\"\logs\index.php?LogVersion=we_hf&Testtype=we_hf&Product=weWebSel&TestBrowser=chrome\" class=\"btn btn-warning btn-sm\" role=\"button\">We (HF)</a> ";
echo "<a href=\"\logs\index.php?LogVersion=we_rc&Testtype=we_rc&Product=weWebSel&TestBrowser=chrome\" class=\"btn btn-warning btn-sm\" role=\"button\">We (RC)</a> ";
echo "<a href=\"\logs\index.php?LogVersion=we_dev&Testtype=we_dev&Product=weWebSel&TestBrowser=chrome\" class=\"btn btn-warning btn-sm\" role=\"button\">We (DEV)</a> ";
echo "<a href=\"\logs\index.php?LogVersion=we_feat&Testtype=we_feat&Product=weWebSel&TestBrowser=chrome\" class=\"btn btn-danger btn-sm\" role=\"button\">We (feature)</a>";
?>
</div>

<div class="collapse" id="menu2">
<?php

echo "<a href=\"\logs\index.php?LogVersion=x16_hf&Testtype=hf_x16&Product=gWWebSel\" class=\"btn btn-danger btn-sm\" role=\"button\" \"btn-sm\>Web X16 (HF)</a> ";
echo "<a href=\"\logs\index.php?LogVersion=x16_rc&Testtype=rc_x16&Product=gWWebSel\" class=\"btn btn-danger btn-sm\" role=\"button\" \"btn-sm\>Web X16 (RC)</a> ";
echo "<a href=\"\logs\index.php?LogVersion=x16_dev&Testtype=dev_x16&Product=gWWebSel\" class=\"btn btn-danger btn-sm\" role=\"button\ \"btn-sm\">Web X16 (DEV)</a> ";
echo "<a href=\"\logs\index.php?LogVersion=x17_hf&Testtype=hf_x17&Product=gWWebSel\" class=\"btn btn-info btn-sm\" role=\"button\" \"btn-sm\>Web X17 (HF)</a> ";
echo "<a href=\"\logs\index.php?LogVersion=x17_rc&Testtype=rc_x17&Product=gWWebSel\" class=\"btn btn-info btn-sm\" role=\"button\ \"btn-sm\">Web X17 (RC)</a> ";
echo "<a href=\"\logs\index.php?LogVersion=x17_dev&Testtype=dev_x17&Product=gWWebSel\" class=\"btn btn-info btn-sm\" role=\"button\ \"btn-sm\">Web X17 (DEV)</a> ";
//echo "<a href=\"\logs\index.php?LogVersion=x18_hf&Testtype=hf_x18&Product=gWWebSel\" class=\"btn btn-warning btn-sm\" role=\"button\" \"btn-sm\>Web X18 (HF)</a> ";
//echo "<a href=\"\logs\index.php?LogVersion=x18_rc&Testtype=rc_x18&Product=gWWebSel\" class=\"btn btn-warning btn-sm\" role=\"button\ \"btn-sm\">Web X18 (RC)</a> ";
echo "<a class=\"btn btn-danger btn-sm\" role=\"button\" \"btn-sm\><s>Web X18 (HF)</s></a> ";
echo "<a class=\"btn btn-danger btn-sm\" role=\"button\ \"btn-sm\"><s>Web X18 (RC)</s></a>";
echo "<a href=\"\logs\index.php?LogVersion=x18_dev&Testtype=dev_x18&Product=gWWebSel\" class=\"btn btn-warning btn-sm\" role=\"button\ \"btn-sm\">Web X18 (DEV)</a> ";
echo "<a href=\"\logs\index.php?LogVersion=web_feat&Testtype=web_feat&Product=gWWebSel\" class=\"btn btn-default btn-sm\" role=\"button\">Web (feature)</a>";
?>
</div>

<div class="collapse" id="menu3">
<?php
//echo "<a href=\"\logs\index.php?LogVersion=x15&Testtype=hf_x15&Product=gWClient\" class=\"btn btn-warning btn-sm\" role=\"button\">gW X15 (HF)</a>";    
echo "<a href=\"\logs\index.php?LogVersion=x16&Testtype=hf_x16&Product=gWClient\" class=\"btn btn-danger btn-sm\" role=\"button\">gW X16 (HF)</a>";   
echo "<a href=\"\logs\index.php?LogVersion=x16&Testtype=rc_x16&Product=gWClient\" class=\"btn btn-danger btn-sm\" role=\"button\">gW X16 (RC)</a> ";
//echo "<a href=\"\logs\index.php?LogVersion=x16&Testtype=dev_x16&Product=gWClient\" class=\"btn btn-danger btn-sm\" role=\"button\">gW X16 (DEV)</a> ";
?>
</div>