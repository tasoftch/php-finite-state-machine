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

use PHPUnit\Framework\TestCase;
use TASoft\FSM\Action\CallbackAction;
use TASoft\FSM\Action\CallbackPostAction;
use TASoft\FSM\Exception\DuplicateStateException;
use TASoft\FSM\ResetInterface;
use TASoft\FSM\StandardActionFSM;
use TASoft\FSM\State\State;
use TASoft\FSM\Transition\AlwaysTransition;
use TASoft\FSM\Transition\GlobalVariableTransition;
use TASoft\FSM\Transition\LoopTransition;
use TASoft\FSM\Transition\NotGlobalVariableTransition;
use TASoft\FSM\Transition\OnceTransition;

class FiniteStateMachineTest extends TestCase
{
	public function testUninitializedFSM() {
		$fsm = new StandardActionFSM();

		$this->expectException(\TASoft\FSM\Exception\UninitializedStateMachineException::class);
		$fsm->update(NULL);
	}

	public function testNotExistingInitialState() {
		$fsm = new StandardActionFSM();
		$fsm->setInitialState("draft");

		$this->expectException(\TASoft\FSM\Exception\StateNotFoundException::class);

		$fsm->update(NULL);
	}

	public function testAutoInitializedState() {
		$fsm = new StandardActionFSM();

		$fsm->addState(new State('draft'));
		$fsm->addState(new State('prepared'));
		$fsm->addState(new State('approved'));
		$fsm->addState(new State('withdrawn'));

		$this->assertEquals("draft", $fsm->getCurrentState());
		$fsm->setInitialState('prepared');

		$this->assertEquals("draft", $fsm->getCurrentState());

		$fsm->reset();
		$this->assertEquals("prepared", $fsm->getCurrentState());
	}

	public function testDuplicatedStateName() {
		$fsm = new StandardActionFSM();

		$fsm->addState(new State('draft'));
		$fsm->addState(new State('prepared'));

		$this->expectException(DuplicateStateException::class);
		$fsm->addState(new State('draft'));
	}

	public function testSimpleFSM() {
		$fsm = new StandardActionFSM();

		$fsm->addState(new State('draft'));
		$fsm->addState(new State('prepared'));
		$fsm->addState(new State('approved'));
		$fsm->addState(new State('withdrawn'));

		$fsm->addTransition(new AlwaysTransition('draft', 'prepared'));
		$fsm->addTransition(new AlwaysTransition('prepared', 'approved'));

		$fsm->setInitialState('draft');
		$this->assertEquals("draft", $fsm->getCurrentState());

		$this->assertTrue($fsm->update(NULL));
		$this->assertEquals("prepared", $fsm->getCurrentState());

		$this->assertTrue($fsm->update(NULL));
		$this->assertEquals("approved", $fsm->getCurrentState());

		$this->assertFalse($fsm->update(NULL));
		$this->assertEquals("approved", $fsm->getCurrentState());

		$this->assertFalse($fsm->update(NULL));
		$this->assertEquals("approved", $fsm->getCurrentState());
	}

	public function testFSMReset() {
		$fsm = new StandardActionFSM();

		$fsm->addState($state = new class('idle') extends State implements ResetInterface {
			private $reset = false;

			public function reset()
			{
				$this->reset = true;
			}

			public function isReset(): bool
			{
				return $this->reset;
			}
		});

		$fsm->addTransition($trans = new class('idle', 'idle') extends AlwaysTransition implements ResetInterface {
			private $reset = false;

			public function reset()
			{
				$this->reset = true;
			}

			public function isReset(): bool
			{
				return $this->reset;
			}
		});

		$fsm->setInitialState('idle');
		$this->assertFalse($state->isReset());
		$this->assertFalse($trans->isReset());

		$fsm->reset();

		$this->assertTrue($state->isReset());
		$this->assertTrue($trans->isReset());
	}

	public function testRemoveState() {
		$fsm = new StandardActionFSM();

		$fsm->addState($s1 = new State("idle"));
		$fsm->addState($s2 = new State("process"));
		$fsm->addState($s3 = new State("finish"));

		$fsm->addTransition($t1 = new AlwaysTransition("idle", 'process'));
		$fsm->addTransition($t2 = new AlwaysTransition("process", 'finish'));
		$fsm->addTransition($t3 = new AlwaysTransition("finish", 'idle'));

		$this->assertEquals($s1, $fsm->getState("idle"));
		$this->assertEquals($s2, $fsm->getState("process"));
		$this->assertEquals($s3, $fsm->getState("finish"));

		$this->assertEquals([$t1], $fsm->getTransitionsFromSourceState('idle'));
		$this->assertEquals([$t3], $fsm->getTransitionsToDestinationState('idle'));

		$this->assertEquals([$t2], $fsm->getTransitionsFromSourceState('process'));
		$this->assertEquals([$t1], $fsm->getTransitionsToDestinationState('process'));

		$this->assertEquals([$t3], $fsm->getTransitionsFromSourceState('finish'));
		$this->assertEquals([$t2], $fsm->getTransitionsToDestinationState('finish'));

		$fsm->removeState('idle');

		$this->assertNull($fsm->getState("idle"));
		$this->assertEquals($s2, $fsm->getState("process"));
		$this->assertEquals($s3, $fsm->getState("finish"));

		$this->assertEmpty($fsm->getTransitionsFromSourceState('idle'));
		$this->assertEmpty( $fsm->getTransitionsToDestinationState('idle'));

		$this->assertEquals([$t2], $fsm->getTransitionsFromSourceState('process'));
		$this->assertEmpty( $fsm->getTransitionsToDestinationState('process'));

		$this->assertEmpty( $fsm->getTransitionsFromSourceState('finish'));
		$this->assertEquals([$t2], $fsm->getTransitionsToDestinationState('finish'));
	}

	public function testLoopTransition() {
		$fsm = new StandardActionFSM();

		$fsm->addState(new State("idle"));
		$fsm->addTransition(new LoopTransition('idle'));

		$this->assertTrue($fsm->update(NULL));
		$this->assertTrue($fsm->update(NULL));
		$this->assertTrue($fsm->update(NULL));
		$this->assertTrue($fsm->update(NULL));

		$this->assertEquals("idle", $fsm->getCurrentState());
	}

	public function testSimpleActions() {
		$fsm = new StandardActionFSM();

		$fsm->addState(new State("idle"));
		$fsm->addState(new State("action"));

		$fsm->addTransition(new AlwaysTransition('idle', 'action'));
		$fsm->addTransition(new AlwaysTransition('action', 'idle'));

		$list = [];

		$fsm->addAction('action', new CallbackAction(function($context) use (&$list) {
			$list[] = 'pre';
			$this->assertEquals(88, $context);
		}));

		$fsm->addAction('action', new CallbackPostAction(function($context) use (&$list) {
			$list[] = 'post';
			$this->assertEquals(88, $context);
		}));

		$fsm->update(88);
		$this->assertEquals("action", $fsm->getCurrentState());
		$this->assertEquals(['pre'], $list);

		$fsm->update(88);
		$this->assertEquals("idle", $fsm->getCurrentState());
		$this->assertEquals(['pre', 'post'], $list);

		$fsm->update(88);
		$this->assertEquals("action", $fsm->getCurrentState());
		$this->assertEquals(['pre', 'post', 'pre'], $list);

		$fsm->update(88);
		$this->assertEquals("idle", $fsm->getCurrentState());
		$this->assertEquals(['pre', 'post', 'pre', 'post'], $list);
	}

	public function testOnceTransition() {
		$fsm = new StandardActionFSM();

		$fsm->addState(new State('draft'));
		$fsm->addState(new State('prepared'));

		$fsm->addTransition(new OnceTransition('draft', 'prepared'));
		$fsm->addTransition(new LoopTransition('draft'));

		$fsm->addTransition(new AlwaysTransition('prepared', 'draft'));

		$this->assertTrue($fsm->update(NULL));
		$this->assertEquals('prepared', $fsm->getCurrentState());

		$this->assertTrue($fsm->update(NULL));
		$this->assertEquals('draft', $fsm->getCurrentState());

		$this->assertTrue($fsm->update(NULL));
		$this->assertEquals('draft', $fsm->getCurrentState());

		$fsm->reset();

		$this->assertTrue($fsm->update(NULL));
		$this->assertEquals('prepared', $fsm->getCurrentState());

		$this->assertTrue($fsm->update(NULL));
		$this->assertEquals('draft', $fsm->getCurrentState());

		$this->assertTrue($fsm->update(NULL));
		$this->assertEquals('draft', $fsm->getCurrentState());
	}

	public function testGlobalVarTransition() {
		$fsm = new StandardActionFSM();

		$fsm->addState(new State('draft'));
		$fsm->addState(new State('prepared'));
		$fsm->addState(new State('approved'));



		$fsm->addTransition(new GlobalVariableTransition('MY_VAR', 'draft', 'prepared'));
		$fsm->addTransition(new GlobalVariableTransition('MY_VAR', 'prepared', 'approved'));
		$fsm->addTransition(new NotGlobalVariableTransition('MY_VAR', 'approved', 'draft'));

		$this->assertFalse($fsm->update(NULL));
		$this->assertEquals("draft", $fsm->getCurrentState());

		$this->assertFalse($fsm->update(NULL));
		$this->assertEquals("draft", $fsm->getCurrentState());

		global $MY_VAR;
		$MY_VAR = false;

		$this->assertFalse($fsm->update(NULL));
		$this->assertEquals("draft", $fsm->getCurrentState());

		$MY_VAR = true;

		$this->assertTrue($fsm->update(NULL));
		$this->assertEquals("prepared", $fsm->getCurrentState());

		$this->assertTrue($fsm->update(NULL));
		$this->assertEquals("approved", $fsm->getCurrentState());

		$this->assertFalse($fsm->update(NULL));
		$this->assertEquals("approved", $fsm->getCurrentState());

		$MY_VAR = false;

		$this->assertTrue($fsm->update(NULL));
		$this->assertEquals("draft", $fsm->getCurrentState());

		$this->assertFalse($fsm->update(NULL));
		$this->assertEquals("draft", $fsm->getCurrentState());
	}

	public function testCallbackTransition() {
		$fsm = new StandardActionFSM();

		$fsm->addState($s1 = new State('draft'));
		$fsm->addState($s2 = new State('prepared'));

		$fsm->addTransition(new AlwaysTransition('prepared', 'draft'));
		$CAN = false;
		$fsm->addTransition(new \TASoft\FSM\Transition\CallbackTransition('draft', 'prepared', function($current, $next, $context) use (&$CAN, $s1, $s2) {
			if($context != 23)
				throw new RuntimeException("Error");

			$this->assertEquals($s1, $current);
			$this->assertEquals($s2, $next);

			return $CAN;
		}));

		$this->assertFalse($fsm->update(23));
		$this->assertEquals('draft', $fsm->getCurrentState());

		$this->assertFalse($fsm->update(23));
		$this->assertEquals('draft', $fsm->getCurrentState());

		$CAN = true;

		$this->assertTrue($fsm->update(23));
		$this->assertEquals('prepared', $fsm->getCurrentState());

		$this->assertTrue($fsm->update(NULL));
		$this->assertEquals('draft', $fsm->getCurrentState());

		$this->assertTrue($fsm->update(23));
		$this->assertEquals('prepared', $fsm->getCurrentState());

		$CAN = false;

		$this->assertTrue($fsm->update(NULL));
		$this->assertEquals('draft', $fsm->getCurrentState());

		$this->assertFalse($fsm->update(23));
		$this->assertEquals('draft', $fsm->getCurrentState());

		$this->expectException(RuntimeException::class);
		$this->assertFalse($fsm->update(98));
	}
}
