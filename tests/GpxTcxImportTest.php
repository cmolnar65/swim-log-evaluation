<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\XML_Importer;
final class GpxTcxImportTest extends TestCase {
 private function tempFile($xml){$p=tempnam(sys_get_temp_dir(),'swimlog-xml-');file_put_contents($p,$xml);return$p;}
 public function test_gpx_normalizes_as_open_water(){
  if(!function_exists('simplexml_load_file'))$this->markTestSkipped('SimpleXML unavailable');
  $xml='<?xml version="1.0"?><gpx version="1.1" xmlns="http://www.topografix.com/GPX/1/1"><trk><trkseg><trkpt lat="39.7000" lon="-104.9000"><time>2026-10-23T14:00:00Z</time><extensions><hr>120</hr></extensions></trkpt><trkpt lat="39.7010" lon="-104.9000"><time>2026-10-23T14:01:00Z</time><extensions><hr>130</hr></extensions></trkpt><trkpt lat="39.7020" lon="-104.9000"><time>2026-10-23T14:02:00Z</time><extensions><hr>140</hr></extensions></trkpt></trkseg></trk></gpx>';
  $p=$this->tempFile($xml);$r=(new XML_Importer())->parse($p,'gpx');unlink($p);
  $this->assertFalse(is_wp_error($r));$this->assertSame('open_water',$r['workout']['workout_type']);$this->assertCount(3,$r['trackpoints']);$this->assertGreaterThan(200,$r['workout']['total_distance_m']);$this->assertEquals(130.0,$r['workout']['average_heart_rate']);$this->assertSame(120000,$r['workout']['elapsed_time_ms']);
 }
 public function test_tcx_prefers_cumulative_source_distance(){
  if(!function_exists('simplexml_load_file'))$this->markTestSkipped('SimpleXML unavailable');
  $xml='<?xml version="1.0"?><TrainingCenterDatabase xmlns="http://www.garmin.com/xmlschemas/TrainingCenterDatabase/v2"><Activities><Activity Sport="Other"><Lap StartTime="2026-10-23T14:00:00Z"><Track><Trackpoint><Time>2026-10-23T14:00:00Z</Time><Position><LatitudeDegrees>39.7000</LatitudeDegrees><LongitudeDegrees>-104.9000</LongitudeDegrees></Position><DistanceMeters>0</DistanceMeters></Trackpoint><Trackpoint><Time>2026-10-23T14:01:00Z</Time><Position><LatitudeDegrees>39.7001</LatitudeDegrees><LongitudeDegrees>-104.9000</LongitudeDegrees></Position><DistanceMeters>500</DistanceMeters></Trackpoint><Trackpoint><Time>2026-10-23T14:02:00Z</Time><Position><LatitudeDegrees>39.7002</LatitudeDegrees><LongitudeDegrees>-104.9000</LongitudeDegrees></Position><DistanceMeters>1000</DistanceMeters></Trackpoint></Track></Lap></Activity></Activities></TrainingCenterDatabase>';
  $p=$this->tempFile($xml);$r=(new XML_Importer())->parse($p,'tcx');unlink($p);
  $this->assertFalse(is_wp_error($r));$this->assertEquals(1000.0,$r['workout']['total_distance_m']);$this->assertEquals(1000.0,$r['trackpoints'][2]['distance_m']);
 }
 public function test_importer_and_admin_support_both_formats(){
  $importer=file_get_contents(dirname(__DIR__).'/includes/class-importer.php');$admin=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');$plugin=file_get_contents(dirname(__DIR__).'/includes/class-plugin.php');
  $this->assertStringContainsString("array('fit','csv','gpx','tcx')",$importer);
  $this->assertStringContainsString('Importer::parse_source($row->source_type,$row->stored_path)',$admin);
  $this->assertStringContainsString('accept=".fit,.fit.gz,.zip,.csv,.csv.gz,.gpx,.gpx.gz,.tcx,.tcx.gz"',$admin);
  $this->assertStringContainsString("\$mimes['gpx'] = 'application/gpx+xml';",$plugin);
  $this->assertStringContainsString("\$mimes['tcx'] = 'application/vnd.garmin.tcx+xml';",$plugin);
 }
}
