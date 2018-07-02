<?php

namespace League\Event;

interface ListenerResponseInterface {
	private $event;
	private $message;
	private $data;
	private $success;
	
	/**
	 * Get the event object that triggered the response from the listener callback.
	 *
	 * @return EventInterface
	 */
	public function getEvent();
	
	/**
	 * Get any message returned from the listener callback response.
	 *
	 * @return string
	 */
	public function getMessage();
	
	/**
	 * Get any data returned from the listener callback response.
	 *
	 * @return mixed
	 */
	public function getData();
	/**
	 * Returns true if the listener callback response was successful, false if there was an error.
	 *
	 * @return boolean
	 */
	public function getSuccess();
}
