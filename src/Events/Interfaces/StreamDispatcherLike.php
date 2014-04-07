<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
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
namespace DreamFactory\Platform\Events\Interfaces;

use DreamFactory\Platform\Events\EventDispatcher;

/**
 * StreamDispatcherLike
 * Something that dispatches events to streams
 */
interface StreamDispatcherLike
{
    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param string          $eventName
     * @param array           $eventData
     * @param EventDispatcher $dispatcher
     *
     * @return int The number of streams to which the event was dispatched
     */
    public static function dispatchEventToStream( $eventName, array $eventData = array(), $dispatcher = null );
}