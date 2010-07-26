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
 * This interface can be implemented by objects which are not Doctrine_Record
 * instances, but which should be able to issue locks for Lockable records, or
 * Ratings for Rateable records.
 * 
 * Usually, you would want to implement this interface in your sfUser instance, 
 * so you do not need to manually pass the user to the lock()/rate() methods.
 * 
 * @package jmsDoctrinePlugin
 * @subpackage interface
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface jmsIssuerInterface
{
	/**
	 * Returns a key/value pair array uniquely identifying this instance of the
	 * given object.
	 * 
	 * @return array
	 */
	public function identifier();
}