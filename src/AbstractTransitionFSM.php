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

use TASoft\FSM\State\StateInterface;
use TASoft\FSM\Transition\TransitionInterface;

abstract class AbstractTransitionFSM extends AbstractFiniteStateMachine
{
	/** @var TransitionInterface[][] */
	private $transitions = [];
	private $back_cache_transitions = [];

	/**
	 * Adds a transition to a specific state
	 *
	 * @param TransitionInterface $transition
	 * @return static
	 */
	public function addTransition(TransitionInterface $transition) {
		$this->transitions[ $transition->getSourceStateName() ][] = $transition;
		$this->back_cache_transitions[ $transition->getDestinationStateName() ][] = $transition;

		$this->didAddTransition($transition);
		return $this;
	}

	/**
	 * @param TransitionInterface $transition
	 * @return static
	 */
	public function removeTransition(TransitionInterface $transition) {
		if(($idx = array_search($transition, $this->transitions[$transition->getSourceStateName()] ?? []))!==false) {
			$this->willRemoveTransition($this->transitions[$transition->getSourceStateName()][$idx]);
			unset($this->transitions[$transition->getSourceStateName()][$idx]);
			if(!$this->transitions[$transition->getSourceStateName()])
				unset($this->transitions[$transition->getSourceStateName()]);
		}

		if(($idx = array_search($transition, $this->back_cache_transitions[$transition->getDestinationStateName()] ?? []))!==false) {
			unset($this->back_cache_transitions[$transition->getDestinationStateName()][$idx]);
			if(!$this->back_cache_transitions[$transition->getDestinationStateName()])
				unset($this->back_cache_transitions[$transition->getDestinationStateName()]);
		}

		return $this;
	}

	/**
	 * @param string|StateInterface $state
	 * @return static
	 */
	public function removeTransitionFromSourceState($state) {
		foreach($this->getTransitionsFromSourceState($state) as $item)
			$this->removeTransition($item);
		return $this;
	}

	/**
	 * @param string|StateInterface $state
	 * @return static
	 */
	public function removeTransitionToDestinationState($state) {
		foreach($this->getTransitionsToDestinationState($state) as $item)
			$this->removeTransition($item);
		return $this;
	}

	/**
	 * @param TransitionInterface $transition
	 * @return void
	 */
	protected function didAddTransition(TransitionInterface $transition) {}

	/**
	 * @param TransitionInterface $transition
	 * @return void
	 */
	protected function willRemoveTransition(TransitionInterface $transition) {}

	/**
	 * Clear transitions coming from and going to state
	 *
	 * @inheritDoc
	 */
	protected function willRemoveState(StateInterface $state)
	{
		parent::willRemoveState($state);
		$this->removeTransitionFromSourceState($state);
		$this->removeTransitionToDestinationState($state);
	}

	public function reset()
	{
		parent::reset();

		foreach($this->transitions as $transitions) {
			foreach($transitions as $transition)
				if($transition instanceof ResetInterface)
					$transition->reset();
		}
	}

	public function update($context): bool
	{
		$transitions = $this->getTransitionsFromSourceState( $this->getCurrentState()->getName() );
		if($transitions) {
			/** @var TransitionInterface $transition */
			foreach($transitions as $transition) {
				$src = $this->getState( $transition->getSourceStateName() );
				$dst = $this->getState( $transition->getDestinationStateName() );

				if($transition->canApply($src, $dst, $context)) {
					return $this->pushNextState($dst, $context);
				}
			}
		}
		return false;
	}

	/**
	 * @param $state
	 * @return array|TransitionInterface[]
	 */
	public function getTransitionsFromSourceState($state): array {
		if($state instanceof StateInterface)
			$state = $state->getName();

		return $this->transitions[$state] ?? [];
	}

	/**
	 * @param $state
	 * @return array|TransitionInterface[]
	 */
	public function getTransitionsToDestinationState($state): array {
		if($state instanceof StateInterface)
			$state = $state->getName();

		return $this->back_cache_transitions[$state] ?? [];
	}
}