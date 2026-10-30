<?php
namespace SwimLogEvaluation;
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Estimated equivalent steps, not pedometer measurements. */
final class Equivalent_Steps {
 const DEFAULT_RATE = 181;
 const VERSION = '1';
 public static function calculate( $w, $lengths = array(), $rate = self::DEFAULT_RATE ) {
  $rate = (int) $rate;
  if ( $rate < 1 || $rate > 1000 ) return null;
  $type = is_array($w) ? ($w['workout_type']??'pool') : ($w->workout_type??'pool');
  if (!in_array($type,array('pool','open_water'),true)) return null;
  $moving = is_array($w) ? ($w['moving_time_ms']??null) : ($w->moving_time_ms??null);
  $active = 0; $has_lengths = false;
  if ('pool' === $type) foreach ($lengths as $length) {
   $kind = is_array($length) ? ($length['length_type']??'') : ($length->length_type??'');
   $ms = is_array($length) ? ($length['elapsed_time_ms']??null) : ($length->elapsed_time_ms??null);
   if ('active' !== $kind || !is_numeric($ms) || (float)$ms <= 0) continue;
   $active += (int)$ms; $has_lengths = true;
  }
  if ($has_lengths) $method = 'pool_active_lengths';
  elseif (is_numeric($moving) && (float)$moving > 0) { $active = (int)$moving; $method = 'recorded_moving_time'; }
  else return null;
  return array('equivalent_steps'=>(int)round($active*$rate/60000),'steps_active_ms'=>$active,'steps_rate'=>$rate,'steps_method'=>$method,'steps_version'=>self::VERSION);
 }
 public static function populate_parsed($parsed) {
  if (!isset($parsed['workout']) || !is_array($parsed['workout'])) return $parsed;
  $result = self::calculate($parsed['workout'],$parsed['lengths']??array());
  foreach (array('equivalent_steps','steps_active_ms','steps_rate','steps_method','steps_version') as $key) $parsed['workout'][$key] = null === $result ? null : $result[$key];
  return $parsed;
 }
 public static function backfill($batch_size=50) {
  global $wpdb;
  $table=Database::table('workouts');$lengths=Database::table('lengths');
  $last=(int)get_option('swimlog_steps_backfill_last_id',0);
  $ids=Database::read( 'get_col', $wpdb->prepare("SELECT id FROM %i WHERE id > %d ORDER BY id ASC LIMIT %d",$table, $last,max(1,min(200,(int)$batch_size))));
  if (!is_array($ids)) return 0;
  $processed=0;
  foreach($ids as $id) {
   $id=(int)$id;
   $w=Database::read( 'get_row', $wpdb->prepare("SELECT * FROM %i WHERE id = %d",$table, $id));
   if (!$w) break;
   $rows=Database::read( 'get_results', $wpdb->prepare("SELECT length_type, elapsed_time_ms FROM %i WHERE workout_id = %d",$lengths, $id));
   if (!is_array($rows)) break;
   $values=self::calculate($w,$rows);
   if (null === $values) $values=array_fill_keys(array('equivalent_steps','steps_active_ms','steps_rate','steps_method','steps_version'),null);
   if (false === Database::write( 'update', $table,$values,array('id'=>$id))) break;
   update_option('swimlog_steps_backfill_last_id',$id,false);
   $processed++;
  }
  return $processed;
 }
}


