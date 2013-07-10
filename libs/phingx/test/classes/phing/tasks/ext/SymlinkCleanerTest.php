<?php
require_once 'phing/BuildFileTest.php';
require_once "phing/util/Tools.php";
class SymlinkCleanerTest extends BuildFileTest
{
    private $_tmpDir;
    private $_testDir;

    public function setUp()
    {
        $this->_testDir = PHING_TEST_BASE . "/etc/tasks/ext/symlinkcleaner";

        $this->_tmpDir = PHING_TEST_BASE . '/tmp/symlinkcleaner';
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
    public function testClear( $num )
    {
        $targetName = 'test' . $num;
        $this->executeTarget( $targetName );

        $skipList = array( '.svn' );

        $this->assertInLogs( "Start cleaner task" );
        $this->assertInLogs( "Delete broke symlink" );

        $actualDir = $this->_tmpDir;
        $actualFileList = Tools::dirList( $actualDir, $skipList );
        $expectedFileList = array( 'symlink_tag1', 'tag1' );
        $this->assertEquals( $actualFileList, $expectedFileList );
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