<?php
require_once 'phing/BuildException.php';
require_once 'phing/util/Converter.php';
class Tools
{

    static public function arrayMergeRecursive( $paArray1, $paArray2 )
    {
        if ( !is_array( $paArray1 ) && !is_array( $paArray2 ) )
        {
            return $paArray2;
        }
        if ( is_array( $paArray2 ) )
        {
            foreach ( $paArray2 as $sKey2 => $sValue2 )
            {
                $sValue1 = isset( $paArray1[ $sKey2 ] ) ? $paArray1[ $sKey2 ] : null;

                if ( !is_array( $sValue1 ) )
                {
                    $paArray1[ $sKey2 ] = $sValue2;
                }
                else
                {
                    $paArray1[ $sKey2 ] = self::arrayMergeRecursive( $sValue1,
                        $sValue2 );
                }
            }
        }
        return $paArray1;
    }

    static public function rmdir( $dir )
    {
        if ( is_link( $dir ) )
        {
            return unlink( $dir );
        }

        if ( !file_exists( $dir ) )
        {
            return true;
        }
        if ( !is_dir( $dir ) )
        {
            return unlink( $dir );
        }
        foreach ( scandir( $dir ) as $item )
        {
            if ( $item == '.' || $item == '..' )
            {
                continue;
            }
            if ( !self::rmdir( $dir . DIRECTORY_SEPARATOR . $item ) )
            {
                return false;
            }
        }
        return rmdir( $dir );
    }

    static public function dirList( $dir, array $skipList = array() )
    {
        $result = array();
        if ( file_exists( $dir ) && is_dir( $dir ) )
        {
            foreach ( scandir( $dir ) as $item )
            {
                if ( $item == '.' || $item == '..' || in_array( $item,
                    $skipList ) )
                    continue;
                $result[] = $item;
            }
        }
        return $result;
    }

    static public function loadConfig( $configFilename, $type = 'xml' )
    {
        switch ( $type )
        {
            case 'php':
                $data = include $configFilename;
                break;
            case 'xml':
                $contents = file_get_contents( $configFilename );
                $data = Converter::xml2array( $contents );
                break;
            default:
                $message = 'Ivalid type';
                throw new BuildException( $message );
        }
        return $data;
    }

    static public function saveToPhp( $filename, $data )
    {
        $content = sprintf( '<?php return %s;', var_export( $data, true ) );
        $result = file_put_contents( $filename, $content );
        return $result;
    }

    static public function saveToXml( $filename, $data )
    {
        $content = Converter::array2xml( $data );
        $result = file_put_contents( $filename, $content );
        return $result;
    }
}