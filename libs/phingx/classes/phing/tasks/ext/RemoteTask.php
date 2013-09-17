<?php
require_once 'phing/Task.php';
require_once 'phing/BuildException.php';

/**
 * Execute remote operations
 */
class RemoteTask extends Task
{
	private $_host;
	private $_target;
	private $_ips = array();

	/**
	 * Whether to check the return code.
	 * @var boolean
	 */
	protected $_checkreturn = true;

	/**
	 *
	 * @param field_type $host
	 */
	public function setHost( $host )
	{
		$this->_host = $host;
	}

	/**
	 *
	 * @param field_type $_target
	 */
	public function setTarget( $target )
	{
		$this->_target = $target;
	}

	/**
	 * Whether to check the return code.
	 *
	 * @param boolean $checkreturn If the return code shall be checked
	 *
	 * @return void
	 */
	public function setCheckreturn($checkreturn)
	{
		$this->_checkreturn = (bool) $checkreturn;
	}

	public function init()
	{

	}

	public function main()
	{
		if ( empty( $this->_target ) )
		{
			$message = 'Param "target" not found.';
			throw new BuildException( $message );
		}
		$this->_checkHost( $this->_host );

		$msg = sprintf( "Host: %s, IPs found: %s", $host, implode( ' ', $this->_ips ) );
		$this->log( $msg );
		$msg = sprintf( 'Target: %s', $this->_target );
		$this->log( $msg );

		$commandAr = array();
		$buildDirRoot = $this->getProject()->getProperty( 'build.dir.root' );
		$commandAr[] = $buildDirRoot . '/bin/phing';
		$projectBasedir = $this->getProject()->getProperty( 'project.basedir' );
		$commandAr[] = sprintf( '-f %s/build.xml', $projectBasedir );
		$commandAr[] = $this->_target;
		$buildType = $this->getProject()->getProperty( 'build.type' );
		$commandAr[] = sprintf( '-Dbt=%s', $buildType );
		$buildUser = $this->getProject()->getProperty( 'build.user' );
		if ( $buildUser )
		{
			$commandAr[] = sprintf( '-Dbu=%s', $buildUser );
		}
		$buildBranch = $this->getProject()->getProperty( 'build.branch' );
		if ( $buildBranch )
		{
		    $commandAr[] = sprintf( '-Dbb=%s', $buildBranch );
		}

		foreach ( $this->_ips as $ip )
		{
			$command = sprintf( 'ssh %s %s', $ip, implode( ' ', $commandAr ) );

			$msg = 'Run command ' . $command;
			$this->log( $msg );

			$returnProp = 'remote.return';
			$outputProp = 'remote.output';
			$obj = new ExecTask();
			$obj->setProject( $this->project );
			$obj->setCommand( $command );
			$obj->setLogoutput( true );
			$obj->setReturnProperty( $returnProp );
			$obj->setOutputProperty( $outputProp );
			$obj->setCheckreturn( $this->_checkreturn );
			$obj->setPassthru( true );
			$obj->setLevel( 'info' );
			$obj->main();
		}
	}

	protected function _checkHost( $host )
	{
		if ( is_null( $host ) )
		{
			$message = 'Param "host" not found.';
			throw new BuildException( $message );
		}
		$this->_ips = gethostbynamel( $host );
		if ( !is_array( $this->_ips ) )
		{
			throw new BuildException( 'Bad param: host:' . $host );
		}
	}
}