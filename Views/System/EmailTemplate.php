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
namespace DreamFactory\Platform\Resources\System;

use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Resources\BaseSystemRestResource;
use DreamFactory\Platform\Services\BasePlatformService;

/**
 * EmailTemplate
 * DSP system administration manager
 *
 */
class EmailTemplate extends BaseSystemRestResource
{
	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * @param BasePlatformService $consumer
	 * @param array               $resources
	 */
	public function __construct( $consumer, $resources = array() )
	{
		parent::__construct(
			  $consumer,
			  array(
				  'service_name'   => 'system',
				  'name'           => 'Email Template',
				  'api_name'       => 'email_template',
				  'type'           => 'System',
				  'type_id'        => PlatformServiceTypes::SYSTEM_SERVICE,
				  'description'    => 'System email template administration.',
				  'is_active'      => true,
				  'resource_array' => $resources,
			  )
		);
	}
}
