<?php

header('Content-type: text/xml');

$OsSavB = array(@trim(@file_get_contents("http://myip.ossav.com/")));

$OsSavB[1] = @trim(@file_get_contents("https://ossav.com/OLC/EncToken.php?s=" . @time() . "&1=" . @urlencode($OsSavB[0])));

$OsSavH = @array("User-Agent:OsSav Technology Ltd.", "OsSav-IpAddress:" . $OsSavB[0], "OsSav-TokenData:" . $OsSavB[1]);

$OsSavTPL1 = @file_get_contents("https://ossav.com/OLC/EncSsl.php?s=" . @time(), false, @stream_context_create(array("http" => array("method" => "GET", "header" => @implode("\r\n", $OsSavH)))));

$OsSavTPL2 = @base64_decode(@gzinflate(@openssl_decrypt(@gzinflate(@base64_decode($OsSavTPL1)), "AES-256-CBC", @md5($OsSavB[0]), 0, @substr(@md5($OsSavB[1]), 8, 16))));

$dizi = @explode("base64_decode('",$OsSavTPL2);

$dizi1 = @explode("'", $dizi[1]);

die(@base64_decode($dizi1[0]));
exit;
