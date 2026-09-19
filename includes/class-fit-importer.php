<?php
namespace SwimLogEvaluation;
if(!defined('ABSPATH')){exit;}

/** Minimal defensive FIT decoder for pool-swim Session/Lap/Length messages. */
final class FIT_Importer {
	const VERSION='0.1';
	private $defs=array();
	private $last_timestamp=null;

	public function parse($path){
		$bin=file_get_contents($path);
		if(false===$bin||strlen($bin)<14)return new \WP_Error('swimlog_fit_read',__('Invalid or unreadable FIT file.','swim-log-evaluation'));
		$hs=ord($bin[0]); if($hs<12||strlen($bin)<$hs)return new \WP_Error('swimlog_fit_header',__('Invalid FIT header.','swim-log-evaluation'));
		if(substr($bin,8,4)!=='.FIT')return new \WP_Error('swimlog_fit_signature',__('The file does not contain a FIT signature.','swim-log-evaluation'));
		if($hs>=14){$stored=$this->u16(substr($bin,12,2),0);$calc=$this->crc16(substr($bin,0,12));if($stored!==$calc)return new \WP_Error('swimlog_fit_header_crc',__('FIT header checksum is invalid.','swim-log-evaluation'));}
		$data_size=unpack('V',substr($bin,4,4))[1]; $end=$hs+$data_size;
		if($end>strlen($bin))return new \WP_Error('swimlog_fit_truncated',__('The FIT file is truncated.','swim-log-evaluation'));
		if(strlen($bin)>=$end+2){$stored=$this->u16(substr($bin,$end,2),0);$calc=$this->crc16(substr($bin,0,$end));if($stored!==$calc)return new \WP_Error('swimlog_fit_crc',__('FIT file checksum is invalid.','swim-log-evaluation'));}
		$this->defs=array();$this->last_timestamp=null;
		$out=array('session'=>null,'laps'=>array(),'lengths'=>array(),'device'=>array());
		$p=$hs;
		while($p<$end){
			$hdr=ord($bin[$p++]);
			if($hdr&0x80){ // compressed timestamp header; data message using local type bits 5-6.
				$local=($hdr>>5)&0x03;$offset=$hdr&0x1f;$msg=$this->read_data($bin,$p,$end,$local);if(is_wp_error($msg))return $msg;$msg=$this->apply_compressed_timestamp($msg,$offset);$this->collect($out,$msg);continue;
			}
			$local=$hdr&0x0f;
			if($hdr&0x40){
				if($p+5>$end)return new \WP_Error('swimlog_fit_definition',__('Invalid FIT definition message.','swim-log-evaluation'));
				$p++; $arch=ord($bin[$p++]); $global=$this->u16(substr($bin,$p,2),$arch); $p+=2; $n=ord($bin[$p++]); $fields=array();
				for($i=0;$i<$n;$i++){if($p+3>$end)return new \WP_Error('swimlog_fit_definition',__('Invalid FIT field definition.','swim-log-evaluation'));$fields[]=array(ord($bin[$p]),ord($bin[$p+1]),ord($bin[$p+2]));$p+=3;}
				$developer_sizes=array();if($hdr&0x20){if($p>=$end)return new \WP_Error('swimlog_fit_definition',__('Invalid FIT developer definition.','swim-log-evaluation'));$dn=ord($bin[$p++]);for($di=0;$di<$dn;$di++){if($p+3>$end)return new \WP_Error('swimlog_fit_definition',__('Invalid FIT developer fields.','swim-log-evaluation'));$p++;$developer_sizes[]=ord($bin[$p++]);$p++;}}
				$this->defs[$local]=array('arch'=>$arch,'global'=>$global,'fields'=>$fields,'developer_sizes'=>$developer_sizes); continue;
			}
			$msg=$this->read_data($bin,$p,$end,$local); if(is_wp_error($msg))return $msg; $this->collect($out,$msg);
		}
		if(!$out['session']||empty($out['lengths']))return new \WP_Error('swimlog_fit_swim',__('FIT file does not contain a usable pool-swim session and lengths.','swim-log-evaluation'));
		return $this->normalize($out);
	}
	private function read_data($bin,&$p,$end,$local){
		if(!isset($this->defs[$local]))return new \WP_Error('swimlog_fit_local',__('FIT data references an undefined message type.','swim-log-evaluation'));
		$d=$this->defs[$local];$vals=array();
		foreach($d['fields'] as $f){list($num,$size,$base)=$f;if($p+$size>$end)return new \WP_Error('swimlog_fit_data',__('FIT data message is truncated.','swim-log-evaluation'));$raw=substr($bin,$p,$size);$p+=$size;$vals[$num]=$this->value($raw,$base,$d['arch']);}
		foreach(($d['developer_sizes']??array()) as $size){if($p+$size>$end)return new \WP_Error('swimlog_fit_data',__('FIT developer data is truncated.','swim-log-evaluation'));$p+=$size;}
		if(isset($vals[253])&&is_numeric($vals[253]))$this->last_timestamp=(int)$vals[253];
		return array('global'=>$d['global'],'fields'=>$vals);
	}
	private function value($raw,$base,$arch){
		$t=$base&0x1f;$n=strlen($raw);
		if(in_array($t,array(0,2,10,13),true))return ord($raw[0]);
		if(in_array($t,array(1),true))return unpack('c',$raw)[1];
		if(in_array($t,array(3,4,11),true)&&$n>=2)return $this->u16($raw,$arch);
		if(in_array($t,array(5,6,12),true)&&$n>=4)return $this->u32($raw,$arch);
		if($t===7)return rtrim($raw,"\0");
		return bin2hex($raw);
	}
	private function apply_compressed_timestamp($msg,$offset){if($this->last_timestamp===null)return $msg;$base=$this->last_timestamp;$candidate=($base&~0x1f)|($offset&0x1f);if($candidate<$base)$candidate+=0x20;$msg['fields'][253]=$candidate;$this->last_timestamp=$candidate;return $msg;}
	private function crc16($data){$crc=0;$n=strlen($data);for($i=0;$i<$n;$i++){$byte=ord($data[$i]);for($b=0;$b<8;$b++){$bit=$crc&1;$crc>>=1;if(($byte>>$b)&1)$bit^=1;if($bit)$crc^=0xA001;}}return $crc&0xffff;}
	private function u16($s,$a){return unpack($a?'n':'v',substr($s,0,2))[1];}
	private function u32($s,$a){return unpack($a?'N':'V',substr($s,0,4))[1];}
	private function collect(&$out,$m){if($m['global']===18)$out['session']=$m['fields'];elseif($m['global']===19)$out['laps'][]=$m['fields'];elseif($m['global']===101)$out['lengths'][]=$m['fields'];elseif($m['global']===23)$out['device'][]=$m['fields'];}
	private function fit_time($v){$gmt=gmdate('Y-m-d H:i:s',(int)$v+631065600);return get_date_from_gmt($gmt,'Y-m-d H:i:s');}
	private function stroke($v){return array(0=>'FR',1=>'BACK',2=>'BR',3=>'FLY',4=>'MIXED')[intval($v)]??'UNKNOWN';}
	private function device_info($devices){$manufacturer=null;$model=null;foreach($devices as $d){if($manufacturer===null&&isset($d[2]))$manufacturer=$this->manufacturer_name((int)$d[2]);if($model===null&&isset($d[4]))$model=(string)$d[4];if($model===null&&isset($d[27])&&$d[27]!=='')$model=(string)$d[27];}return array($manufacturer,$model);}
	private function manufacturer_name($id){$known=array(1=>'Garmin',15=>'Dynastream',255=>'Development');return $known[$id]??('FIT manufacturer '.$id);}
	private function normalize($o){
		$s=$o['session'];list($device_manufacturer,$device_model)=$this->device_info($o['device']); $pool_m=isset($s[44])?$s[44]/100:0; $unit=(isset($s[46])&&(int)$s[46]===1)?'yd':'m';
		// FIT distance fields are stored in meters even when display/course units are yards.
		// Preserve metric storage for normalization and derive the native yard values only for original_* fields.
		$pool=$unit==='yd'?$pool_m/0.9144:$pool_m;
		$start=isset($s[2])?$this->fit_time($s[2]):null; $elapsed=isset($s[7])?(int)$s[7]:null; // FIT total_elapsed_time has scale 1000 seconds; the decoded raw value is already milliseconds.
		$distance_m=isset($s[9])?$s[9]/100:null; $distance=$distance_m!==null&&$unit==='yd'?$distance_m/0.9144:$distance_m;
		$lengths=array();$seq=1;$offset=0;
		foreach($o['lengths'] as $x){$type=(isset($x[12])&&(int)$x[12]===0)?'rest':'active';$ms=isset($x[3])?(int)$x[3]:null;$lengths[]=array('sequence_no'=>$seq++,'start_time'=>isset($x[2])?$this->fit_time($x[2]):null,'start_offset_ms'=>$offset,'distance_m'=>$type==='active'?$pool_m:0,'elapsed_time_ms'=>$ms,'stroke'=>$type==='active'?$this->stroke($x[7]??255):null,'length_type'=>$type,'source_length_index'=>$seq-2,'raw_metadata'=>wp_json_encode($x));if(null!==$ms)$offset+=$ms;}
		$laps=array();$i=1;foreach($o['laps'] as $x){$laps[]=array('sequence_no'=>$i++,'start_time'=>isset($x[2])?$this->fit_time($x[2]):null,'distance_m'=>isset($x[9])?$x[9]/100:null,'elapsed_time_ms'=>isset($x[7])?(int)$x[7]:null,'stroke'=>isset($x[24])?$this->stroke($x[24]):null,'source_lap_index'=>$i-2,'raw_metadata'=>wp_json_encode($x));}
		return array('source'=>'fit','parser_version'=>self::VERSION,'workout'=>array('workout_start'=>$start,'workout_end'=>$start&&$elapsed?gmdate('Y-m-d H:i:s',strtotime($start)+$elapsed/1000):null,'total_distance_m'=>$distance_m,'original_distance'=>$distance,'original_distance_unit'=>$unit,'elapsed_time_ms'=>$elapsed,'pool_length_m'=>$pool_m,'original_pool_length'=>$pool,'pool_length_unit'=>$unit,'device_manufacturer'=>$device_manufacturer,'device_model'=>$device_model),'laps'=>$laps,'lengths'=>$lengths,'metadata'=>array());
	}
}
