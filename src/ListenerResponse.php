<?php
namespace League\Event;

use FromArray;

class ListenerResponse implements ListenerResponseInterface
{
    use FromArray;

    public function getEvent()
    {
        return $this->event;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setEvent(EventInterface $event)
    {
        $this->event = $event;
        return $this;
    }

    public function setMessage(String $message = NULL)
    {
        $this->message = $message;
        return $this;
    }

    public function setData($data = NULL)
    {
        $this->data = $data;
        return $this;
    }

    public function set(EventInterface $event, $data = NULL, String $message = NULL)
    {
        return $this->setEvent($event)
            ->setData($data)
            ->setMessage($message);
    }

    public function setProperties(array $array)
    {
        return $this::fromArray($array);
    }
}