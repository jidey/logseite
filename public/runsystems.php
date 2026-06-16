<?php
    //echo $LogVersion."<br>";
	
    if (substr($LogVersion, 0, 2) == "we") {
        $versionNumber = "we";
    } else {
        // Extraire x15, x16, x17, x18, etc.
        // Format: "x17_rc" -> extraire "x17"
        preg_match('/x\d+/', $LogVersion, $matches);
        $versionNumber = $matches[0] ?? 'x17';
    }

    if ($versionNumber == "we")
    {
    ?>
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
    if ($versionNumber == "x18")
    {
    ?>
        <select name="Test_x18" size="1">
            <option>Grid</option>
            <option>JDF</option>
            <option>SV</option>
            <option>OG</option>
            <option>AS</option>
            <option>x18dev</option>
            <option>x18rc</option>
            <option>x18hf</option>
        </select>	    
    <?php	
    }
    if ($versionNumber == "x17")
    {        
    ?>
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
    <?php	
    }
    if ($versionNumber == "x16")
    {
    ?>
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
    if ($versionNumber == "x15")
    {
    ?>
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
    ?>