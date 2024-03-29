<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
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
namespace DreamFactory\Platform\Events;

use DreamFactory\Platform\Components\PlatformStore;
use DreamFactory\Platform\Resources\System\Script;
use DreamFactory\Platform\Services\BasePlatformRestService;
use DreamFactory\Platform\Services\SwaggerManager;
use DreamFactory\Platform\Utility\Platform;
use DreamFactory\Yii\Utility\Pii;
use Guzzle\Http\Client;
use Guzzle\Http\Exception\MultiTransferException;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * EventDispatcher
 */
class EventDispatcher implements EventDispatcherInterface
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type string
     */
    const DEFAULT_USER_AGENT = 'DreamFactory/SSE_1.0';
    /**
     * @type string The name of the subdirectory under private in which to save the store
     */
    const DEFAULT_FILE_CACHE_PATH = '/store.cache';
    /**
     * @type string The default namespace for our store
     */
    const DEFAULT_STORE_NAMESPACE = 'platform.events';
    /**
     * @typ string
     */
    const STORE_LISTENERS_KEY = 'platform.events.listeners';
    /**
     * @typ string
     */
    const STORE_SCRIPTS_KEY = 'platform.events.scripts';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var bool Will log dispatched events if true
     */
    protected static $_logEvents = false;
    /**
     * @var bool Will log all events if true
     */
    protected static $_logAllEvents = false;
    /**
     * @var bool Enable/disable REST events
     */
    protected static $_enableRestEvents = true;
    /**
     * @var bool Enable/disable platform events
     */
    protected static $_enablePlatformEvents = true;
    /**
     * @var bool Enable/disable event scripts
     */
    protected static $_enableEventScripts = true;
    /**
     * @var Client
     */
    protected static $_client = null;
    /**
     * @var BasePlatformRestService
     */
    protected $_service;
    /**
     * @var array
     */
    protected $_listeners = array();
    /**
     * @var array
     */
    protected $_scripts = array();
    /**
     * @var array
     */
    protected $_sorted = array();
    /**
     * @var \Kisma\Core\Components\Flexistore
     */
    protected static $_store = null;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Load any stored events
     */
    public function __construct()
    {
        static::$_logEvents = Pii::getParam( 'dsp.log_events', static::$_logEvents );
        static::$_logAllEvents = Pii::getParam( 'dsp.log_all_events', static::$_logAllEvents );

        static::$_enableRestEvents = Pii::getParam( 'dsp.enable_rest_events', static::$_enableRestEvents );
        static::$_enablePlatformEvents = Pii::getParam( 'dsp.enable_rest_events', static::$_enablePlatformEvents );
        static::$_enableEventScripts = Pii::getParam( 'dsp.enable_event_scripts', static::$_enableEventScripts );

        try
        {
            $this->_initializeEvents();
            $this->_initializeEventScripting();
        }
        catch ( \Exception $_ex )
        {
            Log::notice( 'Event system unavailable at this time.' );
        }
    }

    /**
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     */
    protected function _initializeEvents()
    {
        if ( !static::$_enablePlatformEvents && !static::$_enableRestEvents )
        {
            Log::info( 'Events disabled.' );

            return false;
        }

        //  Load any stored listeners
        $this->_listeners = Platform::storeGet( static::STORE_LISTENERS_KEY, array() );

        if ( !empty( $this->_listeners ) )
        {
            Log::debug( 'Registered ' . count( $this->_listeners ) . ' cached listeners.' );
        }
    }

    /**
     * @return bool
     */
    protected function _initializeEventScripting()
    {
        if ( !static::$_enableEventScripts )
        {
            Log::info( 'Event scripting disabled.' );

            return false;
        }

        $this->_scripts = Platform::storeGet( static::STORE_SCRIPTS_KEY, array() );

//        if ( empty( $this->_scripts ) )
        {
            $_scriptPath = Platform::getPrivatePath( Script::DEFAULT_SCRIPT_PATH );

            foreach ( SwaggerManager::getEventMap() as $_routes )
            {
                foreach ( $_routes as $_routeInfo )
                {
                    foreach ( $_routeInfo as $_methodInfo )
                    {
                        foreach ( Option::get( $_methodInfo, 'scripts', array() ) as $_script )
                        {
                            $_eventKey = str_replace( '.js', null, $_script );
                            $this->_scripts[ $_eventKey ] = $_scriptPath . '/' . $_script;
                        }
                    }
                }
            }

            //  Cache for 30 seconds. New scripts will have to wait.
            Platform::storeSet( static::STORE_SCRIPTS_KEY, $this->_scripts, 30 );
        }

        return true;
    }

    /**
     * Destruction
     */
    public function __destruct()
    {
        //  Save off listeners and scripts
        Platform::storeSet( static::STORE_LISTENERS_KEY, $this->_listeners, PlatformStore::DEFAULT_CACHE_TTL );
        Platform::storeSet( static::STORE_SCRIPTS_KEY, $this->_scripts, 30 );
    }

    /**
     * @param string $eventName
     * @param Event  $event
     *
     * @return \Symfony\Component\EventDispatcher\Event|void
     */
    public function dispatch( $eventName, Event $event = null )
    {
        $_event = $event ? : new PlatformEvent( $eventName );

        $this->_doDispatch( $_event, $eventName, $this );

        return $_event;
    }

    /**
     * @param BasePlatformRestService                        $service
     * @param string                                         $eventName
     * @param \DreamFactory\Platform\Events\RestServiceEvent $event
     *
     * @return \DreamFactory\Platform\Events\RestServiceEvent
     */
    public function dispatchRestServiceEvent( $service, $eventName, RestServiceEvent $event = null )
    {
        $_event = $event ? : new RestServiceEvent( $eventName, $service->getApiName(), $service->getResource() );

        $this->_doDispatch( $_event, $eventName, $this );

        return $_event;
    }

    /**
     * @param string                                 $eventName
     * @param \DreamFactory\Platform\Events\DspEvent $event
     *
     * @return \DreamFactory\Platform\Events\DspEvent
     */
    public function dispatchDspEvent( $eventName, DspEvent $event = null )
    {
        $_event = $event ? : new DspEvent();

        $this->_doDispatch( $_event, $eventName, $this );

        return $_event;
    }

    /**
     * @param PlatformEvent|RestServiceEvent $event
     * @param string                         $eventName
     * @param EventDispatcher                $dispatcher
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \Exception
     *
     * @return bool|\DreamFactory\Platform\Events\PlatformEvent Returns the original $event
     * if successfully dispatched to all listeners. Returns false if nothing was dispatched
     * and true if propagation was stopped.
     */
    protected function _doDispatch( &$event, $eventName, $dispatcher )
    {
        if ( !static::$_enableRestEvents && !static::$_enablePlatformEvents && !static::$_enableEventScripts )
        {
            return false;
        }

        //	Queue up the posts
        $_posts = array();
        $_dispatched = true;
        $_pathInfo = preg_replace( '#^\/rest#', null, Pii::request( true )->getPathInfo(), 1 );

        if ( static::$_logAllEvents )
        {
            Log::debug(
                'Triggered: event "' . $eventName . '" triggered by ' . $_pathInfo
            );
        }

        if ( null === Option::get( $this->_listeners, $eventName ) && null === Option::get( $this->_scripts, $eventName ) )
        {
            return false;
        }

        $_event = array_merge(
            $event->toArray(),
            array(
                'event_name'       => $eventName,
                'dispatcher_id'    => spl_object_hash( $dispatcher ),
                'trigger'          => $_pathInfo,
                'stop_propagation' => $event->isPropagationStopped(),
            )
        );

        if ( static::$_enableEventScripts )
        {
            //  Run scripts
            if ( null === ( $_script = Option::get( $this->_scripts, $eventName ) ) )
            {
                //  See if we have a platform event handler...
                if ( false === ( $_script = Script::existsForEvent( $eventName ) ) )
                {
                    $_script = null;
                }
            }

            if ( !empty( $_script ) )
            {
                $_script = $this->_scripts[ $eventName ];

                $_eventData = Option::get( $_event, 'data', array() );
                $_result = Script::runScript( $_script, $eventName . '.js', $_eventData, $_output );

                if ( is_array( $_result ) )
                {
                    $_event['data'] = $_result;
                    $event->fromArray( $_event );
                }

                if ( !empty( $_output ) )
                {
                    Log::debug( 'Script "' . $eventName . '.js" output: ' . $_output );
                }

                if ( $event->isPropagationStopped() )
                {
                    Log::info( '  * Propagation stopped by script.' );

                    return true;
                }
            }
        }

        //  Callbacks
        foreach ( $this->getListeners( $eventName ) as $_listener )
        {
            //  Local code listener
            if ( !is_string( $_listener ) && is_callable( $_listener ) )
            {
                call_user_func( $_listener, $event, $eventName, $dispatcher );
            } //  External PHP script listener
            elseif ( $this->isPhpScript( $_listener ) )
            {
                $_className = substr( $_listener, 0, strpos( $_listener, '::' ) );
                $_methodName = substr( $_listener, strpos( $_listener, '::' ) + 2 );

                if ( !class_exists( $_className ) )
                {
                    Log::warning( 'Class ' . $_className . ' is not auto-loadable. Cannot call ' . $eventName . ' script' );
                    continue;
                }

                if ( !is_callable( $_listener ) )
                {
                    Log::warning( 'Method ' . $_listener . ' is not callable. Cannot call ' . $eventName . ' script' );
                    continue;
                }

                try
                {
                    $this->_executeEventPhpScript( $_className, $_methodName, $event, $eventName, $dispatcher );
                }
                catch ( \Exception $_ex )
                {
                    Log::error( 'Exception running script "' . $_listener . '" handling the event "' . $eventName . '"' );
                    throw $_ex;
                }
            } //  HTTP POST event
            elseif ( is_string( $_listener ) && (bool)@parse_url( $_listener ) )
            {
                if ( !static::$_client )
                {
                    static::$_client = static::$_client ? : new Client();
                    static::$_client->setUserAgent( static::DEFAULT_USER_AGENT );
                }

                $_payload = $this->_envelope( $_event );

                $_posts[] = static::$_client->post(
                    $_listener,
                    array( 'content-type' => 'application/json' ),
                    json_encode( $_payload, JSON_UNESCAPED_SLASHES + JSON_PRETTY_PRINT )
                );
            }
            else
            {
                $_dispatched = false;
            }

            if ( !empty( $_posts ) )
            {
                try
                {
                    //	Send the posts all at once
                    static::$_client->send( $_posts );
                }
                catch ( MultiTransferException $_exceptions )
                {
                    /** @var \Exception $_exception */
                    foreach ( $_exceptions as $_exception )
                    {
                        Log::error( '  * Action event exception: ' . $_exception->getMessage() );
                    }

                    foreach ( $_exceptions->getFailedRequests() as $_request )
                    {
                        Log::error( '  * Dispatch Failure: ' . $_request );
                    }

                    foreach ( $_exceptions->getSuccessfulRequests() as $_request )
                    {
                        Log::debug( '  * Dispatch success: ' . $_request );
                    }
                }
            }

            if ( $_dispatched && static::$_logEvents && !static::$_logAllEvents )
            {
                Log::debug(
                    ( $_dispatched ? 'Dispatcher' : 'Unhandled' ) .
                    ': event "' .
                    $eventName .
                    '" triggered by /' .
                    Option::get( $_GET, 'path', $event->getApiName() . '/' . $event->getResource() )
                );
            }

            if ( $event->isPropagationStopped() )
            {
                break;
            }
        }

        return $_dispatched;
    }

    /**
     * @param string          $className
     * @param string          $methodName
     * @param Event           $event
     * @param string          $eventName
     * @param EventDispatcher $dispatcher
     *
     * @return mixed
     */
    protected function _executeEventPhpScript( $className, $methodName, Event $event, $eventName = null, $dispatcher = null )
    {
        return call_user_func(
            array( $className, $methodName ),
            $event,
            $eventName,
            $dispatcher
        );
    }

    /**
     * @param string $eventName
     *
     * @return array
     */
    public function getListeners( $eventName = null )
    {
        if ( null !== $eventName )
        {
            if ( !isset( $this->_sorted[ $eventName ] ) )
            {
                $this->_sortListeners( $eventName );
            }

            return $this->_sorted[ $eventName ];
        }

        foreach ( array_keys( $this->_listeners ) as $eventName )
        {
            if ( !isset( $this->_sorted[ $eventName ] ) )
            {
                $this->_sortListeners( $eventName );
            }
        }

        return $this->_sorted;
    }

    /**
     * @param $callable
     *
     * @return bool
     */
    protected function isPhpScript( $callable )
    {
        return false === strpos( $callable, ' ' ) && false !== strpos( $callable, '::' );
    }

    /**
     * @see EventDispatcherInterface::hasListeners
     */
    public function hasListeners( $eventName = null )
    {
        return (Boolean)count( $this->getListeners( $eventName ) );
    }

    /**
     * @see EventDispatcherInterface::addListener
     */
    public function addListener( $eventName, $listener, $priority = 0 )
    {
        if ( !isset( $this->_listeners[ $eventName ] ) )
        {
            $this->_listeners[ $eventName ] = array();
        }

        foreach ( $this->_listeners[ $eventName ] as $priority => $listeners )
        {
            if ( false !== ( $_key = array_search( $listener, $listeners, true ) ) )
            {
                $this->_listeners[ $eventName ][ $priority ][ $_key ] = $listener;
                unset( $this->_sorted[ $eventName ] );

                return;
            }
        }

        $this->_listeners[ $eventName ][ $priority ][] = $listener;
        unset( $this->_sorted[ $eventName ] );
    }

    /**
     * @see EventDispatcherInterface::removeListener
     */
    public function removeListener( $eventName, $listener )
    {
        if ( !isset( $this->_listeners[ $eventName ] ) )
        {
            return;
        }

        foreach ( $this->_listeners[ $eventName ] as $priority => $listeners )
        {
            if ( false !== ( $key = array_search( $listener, $listeners, true ) ) )
            {
                unset( $this->_listeners[ $eventName ][ $priority ][ $key ], $this->_sorted[ $eventName ] );
            }
        }
    }

    /**
     * @see EventDispatcherInterface::addSubscriber
     */
    public function addSubscriber( EventSubscriberInterface $subscriber )
    {
        foreach ( $subscriber->getSubscribedEvents() as $_eventName => $_params )
        {
            if ( is_string( $_params ) )
            {
                $this->addListener( $_eventName, array( $subscriber, $_params ) );
            }
            elseif ( is_string( $_params[0] ) )
            {
                $this->addListener( $_eventName, array( $subscriber, $_params[0] ), isset( $_params[1] ) ? $_params[1] : 0 );
            }
            else
            {
                foreach ( $_params as $listener )
                {
                    $this->addListener( $_eventName, array( $subscriber, $listener[0] ), isset( $listener[1] ) ? $listener[1] : 0 );
                }
            }
        }
    }

    /**
     * @see EventDispatcherInterface::removeSubscriber
     */
    public function removeSubscriber( EventSubscriberInterface $subscriber )
    {
        foreach ( $subscriber->getSubscribedEvents() as $_eventName => $_params )
        {
            if ( is_array( $_params ) && is_array( $_params[0] ) )
            {
                foreach ( $_params as $listener )
                {
                    $this->removeListener( $_eventName, array( $subscriber, $listener[0] ) );
                }
            }
            else
            {
                $this->removeListener( $_eventName, array( $subscriber, is_string( $_params ) ? $_params : $_params[0] ) );
            }
        }
    }

    /**
     * Sorts the internal list of listeners for the given event by priority.
     *
     * @param string $eventName The name of the event.
     */
    protected function _sortListeners( $eventName )
    {
        $this->_sorted[ $eventName ] = array();

        if ( isset( $this->_listeners[ $eventName ] ) )
        {
            krsort( $this->_listeners[ $eventName ] );
            $this->_sorted[ $eventName ] = call_user_func_array( 'array_merge', $this->_listeners[ $eventName ] );
        }
    }

    /**
     * Creates a JSON encoded array (as a string) with a standard REST response. Override to provide
     * a different response format.
     *
     * @param array   $resultList
     * @param boolean $isError
     * @param string  $errorMessage
     * @param integer $errorCode
     * @param array   $additionalInfo
     *
     * @return string JSON encoded array
     */
    protected function _envelope( $resultList = null, $isError = false, $errorMessage = 'failure', $errorCode = 0, $additionalInfo = array() )
    {
        if ( $isError )
        {
            $_info = array(
                'error_code'    => $errorCode,
                'error_message' => $errorMessage,
            );

            if ( !empty( $additionalInfo ) )
            {
                $_info = array_merge( $additionalInfo, $_info );
            }

            return $this->_buildContainer( false, $resultList, $_info );
        }

        return $this->_buildContainer( true, $resultList );
    }

    /**
     * Builds a v2 response container
     *
     * @param bool  $success
     * @param mixed $details   Additional details/data/payload
     * @param array $extraInfo Additional data to add to the _info object
     *
     * @return array
     */
    protected function _buildContainer( $success = true, $details = null, $extraInfo = null )
    {
        $_id = sha1(
            Option::server( 'REQUEST_TIME_FLOAT', microtime( true ) ) .
            Option::server( 'HTTP_HOST', $_host = gethostname() ) .
            Option::server( 'REMOTE_ADDR', gethostbyname( $_host ) )
        );

        $_ro = Pii::app()->getRequestObject();

        $_container = array(
            'success' => $success,
            'details' => $details,
            '_info'   => array_merge(
                array(
                    'id'        => $_id,
                    'timestamp' => date( 'c', $_start = $_SERVER['REQUEST_TIME'] ),
                    'elapsed'   => (float)number_format( microtime( true ) - $_start, 4 ),
                    'verb'      => $_ro->getMethod(),
                    'uri'       => $_ro->server->get( 'request-uri' ),
                    'signature' => base64_encode( hash_hmac( 'sha256', $_id, $_id, true ) ),
                ),
                Option::clean( $extraInfo )
            ),
        );

        return $_container;
    }

    /**
     * @param boolean $logEvents
     */
    public static function setLogEvents( $logEvents )
    {
        self::$_logEvents = $logEvents;
    }

    /**
     * @return boolean
     */
    public static function getLogEvents()
    {
        return self::$_logEvents;
    }

    /**
     * @return boolean
     */
    public static function getLogAllEvents()
    {
        return self::$_logAllEvents;
    }

    /**
     * @param boolean $logAllEvents
     */
    public static function setLogAllEvents( $logAllEvents )
    {
        self::$_logAllEvents = $logAllEvents;
    }

}
