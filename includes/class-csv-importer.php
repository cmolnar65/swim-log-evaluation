<?php
namespace SwimLogEvaluation;
if(!defined('ABSPATH')){exit;}

/** FORM-oriented CSV parser with conservative normalization. */
final class CSV_Importer {
	const VERSION='0.1';
	public function parse($path){
		global $wp_filesystem;if(!function_exists('WP_Filesystem'))require_once ABSPATH.'wp-admin/includes/file.php';
		if(!$wp_filesystem&&!WP_Filesystem())return new \WP_Error('swimlog_csv_read',__('WordPress could not initialize filesystem access for the CSV import.','swim-log-and-evaluation'));
		$contents=$wp_filesystem->get_contents($path);if(false===$contents)return new \WP_Error('swimlog_csv_read',__('CSV file could not be read.','swim-log-and-evaluation'));
		$records=str_getcsv($contents,"\n");$rows=array();foreach($records as $record){$record=rtrim($record,"\r");if(''===$record)continue;$rows[]=str_getcsv($record);if(count($rows)>20000)return new \WP_Error('swimlog_csv_large',__('CSV contains too many rows.','swim-log-and-evaluation'));}
		$summary=null;$detail_header=null;$detail_start=null;
		for($i=0;$i<count($rows);$i++){
			$norm=array_map(array($this,'key'),$rows[$i]);
			if(in_array('swimdate',$norm,true)&&$this->has_any($norm,array('start','swimstarttime'))&&$this->has_any($norm,array('end','swimendtime'))&&$i+1<count($rows)&&!$summary){$summary=array_combine($norm,array_pad($rows[$i+1],count($norm),''));}
			if(in_array('swimdate',$norm,true)&&in_array('swimtime',$norm,true)&&$this->has_any($norm,array('lengthm','lengthyd','lengthyards','distm','distyd','distyards'))){$detail_header=$this->unique_keys($norm);$detail_start=$i+1;break;}
		}
		if(!$summary&&!$detail_header)return new \WP_Error('swimlog_csv_format',__('CSV format is not recognized as a supported swim export.','swim-log-and-evaluation'));
		$pool=$summary?$this->number($summary['poolsize']??''):null;$unit=$this->course_unit($summary,$detail_header);
		$pool_m=$pool!==null?($unit==='yd'?$pool*0.9144:$pool):null;
		$start=$summary?$this->datetime($summary['swimdate']??'', $summary['swimstarttime']??($summary['start']??'')):null;
		$end=$summary?$this->datetime($summary['swimdate']??'', $summary['swimendtime']??($summary['end']??'')):null;
		$lengths=array();$seq=1;$offset=0;$total_native=0;
		if($detail_header){
			for($i=$detail_start;$i<count($rows);$i++){
				if(!array_filter($rows[$i],function($v){return trim((string)$v)!=='';}))continue;
				$r=array_combine($detail_header,array_pad($rows[$i],count($detail_header),''));
				$type=(isset($r['strk'])&&strtoupper(trim($r['strk']))==='REST')?'rest':'active';
				$move=$this->seconds($r['movetime']??'');$rest=$this->seconds($r['resttime']??'');$ms=(int)round((($type==='rest'?$rest:$move)??0)*1000);
				$native=$type==='active'?($this->number($r[$unit==='yd'?'lengthyd':'lengthm']??($r[$unit==='yd'?'lengthyards':'lengthm']??''))??$pool??0):0;$total_native+=$native;
				$stroke=$type==='active'?$this->stroke($r['strk']??'') : null;
				$raw=$r; $lengths[]=array('sequence_no'=>$seq++,'start_time'=>$this->datetime($r['swimdate']??'', $r['swimtime']??''),'start_offset_ms'=>$offset,'distance_m'=>$unit==='yd'?$native*0.9144:$native,'elapsed_time_ms'=>$ms?:null,'stroke'=>$stroke,'length_type'=>$type,'source_length_index'=>isset($r['len'])?absint($r['len']):null,'raw_metadata'=>wp_json_encode($raw));$offset+=$ms;
			}
		}
		$distance=$summary?$this->number($summary[$unit==='yd'?'distyd':'distm']??($summary[$unit==='yd'?'distanceyd':'distance']??($summary['distance']??''))):null;if(null===$distance&&$total_native)$distance=$total_native;
		// FORM detail timing preserves hundredths of a second; prefer it over whole-second summary start/end when available.
		$elapsed=$offset?:($start&&$end?max(0,(strtotime($end)-strtotime($start))*1000):null);
		return array('source'=>'csv','parser_version'=>self::VERSION,'workout'=>array('workout_start'=>$start,'workout_end'=>$end,'total_distance_m'=>$distance!==null?($unit==='yd'?$distance*0.9144:$distance):null,'original_distance'=>$distance,'original_distance_unit'=>$unit,'elapsed_time_ms'=>$elapsed,'pool_length_m'=>$pool_m,'original_pool_length'=>$pool,'pool_length_unit'=>$unit,'device_manufacturer'=>'FORM','device_model'=>$summary['device']??null,'notes'=>$summary['swimtitle']??null),'laps'=>array(),'lengths'=>$lengths,'metadata'=>$summary?array_merge($summary,array('_form_location'=>trim((string)($summary['location']??'')),'_firmware'=>trim((string)($summary['firmware']??'')))):array());
	}
	private function course_unit($summary,$detail_header){$pool=(string)($summary['poolsize']??'');if(preg_match('/yd|yard/i',$pool))return'yd';if(preg_match('/\bm\b|meter/i',$pool))return'm';foreach((array)$detail_header as $k){if(in_array($k,array('lengthyd','lengthyards','distyd','distyards'),true))return'yd';}return'm';}
	private function has_any($haystack,$needles){foreach($needles as$n){if(in_array($n,$haystack,true))return true;}return false;}
	private function unique_keys($keys){$seen=array();$out=array();foreach($keys as$key){$base=$key!==''?$key:'column';$seen[$base]=($seen[$base]??0)+1;$out[]=$seen[$base]===1?$base:$base.$seen[$base];}return$out;}
	private function key($v){return strtolower(preg_replace('/[^a-z0-9]+/i','',trim((string)$v)));}
	private function number($v){if(preg_match('/-?\d+(?:\.\d+)?/',str_replace(',','',(string)$v),$m))return(float)$m[0];return null;}
	private function seconds($v){$v=trim((string)$v);if($v==='')return null;if(is_numeric($v))return(float)$v;$p=array_map('floatval',explode(':',$v));if(count($p)===3)return$p[0]*3600+$p[1]*60+$p[2];if(count($p)===2)return$p[0]*60+$p[1];return null;}
	private function datetime($date,$time){$s=trim($date.' '.$time);if(trim($s)==='')return null;if(function_exists('wp_timezone')){$dt=date_create_immutable($s,wp_timezone());return $dt?$dt->format('Y-m-d H:i:s'):null;}$dt=date_create_immutable($s,new \DateTimeZone('UTC'));return $dt?$dt->format('Y-m-d H:i:s'):null;}
	private function stroke($v){$v=strtoupper(trim((string)$v));if(in_array($v,array('FR','FREE','FREESTYLE'),true))return'FR';if(in_array($v,array('BR','BREAST','BREASTSTROKE'),true))return'BR';if(in_array($v,array('BACK','BK','BACKSTROKE'),true))return'BACK';if(in_array($v,array('FLY','BUTTERFLY'),true))return'FLY';if(in_array($v,array('MIXED','IM','MEDLEY'),true))return'MIXED';return'UNKNOWN';}
}
