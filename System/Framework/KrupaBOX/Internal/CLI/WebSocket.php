<?php

define("__KRUPA_IS_WEBSOCKET_CLI_REQUEST__", true);

$file = str_replace("\\", "/", __FILE__);
$fileFindIndex = strpos($file, "/System/Framework/KrupaBOX/Internal/CLI/WebSocket.php");

if ($fileFindIndex !== false)
{ $file = substr($file, 0, $fileFindIndex);
} else exit;

$indexPath = realpath($file . "/index.php");
if ($indexPath == null) exit;

include($indexPath);