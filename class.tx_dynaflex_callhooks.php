<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007 Thomas Hempel (thomas@work.de)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * This class provides all hooks that are needed at the various places inside the core where
 * DynaFlex should be called to get verything running smoothly.
 * It loads all registered configurations for a specific table and also executes all hooks that
 * want to alter the configurations.
 *
 * @package		TYPO3
 * @subpackage	dynaflex
 *
 * @author		Thomas Hempel <thomas@typo3-unleashed.net>
 * @maintainer	Thomas Hempel <thomas@typo3-unleashed.net>
 */

class tx_dynaflex_callhooks	{
	/* Hooks in class t3lib/class.t3lib_befunc.php */
	function getFlexFormDS_postProcessDS(&$dataStructArray, $conf, $row, $table, $fieldName)	{
		$config = $this->loadDynaFlexConfig($table, $row['pid']);
		$this->callDynaFlex($table, $config['DCA'], $dataStructArray);
	}

	/* Hooks in class t3lib/class.t3lib_tceforms.php */
	function getMainFields_postProcess($table, $row, $pObj)	{
		$config = $this->loadDynaFlexConfig($table, $row['pid']);
		$this->callDynaFlex($table, $config['DCA'], NULL, $config['cleanUpField']);
	}

	/* Here comes the routines that are implemented for DynaFlex */

	/**
	 * This method calls DynaFlex.
	 *
	 * @param	string		$table: The table we are working on
	 * @param	array		$DCA: The DCA loaded from class
	 * @param	array		$dataStructArray: The datastructure we want to change in some cases
	 * @param	boolean		$doCleanUp: If true the cleanup will be performed at the end of the process
	 */
	function callDynaFlex($table, $DCA, $dataStructArray = NULL, $cleanUpField = false)	{
		if ($DCA != NULL && t3lib_extMgm::isLoaded('dynaflex'))	{
			require_once(t3lib_extMgm::extPath('dynaflex') .'class.dynaflex.php');
			$dynaflex = t3lib_div::makeInstance('dynaflex');
			$dynaflex->init($GLOBALS['TCA'], $DCA);
			$GLOBALS['TCA'] = $dynaflex->getDynamicTCA();

			if (!empty($dataStructArray))	{
				$dataStructArray = $dynaflex->dataStructArray[0];
			}

			if ($cleanUpField)	{
				$dynaflex->doCleanup($cleanUpField);
			}
		}
	}

	/**
	 * Loads the specific DCA for a table. It loads the plain DCA from the first class (usually the extension that
	 * wants to change a flexform) and calls all registered hooks for this table. The hooks are usually provided by
	 * a third-party extension that want to extend the orginal DCA.
	 *
	 * @param	string	$table: The table we are working on
	 */
	function loadDynaFlexConfig($table, $pid)	{
		$resultDCA = NULL;

		if (!is_array($GLOBALS['T3_VAR']['ext']['dynaflex'][$table]))	{
			return $resultDCA;
		}

		$tableRegs = $GLOBALS['T3_VAR']['ext']['dynaflex'][$table];
		foreach ($tableRegs as $tableRegRef)	{
			if ($tableRegRef == 'TS')	{
					// load the DCA from page typoscript
				$ts = $GLOBALS['BE_USER']->getTSConfig('dynaflex.'.$table, t3lib_BEfunc::getPagesTSconfig($pid));
				$resultDCA = $this->removeTrailingDotsRecursive($ts['properties']);
			} else {
					// load the DCA from a class and maybe some hooks from within the class
				$tableRegObj = t3lib_div::getUserObj($tableRegRef);
				
				if (empty($resultDCA))	{
					$resultDCA = $tableRegObj->DCA;
				}
				
				if (is_array($tableRegObj->hooks) && count($tableRegObj->hooks) > 0)	{
					foreach ($tableRegObj->hooks as $classRef)	{
						$hookObj = t3lib_div::getUserObj($classRef);
	
						if (method_exists($hookObj, 'alterDCA_onLoad')) {
							$hookObj->alterDCA_onLoad(&$resultDCA, $table);
						}
					}
				}
			}
		}
		
		return array('DCA' => $resultDCA, 'cleanUpField' => $tableRegObj->cleanUpField);
	}
	
	/**
	 * Awaits a DCA configured in TypoScript and runs through all elements and removes the dots from the
	 * end if one was found.
	 * 
	 * @param	array		$ts: The DCA as TypoScript
	 * @return	array		a valid DCA 
	 */
	function removeTrailingDotsRecursive($ts)	{
		$result = array();
		
		foreach ($ts as $k => $v)	{
			$k = preg_replace('/\.$/', '', $k);
			if (is_array($v))	{
				$v = $this->removeTrailingDotsRecursive($v);
			}
			$result[$k] = $v;
		}
		return $result;
	}
}
?>
