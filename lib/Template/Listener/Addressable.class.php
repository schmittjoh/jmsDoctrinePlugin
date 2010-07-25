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
 * Record Listener
 * 
 * @package jmsDoctrinePlugin
 * @subpackage Listener
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class AddressableListener extends Doctrine_Record_Listener
{
  /**
   * Constructor
   * @param array $options
   */
  public function __construct(array $options = array())
  {
    $this->_options = $options;
  }

  /**
   * Automatically calculates the accuracy of an address. The accuracy is not 
   * recalculated unless the address changes.
   */
  public function preSave(Doctrine_Event $event)
  {
    $invoker = $event->getInvoker();
    $accuracy = $this->_options['accuracy']['name'];
    
    if ($invoker->$accuracy == Addressable::ACCURACY_PENDING 
        || $invoker->isAddressModified())
      $invoker->calculateAccuracy();
  }
}