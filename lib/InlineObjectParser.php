<?php

/**
 * Parses a string and converts inline syntax into InlineObject instances
 * 
 * This takes in raw input and detects inline object syntax. The inline
 * object syntax are converted into InlineObject instances, which are
 * then rendered.
 * 
 * The final output is a processed string
 * 
 * @package     InlineObjectParser
 * @author      Ryan Weaver <ryan@thatsquality.com>
 * @author      Jonathan H. Wage <jonwage@gmail.com>
 */

class InlineObjectParser
{
  /**
   * @var array A map of type => class that identifies the class to use
   *            for each inline type
   */
  protected $_types = array();

  /**
   * Class constructor
   */
  public function __construct($types = array())
  {
    $this->_types = $types;
  }

  /**
   * Parses raw text and returns the processed result
   * 
   * @param string $text The raw text that should be processed
   */
  public function parse($text)
  {
    // Parse the string to retrieve tokenized text and an array of InlineObjects
    $parsed = $this->parseTypes($text);
    $text = $parsed[0];
    $objects = $parsed[1];

    // Create an array of the text from the rendered objects
    $renderedObjects = array();
    foreach ($objects as $object)
    {
      $renderedObjects[] = $object->render();
    }

    // Call sprintf using the rendered objects to get the final, processed text
    return call_user_func_array('sprintf', $renderedObjects);
  }

  /**
   * Add a object type to be processed
   * 
   * @example
   * $parser->addType('image', 'InlineObjectImage');
   * 
   * @param string $name  The name by which the type will be identified when
   *                      written inline
   * @param string $class The InlineObject class that will render the type
   */
  public function addType($name, $class)
  {
    $this->_types[$name] = $class;
  }

  /**
   * Parses raw text and returns a tokenized string and an array of InlineObjects
   * 
   * array(
   *   0 => 'The inline object with tokens like this %s and this %s',
   *   1 => array(
   *     0 => InlineObject instance
   *     1 => InlineObject instance
   *   )
   * )
   * 
   * @return array The array containing the string and the InlineObjects
   */
  public function parseTypes($text)
  {
    $matches = array();
    preg_match_all($this->_getTypeRegex(), $text, $matches);

    // If no matches found, return array with just the raw text
    if (!isset($matches[0]) || !$matches[0])
    {
      return array($text, array());
    }

    $types = $matches[1];
    $bodies = $matches[2];

    $inlineObjects = array();

    foreach ($bodies as $key => $body)
    {
      $type = $types[$key];
      $class = $this->getTypeClass($type);

      if (!$class)
      {
        throw new Exception(sprintf('Cannot process type %s. No InlineObject class found', $type));
      }

      $e = explode(' ', $body);
      $name = $e[0];

      $options = InlineObjectToolkit::stringToArray(substr($body, strlen($e[0])));

      $inlineObject = new $class($name, $options);

      // Store the object and replace the text with a token
      $inlineObjects[] = $inlineObject;
      $text = str_replace($matches[0][$key], '%s', $text);
    }

    return array($text, $inlineObjects);
  }

  /**
   * Returns the class name for the given type
   * 
   * @return string or null
   */
  public function getTypeClass($type)
  {
    return isset($this->_types[$type]) ? $this->_types[$type] : null;
  }

  /**
   * Returns the array of type => class entries that will be processed
   * 
   * @return array
   */
  public function getTypes()
  {
    return $this->_types;
  }

  /**
   * Returns the regular expression used to match the inline objects
   * 
   * @return string
   */
  protected function _getTypeRegex()
  {
    $typesMatch = implode('|', array_keys($this->_types));

    return '/\[('.$typesMatch.'):(.*?)\]/';
  }
}