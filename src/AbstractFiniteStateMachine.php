<?php
/*
 * BSD 3-Clause License
 *
 * Copyright (c) 2019, TASoft Applications
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *  Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 *
 *  Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 *  Neither the name of the copyright holder nor the names of its
 *   contributors may be used to endorse or promote products derived from
 *   this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace TASoft\FSM;

use TASoft\FSM\Exception\DuplicateStateException;
use TASoft\FSM\Exception\FiniteStateMachineException;
use TASoft\FSM\Exception\StateNotFoundException;
use TASoft\FSM\Exception\UninitializedStateMachineException;
use TASoft\FSM\State\StateDelegateInterface;
use TASoft\FSM\State\StateInterface;
use TASoft\FSM\Transition\TransitionInterface;

abstract class AbstractFiniteStateMachine implements FiniteStateMachineInterface, ResetInterface
{
	private $states = [];

	/** @var StateInterface|null */
	private $currentState;

	/** @var string */
	private $initialState;

	/**
	 * @inheritDoc
	 */
	public function addState(StateInterface $state) {
		if(!isset($this->states[ $state->getName() ])) {
			$this->states[ $state->getName() ] = $state;
			$this->didAddState($state);
		}
		else
			throw (new DuplicateStateException("Duplicated state %s", 0, NULL, $state->getName()))->setStateName($state->getName())->setState($state);
		return $this;
	}

	/**
	 * @param string|StateInterface $state
	 * @return static
	 */
	public function removeState($state) {
		if($state instanceof StateInterface)
			$state = $state->getName();

		if(isset($this->states[$state])) {
			$this->willRemoveState($this->states[$state]);
			unset($this->states[$state]);
		}

		return $this;
	}

	/**
	 * @param StateInterface $state
	 * @return void
	 */
	protected function didAddState(StateInterface $state) {
		if(!$this->initialState)
			$this->initialState = $state->getName();
	}

	/**
	 * @param StateInterface $state
	 * @return void
	 */
	protected function willRemoveState(StateInterface $state) {}

	/**
	 * @param StateInterface|null $state
	 * @return void
	 */
	protected function stateWillResignActive(?StateInterface $state, $context): bool {
		if($state instanceof StateDelegateInterface)
			return $state->willResignActive($context);
		return true;
	}

	/**
	 * @param StateInterface|null $state
	 * @return void
	 */
	protected function stateDidBecomeActive(?StateInterface $state, $context): bool {
		if($state instanceof StateDelegateInterface)
			return $state->didBecomeActive($context);
		return true;
	}


	public function pushNextState($state, $context = NULL): bool {
		if($state instanceof StateInterface)
			$state = $state->getName();

		if($state && !isset($this->states[$state]))
			throw (new StateNotFoundException("State $state does not exist"))->setState($state);

		if(!$this->currentState || $this->currentState->getName() != $state) {
			$this->stateWillResignActive($this->currentState, $context);
			$this->currentState = $this->states[$state];
			return $this->stateDidBecomeActive($this->currentState, $context);
		}
		return $this->currentState->getName() == $state;
	}


	public function reset() {
		if(!$this->initialState)
			throw new UninitializedStateMachineException("No initial state defined");

		$this->pushNextState($this->getInitialState());
		foreach($this->states as$state) {
			if($state instanceof ResetInterface)
				$state->reset();
		}
	}

	public function setInitialState(string $initialState)
	{
		$this->initialState = $initialState;
		return $this;
	}

	public function getInitialState(): string
	{
		return $this->initialState;
	}

	public function getCurrentState(): StateInterface
	{
		if(!$this->currentState)
			$this->reset();

		return $this->currentState;
	}

	/**
	 * @param string $name
	 * @return StateInterface|null
	 */
	public function getState(string $name): ?StateInterface {
		return $this->states[$name] ?? NULL;
	}
}