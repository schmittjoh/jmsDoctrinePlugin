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
 * A common interface for verifying credentials. This is usually implemented by
 * the child class of sfUser, and any proprietary, persistent user class you
 * might have (e.g. sfDoctrineGuardPlugin). 
 * 
 * Implementing this interface allows you to perform different checks depending
 * on the record that is currently being accessed.
 * 
 * @package jmsDoctrinePlugin
 * @subpackage interface
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface jmsCredentialsInterface {
  /**
   * Verifies whether the object has the given credentials
   *  
   * @param mixed $credentials An array or a string
   * @param boolean $useAnd
   * @param Doctrine_Record $record Records passed here always implement the 
   *                                CredentialableTemplate
   * @return boolean
   */
  public function hasCredential($credentials, $useAnd = true, Doctrine_Record $record = null);  
}

