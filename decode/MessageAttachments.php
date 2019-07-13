<?php

namespace Mail;

class MessageAttachments
{
    private $email;
    private $name;
    private $path;
    private $size;
    private $mime;

    public function __set($name, $value) {}

	/**
	 * Use for Calling Non-Existent Functions, handling Getters
	 * @method get{property} - a property that needs to be accessed 
	 *
	 * @property-read function
	 * @property-write args
	 *
	 * @return mixed
	 * @throws \Exception
	 */
    public function __call($function, $args)
    {
        $prefix = \substr($function, 0, 3);
        $property = \strtolower(\substr($function, 3, \strlen($function)));

        if (($prefix == 'get') && \property_exists($this, $property))
            return $this->$property;

        throw new \Exception("$function does not exist");
	}
	
	public function __construct()
    {       
        \settype($this->size, 'integer');
    }
}
