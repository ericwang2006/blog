<?php
// echo $_SERVER['REMOTE_ADDR']; 
if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
    $clientIP = $_SERVER['HTTP_X_REAL_IP'];
} else {
    $clientIP = $_SERVER['REMOTE_ADDR'];
}
echo $clientIP;
?>