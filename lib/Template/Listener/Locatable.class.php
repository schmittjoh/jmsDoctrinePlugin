<?php 
/*
 * Copyright 2010 Johannes M. Schmitt
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * The listener for the Locatable template which calculates cartesian 
 * coordinates when latitude/longitude are updated directly, and not by
 * calling setCoordinates().
 * 
 * @package jmsDoctrinePlugin
 * @subpackage Listener
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class LocatableListener extends Doctrine_Record_Listener
{
  /**
   * Constructor. Update our options.
   * 
   * @param array $options
   */
  public function __construct(array $options = array())
  {
    $this->_options = $options;
  }
  
  /**
   * Update cartesian coordinates
   */
  public function preSave(Doctrine_Event $event)
  {
    $invoker = $event->getInvoker();
    
    $longitude = $this->_options['longitude']['name'];
    $latitude = $this->_options['latitude']['name'];
    
    // calculate cartesian coordinates which are used to speed up radius searches
    if ($invoker->$longitude !== null && $invoker->$latitude !== null)
      $invoker->calculateCoordinates($invoker->$latitude, $invoker->$longitude);
  }
}