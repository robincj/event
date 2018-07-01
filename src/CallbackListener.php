<?php
namespace League\Event;

class CallbackListener implements ListenerInterface
{
    
    /**
     * The callback.
     *
     * @var callable
     */
    protected $callback;
    
    /**
     * The last response from the callback
     *
     * @var mixed
     */
    protected $response;
    
    /**
     * The last ListenerResponse from the callback
     *
     * @var ListenerResponse
     */
    protected $listenerResponse;
    
    /**
     * Create a new callback listener instance.
     *
     * @param callable $callback
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }
    
    /**
     * Get the callback.
     *
     * @return callable
     */
    public function getCallback()
    {
        return $this->callback;
    }
    
    /**
     *
     * @inheritdoc
     */
    public function handle(EventInterface $event)
    {
        $this->listenerResponse = NULL;
        $this->response = call_user_func_array($this->callback, func_get_args());
        return $this;
    }
    
    /**
     * Get the raw response from the last callback
     *
     * @return mixed
     */
    public function getResponse()
    {
        return $this->response;
    }
    
    /**
     * Get the ListenerResponse object from the last callback.
     * If the callback did not return a ListenerResponse then the response is converted into one.
     *
     * @return \League\Event\ListenerResponse|mixed
     */
    public function getListenerResponse()
    {
        if (! $this->listenerResponse) {
            $listenerResponse = $this->response;
            if (! is_a($this->response, 'ListenerResponseInterface')) {
                // Treat the response as data
                $listenerResponse = new ListenerResponse();
                $listenerResponse->setEvent($event)->setData($response);
            }
            $this->listenerResponse = $listenerResponse;
        }
        return $this->listenerResponse;
    }
    
    /**
     *
     * @inheritdoc
     */
    public function isListener($listener)
    {
        if ($listener instanceof CallbackListener) {
            $listener = $listener->getCallback();
        }
        
        return $this->callback === $listener;
    }
    
    /**
     * Named constructor
     *
     * @param callable $callable
     *
     * @return static
     */
    public static function fromCallable(callable $callable)
    {
        return new static($callable);
    }
}
