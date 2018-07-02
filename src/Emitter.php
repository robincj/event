<?php

namespace League\Event;

use InvalidArgumentException;

class Emitter implements EmitterInterface {
	protected $regexEventListeners = [ ];
	protected $responses = [ ];
	protected $eventNames = [ ];
	protected $batchMode = false;
	/**
	 * The registered listeners.
	 *
	 * @var array
	 */
	protected $listeners = [ ];
	
	/**
	 * The sorted listeners
	 *
	 * Listeners will get sorted and stored for re-use.
	 *
	 * @var ListenerInterface[]
	 */
	protected $sortedListeners = [ ];
	
	/**
	 * Add a listener for an event.
	 *
	 * Handles regex and listener names containing simple wildcards.
	 *
	 * If the event name starts with a tilde ~ or contains a '*' it will be used as a regular expression pattern to match against emitted events.
	 * The regex will have the start and end anchors (^ and $) added automatically so these should not be supplied when registering the
	 *
	 * {@inheritdoc}
	 */
	public function addListener($event, $listener, $priority = self::P_NORMAL) {
		$listener = $this->ensureListener ( $listener );
		/*
		 * If the event name starts with a tilde ~ or contains a '*' then convert it into a preg pattern and add it to regexEventListeners.
		 * regexEventListeners primary key is the event name regex.
		 */
		if (strpos ( $event, '~' ) === 0) {
			$pattn = "/^" . ltrim ( $event, '~' ) . "$/";
			$this->regexEventListeners [$pattn] [$priority] [] = $listener;
		} elseif ($event !== '*' && strpos ( $event, '*' ) !== false) {
			$pattn = str_replace ( '.', '\.', $event );
			$pattn = "/^" . str_replace ( '*', '.*', $pattn ) . "$/";
			$this->regexEventListeners [$pattn] [$priority] [] = $listener;
		} else {
			$this->listeners [$event] [$priority] [] = $listener;
		}
		
		$this->clearSortedListeners ( $event );
		return $this;
	}
	/**
	 *
	 * @inheritdoc
	 */
	public function addOneTimeListener($event, $listener, $priority = self::P_NORMAL) {
		$listener = $this->ensureListener ( $listener );
		$listener = new OneTimeListener ( $listener );
		
		return $this->addListener ( $event, $listener, $priority );
	}
	
	/**
	 *
	 * Loads an array of listeners. e.g.,
	 *<!--
	 * @formatter:off
	 * -->
   * <pre>
   *     $listeners = [
   *        'plan.delete' => function ($e, ...$d) {
   *          $this->doThisThing ( $e, ...$d );
   *        },
   *        'plan.reject' => function ($e, ...$d) {
   *          $this->doThisThing ( $e, ...$d );
   *        },
   *        'plan.approve' => [
   *            function ($e, ...$d) {
   *              echo "<br>This is the second plan.approve listener<br>";
   *            },
   *            function ($e, ...$d) {
   *              echo "<br>This is the third plan.approve listener<br>";
   *            }
   *        ],
   *        'plan.reject.*' => function ($e, ...$d) {
   *          $this->doThisThing ( $e, ...$d );
   *        }
   *    ];
   *
   *    $listeners ['plan.approve'] [] = function ($e, ...$d) {
   *      echo "<br>This is the fourth plan.approve listener<br>";
   *    };
   *    $this->addListenerBatch ( $listeners );
   * </pre>
	 * <!--
	 * @formatter:off
	 * -->
	 * @param array $listeners
	 * @return Emitter
	 */
	public function addListenerBatch($listeners) {
		foreach ( $listeners as $eventName => $callbacks ) {
			foreach ( to_array ( $callbacks ) as $callback )
				$this->addListener ( $eventName, $callback );
		}
		return $this;
	}
	/**
	 *
	 * @inheritdoc
	 */
	public function useListenerProvider(ListenerProviderInterface $provider) {
		$acceptor = new ListenerAcceptor ( $this );
		$provider->provideListeners ( $acceptor );
		
		return $this;
	}
	
	/**
	 *
	 * @inheritdoc
	 */
	public function removeListener($event, $listener) {
		$this->clearSortedListeners ( $event );
		$listeners = $this->hasListeners ( $event ) ? $this->listeners [$event] : [ ];
		
		$filter = function ($registered) use ($listener) {
			return ! $registered->isListener ( $listener );
		};
		
		foreach ( $listeners as $priority => $collection ) {
			$listeners [$priority] = array_filter ( $collection, $filter );
		}
		
		$this->listeners [$event] = $listeners;
		
		return $this;
	}
	
	/**
	 *
	 * @inheritdoc
	 */
	public function removeAllListeners($event) {
		$this->clearSortedListeners ( $event );
		
		if ($this->hasListeners ( $event )) {
			unset ( $this->listeners [$event] );
		}
		
		return $this;
	}
	
	/**
	 * Ensure the input is a listener.
	 *
	 * @param ListenerInterface|callable $listener
	 *
	 * @throws InvalidArgumentException
	 *
	 * @return ListenerInterface
	 */
	protected function ensureListener($listener) {
		if ($listener instanceof ListenerInterface) {
			return $listener;
		}
		
		if (is_callable ( $listener )) {
			return CallbackListener::fromCallable ( $listener );
		}
		
		throw new InvalidArgumentException ( 'Listeners should be ListenerInterface, Closure or callable. Received type: ' . gettype ( $listener ) );
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function hasListeners($event) {
		if (empty ( $this->regexEventListeners ) && empty ( $this->listeners [$event] )) {
			return false;
		}
		
		return true;
	}
	/**
	 *
	 * @inheritdoc
	 */
	public function getListeners($event) {
		if (array_key_exists ( $event, $this->sortedListeners )) {
			return $this->sortedListeners [$event];
		}
		
		return $this->sortedListeners [$event] = $this->getSortedListeners ( $event );
	}
	
	/**
	 * Get the listeners sorted by priority for a given event.
	 *
	 * Handles partial wildcard listener names.
	 *
	 * @param string $event
	 *
	 * @return ListenerInterface[]
	 */
	protected function getSortedListeners($event) {
		if (! $this->hasListeners ( $event )) {
			return [ ];
		}
		
		// see if event matches any wildCardEventListeners
		foreach ( $this->regexEventListeners as $pattn => $wPriorityListeners ) {
			if (preg_match ( $pattn, $event )) {
				// It matches so we need to add $wPriorityListeners that aren't already added
				foreach ( $wPriorityListeners as $wPriority => $wListeners ) {
					foreach ( $wListeners as $wListener ) {
						if ( empty($this->listeners [$event] [$wPriority]) || ! in_array ( $wListener, $this->listeners [$event] [$wPriority] )) {
							$this->listeners [$event] [$wPriority] [] = $wListener;
						}
					}
				}
			}
		}
		$listeners = empty ( $this->listeners [$event] ) ? [ ] : $this->listeners [$event];
		krsort ( $listeners );
		return $listeners ? call_user_func_array ( 'array_merge', $listeners ) : [];
	}
	/**
	 * Returns a list of registered listener names, including wildcard/regex listener names.
	 * 
	 * @return array
	 */
	public function getListenerNames() {
		return array_merge ( array_keys ( $this->listeners ), array_keys ( $this->wildCardEventListeners ) );
	}
	/**
	 * Handles partial wildcard listener names.
	 * Responses (returned values) from all listener callbacks invoked by this method can be retrieved using getResponses()
	 *
	 * {@inheritdoc}
	 */
	public function emit($event) {
		$this->eventNames [] = $event;
		list ( $name, $event ) = $this->prepareEvent ( $event );
		if (! $this->batchMode)
			$this->responses = $this->eventNames = [ ];
		$arguments = [ 
				$event 
		] + func_get_args ();
		$this->invokeListeners ( $name, $event, $arguments );
		$this->invokeListeners ( '*', $event, $arguments );
		return $event;
	}
	/**
	 * Emit a batch of events.
	 *
	 * Overridden original Emitter::emitBatch() so that emitBatch can take data as extra parameters.
	 *
	 * The same set of data is sent to all event emissions.
	 * Responses (returned values) from all listener callbacks invoked by this method can be retrieved using getResponses()
	 *
	 * @see \League\Event\Emitter::emitBatch()
	 * @param array $events
	 *
	 * @return array
	 */
	public function emitBatch(array $events, ...$data) {
		$results = [ ];
		$this->batchMode = true;
		$this->responses = $this->eventNames = [ ];
		foreach ( $events as $event ) {
			$results [] = $this->emit ( $event, ...$data );
		}
		
		$this->batchMode = false;
		return $results;
	}
	/**
	 *
	 * @inheritdoc
	 */
	public function emitGeneratedEvents(GeneratorInterface $generator) {
		$events = $generator->releaseEvents ();
		
		return $this->emitBatch ( $events );
	}
	
	/**
	 * Invoke the listeners for an event.
	 *
	 * @param string $name
	 * @param EventInterface $event
	 * @param array $arguments
	 *
	 * @return void
	 */
	protected function invokeListeners($name, EventInterface $event, array $arguments) {
		$listeners = $this->getListeners ( $name );
		
		foreach ( $listeners as $listener ) {
			if ($event->isPropagationStopped ()) {
				break;
			}
			if ($event)
				$this->eventNames [] = $event->getName ();
			$this->responses [] = call_user_func_array ( [ 
					$listener,
					'handle' 
			], $arguments );
		}
		return $this;
	}
	/**
	 * Prepare an event for emitting.
	 *
	 * @param string|EventInterface $event
	 *
	 * @return array
	 */
	protected function prepareEvent($event) {
		$event = $this->ensureEvent ( $event );
		$name = $event->getName ();
		$event->setEmitter ( $this );
		
		return [ 
				$name,
				$event 
		];
	}
	
	/**
	 * Ensure event input is of type EventInterface or convert it.
	 *
	 * @param string|EventInterface $event
	 *
	 * @throws InvalidArgumentException
	 *
	 * @return EventInterface
	 */
	protected function ensureEvent($event) {
		if (is_string ( $event )) {
			return Event::named ( $event );
		}
		
		if (! $event instanceof EventInterface) {
			throw new InvalidArgumentException ( 'Events should be provides as Event instances or string, received type: ' . gettype ( $event ) );
		}
		
		return $event;
	}
	
	/**
	 * Clear the sorted listeners for an event
	 *
	 * @param
	 *        	$event
	 */
	protected function clearSortedListeners($event) {
		unset ( $this->sortedListeners [$event] );
	}
	
	/**
	 *
	 * Returns an array of responses (returned values) from all listener callbacks invoked by the last emit() or emitBatch().
	 *
	 * @return array
	 */
	public function getResponses($where = NULL) {
		$ret = $this->responses;
		if ($where) {
			$ret = array_where ( $ret, $where );
		}
		return $ret;
	}
	/**
	 * Returns an array of responses that have success===false.
	 *
	 * @return array[]
	 */
	public function getErrorResponses($where = []) {
		$where ['success'] = false;
		$e = [ ];
		foreach ( $this->getResponses ( $where ) as $r ) {
			if ($r->getSuccess () === false)
				$e [] = $r;
		}
		return $e;
	}
	/**
	 * Returns an array of responses that succeeded (success is truthy).
	 *
	 * @return array[]
	 */
	public function getSuccessResponses() {
		$e = [ ];
		foreach ( $this->getResponses () as $r ) {
			if ($r->getSuccess ())
				$e [] = $r;
		}
		return $e;
	}
	/**
	 * Returns name of last event emitted
	 *
	 * @return string|null
	 */
	public function getLastEventName() {
		return end ( $this->eventNames );
	}
	/**
	 * Returns names of events invoked by the last emit() or emitBatch() that actually triggered a listener.
	 *
	 * @return array
	 */
	public function getEventNames() {
		return $this->eventNames;
	}
}
