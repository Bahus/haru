<?php
require_once 'phing/BuildException.php';
require_once 'phing/util/Converter.php';
/**
 * Берем главную секцию, сохраняем
 * Находим библиотеки (libs), анализируя "главную" секцию
 * Пробегаем по модулям библиотеки, сохраняем конфиги, если модуля нет Exception
 *
 * @author vpak
 *
 */
class Configure_Slice
{
    const INCLUDES_SECTION_NAME = 'secret_lib_includes';

    private $_rootTagName = 'config';
    private $_configDirModulesName = 'modules';
    private $_configDirName = 'data';
    private $_fileBasename = 'config';
    private $_xmlObj = null;
    private $_xmlMainConfig;

    public function __construct( $xmlPhpPropertiesFilename )
    {
        assert( is_string( $xmlPhpPropertiesFilename ) );

        $xml = simplexml_load_file( $xmlPhpPropertiesFilename );

        if ( !$xml || !$xml->libs )
        {
            throw new BuildException( 'Invalid config (no build or no build->libs sections: ' . $xmlPhpPropertiesFilename . ')' );
        }

        $this->_xmlObj = $xml;

        $config = clone $xml;
        $libDir = ( string ) $config->paths->root;
        unset( $config->libs );
        $this->_xmlMainConfig = $config;
    }

    public function run()
    {
        $result = $this->_runLibs();
        return $result;
    }

    protected function _runLibs()
    {
        $xml = $this->_xmlObj;

        $result = array();
        $libsList = self::getLibPathList( $xml->libs );

        foreach ( $libsList as $libName => $libDir )
        {
            $configFilenameList = $this->_getConfigFilenameList( $libDir, $libName );
            $xmlConfigFilenameList = simplexml_load_string( Converter::array2xml( $configFilenameList ) );
            foreach ( $xml->libs->children() as $lib )
            {
                $subLibName = $lib->getName();
                $filename = $this->_getConfigFilename( $libName, $subLibName );
                $config = $this->_getModules( $lib );
                if ( $config )
                {
                    $this->_sxmlAppend( $config, $xmlConfigFilenameList );
                    $result[ $subLibName ] = $this->_generateSlice( $libDir . '/data', $config, $filename );
                }
            }
            $result[ $libName . '_main' ] = $this->_generateSlice( $libDir . '/data', $this->_xmlMainConfig, 'config_main' );
        }
        return $result;
    }

    protected function _getConfigFilenameList( $libDir, $libName )
    {
        $xml = $this->_xmlObj;
        $result = array();
        foreach ( $xml->libs->children() as $lib )
        {
            $subLibName = $lib->getName();
            $filename = $this->_getConfigFilename( $libName, $subLibName );

            $result[ 'php' ][ $subLibName ] = $libDir . '/data/' . $filename . '.php';
            $result[ 'xml' ][ $subLibName ] = $libDir . '/data/' . $filename . '.xml';
        }
        $result = array( self::INCLUDES_SECTION_NAME => $result );
        return $result;
    }

    protected function _getConfigFilename( $libName, $subLibName )
    {
        $filename = 'config';
        if ( $libName != $subLibName )
        {
            $filename .= '_' . strtolower( $subLibName );
        }
        return $filename;
    }

    protected function _getModules( $lib )
    {
        $config = clone $lib;
        unset( $config->configure );
        unset( $config->link );
        if ( $config->modules )
        {
            $modules = clone $config->modules;
            unset( $config->modules );
            foreach ( ( array ) $modules->children() as $node )
            {
                $this->_sxmlAppend( $config, $node );
            }
        }
        else
        {
            $config = null;
        }
        return $config;
    }

    protected function _sxmlAppend( SimpleXMLElement $to, SimpleXMLElement $from )
    {
        $toDom = dom_import_simplexml( $to );
        $fromDom = dom_import_simplexml( $from );
        $toDom->appendChild( $toDom->ownerDocument->importNode( $fromDom, true ) );
    }

    protected function _xmlAdopt( $root, $new )
    {
        $node = $root->addChild( $new->getName(), ( string ) $new );
        foreach ( $new->attributes() as $attr => $value )
        {
            $node->addAttribute( $attr, $value );
        }
        foreach ( $new->children() as $ch )
        {
            $this->_xmlAdopt( $node, $ch );
        }
    }

    protected function _generateSlice( $dstDir, $xml, $fileBasename = '' )
    {
        if ( empty( $fileBasename ) )
        {
            $fileBasename = $this->_fileBasename;
        }

        $phpFileName = sprintf( '%s/%s.php', $dstDir, $fileBasename );
        $xmlFileName = sprintf( '%s/%s.xml', $dstDir, $fileBasename );

        if ( !file_exists( $dstDir ) )
        {
            mkdir( $dstDir, 0777, true );
        }

        $xmlString = $xml->asXml();
        $resPhpSave = $this->_saveToPhp( $phpFileName, $xmlString );

        $xmlString = '<?xml version="1.0" encoding="UTF-8"?>' . chr( 10 ) . $xmlString;
        $resXmlSave = file_put_contents( $xmlFileName, $xmlString );

        return array( $phpFileName, $xmlFileName );
    }

    protected function _saveToPhp( $filename, $xmlString )
    {
        $data = Converter::xml2array( $xmlString );
        $content = sprintf( '<?php return %s;', var_export( $data, true ) );
        $result = file_put_contents( $filename, $content );
        return $result;
    }

    public static function getLibPathList( $xml )
    {
        $libsList = array();
        foreach ( ( array ) $xml->children() as $lib )
        {
            $libName = $lib->getName();
            $libDstDir = strval( $lib->deploy->dst );

            if ( $libName && $libDstDir )
            {
                $libsList[ $libName ] = $libDstDir;
            }
        }

        return $libsList;
    }
}