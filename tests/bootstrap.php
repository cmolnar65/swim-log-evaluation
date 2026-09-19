<?php
define('ABSPATH',__DIR__.'/');
if(!function_exists('__')){function __($s,$d=null){return$s;}}
if(!function_exists('wp_json_encode')){function wp_json_encode($v){return json_encode($v);}}
if(!function_exists('absint')){function absint($v){return abs((int)$v);}}
if(!function_exists('get_date_from_gmt')){function get_date_from_gmt($s,$f='Y-m-d H:i:s'){return gmdate($f,strtotime($s.' UTC'));}}
if(!class_exists('WP_Error')){class WP_Error{private $code,$message;public function __construct($c='',$m=''){$this->code=$c;$this->message=$m;}public function get_error_message(){return$this->message;}public function get_error_code(){return$this->code;}}}
if(!function_exists('is_wp_error')){function is_wp_error($v){return$v instanceof WP_Error;}}
require_once dirname(__DIR__).'/includes/class-database.php';
require_once dirname(__DIR__).'/includes/class-fit-importer.php';
require_once dirname(__DIR__).'/includes/class-csv-importer.php';
require_once dirname(__DIR__).'/includes/class-evaluator.php';

require_once dirname(__DIR__).'/includes/class-importer.php';
