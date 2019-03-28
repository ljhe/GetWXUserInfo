<?php
require "wxUserInfo.php";

$data = $_GET;

if (!isset($data['name'])) {
    echo '参数错误';
    return;
}

$res = new wxUserInfo($data['name']);
//$res->makeCsv();
