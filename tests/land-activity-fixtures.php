<?php
namespace SwimLogEvaluation\Tests;
/** Synthetic FIT generation only. No personal activities or coordinates. */
final class Land_Fixture {
    private static function crc( $data ) {
        $crc=0;
        for($i=0;$i<strlen($data);$i++){for($bit=0;$bit<8;$bit++){$v=($crc&1)^((ord($data[$i])>>$bit)&1);$crc>>=1;if($v)$crc^=0xA001;}}
        return $crc&0xffff;
    }
    private static function definition( $local, $global, $fields ) {
        $s=chr(0x40|$local).chr(0).chr(0).pack('v',$global).chr(count($fields));
        foreach($fields as $f)$s.=chr($f[0]).chr($f[1]).chr($f[2]);
        return $s;
    }
    private static function data( $local, $fields, $values ) {
        $s=chr($local);
        foreach($fields as $f)$s.=$f[1]===1?chr($values[$f[0]]):pack('V',$values[$f[0]]);
        return $s;
    }
    public static function bytes( $sport=1, $sub=2, $gps=true, $variant=0, $sessions=1, $distance=500000, $elapsed=60000, $moving=45000 ) {
        $start=900000000;
        $session=array(array(2,4,6),array(5,1,2),array(6,1,2),array(7,4,6),array(8,4,6),array(59,4,6),array(9,4,6),array(16,1,2));
        $record=array(array(253,4,6),array(0,4,5),array(1,4,5),array(5,4,6),array(3,1,2));
        $lap=array(array(2,4,6),array(7,4,6),array(8,4,6),array(52,4,6),array(9,4,6));
        $data=self::definition(0,18,$session).self::definition(1,20,$record).self::definition(2,19,$lap);
        foreach(array(0,30,60) as $i=>$offset){
            $lat=$gps?(int)round((40+$i*.001)*2147483648/180):2147483647;
            $lon=$gps?(int)round((-105+$i*.001)*2147483648/180):2147483647;
            $data.=self::data(1,$record,array(253=>$start+$offset,0=>$lat,1=>$lon,5=>$i*250000,3=>120+$variant));
        }
        $data.=self::data(2,$lap,array(2=>$start,7=>$elapsed,8=>50000,52=>$moving,9=>$distance));
        for($i=0;$i<$sessions;$i++)$data.=self::data(0,$session,array(2=>$start,5=>$sport,6=>$sub,7=>$elapsed,8=>50000,59=>$moving,9=>$distance,16=>120+$variant));
        $header=chr(14).chr(0x10).pack('v',100).pack('V',strlen($data)).'.FIT';$header.=pack('v',self::crc($header));
        $file=$header.$data;return $file.pack('v',self::crc($file));
    }
    public static function path( ...$args ) {
        $path=tempnam(sys_get_temp_dir(),'swimlog-land-');file_put_contents($path,self::bytes(...$args));return $path;
    }
}