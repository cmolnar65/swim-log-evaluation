<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Shortcodes;
require_once __DIR__.'/public-visibility-fixture.php';
final class AnnualWorkoutTypeLogTest extends TestCase {
 public function test_annual_log_separates_public_pool_open_water_and_land_totals(){
  global $wpdb,$swimlog_test_user_meta;$old=$swimlog_test_user_meta;$swimlog_test_user_meta[42]['swimlog_public_results']='1';
  try{$wpdb=new PublicVisibilityFixture(array(PublicVisibilityFixture::row('pool',3000),PublicVisibilityFixture::row('pool',2000,'yd'),PublicVisibilityFixture::row('open_water',5000),PublicVisibilityFixture::row('running',10000),PublicVisibilityFixture::row('walking',700)));
   $html=Shortcodes::log(array('user'=>42,'year'=>2026));foreach(array('Pool — 2','Open Water — 1','Running — 1','Walking — 1','3,000.00 m','2,000.00 yd','5,000.00 m','10,000.00 m','700.00 m','2026 Total','January 2026')as$text)$this->assertStringContainsString($text,$html);
   $sql=implode("\n",$wpdb->queries);$this->assertStringContainsString("v.visibility IN ('public','legacy_public')",$sql);$this->assertStringContainsString('2026-12-31 23:59:59',$sql);
  }finally{$swimlog_test_user_meta=$old;}
 }
}
