<?php
require_once 'phing/Task.php';
require_once 'phing/tasks/system/ExecTask.php';

/**
 * Super installer
 */
class LibDeployTask extends Task
{

	protected $failonerror = false;

	protected $filesets = array();

	protected $quiet = false;

	protected $isTest = false;

	protected $_execTask;

	public function setFailonerror( $value )
	{
		$this->failonerror = $value;
	}

	public function setIsTest( $isTest )
	{
		$this->isTest = $isTest;
	}

	public function createFileSet()
	{
		$num = array_push( $this->filesets, new FileSet() );
		$num--;
		$result = $this->filesets[ $num ];
		return $result;
	}

	public function init()
	{
		$this->_execTask = new ExecTask();
		$this->_execTask->setPassthru( true );
		$this->_execTask->setLevel( 'info' );
		return true;
	}

	public function main()
	{
		$msg = 'Start';
		$this->log( $msg );

		foreach ( $this->filesets as $fs )
		{
			try
			{
				// получаем массив со списком исходных файлов
				$files = $fs->getDirectoryScanner( $this->project )->getIncludedFiles();
				$fullPath = realpath( $fs->getDir( $this->project ) );

				foreach ( $files as $file )
				{
					$name = str_replace( '.xml', '', $file );
					$msg = sprintf( 'Start deploy %s', $name );
					$this->log( $msg );

					$filename = sprintf( '%s/%s', $fullPath, $file );
					$config = simplexml_load_file( $filename );
					$this->_deployItem( $name, $config );
				}
			}
			catch ( BuildException $be )
			{
				// папка не существует или доступ к ней закрыт
				if ( $this->failonerror )
				{
					throw $be;
				}
				else
				{
					$this->log( $be->getMessage(), $this->quiet ? Project::MSG_VERBOSE : Project::MSG_ERR );
				}
			}
		}

		$msg = 'Complete';
		$this->log( $msg );
	}

	protected function _deployItem( $name, $config )
	{
		$msg = sprintf( "\tRead config %s", $name );
		$this->log( $msg );

		$type = ( string ) $config->deploy->type;
		$functionName = '_deploy' . ucfirst( $type );

		if ( !method_exists( $this, $functionName ) )
		{
			$msg = sprintf( "Unknown deploy type %s", $type );
			$this->log( $msg, Project::MSG_ERR );
			throw new BuildException( $msg );
		}

		call_user_func_array( array( $this, $functionName ), array(
			$name,
			$config ) );
	}

	protected function _deployNone()
	{
		$msg = sprintf( "\tNothing to deploy!" );
		$this->log( $msg );
	}

	/**
	 * @param $name
	 * @param $config
	 */
	protected function _deploySvn( $name, $config )
	{
		$this->log( "\tDeploy by svn" );

		$username = ( string ) $config->deploy->svn->username;
		$username = trim( $username );
		$password = ( string ) $config->deploy->svn->password;
		$isExport = ( bool ) $config->deploy->export;
		$bin = $this->project->getProperty( 'system.bin.svn' );
		if ( empty( $bin ) )
		{
			$bin = 'svn';
		}

		$repositoryUrl = ( string ) $config->deploy->src;
		$repositoryUrl = trim( $repositoryUrl );

		$toDir = ( string ) $config->deploy->dst;
		$toDir = trim( $toDir );

		$toDir = $this->_geFullPath( $toDir );

		$commandAr = array();
		$isUpdate = false;
		if ( file_exists( $toDir ) && is_dir( $toDir ) && file_exists( $toDir . '/.svn' ) )
		{
			$commandAr[] = sprintf( '%s update', $bin );
			$isUpdate = true;
		}
		else if ( $isExport )
		{
			$commandAr[] = sprintf( '%s export', $bin );
		}
		else
		{
			$commandAr[] = sprintf( '%s checkout', $bin );
		}

		if ( $username )
		{
			$commandAr[] = sprintf( '--username "%s"', $username );
		}
		if ( $password )
		{
			$commandAr[] = sprintf( '--password "%s"', $password );
		}

		$returnProp = 'libdeploy.svn.return';
		$outputProp = 'libdeploy.svn.output';
		if ( !$isUpdate )
		{
			$commandAr[] = sprintf( '"%s" "%s"', $repositoryUrl, $toDir );
			$command = implode( ' ', $commandAr );
			$this->_exec( $command, $returnProp, $outputProp );
		}
		else
		{
			$command = implode( ' ', $commandAr );
			$this->_exec( $command, $returnProp, $outputProp, $toDir );
		}
		$msg = sprintf( "\tDeploy by svn complete" );
		$this->log( $msg );
	}

	/**
	 * @param $name
	 * @param $config
	 * @throws BuildException
	 */
	protected function _deployGit( $name, $config )
	{
		$this->log( "\tDeploy by git" );

		$repositoryUrl = ( string ) $config->deploy->src;
		$repositoryUrl = trim( $repositoryUrl );
		$bin = $this->project->getProperty( 'system.bin.git' );
		if ( empty( $bin ) )
		{
			$bin = 'git';
		}

		$branch = '';
		if ( $config->deploy->tag )
		{
			$branch = ( string ) $config->deploy->tag;
		}

		$toDir = ( string ) $config->deploy->dst;
		$toDir = trim( $toDir );
		$toDir = $this->_geFullPath( $toDir );

		$returnProp = 'libdeploy.git.return';
		$outputProp = 'libdeploy.git.output';

		if ( file_exists( $toDir ) && is_dir( $toDir ) && file_exists( $toDir . '/.git' ) )
		{
			$command = '';
			if ( !empty( $branch ) )
			{
				$command = sprintf( '%s checkout "%s";', $bin, $branch );
				$this->_exec( $command, $returnProp, $outputProp, $toDir );
			}
			$command = sprintf( '%s pull origin %s', $bin, $branch );
			$this->_exec( $command, $returnProp, $outputProp, $toDir );
		}
		else
		{
			$command = sprintf( '%s clone "%s" "%s"', $bin, $repositoryUrl, $toDir );
			if ( !empty( $branch ) )
			{
				$command = $command . ' --branch ' . $branch;
			}
			$this->_exec( $command, $returnProp, $outputProp );
		}

		$msg = sprintf( "\tDeploy by git complete" );
		$this->log( $msg );
	}

	/**
	 * @param $name
	 * @param $config
	 * @throws BuildException
	 */
	protected function _deployHg( $name, $config )
	{
		$this->log( "\tDeploy by Mercurial" );

		$username = ( string ) $config->deploy->hg->username;
		$username = trim( $username );
		$password = ( string ) $config->deploy->hg->password;
		$repositoryUrl = ( string ) $config->deploy->src;
		$repositoryUrl = trim( $repositoryUrl );
		$bin = $this->project->getProperty( 'system.bin.hg' );
		if ( empty( $bin ) )
		{
			$bin = 'hg';
		}

		$inner = '';
		if ( !empty( $username ) )
		{
			$inner = $username;
		}
		if ( !empty( $inner ) )
		{
			if ( !empty( $password ) )
			{
				$inner = $inner . ':' . $password;
			}
			$inner .= '@';
		}
		$replace = sprintf( '$1://%s$2', $inner );
		$repositoryUrlSecure = preg_replace( '/(http|https|ssh|hb|git):\/\/(.*)/i', $replace, $repositoryUrl );

		$branch = '';
		if ( $config->deploy->tag )
		{
			$branch = ( string ) $config->deploy->tag;
		}

		$toDir = ( string ) $config->deploy->dst;
		$toDir = trim( $toDir );
		$toDir = $this->_geFullPath( $toDir );

		$returnProp = 'libdeploy.git.return';
		$outputProp = 'libdeploy.git.output';

		if ( file_exists( $toDir ) && is_dir( $toDir ) && file_exists( $toDir . '/.hg' ) )
		{
			$branchSuffix = empty( $branch ) ? '' : sprintf( ' -r %s', $branch );
			$command = sprintf( '%s pull -u %s', $bin, $branchSuffix );


			$this->log( 'Please enter login and password' );
			$this->_exec( $command, $returnProp, $outputProp, $toDir );
		}
		else
		{
			$command = sprintf( '%s clone "%s" "%s"', $bin, $repositoryUrlSecure, $toDir );
			if ( !empty( $branch ) )
			{
				$command = $command . ' -r ' . $branch;
			}
			$this->log( 'Please enter login and password' );
			$this->_exec( $command, $returnProp, $outputProp );
		}

		$msg = sprintf( "\tDeploy by mercurial complete" );
		$this->log( $msg );
	}

	protected function _geFullPath( $filename )
	{
		$file = new PhingFile( $filename );
		if ( !$file->isAbsolute() )
		{
			$file = new PhingFile( $this->project->getBasedir(), $filename );
		}
		$result = $file->getPath();
		return $result;
	}

	protected function _exec( $command, $returnProp, $outputProp, $dir = '' )
	{
		$obj = $this->_execTask;
		$returnProp = 'libdeploy.hg.return';
		$outputProp = 'libdeploy.hg.output';
		$obj->setProject( $this->project );
		$obj->setReturnProperty( $returnProp );
		$obj->setOutputProperty( $outputProp );

		if ( !empty( $dir ) )
		{
			$dir = new PhingFile( $dir );
			$obj->setDir( $dir );
		}

		$obj->setCommand( $command );
		$obj->main();

		$res = $this->project->getProperty( $returnProp );
		$output = $this->project->getProperty( $outputProp );

		if ( 0 !== $res )
		{
			throw new BuildException( $output );
		}
	}
}
