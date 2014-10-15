<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2006 Thomas Hempel (thomas@work.de)
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
 * This metaclass provides several methods for manipulating the TCA (in fact
 * it can modify any other array as well, but it makes most sense for the TCA
 * now).
 * It was developed for the tx_commerce extension but I think it can be useful
 * many other developers out there.
 *
 * @package TYPO3
 * @subpackage dynaflex
 *
 * @author Thomas Hempel <thomas@typo3-unleashed.net>
 * @maintainer Ingo Schmitt <is@marketing-factory.de>
 */
class Tx_Dynaflex_Utility_TcaUtility {

	/**
	 * the internal representation of the Typo Configuration Array (TCA)
	 *
	 * @var array
	 */
	public $orgTCA = array();

	/**
	 * configuration for TCA modfications
	 *
	 * @var array
	 */
	public $conf = array();

	/**
	 * The language identifier for the content element
	 *
	 * @var string
	 */
	public $ceLang = 'DEF';

	/**
	 * The table the current content element is placed in
	 *
	 * @var string
	 */
	public $ceTable = '';

	/**
	 * the uid of the content element we're working on
	 *
	 * @var int
	 */
	public $ceUid = 0;

	/**
	 * the page id of the content element
	 *
	 * @var int
	 */
	public $cePid = 0;

	/**
	 * stores all data of the content element
	 *
	 * @var array
	 */
	public $ceFields = array();

	/**
	 * The flexform data (XML or Array) the class is currently working on
	 *
	 * @var array
	 */
	public $flexData;

	/**
	 * This variable will be filled with the datastructure of a
	 * collection of modifications
	 *
	 * @var array
	 */
	public $dataStructArray = array();

	/**
	 * A dummy that holds the data, from a condition
	 *
	 * @var array
	 */
	public $conditionData;

	/**
	 * @var string
	 */
	public $path = '';

	/**
	 * @var array
	 */
	public $callbackValues = array();


	/** BASE **/

	/**
	 * Constructor of this class. Does nothing else than
	 * setting some internal class variables
	 *
	 * @param array $TCA The original TCA (or any other array that should be modfied)
	 * @param array $conf The DCA (Dynaflex Configuration Array)
	 * @return self
	 */
	public function __construct($TCA = array(), $conf = array()) {
		if (is_array($TCA) && count($TCA)) {
			$this->init($TCA, $conf);
		}
	}

	/**
	 * This method is needed because you can't pass any arguments to the constructor
	 * if you instantiate the class with TYPO3 methods (t3lib_div::makeInstance).
	 * So you have to call this method after initializing and BEFORE the
	 * getDynamicTCA So this method initializes some values dynaflex needs for
	 * working.
	 *
	 * @param array $TCA the orginal TCA array that should be modfied
	 * @param array $conf the configuration array for dynaflex
	 * @return void
	 */
	public function init($TCA = array(), $conf = array()) {
		$this->orgTCA = $TCA;
		$this->conf = $conf;

		// determine the language we are working on
		// by the way we're trying to get some data of the content
		// element we're working on
		$contentInfo = $GLOBALS['SOBE']->editconf;

		// fetch it from PostVars if nothing was found in SOBE
		if (empty($contentInfo)) {
			$contentInfo = t3lib_div::_GP('edit');
		}

		// if we got any information about the current content element
		if (is_array($contentInfo)) {
			// get the table
			// The table can be defined on dynaflex call or not at all.
			// A explicite definition of a table
			// can be useful when dynaflex is called in a multitable
			// editview (like columnsOnly) environment.
			if (empty($conf['workingTable'])) {
				$this->ceTable = reset(array_keys($contentInfo));
			} else {
				$this->ceTable = $conf['workingTable'];
			}

			// and the UID of this content element
			$this->ceUid = intval(reset(array_keys($contentInfo[$this->ceTable])));

			$contentValues = array_values($contentInfo);
			$uidData = ($contentValues[0][$this->ceUid]);
			if ($uidData == 'new') {
				// we have a new dataset
				$this->cePid = $this->ceUid;
				$this->ceUid = 0;
			} else {
				// last but not least the page id of the content element
				if ($this->ceUid != 0) {
					/** @var t3lib_db $database */
					$database = $GLOBALS['TYPO3_DB'];
					$this->ceFields = $database->exec_SELECTgetSingleRow('*', $this->ceTable, 'uid = ' . $this->ceUid);
					$this->cePid = $this->ceFields['pid'];
				}
			}

			// we only do the rest if the content table has localization option in the TCA
			$languageField = $TCA[$this->ceTable]['ctrl']['languageField'];
			if (!empty($languageField)) {
				// in this case we're trying to get the current language
				// identifier for this explcite content element
				$sysLanguageUid = t3lib_BEfunc::getRecord($this->ceTable, (int)$this->ceUid, $languageField);
				if ($sysLanguageUid[$languageField]) {
					$langIsoCode = t3lib_BEfunc::getRecord('sys_language', (int)$sysLanguageUid[$languageField], 'static_lang_isocode');
					$langIdent = t3lib_BEfunc::getRecord('static_languages', (int)$langIsoCode['static_lang_isocode'], 'lg_typo3');
					$this->ceLang = strtoupper($langIdent['lg_typo3']);
				}
			}
		}

		if (!empty($conf['uid'])) {
			$this->ceUid = $conf['uid'];
		}
	}

	/**
	 * main method of this class. Processes the configuration array and
	 * calls the different internal methods for modifieng the TCA.
	 *
	 * @return array The modified array that was passed by the init method
	 */
	public function getDynamicTCA() {
		// Process every item (path) in the configuration
		foreach ($this->conf as $runIndex => $singleConf) {

			// build the path-array
			$this->path = $this->getPath($singleConf['path']);

			// get the data from the TCA
			$this->flexData = $this->getDataFromTCAByPath($this->path);

			// load the file if it is a refrence and not an XML
			if (substr($this->flexData, 0, 5) == 'FILE:') {
				$file = t3lib_div::getFileAbsFileName(substr($this->flexData, 5));
				if ($file && @is_file($file)) {
					$this->flexData = t3lib_div::getUrl($file);
				}
			}

			// if the option is not set or set as true make an array from the xml
			if (!isset($singleConf['parseXML']) || $singleConf['parseXML'] == TRUE) {
				$this->flexData = t3lib_div::xml2array($this->flexData);
			}

			if ($this->handleModifications($singleConf['modifications']) === FALSE) {
				continue;
			}

			$this->dataStructArray[$runIndex] = $this->flexData;

			// Write the result back to the internal TCA
			if (!isset($singleConf['parseXML']) || $singleConf['parseXML'] == TRUE) {
				$this->flexData = t3lib_div::array2xml($this->flexData, '', 0, 'T3DataStructure');
			}

			$this->setDataInTCAByPath($this->path, $this->flexData);
		}

		// return the modified TCA
		return $this->orgTCA;
	}


	/** HELPER **/

	/**
	 * Explodes a string by "/" and returns an array.
	 *
	 * @param string $path A string that represents the path,
	 * 	the single steps are devided by "/"
	 * @return array where every element is a step inside the path
	 */
	public function getPath($path = '') {
		if ($path == '') {
			return $path;
		}
		return explode('/', $path);
	}

	/**
	 * Returns the content of the internal TCA at the end of the given path.
	 *
	 * @param array $path the path of the content that should be returned
	 * @return mixed (depends on the data that is stored at the path)
	 */
	public function getDataFromTCAByPath($path = array()) {
		$data = $this->getDataByPath($this->orgTCA, $path);
		return $data;
	}

	/**
	 * Writes some content into the internal TCA at the end of the path.
	 *
	 * @param array $path the path where the data should be stored
	 * @param mixed $data The data that should be stored
	 * @return void
	 */
	public function setDataInTCAByPath($path, $data) {
		$this->setDataByPath($this->orgTCA, $path, $data);
	}

	/**
	 * Get the content of the element of the source array at
	 * the end of the given path.
	 *
	 * @param array $source the array from which the result should be extracted
	 * @param mixed $path the path where the result can be found
	 * 	(if the path is a string, it will be transformed into an array)
	 * @return mixed (depends on the data that is stored at the path)
	 */
	public function getDataByPath($source, $path) {
		// get the path
		if (!is_array($path)) {
			$path = $this->getPath($path);
		}
		$data = $source;

		// fetch from source array
		if (is_array($path)) {
			foreach ($path as $pathPart) {
				$data = $data[$pathPart];
			}
		}

		// return the fetched data
		return $data;
	}

	/**
	 * Writes some content into the given array at the end of the path.
	 *
	 * @param array $dest The array where the data is stored in
	 * 	(PASSED BY REFERENCE!)
	 * @param mixed $path the path inside the dest, where the data
	 * 	should be stored (if it is a string it will be transformed
	 * 	into an array)
	 * @param mixed $data the data that should be stored
	 * @return void
	 */
	public function setDataByPath(&$dest, $path, $data) {
		if (!is_array($path)) {
			$path = $this->getPath($path);
		}
		$dataDest = &$dest;

		if (is_array($path) && is_array($dataDest)) {
			foreach ($path as $pathPart) {
				$dataDest = &$dataDest[$pathPart];
			}
		}
		$dataDest = $data;
	}

	/**
	 * Inserts the data into the current FlexForm data with the key
	 * inside the given path.
	 *
	 * @param mixed $path the path inside the current FlexForm data where the
	 * 	data should be added
	 * @param string $key the key of the new array element
	 * @param mixed $data the data that should be added
	 * @return void
	 */
	public function insertDataAtPath($path, $key, $data) {
		// get data
		$theData = $this->getDataByPath($this->flexData, $path);
		if (!is_array($theData)) {
			$theData = (strlen(trim($theData)) == 0) ? array() : array($theData);
		}

		// add data
		$theData[$key] = $data;

		// write back to flexform
		$this->setDataByPath($this->flexData, $path, $theData);
	}

	/**
	 * inserts a new row after a given key inside of an array.
	 *
	 * @param mixed $key The key of the element aftr which the new
	 * 	row should be inserted
	 * @param array $array The array on which the action is performed
	 * @param mixed $newRow The new row which is inserted into the array
	 * @return array The updated array
	 */
	public function insertAfterKey($key, $array, $newRow) {
		if (!is_array($array)) {
			return $array;
		}

		$before = array_slice($array, 0, $key);
		$after = array_slice($array, $key);

		// build the new array
		$new = array();
		foreach ($before as $row) {
			$new[] = $row;
		}
		$new[] = $newRow;
		foreach ($after as $row) {
			$new[] = $row;
		}

		return $new;
	}

	/**
	 * Cycles through an array and searches if the beginning of the data inside
	 * the array is the same as the given searchword. This only works if the
	 * data is a string.
	 *
	 * @param string $word The word for which it should search
	 * @param array $array The array in which should be searched
	 * @return boolean False if something is wrong with the data or nothing was
	 * 	found. Otherwise the key of the first row that matches the searchword.
	 */
	public function searchBeginInArray($word, $array) {
		if (!is_array($array)) {
			return FALSE;
		}
		$word = trim($word);
		$wLen = strlen($word) + 1;
		foreach ($array as $key => $data) {
			if (!is_string($data)) {
				continue;
			}
			if (trim(substr($data, 0, $wLen)) == $word) {
				return $key;
			}
		}
		return FALSE;
	}

	/**
	 * Fetches data from a specific field inside of an TCE form. It fetches the data
	 * from a special field inside the database. If a path and a xml_field is set
	 * it replaces some markers in the path and the name of the xml_field and fetches
	 * the value of the field inside of the flexform structure. If no xml_field is
	 * set, the value of the database field itself is returned.
	 *
	 * @param array $sourceConfig A source_config part of the dynaflex configuration
	 * @param array $markerArray An array with marker => value pairs
	 * @return mixed Depends on the data inside the field
	 */
	public function getFieldData($sourceConfig, $markerArray = array()) {
		// if the field is in the same table than the current or if the table if not set
		// we don't need to fetch anything from the database because the requested data
		// should be in the GP vars
		$ceData = t3lib_div::_GP('data');
		$ceData = $ceData[$this->ceTable][$this->ceUid];

		if (!empty($ceData) && (empty($sourceConfig['table']) || $sourceConfig['table'] == $this->ceTable)) {
			$sourceData[$sourceConfig['db_field']] = $ceData[$sourceConfig['db_field']];
		} else {
			/** @var t3lib_db $database */
			$database = $GLOBALS['TYPO3_DB'];
			$res = $database->exec_SELECTquery(
				$sourceConfig['db_field'],
				$sourceConfig['table'],
				'uid = ' . $this->ceUid
			);
			$sourceData = $database->sql_fetch_assoc($res);
		}

		foreach ($markerArray as $marker => $value) {
			$sourceConfig['path'] = str_replace($marker, $value, $sourceConfig['path']);
			$sourceConfig['xml_field'] = str_replace($marker, $value, $sourceConfig['xml_field']);
		}

		if (!empty($sourceConfig['xml_field'])) {
			$sourceData = (
			is_array($sourceData[$sourceConfig['db_field']])) ?
				$sourceData[$sourceConfig['db_field']] :
				t3lib_div::xml2array($sourceData[$sourceConfig['db_field']]
				);
			$sourceData = $this->getDataByPath($sourceData['data'], $sourceConfig['path'] . '/' . $sourceConfig['xml_field']);
			$sourceData = $sourceData['vDEF'];
		} else {
			$sourceData = $sourceData[$sourceConfig['db_field']];
		}

		return $sourceData;
	}

	/**
	 * Returns the label for a field, sheet or whatever. If the labelConfig is
	 * an array it tries to fetch the label from the database, based on the
	 * settings in the config. Otherwise it simply returns the labelConfig.
	 * In every case, markers in the result are replaced with the values from
	 * the markerArray.
	 *
	 * @param mixed $labelConfig Array with config or simple string
	 * @param array $markerArray An array with marker => value pairs
	 * @return string The label with replaced markers
	 */
	public function getLabel($labelConfig, $markerArray = array()) {
		$markerArray['###ce_uid###'] = $this->ceUid;
		$markerArray['###ce_pid###'] = $this->cePid;

		if (is_array($labelConfig)) {
			// the label config is an array, that means that we have to
			// fetch something from the database
			foreach ($markerArray as $marker => $value) {
				$labelConfig['where'] = str_replace($marker, $value, $labelConfig['where']);
			}
			/** @var t3lib_db $database */
			$database = $GLOBALS['TYPO3_DB'];
			$res = $database->exec_SELECTquery(
				$labelConfig['field'],
				$labelConfig['table'],
				$labelConfig['where'],
				'',
				'',
				1
			);
			$labelData = $database->sql_fetch_assoc($res);
			$label = $labelData[$labelConfig['field']];
		} else {
			foreach ($markerArray as $marker => $value) {
				$labelConfig = str_replace($marker, $value, $labelConfig);
			}
			$label = $labelConfig;
		}

		return $label;
	}


	/**
	 * Handling basics
	 */

	/**
	 * Handles the modifications. We pass a "modifications" section to this method
	 * and it calls the responsible method.
	 *
	 * @param array $modifications a modifications part of the dynaflex configuration
	 * @param array $markerArray an array that contains some marker => value pairs.
	 * @return boolean False if the passed modifications are not an array,
	 * 	otherwise false.
	 */
	public function handleModifications($modifications, $markerArray = array()) {
		if (!is_array($modifications)) {
			return FALSE;
		}

		// process all modification definitons for this path
		foreach ($modifications as $singleFunc) {
			// check the conditions if any conditions are set (THIS IS DEPRECATED!)
			$doIt = $this->checkCondition($singleFunc['condition']);

			// if more conditions are set in the "conditions" field, check them all
			if ($doIt && isset($singleFunc['conditions']) && is_array($singleFunc['conditions'])) {
				foreach ($singleFunc['conditions'] as $condition) {
					// process the single condition, but only if it is an array
					if (is_array($condition)) {
						$doIt = $this->checkCondition($condition);
					}

					// leave the cycle if the last condition doesn't match
					if (!$doIt) {
						break;
					}
				}
			}

			// process the modification if condition is true
			if ($doIt) {
				// check if a condition is set for a group of modifications
				if (!isset($singleFunc['elements'])) {
					$elements = array($singleFunc);
				} else {
					$elements = $singleFunc['elements'];
				}

				foreach ($elements as $modConf) {
					switch (strtolower((string)$modConf['method'])) {
						// Adding something somewhere
						case 'add':
							$this->add($modConf, $markerArray);
							break;

						case 'move':
							$this->move($modConf);
							break;

						case 'remove':
							$this->remove($modConf);
							break;

						case 'function_call':
							$this->call($modConf);
							break;

						default:
					}
				}
			}
		}

		return TRUE;
	}

	/**
	 * Checks a condition if it is valid
	 *
	 * @param array $condition The configuration array
	 * @return boolean
	 */
	public function checkCondition($condition) {
		unset($this->conditionData);

		// without a condition we can't check anything
		if (!is_array($condition)) {
			return TRUE;
		}

		// if the uid is set but no integer exit (that shouldn't be)
		if (isset($this->ceUid) && !is_int($this->ceUid)) {
			return FALSE;
		}

		// replace some placeholders in the select queries
		$where = str_replace('###uid###', $this->ceUid, $condition['where']);

		// get type
		$condSource = strtolower($condition['source']);

		// get data
		switch ($condSource) {
			case 'language':
				// set the language of the current record as data we compare with
				$data = $this->ceLang;
				break;

			case 'pid':
				$data = $this->cePid;
				break;

			case 'cce':
				$data = $this->ceFields[$condition['cefield']];
				break;

			case 'db':
				// fall through
			default:
				// fetch the data from database
				/** @var t3lib_db $database */
				$database = $GLOBALS['TYPO3_DB'];
				$res = $database->exec_SELECTquery($condition['select'], $condition['table'], $where);
				$data = $database->sql_fetch_assoc($res);
				if ($condition['select'] != '*') {
					$data = $data[$condition['select']];
				}
		}

		if ($condition['isXML']) {
			$data = t3lib_div::xml2array($data);
		}
		if (isset($condition['path'])) {
			$data = $this->getDataByPath($data, $condition['path']);
		}

		$isTrue = FALSE;

		// make the comparison
		switch ((string)$condition['if']) {
			case 'hasValues':
				$isTrue = (isset($data) && (string)$data != '');
				break;

			case 'isLess':
				$isTrue = (isset($data) && intval($data) < $condition['compareTo']);
				break;

			case 'isEqual':
				$isTrue = (isset($data) && $data == $condition['compareTo']);
				break;

			case 'notEqual':
				$isTrue = (isset($data) && $data != $condition['compareTo']);
				break;

			case 'isGreater':
				$isTrue = (isset($data) && intval($data) > $condition['compareTo']);
				break;

			case 'regex':
				$isTrue = (isset($data) && preg_match($condition['compareTo'], $data));
				break;

			default:
		}

		// and return the result
		if ($isTrue) {
			$this->conditionData = $data;
			return TRUE;
		}

		return FALSE;
	}


	/** HANDLING **/

	/**
	 * The switching method to write something into the flexForm Structure
	 *
	 * @param array $funcConf the complete configuration for this MA
	 * @param array $markerArray An array with marker => value pairs
	 * @return void
	 */
	public function add($funcConf = array(), $markerArray = array()) {
		switch ((string)$funcConf['type']) {
			case 'staticXML':
				$this->addStaticXML($funcConf, $markerArray);
				break;

			case 'sheet':
				$this->addSheet($funcConf, $markerArray);
				break;

			case 'sheets':
				$this->addSheets($funcConf, $markerArray);
				break;

			case 'field':
				$this->addField($funcConf, $markerArray);
				break;

			case 'fields':
				$this->addFields($funcConf, $markerArray);
				break;

			case 'append':
				$this->append($funcConf, $markerArray);
				break;

			default:
		}
	}

	/**
	 * Adds a XML-structure into the XML-structure of another field after a
	 * specific element or at the beginning of the structure.
	 * The XML-structure that should be inserted MUST be wrapped in some tags.
	 * Normally <ROOT> and </ROOT> are used.
	 *
	 * @param array $funcConf configuration array for a single configuration
	 * @return void
	 */
	public function addStaticXML($funcConf = array()) {
		// get the array of the structure
		$formAddition = t3lib_div::xml2array($funcConf['data']);

		// Now determine the path and get the fielddata from the flexForm structure
		$path = $this->getPath($funcConf['inside']);
		$oldData = $this->getDataByPath($this->flexData, $path);

		$newData = array();

		// cycle through the data and insert the new field after the field
		// setted in conf. The new data will always inserted on the same layer
		// where the element is placed on after which it should be inserted.
		if ($funcConf['beforeAll']) {
			// If the new structure should be inserted at the beginning of the old structure
			// this is handeld here
			$newData = array_merge($formAddition, $oldData);
		} elseif (isset($funcConf['after'])) {
			// otherwise cycle through the elements in this level...
			foreach ($oldData as $key => $value) {
				$newData[$key] = $value;
				// If the field is reached after which the new structure should be inserted...
				if ($key == $funcConf['after']) {
					// ... do so ...
					$newData = array_merge($newData, $formAddition);
				}
			}
		} else {
			// "afterAll" or no position is defined
			$newData = array_merge($oldData, $formAddition);
		}

		// Write back the modified structure to the flexForm structure
		$this->setDataByPath($this->flexData, $path, $newData);
	}

	/**
	 * Adds a sheet at the root level of data structure
	 *
	 * @param array $funcConf configuration array for a single configuration
	 * @return void
	 */
	public function addSheet($funcConf = array()) {
		// Build a default array for the new sheet
		$sheetArray = Array(
			'ROOT' => Array (
				'TCEforms' => Array (
					'sheetTitle' => $this->getLabel($funcConf['label']),
				),
				'type' => 'array',
				'el' => Array()
			),
		);

		// insert the new sheet into the datastructure
		$this->flexData['sheets'][$funcConf['name']] = $sheetArray;
	}

	/**
	 * Adds a various number of sheets at the root level of the datastructure.
	 * How many sheets are inserted are defined by "source" field in funcConf.
	 *
	 * @param array $funcConf configuration array for a single configuration
	 * @return void
	 */
	public function addSheets($funcConf = array()) {
		$sheetCount = 0;
		switch (strtolower((string)$funcConf['source'])) {
			case 'field':
				$sourceData = $this->getFieldData($funcConf['source_config']);
				$sheetCount = intval($sourceData);
				break;

			default:
		}

		for ($sIndex = 0; $sIndex < $sheetCount; $sIndex++) {
			$sheetName = $funcConf['sheet_config']['name'] . '_' . $sIndex;
			$sheetConf = array(
				'label' => $this->getLabel($funcConf['sheet_config']['label'], array('###SINDEX###' => ($sIndex + 1))),
				'name' => $sheetName,
			);
			$this->addSheet($sheetConf);
			if (is_array($funcConf['sheet_config']['fields'])) {
				foreach ($funcConf['sheet_config']['fields'] as $fieldConf) {
					$fieldConfig = $fieldConf;
					$fieldConfig['path'] = 'sheets/' . $sheetName . '/ROOT/el';
					$fieldConfig['name'] .= '_' . $sIndex;
					$fieldConfig['label'] = str_replace('###SINDEX###', ($sIndex + 1), $fieldConfig['label']);
					$this->addField($fieldConfig);
				}
			}

			if (is_array($funcConf['modifications'])) {
				$this->handleModifications(
					$funcConf['modifications'],
					array('###SINDEX###' => $sIndex)
				);
			}
		}
	}

	/**
	 * Adds a field at the given position. The XML is build from an array,
	 * like it's used for a "normal" TCE field.
	 *
	 * @param array $funcConf configuration array for a single configuration
	 * @return void
	 */
	public function addField($funcConf) {
		if (isset($funcConf['field_config'])) {
			$data = $funcConf['field_config'];
		} else {
			$data = $funcConf;
		}

		// Build the basic xml for a new field
		unset($data['config']['data']);
		$fieldArray = array (
			'TCEforms' => array (
				'label' => $this->getLabel($data['label']),
				'config' => $data['config'],
			),
		);

		// add default Extras if set in config
		if (isset($data['defaultExtras'])) {
			$fieldArray['TCEforms']['defaultExtras'] = $data['defaultExtras'];
		}

		$this->insertDataAtPath($funcConf['path'], $data['name'], $fieldArray);
	}

	/**
	 * Adds a series of fields at the given position. The fields will be created
	 * by special configurations.
	 *
	 * @param array $funcConf configuration array for a single configuration
	 * @param array $markerArray An array with marker => value pairs
	 * @return void
	 */
	public function addFields($funcConf, $markerArray = array()) {
		// get the data
		switch (strtolower((string)$funcConf['source'])) {
			case 'condition':
				// The data is got from the field the condition checks
				$data = $this->conditionData;
				break;

			case 'field':
				$data = $this->getFieldData($funcConf['source_config'], $markerArray);
				break;

			case 'db':
				// The data is fetched from the database
				// we got the configuration in the 'source_config' array
				// the values are: table, select, where
				$handling = $funcConf['source_config'];
				preg_match_all('/###(.[^#]*)###/i', $handling['where'], $whereMarker);
				$whereMarkerCount = count($whereMarker[0]);
				for ($index = 0; $index < $whereMarkerCount; $index++) {
					$handling['where'] = str_replace(
						$whereMarker[0][$index],
						$this->ceFields[$whereMarker[1][$index]],
						$handling['where']
					);
				}

				/** @var t3lib_db $database */
				$database = $GLOBALS['TYPO3_DB'];
				$data = $database->exec_SELECTgetRows(
					$handling['select'],
					$handling['table'],
					$handling['where'],
					$handling['groupby'],
					$handling['orderby'],
					$handling['limit']
				);
				break;

			case '':
				$data = array('empty');
				break;

			default:
				$data = (string)$funcConf['source'];
		}

		// leave here if source was set but no data was found
		if (empty($data) && isset($funcConf['source'])) {
			return;
		}

		if (isset($funcConf['source_type'])) {
			switch (strtolower((string)$funcConf['source_type'])) {
				case 'csl':
					// The data is a comma separated list
					$data = explode(',', $data);
					break;

				case 'int':
					$data = range(1, intval($data));
					break;

				case 'db_row':
					$data = array($data);
					break;

				case 'entry_count':
				default:
			}
		}

		// get the sourcedata from a flexform datastructure if no config is given
		if (!isset($funcConf['source_config']) && !empty($formConf['source'])) {
			$tcaDataArray = t3lib_div::xml2array($this->getDataFromTCAByPath($funcConf['source_config']['handling']['TCApath']));
			$xmlDataArray = $this->getDataByPath($tcaDataArray, $funcConf['source_config']['handling']['XMLpath']);

			// get the keys for sequencing the fields
			$keys = array_keys($xmlDataArray);

			// build the basic configuration
			$config = array (
				'inside' => $funcConf['source_config']['path'],
				'data' => '',
			);

			// cycle through all fields of the source content element
			// in every cycle the name of the field is appended by _X where X is
			// the uid of the dataset in the table
			foreach ($data as $uid) {
				// rename all keys
				if (!is_array($keys)) {
					continue;
				}

				$theData = array();
				foreach ($keys as $key) {
					$singleData = $xmlDataArray[$key];
					$theData[$key . '_' . $uid] = $singleData;
				}

				// write the xml structure into the configuration
				$config['data'] = t3lib_div::array2xml($theData);

				// Add the element
				$this->addStaticXML($config);

				// unset the insertion data
				unset($theData);
			}

		} else {
			// we got a config
			$fConfA = $funcConf['field_config'];

			if (isset($fConfA['config'])) {
				$fConfA = array($fConfA);
			}

			$funcDataArray = array();

			$baseConfig = array (
				'path' => $funcConf['path'],
			);

			foreach ($data as $count => $fieldData) {
				if (empty($fieldData)) {
					continue;
				}

				$dataArray = array('row' => $fieldData, 'dfConfig' => $funcConf);
				$funcDataArray[] = $dataArray;

				if (!is_array($fConfA)) {
					continue;
				}
				$fIndex = 0;

				foreach ($fConfA as $confKey => $fConf) {
					$aConfig = $baseConfig;
					if ($confKey === 'singleUserFunc') {
						// The fieldconfig should be created by a userfunction, so we call it here
						$aConfig = t3lib_div::callUserFunction($fConf, $dataArray, $aConfig);
					} else {
						// otherwise the fieldconfig has to be created by dynaflex
						if (isset($fConf['label_offset']) && is_integer($fConf['label_offset'])) {
							$labelIndex = $count + intval($fConf['label_offset']);
						} else {
							$labelIndex = $count;
						}
						$fConf['label'] = $this->getLabel($fConf['label'], array('###DATA###' => $fieldData, '###FINDEX###' => $labelIndex));

						// set some basics
						$aConfig['name'] = $fConf['name'];
						$aConfig['label'] = $fConf['label'];
						$aConfig['config'] = $fConf['config'];

						// add the data to the fieldconfig (this is useful for userfields inside of
						// an flexform in the case some of the data is needed for further processing
						$aConfig['config']['data'] = $fieldData;

						// replace all markers in title and label
						// make special handling of ###FINDEX### and ###DATA###

						if (is_array($fieldData)) {
							$fieldData['DATA'] = $fIndex;
						} else {
							$fieldData = array();
							$fieldData['DATA'] = $aConfig['config']['data'];
						}
						$fieldData['FINDEX'] = $count;

						$this->callbackValues = $fieldData;
						$aConfig['name'] = preg_replace_callback('/###(.[^#]*)###/i', array($this, 'replaceMarker'), $aConfig['name']);
						$aConfig['label'] = preg_replace_callback('/###(.[^#]*)###/i', array($this, 'replaceMarker'), $aConfig['label']);
						$aConfig['config']['foreign_table_where'] =
							preg_replace_callback('/###(.[^#]*)###/i', array($this, 'replaceMarker'), $aConfig['config']['foreign_table_where']);
					}

					if (!$aConfig) {
						continue;
					}

					if ($aConfig['multi'] == TRUE) {
						// a multifield configuration was returned
						if (is_array($aConfig['fields'])) {
							foreach ($aConfig['fields'] as $fieldConfig) {
								$this->replaceAndAdd($fieldConfig, $markerArray);
							}
						}
					} else {
						$this->replaceAndAdd($aConfig, $markerArray);
					}

					if (is_array($funcConf['modifications'])) {
						$this->handleModifications(
							$funcConf['modifications'],
							array('###FINDEX###' => $fIndex)
						);
					}

					$fIndex++;
				}
			}

			if (isset($funcConf['allUserFunc'])) {
				$fieldConfigs = t3lib_div::callUserFunction($funcConf['allUserFunc'], $funcDataArray, $baseConfig);
				foreach ($fieldConfigs as $fieldConfig) {
					$this->addField($fieldConfig);
				}
			}
		}
	}

	/**
	 * @param array $matches
	 * @return string
	 */
	protected function replaceMarker($matches) {
		$result = strtolower($matches[1]);
		if (isset($this->callbackValues[$result])) {
			$result = $this->callbackValues[$result];
		}
		return $result;
	}

	/**
	 * Takes a dynaflex config for a single field, replaces the markers from
	 * the markerArray in fields "path", "name" and "label". And calls
	 * "addField" with the modified config.
	 *
	 * @param array $config The dynaflex configuration for a single field
	 * @param array $markerArray An array with marker => value pairs
	 * @return void
	 */
	public function replaceAndAdd($config, $markerArray = array()) {
		foreach ($markerArray as $marker => $value) {
			$config['path'] = str_replace($marker, $value, $config['path']);
			$config['name'] = str_replace($marker, $value, $config['name']);
			$config['label'] = str_replace($marker, $value, $config['label']);
		}
		$this->addField($config);
	}

	/**
	 * Appends a string to current flexData. In this case the flexData is a
	 * simple string from the TCA!
	 *
	 * @param array $funcConf configuration array for a single configuration
	 * @return void
	 */
	public function append($funcConf) {
		$this->flexData .= $funcConf['config']['text'];
	}


	/**
	 * Handling other
	 */

	/**
	 * Moves an element from one path into another
	 *
	 * @param array $funcConf configuration array for a single configuration
	 * @return void
	 */
	public function move($funcConf = array()) {
		if (isset($funcConf['type']) && $funcConf['type'] == 'extraFields') {
			$sourceData = explode(',', $this->flexData);

			// cycle through the global ext configuration
			if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['dynaflex']['extraFields'][$funcConf['table']])) {
				return;
			}
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['dynaflex']['extraFields'][$funcConf['table']] as $dest => $fields) {
				if (!is_array($fields)) {
					continue;
				}
				$foundDest = array_search($dest, $sourceData);
				if ($foundDest === FALSE || $foundDest == NULL) {
					continue;
				}
				foreach ($fields as $field) {
					$sIndex = $this->searchBeginInArray($field, $sourceData);
					$fieldData = $sourceData[$sIndex];
					if ($sIndex !== FALSE) {
						unset($sourceData[$sIndex]);
					} else {
						continue;
					}
					// split up the source array and insert the data after the field
					// which is defined by the keys
					$destKey = $this->searchBeginInArray($dest, $sourceData);
					if ($destKey === FALSE) {
						continue;
					}
					$sourceData = $this->insertAfterKey($destKey, $sourceData, $fieldData);
				}
			}

			// repack
			$this->flexData = implode(',', $sourceData);

		} else {
			// get paths
			$sourcePath = $this->getPath($funcConf['source']);
			$destPath = $this->getPath($funcConf['dest']);

			// get the data
			$sourceData = $this->getDataByPath($this->flexData, $sourcePath);
			$destData = $this->getDataByPath($this->flexData, $destPath);

			// replace or add to the destination data
			if ($funcConf['overwrite']) {
				$destData = $sourceData;
			} else {
				$destData = array_merge($destData, $sourceData);
			}

			// write back the destination
			$this->setDataByPath($this->flexData, $destPath, $destData);

			// build configuration for removing the source
			if (!isset($funcConf['remove'])) {
				// if nothing is defined in configuration, use the sourcepath to
				// determine what has to be removed
				// get the element
				$removeElement = $sourcePath[count($sourcePath) - 1];
				unset($sourcePath[count($sourcePath) - 1]);
				// the rest of the path is the path from which the element should be removed
				$removePath = implode('/', $sourcePath);
			} else {
				// otherwise the path is given in key "remove"
				$removePath = $this->getPath($funcConf['remove']);
				$removeElement = $removePath[count($removePath) - 1];
				unset($removePath[count($removePath) - 1]);
				$removePath = implode('/', $removePath);
			}

			// build configuration
			$removeConf = array (
				'inside' => $removePath,
				'element' => $removeElement,
			);

			// and remove it
			$this->remove($removeConf);
		}
	}

	/**
	 * Removes an element from a flexform structure.
	 *
	 * @param array $funcConf configuration array for a single configuration
	 * @return void
	 */
	public function remove($funcConf = array()) {
		// build path
		$path = $this->getPath($funcConf['inside']);

		// get the Data
		$workData = $this->getDataByPath($this->flexData, $path);

		// remove the element
		unset($workData[$funcConf['element']]);

		// Write back the modified data
		$this->setDataByPath($this->flexData, $path, $workData);
	}

	/**
	 * calls a userfunction
	 *
	 * @param array $funcConf configuration array for a single configuration
	 * @return void
	 */
	public function call($funcConf = array()) {
		t3lib_div::callUserFunction($funcConf['function'], $this->ceUid, $this->flexData);
	}

	/**
	 * Does the cleanup. This removes all not needed elements from the data XML
	 * in the database of this content element.
	 * If TYPO3 version is 4.0 or greater we use the build in methods from
	 * flexformtools. Otherwise we use a copy of that class that is shipped
	 * with DynaFlex.
	 *
	 * @param string $field
	 * @return void
	 */
	public function doCleanup($field) {
		$row = t3lib_BEfunc::getRecord($this->ceTable, intval($this->ceUid));

		/** @var t3lib_flexformtools $fft */
		$fft = t3lib_div::makeInstance('t3lib_flexformtools');
		$newFlexData = $fft->cleanFlexFormXML($this->ceTable, $field, $row);

		// write back the result to the database
		/** @var t3lib_db $database */
		$database = $GLOBALS['TYPO3_DB'];
		$database->exec_UPDATEquery($this->ceTable, 'uid = ' . $this->ceUid, array($field => $newFlexData));
	}


	/**
	 * Loads the specific DCA for a table. It loads the plain DCA from the
	 * first class (usually the extension that wants to change a flexform) and
	 * calls all registered hooks for this table. The hooks are usually
	 * provided by a third-party extension that want to extend the orginal DCA.
	 *
	 * @param string $table The table we are working on
	 * @param int $pid
	 * @param array $row
	 * @return array
	 */
	public static function loadDynaFlexConfig($table, $pid, &$row) {
		$resultDca = NULL;

		if (!is_array($GLOBALS['T3_VAR']['ext']['dynaflex'][$table])) {
			return $resultDca;
		}

		$cleanUpFields = array();
		$tableRegs = $GLOBALS['T3_VAR']['ext']['dynaflex'][$table];
		foreach ($tableRegs as $tableRegRef) {
			if ($tableRegRef == 'TS') {
				/** @var t3lib_beUserAuth $backendUser */
				$backendUser = $GLOBALS['BE_USER'];

				// load the DCA from page typoscript
				$pageTsConfig = $backendUser->getTSConfig('dynaflex.' . $table, t3lib_BEfunc::getPagesTSconfig($pid));
				$resultDca = self::removeTrailingDotsRecursive($pageTsConfig['properties']);
			} else {
				// load the DCA from a class and maybe some hooks from within the class
				$tableRegObj = t3lib_div::getUserObj($tableRegRef);

				// check if should do anything
				if (isset($tableRegObj->rowChecks) && is_array($tableRegObj->rowChecks)) {
					foreach ($tableRegObj->rowChecks as $fieldName => $checkValue) {
						if ($row[$fieldName] != $checkValue) {
							continue 2;
						}
					}
				}

				if (empty($resultDca)) {
					$resultDca = $tableRegObj->DCA;
				}

				if (is_array($tableRegObj->hooks)) {
					foreach ($tableRegObj->hooks as $classRef) {
						$hookObj = t3lib_div::getUserObj($classRef);

						if (method_exists($hookObj, 'alterDCA_onLoad')) {
							$hookObj->alterDCA_onLoad($resultDca, $table);
						}
					}
				}

				if (is_array($tableRegObj->cleanUpField)) {
					$cleanUpFields = array_unique(array_merge($cleanUpFields, $tableRegObj->cleanUpField));
				}
			}
		}

		return array('DCA' => $resultDca, 'cleanUpField' => $cleanUpFields);
	}

	/**
	 * Awaits a DCA configured in TypoScript and runs through all elements and
	 * removes the dots from the end if one was found.
	 *
	 * @param array $typoscript The DCA as TypoScript
	 * @return array a valid DCA
	 */
	public static function removeTrailingDotsRecursive($typoscript) {
		$result = array();

		foreach ($typoscript as $key => $value) {
			$key = preg_replace('/\.$/', '', $key);
			if (is_array($value)) {
				$value = self::removeTrailingDotsRecursive($value);
			}
			$result[$key] = $value;
		}

		return $result;
	}
}

if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/dynaflex/class.dynaflex.php']) {
	require_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/dynaflex/class.dynaflex.php']);
}
