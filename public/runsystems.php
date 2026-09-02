<?php
    //echo $LogVersion."<br>";

    if (substr($LogVersion, 0, 2) == "we") {
        $versionNumber = "we";
    } else {
        // Extract x15, x16, x17, x18, x19, etc.
        // Format: "x17_rc" -> extract "x17"
        preg_match('/x\d+/', $LogVersion, $matches);
        $versionNumber = $matches[0] ?? 'x17';
    }

    // Dynamically generates the "Jenkins Node" field for any
    // version (no need to add a block here when a new gW Web/Desktop
    // version is created in config/versions_config.php).
    if ($versionNumber === "we") {
        $selectName = "Test_Node";
        // smartWe: legacy submenu (behavior unchanged)
        $options = ['Grid', 'JDF', 'SV', 'OG', 'AS', 'x16dev', 'x16rc', 'x16hf'];
    } else {
        $selectName = "Test_" . $versionNumber;
        $options = ['Grid', 'JDF', 'SV', 'OG', 'AS', $versionNumber . 'dev', $versionNumber . 'rc', $versionNumber . 'hf'];
    }
    ?>
    <div class="form-group">
    <select name="<?php echo htmlspecialchars($selectName); ?>" size="1">
        <?php foreach ($options as $opt): ?>
        <option><?php echo htmlspecialchars($opt); ?></option>
        <?php endforeach; ?>
    </select>
    </div>
