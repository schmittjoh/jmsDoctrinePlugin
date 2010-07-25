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
 * Adds the ability to set passwords for a record.
 * 
 * @package jmsDoctrinePlugin
 * @subpackage Template
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Passwordable extends Doctrine_Template
{
  /**
   * Our default options
   * @var array
   */
  protected $_options = array(
    'password' => array(
      'name' => 'password',
      'type' => 'string',
      'length' => 255,
      'options' => array('default' => null, 'notnull' => false),
    )
  );
  
  /**
   * Make sure mandatory templates are loaded and add a password column
   */
  public function setTableDefinition()
  {
    if ($this->getInvoker()->getTable()->hasTemplate('Timestampable') === false)
      throw new RuntimeException('Your '.get_class($this->getInvoker()).' model must implement the Timestampable template.');
    
    $tmplOptions = $this->getInvoker()->getTable()->getTemplate('Timestampable')->getOptions();
    if ($tmplOptions['created']['disabled'] !== false)
      throw new RuntimeException('The created field must be enabled in the Timestampable template.');
      
    $this->hasColumn(
      $this->_options['password']['name'],
      $this->_options['password']['type'],
      $this->_options['password']['length'],
      $this->_options['password']['options']
    );  
  }  
  
  /**
   * Verifies if the given password matches this model
   * @author Johannes
   * @param string $password The password to verify
   * @return boolean Whether the password matches or not
   */
  public function verifyPassword($password)
  {
    $r = $this->getInvoker();
    $passwordName = $this->_options['password']['name'];
    $created = $r->getTable()->getTemplate('Timestampable')->getOption('created');
    $created_at = $created['name'];
    
    // extract algorithm and fingerprint
    if (($pos = strpos($r->$passwordName, '-')) === false)
      throw new RuntimeException('Password format is incorrect.');
      
    $algo = substr($r->$passwordName, 0, $pos);
    $fingerprint = substr($r->$passwordName, $pos+1);

    // set some variables
    $createdAt = $algo === 'tHash'? 0 : strtotime($r->$created_at);
    $saltLength = bcmod($r->id * $createdAt, $this->getMaxSaltLength() - $this->getMinSaltLength()) + $this->getMinSaltLength();
    if ($saltLength <= $this->getMinSaltLength()) $saltLength = $this->getMinSaltLength();
    
    // check if algorithm is available and re-generate fingerprint
    $fingerprintLength = strlen($this->callAlgorithm($algo, 't'));
    $saltPositionInFingerprint = bcmod($r->id * $createdAt, $fingerprintLength - $saltLength);
    $salt = substr($fingerprint, $saltPositionInFingerprint, $saltLength);
    $saltPositionInPassword = strlen($password)==0? 0 : bcmod($r->id * $createdAt, strlen($password));
    $cFingerprint = $this->callAlgorithm($algo, substr($password, 0, $saltPositionInPassword).$salt.(substr($password, $saltPositionInPassword)).$this->getSalt(), 2);
    
    // compare fingerprints
    return substr($cFingerprint, 0, $saltPositionInFingerprint).$salt.substr($cFingerprint, $saltPositionInFingerprint) === $fingerprint;
  }
  
  /**
   * This is called when someone changes the password, and encrypts it.
   * @param string $password
   * @return void
   */
  public function setPlainPassword($password)
  {
    if ($password === '')
      throw new InvalidArgumentException('$password cannot be empty.');
      
    $this->getInvoker()->{$this->_options['password']['name']} = $this->generateFingerprint($password);
  }
  
  /**
   * Alias for sha1
   * @param string $string
   * @return string
   */
  public function tHash($string)
  {
    return sha1($string);
  }  
  
  private function getMinSaltLength()
  {
    $length = sfConfig::get('app_jmsDoctrinePlugin_minSaltLength');
    if ($length === null)
      throw new RuntimeException('No min salt length configured.');
    
    $length = intval($length);
    if ($length <= 0)
      throw new RuntimeException('Min salt length must be greater than 0.');
    
    return $length;
  }
  
  private function getMaxSaltLength()
  {
    $length = sfConfig::get('app_jmsDoctrinePlugin_maxSaltLength');
    if ($length === null)
      throw new RuntimeException('No max salt length configured.');
    
    $length = intval($length);
    if ($length <= 0)
      throw new RuntimeException('Max salt length must be greater than 0.');
    if ($length <= $this->getMinSaltLength())
      throw new RuntimeException('Max salt length must be greater than min salt length.');
    
    return $length;
  }
  
  private function getSalt()
  {
    $salt = sfConfig::get('app_jmsDoctrinePlugin_salt');
    if ($salt === null)
      throw new RuntimeException('No project salt configured.');
    
    return $salt;
  }
  
  private function getAlgorithms()
  {
    $algos = sfConfig::get('app_jmsDoctrinePlugin_algorithms');
    if ($algos === null)
      throw new RuntimeException('No algorithms configured.');
    if (!is_array($algos))
      $algos = array($algos);
    
    return $algos;
  }
  
  /**
   * Returns an array with available algorithms
   * @return array available algorithms
   */
  private function getAvailableAlgorithms()
  {
    return function_exists('hash_algos')? hash_algos() : array();
  }
  
  /**
   * Calls an algorithm and returns the generated hash
   * 
   * @param string $algorithm The algorithm to use for generating a hash
   * @param string $string The string to generate a hash for
   * @param integer $times The number of times to apply the algorithm
   * @return string The hash after passing it to the algorithm
   * @throws RuntimeException
   */
  private function callAlgorithm($algorithm, $string, $times = 1)
  {
    if (!is_int($times))
      throw new InvalidArgumentException('times must be an integer.');
    if ($times <= 0 || $times > 10)
      throw new InvalidArgumentException('times must be between 1 and 10.');
    
    // check if this algorithm is supported by the hash() function
    $hashAlgos = $this->getAvailableAlgorithms();
    if (in_array($algorithm, $hashAlgos, true))
    {
      for ($i=0;$i<$times;$i++)
        $string = hash($algorithm, $string);
      
      return $string;
    }
      
    // check if model has a method with the algorithm's name
    $r = $this->getInvoker();
    if (is_callable(array($r, $algorithm)))
    {
      for ($i=0;$i<$times;$i++)
        $string = $r->$algorithm($string);
      
      return $string;
    }
      
    // check if there is a function with the algorithm's name
    if (function_exists($algorithm))
    {
      for ($i=0;$i<$times;$i++)
        $string = $algorithm($string);

      return $string;
    }
      
    throw new InvalidAlgorithmException(sprintf("The algorithm '%s' is not available.", $algorithm));    
  }
  
  /**
   * Generates a fingerprint for a password
   * 
   * @param string $password The plain password
   * @param array|string $algos Algorithms to use for generating the fingerprint
   * @return string The fingerprint for the password
   */
  private function generateFingerprint($password, $algos = null)
  {
    if ($algos === null)
      $algos = $this->getAlgorithms();
    if (is_string($algos))
      $algos = array($algos);
    if (!is_array($algos))
      throw new RuntimeException('algos must be an array with algorithms.');
    if (strlen($password) == 0)
      throw new InvalidArgumentException('Password cannot be empty.');
      
    $r = $this->getInvoker();
    $created = $r->getTable()->getTemplate('Timestampable')->getOption('created');
    $created_at = $created['name'];
    $passwordName = $this->_options['password']['name'];
    
    // generate salt
    $createdAt = ($r->$created_at === null || $r->id === null)? 0 : strtotime($r->$created_at);
    $saltLength = bcmod($r->id*$createdAt, $this->getMaxSaltLength() - $this->getMinSaltLength()) + $this->getMinSaltLength();
    if ($saltLength <= $this->getMinSaltLength()) $saltLength = $this->getMinSaltLength();
    $salt = $this->getRandomString($saltLength);
    $saltPosition = bcmod($r->id * $createdAt, strlen($password));
    if ($createdAt === 0)
      $algos = array('tHash');
    
    // generate basic fingerprint
    $fingerprint = null;
    foreach ($algos as $algo) {
      try 
      {
        $length = strlen($this->callAlgorithm($algo, $password));
        $fingerprint = $this->callAlgorithm($algo, substr($password, 0, $saltPosition).$salt.substr($password, $saltPosition) . $this->getSalt(), 2);
        break;
      }
      catch (InvalidAlgorithmException $e) {}
    }
    
    // add salt and used algorithm to the fingerprint
    if ($fingerprint !== null) {
      $saltPosition = bcmod($r->id*$createdAt, strlen($fingerprint)-strlen($salt));
      $fingerprint = $algo.'-'.substr($fingerprint, 0, $saltPosition).$salt.substr($fingerprint, $saltPosition);
    } else 
      throw new RuntimeException(sprintf("None of the algorithms '%s' exists.", implode(',', $algos)));
    
    return $fingerprint;    
  }
  
  /**
   * Generates a random string. Several different algorithms are used to try to
   * get a cryptographically strong, actually random result.
   * 
   * @param int $length The length of the generated string.
   * @return string The generated string.
   */
  private function getRandomString($length) {
    if (!is_int($length))
      throw new InvalidArgumentException('length must be an integer.');
    if ($length <= 0)
      throw new InvalidArgumentException('length must be greater than zero.');
    
    // try OpenSSL's random pseudo bytes function
    if (function_exists('openssl_random_pseudo_bytes'))
    {
      $bytes = openssl_random_pseudo_bytes($length, $strong);
      if ($strong === true && $bytes !== false)
      {
        $hex = bin2hex($bytes);
        return substr($hex, 0, $length);
      }
    }
    
    // use mt_rand to generate our random string
    $chars = 'abcdef0123456789';
    $max = strlen($chars)-1;
    $string = '';
    while (strlen($string) < $length) 
      $string .= substr($chars, mt_rand(0, $max), 1);
      
    return $string;
  }  
}