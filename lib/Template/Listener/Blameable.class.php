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
 * Record Listener that adds some default log messages
 * 
 * @package jmsDoctrinePlugin
 * @subpackage Listener
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class BlameableListener extends Doctrine_Record_Listener
{
  protected $_options;
  
  public function __construct($options)
  {
    $this->_options = $options;
  }
  
  public function postInsert(Doctrine_Event $event)
  {
    if(!$this->_options['log_created']) return;
    
    $this->logMessage($event, 'public', 'created');
  }
  
  public function preUpdate(Doctrine_Event $event)
  {
    $modified = $event->getInvoker()->getModified(true);
    
    if ($event->getInvoker()->getTable()->hasTemplate('SoftDelete'))
    {
      $template = $event->getInvoker()->getTable()->getTemplate('SoftDelete');
      if (array_key_exists($template->getOption('name'), $modified))
      {
        if ($event->getInvoker()->{$template->getOption('name')} === null && $this->_options['log_restored'])
          $this->logMessage($event, 'public', 'restored');
        
        unset($modified[$template->getOption('name')]);
      }
    }
    
    if ($event->getInvoker()->getTable()->hasTemplate('Deactivatable'))
    {
      $options = $event->getInvoker()->getTable()->getTemplate('Deactivatable')->getOptions();
      if (array_key_exists($options['from']['name'], $modified))
      {
        if ($event->getInvoker()->{$options['from']['name']} === null && $this->_options['log_activated'])
          $this->logMessage($event, 'public', 'activated');
        elseif ($event->getInvoker()->{$options['from']['name']} !== null && $this->_options['log_deactivated'])
          $this->logMessage($event, 'public', 'deactivated', array('from' => $event->getInvoker()->{$options['from']['name']}, 'until' => $event->getInvoker()->{$options['until']['name']}));
        
        unset($modified[$options['from']['name']], $modified[$options['until']['name']]);
      }
    }
    
    if (count($modified) == 0 || !$this->_options['log_updated']) 
      return;

    $this->logMessage($event, 'public', 'updated', $event->getInvoker()->getModified());
  }
  
  public function preDelete(Doctrine_Event $event)
  {
    if(!$this->_options['log_deleted']) return;
    
    if ($event->getInvoker()->getTable()->hasTemplate('SoftDelete'))
      $this->logMessage($event, 'public', 'deleted');
    else
      Doctrine::getTable('LogEntry')->findByTargetEntityIdAndTargetEntityType(
        $event->getInvoker()->{$event->getInvoker()->getTable()->getIdentifier()}, 
        get_class($event->getInvoker())
      )->delete();
  }
  
  protected function logMessage($event, $visibility, $message, $variables = null)
  {
    $logEntry = new BlameableLogEntry();
    $record = $event->getInvoker();
    $pKey = $record->getTable()->getIdentifier();
    if (is_array($pKey))
      throw new RuntimeException('Records with multi-column primary keys are not supported.');
    
    $logEntry->target_entity_id = $record->$pKey;
    $logEntry->target_entity_type = get_class($record);
    
    // TODO: Allow the user to specify a way how the acting user id is being determined
    if (sfContext::hasInstance() 
        && sfContext::getInstance()->getUser() instanceof jmsUser
        && sfContext::getInstance()->getUser()->isAuthenticated())
    {      
      $logEntry->issuer_entity_id = sfContext::getInstance()->getUser()->id;
      $logEntry->issuer_entity_type = 'User';
    }
    
    $logEntry->message = $message;
    $logEntry->visibility = $visibility;
    $logEntry->variables = $variables;
    $logEntry->save();
    $logEntry->free(true);
  }
}