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
 * Provides some status information about a record
 * 
 * @package jmsDoctrinePlugin
 * @subpackage Template
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * TODO: Incorporate information about Lockable template once that is open 
 *       sourced
 */
class AdvancedStatus extends Doctrine_Template
{
  const ACTIVE = 1;
  const DELETED = 2;
  const DEACTIVATED = 3;
  
  /**
   * Returns the status of this record based on the templates that are
   * implemented.
   * 
   * @return integer 
   */
  public function getStatus()
  {
    $r = $this->getInvoker();
    $t = $r->getTable();
    
    if ($t->hasTemplate('SoftDelete') && $r->{$t->getTemplate('SoftDelete')->getOption('name')} !== null)
      return self::DELETED;
    
    if ($t->hasTemplate('Deactivatable') && $r->isDeactivated())
      return self::DEACTIVATED;
      
    return self::ACTIVE;    
  }  
}