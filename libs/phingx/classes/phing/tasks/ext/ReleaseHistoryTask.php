<?php
require_once 'phing/Task.php';
require_once 'phing/BuildException.php';
require_once "phing/util/ReleaseHistory.php";

/**
 * Update one or all lib items
 */
class ReleaseHistoryTask extends Task
{
	private $_config;

	protected $_bt;
	protected $_type = '';

	/**
	 *
	 * @param field_type $configXml
	 */
	public function setConfig( $configXmlFilename )
	{
		$this->_config = $this->_initConfig( $configXmlFilename );
	}

	public function setType( $type )
	{
		$this->_type = strval( $type );
	}

	public function main()
	{

		$this->_bt = $this->project->getProperty( 'build.type' );
		$type = $this->_type;

		if ( !$type )
		{
			foreach ( $this->_config->release_history->children() as $item )
			{
				$this->_runItem( $item );
			}

			$this->log( 'Delete old items...' );
			$h = new ReleaseHistory( $this->_config, $this->getProject() );
			$h->deleteOldItems();
			$this->log( 'Done.' );
		}
		// оставлено на случай необходимости вставки в кастомные таргеты с
		// выполнением не всех действий (н-р, только отправка письма)
		else
		{
			$item = $this->_config->release_history->$type;
			if ( $item )
			{
				$this->_runItem( $item );
			}
		}
	}

	protected function _initConfig( $configXmlFilename )
	{
		$xml = simplexml_load_file( $configXmlFilename );
		if ( !$xml || !$xml->libs )
		{
			$msg = sprintf( 'Invalid config structure %s', $configXmlFilename );

			throw new BuildException( $msg );
		}

		return $xml;
	}

	protected function _runItem( SimpleXMLElement $item )
	{
		$typeName = strval( $item->getName() );
		$this->log( 'Save|notify via ' . $typeName );

		$method = '_' . $typeName;
		if ( method_exists( $this, $method ) )
		{
			$res = $this->$method( $item );
			$this->log( 'Save|notify ' . ( $res ? 'completed successful' : 'failed' ), ( $res ? PROJECT::MSG_INFO : PROJECT::MSG_WARN ) );
		}
		else
		{
			$this->log( 'Save|notify method is not implemented' );
		}
	}

	protected function _mailByUrl( SimpleXMLElement $config )
	{
		$res = false;

		$url = strval( $config->url );
		$projectId = strval( $config->project_id );
		$user = $this->_getUser();

		$delimiter = false === strpos( $url, '?' ) ? '?' : '&';

		if ( $url && $projectId && $this->_bt )
		{

			$url = sprintf( '%s%sproject=%s&action=%s&user=%s', $url, $delimiter, $projectId, $this->_bt, $user );
			$logMessage = 'Send mail via url ' . $url;
			$this->log( $logMessage );

			$res = json_decode( file_get_contents( $url ), true );
			$res = !empty( $res[ 'result' ] ) && $res[ 'result' ] == '1';
		}
		else
		{
			$this->log( 'mailByUrl params are not full' );
		}

		return $res;
	}

	protected function _getUser()
	{
		$user = $this->getProject()->getProperty( 'env.SUDO_USER' );
		if ( empty( $user ) )
		{
			$user = $this->getProject()->getProperty( 'env.USER' );
		}
		return $user;
	}

	protected function _local( SimpleXMLElement $el )
	{
		$this->log( 'Save history in ' . strval( $el->file ) );

		$h = new ReleaseHistory( $this->_config, $this->getProject() );
		$res = $h->save();

		return $res;
	}
}