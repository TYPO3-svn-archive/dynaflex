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
 * @maintainer	Ingo Schmitt <is@marketing-factory.de>
 */
class tx_dynaflex_callhooks extends Tx_Dynaflex_Hook_CallHook {
}