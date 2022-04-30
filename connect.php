<?php

$servername = "localhost";
$username = "mitchell_g-user";
// ,_Tnyu_Mh_uS
$password = ",_Tnyu_Mh_uS";
$dbname = "mitchell_game-collection";


$db = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
// set the PDO error mode to exception
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

?>