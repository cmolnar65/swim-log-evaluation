<?php
define('ABSPATH',__DIR__.'/');
define('SWIMLOG_EVALUATION_DIR',dirname(__DIR__).'/');
define('SWIMLOG_EVALUATION_URL','https://example.test/wp-content/plugins/chriss-swim-training-progress-evaluation/');
define('SWIMLOG_EVALUATION_VERSION','0.6-test');
if(!function_exists('__')){function __($s,$d=null){return$s;}}
if(!function_exists('wp_json_encode')){function wp_json_encode($v){return json_encode($v);}}
if(!function_exists('absint')){function absint($v){return abs((int)$v);}}
if(!function_exists('get_date_from_gmt')){function get_date_from_gmt($s,$f='Y-m-d H:i:s'){return gmdate($f,strtotime($s.' UTC'));}}
if(!function_exists('wp_timezone')){function wp_timezone(){return new DateTimeZone('America/Denver');}}
if(!function_exists('sanitize_key')){function sanitize_key($v){return strtolower(preg_replace('/[^a-z0-9_\-]/','',(string)$v));}}
if(!function_exists('sanitize_text_field')){function sanitize_text_field($v){return trim((string)$v);}}
if(!function_exists('sanitize_textarea_field')){function sanitize_textarea_field($v){return trim((string)$v);}}
if(!function_exists('shortcode_atts')){function shortcode_atts($pairs,$atts){return array_merge($pairs,(array)$atts);}}
if(!function_exists('esc_html')){function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}}
if(!function_exists('esc_html__')){function esc_html__($s,$d=null){return esc_html($s);}}
if(!function_exists('wp_enqueue_style')){function wp_enqueue_style(){return true;}}
if(!function_exists('wp_date')){function wp_date($f,$ts=null,$tz=null){$d=new DateTimeImmutable('@'.($ts??time()));if($tz)$d=$d->setTimezone($tz);return $d->format($f);}}
if(!function_exists('number_format_i18n')){function number_format_i18n($number,$decimals=0){return number_format((float)$number,(int)$decimals,'.',',');}}
if(!function_exists('is_user_logged_in')){function is_user_logged_in(){global $swimlog_test_logged_in;return null===$swimlog_test_logged_in?true:(bool)$swimlog_test_logged_in;}}
if(!function_exists('get_current_user_id')){function get_current_user_id(){global $swimlog_test_current_user_id;return $swimlog_test_current_user_id?:42;}}
if(!function_exists('current_user_can')){function current_user_can($cap){global $swimlog_test_capabilities;return !empty($swimlog_test_capabilities[$cap]);}}
if(!function_exists('get_user_meta')){function get_user_meta($uid,$key,$single=true){global $swimlog_test_user_meta;return $swimlog_test_user_meta[$uid][$key]??'';}}
if(!class_exists('WP_Error')){class WP_Error{private $code,$message,$data;public function __construct($c='',$m='',$d=null){$this->code=$c;$this->message=$m;$this->data=$d;}public function get_error_message(){return$this->message;}public function get_error_code(){return$this->code;}public function get_error_data(){return$this->data;}}}
if(!function_exists('is_wp_error')){function is_wp_error($v){return$v instanceof WP_Error;}}
require_once dirname(__DIR__).'/includes/class-database.php';
require_once dirname(__DIR__).'/includes/class-fit-importer.php';
require_once dirname(__DIR__).'/includes/class-csv-importer.php';
require_once dirname(__DIR__).'/includes/class-xml-importer.php';
require_once dirname(__DIR__).'/includes/class-evaluator.php';
require_once dirname(__DIR__).'/includes/class-workout.php';
require_once dirname(__DIR__).'/includes/class-event.php';
require_once dirname(__DIR__).'/includes/class-planned-workout.php';
require_once dirname(__DIR__).'/includes/class-calendar.php';
require_once dirname(__DIR__).'/includes/class-pool-analytics.php';
require_once dirname(__DIR__).'/includes/class-training-trends.php';

require_once dirname(__DIR__).'/includes/class-location.php';

require_once dirname(__DIR__).'/includes/class-swolf.php';
require_once dirname(__DIR__).'/includes/class-importer.php';
require_once dirname(__DIR__).'/includes/class-manual-workout.php';

require_once dirname(__DIR__).'/includes/class-shortcodes.php';
require_once dirname(__DIR__).'/includes/class-blocks.php';

require_once dirname(__DIR__).'/admin/class-admin.php';

require_once dirname(__DIR__).'/includes/class-rest-api.php';
