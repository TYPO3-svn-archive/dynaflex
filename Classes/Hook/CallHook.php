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
 * This class provides all hooks that are needed at the various places inside
 * the core where. DynaFlex should be called to get verything running smoothly.
 * It loads all registered configurations for a specific table and also
 * executes all hooks that want to alter the configurations.
 *
 * @package TYPO3
 * @subpackage dynaflex
 *
 * @author Thomas Hempel <thomas@typo3-unleashed.net>
 * @maintainer Ingo Schmitt <is@marketing-factory.de>
 */
class Tx_Dynaflex_Hook_CallHook {
	protected static $executed = FALSE;

	/**
	 * Hooks in class t3lib/class.t3lib_befunc.php
	 *
	 * @param array $dataStructArray
	 * @param array $config
	 * @param string $row
	 * @param string $table
	 * @return void
	 */
	public function getFlexFormDS_postProcessDS(&$dataStructArray, $config, $row, $table) {
		if (!self::$executed) {
			self::$executed = TRUE;
			$config = Tx_Dynaflex_Utility_TcaUtility::loadDynaFlexConfig($table, $row['pid'], $row);
			if ($config !== FALSE) {
				$this->callDynaFlex($table, $config['DCA'], $dataStructArray);
			}
		}
	}

	/**
	 * Hooks in class t3lib/class.t3lib_tceforms.php
	 *
	 * @param string $table
	 * @param array $row
	 * @return void
	 */
	public function getMainFields_postProcess($table, $row) {
		if (!self::$executed) {
			self::$executed = TRUE;
			$config = Tx_Dynaflex_Utility_TcaUtility::loadDynaFlexConfig($table, $row['pid'], $row);
			if ($config !== FALSE) {
				$dataStructArray = NULL;
				$this->callDynaFlex($table, $config['DCA'], $dataStructArray, $config['cleanUpField']);
			}
		}
	}

	/* Here comes the routines that are implemented for DynaFlex */

	/**
	 * This method calls DynaFlex.
	 *
	 * @param string $table The table we are working on
	 * @param array $dca The DCA loaded from class
	 * @param array $dataStructArray The datastructure
	 * @param bool $cleanUpField If true the cleanup will be performed at the end
	 * @return void
	 */
	public function callDynaFlex($table, $dca, &$dataStructArray = NULL, $cleanUpField = FALSE) {
		if ($dca != NULL) {
			/** @var Tx_Dynaflex_Utility_TcaUtility $dynaflex */
			$dynaflex = t3lib_div::makeInstance('Tx_Dynaflex_Utility_TcaUtility');
			$dynaflex->init($GLOBALS['TCA'], $dca);
			$GLOBALS['TCA'] = $dynaflex->getDynamicTCA();

			if (!empty($dataStructArray)) {
				$dataStructArray = $dynaflex->dataStructArray[0];
			}

			if ($cleanUpField) {
				$dynaflex->doCleanup($cleanUpField);
			}
		}
	}
}
