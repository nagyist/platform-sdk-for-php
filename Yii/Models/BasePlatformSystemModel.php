<?php
/**
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
namespace DreamFactory\Platform\Yii\Models;

use DreamFactory\Platform\Services\SystemManager;
use DreamFactory\Platform\Utility\ResourceStore;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\Inflector;

/**
 * BasePlatformSystemModel.php
 * A base class for DSP system table models
 *
 * Base Columns:
 *
 * @property integer         $created_by_id
 * @property integer         $last_modified_by_id
 *
 * Base Relations:
 *
 * @property PlatformUser    $created_by
 * @property PlatformUser    $last_modified_by
 */
abstract class BasePlatformSystemModel extends BasePlatformModel
{
	//*************************************************************************
	//	Constants
	//*************************************************************************

	/**
	 * @var string
	 */
	const ALL_ATTRIBUTES = '*';

	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * @return string the system database table name prefix
	 */
	public static function tableNamePrefix()
	{
		return 'df_sys_';
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		return array(
			'created_by'       => array( self::BELONGS_TO, 'User', 'created_by_id' ),
			'last_modified_by' => array( self::BELONGS_TO, 'User', 'last_modified_by_id' ),
		);
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 *
	 * @param \CDbCriteria $criteria
	 *
	 * @return \CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
	 */
	public function search( $criteria = null )
	{
		$_criteria = $criteria ? : new \CDbCriteria;

		$_criteria->compare( 'created_by_id', $this->created_by_id );
		$_criteria->compare( 'last_modified_by_id', $this->last_modified_by_id );

		return parent::search( $criteria );
	}

	/**
	 * {@InheritDoc}
	 */
	public function restMap( $mappings = array() )
	{
		static $_map;

		if ( null === $_map )
		{
			$_map = array( 'created_by_id', 'last_modified_by_id' );
			$_map = array_combine( $_map, $_map );
		}

		//	Default to everything if none are specified
		if ( empty( $mappings ) )
		{
			$mappings = $this->getTableSchema()->getColumnNames();
			$mappings = array_combine( $mappings, $mappings );
		}

		return parent::restMap( $_map + $mappings );
	}

	/**
	 * {@InheritDoc}
	 */
	public function getRetrievableAttributes( $requested, $columns = array(), $hidden = array() )
	{
		if ( empty( $requested ) )
		{
			// primary keys only
			return array( 'id' );
		}

		if ( static::ALL_ATTRIBUTES == $requested )
		{
			return array_merge(
				array(
					 'id',
					 'created_date',
					 'created_by_id',
					 'last_modified_date',
					 'last_modified_by_id'
				),
				!empty( $columns ) ? $columns : $this->getSafeAttributeNames()
			);
		}

		//	Remove the hidden fields
		$_columns = ( is_string( $requested ) ? explode( ',', $requested ) : $requested ? : array() );

		if ( !empty( $hidden ) )
		{
			$_compare = array_map( 'strtolower', $_columns );

			foreach ( $hidden as $_hide )
			{
				if ( false !== ( $_index = array_search( strtolower( $_hide ), $_compare ) ) )
				{
					unset( $_columns[$_index] );
				}
			}
		}

		return $_columns;
	}

	/**
	 * Add in our additional labels
	 *
	 * @param array $additionalLabels
	 *
	 * @return array
	 */
	public function attributeLabels( $additionalLabels = array() )
	{
		return parent::attributeLabels(
			array(
				'created_by_id'       => 'Created By',
				'last_modified_by_id' => 'Last Modified By',
			) + $additionalLabels
		);
	}

	/**
	 * @return array The model as a resource
	 */
	public function asResource()
	{
		return ResourceStore::buildResponsePayload( $this );
	}

	/**
	 * @param array $values
	 * @param int   $id
	 */
	public function setRelated( $values, $id )
	{
		//	Does nothing here
	}

	/**
	 * @param string $sourceId
	 * @param string $mapTable
	 * @param string $mapColumn
	 * @param array  $targetRows
	 *
	 * @throws Exception
	 * @throws BadRequestException
	 * @return void
	 */
	protected function assignManyToOne( $sourceId, $mapTable, $mapColumn, $targetRows = array() )
	{
		if ( empty( $sourceId ) )
		{
			throw new BadRequestException( 'The id can not be empty.' );
		}

		/**
		 * Map tables have a
		 */

		try
		{
			$_sql
				= <<<MYSQL
SELECT
	id,
	$mapColumn
FROM
	$mapTable
WHERE
	$mapColumn = :id
MYSQL;

			$_manyModel = ResourceStore::model( $mapTable );
			$_primaryKey = $_manyModel->tableSchema->primaryKey;
			$mapTable = $_manyModel->tableName();

			// use query builder
			$command = Pii::db()->createCommand();
			$command->select( "$_primaryKey,$mapColumn" );
			$command->from( $mapTable );
			$command->where( "$mapColumn = :oid" );
			$maps = $command->queryAll( true, array( ':oid' => $sourceId ) );

			$toDelete = array();
			foreach ( $maps as $map )
			{
				$id = Utilities::getArrayValue( $_primaryKey, $map, '' );
				$found = false;
				foreach ( $targetRows as $key => $item )
				{
					$assignId = Utilities::getArrayValue( $_primaryKey, $item, '' );
					if ( $id == $assignId )
					{
						// found it, keeping it, so remove it from the list, as this becomes adds
						unset( $targetRows[$key] );
						$found = true;
						continue;
					}
				}
				if ( !$found )
				{
					$toDelete[] = $id;
					continue;
				}
			}
			if ( !empty( $toDelete ) )
			{
				// simple update to null request
				$command->reset();
				$rows = $command->update( $mapTable, array( $mapColumn => null ), array( 'in', $_primaryKey, $toDelete ) );
				if ( 0 >= $rows )
				{
//					throw new Exception( "Record update failed for table '$mapTable'." );
				}
			}
			if ( !empty( $targetRows ) )
			{
				$toAdd = array();
				foreach ( $targetRows as $item )
				{
					$itemId = Utilities::getArrayValue( $_primaryKey, $item, '' );
					if ( !empty( $itemId ) )
					{
						$toAdd[] = $itemId;
					}
				}
				if ( !empty( $toAdd ) )
				{
					// simple update to null request
					$command->reset();
					$rows = $command->update( $mapTable, array( $mapColumn => $sourceId ), array( 'in', $_primaryKey, $toAdd ) );
					if ( 0 >= $rows )
					{
//						throw new Exception( "Record update failed for table '$mapTable'." );
					}
				}
			}
		}
		catch ( Exception $ex )
		{
			throw new Exception( "Error updating many to one assignment.\n{$ex->getMessage()}", $ex->getCode() );
		}
	}

	/**
	 * @param        $sourceId
	 * @param string $mapTable
	 * @param string $entity The associative entity, or mapping table
	 * @param        $sourceColumn
	 * @param        $mapColumn
	 * @param array  $targetRows
	 *
	 * @throws Exception
	 * @throws BadRequestException
	 * @internal param int $id
	 * @return void
	 */
	protected function assignManyToOneByMap( $sourceId, $mapTable, $entity, $sourceColumn, $mapColumn, $targetRows = array() )
	{
		if ( empty( $sourceId ) )
		{
			throw new BadRequestException( "The id can not be empty." );
		}

		$entity = static::tableNamePrefix() . $entity;

		try
		{
			$_manyModel = ResourceStore::model( $mapTable );
			$pkManyField = $_manyModel->tableSchema->primaryKey;
			$pkMapField = 'id';
			//	Use query builder
			$command = Pii::db()->createCommand();
			$command->select( $pkMapField . ',' . $mapColumn );
			$command->from( $entity );
			$command->where( "$sourceColumn = :id" );
			$maps = $command->queryAll( true, array( ':id' => $sourceId ) );

			$toDelete = array();
			foreach ( $maps as $map )
			{
				$manyId = Utilities::getArrayValue( $mapColumn, $map, '' );
				$id = Utilities::getArrayValue( $pkMapField, $map, '' );
				$found = false;
				foreach ( $targetRows as $key => $item )
				{
					$assignId = Utilities::getArrayValue( $pkManyField, $item, '' );
					if ( $assignId == $manyId )
					{
						// found it, keeping it, so remove it from the list, as this becomes adds
						unset( $targetRows[$key] );
						$found = true;
						continue;
					}
				}
				if ( !$found )
				{
					$toDelete[] = $id;
					continue;
				}
			}
			if ( !empty( $toDelete ) )
			{
				// simple delete request
				$command->reset();
				$rows = $command->delete( $entity, array( 'in', $pkMapField, $toDelete ) );
				if ( 0 >= $rows )
				{
//					throw new Exception( "Record delete failed for table '$entity'." );
				}
			}
			if ( !empty( $targetRows ) )
			{
				foreach ( $targetRows as $item )
				{
					$itemId = Utilities::getArrayValue( $pkManyField, $item, '' );
					$record = array( $mapColumn => $itemId, $sourceColumn => $sourceId );
					// simple update request
					$command->reset();
					$rows = $command->insert( $entity, $record );
					if ( 0 >= $rows )
					{
						throw new Exception( "Record insert failed for table '$entity'." );
					}
				}
			}
		}
		catch ( Exception $ex )
		{
			throw new Exception( "Error updating many to one map assignment.\n{$ex->getMessage()}", $ex->getCode() );
		}
	}

	/**
	 * @param array $columns The columns to return in the permissions array
	 *
	 * @return array|null
	 */
	public function getRoleServicePermissions( $columns = null )
	{
		static $_permsFields = array( 'service_id', 'component', 'access' );

		$_perms = null;

		/**
		 * @var RoleServiceAccess[] $_permissions
		 * @var Service[]           $_services
		 */
		if ( $this->hasRelated( 'role' ) && $this->role && $this->role->role_service_accesses )
		{
			/** @var Role $_perm */
			foreach ( $this->role->role_service_accesses as $_perm )
			{
				$_permServiceId = $_perm->service_id;
				$_temp = $_perm->getAttributes( $columns ? : $_permsFields );

				if ( $this->role->services )
				{
					foreach ( $this->role->services as $_service )
					{
						if ( $_permServiceId == $_service->id )
						{
							$_temp['service'] = $_service->api_name;
						}
					}
				}

				$_perms[] = $_temp;
			}
		}

		return $_perms;
	}

	/**
	 * Named scope that filters by api_name
	 *
	 * @param string $name
	 *
	 * @return Service
	 */
	public function byApiName( $name )
	{
		if ( $this->hasAttribute( 'api_name' ) )
		{
			$this->getDbCriteria()->mergeWith(
				array(
					 'condition' => 'api_name = :api_name',
					 'params'    => array( ':api_name' => $name ),
				)
			);
		}

		return $this;
	}
}