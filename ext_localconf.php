<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

/**
 * Here we register various hooks. Inside every hook, DynaFlex is called and loads the registered configurations
 * and hooks that can alter the configs.
 */
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tceforms.php']['getMainFieldsClass'][] = 'EXT:dynaflex/class.tx_dynaflex_callhooks.php:tx_dynaflex_callhooks';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_befunc.php']['getFlexFormDSClass'][] = 'EXT:dynaflex/class.tx_dynaflex_callhooks.php:tx_dynaflex_callhooks';
/*
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = 'EXT:dynaflex/class.tx_dynaflex_callhooks.php:tx_dynaflex_callhooks';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = 'EXT:dynaflex/class.tx_dynaflex_callhooks.php:tx_dynaflex_callhooks';
*/
?>
