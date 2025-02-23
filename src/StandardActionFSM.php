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


use TASoft\FSM\Action\ActionInterface;
use TASoft\FSM\State\StateInterface;

class StandardActionFSM extends AbstractTransitionFSM
{
	/** @var ActionInterface[][] */
	private $actions = [];

	public function addAction(string $state, ActionInterface $action) {
		$this->actions[$state][$action->getRunMode()][] = $action;
		return $this;
	}

	/**
	 * @param $state
	 * @return static
	 */
	public function removeActionsAtState($state) {
		if($state instanceof StateInterface)
			$state = $state->getName();

		if(isset($this->actions[$state]))
			unset($this->actions[$state]);

		return $this;
	}

	protected function stateWillResignActive(?StateInterface $state, $context): bool
	{
		if(parent::stateWillResignActive($state, $context) && $state) {
			if($actions = $this->actions[$state->getName()][ ActionInterface::RUN_MODE_ON_RESIGN_ACTIVE ] ?? NULL) {
				/** @var ActionInterface $action */
				foreach($actions as $action) {
					if(!$action->runAction($context))
						return false;
				}
			}
			return true;
		}
		return false;
	}

	protected function stateDidBecomeActive(?StateInterface $state, $context): bool
	{
		if( parent::stateDidBecomeActive($state, $context) ) {
			if($actions = $this->actions[$state->getName()][ ActionInterface::RUN_MODE_ON_BECOME_ACTIVE ] ?? NULL) {
				/** @var ActionInterface $action */
				foreach($actions as $action) {
					if(!$action->runAction($context))
						return false;
				}
			}
			return true;
		}
		return false;
	}

	protected function willRemoveState(StateInterface $state)
	{
		parent::willRemoveState($state);
		$this->removeActionsAtState($state);
	}
}