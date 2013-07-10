<?php
require_once 'phing/Task.php';
require_once 'phing/BuildException.php';

/**
 * Delete damaged symlinks
 */
class SymlinkCleanerTask extends Task
{
    private $_dir;

    /**
	 * Working directory
	 *
     * @param string $dir
     */
    public function setDir( $dir )
    {
        $this->_dir = $dir;
    }

    public function init()
    {
    }

    public function main()
    {
        $dir = $this->_dir;
        if ( empty( $dir ) )
        {
            $msg = sprintf( 'Invalid param "dir", must be not empty' );
            throw new BuildException( $msg );
        }
        $msg = sprintf( 'Start cleaner task for "%s"', $dir );
        $this->log( $msg );

        $skipList = array( '.svn' );
        $list = Tools::dirList( $dir, $skipList );
        foreach ( $list as $item )
        {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if ( is_link( $path ) )
            {
                $realFile = readlink( $path );
                if ( !file_exists( $realFile ) )
                {
                    $msg = sprintf(
                        'Delete broke symlink "%s" pointing to "%s"', $path,
                        $realFile );
                    $this->log( $msg );
                    unlink( $path );
                }
            }
        }
    }
}