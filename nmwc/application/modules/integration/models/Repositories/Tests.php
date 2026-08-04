<?php
class Integration_Model_Repositories_Tests
{
	public function test($message)
	{
		if (TRUE === empty($message))
		{
			throw new Zend_Exception('Invalid Message Provided to the Test Object');
		}
	
		$test_entity = new Integration_Model_Entities_Test();
		return $test_entity->test($message);
	}
}