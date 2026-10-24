<?php
namespace SwimLogEvaluation;
if(!defined('ABSPATH')){exit;}

/** Defensive GPX/TCX parser for open-water workout tracks. */
final class XML_Importer {
 const VERSION='0.1';
 public function parse($path,$source){
  $source=strtolower((string)$source);if(!in_array($source,array('gpx','tcx'),true))return new \WP_Error('swimlog_xml_type',__('Unsupported XML workout type.','chriss-swim-training-progress-evaluation'));
  if(!function_exists('simplexml_load_file'))return new \WP_Error('swimlog_xml_unavailable',__('This server does not provide the XML support required for GPX/TCX import.','chriss-swim-training-progress-evaluation'));
  $old=libxml_use_internal_errors(true);$xml=simplexml_load_file($path,'SimpleXMLElement',LIBXML_NONET|LIBXML_NOBLANKS);libxml_clear_errors();libxml_use_internal_errors($old);
  if(false===$xml)return new \WP_Error('swimlog_xml_invalid',__('The GPX/TCX file is not valid XML.','chriss-swim-training-progress-evaluation'));
  $points='gpx'===$source?$this->gpx_points($xml):$this->tcx_points($xml);
  if(is_wp_error($points))return $points;if(count($points)<2)return new \WP_Error('swimlog_xml_track',__('The GPX/TCX file does not contain enough usable trackpoints.','chriss-swim-training-progress-evaluation'));
  $distance=0.0;$prev=null;$start=null;$end=null;$hr=array();$cad=array();$seq=1;
  foreach($points as&$p){$p['sequence_no']=$seq++;if($p['recorded_at']&&null===$start)$start=$p['recorded_at'];if($p['recorded_at'])$end=$p['recorded_at'];
   if(null!==$p['latitude']&&null!==$p['longitude']&&$prev)$distance+=$this->distance_m($prev[0],$prev[1],$p['latitude'],$p['longitude']);
   if(null!==$p['distance_m']&&$p['distance_m']>$distance)$distance=(float)$p['distance_m'];else$p['distance_m']=$distance;
   if(null!==$p['latitude']&&null!==$p['longitude'])$prev=array($p['latitude'],$p['longitude']);if(null!==$p['heart_rate'])$hr[]=$p['heart_rate'];if(null!==$p['cadence'])$cad[]=$p['cadence'];
  }unset($p);
  if(!$start||!$end||$distance<=0)return new \WP_Error('swimlog_xml_track',__('The GPX/TCX track is missing usable time or distance data.','chriss-swim-training-progress-evaluation'));
  $elapsed=max(0,(strtotime($end)-strtotime($start))*1000);
  return array('source'=>$source,'parser_version'=>self::VERSION,'workout'=>array('workout_type'=>'open_water','workout_start'=>$start,'workout_end'=>$end,'total_distance_m'=>$distance,'original_distance'=>$distance,'original_distance_unit'=>'m','elapsed_time_ms'=>$elapsed,'moving_time_ms'=>null,'average_heart_rate'=>$hr?array_sum($hr)/count($hr):null,'max_heart_rate'=>$hr?max($hr):null,'average_cadence'=>$cad?array_sum($cad)/count($cad):null,'max_cadence'=>$cad?max($cad):null,'pool_length_m'=>null,'original_pool_length'=>null,'pool_length_unit'=>null,'device_manufacturer'=>null,'device_model'=>null),'laps'=>array(),'lengths'=>array(),'trackpoints'=>$points,'metadata'=>array('xml_source'=>$source));
 }
 private function gpx_points($xml){
  $nodes=$xml->xpath('//*[local-name()="trkpt"]');$out=array();foreach((array)$nodes as$n){$a=$n->attributes();$lat=isset($a['lat'])?(float)$a['lat']:null;$lon=isset($a['lon'])?(float)$a['lon']:null;$t=$n->xpath('./*[local-name()="time"]');$hr=$n->xpath('.//*[local-name()="hr"]');$cad=$n->xpath('.//*[local-name()="cad"]');
   if(null===$lat||null===$lon||empty($t))continue;$out[]=array('recorded_at'=>$this->local_time((string)$t[0]),'latitude'=>$lat,'longitude'=>$lon,'distance_m'=>null,'heart_rate'=>!empty($hr)&&is_numeric((string)$hr[0])?(int)$hr[0]:null,'cadence'=>!empty($cad)&&is_numeric((string)$cad[0])?(float)$cad[0]:null);}
  return $out;
 }
 private function tcx_points($xml){
  $nodes=$xml->xpath('//*[local-name()="Trackpoint"]');$out=array();foreach((array)$nodes as$n){$t=$n->xpath('./*[local-name()="Time"]');$lat=$n->xpath('.//*[local-name()="LatitudeDegrees"]');$lon=$n->xpath('.//*[local-name()="LongitudeDegrees"]');$dist=$n->xpath('./*[local-name()="DistanceMeters"]');$hr=$n->xpath('.//*[local-name()="HeartRateBpm"]/*[local-name()="Value"]');$cad=$n->xpath('./*[local-name()="Cadence"]');
   if(empty($t)||empty($lat)||empty($lon))continue;$out[]=array('recorded_at'=>$this->local_time((string)$t[0]),'latitude'=>(float)$lat[0],'longitude'=>(float)$lon[0],'distance_m'=>!empty($dist)&&is_numeric((string)$dist[0])?(float)$dist[0]:null,'heart_rate'=>!empty($hr)&&is_numeric((string)$hr[0])?(int)$hr[0]:null,'cadence'=>!empty($cad)&&is_numeric((string)$cad[0])?(float)$cad[0]:null);}
  return $out;
 }
 private function local_time($value){try{$d=new \DateTimeImmutable(trim((string)$value));return$d->setTimezone(wp_timezone())->format('Y-m-d H:i:s');}catch(\Exception $e){return null;}}
 private function distance_m($lat1,$lon1,$lat2,$lon2){$r=6371000.0;$p1=deg2rad($lat1);$p2=deg2rad($lat2);$dp=deg2rad($lat2-$lat1);$dl=deg2rad($lon2-$lon1);$a=sin($dp/2)*sin($dp/2)+cos($p1)*cos($p2)*sin($dl/2)*sin($dl/2);return$r*2*atan2(sqrt($a),sqrt(max(0,1-$a)));}
}
