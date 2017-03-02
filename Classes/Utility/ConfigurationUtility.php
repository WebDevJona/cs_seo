<?php
namespace Clickstorm\CsSeo\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2016 Marc Hirdes <hirdes@clickstorm.de>, clickstorm GmbH
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
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
 * Get Extension Configuration
 *
 * Class ConfigurationUtility
 * @package Clickstorm\CsSeo\Utility
 */
class ConfigurationUtility {

	/**
	 * Get the configuration from the extension manager
	 *
	 * @return array
	 */
	public static function getEmConfiguration() {
		$conf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['cs_seo']);
		return is_array($conf) ? $conf : [];
	}

	/**
	 * return the allowed doktypes of pages for evaluation
	 *
	 * @return array
	 */
	public static function getEvaluationDoktypes() {
		$allowedDoktypes = [1];
		$extConf = self::getEmConfiguration();
		if($extConf['evaluationDoktypes']) {
			$allowedDoktypes = GeneralUtility::trimExplode(',', $extConf['evaluationDoktypes']);
		}
		return $allowedDoktypes;
	}

}