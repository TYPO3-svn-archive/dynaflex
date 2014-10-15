<?php

$extensionPath = t3lib_extMgm::extPath('dynaflex');

return array(
	'dynaflex' => $extensionPath . 'class.dynaflex.php',
	'tx_dynaflex_callhooks' => $extensionPath . 'class.tx_dynaflex_callhooks.php',

	'tx_dynaflex_utility_tcautility' => $extensionPath . 'Classes/Utility/TcaUtility.php',
	'tx_dynaflex_hook_callhook' => $extensionPath . 'Classes/Hook/CallHook.php',
);