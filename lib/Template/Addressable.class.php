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
 * This template implements some of the xAL 2.0 final specifications for 
 * representing physical addresses. Per default, every address is geocoded 
 * using Google's geocoding API. You need to verify that you abide by their 
 * Terms and Conditions, or otherwise disable geocoding by setting the record
 * listener to false.
 * 
 * @see http://code.google.com/intl/en-EN/apis/maps/documentation/geocoding/
 * @see http://code.google.com/apis/maps/terms.html
 * @see http://www.oasis-open.org/committees/ciq/download.html
 * @package jmsDoctrinePlugin
 * @subpackage Template
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Addressable extends Doctrine_Template
{
  /**
   * Indicates that the returned result is a precise geocode for which we 
   * have location information accurate down to street address precision.
   */
  const ACCURACY_ROOFTOP = 4;
  
  /**
   * Indicates that the returned result reflects an approximation (usually on a
   * road) interpolated between two precise points (such as intersections). 
   * Interpolated results are generally returned when rooftop geocodes are 
   * unavailable for a street address.
   */
  const ACCURACY_RANGE_INTERPOLATED = 3;
  
  /**
   * Indicates that the returned result is the geometric center of a result such 
   * as a polyline (for example, a street) or polygon (region).
   */
  const ACCURACY_GEOMETRIC_CENTER = 2;
  
  /**
   * Indicates that the returned result is approximate.
   */
  const ACCURACY_APPROXIMATE = 1;
  
  /**
   * Indicates that the accuracy has not yet been determined.
   */
  const ACCURACY_PENDING = 0;
  
  /**
   * Indicates that the address was not found
   */
  const ACCURACY_NOT_FOUND = -1;
  
  /**
   * The default options for this template
   * @var array
   */
  protected $_options = array(
    'accuracy' => array(
      'name' => 'accuracy',
      'type' => 'integer',
      'size' => 1,
      'default' => self::ACCURACY_PENDING,
      'options' => array(),
      'unsigned' => false,
      'comment' => 'This will be populated with Google\'s assessment of the accuracy of the given address.',           
    ),
    'address' => array(
      'thoroughfare' => array(
        'name' => 'thoroughfare',
        'type' => 'string',
        'size' => 255,
        'options' => array(),                
      ),
      'thoroughfare_number' => array(
        'name' => 'thoroughfare_number',
        'type' => 'integer',
        'size' => 5,
        'options' => array(),
      ),
      'thoroughfare_number_suffix' => array(
        'name' => 'thoroughfare_number_suffix',
        'type' => 'string',
        'size' => 20,
        'options' => array(),
      ),
      'postal_code' => array(
        'name' => 'postal_code',
        'type' => 'string', 
        'size' => 20,
        'options' => array(),
      ),
      'locality' => array(
        'name' => 'locality',
        'type' => 'string',
        'size' => 255,
        'options' => array(),
      ),
      'country_code' => array(
        'name' => 'country_code',
        'type' => 'string',
        'size' => 2,
        'options' => array(),
      ),
    ),
    // you can set the listener to false, if you do not want to determine the
    // accuracy of the address
    'listener' => 'AddressableListener',
  );
  
  /**
   * Add the required columns to the model
   */
  public function setTableDefinition()
  {
    foreach ($this->_options['address'] as $def)
      $this->hasColumnFromOptions($def);
      
    $this->hasColumnFromOptions($this->_options['accuracy']);
  }
    
  /**
   * Add our listener which populates the accuracy field upon saving
   */
  public function setUp()
  {
    if (($listener = $this->getOption('listener')) !== false)
      $this->addListener(new $listener($this->_options));
  }
    
  /**
   * Shortcut for hasColumn()
   * 
   * @param array $def
   */
  private function hasColumnFromOptions($def)
  {
    $this->hasColumn($def['name'], $def['type'], $def['size'], $def['options']);
  }

  /**
   * Returns true if some basic address fields have been set, so we can try a
   * geocoding of the address.
   * 
   * @return boolean
   */
  public function hasAddress()
  {
    $record = $this->getInvoker();
    
    return $record->{$this->_options['address']['thoroughfare']['name']} !== null 
           && $record->{$this->_options['address']['locality']['name']} !== null
           && $record->{$this->_options['address']['country_code']['name']} !== null;
  }
  
  /**
   * Checks whether the current, un-saved address is modified.
   * 
   * @see Doctrine_Record::getModified()
   * @return boolean
   */
  public function isAddressModified()
  {
    return $this->isAddressDataModified($this->getInvoker()->getModified());
  }
  
  /**
   * Checks whether the current, saved address was modified during the last
   * save process.
   * 
   * @see Doctrine_Record::getLastModified()
   * @return boolean
   */
  public function wasAddressModified()
  {
    return $this->isAddressDataModified($this->getInvoker()->getLastModified());
  }
  
  /**
   * Checks whether the address has been modified for the given data array
   * @param array $data
   * @return boolean
   */
  private function isAddressDataModified(array $data)
  {
    foreach ($this->_options['address'] as $def)
      if (array_key_exists($def['name'], $data))
        return true;

    return false;    
  }

  /**
   * Returns an address line representing the address
   * 
   * @return string
   */
  public function getAddressLine()
  {
    $record = $this->getInvoker();  
  
    if (!$record->hasAddress())
      return null;
    
    switch ($record->country_code)
    {
      case 'AU':
      case 'GB':
      case 'US':
        return sprintf('%s %d%s, %s %s', 
          $record->{$this->_options['address']['thoroughfare']['name']}, 
          $record->{$this->_options['address']['thoroughfare_number']['name']}, 
          $record->{$this->_options['address']['thoroughfare_number_suffix']['name']},
          $record->{$this->_options['address']['locality']['name']},         
          $record->{$this->_options['address']['postal_code']['name']}
        );
      
      // TODO: add some more country specific formatting rules here
      default:
        return sprintf('%s %d%s, %s %s', 
          $record->{$this->_options['address']['thoroughfare']['name']}, 
          $record->{$this->_options['address']['thoroughfare_number']['name']}, 
          $record->{$this->_options['address']['thoroughfare_number_suffix']['name']},
          $record->{$this->_options['address']['postal_code']['name']},
          $record->{$this->_options['address']['locality']['name']}
        );
    }
  }
  
  /**
   * Calculates the accuracy of a given address
   * @return void
   */
  public function calculateAccuracy()
  {
    $invoker = $this->getInvoker();
    
    if (!$this->hasAddress())
      return;
    
    $json = json_decode(file_get_contents(
      'http://maps.google.com/maps/api/geocode/json'
      .'?address='.urlencode($this->getAddressLine())
      .'&sensor=false'
    ), true);

    if ($json !== null && $json['status'] === 'OK' && count($json['results']) > 0)
    {
      $address = reset($json['results']);
      $accuracyConstant = 'Addressable::ACCURACY_'.$address['geometry']['location_type'];
      
      if (!defined($accuracyConstant))
        throw new RuntimeException(sprintf(
          'The accuracy type "%s" is invalid.', $locationType));
        
      $invoker->{$this->_options['accuracy']['name']} = constant($accuracyConstant);
      
      if ($invoker->getTable()->hasTemplate('Locatable'))
        $invoker->setCoordinates(
          $address['geometry']['location']['lat'],
          $address['geometry']['location']['lng']
        );
      else if ($invoker->getTable()->hasTemplate('Geographical'))
      {
        $tmplOptions = $invoker->getTable()->getTemplate('Geographical')->getOptions();
        $invoker->{$tmplOptions['latitude']['name']} = $address['geometry']['location']['lat'];
        $invoker->{$tmplOptions['longitude']['name']} = $address['geometry']['location']['lng'];
      }
    }
    else
    {
      $invoker->{$this->_options['accuracy']['name']} = Addressable::ACCURACY_NOT_FOUND;
    }  
  }
}