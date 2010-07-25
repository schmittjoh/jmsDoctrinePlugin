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
 * Adds cartesian coordinates in addition to latitude/longitude which are
 * already added by the Geographical template. Cartesian coordinates are more
 * efficient/exact when performing radius searches, or calculating distances.
 * 
 * However, if your RDMS system supports a native geographic type, it's 
 * probably better to use that. 
 * 
 * @package jmsDoctrinePlugin
 * @subpackage Template
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * TODO: Add UTM support (for MySQL) either optional via this template, or via
 *       its own dedicated template
 */
class Locatable extends Doctrine_Template_Geographical
{
  const EARTH_RADIUS = 6371000.785;
  
  /**
   * Our extended default options in addition to the default options of the
   * Geographical template.
   * 
   * @var array
   */
  protected $_extendedOptions = array(
      'x_coordinate' => array(
        'name' => 'x_coordinate',
        'type' => 'decimal',
        'size' => 18,
        'options' => array('scale' => 7),
      ),
      'y_coordinate' => array(
        'name' => 'y_coordinate',
        'type' => 'decimal',
        'size' => 18,
        'options' => array('scale' => 7),
      ),
      'z_coordinate' => array(
        'name' => 'z_coordinate',
        'type' => 'decimal',
        'size' => 18,
        'options' => array('scale' => 7),
      ),
      'latitude' =>  array(
        'name' => 'latitude',
        'type' => 'decimal',
        'size' => 18,
        'options' =>  array('scale' => 7)
      ),
      'longitude' => array(
        'name' => 'longitude',
        'type' => 'decimal',
        'size' => 18,
        'options' => array('scale' => 7)
      ),
      'listener' => 'LocatableListener',
      'radiusSearchIndex' => 'locatableRadiusSearch',
    );
  
  /**
   * Add our extended options before continuing with normal processing.
   * 
   * @param array $options
   */
  public function __construct(array $options = array())
  {
    $this->_options = Doctrine_Lib::arrayDeepMerge(
      $this->_options, 
      $this->_extendedOptions
    );
      
    parent::__construct($options);
  }
    
  /**
   * Add columns for cartesian coordinates, and an index for performance
   */
  public function setTableDefinition()
  {
    $this->hasColumn(
      $this->_options['x_coordinate']['name'], 
      $this->_options['x_coordinate']['type'], 
      $this->_options['x_coordinate']['size'], 
      $this->_options['x_coordinate']['options']
    );
    $this->hasColumn(
      $this->_options['y_coordinate']['name'], 
      $this->_options['y_coordinate']['type'], 
      $this->_options['y_coordinate']['size'], 
      $this->_options['y_coordinate']['options']
    );
    $this->hasColumn(
      $this->_options['z_coordinate']['name'], 
      $this->_options['z_coordinate']['type'], 
      $this->_options['z_coordinate']['size'], 
      $this->_options['z_coordinate']['options']
    );
      
    $this->index(
      $this->_options['radiusSearchIndex'], 
      array( 
        'fields' => array(
          $this->_options['x_coordinate']['name'], 
          $this->_options['y_coordinate']['name'], 
          $this->_options['z_coordinate']['name'],
        ),
        'unique' => false,  
      )
    );
  }
    
  /**
   * Add our record listener
   */
  public function setUp()
  {
    if (($listener = $this->_options['listener']) !== false)
      $this->addListener(new $listener($this->_options));
  }
    
  /**
   * Sets spheric coordinates in one method call
   * 
   * @param float $latitude
   * @param float $longitude
   * @return void
   */
  public function setCoordinates($latitude, $longitude)
  {
    $invoker = $this->getInvoker();
    $invoker->{$this->_options['latitude']['name']} = $latitude;
    $invoker->{$this->_options['longitude']['name']} = $longitude;
    
    $this->calculateCoordinates();
  }
    
  /**
   * Calculates all missing cartesian coordinates
   * @return void
   */
  public function calculateCoordinates()
  {
    $invoker = $this->getInvoker();
    
    if ($invoker->{$this->_options['latitude']['name']} === null 
        || $invoker->{$this->_options['longitude']['name']} === null
    )
      return;
      
    list(
      $invoker->{$this->_options['x_coordinate']['name']},
      $invoker->{$this->_options['y_coordinate']['name']},
      $invoker->{$this->_options['z_coordinate']['name']}
    ) = $this->sphere2cartesian($invoker->{$this->_options['latitude']['name']}, $invoker->{$this->_options['longitude']['name']});
  }

  /**
   * Transforms spheric to cartesian coordinates
   * 
   * @param double $lat
   * @param double $lon
   * @return array
   */    
  private function sphere2cartesian($lat, $lon)
  {
    $lambda = $lon * pi() / 180;
    $phi = $lat * pi() / 180; 
    $x = self::EARTH_RADIUS * cos($phi) * cos($lambda);
    $y = self::EARTH_RADIUS * cos($phi) * sin($lambda);
    $z = self::EARTH_RADIUS * sin($phi); 

    return array($x, $y, $z);    
  }
}