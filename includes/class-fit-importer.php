<?php
namespace SwimLogEvaluation;
if(!defined('ABSPATH')){exit;}

/** Minimal defensive FIT decoder for swimming Session/Lap/Length messages. */
final class FIT_Importer {
	const VERSION='0.3';
	private $defs=array();
	private $last_timestamp=null;

	public function parse($path){
		$bin=file_get_contents($path);
		if(false===$bin||strlen($bin)<14)return new \WP_Error('swimlog_fit_read',__('Invalid or unreadable FIT file.','chriss-swim-training-progress-evaluation'));
		$hs=ord($bin[0]); if($hs<12||strlen($bin)<$hs)return new \WP_Error('swimlog_fit_header',__('Invalid FIT header.','chriss-swim-training-progress-evaluation'));
		if(substr($bin,8,4)!=='.FIT')return new \WP_Error('swimlog_fit_signature',__('The file does not contain a FIT signature.','chriss-swim-training-progress-evaluation'));
		if($hs>=14){$stored=$this->u16(substr($bin,12,2),0);$calc=$this->crc16(substr($bin,0,12));if($stored!==$calc)return new \WP_Error('swimlog_fit_header_crc',__('FIT header checksum is invalid.','chriss-swim-training-progress-evaluation'));}
		$data_size=unpack('V',substr($bin,4,4))[1]; $end=$hs+$data_size;
		if($end>strlen($bin))return new \WP_Error('swimlog_fit_truncated',__('The FIT file is truncated.','chriss-swim-training-progress-evaluation'));
		if(strlen($bin)>=$end+2){$stored=$this->u16(substr($bin,$end,2),0);$calc=$this->crc16(substr($bin,0,$end));if($stored!==$calc)return new \WP_Error('swimlog_fit_crc',__('FIT file checksum is invalid.','chriss-swim-training-progress-evaluation'));}
		$this->defs=array();$this->last_timestamp=null;
		$out=array('session'=>null,'laps'=>array(),'lengths'=>array(),'records'=>array(),'device'=>array(),'activity'=>array(),'session_count'=>0);
		$p=$hs;
		while($p<$end){
			$hdr=ord($bin[$p++]);
			if($hdr&0x80){ // compressed timestamp header; data message using local type bits 5-6.
				$local=($hdr>>5)&0x03;$offset=$hdr&0x1f;$msg=$this->read_data($bin,$p,$end,$local);if(is_wp_error($msg))return $msg;$msg=$this->apply_compressed_timestamp($msg,$offset);$this->collect($out,$msg);continue;
			}
			$local=$hdr&0x0f;
			if($hdr&0x40){
				if($p+5>$end)return new \WP_Error('swimlog_fit_definition',__('Invalid FIT definition message.','chriss-swim-training-progress-evaluation'));
				$p++; $arch=ord($bin[$p++]); $global=$this->u16(substr($bin,$p,2),$arch); $p+=2; $n=ord($bin[$p++]); $fields=array();
				for($i=0;$i<$n;$i++){if($p+3>$end)return new \WP_Error('swimlog_fit_definition',__('Invalid FIT field definition.','chriss-swim-training-progress-evaluation'));$fields[]=array(ord($bin[$p]),ord($bin[$p+1]),ord($bin[$p+2]));$p+=3;}
				$developer_sizes=array();if($hdr&0x20){if($p>=$end)return new \WP_Error('swimlog_fit_definition',__('Invalid FIT developer definition.','chriss-swim-training-progress-evaluation'));$dn=ord($bin[$p++]);for($di=0;$di<$dn;$di++){if($p+3>$end)return new \WP_Error('swimlog_fit_definition',__('Invalid FIT developer fields.','chriss-swim-training-progress-evaluation'));$p++;$developer_sizes[]=ord($bin[$p++]);$p++;}}
				$this->defs[$local]=array('arch'=>$arch,'global'=>$global,'fields'=>$fields,'developer_sizes'=>$developer_sizes); continue;
			}
			$msg=$this->read_data($bin,$p,$end,$local); if(is_wp_error($msg))return $msg; $this->collect($out,$msg);
		}
		if(!$out['session'])return new \WP_Error('swimlog_fit_swim',__('FIT file does not contain a usable swimming session.','chriss-swim-training-progress-evaluation'));
		if ( $out['session_count'] > 1 ) return new \WP_Error( 'swimlog_fit_multisession', __( 'Multisession FIT files require explicit review and cannot be imported as one workout.', 'chriss-swim-training-progress-evaluation' ) );
		$workout_type=$this->workout_type($out['session'],$out['lengths']);
		if(in_array($workout_type,array('running','walking'),true))return $this->normalize_land($out,$workout_type);
		if('open_water'===$workout_type)return $this->normalize_open_water($out);
		if('pool'!==$workout_type)return new \WP_Error('swimlog_fit_swim_type',__('FIT swimming session could not be classified safely as pool or open-water swimming.','chriss-swim-training-progress-evaluation'));
		if(empty($out['lengths']))return new \WP_Error('swimlog_fit_swim',__('FIT file does not contain usable pool-swim lengths.','chriss-swim-training-progress-evaluation'));
		return $this->normalize($out);
	}
	private function read_data($bin,&$p,$end,$local){
		if(!isset($this->defs[$local]))return new \WP_Error('swimlog_fit_local',__('FIT data references an undefined message type.','chriss-swim-training-progress-evaluation'));
		$d=$this->defs[$local];$vals=array();
		foreach($d['fields'] as $f){list($num,$size,$base)=$f;if($p+$size>$end)return new \WP_Error('swimlog_fit_data',__('FIT data message is truncated.','chriss-swim-training-progress-evaluation'));$raw=substr($bin,$p,$size);$p+=$size;$vals[$num]=$this->value($raw,$base,$d['arch']);}
		foreach(($d['developer_sizes']??array()) as $size){if($p+$size>$end)return new \WP_Error('swimlog_fit_data',__('FIT developer data is truncated.','chriss-swim-training-progress-evaluation'));$p+=$size;}
		if(isset($vals[253])&&is_numeric($vals[253]))$this->last_timestamp=(int)$vals[253];
		return array('global'=>$d['global'],'fields'=>$vals);
	}
	private function value($raw,$base,$arch){
		$t=$base&0x1f;$n=strlen($raw);
		if(in_array($t,array(0,2,10,13),true))return ord($raw[0]);
		if(in_array($t,array(1),true))return unpack('c',$raw)[1];
		if(in_array($t,array(3,4,11),true)&&$n>=2)return $this->u16($raw,$arch);
		if($t===5&&$n>=4){$v=$this->u32($raw,$arch);return $v>=2147483648?$v-4294967296:$v;}
		if(in_array($t,array(6,12),true)&&$n>=4)return $this->u32($raw,$arch);
		if($t===7)return rtrim($raw,"\0");
		return bin2hex($raw);
	}
	private function apply_compressed_timestamp($msg,$offset){if($this->last_timestamp===null)return $msg;$base=$this->last_timestamp;$candidate=($base&~0x1f)|($offset&0x1f);if($candidate<$base)$candidate+=0x20;$msg['fields'][253]=$candidate;$this->last_timestamp=$candidate;return $msg;}
	private function crc16($data){$crc=0;$n=strlen($data);for($i=0;$i<$n;$i++){$byte=ord($data[$i]);for($b=0;$b<8;$b++){$bit=$crc&1;$crc>>=1;if(($byte>>$b)&1)$bit^=1;if($bit)$crc^=0xA001;}}return $crc&0xffff;}
	private function u16($s,$a){return unpack($a?'n':'v',substr($s,0,2))[1];}
	private function u32($s,$a){return unpack($a?'N':'V',substr($s,0,4))[1];}
	private function collect(&$out,$m){if($m['global']===18){$out['session']=$m['fields'];$out['session_count']=($out['session_count']??0)+1;}elseif($m['global']===19)$out['laps'][]=$m['fields'];elseif($m['global']===20)$out['records'][]=$m['fields'];elseif($m['global']===101)$out['lengths'][]=$m['fields'];elseif($m['global']===23)$out['device'][]=$m['fields'];elseif($m['global']===34)$out['activity'][]=$m['fields'];}
	private function workout_type($session,$lengths){
		// FIT Profile: sport 5 = swimming; sub_sport 17 = lap_swimming; 18 = open_water.
		$sport=isset($session[5])&&is_numeric($session[5])?(int)$session[5]:null;
		$sub_sport=isset($session[6])&&is_numeric($session[6])?(int)$session[6]:null;
		if(1===$sport && in_array($sub_sport,array(null,0,1,2,3,4,45,67),true))return 'running';
		if(11===$sport && in_array($sub_sport,array(null,0,27,30,31),true))return 'walking';
		if(5!==$sport)return null;
		if(17===$sub_sport)return 'pool';
		if(18===$sub_sport)return 'open_water';
		// Some swimming FIT producers use generic/omitted sub_sport. FIT Length
		// messages are structural evidence of lap swimming; their absence is not
		// enough to infer open water, so ambiguous sessions fail closed.
		if((0===$sub_sport||null===$sub_sport)&&!empty($lengths))return 'pool';
		return null;
	}
	private function local_offset_seconds($activities){
		foreach(array_reverse((array)$activities) as $a){
			if(!isset($a[253],$a[5])||!is_numeric($a[253])||!is_numeric($a[5]))continue;
			$offset=(int)$a[5]-(int)$a[253];
			if($offset>=-50400&&$offset<=50400)return$offset;
		}
		return null;
	}
	private function fit_time($v,$offset=null){
		$unix=(int)$v+631065600;
		if(null!==$offset)return gmdate('Y-m-d H:i:s',$unix+(int)$offset);
		$gmt=gmdate('Y-m-d H:i:s',$unix);return get_date_from_gmt($gmt,'Y-m-d H:i:s');
	}
	private function add_milliseconds($local,$milliseconds){
		if(!$local||!is_numeric($milliseconds))return null;
		$base=strtotime($local.' UTC');if(false===$base)return null;
		return gmdate('Y-m-d H:i:s',$base+((int)$milliseconds/1000));
	}
	private function stroke($v){return array(0=>'FR',1=>'BACK',2=>'BR',3=>'FLY',4=>'MIXED')[intval($v)]??'UNKNOWN';}
	private function device_info($devices){
		$best=null;$best_score=-1;
		foreach($devices as $d){
			$manufacturer_id=isset($d[2])&&is_numeric($d[2])?(int)$d[2]:0;
			$model='';$descriptor=isset($d[19])&&is_string($d[19])?trim($d[19]):'';
			if(isset($d[27])&&is_string($d[27])&&trim($d[27])!=='')$model=trim($d[27]);
			elseif(isset($d[4])&&$d[4]!==''&&$d[4]!==null)$model=(string)$d[4];
			if(255===$manufacturer_id&&$descriptor!==''&&preg_match('/\\bapple\\s*watch\\b/i',$descriptor))$model_for_manufacturer=$model.' '.$descriptor;else$model_for_manufacturer=$model;
			$score=0;
			if($manufacturer_id>0)$score+=2;
			if($model!=='')$score+=2;
			// device_index 0 is normally the recording device. Prefer it over
			// accessories such as HR straps when the FIT producer supplies it.
			if(isset($d[0])&&is_numeric($d[0])&&(int)$d[0]===0)$score+=4;
			if($score>$best_score){$best=array($manufacturer_id,$model,$model_for_manufacturer);$best_score=$score;}
		}
		if(!$best)return array(null,null);
		list($manufacturer_id,$model,$model_for_manufacturer)=$best;
		return array($this->manufacturer_name($manufacturer_id,$model_for_manufacturer),$model!==''?$model:null);
	}
	private function manufacturer_name($id,$model=''){
		$known=array(1=>'Garmin',15=>'Dynastream');
		if(isset($known[$id]))return $known[$id];
		// FIT manufacturer 255 is development/developer use. It is not Apple by
		// itself. Only promote it to Apple when the same Device Info record has
		// explicit Apple Watch model text.
		if(255===$id&&preg_match('/\\bapple\\s*watch\\b/i',(string)$model))return 'Apple';
		if(255===$id)return 'Development';
		return $id>0?('FIT manufacturer '.$id):null;
	}
	private function semicircles_to_degrees($value){return ((float)$value)*180.0/2147483648.0;}
    /** FIT SDK profile 21.218: running=1, walking=11; moving time is session 59, not timer field 8. */
    private function normalize_land( $o, $type ) {
        $s = $o['session'];
        if ( ! isset( $s[2] ) || ! is_numeric( $s[2] ) || $s[2] < 0 || $s[2] >= 4294967295 ) return new \WP_Error( 'swimlog_fit_land_start', __( 'Recorded activity start time is missing.', 'chriss-swim-training-progress-evaluation' ) );
        // Reuse the existing lap/record decoder, then remove all swim interpretation.
        $p = $this->normalize_open_water( $o );
        $w = &$p['workout'];
        $w['workout_type'] = $type;
        $sub = $s[6] ?? null;
        $w['activity_environment'] = in_array( $sub, array( 1, 27, 45 ), true ) ? 'indoor' : ( in_array( $sub, array( 2, 3, 4 ), true ) ? 'outdoor' : null );
        $w['moving_time_ms'] = isset( $s[59] ) && is_numeric( $s[59] ) && $s[59] >= 0 && $s[59] < 4294967295 ? (int) $s[59] : null;
        foreach ( array( 7 => 'elapsed_time_ms', 9 => 'total_distance_m' ) as $field => $key ) if ( isset( $s[$field] ) && $s[$field] >= 4294967295 ) $w[$key] = null;
        $w['original_distance'] = $w['total_distance_m'];
        $w['workout_end'] = $this->add_milliseconds( $w['workout_start'], $w['elapsed_time_ms'] );
        $w['average_cadence'] = null; $w['max_cadence'] = null;
        foreach ( array( 16 => 'average_heart_rate', 17 => 'max_heart_rate' ) as $field => $key ) if ( ! isset( $s[$field] ) || $s[$field] >= 255 ) $w[$key] = null;
        foreach ( $p['laps'] as $i => &$lap ) {
            $lap['stroke'] = null;
            $raw = $o['laps'][$i];
            $lap['moving_time_ms'] = isset( $raw[52] ) && $raw[52] < 4294967295 ? (int) $raw[52] : null;
            foreach ( array( 7 => 'elapsed_time_ms', 9 => 'distance_m' ) as $field => $key ) if ( isset( $raw[$field] ) && $raw[$field] >= 4294967295 ) $lap[$key] = null;
        }
        unset( $lap );
        foreach ( $p['trackpoints'] as &$point ) {
            if ( null === $point['latitude'] || null === $point['longitude'] || abs( $point['latitude'] ) > 90 || abs( $point['longitude'] ) > 180 || $point['latitude'] >= 179.9999999 || $point['longitude'] >= 179.9999999 ) { $point['latitude'] = null; $point['longitude'] = null; }
            if ( null !== $point['distance_m'] && $point['distance_m'] >= 42949672.95 ) $point['distance_m'] = null;
            if ( null !== $point['heart_rate'] && $point['heart_rate'] >= 255 ) $point['heart_rate'] = null;
            $point['cadence'] = null; // Sport-specific cadence units deferred, never presented as swim cadence.
        }
        unset( $point, $w );
        return $p;
    }
	private function normalize_open_water($o){
		$s=$o['session'];list($device_manufacturer,$device_model)=$this->device_info($o['device']);$local_offset=$this->local_offset_seconds($o['activity']??array());
		$start=isset($s[2])?$this->fit_time($s[2],$local_offset):null;
		$elapsed=isset($s[7])?(int)$s[7]:null;
		$moving=isset($s[8])?(int)$s[8]:null;
		$distance_m=isset($s[9])?$s[9]/100:null;
		$avg_hr=isset($s[16])&&is_numeric($s[16])?(int)$s[16]:null;
		$max_hr=isset($s[17])&&is_numeric($s[17])?(int)$s[17]:null;
		$avg_cadence=isset($s[18])&&is_numeric($s[18])?(float)$s[18]:null;
		$max_cadence=isset($s[19])&&is_numeric($s[19])?(float)$s[19]:null;
		$laps=array();$i=1;
		foreach($o['laps'] as $x){$lap_start=isset($x[2])?$this->fit_time($x[2],$local_offset):null;$lap_offset=null;if(isset($s[2],$x[2])&&is_numeric($s[2])&&is_numeric($x[2])){$lap_offset=max(0,((int)$x[2]-(int)$s[2])*1000);}$laps[]=array('sequence_no'=>$i++,'start_time'=>$lap_start,'start_offset_ms'=>$lap_offset,'distance_m'=>isset($x[9])?$x[9]/100:null,'elapsed_time_ms'=>isset($x[7])?(int)$x[7]:null,'moving_time_ms'=>isset($x[8])?(int)$x[8]:null,'stroke'=>isset($x[24])?$this->stroke($x[24]):null,'source_lap_index'=>$i-2,'raw_metadata'=>wp_json_encode($x));}
		$trackpoints=array();$track_seq=1;
		foreach($o['records'] as $x){
			$point=array('sequence_no'=>$track_seq++,'recorded_at'=>isset($x[253])?$this->fit_time($x[253],$local_offset):null,'latitude'=>null,'longitude'=>null,'distance_m'=>isset($x[5])&&is_numeric($x[5])?$x[5]/100:null,'heart_rate'=>isset($x[3])&&is_numeric($x[3])?(int)$x[3]:null,'cadence'=>isset($x[4])&&is_numeric($x[4])?(float)$x[4]:null);
			if(isset($x[0])&&is_numeric($x[0])&&isset($x[1])&&is_numeric($x[1])){$point['latitude']=$this->semicircles_to_degrees($x[0]);$point['longitude']=$this->semicircles_to_degrees($x[1]);}
			if(null!==$point['recorded_at']||null!==$point['latitude']||null!==$point['heart_rate']||null!==$point['distance_m'])$trackpoints[]=$point;
		}
		return array('source'=>'fit','parser_version'=>self::VERSION,'workout'=>array('workout_type'=>'open_water','workout_start'=>$start,'workout_end'=>$this->add_milliseconds($start,$elapsed),'total_distance_m'=>$distance_m,'original_distance'=>$distance_m,'original_distance_unit'=>'m','elapsed_time_ms'=>$elapsed,'moving_time_ms'=>$moving,'average_heart_rate'=>$avg_hr,'max_heart_rate'=>$max_hr,'average_cadence'=>$avg_cadence,'max_cadence'=>$max_cadence,'pool_length_m'=>null,'original_pool_length'=>null,'pool_length_unit'=>null,'device_manufacturer'=>$device_manufacturer,'device_model'=>$device_model),'laps'=>$laps,'lengths'=>array(),'trackpoints'=>$trackpoints,'metadata'=>array('fit_sport'=>isset($s[5])?(int)$s[5]:null,'fit_sub_sport'=>isset($s[6])?(int)$s[6]:null,'fit_local_offset_seconds'=>$local_offset));
	}
	private function normalize($o){
		$s=$o['session'];list($device_manufacturer,$device_model)=$this->device_info($o['device']);$local_offset=$this->local_offset_seconds($o['activity']??array()); $pool_m=isset($s[44])?$s[44]/100:0; $unit=(isset($s[46])&&(int)$s[46]===1)?'yd':'m';
		// FIT distance fields are stored in meters even when display/course units are yards.
		// Preserve metric storage for normalization and derive the native yard values only for original_* fields.
		$pool=$unit==='yd'?$pool_m/0.9144:$pool_m;
		$start=isset($s[2])?$this->fit_time($s[2],$local_offset):null; $elapsed=isset($s[7])?(int)$s[7]:null; // FIT total_elapsed_time has scale 1000 seconds; the decoded raw value is already milliseconds.
		$distance_m=isset($s[9])?$s[9]/100:null; $distance=$distance_m!==null&&$unit==='yd'?$distance_m/0.9144:$distance_m;
		$avg_hr=isset($s[16])&&is_numeric($s[16])?(int)$s[16]:null;$max_hr=isset($s[17])&&is_numeric($s[17])?(int)$s[17]:null;
		$avg_cadence=isset($s[18])&&is_numeric($s[18])?(float)$s[18]:null;$max_cadence=isset($s[19])&&is_numeric($s[19])?(float)$s[19]:null;
		$lengths=array();$seq=1;$offset=0;
		foreach($o['lengths'] as $x){$type=(isset($x[12])&&(int)$x[12]===0)?'rest':'active';$ms=isset($x[3])?(int)$x[3]:null;$lengths[]=array('sequence_no'=>$seq++,'start_time'=>isset($x[2])?$this->fit_time($x[2],$local_offset):null,'start_offset_ms'=>$offset,'distance_m'=>$type==='active'?$pool_m:0,'elapsed_time_ms'=>$ms,'stroke'=>$type==='active'?$this->stroke($x[7]??255):null,'stroke_count'=>$type==='active'&&isset($x[5])&&is_numeric($x[5])?(int)$x[5]:null,'length_type'=>$type,'source_length_index'=>$seq-2,'raw_metadata'=>wp_json_encode($x));if(null!==$ms)$offset+=$ms;}
		$laps=array();$i=1;foreach($o['laps'] as $x){$laps[]=array('sequence_no'=>$i++,'start_time'=>isset($x[2])?$this->fit_time($x[2],$local_offset):null,'distance_m'=>isset($x[9])?$x[9]/100:null,'elapsed_time_ms'=>isset($x[7])?(int)$x[7]:null,'stroke'=>isset($x[24])?$this->stroke($x[24]):null,'source_lap_index'=>$i-2,'raw_metadata'=>wp_json_encode($x));}
		return array('source'=>'fit','parser_version'=>self::VERSION,'workout'=>array('workout_start'=>$start,'workout_end'=>$this->add_milliseconds($start,$elapsed),'total_distance_m'=>$distance_m,'original_distance'=>$distance,'original_distance_unit'=>$unit,'elapsed_time_ms'=>$elapsed,'average_heart_rate'=>$avg_hr,'max_heart_rate'=>$max_hr,'average_cadence'=>$avg_cadence,'max_cadence'=>$max_cadence,'pool_length_m'=>$pool_m,'original_pool_length'=>$pool,'pool_length_unit'=>$unit,'device_manufacturer'=>$device_manufacturer,'device_model'=>$device_model),'laps'=>$laps,'lengths'=>$lengths,'metadata'=>array('fit_local_offset_seconds'=>$local_offset));
	}
}

