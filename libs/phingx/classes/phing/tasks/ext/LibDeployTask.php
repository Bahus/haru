<?php
use Demo\Example\implode;

require_once 'phing/Task.php';
require_once 'phing/tasks/system/ExecTask.php';

/**
 * Deploy lib by config file
 *
 * @example
 * <code>
 * <Kin>
 * 	...
 * 		<deploy>
 * 			<type>hg</type>		<!-- Require. Variants: <none|svn|git|hg>. Version Control System type. -->
 * 			<tag>default</tag>	<!-- Require. Tag name. -->
 *
 * 			<src>https://github.com/TheRatG/kin.git</src>	<!-- Require. Source library: url or path.-->
 * 			<dst>/www/project/libs/kin/default</dst>	<!-- Require. Destination folder.-->
 *
 * 			<current>/www/project/libs/kin/current</current> <!-- Optional. Symlink for current library version-->
 *
 * 			<username></username> <!-- Optional. VSC username-->
 * 			<password></password> <!-- Optional. VSC password-->
 * 			<export></export> <!-- Optional. For svn -->
 * 		</deploy>
 * 	...
 * </Kin>
 * </code>
 */
class LibDeployTask extends Task
{
	const TYPE_SVN = 'svn';
	const TYPE_GIT = 'git';
	const TYPE_MERCURIAL = 'hg';
	const TYPE_NONE = 'none';
	protected $filesets = array();

	public function setFailonerror( $value )
	{
		$this->failonerror = $value;
	}

	public function createFileSet()
	{
		$num = array_push( $this->filesets, new FileSet() );
		$num--;
		$result = $this->filesets[ $num ];
		return $result;
	}

	/* internal prop */
	protected $_execTask;
	protected $_type;
	protected $_bin;
	protected $_username;
	protected $_password;
	protected $_tag;
	protected $_dst;
	protected $_src;
	protected $_isExport;

	public function setIsTest( $isTest )
	{
		$this->isTest = $isTest;
	}

	public function init()
	{
		$this->_execTask = new ExecTask();
		$this->_execTask->setPassthru( true );
		$this->_execTask->setLevel( 'info' );
		$this->_execTask->setCheckreturn( true );
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
				$files = $fs->getDirectoryScanner(
					$this->project )->getIncludedFiles();
				$fullPath = realpath( $fs->getDir( $this->project ) );

				foreach ( $files as $file )
				{
					$name = str_replace( '.xml', '', $file );
					$msg = sprintf( 'Start deploy %s', $name );
					$this->log( $msg );

					$filename = sprintf( '%s/%s', $fullPath, $file );
					$config = simplexml_load_file( $filename );
                    if ( $config->deploy )
                    {
					    $this->_initDeployParams( $name, $config );
					    $this->_deployItem( $name, $config );
                    }
                    else
                    {
                        $msg = sprintf( "\tDeploy section not found", $name );
                        $this->log( $msg );
                    }
				}
			}
			catch ( BuildException $be )
			{
				// папка не существует или доступ к ней закрыт
				throw $be;
			}
		}

		$msg = 'Complete';
		$this->log( $msg );
	}

	protected function _initDeployParams( $name, $config )
	{
		$msg = sprintf( "\tRead config %s", $name );
		$this->log( $msg );

		$type = ( string ) $config->deploy->type;
		$this->_type = $type;

		$tag = null;
		if ( $config->deploy->tag )
		{
			$tag = ( string ) $config->deploy->tag;
		}
		if ( !$tag )
		{
			switch ( $this->_type )
			{
				case self::TYPE_MERCURIAL:
					$tag = 'default';
					break;
				case self::TYPE_SVN:
					$tag = 'trunk';
					break;
				case self::TYPE_GIT:
					$tag = 'master';
					break;
			}
		}
		$this->_tag = $tag;

		$this->_username = ( string ) $config->deploy->username;
		$this->_password = ( string ) $config->deploy->password;
		$src = ( string ) $config->deploy->src;
		$this->_src = trim( $src );
		$dst = ( string ) $config->deploy->dst;
		$this->_dst = trim( $dst );

		$this->_isExport = false;
		if ( $config->deploy->export )
		{
			$this->_isExport = ( bool ) $config->deploy->export;
		}

		$msg = sprintf( "\tType: %s", $this->_type );
		$this->log( $msg );
		$msg = sprintf( "\tTag: %s", $this->_tag );
		$this->log( $msg );
		$msg = sprintf( "\tUsername: %s", $this->_username );
		$this->log( $msg );
		$msg = sprintf( "\tSource url: %s", $this->_src );
		$this->log( $msg );
		$msg = sprintf( "\tDestination folder: %s", $this->_dst );
		$this->log( $msg );

		$error = '';
		if ( empty( $this->_src ) && $this->_type !== self::TYPE_NONE )
		{
			$error = 'Invalid config param "src" is require';
		}
		if ( empty( $this->_type ) )
		{
			$error = 'Invalid config param "src" is require';
		}
		if ( empty( $this->_dst ) )
		{
			$error = 'Invalid config param "src" is require';
		}
		if ( $error )
		{
			throw new BuildException( $error );
		}
	}

	protected function _deployItem( $name, $config )
	{
		$type = $this->_type;
		$functionName = '_deploy' . ucfirst( $type );

		if ( !method_exists( $this, $functionName ) )
		{
			$msg = sprintf( "Unknown deploy type %s", $type );
			$this->log( $msg, Project::MSG_ERR );
			throw new BuildException( $msg );
		}

		call_user_func_array( array( $this, $functionName ),
			array( $name, $config ) );
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

		$username = $this->_username;
		$password = $this->_password;
		$isExport = $this->_isExport;
		$bin = $this->project->getProperty( 'system.bin.svn' );
		if ( empty( $bin ) )
		{
			$bin = 'svn';
		}

		$repositoryUrl = $this->_src;

		$toDir = $this->_dst;
		$toDir = $this->_geFullPath( $toDir );

		$commandAr = array();
		$isUpdate = false;
		if ( file_exists( $toDir ) && is_dir( $toDir ) && file_exists(
			$toDir . '/.svn' ) )
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

		$repositoryUrl = $this->_src;
		$bin = $this->project->getProperty( 'system.bin.git' );
		if ( empty( $bin ) )
		{
			$bin = 'git';
		}

		$branch = $this->_tag;

		$toDir = $this->_dst;
		$toDir = $this->_geFullPath( $toDir );

		$returnProp = 'libdeploy.git.return';
		$outputProp = 'libdeploy.git.output';

		if ( file_exists( $toDir ) && is_dir( $toDir ) && file_exists(
			$toDir . '/.git' ) )
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
			$command = sprintf( '%s clone "%s" "%s"', $bin, $repositoryUrl,
				$toDir );
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
		$this->log( "\tDeploy by mercurial" );

		$username = $this->_username;
		$password = $this->_password;
		$repositoryUrl = $this->_src;
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
		$repositoryUrlSecure = preg_replace(
			'/(http|https|ssh|hb|git):\/\/(.*)/i', $replace, $repositoryUrl );
		$auth = array();
		if ( $this->_username )
		{
		    $auth[] = sprintf( '--config auth.repo.username="%s"', $this->_username );
		}
		if ( $this->_password )
		{
		    $auth[] = sprintf( '--config auth.repo.password="%s"', $this->_password );
		}
		if ( !empty( $auth ) )
		{
		    $ar = parse_url( $repositoryUrl );
		    array_unshift( $auth, sprintf( '--config auth.repo.prefix="%s"', $ar['scheme'] . '://' . $ar['host'] ) );
		}
		$auth = implode( ' ', $auth );

		$branch = $this->_tag;

		$toDir = ( string ) $config->deploy->dst;
		$toDir = trim( $toDir );
		$toDir = $this->_geFullPath( $toDir );

		$returnProp = 'libdeploy.git.return';
		$outputProp = 'libdeploy.git.output';

		if ( file_exists( $toDir ) && is_dir( $toDir ) && file_exists(
			$toDir . '/.hg' ) )
		{
			$branchSuffix = empty( $branch ) ? '' : sprintf( ' -r %s', $branch );
			$command = sprintf( '%s %s pull -u %s', $bin, $auth, $branchSuffix );

			$this->_exec( $command, $returnProp, $outputProp, $toDir );
		}
		else
		{
			$parentDir = dirname( $toDir );
			if ( !file_exists( $parentDir ) )
			{
				$this->log( 'Trying to create lib root dir: ' . $parentDir );
				$rootDirCreateResult = mkdir( $parentDir, 0775, true );
				$this->log( $rootDirCreateResult ? 'OK' : 'Fail' );
			}

			$command = sprintf( '%s clone "%s" "%s"', $bin,
				$repositoryUrlSecure, $toDir );
			if ( !empty( $branch ) )
			{
				$command = $command . ' -r ' . $branch;
			}
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
