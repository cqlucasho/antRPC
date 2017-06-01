<?php
$string = pack("n", 6334);
echo $string;
$string1 = unpack('n', $string);
var_dump($string1);