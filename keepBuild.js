function disableSaving(vmNameToSave) 
{
	var image = 'validate' + vmNameToSave;
	var x = document.getElementById(image);
	x.style.display = "none";
	var image = 'cancel' + vmNameToSave;
	var x = document.getElementById(image);
	x.style.display = "none";
}  		
function enableSaving(vmNameToSave) 
{
	var image = 'validate' + vmNameToSave;
	var x = document.getElementById(image);
	x.style.display = "block";
	var image = 'cancel' + vmNameToSave;
	var x = document.getElementById(image);
	x.style.display = "block";
}  	
function cancelVMConfig(vmName)
{
	disableSaving(vmName);
}
function updateVMConfig(vmName)
{
	var managable=document.getElementsByName('Managable_'+vmName)[0].checked ? "1" : "0";
	var OFF_Evening=document.getElementsByName('Off_evening_'+vmName)[0].checked ? "1" : "0";
	var ON_Morning=document.getElementsByName('On_morning_'+vmName)[0].checked ? "1" : "0";
	var OFF_Morning=document.getElementsByName('Off_morning_'+vmName)[0].checked ? "1" : "0";
	var ON_Evening=document.getElementsByName('On_evening_'+vmName)[0].checked ? "1" : "0";
			
	var xmlhttp = new XMLHttpRequest();
	xmlhttp.onreadystatechange = function() {
		if (this.readyState == 4 && this.status == 200) {
		   disableSaving(vmName);
		   if (managable == 0) {
			location.reload();
		   }
		}
	};
	xmlhttp.open("POST", "postvm.php?vmname="+vmName+"&managable="+managable+"&OFF_Evening="+OFF_Evening+"&ON_Morning="+ON_Morning+"&OFF_Morning="+OFF_Morning+"&ON_Evening="+ON_Evening);
	xmlhttp.send();
}
function setManagable(vMName)
{
	console.log('TEST');
	console.log(vMName);
	var xmlhttp = new XMLHttpRequest();
	 xmlhttp.onreadystatechange = function() {
		if (this.readyState == 4 && this.status == 200) {
		   location.reload();
		}
	};
	xmlhttp.open("POST", "postvm.php?vmname="+vMName+"&managable=1");
	xmlhttp.send();
	
}

function SetNightlyVersion(value,version)
{
	console.log("SetNightlyVersion:")
	console.log(version)
	console.log(value)
	console.log("")
	$.ajax({
		url: "KeepBuild.php?value=" + value + "&Nightly=1&Version=" + version
		}		
	);
	location.reload();

	//ReadNightlyVersionStatus(version);
}

function ReadNightlyVersionStatus(version)
{
	$.ajax({
		  url: "KeepBuild.php?Nightly=1&Version=" + version			  
		}).done(function(data) {
			console.log("ReadNightlyVersionStatus: ")
			console.log("version: "+version)	
			console.log("Data: "+data)		
			console.log("")					
			
			if (version == 'x14')
			{
				if (data == 'hf_x14')
				{
					$("input[name=DisableX14]").prop('checked', false);
					//$("input[name=SaveTestTypeDEVx14]").prop('checked', false);
					$("input[name=SaveTestTypeRCx14]").prop('checked', false);
					$("input[name=SaveTestTypeHFx14]").prop('checked', true);
				}
				else if (data == 'rc_x14')
				{
					$("input[name=DisableX14]").prop('checked', false);
					//$("input[name=SaveTestTypeDEVx14]").prop('checked', false);
					$("input[name=SaveTestTypeRCx14]").prop('checked', true);
					$("input[name=SaveTestTypeHFx14]").prop('checked', false);				
				}
				else if (data == 'dev_x14')
				{
					$("input[name=DisableX14]").prop('checked', false);
					//$("input[name=SaveTestTypeDEVx14]").prop('checked', true);
					$("input[name=SaveTestTypeRCx14]").prop('checked', false);
					$("input[name=SaveTestTypeHFx14]").prop('checked', false);				
				}
				else
				{
					$("input[name=DisableX14]").prop('checked', true);
					$("input[name=SaveTestTypeRCx14]").prop('checked', false);
					$("input[name=SaveTestTypeHFx14]").prop('checked', false);	
					//$("input[name=SaveTestTypeDEVx14]").prop('checked', false);					
				}
			}
			if (version == 'x15')
			{
				if (data == 'hf_x15')
				{
					$("input[name=DisableX15]").prop('checked', false);
					//$("input[name=SaveTestTypeDEVx15]").prop('checked', false);
					$("input[name=SaveTestTypeRCx15]").prop('checked', false);
					$("input[name=SaveTestTypeHFx15]").prop('checked', true);
				}
				else if (data == 'rc_x15')
				{
					$("input[name=DisableX15]").prop('checked', false);
					//$("input[name=SaveTestTypeDEVx15]").prop('checked', false);
					$("input[name=SaveTestTypeRCx15]").prop('checked', true);
					$("input[name=SaveTestTypeHFx15]").prop('checked', false);				
				}
				else if (data == 'dev_x15')
				{
					$("input[name=DisableX15]").prop('checked', false);
					//$("input[name=SaveTestTypeDEVx15]").prop('checked', true);
					$("input[name=SaveTestTypeRCx15]").prop('checked', false);
					$("input[name=SaveTestTypeHFx15]").prop('checked', false);				
				}
				else
				{
					$("input[name=DisableX15]").prop('checked', true);
					$("input[name=SaveTestTypeRCx15]").prop('checked', false);
					$("input[name=SaveTestTypeHFx15]").prop('checked', false);	
					//$("input[name=SaveTestTypeDEVx15]").prop('checked', false);					
				}
			}
            if (version == 'x16')
			{
				if (data == 'hf_x16')
				{
					$("input[name=DisableX16]").prop('checked', false);
					$("input[name=SaveTestTypeDEVx16]").prop('checked', false);
					$("input[name=SaveTestTypeRCx16]").prop('checked', false);
					$("input[name=SaveTestTypeHFx16]").prop('checked', true);
				}
				else if (data == 'rc_x16')
				{
					$("input[name=DisableX16]").prop('checked', false);
					$("input[name=SaveTestTypeDEVx16]").prop('checked', false);
					$("input[name=SaveTestTypeRCx16]").prop('checked', true);
					$("input[name=SaveTestTypeHFx16]").prop('checked', false);				
				}
				else if (data == 'dev_x16')
				{
					$("input[name=DisableX16]").prop('checked', false);
					$("input[name=SaveTestTypeDEVx16]").prop('checked', true);
					$("input[name=SaveTestTypeRCx16]").prop('checked', false);
					$("input[name=SaveTestTypeHFx16]").prop('checked', false);				
				}
				else
				{
					$("input[name=DisableX16]").prop('checked', true);
					$("input[name=SaveTestTypeRCx16]").prop('checked', false);
					$("input[name=SaveTestTypeHFx16]").prop('checked', false);	
					$("input[name=SaveTestTypeDEVx16]").prop('checked', false);					
				}
			}
           /* if (version == 'x15web')
			{
                $("input[name=DisableX15web]").prop('checked', true);                  
            }
            if (version == 'x14web')
			{
                $("input[name=DisableX14web]").prop('checked', true);  
            }*/
		});					
}

$(document).ready(function() {
	ReadNightlyVersionStatus('x14');
	ReadNightlyVersionStatus('x15');
    ReadNightlyVersionStatus('x16');
    //ReadNightlyVersionStatus('x15web');
    //ReadNightlyVersionStatus('x14web');
});

