<?php
	/**
	 * @author Arnia (dev@karybu.org)
	 * @package /classes/db/queryparts/condition
	 * @version 0.1
	 */
	class ConditionSubquery extends Condition {

		/**
		 * constructor
		 * @param string $column_name
		 * @param mixed $argument
		 * @param string $operation
		 * @param string $pipe
		 * @return void
		 */
            function ConditionSubquery($column_name, $argument, $operation, $pipe = ""){
                parent::Condition($column_name, $argument, $operation, $pipe);
                $this->_value = $this->argument->toString();
            }
	}

?>
