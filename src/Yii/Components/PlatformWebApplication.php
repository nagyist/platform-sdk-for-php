<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
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
namespace DreamFactory\Platform\Yii\Components;

use Composer\Autoload\ClassLoader;
use DreamFactory\Platform\Components\Profiler;
use DreamFactory\Platform\Events\DspEvent;
use DreamFactory\Platform\Events\Enums\DspEvents;
use DreamFactory\Platform\Events\EventDispatcher;
use DreamFactory\Platform\Events\PlatformEvent;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Utility\CorsManager;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\CoreSettings;
use Kisma\Core\Enums\HttpMethod;
use Kisma\Core\Enums\HttpResponse;
use Kisma\Core\Interfaces\PublisherLike;
use Kisma\Core\Interfaces\SubscriberLike;
use Kisma\Core\Utility\Log;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PlatformWebApplication
 *
 * @property callable onEndRequest
 * @property callable onBeginRequest
 */
class PlatformWebApplication extends \CWebApplication implements PublisherLike, SubscriberLike
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @var string The default DSP resource namespace
     */
    const DEFAULT_SERVICE_NAMESPACE_ROOT = 'DreamFactory\\Platform\\Services';
    /**
     * @var string The default DSP resource namespace
     */
    const DEFAULT_RESOURCE_NAMESPACE_ROOT = 'DreamFactory\\Platform\\Resources';
    /**
     * @var string The default DSP model namespace
     */
    const DEFAULT_MODEL_NAMESPACE_ROOT = 'DreamFactory\\Platform\\Yii\\Models';
    /**
     * @var string The default path (sub-path) of installed plug-ins
     */
    const DEFAULT_PLUGINS_PATH = '/storage/plugins';
    /**
     * @const int The services namespace map index
     */
    const NS_SERVICES = 0;
    /**
     * @const int The resources namespace map index
     */
    const NS_RESOURCES = 1;
    /**
     * @const int The models namespace map index
     */
    const NS_MODELS = 2;

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var EventDispatcher
     */
    protected static $_dispatcher;
    /**
     * @var bool If true, profiling information is output to the log
     */
    protected static $_enableProfiler = false;
    /**
     * @var array[] The namespaces in use by this system. Used by the routing engine
     */
    protected static $_namespaceMap = array( self::NS_MODELS => array(), self::NS_SERVICES => array(), self::NS_RESOURCES => array() );
    /**
     * @var array The namespaces that contain resources. Used by the routing engine
     */
    protected $_resourceNamespaces = array();
    /**
     * @var array The namespaces that contain models. Used by the resource manager
     */
    protected $_modelNamespaces = array();
    /**
     * @var Request
     */
    protected $_requestObject;
    /**
     * @var  Response
     */
    protected $_responseObject;
    /**
     * @var bool If true, headers will be added to the response object instance of this run
     */
    protected $_useResponseObject = false;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Initialize
     */
    protected function init()
    {
        parent::init();

        CorsManager::initialize( $this->getResponseObject() );

        //	Debug options
        static::$_enableProfiler = Pii::getParam( 'dsp.enable_profiler', false );

        //	Setup the request handler and events
        $this->onBeginRequest = array( $this, '_onBeginRequest' );
        $this->onEndRequest = array( $this, '_onEndRequest' );
    }

    /**
     * Start a profiler
     *
     * @param string $id The id of the profiler
     *
     * @return $this
     */
    public function startProfiler( $id = __CLASS__ )
    {
        if ( static::$_enableProfiler )
        {
            Profiler::start( $id );
        }

        return $this;
    }

    /**
     * Stop a profiler
     *
     * @param string $id The id of the profiler
     * @param bool   $prettyPrint
     *
     * @throws \InvalidArgumentException
     * @return $this
     */
    public function stopProfiler( $id = __CLASS__, $prettyPrint = true )
    {
        if ( static::$_enableProfiler )
        {
            Log::debug( '~~ "' . $id . '" profile: ' . Profiler::stop( 'app.request', $prettyPrint ) );
        }

        return $this;
    }

    /**
     * Conditionally adds CORS headers to response
     *
     * @param array $whitelist
     * @param bool  $returnHeaders
     * @param bool  $sendHeaders
     *
     * @throws \DreamFactory\Platform\Utility\RestException
     */
    public function addCorsHeaders( $whitelist = array(), $returnHeaders = false, $sendHeaders = true )
    {
        CorsManager::autoSendHeaders( $whitelist, $returnHeaders, $sendHeaders );
    }

    /**
     * Loads up any plug-ins configured
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     * @return bool
     */
    protected function _loadPlugins()
    {
        if ( null === ( $_autoloadPath = \Kisma::get( 'dsp.plugin_autoload_path' ) ) )
        {
            //	Locate plug-in directory...
            $_path = Pii::getParam( 'dsp.plugins_path', Pii::getParam( 'dsp.base_path' ) . static::DEFAULT_PLUGINS_PATH );

            if ( !is_dir( $_path ) )
            {
                // No plug-ins installed

                return false;
            }

            if ( file_exists( $_path . '/autoload.php' ) && is_readable( $_path . '/autoload.php' ) )
            {
                $_autoloadPath = $_path . '/autoload.php';
                Log::debug( 'Found plug-in autoload.php' );
            }
            else
            {
                Log::debug( 'No autoload.php file found for installed plug-ins.' );

                return false;
            }

            \Kisma::set( 'dsp.plugin_autoload_path', $_autoloadPath );
        }

        /** @noinspection PhpIncludeInspection */
        if ( false === @require( $_autoloadPath ) )
        {
            Log::error( 'Error reading plug-in autoload.php file. Some plug-ins may not function properly.' );

            return false;
        }

        $this->trigger( DspEvents::PLUGINS_LOADED );

        return true;
    }

    //*************************************************************************
    //	Event Handlers
    //*************************************************************************

    /**
     * Handles an OPTIONS request to the server to allow CORS and optionally sends the CORS headers
     *
     * @param \CEvent $event
     *
     * @throws \UnexpectedValueException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \LogicException
     * @throws \InvalidArgumentException
     * @throws \DreamFactory\Platform\Utility\RestException
     * @return bool
     */
    protected function _onBeginRequest( \CEvent $event )
    {
        //	Start the request-only profile
        $this->startProfiler( 'app.request' );

        $this->_requestObject = Request::createFromGlobals();

        //  Call getter to get CORS headers auto-added
        $_response = $this->_useResponseObject ? $this->getResponseObject() : null;

        switch ( $this->_requestObject->getMethod() )
        {
            case HttpMethod::TRACE:
                Log::error(
                    'HTTP TRACE received!',
                    array(
                        'server'  => $_SERVER,
                        'request' => $_REQUEST,
                    )
                );

                throw new BadRequestException();

            case HttpMethod::OPTIONS:
                if ( $this->_useResponseObject )
                {
                    $_response->setStatusCode( HttpResponse::NoContent )->setContent( '' )->headers->set( 'Content-Type', 'text/plain', true );
                    $_response->send();
                }
                else
                {
                    header( 'HTTP/1.1 204' );
                    header( 'content-length: 0' );
                    header( 'content-type: text/plain' );

                    $this->_useResponseObject = false;
                    CorsManager::autoSendHeaders();
                }

                return Pii::end();
        }

        //	Auto-add the CORS headers...
        CorsManager::autoSendHeaders();

        //	Load any plug-ins
        $this->_loadPlugins();
    }

    /**
     * @param \CEvent $event
     */
    protected function _onEndRequest( \CEvent $event )
    {
        $this->stopProfiler( 'app.request' );
    }

    //*************************************************************************
    //  Server-Side Event Support
    //*************************************************************************

    /**
     * Triggers an event
     *
     * @param string        $eventName
     * @param PlatformEvent $event
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \Exception
     * @return DspEvent
     */
    public function trigger( $eventName, $event = null )
    {
        return static::getDispatcher()->dispatch( $eventName, $event );
    }

    /**
     * Adds an event listener that listens on the specified events.
     *
     * @param string   $eventName            The event to listen on
     * @param callable $listener             The listener
     * @param integer  $priority             The higher this value, the earlier an event
     *                                       listener will be triggered in the chain (defaults to 0)
     *
     * @return void
     */
    public function on( $eventName, $listener, $priority = 0 )
    {
        static::getDispatcher()->addListener( $eventName, $listener, $priority );
    }

    /**
     * Turn off/unbind/remove $listener from an event
     *
     * @param string   $eventName
     * @param callable $listener
     *
     * @return void
     */
    public function off( $eventName, $listener )
    {
        static::getDispatcher()->removeListener( $eventName, $listener );
    }

    /**
     * @param array $corsWhitelist
     *
     * @throws \DreamFactory\Platform\Utility\RestException
     * @return PlatformWebApplication
     */
    public function setCorsWhitelist( $corsWhitelist )
    {
        CorsManager::setCorsWhitelist( $corsWhitelist );

        return $this;
    }

    /**
     * @return array
     */
    public function getCorsWhitelist()
    {
        return CorsManager::getCorsWhitelist();
    }

    /**
     * @param boolean $autoAddHeaders
     *
     * @return PlatformWebApplication
     */
    public function setAutoAddHeaders( $autoAddHeaders = true )
    {
        CorsManager::setAutoAddHeaders( $autoAddHeaders );

        return $this;
    }

    /**
     * @return boolean
     */
    public function getAutoAddHeaders()
    {
        return CorsManager::getAutoAddHeaders();
    }

    /**
     * @param boolean $extendedHeaders
     *
     * @return PlatformWebApplication
     */
    public function setExtendedHeaders( $extendedHeaders = true )
    {
        CorsManager::setExtendedHeaders( $extendedHeaders );

        return $this;
    }

    /**
     * @return boolean
     */
    public function getExtendedHeaders()
    {
        return CorsManager::getExtendedHeaders();
    }

    /**
     * @param bool $createIfNull If true, the default, the response object will be created if it hasn't already
     *
     * @throws \DreamFactory\Platform\Utility\RestException
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getResponseObject( $createIfNull = true )
    {
        if ( null === $this->_responseObject && $createIfNull )
        {
            $this->_responseObject = Response::create();

            CorsManager::autoSendHeaders();
        }

        return $this->_responseObject;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Response $responseObject
     *
     * @return PlatformWebApplication
     */
    public function setResponseObject( $responseObject )
    {
        $this->_responseObject = $responseObject;

        return $this;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $requestObject
     *
     * @return PlatformWebApplication
     */
    public function setRequestObject( $requestObject )
    {
        $this->_requestObject = $requestObject;

        return $this;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Request
     */
    public function getRequestObject()
    {
        return $this->_requestObject;
    }

    /**
     * @param boolean $enableProfiler
     */
    public static function setEnableProfiler( $enableProfiler )
    {
        static::$_enableProfiler = $enableProfiler;
    }

    /**
     * @return boolean
     */
    public static function getEnableProfiler()
    {
        return static::$_enableProfiler;
    }

    /**
     * @param array $resourceNamespaces
     *
     * @return PlatformWebApplication
     */
    public function setResourceNamespaces( $resourceNamespaces )
    {
        static::$_namespaceMap[static::NS_RESOURCES] = $resourceNamespaces;

        return $this;
    }

    /**
     * @return array
     */
    public function getResourceNamespaces()
    {
        return static::$_namespaceMap[static::NS_RESOURCES];
    }

    /**
     * @param string $namespace
     * @param string $path
     * @param bool   $prepend If true, the namespace(s) will be placed at the beginning of the list
     *
     * @return PlatformWebApplication
     */
    public function addResourceNamespace( $namespace, $path, $prepend = false )
    {
        static::_mapNamespace( static::NS_RESOURCES, $namespace, $path, $prepend );
        array_unshift( $this->_modelNamespaces, $_entry );

        return $this;
    }

    /**
     * @param array $modelNamespaces
     *
     * @return PlatformWebApplication
     */
    public function setModelNamespaces( $modelNamespaces )
    {
        static::$_namespaceMap[static::NS_MODELS] = $modelNamespaces;

        return $this;
    }

    /**
     * @return array
     */
    public function getModelNamespaces()
    {
        return static::$_namespaceMap[static::NS_MODELS];
    }

    /**
     * @param string $namespace
     * @param string $path
     * @param bool   $prepend If true, the namespace(s) will be placed at the beginning of the list
     *
     * @return PlatformWebApplication
     */
    public function addModelNamespace( $namespace, $path, $prepend = false )
    {
        static::_mapNamespace( static::NS_MODELS, $namespace, $path, $prepend );

        return $this;
    }

    /**
     * @param int $which Which map to return or null for all
     *
     * @return \array[]
     */
    public static function getNamespaceMap( $which = null )
    {
        return $which ? static::$_namespaceMap[$which] : static::$_namespaceMap;
    }

    /**
     * Registers a set of PSR-4 directories for a given namespace, either
     * appending or prepending to the ones previously set for this namespace.
     *
     * @param string       $prefix  The prefix/namespace, with trailing '\\'
     * @param array|string $paths   The PSR-0 base directories
     * @param bool         $prepend Whether to prepend the directories
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @return \Composer\Autoload\ClassLoader
     */
    public static function addNamespace( $prefix, $paths, $prepend = false )
    {
        /** @var ClassLoader $_loader */
        if ( null === ( $_loader = \Kisma::get( CoreSettings::AUTO_LOADER ) ) )
        {
            throw new InternalServerErrorException( 'Unable to find auto-loader. :(' );
        }

        $_loader->addPsr4( $prefix, $paths, $prepend );

        return $_loader;
    }

    /**
     * @param int    $which
     * @param string $namespace
     * @param string $path
     * @param bool   $prepend If true, the namespace(s) will be placed at the beginning of the list
     *
     * @return PlatformWebApplication
     */
    protected static function _mapNamespace( $which, $namespace, $path, $prepend = false )
    {
        if ( $prepend )
        {
            array_unshift( static::$_namespaceMap[$which], array( $namespace, $path ) );
        }
        else
        {
            static::$_namespaceMap[$which][$namespace] = $path;
        }
    }

    /**
     * @param EventDispatcher $dispatcher
     */
    public static function setDispatcher( $dispatcher )
    {
        static::$_dispatcher = $dispatcher;
    }

    /**
     * @return EventDispatcher
     */
    public static function getDispatcher()
    {
        if ( empty( static::$_dispatcher ) )
        {
            static::$_dispatcher = new EventDispatcher();
        }

        return static::$_dispatcher;
    }

    /**
     * @return boolean
     */
    public function getUseResponseObject()
    {
        return $this->_useResponseObject;
    }

    /**
     * @param boolean $useResponseObject
     *
     * @return PlatformWebApplication
     */
    public function setUseResponseObject( $useResponseObject )
    {
        $this->_useResponseObject = $useResponseObject;

        return $this;
    }

}