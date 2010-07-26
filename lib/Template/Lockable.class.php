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
 * Allows an issuer to lock a record instance. The lock must be enforced
 * by your application code. This template only provides some utility methods
 * to cover the persistence part.
 * 
 * @package jmsDoctrinePlugin
 * @subpackage Template
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Lockable extends Doctrine_Template
{
  protected $_options = array(
    'locked_at' => array(
      'name' => 'locked_at',
      'type' => 'timestamp',
      'length' => null,
      'options' => array('default' => null),
    ),
    'locked_by' => array(
      'name' => 'locked_by',
      'type' => 'string',
      'length' => '255',
      'options' => array('notnull' => false, 'default' => null)
    ),
    'expires_after' => 300, // in seconds
  );
  
  public function setTableDefinition()
  {
    $this->hasColumnFromOption('locked_at');
    $this->hasColumnFromOption('locked_by');
  }
  
  private function hasColumnFromOption($name)
  {
    $def = $this->getOption($name);
    $this->hasColumn($def['name'], $def['type'], $def['length'], $def['options']);
  }
  
  private function getIdentifier($record)
  {
  	if (!$record instanceof Doctrine_Record 
  	    && !$record instanceof jmsIssuerInterface)
  	    throw new InvalidArgumentException(
  	      '$record must be an instance of Doctrine_Record, or jmsIssuerInterface.');
  	
  	$identifier = $record->identifier();
  	ksort($identifier);
  	
  	return get_class($record).'.'.implode('.', $identifier);
  }
  
  /**
   * Locks this record for the given issuer.
   * 
   * @param mixed $issuer Either a Doctrine_Record, or any object which
   *         implements the jmsIssuerInterface.
   * @throws jmsRecordAlreadyLockedException when the record has already been
   *         locked by another issuer
   */
  public function lock($issuer = null)
  {
  	if (
  	  $issuer === null && sfContext::hasInstance() 
  	  && sfContext::getInstance()->getUser() instanceof jmsIssuerInterface
  	)
  	  $issuer = sfContext::getInstance()->getUser();
  	
  	if ($issuer === null)
  	  throw new InvalidArgumentException('$issuer must be passed.');
  	  
  	$record = $this->getInvoker();
  	$lockedAt = $this->_options['locked_at']['name'];
  	$lockedBy = $this->_options['locked_by']['name'];
    $identifier = $this->getIdentifier($issuer);
  	
  	if ($this->isLocked() && $record->$lockedBy !== $identifier)
  	  throw new jmsRecordAlreadyLockedException(
  	    'The record is already locked by another issuer.');
    
    $record->$lockedAt = date('Y-m-d H:i:s');
    $record->$lockedBy = $identifier;
  }
  
  /**
   * Release the lock from this record. 
   */
  public function releaseLock()
  {
    $record = $this->getInvoker();
    $lockedAt = $this->_options['locked_at']['name'];
    $lockedBy = $this->_options['locked_by']['name'];
    
    $record->$lockedAt = null;
    $record->$lockedBy = null;
  }
  
  /**
   * Whether this record is locked at the moment
   * @return boolean
   */
  public function isLocked()
  {
    $record = $this->getInvoker();
    $lockedAt = $this->_options['locked_at']['name'];
    $lockedBy = $this->_options['locked_by']['name'];
    
    return $record->$lockedAt !== null
           && strtotime($record->$lockedAt) + $this->getOption('expires_after') 
              > time();
  }
  
  /**
   * Whether the given user has currently a lock for this record
   * 
   * @param mixed $issuer either a Doctrine_Record, or any object which
   *        implements the jmsLockIssuerInterface.
   * @return boolean
   */
  public function hasLock($issuer)
  {
  	return $this->isLocked() 
  	       && $this->getInvoker()->{$this->_options['locked_by']['name']} 
  	          === $this->getIdentifier($issuer);
  }
}