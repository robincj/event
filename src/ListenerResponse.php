<?php

namespace League\Event;

use FromArray;

class ListenerResponse implements ListenerResponseInterface {
	public function __construct(EventInterface $event = NULL) {
		if ($event)
			$this->setEvent ( $event );
	}
	/**
	 * Magic method required so array_column can be used to parse arrays of this object
	 *
	 * @param string $name
	 * @return string
	 */
	public function __get($name) {
		if ($name === 'event_name')
			$ret = $this->getEventName ();
		else
			$ret = $this->$name;
		return $ret;
	}
	/**
	 * Magic method required so array_column can be used to parse arrays of this object
	 *
	 * @param string $name
	 * @return NULL|string
	 */
	public function __isset($name) {
		if ($name === 'event_name')
			$ret = isset ( $this->event );
		else
			$ret = isset ( $this->$name );
		return $ret;
	}
	/**
	 *
	 * @return mixed
	 */
	public function getSuccess() {
		return $this->success;
	}
	/**
	 *
	 * @return mixed
	 */
	public function getMessage() {
		return $this->message;
	}
	
	/**
	 *
	 * @return mixed
	 */
	public function getEvent() {
		return $this->event;
	}
	/**
	 *
	 * @return NULL|string
	 */
	public function getEventName() {
		return isset ( $this->event ) ? $this->event->getName () : null;
	}
	
	/**
	 *
	 * @return mixed
	 */
	public function getData() {
		return $this->data;
	}
	
	/**
	 *
	 * @param boolean $success
	 */
	public function setSuccess($success) {
		$this->success = $success;
		return $this;
	}
	/**
	 *
	 * @param mixed $message
	 */
	public function setMessage($message) {
		$this->message = $message;
		return $this;
	}
	
	/**
	 *
	 * @param mixed $event
	 */
	public function setEvent(EventInterface $event) {
		$this->event = $event;
		return $this;
	}
	
	/**
	 *
	 * @param mixed $data
	 */
	public function setData($data) {
		$this->data = $data;
		return $this;
	}
	public function set(EventInterface $event, $data = NULL, String $message = NULL) {
		return $this->setEvent ( $event )->setData ( $data )->setMessage ( $message );
	}
	public function setProperties(array $array) {
		return $this::fromArray ( $array );
	}
}