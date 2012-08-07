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
 * @package		TYPO3
 * @subpackage	dynaflex
 *
 * @author		Thomas Hempel <thomas@typo3-unleashed.net>
 * @maintainer	Ingo Schmitt <is@marketing-factory.de>
 */
class dynaflex	{
		// the internal representation of the Typo Configuration Array (TCA)
	var $orgTCA = array();

		// configuration for TCA modfications
	var $conf = array();

		// The language identifier for the content element
	var $ceLang = 'DEF';

		// The table the current content element is placed in
	var $ceTable = '';

		// the uid of the content element we're working on
	var $ceUid = 0;
	
		// the page id of the content element
	var $cePid = 0;
	
		// stores all data of the content element
	var	$ceFields = array();
	
		// The flexform data (XML or Array) the class is currently working on
	var $flexData;

		// This variable will be filled with the datastructure of a collection of modifications
	var $dataStructArray = array();
	
		// A dummy that holds the data, from a condition
	var $conditionData;
	var $path = '';


	/** BASE **/

	/**
	 * Constructor of this class. Does nothing else than setting some internal class variables
	 *
	 * @param	array		$TCA: The original TCA (or any other array that should be modfied)
	 * @param	array		$conf: The DCA (Dynaflex Configuration Array)
	 * @return	void
	 */
	function dynaflex($TCA = array(), $conf = array())	{
		$this->init($TCA, $conf);
	}


	/**
	 * This method is needed because you can't pass any arguments to the constructor
	 * if you instantiate the class with TYPO3 methods (t3lib_div::makeInstance). So you
	 * have to call this method after initializing and BEFORE the getDynamicTCA
	 * So this method initializes some values dynaflex needs for working.
	 *
	 * @param	array		$TCA: the orginal TCA array that should be modfied
	 * @param	array		$conf: the configuration array for dynaflex
	 * @return	void
	 */
	function init($TCA = array(), $conf = array())	{
		$this->orgTCA = $TCA;
		$this->conf = $conf;

			// determine the language we are working on
			// by the way we're trying to get some data of the content element we're working on
		$contentInfo = $GLOBALS['SOBE']->editconf;

			// fetch it from PostVars if nothing was found in SOBE
		if (empty($contentInfo))	{
			$contentInfo = t3lib_div::_GP('edit');
		}
		
			// if we got any information about the current content element
		if (is_array($contentInfo))	{
				// get the table
				// The table can be defined on dynaflex call or not at all. A explicite definition of a table
				// can be useful when dynaflex is called in a multitable editview (like columnsOnly) environment.
			if (empty($conf['workingTable']))	{
				$this->ceTable = array_keys($contentInfo);
				$this->ceTable = $this->ceTable[0];
			} else {
				$this->ceTable = $conf['workingTable'];
			}

				// and the UID of this content element
			$this->ceUid = array_keys($contentInfo[$this->ceTable]);
			$this->ceUid = intval($this->ceUid[0]);
			
			$contentValues = array_values($contentInfo);
			$uidData = ($contentValues[0][$this->ceUid]);
			if ($uidData == 'new')	{
					// we have a new dataset
				$this->cePid = $this->ceUid;
				$this->ceUid = 0;
			} else {
					// last but not least the page id of the content element
				if ($this->ceUid != 0)	{
					$pidRes = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->ceTable, 'uid='.$this->ceUid);					
					if ($pidRes)	{
						$pidRes = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($pidRes);
						$this->ceFields = $pidRes;
						$this->cePid = $pidRes['pid'];
					}
				}
			}

				// we only do the rest if the content table has localization option in the TCA
			$languageField = $TCA[$this->ceTable]['ctrl']['languageField'];
			if (!empty($languageField))	{
					// in this case we're trying to get the current language identifier for this explcite content element
				$sysLangUid = t3lib_BEfunc::getRecord($this->ceTable, intval($this->ceUid), $languageField);
				if ($sysLangUid[$languageField] > 0)	{
					$langIsoCode = t3lib_BEfunc::getRecord('sys_language', intval($sysLangUid[$languageField]), 'static_lang_isocode');
					$langIdent = t3lib_BEfunc::getRecord('static_languages', intval($langIsoCode['static_lang_isocode']), 'lg_typo3');
					$this->ceLang = strtoupper($langIdent['lg_typo3']);
				}
			}
		}

		if (!empty($conf['uid']))	{
			$this->ceUid = $conf['uid'];
		}
	}


	/**
	 * main method of this class. Processes the configuration array and calls the different internal
	 * methods for modifieng the TCA.
	 *
	 * @return	array		The modified array that was passed by the init method
	 */
	function getDynamicTCA()	{
		// if (empty($this->conf) || empty($this->orgTCA))	return $this->orgTCA;
			// Process every item (path) in the configuration
		while (list($runIndex, $singleConf) = each($this->conf))	{
				
				// build the path-array
			$this->path = $this->getPath($singleConf['path']);

				// get the data from the TCA
			$this->flexData = $this->getDataFromTCAByPath($this->path);
			
				// load the file if it is a refrence and not an XML
			if (substr($this->flexData, 0, 5) == 'FILE:')	{
				$file = t3lib_div::getFileAbsFileName(substr($this->flexData, 5));
				if ($file && @is_file($file))	{
					$this->flexData = t3lib_div::getUrl($file);
				}
			}
				// if the option is not set or set as true make an array from the xml
			if (!isset($singleConf['parseXML']) || $singleConf['parseXML'] == true)	{
				$this->flexData = t3lib_div::xml2array($this->flexData);
			}

			if ($this->handleModifications($singleConf['modifications']) === false) continue;
			
			$this->dataStructArray[$runIndex] = $this->flexData;

				// Write the result back to the internal TCA
			if (!isset($singleConf['parseXML']) || $singleConf['parseXML'] == true)	{
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
	 * @param	string		$path: A string that represents the path, the single steps are devided by "/"
	 * @return	array		where every element is a step inside the path
	 */
	function getPath($path = '')	{
		if ($path == '') return $path;
		return explode('/', $path);
	}

	/**
	 * Returns the content of the internal TCA at the end of the given path.
	 *
	 * @param	array		$path: the path of the content that should be returned
	 * @return	mixed		(depends on the data that is stored at the path)
	 */
	function getDataFromTCAByPath($path = array())	{
		$data = $this->getDataByPath($this->orgTCA, $path);
		return $data;
	}

	/**
	 * Writes some content into the internal TCA at the end of the path.
	 *
	 * @param	array		$path: the path where the data should be stored
	 * @param	mixed		$data: The data that should be stored
	 * @return	void
	 */
	function setDataInTCAByPath($path, $data)	{
		return $this->setDataByPath($this->orgTCA, $path, $data);
	}

	/**
	 * Get the content of the element of the source array at the end of the given path.
	 *
	 * @param	array		$source: the array from which the result should be extracted
	 * @param	mixed		$path: the path where the result can be found (if the path is a string, it will be transformed into an array)
	 * @return	mixed		(depends on the data that is stored at the path)
	 */
	function getDataByPath($source, $path)	{
			// get the path
		if (!is_array($path)) $path = $this->getPath($path);
		$data = $source;

			// fetch from source array
		if (is_array($path))	{
			foreach ($path as $pathPart)	{
				$data = $data[$pathPart];
			}
		}

			// return the fetched data
		return $data;
	}

	/**
	 * Writes some content into the given array at the end of the path.
	 *
	 * @param	array		$dest: The array where the data is stored in (PASSED BY REFERENCE!)
	 * @param	mixed		$path: the path inside the dest, where the data should be stored (if it is a string it will be transformed into an array)
	 * @param	mixed		$data: the data that should be stored
	 * @return	void
	 */
	function setDataByPath(&$dest, $path, $data)	{
		if (!is_array($path)) $path = $this->getPath($path);
		$dataDest = &$dest;

		if (is_array($path) && is_array($dataDest))	{
			foreach ($path as $pathPart)	{
				$dataDest = &$dataDest[$pathPart];
			}
		}
		$dataDest = $data;
	}

	/**
	 * Inserts the data into the current FlexForm data with the key inside the given path.
	 *
	 * @param	mixed		$path: the path inside the current FlexForm data where the data should be added
	 * @param	string		$key: the key of the new array element
	 * @param	mixed		$data: the data that should be added
	 * @return	void
	 */
	function insertDataAtPath($path, $key, $data)	{
			// get data
		$theData = $this->getDataByPath($this->flexData, $path);
		if (!is_array($theData)) $theData = (strlen(trim($theData)) == 0) ? array() : array($theData);

			// add data
		$theData[$key] = $data;

			// write back to flexform
		$this->setDataByPath($this->flexData, $path, $theData);
	}

	/**
	 * inserts a new row after a given key inside of an array.
	 *
	 * @param	mixed		$key: The key of the element aftr which the new row should be inserted
	 * @param	array		$array: The array on which the action is performed
	 * @param	mixed		$newRow: The new row which is inserted into the array
	 * @return	array		The updated array
	 */
	function insertAfterKey($key, $array, $newRow)	{
		if (!is_array($array)) return $array;

		$before = array_slice($array, 0, $key);
		$after = array_slice($array, $key);

			// build the new array
		$new = array();
		foreach ($before as $row)	{
			$new[] = $row;
		}
		$new[] = $newRow;
		foreach ($after as $row)	{
			$new[] = $row;
		}

		return $new;
	}

	/**
	 * Cycles through an array and searches if the beginning of the data inside the array
	 * is the same as the given searchword. This only works if the data is a string.
	 *
	 * @param	string		$word: The word for which it should search
	 * @param	array		$array: The array in which should be searched
	 * @return	boolean		False if something is wrong with the data or nothing was found. Otherwise the key of the first row that matches the searchword.
	 */
	function searchBeginInArray($word, $array)	{
		if (!is_array($array)) return false;
		$word = trim($word);
		$wLen = strlen($word) +1;
		foreach ($array as $key => $data)	{
			if (!is_string($data)) continue;
			if (trim(substr($data, 0, $wLen)) == $word) return $key;
		}
		return false;
	}


	/**
	 * Fetches data from a specific field inside of an TCE form. It fetches the data
	 * from a special field inside the database. If a path and a xml_field is set
	 * it replaces some markers in the path and the name of the xml_field and fetches
	 * the value of the field inside of the flexform structure. If no xml_field is set,
	 * the value of the database field itself is returned.
	 *
	 * @param	array		$sourceConfig: A source_config part of the dynaflex configuration
	 * @param	array		$markerArray: An array with marker => value pairs
	 * @return	mixed		Depends on the data inside the field
	 */
	function getFieldData($sourceConfig, $markerArray = array())	{
			// if the field is in the same table than the current or if the table if not set
			// we don't need to fetch anything from the database because the requested data
			// should be in the GP vars
		$ceData = t3lib_div::_GP('data');
		$ceData = $ceData[$this->ceTable][$this->ceUid];
		
		if (!empty($ceData) && (empty($sourceConfig['table']) || $sourceConfig['table'] == $this->ceTable))	{
			$sourceData[$sourceConfig['db_field']] = $ceData[$sourceConfig['db_field']];
		} else {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				$sourceConfig['db_field'],
				$sourceConfig['table'],
				'uid=' .$this->ceUid
			);
			$sourceData = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		}
		
		foreach ($markerArray as $marker => $value)	{
			$sourceConfig['path'] = str_replace($marker, $value, $sourceConfig['path']);
			$sourceConfig['xml_field'] = str_replace($marker, $value, $sourceConfig['xml_field']);
		}
		
		if (!empty($sourceConfig['xml_field']))	{
			$sourceData = (is_array($sourceData[$sourceConfig['db_field']])) ? $sourceData[$sourceConfig['db_field']] : t3lib_div::xml2array($sourceData[$sourceConfig['db_field']]);
			$sourceData = $this->getDataByPath($sourceData['data'], $sourceConfig['path'] .'/' .$sourceConfig['xml_field']);
			$sourceData = $sourceData['vDEF'];
		} else {
			$sourceData = $sourceData[$sourceConfig['db_field']];
		}

		return $sourceData;
	}

	/**
	 * Returns the label for a field, sheet or whatever. If the labelConfig is an array
	 * it tries to fetch the label from the database, based on the settings in the config.
	 * Otherwise it simply returns the labelConfig.
	 * In every case, markers in the result are replaced with the values from the markerArray.
	 *
	 * @param	mixed		$labelConfig: Array with config or simple string
	 * @param	array		$markerArray: An array with marker => value pairs
	 * @return	string		The label with replaced markers
	 */
	function getLabel($labelConfig, $markerArray = array())	{
		$label = '';
		$markerArray['###ce_uid###'] = $this->ceUid;
		$markerArray['###ce_pid###'] = $this->cePid;
		
		if (is_array($labelConfig))	{
				// the label config is an array, that means that we have to fetch something from the database
			foreach ($markerArray as $marker => $value)	{
				$labelConfig['where'] = str_replace($marker, $value, $labelConfig['where']);
			}
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				$labelConfig['field'],
				$labelConfig['table'],
				$labelConfig['where'],
				'',
				'',
				1
			);
			$labelData = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			$label = $labelData[$labelConfig['field']];
		} else {
			foreach ($markerArray as $marker => $value)	{
				$labelConfig = str_replace($marker, $value, $labelConfig);
			}
			$label = $labelConfig;
		}
		
		return $label;
	}


	/** Handling basics
	 **/


	/**
	 * Handles the modifications. We pass a "modifications" section to this method
	 * and it calls the responsible method.
	 *
	 * @param	array		$modifications: a modifications part of the dynaflex configuration
	 * @param	array		$markerArray: an array that contains some marker => value pairs.
	 * @return	boolean		False if the passed modifications are not an array, otherwise false.
	 */
	function handleModifications($modifications, $markerArray = array())	{
		if (!is_array($modifications)) return false;

			// process all modification definitons for this path
		while (list($modificationIndex, $singleFunc) = each($modifications))	{
				// check the conditions if any conditions are set (THIS IS DEPRECATED!)
			$doIt = $this->checkCondition($singleFunc['condition']);

				// if more conditions are set in the "conditions" field, check them all
			if ($doIt && isset($singleFunc['conditions']) && is_array($singleFunc['conditions']))	{
				foreach ($singleFunc['conditions'] as $condition)	{
						// process the single condition, but only if it is an array
					if (is_array($condition)) $doIt = $this->checkCondition($condition);

						// leave the cycle if the last condition doesn't match
					if (!$doIt)	break;
				}
			}

				// process the modification if condition is true
			if ($doIt)	{
					// check if a condition is set for a group of modifications
				if (!isset($singleFunc['elements']))	{
					$elements = array($singleFunc);
				} else {
					$elements = $singleFunc['elements'];
				}

				while (list($elCount, $modConf) = each($elements))	{
					switch (strtolower((string)$modConf['method']))	{
							// Adding something somewhere
						case 'add': $this->add($modConf, $markerArray); break;
						case 'move': $this->move($modConf); break;
						case 'remove': $this->remove($modConf); break;
						case 'function_call': $this->call($modConf); break;

					}
				}
			}
		}

		return true;
	}


	/**
	 * Checks a condition if it is valid
	 *
	 * @param	array		$config: The configuration of the current SCA
	 * @param	array		$condition:The configuration array
	 * @return	boolean
	 */
	function checkCondition($condition)	{
		unset($this->conditionData);

			// without a condition we can't check anything
		if (!is_array($condition)) return true;

			// if the uid is set but no integer exit (that shouldn't be)
		if (isset($this->ceUid) && !is_int($this->ceUid)) return false;

			// replace some placeholders in the select queries
		$where = str_replace('###uid###', $this->ceUid, $condition['where']);

			// get type
		$condSource = strtolower($condition['source']);

			// get data
		switch ($condSource)	{
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
			default:
					// fetch the data from database
				// debug(array($GLOBALS['TYPO3_DB']->SELECTquery($condition['select'], $condition['table'], $where)));
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($condition['select'], $condition['table'], $where);
				$data = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				if ($condition['select'] != '*')	{
					$data = $data[$condition['select']];
				}
				break;
		}
		
		
		if ($condition['isXML']) $data = t3lib_div::xml2array($data);
		if (isset($condition['path']))	{
			$data = $this->getDataByPath($data, $condition['path']);
		}
		
		$isTrue = false;

			// make the comparison
		switch ((string)$condition['if'])	{
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
		}

			// and return the result
		if ($isTrue)	{
			$this->conditionData = $data;
			return true;
		} else {
			return false;
		}
	}


	/** HANDLING **/


	/**
	 * The switching method to write something into the flexForm Structure
	 *
	 * @param	array		$funcConf: the complete configuration for this MA
	 * @param	array		$markerArray: An array with marker => value pairs
	 * @return	void
	 */
	function add($funcConf = array(), $markerArray = array())	{
		switch ((string)$funcConf['type'])	{
			case 'staticXML':	$this->addStaticXML($funcConf, $markerArray); break;
			case 'sheet':		$this->addSheet($funcConf, $markerArray); break;
			case 'sheets':		$this->addSheets($funcConf, $markerArray); break;
			case 'field':		$this->addField($funcConf, $markerArray); break;
			case 'fields':		$this->addFields($funcConf, $markerArray); break;
			case 'append':		$this->append($funcConf, $markerArray); break;
		}
	}

	/**
	 * Adds a XML-structure into the XML-structure of another field after a
	 * specific element or at the beginning of the structure.
	 * The XML-structure that should be inserted MUST be wrapped in some tags.
	 * Normally <ROOT> and </ROOT> are used.
	 *
	 * @param	array		$funcConf: configuration array for a single configuration
	 * @param	array		$markerArray: An array with marker => value pairs
	 * @return	void
	 */
	function addStaticXML($funcConf = array(), $markerArray = array())	{
			// get the array of the structure
		$formAddition = t3lib_div::xml2array($funcConf['data']);

			// Now determine the path and get the fielddata from the flexForm structure
		$path = $this->getPath($funcConf['inside']);
		$oldData = $this->getDataByPath($this->flexData, $path);

		$newData = array();

			// cycle through the data and insert the new field after the field setted in conf
			// The new data will allways inserted on the same layer where the element is placed
			// on after which it should be inserted.
		if ($funcConf['beforeAll'])	{
				// If the new structure should be inserted at the beginning of the old structure
				// this is handeld here
			$newData = array_merge($formAddition, $oldData);
		} elseif (isset($funcConf['after']))	{
				// otherwise cycle through the elements in this level...
			foreach ($oldData as $key => $value)	{
				$newData[$key] = $value;
					// If the field is reached after which the new structure should be inserted...
				if ($key == $funcConf['after'])	{
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
	 * @param	array		$funcConf: configuration array for a single configuration
	 * @param	array		$markerArray: An array with marker => value pairs
	 * @return	void
	 */
	function addSheet($funcConf = array(), $markerArray = array())	{
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
	 * Adds a various number of sheets at the root level of the datastructure. How many sheets
	 * are inserted are defined by "source" field in funcConf.
	 *
	 * @param	array		$funcConf: configuration array for a single configuration
	 * @param	array		$markerArray: An array with marker => value pairs
	 * @return	void
	 */
	function addSheets($funcConf = array(), $markerArray = array())	{
		switch (strtolower((string)$funcConf['source']))	{
			case 'field':
				$sourceData = $this->getFieldData($funcConf['source_config']);
				$sheetCount = intval($sourceData);
			break;
		}

		for ($sIndex = 0; $sIndex < $sheetCount; $sIndex++)	{
			$sheetName = $funcConf['sheet_config']['name'] .'_' .$sIndex;
			$sheetConf = array(
				'label' => $this->getLabel($funcConf['sheet_config']['label'], array('###SINDEX###' => ($sIndex +1))),
				'name' => $sheetName,
			);
			$this->addSheet($sheetConf);
			if (is_array($funcConf['sheet_config']['fields']))	{
				foreach ($funcConf['sheet_config']['fields'] as $fieldConf)	{
					$fieldConfig = $fieldConf;
					$fieldConfig['path'] = 'sheets/' .$sheetName .'/ROOT/el';
					$fieldConfig['name'] .= '_' .$sIndex;
					$fieldConfig['label'] = str_replace('###SINDEX###', ($sIndex +1), $fieldConfig['label']);
					$this->addField($fieldConfig);
				}
			}

			if (is_array($funcConf['modifications']))	{
				$this->handleModifications(
					$funcConf['modifications'],
					array('###SINDEX###' => $sIndex)
				);
			}
		}
	}

	/**
	 * Adds a field at the given position. The XML is build from an array, like it's used
	 * for a "normal" TCE field.
	 *
	 * @param	array		$funcConf: configuration array for a single configuration
	 * @param	array		$markerArray:  An array with marker => value pairs
	 * @return	void
	 */
	function addField($funcConf, $markerArray = array())	{
		if (isset($funcConf['field_config']))	{
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
		if (isset($data['defaultExtras']))	{
			$fieldArray['TCEforms']['defaultExtras'] = $data['defaultExtras'];
		}
		
		$this->insertDataAtPath($funcConf['path'], $data['name'], $fieldArray);
	}

	/**
	 * Adds a series of fields at the given position. The fields will be created
	 * by special configurations.
	 *
	 * @param	array		$funcConf: configuration array for a single configuration
	 * @param	array		$markerArray:  An array with marker => value pairs
	 * @return	void
	 */
	function addFields($funcConf, $markerArray = array())	{
			// get the data
		switch (strtolower((string)$funcConf['source']))	{
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
				for ($index = 0; $index < count($whereMarker[0]); $index++) {
					$handling['where'] = str_replace(
						$whereMarker[0][$index], 
						$this->ceFields[$whereMarker[1][$index]], 
						$handling['where']
					);
 				}				
				$dbData = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
					$handling['select'],
					$handling['table'],
					$handling['where'],
					$handling['groupby'],
					$handling['orderby'],
					$handling['limit']
				);

				$data = array();
				while ($dummy = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dbData))	{
					$data[] = $dummy;
				}
				break;
				
			case '':
				$data = array('empty');
				break;
				
			default:
				$data = (string)$funcConf['source'];
				break;
		}
		
			// leave here if source was set but no data was found
		if (empty($data) && isset($funcConf['source']))	return;

		if (isset($funcConf['source_type']))	{
			switch (strtolower((string)$funcConf['source_type']))	{
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
					break;
			}
		}


			// get the sourcedata from a flexform datastructure if no config is given
		if (!isset($funcConf['source_config']) && !empty($formConf['source']))	{
			$TCADataArray = t3lib_div::xml2array($this->getDataFromTCAByPath($funcConf['source_config']['handling']['TCApath']));
			$XMLDataArray = $this->getDataByPath($TCADataArray, $funcConf['source_config']['handling']['XMLpath']);

				// get the keys for sequencing the fields
			$keys = array_keys($XMLDataArray);

				// build the basic configuration
			$config = array (
				'inside' => $funcConf['source_config']['path'],
				'data' => '',
			);

				// cycle through all fields of the source content element
				// in every cycle the name of the field is appended by _X where X is
				// the uid of the dataset in the table
			while (list($count, $uid) = each($data))	{
					// rename all keys
				if (!is_array($keys)) continue;

				foreach ($keys as $key)	{
					$singleData = $XMLDataArray[$key];
					$theData[$key .'_' .$uid] = $singleData;
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

			if (isset($fConfA['config'])) $fConfA = array($fConfA);

			$funcDataArray = array();

			$baseConfig = array (
				'path' => $funcConf['path'],
			);

			while (list($count, $fieldData) = each($data))	{
				if (empty($fieldData)) continue;

				$dataArray = array('row' => $fieldData, 'dfConfig' => $funcConf);
				$funcDataArray[] = $dataArray;

				if (!is_array($fConfA)) continue;
				$fIndex = 0;

				foreach ($fConfA as $confKey => $fConf)	{
					$aConfig = $baseConfig;
					if ($confKey === 'singleUserFunc')	{
							// The fieldconfig should be created by a userfunction, so we call it here
						$aConfig = t3lib_div::callUserFunction($fConf, $dataArray, $aConfig);
					} else {
							// otherwise the fieldconfig has to be created by dynaflex
						if (isset($fConf['label_offset']) && is_integer($fConf['label_offset']))	{
							$labelIndex = $count +intval($fConf['label_offset']); 
						} else {
							$labelIndex = $count;
						}
						$fConf['label'] = $this->getLabel($fConf['label'], array('###DATA###' => $fieldData, '###FINDEX###' => $labelIndex));
						preg_match_all('/###(.[^#]*)###/i', $fConf['name'], $nameMarker);
						preg_match_all('/###(.[^#]*)###/i', $fConf['label'], $labelMarker);
						preg_match_all('/###(.[^#]*)###/i', $fConf['config']['foreign_table_where'], $ftwMarker);
						
							// set some basics
						$aConfig['name'] = $fConf['name'];
						$aConfig['label'] = $fConf['label'];
						$aConfig['config'] = $fConf['config'];

							// add the data to the fieldconfig (this is useful for userfields inside of
							// an flexform in the case some of the data is needed for further processing
						$aConfig['config']['data'] = $fieldData;

							// replace all markers in title and label
							// make special handling of ###FINDEX### and ###DATA###
						
						if (is_array($fieldData))	{
							$fieldData['DATA'] = $fIndex;
						} else {
							$fieldData = array();
							$fieldData['DATA'] = $aConfig['config']['data'];
						}
						$fieldData['FINDEX'] = $count;
						
						for ($index = 0; $index < count($nameMarker[0]); $index++)	{
							$aConfig['name'] = str_replace($nameMarker[0][$index], str_replace(' ', '', $fieldData[$nameMarker[1][$index]]), $aConfig['name']);
						}
						for ($index = 0; $index < count($labelMarker[0]); $index++)	{
							$aConfig['label'] = str_replace($labelMarker[0][$index], $fieldData[$labelMarker[1][$index]], $aConfig['label']);
						}
						for ($index = 0; $index < count($ftwMarker[0]); $index++) {
							$aConfig['config']['foreign_table_where'] = str_replace($ftwMarker[0][$index], $fieldData[$ftwMarker[1][$index]], $aConfig['config']['foreign_table_where']);
						}						
					}
					
					if (!$aConfig) continue;

					if ($aConfig['multi'] == true)	{
							// a multifield configuration was returned
						if (is_array($aConfig['fields']))	{
							foreach ($aConfig['fields'] as $fieldConfig)	{
								$this->replaceAndAdd($fieldConfig, $markerArray);
							}
						}
					} else {
						$this->replaceAndAdd($aConfig, $markerArray);
					}

					if (is_array($funcConf['modifications']))	{
						$this->handleModifications(
							$funcConf['modifications'],
							array('###FINDEX###' => $fIndex)
						);
					}

					$fIndex++;
				}
			}

			if (isset($funcConf['allUserFunc']))	{
				$fieldConfigs = t3lib_div::callUserFunction($funcConf['allUserFunc'], $funcDataArray, $baseConfig);
				foreach ($fieldConfigs as $fieldConfig) $this->addField($fieldConfig);
			}
		}
	}

	/**
	 * Takes a dynaflex config for a single field, replaces the markers from the markerArray in fields
	 * "path", "name" and "label". And calls "addField" with the modified config.
	 *
	 * @param	array		$config: The dynaflex configuration for a single field
	 * @param	array		$markerArray:  An array with marker => value pairs
	 * @return	void
	 */
	function replaceAndAdd($config, $markerArray = array())	{
		foreach ($markerArray as $marker => $value)	{
			$config['path'] = str_replace($marker, $value, $config['path']);
			$config['name'] = str_replace($marker, $value, $config['name']);
			$config['label'] = str_replace($marker, $value, $config['label']);
		}
		$this->addField($config);
	}

	/**
	 * Appends a string to current flexData. In this case the flexData is a simple string from the TCA!
	 *
	 * @param	array		$funcConf: configuration array for a single configuration
	 * @return	void
	 */
	function append($funcConf)	{
		$this->flexData .= $funcConf['config']['text'];
	}


	/** Handling other
	 **/


	/**
	 * Moves an element from one path into another
	 *
	 * @param	array		$funcConf: configuration array for a single configuration
	 * @return	void
	 */
	function move($funcConf = array())	{
		global $TYPO3_CONF_VARS;

		if (isset($funcConf['type']) && $funcConf['type'] == 'extraFields')	{
			$sourceData = explode(',', $this->flexData);

				// cycle through the global ext configuration
			if (!is_array($TYPO3_CONF_VARS['EXTCONF']['dynaflex']['extraFields'][$funcConf['table']])) return;
			foreach ($TYPO3_CONF_VARS['EXTCONF']['dynaflex']['extraFields'][$funcConf['table']] as $dest => $fields)	{
				if (!is_array($fields)) continue;
				$foundDest = array_search($dest, $sourceData);
				if ($foundDest === false || $foundDest == NULL) continue;
				foreach ($fields as $field)	{
					$sIndex = $this->searchBeginInArray($field, $sourceData);
					$fieldData = $sourceData[$sIndex];
					if ($sIndex !== false)	{
						unset($sourceData[$sIndex]);
					} else {
						continue;
					}
						// split up the source array and insert the data after the field
						// which is defined by the keys
					$destKey = $this->searchBeginInArray($dest, $sourceData);
					if ($destKey === false) continue;
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
			if ($funcConf['overwrite'])	{
				$destData = $sourceData;
			} else {
				$destData = array_merge($destData, $sourceData);
			}

				// write back the destination
			$this->setDataByPath($this->flexData, $destPath, $destData);

				// build configuration for removing the source
			if (!isset($funcConf['remove']))	{
					// if nothing is defined in configuration, use the sourcepath to
					// determine what has to be removed
					// get the element
				$removeElement = $sourcePath[count($sourcePath) -1];
				unset($sourcePath[count($sourcePath) -1]);
					// the rest of the path is the path from which the element should be removed
				$removePath = implode('/', $sourcePath);
			} else {
					// otherwise the path is given in key "remove"
				$removePath = $this->getPath($funcConf['remove']);
				$removeElement = $removePath[count($removePath) -1];
				unset($removePath[count($removePath) -1]);
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
	 * @param	array		$funcConf: configuration array for a single configuration
	 * @return	void
	 */
	function remove($funcConf = array())	{
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
	 * @param	array		$funcConf: configuration array for a single configuration
	 * @return	void
	 */
	function call($funcConf = array())	{
		t3lib_div::callUserFunction($funcConf['function'], $this->ceUid, $this->flexData);
	}
	
	
	/**
	 * Does the cleanup. This removes all not needed elements from the data XML in the database of this
	 * content element.
	 * If TYPO3 version is 4.0 or greater we use the build in methods from flexformtools. Otherwise we use
	 * a copy of that class that is shipped with DynaFlex.
	 */
	function doCleanup($field)	{
		$row = t3lib_BEfunc::getRecord($this->ceTable, intval($this->ceUid));
		
		$t3Version = floatval($GLOBALS['TYPO3_VERSION']?$GLOBALS['TYPO3_VERSION']:$GLOBALS['TYPO_VERSION']);

		if ($t3Version >= 4.0)	{
				// use flextools
			require_once(PATH_t3lib.'class.t3lib_flexformtools.php');
			$fft = t3lib_div::makeInstance('t3lib_flexformtools');
			$newFlexData = $fft->cleanFlexFormXML($this->ceTable, $field, $row);
		} else {
				// use own class
			require_once(t3lib_extMgm::extPath('dynaflex').'class.ux_t3lib_flexformtools.php');
			$fft = t3lib_div::makeInstance('ux_t3lib_flexformtools');
			$newFlexData = $fft->cleanFlexFormXML($this->ceTable, $field, $row);
		}
		
			// write back the result to the database
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->ceTable, 'uid='.$this->ceUid, array($field => $newFlexData));
	} 

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dynaflex/class.dynaflex.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dynaflex/class.dynaflex.php']);
}
?>
