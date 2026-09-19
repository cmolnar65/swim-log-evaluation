<?php
namespace SwimLogEvaluation;
if(!defined('ABSPATH')){exit;}

/** FORM-oriented CSV parser with conservative normalization. */
final class CSV_Importer {
	const VERSION='0.1';
	public function parse($path){
		$h=fopen($path,'r');if(!$h)return new \WP_Error('swimlog_csv_read',__('CSV file could not be read.','swim-log-evaluation'));
		$rows=array();while(($r=fgetcsv($h))!==false){$rows[]=$r;if(count($rows)>20000){fclose($h);return new \WP_Error('swimlog_csv_large',__('CSV contains too many rows.','swim-log-evaluation'));}}fclose($h);
		$summary=null;$detail_header=null;$detail_start=null;
		for($i=0;$i<count($rows);$i++){
			$norm=array_map(array($this,'key'),$rows[$i]);
			if(in_array('swimdate',$norm,true)&&in_array('start',$norm,true)&&in_array('end',$norm,true)&&$i+1<count($rows)&&!$summary){$summary=array_combine($norm,array_pad($rows[$i+1],count($norm),''));}
			if(in_array('swimdate',$norm,true)&&in_array('swimtime',$norm,true)&&(in_array('lengthm',$norm,true)||in_array('distm',$norm,true))){$detail_header=$norm;$detail_start=$i+1;break;}
		}
		if(!$summary&&!$detail_header)return new \WP_Error('swimlog_csv_format',__('CSV format is not recognized as a supported swim export.','swim-log-evaluation'));
		$pool=$summary?$this->number($summary['poolsize']??''):null; $unit='m';
		if($summary&&preg_match('/yd|yard/i',$summary['poolsize']??''))$unit='yd';
		$pool_m=$pool!==null?($unit==='yd'?$pool*0.9144:$pool):null;
		$start=$summary?$this->datetime($summary['swimdate']??'',$summary['start']??''):null;
		$end=$summary?$this->datetime($summary['swimdate']??'',$summary['end']??''):null;
		$lengths=array();$seq=1;$offset=0;$total_native=0;
		if($detail_header){
			for($i=$detail_start;$i<count($rows);$i++){
				if(!array_filter($rows[$i],function($v){return trim((string)$v)!=='';}))continue;
				$r=array_combine($detail_header,array_pad($rows[$i],count($detail_header),''));
				$type=(isset($r['strk'])&&strtoupper(trim($r['strk']))==='REST')?'rest':'active';
				$move=$this->seconds($r['movetime']??'');$rest=$this->seconds($r['resttime']??'');$ms=(int)round((($type==='rest'?$rest:$move)??0)*1000);
				$native=$type==='active'?($this->number($r['lengthm']??'')??$pool??0):0;$total_native+=$native;
				$stroke=$type==='active'?$this->stroke($r['strk']??'') : null;
				$raw=$r; $lengths[]=array('sequence_no'=>$seq++,'start_time'=>$this->datetime($r['swimdate']??'', $r['swimtime']??''),'start_offset_ms'=>$offset,'distance_m'=>$unit==='yd'?$native*0.9144:$native,'elapsed_time_ms'=>$ms?:null,'stroke'=>$stroke,'length_type'=>$type,'source_length_index'=>isset($r['len'])?absint($r['len']):null,'raw_metadata'=>wp_json_encode($raw));$offset+=$ms;
			}
		}
		$distance=$summary?$this->number($summary['distm']??($summary['distance']??'')):null;if(null===$distance&&$total_native)$distance=$total_native;
		$elapsed=$start&&$end?max(0,(strtotime($end)-strtotime($start))*1000):($offset?:null);
		return array('source'=>'csv','parser_version'=>self::VERSION,'workout'=>array('workout_start'=>$start,'workout_end'=>$end,'total_distance_m'=>$distance!==null?($unit==='yd'?$distance*0.9144:$distance):null,'original_distance'=>$distance,'original_distance_unit'=>$unit,'elapsed_time_ms'=>$elapsed,'pool_length_m'=>$pool_m,'original_pool_length'=>$pool,'pool_length_unit'=>$unit,'device_manufacturer'=>'FORM','device_model'=>$summary['device']??null,'notes'=>$summary['swimtitle']??null),'laps'=>array(),'lengths'=>$lengths,'metadata'=>$summary?:array());
	}
	private function key($v){return strtolower(preg_replace('/[^a-z0-9]+/i','',trim((string)$v)));}
	private function number($v){if(preg_match('/-?\d+(?:\.\d+)?/',str_replace(',','',(string)$v),$m))return(float)$m[0];return null;}
	private function seconds($v){$v=trim((string)$v);if($v==='')return null;if(is_numeric($v))return(float)$v;$p=array_map('floatval',explode(':',$v));if(count($p)===3)return$p[0]*3600+$p[1]*60+$p[2];if(count($p)===2)return$p[0]*60+$p[1];return null;}
	private function datetime($date,$time){$s=trim($date.' '.$time);if(trim($s)==='')return null;$ts=strtotime($s);return $ts?date('Y-m-d H:i:s',$ts):null;}
	private function stroke($v){$v=strtoupper(trim((string)$v));if(in_array($v,array('FR','FREE','FREESTYLE'),true))return'FR';if(in_array($v,array('BR','BREAST','BREASTSTROKE'),true))return'BR';if(in_array($v,array('BACK','BK','BACKSTROKE'),true))return'BACK';if(in_array($v,array('FLY','BUTTERFLY'),true))return'FLY';if(in_array($v,array('MIXED','IM','MEDLEY'),true))return'MIXED';return'UNKNOWN';}
}
