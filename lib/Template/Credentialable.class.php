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
 * This template allows you to specify credentials for accessing specific
 * instances of this model, and also to specify credentials for accessing
 * specific fields of the model.
 * 
 * @package jmsDoctrinePlugin
 * @subpackage Template
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Credentialable extends Doctrine_Template
{
  /**
   * These options are merged with the settings in the schema.yml when this 
   * template is instantiated. You can disable a right by setting it to false.
   * There are four basic credentials which you can refine by overwriting the 
   * default methods:
   * Create, View, Edit, and Delete 
   * 
   * @var $options The default options
   */
  protected $options = array(
    'entityType' => null,
    'credentials' => array(
      'manager' => array(
        'name' => 'Exercise full control over a record',
        'description' => 'Can execute all possible actions on the record',
        'subrights' => array('create', 'view', 'edit', 'delete'),
      ),
      'create' => array(
        'name' => 'Create new record',
        'description' => 'Can create new records',
      ),
      'view' => array(
        'name' => 'View record',
        'description' => 'Can view records',
      ),
      'edit' => array(
        'name' => 'Edit record',
        'description' => 'Can edit records',
        'implies' => array('view'),
      ),
      'delete' => array(
        'name' => 'Delete record',
        'description' => 'Can delete records',
        'implies' => array('view'),
      ),
    ),
    'fields' => array(),
  );
    
  /**
   * These are special options in case the model implements the SoftDelete 
   * template. This adds another basic access right "Restore" as well as two 
   * refinements to the "view" access right.
   * 
   * @var array $optionsWhenSoftDeleteIsEnabled
   */
  protected $optionsWhenSoftDeleteIsEnabled = array(
    'credentials' => array(
      'restore' => array(
        'name' => 'Restore record',
        'description' => 'Can restore records',
        'implies' => array('view_deleted'),
        'subright_of' => array('manager'),
      ),
      'view_undeleted' => array(
        'name' => 'View un-deleted records',
        'description' => 'Can view un-deleted records',
        'subright_of' => array('view'),          
      ),
      'view_deleted' => array(
        'name' => 'View deleted records',
        'description' => 'Can view deleted records',
        'subright_of' => array('view'),
      ),
    ),
  );
  
  /**
   * Default credentials options
   * @var array
   */
  protected $credentialOptions = array(
    'name' => null,
    'description' => null,
    'subrights' => array(),
    'subright_of' => array(),
    'implies' => array(),
    'implied_by' => array(),
  );
  
  /**
   * Here, you can set a special credential that users must have to edit, or 
   * view a certain field of the model. If you do not change default options, 
   * a user with view permission can view all fields, and a user with edit 
   * permission can edit all fields. If you set it to false, a field cannot be 
   * viewed, or editted.
   * 
   * @var $fieldOptions Default options for each field
   */
  protected $fieldOptions = array(
    'view' => null,
    'create' => null,
    'edit' => null,
  );
  
  /**
   * Initialize the template
   * @return void
   */
  public function setUp()
  {
    // TODO: cache compiled options since they do not change frequently
    $options = $this->options;
    
    // merge in special soft delete options
    if ($this->getInvoker()->getTable()->hasTemplate('SoftDelete'))
      $options = Doctrine_Lib::arrayDeepMerge($options, $this->optionsWhenSoftDeleteIsEnabled);
    
    // merge in options defined in the schema
    $options = Doctrine_Lib::arrayDeepMerge($options, $this->_options);
    
    // merge in default options for credentials
    foreach ($options['credentials'] as $credential => $cOptions)
      $options['credentials'][$credential] = array_merge($this->credentialOptions, $cOptions);
    
    // merge in default options for fields
    foreach ($options['fields'] as $fieldName => $fieldOptions)
    {
      $fieldOptions = array_merge($this->fieldOptions, $fieldOptions);
      
      if (!is_string($fieldOptions['view']) && !is_null($fieldOptions['view']) && $fieldOptions['view'] !== false)
        throw new InvalidArgumentException(sprintf('view credential has an invalid type for field %s.', $fieldName));
      if (!is_string($fieldOptions['edit']) && !is_null($fieldOptions['edit']) && $fieldOptions['edit'] !== false)
        throw new InvalidArgumentException(sprintf('edit credential has an invalid type for field %s.', $fieldName));
      if (!is_string($fieldOptions['create']) && !is_null($fieldOptions['create']) && $fieldOptions['create'] !== false)
        throw new InvalidArgumentException(sprintf('create credential has an invalid type for field %s.', $fieldName));
              
      $options['fields'][$fieldName] = $fieldOptions;
    }
    
    $options['credentials'] = $this->copyRelationsToEachSide($options['credentials']);
    
    $this->options = $options;
  }
  
  /**
   * This function synchronizes credential relations across all sides
   * 
   * @param array $credentials
   * @return array
   */
  private function copyRelationsToEachSide($credentials)
  {
    // ok, mirror relations to each side
    // if they have not been specified there
    do
    {
      $lastHash = md5(serialize($credentials));
      
      foreach ($credentials as $credential => $options)
      {
        foreach ($options['implied_by'] as $iCredential)
        {
          if (!isset($credentials[$iCredential]))
            throw new sfConfigurationException(sprintf('%s cannot be implied by %s since it does not exist.', $credential, $iCredential));
          
          $credentials[$iCredential]['implies'] = array_unique(array_merge($credentials[$iCredential]['implies'], array($credential), $options['implies']));
          sort($credentials[$iCredential]['implies']);
        }
        
        foreach ($options['implies'] as $iCredential)
        {
          if (!isset($credentials[$iCredential]))
            throw new sfConfigurationException(sprintf('%s cannot be implied by %s since it does not exist.', $credential, $iCredential));
          
          $credentials[$iCredential]['implied_by'] = array_unique(array_merge($credentials[$iCredential]['implied_by'], array($credential), $options['implied_by']));
          sort($credentials[$iCredential]['implied_by']);
        }
        
        foreach ($options['subrights'] as $iCredential)
        {
          if (!isset($credentials[$iCredential]))
            throw new sfConfigurationException(sprintf('%s cannot be a parent right of %s since it does not exist.', $credential, $iCredential));
          
          $credentials[$iCredential]['subright_of'] = array_unique(array_merge($credentials[$iCredential]['subright_of'], array($credential), $options['subright_of']));
          $credentials[$iCredential]['implied_by'] = array_unique(array_merge($credentials[$iCredential]['implied_by'], array($credential), $options['implied_by']));
          sort($credentials[$iCredential]['subright_of']);
          sort($credentials[$iCredential]['implied_by']);
        }
        
        foreach ($options['subright_of'] as $iCredential)
        {
          if (!isset($credentials[$iCredential]))
            throw new sfConfigurationException(sprintf('%s cannot be a sub right of %s since it does not exist.', $credential, $iCredential));
          
          $credentials[$iCredential]['subrights'] = array_unique(array_merge($credentials[$iCredential]['subrights'], array($credential), $options['subrights']));
          $credentials[$iCredential]['implies'] = array_unique(array_merge($credentials[$iCredential]['implies'], array($credential), $options['implies']));
          sort($credentials[$iCredential]['subrights']);
          sort($credentials[$iCredential]['implies']);
        }
      }    
  
    } while ($lastHash != md5(serialize($credentials)));
    
    return $credentials;
  }
  
  /**
   * Returns an entity type for the invoker
   * 
   * @return string
   */
  public function getEntityType() 
  {
    return $this->options['entityType']===null? 
            get_class($this->getInvoker()) 
            : $this->options['entityType'];
  }
  
  /**
   * Checks whether the user has a given access right for a given object
   * 
   * @param string $accessType
   * @param mixed $user This can be an instance of sfUser, or any object which
   *                    implements the jmsCredentialsInterface
   * @return boolean
   */
  public function hasAccess($accessType, $user = null)
  {
    $credentials = $this->getInvoker()->getAccessCredentials($accessType);
    
    return $this->hasRequiredCredentials($user, $credentials, false);
  }
  
  /**
   * Alias for getAccessCredentials but callable from the table
   * 
   * @param string $accessType
   * @return array required credentials list
   */
  public function getAccessCredentialsTableProxy($accessType)
  {
    return $this->getAccessCredentials($accessType);
  }
  
  /**
   * Returns the required credential for a given access right
   * 
   * @param string $accessType
   * @return array required credentials list
   */
  public function getAccessCredentials($accessType)
  {
    if (!isset($this->options['credentials'][$accessType]))
      throw new RuntimeException(sprintf('Unknown access type "%s".', $accessType));

    // check if there exists a custom function to return the requested credentials
    try {
      $credential = $this->getInvoker()->{'get'.ucfirst($accessType).'AccessCredentials'}();
    } 
    // if there is no such function, we got to use our default values
    catch (Exception $e) 
    {
      $credential = $accessType;
    }
    
    // get parent credentials if any exist
    return $this->getInvoker()->formatCredentials(
             isset($this->options['credentials'][$credential])? 
             array_unique(array_merge(
                array($credential), 
                $this->options['credentials'][$credential]['implied_by']
             )) 
             : array($credential));
  }
  
  /**
   * Alias for getFieldAccessCredentials but callable from the table
   * 
   * @param string $fieldName
   * @param string $accessType
   * @return array|false credentials list
   */
  public function getFieldAccessCredentialsTableProxy($fieldName, $accessType)
  {
    return $this->getFieldAccessCredentials($fieldName, $accessType);
  }
  
  /**
   * Returns the required credentials for the given access type and the given field
   * 
   * @param string $fieldName
   * @param string $accessType
   * @return array|false credentials list
   */
  public function getFieldAccessCredentials($fieldName, $accessType)
  {
    try {
      $credential = $this->getInvoker()->{'get'.ucfirst($accessType).'FieldAccessCredentials'}($fieldName);
      if ($credential === false)
        return 'false';
    } 
    catch (Exception $e)
    {
      // if there is no special access defined, just return an empty array
      // note: isset also returns false if the variable is NULL
      if (!isset($this->options['fields'][$fieldName][$accessType]) 
        || is_null($this->options['fields'][$fieldName][$accessType]))
        return array();
        
      // if access is generally forbidden
      if ($this->options['fields'][$fieldName][$accessType] === false)
        return 'false';

      $credential = $this->options['fields'][$fieldName][$accessType];
    }

    return $this->getInvoker()->formatCredentials(
             isset($this->options['credentials'][$credential])? 
             array_unique(array_merge(
               array($credential), 
               $this->options['credentials'][$credential]['implied_by']
             )) 
             : array($credential));
  }
  
  /**
   * Custom function to return different credentials depending
   * on whether the underlying record has been deleted or not.
   * 
   * @return string Required credential to view the record
   */
  public function getViewAccessCredentials()
  {
    if ($this->getInvoker()->getTable()->hasTemplate('SoftDelete'))
    {
      $tmplOptions = $this->getInvoker()->getTable()->getTemplate('SoftDelete');

      return $this->getInvoker()->{$tmplOptions['name']} !== null?
               'view_deleted' : 'view_undeleted';
    }
    
    return 'view';
  }
  
  /**
   * Formats the given credential
   * @param string $credential
   * @return string
   */
  public function formatCredentials($credentials)
  {
    if (is_array($credentials))
    {
      foreach ($credentials as $i => $credential)
        $credentials[$i] = $this->formatCredentials($credential);
        
      return $credentials;
    }
    else if (is_string($credentials))
    {
      // check if this credential is only available in this module
      // if so, prefix it with the module name
      if (array_key_exists($credentials, $this->options['credentials']))
        return $this->getInvoker()->getEntityType().'___'.$credentials;
      
      return $credentials;
    }
    else
      throw new RuntimeException('Invalid credentials type: '.var_export($credentials, true));
  }
  
  /**
   * Checks whether the user has a given access right for a given field.
   * In order for this to return true, the user needs the global right of
   * the same access type in addition to the local field right.
   * 
   * @param string $fieldName
   * @param string $accessType
   * @param User $user
   * @return boolean
   */
  public function hasFieldAccess($fieldName, $accessType, $user = null)
  {
    $credential = $this->getInvoker()->getFieldAccessCredentials($fieldName, $accessType);
    if ($credential === false)
      return false;
    
    $credentials = array($this->getInvoker()->getAccessCredentials($accessType), $credential);  
    
    return $this->hasRequiredCredentials($user, $credentials, true);
  }
  
  /**
   * Checks whether the given user has the required credentials
   * 
   * @param mixed $user
   * @param mixed $credentials
   * @param boolean $useAnd
   * @return boolean
   */
  private function hasRequiredCredentials($user, $credentials, $useAnd)
  {
    if ($user === null && sfContext::hasInstance())
      $user = sfContext::getInstance()->getUser();
      
    if ($user instanceof jmsCredentialsInterface)
    {
      return $user->hasCredential($credentials, $useAnd, $this->getInvoker());
    }     
    else if ($user instanceof sfBasicSecurityUser)
    {
      return $user->hasCredential($credentials, $useAnd);
    }
    else
      throw new InvalidArgumentException(
        '$user must be an instance of sfBasicSecurityUser, or any object '
       .'implementing the jmsCredentialsInterface.');
  }
}