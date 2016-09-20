<?php

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Mockery\Exception\NoMatchingExpectationException;

class FunctionsContext implements Context
{

    /**
     * @Given function :function does not exist
     */
    public function functionDoesNotExist($function)
    {
        PHPUnit_Framework_Assert::assertFalse(function_exists($function));
    }

    /**
     * @Given I mock passthru function :function with args:
     */
    public function iMockPassthruFunctionWithArgs($function, TableNode $args)
    {
        WP_Mock::passthruFunction($function, array(
            'args' => $args->getRow(0),
        ));
    }

    /**
     * @Given I mock function :function to return :value
     */
    public function iMockFunctionToReturn($function, $value)
    {
        WP_Mock::userFunction($function, array('return' => $value));
    }

    /**
     * @When I mock function :function
     */
    public function iMockFunction($function)
    {
        WP_Mock::userFunction($function);
    }

    /**
     * @Then function :function should exist
     */
    public function functionShouldExist($function)
    {
        PHPUnit_Framework_Assert::assertTrue(function_exists($function));
    }

    /**
     * @Then I expect :return when I run :function with args:
     */
    public function iExpectWhenIRunWithArgs($return, $function, TableNode $args)
    {
        PHPUnit_Framework_Assert::assertEquals($return, call_user_func_array($function, $args->getRow(0)));
    }

    /**
     * @Then I expect :return when I run :function
     */
    public function iExcpectWhenIRun($return, $function)
    {
        $this->iExpectWhenIRunWithArgs($return, $function, new TableNode(array(array())));
    }

    /**
     * @Then I expect an error when I run :function with args:
     */
    public function iExpectAnErrorWhenIRunWithArgs($function, TableNode $args)
    {
        try {
            $this->iExpectWhenIRunWithArgs(null, $function, $args);
        } catch (NoMatchingExpectationException $e) {
            // Move along...
        }
    }

}