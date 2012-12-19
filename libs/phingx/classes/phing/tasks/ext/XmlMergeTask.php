<?php
require_once "phing/Task.php";
require_once "phing/util/Converter.php";
require_once "phing/util/Tools.php";

class XmlMergeTask extends Task
{
	const TYPE_XML = 'xml';
	const TYPE_PHP = 'php';

	private $_srcFileList;
	private $_srcFileListDelimetr = ',';
	private $_dstFile;
	private $_fileType = self::TYPE_PHP;

	private $_srcFileListArr = array();

	/**
	 * all fileset objects assigned to this task
	 *
	 * @var unknown_type
	 */
	protected $filesets = array();

	/**
	 * all filelist objects assigned to this task
	 *
	 * @var unknown_type
	 */
	protected $filelists = array();

	/**
	 * Nested creator, creates a FileSet for this task
	 *
	 * @access public
	 * @return object The created fileset object
	 */
	function createFileSet()
	{
		$num = array_push( $this->filesets, new FileSet() );
		return $this->filesets[ $num - 1 ];
	}

	/**
	 * Nested creator, adds a set of files (nested fileset attribute).
	 *
	 * @access public
	 * @return object The created filelist object
	 */
	function createFileList()
	{
		$num = array_push( $this->filelists, new FileList() );
		return $this->filelists[ $num - 1 ];
	}

	/**
	 *
	 * @param field_type $_srcFileListDelimetr
	 */
	public function setDelim( $delim = ',' )
	{
		$this->_srcFileListDelimetr = $delim;
	}

	public function setType( $type )
	{
		$this->_fileType = $type;
	}

	/**
	 *
	 * @param field_type $_srcFileList
	 */
	public function setSrcFileList( $srcFileList )
	{
		$this->_srcFileList = $srcFileList;
	}

	/**
	 *
	 * @param field_type $_dstFilename
	 */
	public function setDstFile( $dstFile )
	{
		$this->_dstFile = $dstFile;
	}

	/**
	 * The init method: Do init steps.
	 */
	public function init()
	{

	}

	public function main()
	{
		$this->_initSrcFileListArr();
		$srcFileList = $this->_srcFileListArr;

		if ( empty( $srcFileList ) )
		{
			throw new BuildException( "Missing attribute 'srcFileList'" );
		}
		if ( empty( $this->_dstFile ) )
		{
			throw new BuildException( "Missing attribute 'dstFile'" );
		}
		$cnt = count( $srcFileList );
		if ( $cnt < 2 )
		{
			throw new BuildException( "Invalid attribute 'srcFileList': must be more 1 src file" );
		}

		$this->_doWork();
	}

	private function _doWork()
	{
		$srcFileList = $this->_srcFileListArr;
		$cnt = count( $srcFileList );

		$msg = sprintf( "Start xmlmerge files: \n\t - %s", implode( "\n\t - ", $srcFileList ) );
		$this->log( $msg );

		$result = '';
		$mergeData = array();
		for( $i = 0; $i < $cnt; $i++ )
		{
			if ( empty( $srcFileList[ $i ] ) )
			{
				continue;
			}
			$srcFile = $this->_geFullPath( $srcFileList[ $i ] );
			$contents = file_get_contents( $srcFile );

			try
			{
				$data = Converter::xml2array( $contents );
				$mergeData = Tools::arrayMergeRecursive(  $mergeData, $data );
			}
			catch ( BuildException $e )
			{
				$message = $e->getMessage() . ' File: ' . $srcFile;
				throw new BuildException( $message );
			}
		}

		$dstFile = $this->_geFullPath( $this->_dstFile );
		$this->_save( $mergeData, $dstFile );

		$msg = sprintf( "Complete xmlmerge\nSaved (%s) into file: (%s)", count( $result ), $dstFile );
		$this->log( $msg );
	}

	private function _save( $data, $filename )
	{
		$result = '';
		switch ( $this->_fileType )
		{
			case self::TYPE_PHP:
				$result = $this->_saveToPhp( $data, $filename );
				break;
			case self::TYPE_XML:
				$result = $this->_saveToXml( $data, $filename );
				break;
		}
		return $result;
	}

	private function _saveToPhp( $data, $filename )
	{
		$content = sprintf( '<?php return %s;', var_export( $data, true ) );
		$result = file_put_contents( $filename, $content );
		return $result;
	}

	private function _saveToXml( $data, $filename )
	{
		$content = Converter::array2xml( $data );
		$result = file_put_contents( $filename, $content );
		return $result;
	}

	private function _geFullPath( $filename )
	{
		$file = new PhingFile( $filename );
		if ( !$file->isAbsolute() )
		{
			$file = new PhingFile( $this->project->getBasedir(), $filename );
		}
		$result = $file->getPath();
		return $result;
	}

	private function _initSrcFileListArr()
	{
		$srcFileList = $this->_srcFileList;
		$srcFileList = explode( $this->_srcFileListDelimetr, $srcFileList );
		$srcFileList = array_map( 'trim', $srcFileList );
		foreach ( $srcFileList as $filename )
		{
			$this->_addFile( $this->_geFullPath( $filename ) );
		}

		$project = $this->getProject();

		// process filesets
		foreach ( $this->filesets as $fs )
		{
			$ds = $fs->getDirectoryScanner( $project );
			$fromDir = $fs->getDir( $project );
			$srcFiles = $ds->getIncludedFiles();

			foreach ( $srcFiles as $filename )
			{
				$this->_addFile( $fromDir->getAbsolutePath() . '/' . $filename );
			}
		}

		// process filelists
		foreach ( $this->filelists as $fl )
		{
			$fromDir = $fl->getDir( $project );

			$srcFiles = $fl->getFiles( $project );
			$srcDirs = $fl->getDir( $project );

			foreach ( $srcFiles as $filename )
			{
				$this->_addFile( $srcDirs->getAbsolutePath() . '/' . $filename );
			}
		}
	}

	private function _addFile( $filename )
	{
		if ( !in_array( $filename, $this->_srcFileListArr ) && is_file( $filename ) )
		{
			$this->_srcFileListArr[] = $filename;
		}
	}
}