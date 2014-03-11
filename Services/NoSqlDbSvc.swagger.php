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

$_base = require(__DIR__ . '/BaseDbSvc.swagger.php');

$_base['apis'] = array(
	array(
		'path'        => '/{api_name}',
		'operations'  => array(
			array(
				'method'           => 'GET',
				'summary'          => 'getResources() - List all resources.',
				'nickname'         => 'getResources',
				'type'             => 'Resources',
				'event_name'       => '{api_name}.list',
				'responseMessages' => array(
					array(
						'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'    => 400,
					),
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'List the names of the available tables in this storage. ',
			),
			array(
				'method'           => 'GET',
				'summary'          => 'getTables() - List all properties on given tables.',
				'nickname'         => 'getTables',
				'type'             => 'Tables',
				'event_name'       => 'tables.describe',
				'parameters'       => array(
					array(
						'name'          => 'names',
						'description'   => 'Comma-delimited list of the table names to retrieve.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => true,
					),
				),
				'responseMessages' => array(
					array(
						'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'    => 400,
					),
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'List the properties of the given tables in this storage.',
			),
			array(
				'method'           => 'POST',
				'summary'          => 'createTables() - Create one or more tables.',
				'nickname'         => 'createTables',
				'type'             => 'Tables',
				'event_name'       => 'tables.create',
				'parameters'       => array(
					array(
						'name'          => 'tables',
						'description'   => 'Array of tables to create.',
						'allowMultiple' => false,
						'type'          => 'Tables',
						'paramType'     => 'body',
						'required'      => true,
					),
					array(
						'name'          => 'check_exist',
						'description'   => 'If true, the request fails when the table to create already exists.',
						'allowMultiple' => false,
						'type'          => 'boolean',
						'paramType'     => 'query',
						'required'      => false,
					),
					array(
						'name'          => 'X-HTTP-METHOD',
						'description'   => 'Override request using POST to tunnel other http request, such as DELETE.',
						'enum'          => array( 'GET', 'PUT', 'PATCH', 'DELETE' ),
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'header',
						'required'      => false,
					),
				),
				'responseMessages' => array(
					array(
						'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'    => 400,
					),
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'Post body should be a single table definition or an array of table definitions.',
			),
			array(
				'method'           => 'PATCH',
				'summary'          => 'updateTableProperties() - Update properties of one or more tables.',
				'nickname'         => 'updateTableProperties',
				'type'             => 'Tables',
				'event_name'       => 'tables.update',
				'parameters'       => array(
					array(
						'name'          => 'body',
						'description'   => 'Array of tables with properties to update.',
						'allowMultiple' => false,
						'type'          => 'Tables',
						'paramType'     => 'body',
						'required'      => true,
					),
				),
				'responseMessages' => array(
					array(
						'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'    => 400,
					),
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					array(
						'message' => 'Not Found - Requested table does not exist.',
						'code'    => 404,
					),
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'Post body should be a single table definition or an array of table definitions.',
			),
			array(
				'method'           => 'DELETE',
				'summary'          => 'deleteTables() - Delete one or more tables.',
				'nickname'         => 'deleteTables',
				'type'             => 'Tables',
				'event_name'       => 'tables.delete',
				'parameters'       => array(
					array(
						'name'          => 'names',
						'description'   => 'Comma-delimited list of the table names to delete.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					array(
						'name'          => 'force',
						'description'   => 'Set force to true to delete all tables in this database, otherwise \'names\' parameter is required.',
						'allowMultiple' => false,
						'type'          => 'boolean',
						'paramType'     => 'query',
						'required'      => false,
						'default'       => false,
					),
				),
				'responseMessages' => array(
					array(
						'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'    => 400,
					),
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					array(
						'message' => 'Not Found - Requested table does not exist.',
						'code'    => 404,
					),
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            =>
					'Set the names of the tables to delete or set \'force\' to true to clear the database.' .
					'Alternatively, to delete by table definitions or a large list of names, ' .
					'use the POST request with X-HTTP-METHOD = DELETE header and post array of definitions or names.',
			),
		),
		'description' => 'Operations available for database tables.',
	),
	array(
		'path'        => '/{api_name}/{table_name}',
		'operations'  => array(
			array(
				'method'           => 'GET',
				'summary'          => 'getRecords() - Retrieve one or more records.',
				'nickname'         => 'getRecords',
				'type'             => 'Records',
				'event_name'       => '{table_name}.records.read',
				'parameters'       => array(
					array(
						'name'          => 'table_name',
						'description'   => 'Name of the table to perform operations on.',
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					array(
						'name'          => 'ids',
						'description'   => 'Comma-delimited list of the identifiers of the resources to retrieve.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					array(
						'name'          => 'filter',
						'description'   => 'SQL-like filter to limit the resources to retrieve.',
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					array(
						'name'          => 'limit',
						'description'   => 'Set to limit the filter results.',
						'allowMultiple' => false,
						'type'          => 'integer',
						'format'        => 'int32',
						'paramType'     => 'query',
						'required'      => false,
					),
					array(
						'name'          => 'offset',
						'description'   => 'Set to offset the filter results to a particular record count.',
						'allowMultiple' => false,
						'type'          => 'integer',
						'format'        => 'int32',
						'paramType'     => 'query',
						'required'      => false,
					),
					array(
						'name'          => 'order',
						'description'   => 'SQL-like order containing field and direction for filter results.',
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					array(
						'name'          => 'fields',
						'description'   => 'Comma-delimited list of field names to retrieve for each record.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					array(
						'name'          => 'include_count',
						'description'   => 'Include the total number of filter results.',
						'allowMultiple' => false,
						'type'          => 'boolean',
						'paramType'     => 'query',
						'required'      => false,
					),
					array(
						'name'          => 'id_field',
						'description'   => 'Comma-delimited list of the fields used as identifiers or primary keys for the table.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
				),
				'responseMessages' => array(
					array(
						'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'    => 400,
					),
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					array(
						'message' => 'Not Found - Requested table does not exist.',
						'code'    => 404,
					),
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            =>
					'Use the \'ids\' or \'filter\' parameter to limit resources that are returned. ' .
					'Use the \'fields\' parameter to limit properties returned for each resource. ' .
					'By default, all fields are returned for all resources. ' .
					'Alternatively, to send the \'ids\' or \'filter\' as posted data ' .
					'use the POST request with X-HTTP-METHOD = GET header and post array of ids or a filter.',
			),
			array(
				'method'           => 'POST',
				'summary'          => 'createRecords() - Create one or more records.',
				'nickname'         => 'createRecords',
				'type'             => 'Records',
				'event_name'       => '{table_name}.records.create',
				'parameters'       => array(
					array(
						'name'          => 'table_name',
						'description'   => 'Name of the table to perform operations on.',
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					array(
						'name'          => 'body',
						'description'   => 'Data containing name-value pairs of records to create.',
						'allowMultiple' => false,
						'type'          => 'Records',
						'paramType'     => 'body',
						'required'      => true,
					),
					array(
						'name'          => 'id_field',
						'description'   => 'Comma-delimited list of the fields used as identifiers or primary keys for the table.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					array(
						'name'          => 'fields',
						'description'   => 'Comma-delimited list of field names to retrieve for each record.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					array(
						'name'          => 'X-HTTP-METHOD',
						'description'   => 'Override request using POST to tunnel other http request, such as DELETE.',
						'enum'          => array( 'GET', 'PUT', 'PATCH', 'DELETE' ),
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'header',
						'required'      => false,
					),
				),
				'responseMessages' => array(
					array(
						'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'    => 400,
					),
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					array(
						'message' => 'Not Found - Requested table does not exist.',
						'code'    => 404,
					),
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            =>
					'Post data should be a single record or an array of records (shown). ' .
					'By default, only the id property of the record is returned on success. ' .
					'Use \'fields\' parameter to return more info.',
			),
			array(
				'method'           => 'PUT',
				'summary'          => 'replaceRecords() - Update (replace) one or more records.',
				'nickname'         => 'replaceRecords',
				'type'             => 'Records',
				'event_name'       => '{table_name}.records.replace',
				'parameters'       => array(
					array(
						'name'          => 'table_name',
						'description'   => 'Name of the table to perform operations on.',
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					array(
						'name'          => 'body',
						'description'   => 'Data containing name-value pairs of records to update.',
						'allowMultiple' => false,
						'type'          => 'Records',
						'paramType'     => 'body',
						'required'      => true,
					),
					array(
						'name'          => 'ids',
						'description'   => 'Comma-delimited list of the identifiers of the resources to modify.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					array(
						'name'          => 'filter',
						'description'   => 'SQL-like filter to limit the resources to modify.',
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					array(
						'name'          => 'id_field',
						'description'   => 'Comma-delimited list of the fields used as identifiers or primary keys for the table.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					array(
						'name'          => 'fields',
						'description'   => 'Comma-delimited list of field names to retrieve for each record.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
				),
				'responseMessages' => array(
					array(
						'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'    => 400,
					),
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					array(
						'message' => 'Not Found - Requested table does not exist.',
						'code'    => 404,
					),
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            =>
					'Post data should be a single record or an array of records (shown). ' .
					'By default, only the id property of the record is returned on success. ' .
					'Use \'fields\' parameter to return more info.',
			),
			array(
				'method'           => 'PATCH',
				'summary'          => 'updateRecords() - Update (patch) one or more records.',
				'nickname'         => 'updateRecords',
				'type'             => 'Records',
				'event_name'       => '{table_name}.records.update',
				'parameters'       => array(
					array(
						'name'          => 'table_name',
						'description'   => 'Name of the table to perform operations on.',
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					array(
						'name'          => 'body',
						'description'   => 'Data containing name-value pairs of records to update.',
						'allowMultiple' => false,
						'type'          => 'Records',
						'paramType'     => 'body',
						'required'      => true,
					),
					array(
						'name'          => 'ids',
						'description'   => 'Comma-delimited list of the identifiers of the resources to modify.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					array(
						'name'          => 'filter',
						'description'   => 'SQL-like filter to limit the resources to modify.',
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					array(
						'name'          => 'id_field',
						'description'   => 'Comma-delimited list of the fields used as identifiers or primary keys for the table.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					array(
						'name'          => 'fields',
						'description'   => 'Comma-delimited list of field names to retrieve for each record.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
				),
				'responseMessages' => array(
					array(
						'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'    => 400,
					),
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					array(
						'message' => 'Not Found - Requested table does not exist.',
						'code'    => 404,
					),
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            =>
					'Post data should be a single record or an array of records (shown). ' .
					'By default, only the id property of the record is returned on success. ' .
					'Use \'fields\' parameter to return more info.',
			),
			array(
				'method'           => 'DELETE',
				'summary'          => 'deleteRecords() - Delete one or more records.',
				'nickname'         => 'deleteRecords',
				'type'             => 'Records',
				'event_name'       => '{table_name}.records.delete',
				'parameters'       => array(
					array(
						'name'          => 'table_name',
						'description'   => 'Name of the table to perform operations on.',
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					array(
						'name'          => 'ids',
						'description'   => 'Comma-delimited list of the identifiers of the resources to delete.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					array(
						'name'          => 'filter',
						'description'   => 'SQL-like filter to limit the resources to delete.',
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					array(
						'name'          => 'force',
						'description'   => 'Set force to true to delete all records in this table, otherwise \'ids\' parameter is required.',
						'allowMultiple' => false,
						'type'          => 'boolean',
						'paramType'     => 'query',
						'required'      => false,
						'default'       => false,
					),
					array(
						'name'          => 'id_field',
						'description'   => 'Comma-delimited list of the fields used as identifiers or primary keys for the table.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					array(
						'name'          => 'fields',
						'description'   => 'Comma-delimited list of field names to retrieve for each record.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
				),
				'responseMessages' => array(
					array(
						'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'    => 400,
					),
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					array(
						'message' => 'Not Found - Requested table does not exist.',
						'code'    => 404,
					),
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            =>
					'Use \'ids\' or filter to delete specific records, otherwise set \'force\' to true to clear the table. ' .
					'By default, only the id property of the record is returned on success, use \'fields\' to return more info. ' .
					'Alternatively, to delete by records, a complicated filter, or a large list of ids, ' .
					'use the POST request with X-HTTP-METHOD = DELETE header and post array of records, filter, or ids.',
			),
		),
		'description' => 'Operations for table records administration.',
	),
	array(
		'path'        => '/{api_name}/{table_name}/{id}',
		'operations'  => array(
			array(
				'method'           => 'GET',
				'summary'          => 'getRecord() - Retrieve one record by identifier.',
				'nickname'         => 'getRecord',
				'type'             => 'Record',
				'event_name'       => '{table_name}.record.read',
				'parameters'       => array(
					array(
						'name'          => 'table_name',
						'description'   => 'Name of the table to perform operations on.',
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					array(
						'name'          => 'id',
						'description'   => 'Identifier of the resource to retrieve.',
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					array(
						'name'          => 'properties_only',
						'description'   => 'Return just the properties of the record.',
						'allowMultiple' => true,
						'type'          => 'boolean',
						'paramType'     => 'query',
						'required'      => false,
					),
					array(
						'name'          => 'id_field',
						'description'   => 'Comma-delimited list of the fields used as identifiers or primary keys for the table.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					array(
						'name'          => 'fields',
						'description'   => 'Comma-delimited list of field names to retrieve for each record.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
				),
				'responseMessages' => array(
					array(
						'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'    => 400,
					),
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					array(
						'message' => 'Not Found - Requested table or record does not exist.',
						'code'    => 404,
					),
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'Use the \'fields\' parameter to limit properties that are returned. By default, all fields are returned.',
			),
			array(
				'method'           => 'POST',
				'summary'          => 'createRecord() - Create one record with given identifier.',
				'nickname'         => 'createRecord',
				'type'             => 'Record',
				'event_name'       => '{table_name}.record.create',
				'parameters'       => array(
					array(
						'name'          => 'table_name',
						'description'   => 'Name of the table to perform operations on.',
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					array(
						'name'          => 'id',
						'description'   => 'Identifier of the resource to create.',
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					array(
						'name'          => 'id_field',
						'description'   => 'Comma-delimited list of the fields used as identifiers or primary keys for the table.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					array(
						'name'          => 'body',
						'description'   => 'Data containing name-value pairs of the record to create.',
						'allowMultiple' => false,
						'type'          => 'Record',
						'paramType'     => 'body',
						'required'      => true,
					),
					array(
						'name'          => 'fields',
						'description'   => 'Comma-delimited list of field names to retrieve for each record.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
				),
				'responseMessages' => array(
					array(
						'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'    => 400,
					),
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					array(
						'message' => 'Not Found - Requested table does not exist.',
						'code'    => 404,
					),
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            =>
					'Post data should be an array of fields for a single record. ' .
					'Use the \'fields\' parameter to return more properties. By default, the id is returned.',
			),
			array(
				'method'           => 'PUT',
				'summary'          => 'replaceRecord() - Update (replace) one record by identifier.',
				'nickname'         => 'replaceRecord',
				'type'             => 'Record',
				'event_name'       => '{table_name}.record.replace',
				'parameters'       => array(
					array(
						'name'          => 'table_name',
						'description'   => 'Name of the table to perform operations on.',
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					array(
						'name'          => 'id',
						'description'   => 'Identifier of the resource to update.',
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					array(
						'name'          => 'id_field',
						'description'   => 'Comma-delimited list of the fields used as identifiers or primary keys for the table.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					array(
						'name'          => 'body',
						'description'   => 'Data containing name-value pairs of the replacement record.',
						'allowMultiple' => false,
						'type'          => 'Record',
						'paramType'     => 'body',
						'required'      => true,
					),
					array(
						'name'          => 'fields',
						'description'   => 'Comma-delimited list of field names to retrieve for each record.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
				),
				'responseMessages' => array(
					array(
						'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'    => 400,
					),
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					array(
						'message' => 'Not Found - Requested table or record does not exist.',
						'code'    => 404,
					),
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'Post data should be an array of fields for a single record. Use the \'fields\' parameter to return more properties. By default, the id is returned.',
			),
			array(
				'method'           => 'PATCH',
				'summary'          => 'updateRecord() - Update (patch) one record by identifier.',
				'nickname'         => 'updateRecord',
				'type'             => 'Record',
				'event_name'       => '{table_name}.record.update',
				'parameters'       => array(
					array(
						'name'          => 'table_name',
						'description'   => 'The name of the table you want to update.',
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					array(
						'name'          => 'id',
						'description'   => 'Identifier of the resource to update.',
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					array(
						'name'          => 'id_field',
						'description'   => 'Comma-delimited list of the fields used as identifiers or primary keys for the table.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					array(
						'name'          => 'body',
						'description'   => 'Data containing name-value pairs of the fields to update.',
						'allowMultiple' => false,
						'type'          => 'Record',
						'paramType'     => 'body',
						'required'      => true,
					),
					array(
						'name'          => 'fields',
						'description'   => 'Comma-delimited list of field names to retrieve for each record.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
				),
				'responseMessages' => array(
					array(
						'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'    => 400,
					),
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					array(
						'message' => 'Not Found - Requested table or record does not exist.',
						'code'    => 404,
					),
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            =>
					'Post data should be an array of fields for a single record. ' .
					'Use the \'fields\' parameter to return more properties. By default, the id is returned.',
			),
			array(
				'method'           => 'DELETE',
				'summary'          => 'deleteRecord() - Delete one record by identifier.',
				'nickname'         => 'deleteRecord',
				'type'             => 'Record',
				'event_name'       => '{table_name}.record.delete',
				'parameters'       => array(
					array(
						'name'          => 'table_name',
						'description'   => 'Name of the table to perform operations on.',
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					array(
						'name'          => 'id',
						'description'   => 'Identifier of the resource to delete.',
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					array(
						'name'          => 'id_field',
						'description'   => 'Comma-delimited list of the fields used as identifiers or primary keys for the table.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					array(
						'name'          => 'fields',
						'description'   => 'Comma-delimited list of field names to retrieve for each record.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
				),
				'responseMessages' => array(
					array(
						'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'    => 400,
					),
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					array(
						'message' => 'Not Found - Requested table or record does not exist.',
						'code'    => 404,
					),
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'Use the \'fields\' parameter to return more deleted properties. By default, the id is returned.',
			),
		),
		'description' => 'Operations for single record administration.',
	),
);

return $_base;