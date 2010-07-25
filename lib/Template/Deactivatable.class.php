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
 * This template implements some useful methods for activating/deactivating
 * models.
 * 
 * There are four different de-activation cases:
 * 
 * 1. Permanent deactivation starting at a given point (might be now):
 *    until = null, from = DateTime
 *    
 * 2. Permanent deactivation except for a finite amount of time where the
 *    record is activated:
 *    until = DateTime, from = DateTime, until < from
 *    
 * 3. Temporary deactivation which ends sometime in the future:
 *    until = DateTime, from = null
 * 
 * 4. Temporary deactivation which starts, and ends sometime in the future
 *    until = DateTime, from = DateTime, until > from
 * 
 * @package jmsDoctrinePlugin
 * @subpackage Template
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Deactivatable extends Doctrine_Template
{
  /**
   * Our default options
   * @var unknown_type
   */
  protected $_options = array(
    'from' => array(
      'name' => 'deactivated_at',
      'type' => 'timestamp',
      'length' => null,
      'options' => array('default' => null, 'notnull' => false),
    ),
    'until' => array(
      'name' => 'deactivated_until',
      'type' => 'timestamp',
      'length' => null,
      'options' => array('default' => null, 'notnull' => false),
    ),
  );
  
  /**
   * Add some columns 
   */
  public function setTableDefinition()
  {
    $this->hasColumn(
      $this->_options['from']['name'], 
      $this->_options['from']['type'],
      $this->_options['from']['length'],
      $this->_options['from']['options']
    );
    
    $this->hasColumn(
      $this->_options['until']['name'],
      $this->_options['until']['type'],
      $this->_options['until']['length'],
      $this->_options['until']['options']
    );
  }
  
  /**
   * Activates the record, and immediately saves it
   */
  public function activate()
  {
    $r = $this->getInvoker();
    $r->{$this->_options['from']['name']} = null;
    $r->{$this->_options['until']['name']} = null;
    $r->save();   
  }
  
  /**
   * Deactivates the record, and immediately saves the changes. See above, for 
   * which values of $until, and $from have which effect.
   * 
   * @param DateTime $until
   * @param DateTime $from
   * @return void
   */
  public function deactivate(DateTime $until = null, DateTime $from = null)
  {
    // if nothing is passed, we assume the user wants to deactivated the record
    // from now on, and forever
    if ($from === null && $until === null)
      $from = new DateTime();
    
    $record = $this->getInvoker();
    $record->{$this->_options['from']['name']} = $from === null?
                                                  null : $from->format('Y-m-d H:i:s');
    $record->{$this->_options['until']['name']} = $until === null? 
                                                  null : $until->format('Y-m-d H:i:s');
    $record->save();
  }
  
  /**
   * Whether this record is deactivated at the given time, or now if none is 
   * passed.
   * 
   * @param DateTime $refTime
   * @return boolean
   */
  public function isDeactivated(DateTime $refTime = null)
  {
    if ($refTime === null)
      $refTime = new DateTime();
    
    $record = $this->getInvoker();
    $from = $this->_options['from']['name'];
    $until = $this->_options['until']['name'];
    
    // check if this record is deactivated sometime
    if ($this->isDeactivatedSometime() === false)
      return false;
    
    // check if the deactivated starts in the future
    if ($record->$from !== null && strtotime($record->$from) > $refTime->getTimestamp())
      return false;
    
    // so, it's deactivated forever
    if ($record->$until === null)
      return true;
    
    // check if the deactivation has already expired
    return strtotime($record->$until) > $refTime->getTimestamp();
  }
  
  /**
   * Check if this record has been deactivated somewhere in the given period.
   * 
   * Note that the behavior of DatePeriod objects is not really what you 
   * expect at first as every iteration over the period will change the object.
   * So, you should never re-use instances of DatePeriod.
   * 
   * @param DatePeriod $period
   * @return boolean
   */
  public function isDeactivatedWithin(DatePeriod $period)
  {
    $record = $this->getInvoker();
    foreach ($period as $day)
    {
      if ($record->isDeactivated($day))
        return true;
    }
    
    return false;
  } 
  
  /**
   * Whether this record is/was deactivated sometime.
   * @return boolean
   */
  public function isDeactivatedSometime()
  {
    $invoker = $this->getInvoker();
    
    return $invoker->{$this->_options['from']['name']} !== null 
           || $invoker->{$this->_options['until']['name']} !== null;
  }
  
  /**
   * Whether this record is deactivated for a finite amount of time.
   * @return boolean
   */
  public function isDeactivatedTemporarily()
  {
    if ($this->isDeactivatedSometime() === false)
      return false;
      
    $invoker = $this->getInvoker();
    $from = $this->_options['from']['name'];
    $until = $this->_options['until']['name'];
    
    return (
             $invoker->$from !== null 
             && $invoker->$until !== null
             && strtotime($invoker->$from) < strtotime($invoker->$until)
           )
            ||
            (
             $invoker->$from === null 
             && $invoker->$until !== null
           );
  }
  
  /**
   * Whether this record is deactivated permanently
   * @return boolean
   */
  public function isDeactivatedPermanently()
  {
    if ($this->isDeactivatedSometime() === false)
      return false;
      
    return !$this->isDeactivatedTemporarily();
  }
  
  /**
   * Returns the activated period if the record is deactivated permanently.
   * @return DatePeriod
   */
  public function getActivatedPeriod()
  {
    if ($this->isDeactivatedPermanently() === false)
      throw new LogicException('The record is not deactivated permanently, and therefore has no finite, active period.');
    
    $invoker = $this->getInvoker();
    
    return new DatePeriod(
      new DateTime($invoker->{$this->_options['until']['name']}),
      new DateInterval('P1D'),
      new DateTime($invoker->{$this->_options['from']['name']})
    );
  }
  
  /**
   * Returns the deactivated period if the record is deactivated temporarily.
   * @return DatePeriod
   */
  public function getDeactivatedPeriod()
  {
    if ($this->isDeactivatedTemporarily() === false)
      throw new LogicException('The record is not deactivated temporarily, and therefore has no finite, deactivated period.');
      
    $invoker = $this->getInvoker();
    
    return new DatePeriod(
      new DateTime($invoker->{$this->_options['from']['name']}),
      new DateInterval('P1D'),
      new DateTime($invoker->{$this->_options['until']['name']})
    );
  }
}