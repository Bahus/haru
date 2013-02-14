<?php
/**
 *
 * Работа с локальным статусом развернутых версий
 * <li>Хранение текщих выкаченных версий
 * <li>Удаление старых версий
 * @author gfilippov@rbc.ru
 *
 */
class ReleaseHistory
{
	protected $_filename = '';
	protected $_config;

	public function __construct( SimpleXMLElement $config )
	{
		$this->_config = $config;
		$this->_filename = $config->release_history->local->file;
	}

	public function getFileName()
	{
		return $this->_filename;
	}

	public function save()
	{
		$data = $this->load();
		foreach ( $this->_config->libs->children() as $lib )
		{
			if ( $lib->deploy->tag && $lib->deploy->type != 'none' )
			{
				$libName = strval( $lib->getName() );
				$tag = strval( $lib->deploy->tag );
				$dst = strval( $lib->deploy->dst );

				if ( !isset( $data[ $libName ] ) )
				{
					$data[ $libName ] = array();
				}

				if ( !isset( $data[ $libName ][ $tag ] ) )
				{
					$data[ $libName ][ $tag ] = array(
						'first' => strval( time() ),
						'tag' => $tag,
						'dst' => $dst );
					// ,'linked' => true
				}

				$data[ $libName ][ $tag ][ 'last' ] = strval( time() );
			}
		}

		$res = $this->_save( $data );

		return $res;
	}

	protected function _save( $data )
	{
		$fileName = $this->getFileName();
		$res = file_put_contents( $fileName, json_encode( $data ) );
		chmod( $fileName, 0644 );
		return $res;
	}

	public function load()
	{
		$filename = $this->getFileName();
		$data = file_exists( $filename ) ? file_get_contents( $filename ) : '';

		$data = json_decode( $data, true );

		if ( is_null( $data ) )
		{
			$data = array();
		}
		else
		{
			foreach ( $data as $libname => &$items )
			{
				uasort( $items, array(
					$this,
					'_cmp' ) );
			}
		}

		return $data;
	}

	public function deleteOldItems()
	{
		$items = $this->load();
		$itemsForDelete = array();

		foreach ( $this->_config->libs->children() as $lib )
		{
			$deployType = empty( $lib->deploy->type ) ? 'none' : strval( $lib->deploy->type );
			$keepOldVersions = empty( $lib->deploy->keep_old_versions ) ? 0 : intval( $lib->deploy->keep_old_versions );

			if ( $deployType == 'none' || $keepOldVersions < 2 )
			{
				continue;
			}

			$this->_delete( $lib, $items, $keepOldVersions );
		}

		$this->_save( $items );
	}

	private function _delete( SimpleXMLElement $lib, &$items, $keepOldVersions )
	{
		$res = array();

		$libName = strval( $lib->getName() );
		$tmp = is_array( $items[ $libName ] ) ? $items[ $libName ] : array();
		$tmp = array_slice( $tmp, $keepOldVersions );

		$deleteHelper = new ExecTask();
		$deleteHelper->setProject( $this->_project );
		$deleteHelper->setLogoutput = true;
		$deleteHelper->setCheckreturn = true;

		foreach ( $tmp as $deletedItem )
		{
			$dst = $deletedItem[ 'dst' ];
			$tag = $deletedItem[ 'tag' ];

			if ( $dst != $lib->deploy->dst )
			{
				if ( strpos( Phing::getProperty( 'host.fstype' ), 'WIN' ) === 0 )
				{
					$deleteHelper->setCommand( 'rmdir /S /Q ' . escapeshellarg( $dst ) );
				}
				else
				{
					$deleteHelper->setCommand( 'rm -r ' . escapeshellarg( $dst ) );
				}
				$deleteHelper->main();
				unset( $items[ $libName ][ $tag ] );
			}
		}

		unset( $deleteHelper );
		return $res;
	}

	protected function _cmp( $b, $a )
	{
		$al = $a[ 'last' ];
		$bl = $b[ 'last' ];
		$res = $al > $bl ? 1 : ( $al == $bl ? 0 : -1 );
		return $res;
	}
}