<?php
require_once 'phing/BuildFileTest.php';
require_once "phing/util/Tools.php";

class LibDeployTest extends BuildFileTest
{
	private $_testDir;
	private $_tmpDir;

	protected static $_defaultMessages = array(
		'Start',
		'Start deploy',
		'Read config',
		'Complete' );

	public function setUp()
	{
		$this->_testDir = PHING_TEST_BASE . "/etc/tasks/ext/libdeploy";

		$this->_tmpDir = PHING_TEST_BASE . '/tmp/libdeploy';
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
	 *
	 * @dataProvider providerTestDeploy
	 *
	 * @param unknown_type $num
	 */
	public function testDeploy( $num = 1, $messages = array() )
	{
		//because we use verbose exec task
		ob_start();
		$targetName = 'deploy_' . $num;
		$this->executeTarget( $targetName );

		foreach ( $messages as $m )
		{
			$this->assertInLogs( $m );
		}
		ob_end_clean();
	}

	public function providerTestDeploy()
	{
		$data = array();

		$data[] = array(
			1,
			array_merge_recursive( self::$_defaultMessages, array(
				'Deploy by svn',
				'Deploy by svn complete' ) ) );
		$data[] = array(
			2,
			array_merge_recursive( self::$_defaultMessages, array(
				'Deploy by git',
				'Deploy by git complete' ) ) );
		$data[] = array(
				3,
				array_merge_recursive( self::$_defaultMessages, array(
						'Deploy by mercurial',
						'Deploy by mercurial complete' ) ) );
		$data[] = array(
				4,
				array_merge_recursive( self::$_defaultMessages, array(
						'Deploy by mercurial',
						'Deploy by mercurial complete', '1.0' ) ) );

		return $data;
	}

	/**
	 *
	 *
	 * @dataProvider providerTestDeployError
	 *
	 * @param unknown_type $num
	 */
	public function testDeployError( $num = 1, $messages = array() )
	{
		$this->setExpectedException( 'BuildException' );
		$targetName = 'deploy_error_' . $num;
		$this->executeTarget( $targetName );

		foreach ( $messages as $m )
		{
			$this->assertInLogs( $m );
		}
	}

	public function providerTestDeployError()
	{
		$data = array();

		$data[] = array(
			1,
			self::$_defaultMessages + array( 'Unknown deploy type' ) );

// 		$data[] = array(
// 			2,
// 			self::$_defaultMessages + array( 'doesn\'t exist' ) );

		return $data;
	}
}