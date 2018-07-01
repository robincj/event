<?php
namespace League\Event;

interface ListenerResponseInterface
{

    public $event;

    public $message;

    public $data;

    /**
     * Get the event object that triggered the response from the listener callback.
     */
    public function getEvent();

    /**
     * Get any message returned from the listener callback response.
     */
    public function getMessage();

    /**
     * Get any data returned from the listener callback response.
     */
    public function getData();
}
