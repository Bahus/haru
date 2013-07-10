<?php
require_once 'phing/BuildFileTest.php';
require_once "phing/util/Tools.php";

class LibItemTest extends BuildFileTest
{
	private $_tmpDir;
	private $_testDir;

	public function setUp()
	{
		$this->_testDir = PHING_TEST_BASE . "/etc/tasks/ext/libitem";

		$this->_tmpDir = PHING_TEST_BASE . '/tmp/libitem';
		if ( is_readable( $this->_tmpDir ) )
		{
			// make sure we purge previously created directory
			// if left-overs from previous run are found
			Tools::rmdir( $this->_tmpDir );
		}
		// set temp directory used by test cases
		mkdir( $this->_tmpDir );

		$this->configureProject( $this->_testDir . "/build.xml" );
		$this->project->setProperty( 'tmp.dir', $this->_tmpDir );
	}

	public function tearDown()
	{
		Tools::rmdir( $this->_tmpDir );
	}

	/**
	 *
	 * @dataProvider providerTestLibItem
	 *
	 * @param unknown_type $num
	 */
	public function testLibItem( $num )
	{
		$targetName = 'test' . $num;
		$this->executeTarget( $targetName );

		$skipList = array( '.svn' );

		$this->assertInLogs( "Start make libs item conf.xml" );
		$this->assertInLogs( "File created" );

		$actualDir = $this->_tmpDir;
		$actualFileList = Tools::dirList( $actualDir, $skipList );

		$expectedDir = $this->_testDir . '/expected_' . $num;
		$expectedFileList = Tools::dirList( $expectedDir, $skipList );

		$this->assertEquals( count( $actualFileList ), count( $expectedFileList ) );
		$diff = array_diff( $expectedFileList, $actualFileList );

		$this->assertTrue( empty( $diff ) );

		foreach ( $expectedFileList as $expectedFile )
		{
			$expected = $expectedDir . DIRECTORY_SEPARATOR . $expectedFile;
			$actual = $actualDir . DIRECTORY_SEPARATOR . $expectedFile;
			$this->assertXmlFileEqualsXmlFile( $expected, $actual );
		}
	}

	public function providerTestLibItem()
	{
		$data = array();

		$data[] = array( 1 );
		//$data[] = array( 2 );
		//$data[] = array( 3 );

		return $data;
	}
}