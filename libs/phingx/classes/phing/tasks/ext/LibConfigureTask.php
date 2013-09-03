<?php
require_once 'phing/Task.php';
require_once 'phing/BuildException.php';

require_once 'phing/tasks/ext/GenerateFileTask.php';

/**
 * Analize specific section and make files from templates and make folder
 */
class LibConfigureTask extends Task
{
	/**
	 *
	 * @var GenerateFileTask
	 */
	protected $_generateFileTask;
	protected $_mkdirTask;
	protected $_chmodTask;
	protected $_configFilename;

	protected $_configXml;

	/**
	 *
	 * @param field_type $configXml
	 */
	public function setConfig( $configXmlFilename )
	{
		$this->_configFilename = $configXmlFilename;
	}

	public function init()
	{
		$this->_generateFileTask = new GenerateFileTask();
		$this->_generateFileTask->setProject( $this->project );

		$this->_mkdirTask = new MkdirTask();
		$this->_mkdirTask->setProject( $this->project );

		$this->_chmodTask = new ChmodTask();
		$this->_chmodTask->setProject( $this->project );
	}

	public function main()
	{
		$configFilename = $this->_configFilename;
		if ( !file_exists( $configFilename ) or !is_readable( $configFilename ) )
		{
			$msg = sprintf( 'File (%s) not found or is not readable', $configFilename );
			throw new BuildException( $msg );
		}
		$xml = simplexml_load_file( $configFilename );
		if ( !$xml || !$xml->libs )
		{
			$msg = sprintf( 'Invalid config structure %s', $configFilename );
			throw new BuildException( $msg );
		}

		$this->_configXml = $xml;

		$msg = 'Start lib configure';
		$this->log( $msg );

		foreach ( $this->_configXml->libs->children() as $lib )
		{
			if ( $lib->configure )
			{

				$libName = $lib->getName();
				$msg = sprintf( "\tAnalize lib \"%s\"", $libName );
				$this->log( $msg );

				if ( $lib->configure->dirs )
				{
					$this->_processDirs( $lib->configure->dirs );
				}

				if ( $lib->configure->files )
				{
					$this->_processFiles( $lib->configure->files );
				}

				if ( $lib->configure->depends )
				{
					$libDst = ( string ) $lib->deploy->dst;
					$this->_processDepends( $libName, $lib->configure->depends, $libDst );
				}
			}
		}

		$msg = 'End lib configure';
		$this->log( $msg );
	}

	protected function _processDirs( $lib )
	{
		$children = $lib->children();
		$msg = sprintf( "\tProcess dirs, found (%s) items", count( $children ) );
		$this->log( $msg );

		foreach ( $children as $item )
		{
			$dir = new PhingFile( ( string ) $item->dir );
			$mode = ( $item->mode ? ( string ) $item->mode : false );

			$this->_mkdirTask->setDir( $dir );
			$this->_mkdirTask->main();
			if ( $mode )
			{
				$this->_chmodTask->setFile( $dir );
				$this->_chmodTask->setMode( $mode );
				$this->_chmodTask->main();
			}

		}
	}

	protected function _processFiles( $lib )
	{
		$children = $lib->children();
		$msg = sprintf( "\tProcess files, found (%s) items", count( $children ) );
		$this->log( $msg );

		foreach ( $children as $item )
		{
			$srcFile = trim( ( string ) $item->src );
			$dstFile = trim( ( string ) $item->dst );
			$mode = ( $item->mode ? ( string ) $item->mode : false );

			$this->_generateFileTask->setSrc( $srcFile );
			$this->_generateFileTask->setDst( $dstFile );
			$this->_generateFileTask->setMode( $mode );
			$this->_generateFileTask->main();
		}
	}

	protected function _processDepends( $libName, $depends, $libDst )
	{
		$projectRootDir = trim( ( string ) $this->_configXml->paths->root );
		$dataDir = trim( ( string ) $this->_configXml->paths->data );

		$msg = sprintf( "\tProcess depends" );
		$this->log( $msg );

		$children = $depends->children();
		if ( count( $children ) )
		{
			$mainConfigFilename =  $libDst . '/data/config_main.php';
			$libConfigFilename = $libDst . '/data/config_map.php';

			$data = array();
			$data[ 'project_root' ] = $projectRootDir;
			$data[ 'main_config_filename' ] = $mainConfigFilename;
			$data[ 'libs' ] = array();

			$data[ 'libs' ][] = $this->_makeDependItem( $libName );

			foreach ( $children as $item )
			{
				$name = $item->getName();
                $libName = ( string ) $item;
                if ( $this->_configXml->libs->$libName->deploy )
                {
				    $data[ 'libs' ][] = $this->_makeDependItem( $libName );
                }
			}

			$res = Tools::saveToPhp( $libConfigFilename, $data );

			if ( $res )
			{
				$msg = sprintf( "\tLib config saved (%s)", $libConfigFilename );
				$this->log( $msg );
			}
			else
			{
				$message = sprintf( 'File %s cannot save', $libConfigFilename );
				throw new BuildException( $message );
			}
		}
		else
		{
			$msg = sprintf( "\tSkipped depends: empty list" );
			$this->log( $msg );
		}
	}

	protected function _makeDependItem( $libName )
	{
		$tmp = array();
		$tmp[ 'name' ] = $libName;
		$tmp[ 'path' ] = $this->_getDstByLibName( $libName );
		$tmp[ 'plugin' ] = $this->_getPluginByLibName( $libName );
		return $tmp;
	}

	protected function _getDstByLibName( $libName )
	{
		$lib = $this->_configXml->libs->$libName;

		$result = '';
		if ( $lib )
		{
			if ( !$lib->deploy || !$lib->deploy->dst )
			{
				$message = sprintf( 'Invalid lib config (%s): must be content "deploy" section', $libName );
				throw new BuildException( $message );
			}

			$result = ( string ) $lib->deploy->dst;
		}
		else
		{
			$message = sprintf( 'Unknown lib (%s)', $libName );
			throw new BuildException( $message );
		}

		return $result;
	}

	protected function _getPluginByLibName( $libName )
	{
		$lib = $this->_configXml->libs->$libName;

		$result = '';
		if ( $lib )
		{
			if ( $lib->plugin )
			{
				$result = ( string ) $lib->plugin;
			}
			else
			{
				$result = 'Standart';
			}
		}
		else
		{
			$message = sprintf( 'Unknown lib (%s)', $libName );
			throw new BuildException( $message );
		}

		return $result;
	}
}