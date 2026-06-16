<?php
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST["column"]);
    $state = $_POST["state"] === "checked" ? "checked" : "unchecked";

    $filename = __DIR__ . "/$column.txt";
    file_put_contents($filename, $state);
}
?>