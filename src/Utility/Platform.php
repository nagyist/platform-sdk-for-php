<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace DreamFactory\Platform\Utility;

use DreamFactory\Platform\Enums\LocalStorageTypes;
use DreamFactory\Platform\Services\SystemManager;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Exceptions\FileSystemException;
use Kisma\Core\SeedUtility;
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Option;

/**
 * Platform
 * System constants and generic platform helpers
 */
class Platform extends SeedUtility
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @var string The name of the storage container that stores applications
     */
    const APP_STORAGE_CONTAINER = 'applications';

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Constructs a virtual platform path
     *
     * @param string $type            The type of path, used as a key into config
     * @param string $append
     * @param bool   $createIfMissing If true and final directory does not exist, it is created.
     *
     * @throws \InvalidArgumentException
     * @throws \Kisma\Core\Exceptions\FileSystemException
     * @return string
     */
    protected static function _getPlatformPath( $type, $append = null, $createIfMissing = true )
    {
        static $_cache = array();

        $_appendage = ( $append ? '/' . ltrim( $append, '/' ) : null );

        if ( !LocalStorageTypes::contains( $_tag = Inflector::neutralize( $type ) ) )
        {
            throw new \InvalidArgumentException( 'Type "' . $type . '" is invalid.' );
        }

        //	Make a cache tag that includes the requested path...
        $_cacheTag = $_tag . '/' . Inflector::neutralize( $_appendage );

        if ( null === ( $_path = Option::get( $_cache, $_cacheTag ) ) )
        {
            $_path = trim( Pii::getParam( $_tag ) );

            if ( empty( $_path ) )
            {
                $_path = \Kisma::get( 'app.project_root' ) . '/storage';
                Log::notice( 'Empty path for platform path type "' . $type . '". Defaulting to "' . $_path . '"' );
            }

            $_checkPath = dirname( $_path . $_appendage );

            if ( true === $createIfMissing && !is_dir( $_checkPath ) )
            {
                if ( false === @\mkdir( $_checkPath, 0777, true ) )
                {
                    throw new FileSystemException( 'File system error creating directory: ' . $_checkPath );
                }
            }

            $_path .= $_appendage;

            //	Store path for next time...
            Option::set( $_cache, $_cacheTag, $_path );
        }

        return $_path;
    }

    /**
     * Constructs the virtual storage path
     *
     * @param string $append
     *
     * @return string
     */
    public static function getStorageBasePath( $append = null )
    {
        return static::_getPlatformPath( LocalStorageTypes::STORAGE_BASE_PATH, $append );
    }

    /**
     * Constructs the virtual storage path
     *
     * @param string $append
     *
     * @return string
     */
    public static function getStoragePath( $append = null )
    {
        return static::_getPlatformPath( LocalStorageTypes::STORAGE_PATH, $append );
    }

    /**
     * Constructs the virtual private path
     *
     * @param string $append
     *
     * @return string
     */
    public static function getPrivatePath( $append = null )
    {
        return static::_getPlatformPath( LocalStorageTypes::PRIVATE_PATH, $append );
    }

    /**
     * Returns the library configuration path, not the platform's config path in the root
     *
     * @param string $append
     *
     * @return string
     */
    public static function getLibraryConfigPath( $append = null )
    {
        return SystemManager::getConfigPath() . ( $append ? '/' . ltrim( $append, '/' ) : null );
    }

    /**
     * Returns the library template configuration path, not the platform's config path in the root
     *
     * @param string $append
     *
     * @return string
     */
    public static function getLibraryTemplatePath( $append = null )
    {
        return static::getLibraryConfigPath( '/templates' ) . ( $append ? '/' . ltrim( $append, '/' ) : null );
    }

    /**
     * Returns the platform configuration path, in the root
     *
     * @param string $append
     *
     * @return string
     */
    public static function getPlatformConfigPath( $append = null )
    {
        return Pii::getPathOfAlias( 'application.config' ) . ( $append ? '/' . ltrim( $append, '/' ) : null );
    }

    /**
     * Constructs the virtual private path
     *
     * @param string $append
     *
     * @return string
     */
    public static function getSnapshotPath( $append = null )
    {
        return static::_getPlatformPath( LocalStorageTypes::SNAPSHOT_PATH, $append );
    }

    /**
     * Constructs the virtual swagger path
     *
     * @param string $append
     *
     * @return string
     */
    public static function getSwaggerPath( $append = null )
    {
        return static::_getPlatformPath( LocalStorageTypes::SWAGGER_PATH, $append );
    }

    /**
     * Constructs the virtual plugins path
     *
     * @param string $append
     *
     * @return string
     */
    public static function getPluginsPath( $append = null )
    {
        return static::_getPlatformPath( LocalStorageTypes::PLUGINS_PATH, $append );
    }

    /**
     * Constructs the virtual private path
     *
     * @param string $append
     *
     * @return string
     */
    public static function getApplicationsPath( $append = null )
    {
        return static::_getPlatformPath( LocalStorageTypes::APPLICATIONS_PATH, $append );
    }

    /**
     * @param string $namespace
     *
     * @return string
     */
    public static function uuid( $namespace = null )
    {
        static $_uuid = null;

        $_hash = strtoupper(
            hash(
                'ripemd128',
                uniqid( '', true ) . ( $_uuid ? : microtime( true ) ) . md5(
                    $namespace .
                    $_SERVER['REQUEST_TIME'] .
                    $_SERVER['HTTP_USER_AGENT'] .
                    $_SERVER['LOCAL_ADDR'] .
                    $_SERVER['LOCAL_PORT'] .
                    $_SERVER['REMOTE_ADDR'] .
                    $_SERVER['REMOTE_PORT']
                )
            )
        );

        $_uuid =
            '{' .
            substr( $_hash, 0, 8 ) .
            '-' .
            substr( $_hash, 8, 4 ) .
            '-' .
            substr( $_hash, 12, 4 ) .
            '-' .
            substr( $_hash, 16, 4 ) .
            '-' .
            substr( $_hash, 20, 12 ) .
            '}';

        return $_uuid;
    }

    /**
     * Attempts to require one or more autoload files.
     * fUseful for DSP apps written in PHP.
     *
     * @param array $autoloaders
     *
     * @return mixed|bool
     */
    public static function registerAutoloaders( $autoloaders = array() )
    {
        foreach ( Option::clean( $autoloaders ) as $_file )
        {
            if ( file_exists( $_file ) )
            {
                /** @noinspection PhpIncludeInspection */
                return require_once $_file;
            }
        }

        return false;
    }
}