<?php
use PHPUnit\Framework\TestCase;

final class ReleasePackagingBlockTest extends TestCase {
	public function test_release_builders_do_not_exclude_blocks_directory() {
		foreach ( array( 'build-release.sh', 'build-release.ps1' ) as $file ) {
			$script = file_get_contents( dirname( __DIR__ ) . '/' . $file );
			$this->assertStringNotContainsString( '"blocks"', $this->exclusion_section( $script ), $file );
		}
	}


	public function test_release_builders_exclude_wordpress_org_listing_assets() {
		foreach ( array( 'build-release.sh', 'build-release.ps1' ) as $file ) {
			$script = file_get_contents( dirname( __DIR__ ) . '/' . $file );
			$this->assertStringContainsString( '"wordpress-org-assets"', $this->exclusion_section( $script ), $file );
		}
	}

	public function test_release_builders_exclude_all_development_only_top_level_items() {
		foreach ( array( 'build-release.sh', 'build-release.ps1' ) as $file ) {
			$script = file_get_contents( dirname( __DIR__ ) . '/' . $file );
			$section = $this->exclusion_section( $script );
			foreach ( array( '.git', '.github', 'tests', 'phpunit.xml.dist', 'composer.json', 'composer.lock', 'build-release.ps1', 'build-release.sh', 'PLUGIN-CHECK.md', 'wordpress-org-assets' ) as $excluded ) {
				$this->assertStringContainsString( '"' . $excluded . '"', $section, $file . ' must exclude ' . $excluded );
			}
		}
	}

	public function test_all_seven_block_assets_exist_for_distribution() {
		foreach ( array( 'latest-workouts', 'personal-bests', 'upcoming-events', 'workout', 'training-log', 'progress', 'calendar' ) as $dir ) {
			$this->assertFileExists( dirname( __DIR__ ) . '/blocks/' . $dir . '/block.json', $dir );
			$this->assertFileExists( dirname( __DIR__ ) . '/blocks/' . $dir . '/index.js', $dir );
		}
	}

	public function test_plugin_compatibility_headers_remain_wordpress_6_and_php_74() {
		$plugin = file_get_contents( dirname( __DIR__ ) . '/swim-log-evaluation.php' );
		$readme = file_get_contents( dirname( __DIR__ ) . '/readme.txt' );
		$this->assertStringContainsString( 'Requires at least: 6.0', $plugin );
		$this->assertStringContainsString( 'Requires PHP: 7.4', $plugin );
		$this->assertStringContainsString( 'Requires at least: 6.0', $readme );
		$this->assertStringContainsString( 'Requires PHP: 7.4', $readme );
	}

	private function exclusion_section( $script ) {
		$start = strpos( $script, 'EXCLUDE_TOP_LEVEL' );
		if ( false === $start ) {
			$start = strpos( $script, '$ExcludeTopLevel' );
		}
		$end = strpos( $script, ')', $start );
		return substr( $script, $start, $end - $start + 1 );
	}
}
