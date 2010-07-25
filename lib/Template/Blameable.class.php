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
 * Class for easily logging messages related to specific models
 * 
 * @package jmsDoctrinePlugin
 * @subpackage Template
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Blameable extends Doctrine_Template
{
  /**
   * These are the default logging options; per default, everything is logged.
   * 
   * @var array
   */
  protected $_options = array(
    'listener' => true,
    'log_deleted' => true,
    'log_created' => true,
    'log_updated' => true,
    'log_restored' => true,
    'log_activated' => true,
    'log_deactivated' => true,
  );
  
  /**
   * Add our record listener
   */
  public function setUp()
  {
    if ($this->getOption('listener') === true)    
      $this->addListener(new BlameableListener($this->_options));
  }
  
  /**
   * Shortcut for logging a message
   * 
   * @param string $message
   * @param string $visibility
   * @param array $variables
   * @param Doctrine_Record $issuer
   * @return boolean whether the entry has been saved successfully
   */
  public function logMessage($message, $visibility, array $variables = null, Doctrine_Record $issuer = null)
  {
    if (!is_null($variables) && !is_array($variables))
      throw new InvalidArgumentException('$variables must be an array, or null.');
    if (!is_null($issuer) && !($issuer instanceof Doctrine_Record))
      throw new InvalidArgumentException('$issuer must be an instance of Doctrine_Record, or null.');
    if ($issuer !== null && count((array) $issuer->getTable()->getIdentifier()) !== 1)
      throw new RuntimeException('Issuer Entities with composite primary keys are not supported.');  
    if (count((array) $this->getInvoker()->getTable()->getIdentifier()) !== 1)
      throw new RuntimeException('Records with composite primary keys are not supported.');
      
    $logEntry = new BlameableLogEntry();
    $logEntry->target_entity_id = $this->getInvoker()->{$this->getInvoker()->getTable()->getIdentifier()};
    $logEntry->target_entity_type = get_class($this->getInvoker());
    $logEntry->message = $message;
    $logEntry->visibility = $visibility;
    $logEntry->variables = $variables;
     
    if (!is_null($issuer))
    {
      $logEntry->issuer_entity_id = $issuer->{$issuer->getTable()->getIdentifier()};
      $logEntry->issuer_entity_type = get_class($issuer);
    }
    
    $logEntry->save();
    $logEntry->free(true);
  }
}