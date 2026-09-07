<?php
	if (substr($LogVersion, 0, 2) == "we")
	{
	?>
		JenkinsNode:
		<div class="form-group">
		<select name="Test_Node" size="1">
			<option>Grid</option>
			<option>JDF</option>
			<option>SV</option>
			<option>OG</option>
			<option>AS</option>
			<option>x16dev</option>
			<option>x16rc</option>
			<option>x16hf</option>			
		</select>	
		</div>
	<?php	
	}
	if (substr($LogVersion, 0, 3) == "x17")
	{
	?>
		JenkinsNode:
		<div class="form-group">
		<select name="Test_x17" size="1">
			<option>Grid</option>
			<option>JDF</option>
			<option>SV</option>
			<option>OG</option>
			<option>AS</option>
			<option>x17dev</option>
			<option>x17rc</option>
			<option>x17hf</option>
		</select>	
		</div>
	<?php	
	}
	if (substr($LogVersion, 0, 3) == "x16")
	{
	?>
		JenkinsNode:
		<div class="form-group">
		<select name="Test_x16" size="1">
			<option>Grid</option>
			<option>JDF</option>
			<option>SV</option>
			<option>OG</option>
			<option>AS</option>
			<option>x16dev</option>
			<option>x16rc</option>
			<option>x16hf</option>
		</select>	
		</div>
	<?php	
	}
    if (substr($LogVersion, 0, 3) == "x15")
	{
	?>
		JenkinsNode:
		<div class="form-group">
		<select name="Test_x15" size="1">
			<option>Grid</option>	
			<option>JDF</option>
			<option>SV</option>
			<option>OG</option>
			<option>AS</option>
			<option>x15dev</option>
			<option>x15rc</option>
			<option>x15hf</option>			
		</select>	
		</div>
	<?php	
	}
	if (substr($LogVersion, 0, 3) == "x14")
	{
	?>
		JenkinsNode:
		<div class="form-group">
		<select name="Test_x14" size="1">
			<option>Grid</option>				
		</select>	
		</div>
	<?php	
	}
	?>