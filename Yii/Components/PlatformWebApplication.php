<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2013 DreamFactory Software, Inc. <support@dreamfactory.com>
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

use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\HttpMethod;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\Scalar;

/**
 * PlatformWebApplication
 */
class PlatformWebApplication extends \CWebApplication
{
	//*************************************************************************
	//	Constants
	//*************************************************************************

	/**
	 * @var string The HTTP Option method
	 */
	const CORS_OPTION_METHOD = 'OPTIONS';
	/**
	 * @var string The allowed HTTP methods
	 */
	const CORS_DEFAULT_ALLOWED_METHODS = 'GET, POST, PUT, DELETE, PATCH, MERGE, COPY, OPTIONS';
	/**
	 * @var string The allowed HTTP headers
	 */
	const CORS_DEFAULT_ALLOWED_HEADERS = 'Content-Type, X-Requested-With, X-DreamFactory-Application-Name, X-Application-Name, X-DreamFactory-Session-Token';
	/**
	 * @var int The default number of seconds to allow this to be cached. Default is 15 minutes.
	 */
	const CORS_DEFAULT_MAX_AGE = 900;
	/**
	 * @var string The private CORS configuration file
	 */
	const CORS_DEFAULT_CONFIG_FILE = '/cors.config.json';
	/**
	 * @var string The default DSP resource namespace
	 */
	const DEFAULT_RESOURCE_NAMESPACE_ROOT = 'DreamFactory\\Platform\\Resource';
	/**
	 * @var string The default DSP model namespace
	 */
	const DEFAULT_MODEL_NAMESPACE_ROOT = 'DreamFactory\\Platform\\Yii\\Models';
	/**
	 * @var string The default path (sub-path) of installed plug-ins
	 */
	const DEFAULT_PLUGINS_PATH = '/storage/plugins';

	//*************************************************************************
	//	Members
	//*************************************************************************

	/**
	 * @var array An indexed array of white-listed hosts (ajax.example.com or foo.bar.com or just bar.com)
	 */
	protected $_corsWhitelist = array();
	/**
	 * @var bool    If true, the CORS headers will be sent automatically before dispatching the action.
	 *              NOTE: "OPTIONS" calls will always get headers, regardless of the setting. All other requests respect the setting.
	 */
	protected $_autoAddHeaders = true;
	/**
	 * @var bool If true, adds some extended information about the request in the form of X-DreamFactory-* headers
	 */
	protected $_extendedHeaders = true;
	/**
	 * @var array The namespaces that contain resources. Used by the routing engine
	 */
	protected $_resourceNamespaces = array();
	/**
	 * @var array The namespaces that contain models. Used by the resource manager
	 */
	protected $_modelNamespaces = array();

	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * Initialize
	 */
	protected function init()
	{
		parent::init();

		//	Get CORS data from config file
		$_config = Pii::getParam( 'storage_base_path' ) . static::CORS_DEFAULT_CONFIG_FILE;

		if ( !file_exists( $_config ) )
		{
			// old location
			$_config = Pii::getParam( 'private_path' ) . static::CORS_DEFAULT_CONFIG_FILE;
		}

		if ( file_exists( $_config ) )
		{
			$_allowedHosts = array();
			$_content = @file_get_contents( $_config );

			if ( !empty( $_content ) )
			{
				$_allowedHosts = json_decode( $_content, true );
			}

			$this->setCorsWhitelist( $_allowedHosts );
		}

		// setup the request handler
		$this->onBeginRequest = array( $this, 'checkRequestMethod' );
	}

	/**
	 * Handles an OPTIONS request to the server to allow CORS and optionally sends the CORS headers
	 *
	 * @param \CEvent $event
	 *
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 */
	public function checkRequestMethod( \CEvent $event )
	{
		//	Answer an options call...
		switch ( FilterInput::server( 'REQUEST_METHOD' ) )
		{
			case HttpMethod::Options:
				header( 'HTTP/1.1 204' );
				header( 'content-length: 0' );
				header( 'content-type: text/plain' );

				$this->addCorsHeaders();
				Pii::end();
				break;

			case HttpMethod::Trace:
				throw new BadRequestException();
		}

		//	Auto-add the CORS headers...
		if ( $this->_autoAddHeaders )
		{
			$this->addCorsHeaders();
		}

		//	Load any plug-ins
		$this->_loadPlugins();
	}

	/**
	 * @param array|bool $whitelist Set to "false" to reset the internal method cache.
	 *
	 * @return bool
	 */
	public function addCorsHeaders( $whitelist = array() )
	{
		static $_cache = array();
		static $_cacheVerbs = array();

		//	Reset the cache before processing...
		if ( false === $whitelist )
		{
			$_cache = array();
			$_cacheVerbs = array();

			return true;
		}

		$_requestSource = $_SERVER['SERVER_NAME'];
		$_origin = trim( Option::server( 'HTTP_ORIGIN' ) );
//		Log::debug( 'The received origin: [' . $_origin . ']' );

		$_originUri = null;

		//	Was an origin header passed? If not, don't do CORS.
		if ( empty( $_origin ) )
		{
			return true;
		}

		if ( false === ( $_originParts = $this->_parseUri( $_origin ) ) )
		{
			//	Not parse-able, set to itself, check later (testing from local files - no session)?
			Log::warning( 'Unable to parse received origin: [' . $_origin . ']' );
			$_originParts = $_origin;
		}

		$_originUri = $this->_normalizeUri( $_originParts );
		$_key = sha1( $_requestSource . $_originUri );

		//	Not in cache, check it out...
		if ( !in_array( $_key, $_cache ) )
		{
			if ( false === ( $_allowedMethods = $this->_allowedOrigin( $_originParts, $_requestSource ) ) )
			{
				Log::error( 'Unauthorized origin rejected via CORS > Source: ' . $_requestSource . ' > Origin: ' . $_originUri );

				/**
				 * No sir, I didn't like it.
				 *
				 * @link http://www.youtube.com/watch?v=VRaoHi_xcWk
				 */
				header( 'HTTP/1.1 403 Forbidden' );

				Pii::end();

				//	If end fails for some unknown impossible reason...
				return false;
			}

//			Log::debug( 'Committing origin to the CORS cache > Source: ' . $_requestSource . ' > Origin: ' . $_originUri );
			$_cache[$_key] = $_originUri;
			$_cacheVerbs[$_key] = $_allowedMethods;
		}
		else
		{
			$_originUri = $_cache[$_key];
			$_allowedMethods = $_cacheVerbs[$_key];
		}

		if ( !empty( $_originUri ) )
		{
			header( 'Access-Control-Allow-Origin: ' . $_originUri );
		}

		header( 'Access-Control-Allow-Credentials: true' );
		header( 'Access-Control-Allow-Headers: ' . static::CORS_DEFAULT_ALLOWED_HEADERS );
		header( 'Access-Control-Allow-Methods: ' . $_allowedMethods );
		header( 'Access-Control-Max-Age: ' . static::CORS_DEFAULT_MAX_AGE );

		if ( $this->_extendedHeaders )
		{
			header( 'X-DreamFactory-Source: ' . $_requestSource );

			if ( $_origin )
			{
				header( 'X-DreamFactory-Origin-Whitelisted: ' . preg_match( '/^([\w_-]+\.)*' . $_requestSource . '$/', $_originUri ) );
			}
		}

		return true;
	}

	/**
	 * Loads up any plug-ins configured
	 *
	 * @return bool
	 */
	protected function _loadPlugins()
	{
		if ( null === ( $_autoloadPath = Pii::getState( 'dsp.plugin_autoload_path' ) ) )
		{
			//	Locate plug-in directory...
			$_path = Pii::getParam( 'dsp.plugins_path', Pii::getParam( 'dsp.base_path' ) . static::DEFAULT_PLUGINS_PATH );

			if ( !is_dir( $_path ) )
			{
//				Log::debug( 'No plug-ins installed.' );

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

			Pii::setState( 'dsp.plugin_autoload_path', $_autoloadPath );
		}

		/** @noinspection PhpIncludeInspection */
		if ( false === @require( $_autoloadPath ) )
		{
			Log::error( 'Error reading plug-in autoload.php file. Some plug-ins may not function properly.' );

			return false;
		}

		Log::debug( 'Plug-ins loaded.' );

		return true;
	}

	/**
	 * @param string|array $origin     The parse_url value of origin
	 * @param array        $additional Additional origins to allow
	 *
	 * @return bool|array false if not allowed, otherwise array of verbs allowed
	 */
	protected function _allowedOrigin( $origin, $additional = array() )
	{
		foreach ( array_merge( $this->_corsWhitelist, Option::clean( $additional ) ) as $_hostInfo )
		{
			$_allowedMethods = static::CORS_DEFAULT_ALLOWED_METHODS;

			if ( is_array( $_hostInfo ) )
			{
				//	If is_enabled prop not there, assuming enabled.
				if ( !Scalar::boolval( Option::get( $_hostInfo, 'is_enabled', true ) ) )
				{
					continue;
				}

				if ( null === ( $_whiteGuy = Option::get( $_hostInfo, 'host' ) ) )
				{
					Log::error( 'CORS whitelist info does not contain a "host" parameter!' );
					continue;
				}

				if ( isset( $_hostInfo['verbs'] ) )
				{
					if ( false === array_search( static::CORS_OPTION_METHOD, $_hostInfo['verbs'] ) )
					{
						// add OPTION to allowed list
						$_hostInfo['verbs'][] = static::CORS_OPTION_METHOD;
					}
					$_allowedMethods = implode( ', ', $_hostInfo['verbs'] );
				}
			}
			else
			{
				$_whiteGuy = $_hostInfo;
			}

			//	All allowed?
			if ( '*' == $_whiteGuy )
			{
				return $_allowedMethods;
			}

			if ( false === ( $_whiteParts = $this->_parseUri( $_whiteGuy ) ) )
			{
				continue;
			}

			//	Check for un-parsed origin, 'null' sent when testing js files locally
			if ( is_array( $origin ) )
			{
				//	Is this origin on the whitelist?
				if ( $this->_compareUris( $origin, $_whiteParts ) )
				{
					return $_allowedMethods;
				}
			}
		}

		return false;
	}

	/**
	 * @param array $first
	 * @param array $second
	 *
	 * @return bool
	 */
	protected function _compareUris( $first, $second )
	{
		return ( $first['scheme'] == $second['scheme'] ) && ( $first['host'] == $second['host'] ) && ( $first['port'] == $second['port'] );
	}

	/**
	 * @param string $uri
	 * @param bool   $normalize
	 *
	 * @return array
	 */
	protected function _parseUri( $uri, $normalize = false )
	{
		if ( false === ( $_parts = parse_url( $uri ) ) || !( isset( $_parts['host'] ) || isset( $_parts['path'] ) ) )
		{
			return false;
		}

		if ( isset( $_parts['path'] ) && !isset( $_parts['host'] ) )
		{
			//	Special case, handle this generically later
			if ( 'null' == $_parts['path'] )
			{
				return 'null';
			}
			$_parts['host'] = $_parts['path'];
			unset( $_parts['path'] );
		}

		$_protocol = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] != 'off' ) ? 'https' : 'http';

		$_uri = array(
			'scheme' => Option::get( $_parts, 'scheme', $_protocol ),
			'host'   => Option::get( $_parts, 'host' ),
			'port'   => Option::get( $_parts, 'port' ),
		);

		return $normalize ? $this->_normalizeUri( $_uri ) : $_uri;
	}

	/**
	 * @param array $parts Return from \parse_url
	 *
	 * @return string
	 */
	protected function _normalizeUri( $parts )
	{
		return is_array( $parts ) ?
			( isset( $parts['scheme'] ) ? $parts['scheme'] : 'http' ) . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : null )
			: $parts;
	}

	/**
	 * @param array $corsWhitelist
	 *
	 * @return PlatformWebApplication
	 */
	public function setCorsWhitelist( $corsWhitelist )
	{
		$this->_corsWhitelist = $corsWhitelist;

		//	Reset the header cache
		$this->addCorsHeaders( false );

		return $this;
	}

	/**
	 * @return array
	 */
	public function getCorsWhitelist()
	{
		return $this->_corsWhitelist;
	}

	/**
	 * @param boolean $autoAddHeaders
	 *
	 * @return PlatformWebApplication
	 */
	public function setAutoAddHeaders( $autoAddHeaders = true )
	{
		$this->_autoAddHeaders = $autoAddHeaders;

		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getAutoAddHeaders()
	{
		return $this->_autoAddHeaders;
	}

	/**
	 * @param boolean $extendedHeaders
	 *
	 * @return PlatformWebApplication
	 */
	public function setExtendedHeaders( $extendedHeaders = true )
	{
		$this->_extendedHeaders = $extendedHeaders;

		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getExtendedHeaders()
	{
		return $this->_extendedHeaders;
	}

	/**
	 * @param array $resourceNamespaces
	 *
	 * @return PlatformWebApplication
	 */
	public function setResourceNamespaces( $resourceNamespaces )
	{
		$this->_resourceNamespaces = $resourceNamespaces;

		return $this;
	}

	/**
	 * @return array
	 */
	public function getResourceNamespaces()
	{
		return $this->_resourceNamespaces;
	}

	/**
	 * @param string|array $namespace
	 * @param bool         $prepend If true, the namespace(s) will be placed at the beginning of the list
	 *
	 * @return PlatformWebApplication
	 */
	public function addResourceNamespace( $namespace, $prepend = false )
	{
		foreach ( Option::clean( $namespace ) as $_entry )
		{
			if ( !in_array( $_entry, $this->_resourceNamespaces ) )
			{
				if ( false === $prepend )
				{
					$this->_resourceNamespaces[] = $_entry;
					continue;
				}

				array_unshift( $this->_resourceNamespaces, $_entry );
			}
		}

		return $this;
	}

	/**
	 * @param array $modelNamespaces
	 *
	 * @return PlatformWebApplication
	 */
	public function setModelNamespaces( $modelNamespaces )
	{
		$this->_modelNamespaces = $modelNamespaces;

		return $this;
	}

	/**
	 * @return array
	 */
	public function getModelNamespaces()
	{
		return $this->_modelNamespaces;
	}

	/**
	 * @param string|array $namespace
	 * @param bool         $prepend If true, the namespace(s) will be placed at the beginning of the list
	 *
	 * @return PlatformWebApplication
	 */
	public function addModelNamespace( $namespace, $prepend = false )
	{
		foreach ( Option::clean( $namespace ) as $_entry )
		{
			if ( !in_array( $_entry, $this->_modelNamespaces ) )
			{
				if ( false === $prepend )
				{
					$this->_modelNamespaces[] = $_entry;
					continue;
				}

				array_unshift( $this->_modelNamespaces, $_entry );
			}
		}

		return $this;
	}
}