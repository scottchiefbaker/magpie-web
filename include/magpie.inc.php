<?php

$BASE_DIR = realpath(dirname(__FILE__) . "/../");

// This is the zstandard dictionary used to compress new test results
// Note: it must be in the `dict_info` table in the DB for decompression to work
$ZSTD_DICT = "$BASE_DIR/include/zstd-dict/magpie-dict-2025";

////////////////////////////////////////////////////////////////////////////////
// Security headers. The CSP blocks injected scripts even if an XSS slips
// through; style-src 'unsafe-inline' is required by the Bootstrap utility
// style="" attributes. nosniff stops browsers from MIME-sniffing responses.
header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'; object-src 'none'");
header("X-Content-Type-Options: nosniff");
////////////////////////////////////////////////////////////////////////////////
require("$BASE_DIR/include/krumo/class.krumo.php");
////////////////////////////////////////////////////////////////////////////////
require("$BASE_DIR/include/dbquery/db_query.class.php");
////////////////////////////////////////////////////////////////////////////////
require("$BASE_DIR/include/sluz/sluz.class.php");
$s = new sluz();
$s->setEscapeHtml(true);
////////////////////////////////////////////////////////////////////////////////
require("$BASE_DIR/include/global.inc.php");
$dbq = db_init();

if (isset($_GET['debug']) && !is_admin()) {
	unset($_GET['debug']);
}
////////////////////////////////////////////////////////////////////////////////
$mc  = new Memcached();
$mc->addServer('127.0.0.1', 11211);

// Compression is off for now. This was slowing things down one some larger
// data set()'s
$mc->setOption(Memcached::OPT_COMPRESSION, false);
////////////////////////////////////////////////////////////////////////////////
require("$BASE_DIR/bot-rate-limit.php");
////////////////////////////////////////////////////////////////////////////////
openlog("MagpieWeb", LOG_PID | LOG_PERROR, LOG_LOCAL7);
