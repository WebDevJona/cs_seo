<?php

namespace Clickstorm\CsSeo\Command;

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

use Clickstorm\CsSeo\Domain\Model\Evaluation;
use Clickstorm\CsSeo\Domain\Repository\EvaluationRepository;
use Clickstorm\CsSeo\Service\EvaluationService;
use Clickstorm\CsSeo\Service\FrontendPageService;
use Clickstorm\CsSeo\Utility\ConfigurationUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Http\AjaxRequestHandler;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use \TYPO3\CMS\Extbase\Mvc\Controller\CommandController;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

/**
 * Class EvaluationCommandController
 *
 * @package Clickstorm\CsSeo\Command
 */
class EvaluationCommandController extends CommandController
{

    /**
     * @var \Clickstorm\CsSeo\Domain\Repository\EvaluationRepository
     * @inject
     */
    protected $evaluationRepository;

    /**
     * @var \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager
     * @inject
     */
    protected $persistenceManager;

    /**
     * @var string
     */
    protected $tableName = 'pages';

    /**
     * @param int $uid
     * @param string $tableName
     */
    public function updateCommand($uid = 0, $tableName = '')
    {
        if (!empty($tableName)) {
            $this->tableName = $tableName;
        }
        $this->processResults($uid);
    }

    /**
     * make the ajax update
     *
     * @param array $params Array of parameters from the AJAX interface, currently unused
     * @param AjaxRequestHandler $ajaxObj Object of type AjaxRequestHandler
     *
     * @return void
     */
    public function ajaxUpdate($params = [], AjaxRequestHandler &$ajaxObj = null)
    {
        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $this->evaluationRepository = $this->objectManager->get(EvaluationRepository::class);
        $this->persistenceManager = $this->objectManager->get(PersistenceManager::class);

        // get parameter
        $table = '';
        if (empty($params)) {
            $uid = $GLOBALS['GLOBALS']['HTTP_POST_VARS']['uid'];
            $table = $GLOBALS['GLOBALS']['HTTP_POST_VARS']['table'];
        } else {
            $attr = $params['request']->getParsedBody();
            $uid = $attr['uid'];
            $table = $attr['table'];
        }
        if ($table != '') {
            $this->tableName = $table;
        }
        $this->processResults($uid);

        /** @var $flashMessageService \TYPO3\CMS\Core\Messaging\FlashMessageService */
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        /** @var $defaultFlashMessageQueue \TYPO3\CMS\Core\Messaging\FlashMessageQueue */
        $flashMessageQueue = $flashMessageService->getMessageQueueByIdentifier('tx_csseo');
        $ajaxObj->addContent('messages', $flashMessageQueue->renderFlashMessages());
    }

    /**
     * @param int $uid
     * @param bool $localized
     */
    protected function processResults($uid = 0, $localized = false)
    {
        $query = $this->buildQuery($uid, $localized);
        $items = $this->getAllItems($query);
        $this->updateResults($items);

        if (!$localized) {
            $this->processResults($uid, true);
        }
    }

    /**
     * @param $items
     */
    protected function updateResults($items)
    {
        foreach ($items as $item) {
            /** @var FrontendPageService $frontendPageService */
            $frontendPageService = GeneralUtility::makeInstance(FrontendPageService::class, $item, $this->tableName);
            $frontendPage = $frontendPageService->getFrontendPage();

            if (isset($frontendPage['content'])) {
                /** @var EvaluationService $evaluationUtility */
                $evaluationUtility = GeneralUtility::makeInstance(EvaluationService::class);

                $results = $evaluationUtility->evaluate($frontendPage['content'], $this->getFocusKeyword($item));

                $this->saveChanges($results, $item['uid'], $frontendPage['url']);
            }
        }
    }

    /**
     * store the results in the db
     *
     * @param array $results
     * @param int $uidForeign
     * @param string $url
     */
    protected function saveChanges($results, $uidForeign, $url)
    {
        /**
         * @var Evaluation $evaluation
         */
        $evaluation = $this->evaluationRepository->findByUidForeignAndTableName($uidForeign, $this->tableName);

        if (!$evaluation) {
            $evaluation = GeneralUtility::makeInstance(Evaluation::class);
            $evaluation->setUidForeign($uidForeign);
            $evaluation->setTablenames($this->tableName);
        }

        $evaluation->setUrl($url);
        $evaluation->setResults($results);

        if ($evaluation->_isNew()) {
            $this->evaluationRepository->add($evaluation);
        } else {
            $this->evaluationRepository->update($evaluation);
        }
        $this->persistenceManager->persistAll();
    }

    /**
     * @param $uid
     * @param bool $localizations
     *
     * @return string
     */
    protected function buildQuery($uid, $localizations = false)
    {
        $constraints = ['1'];
        $tcaCtrl = $GLOBALS['TCA'][$this->tableName]['ctrl'];
        $allowedDoktypes = ConfigurationUtility::getEvaluationDoktypes();

        // only with doktype page
        if ($this->tableName == 'pages') {
            $constraints[] = 'doktype IN (' . implode(',', $allowedDoktypes) . ')';
        }

        // check localization
        if ($localizations) {
            if ($tcaCtrl['transForeignTable']) {
                $this->tableName = $tcaCtrl['transForeignTable'];
	            $tcaCtrl = $GLOBALS['TCA'][$this->tableName]['ctrl'];
            } else {
                if ($tcaCtrl['languageField']) {
                    $constraints[] = $tcaCtrl['languageField'] . ' > 0';
                } elseif ($this->tableName == 'pages') {
	                $this->tableName = 'pages_language_overlay';
	                $tcaCtrl = $GLOBALS['TCA'][$this->tableName]['ctrl'];
                }
            }
        }

        // if single uid
        if ($uid > 0) {
            if ($localizations && $tcaCtrl['transOrigPointerField']) {
	            $constraints[] = $tcaCtrl['transOrigPointerField'] . ' = ' . $uid;
            } else {
                $constraints[] = 'uid = ' . $uid;
            }
        }
        return implode($constraints, ' AND ') . BackendUtility::BEenableFields($this->tableName) . BackendUtility::deleteClause($this->tableName);
    }

    /**
     * @param $where
     *
     * @return array
     */
    protected function getAllItems($where)
    {
        $items = [];

        $res = $this->getDatabaseConnection()->exec_SELECTquery(
            '*',
            $this->tableName,
            $where
        );
        while ($row = $this->getDatabaseConnection()->sql_fetch_assoc($res)) {
            $items[] = $row;
        }
        return $items;
    }

    /**
     * Get Keyword from record or page
     *
     * @param $record
     * @return string
     */
    protected function getFocusKeyword($record)
    {
        $keyword = '';
        if ($record['tx_csseo']) {
            $metaTableName = 'tx_csseo_domain_model_meta';
            $where = 'tablenames = \'' . $this->getDatabaseConnection()->quoteStr($this->tableName,
                    $metaTableName) . '\'';
            $where .= ' AND uid_foreign = ' . $record['uid'];
            $where .= BackendUtility::BEenableFields($metaTableName);
            $where .= BackendUtility::deleteClause($metaTableName);
            $res = $this->getDatabaseConnection()->exec_SELECTquery(
                'keyword',
                $metaTableName,
                $where
            );

            while ($row = $this->getDatabaseConnection()->sql_fetch_assoc($res)) {
                $keyword = $row['keyword'];
            }
        } else {
            $keyword = $record['tx_csseo_keyword'];
        }
        return $keyword;
    }

    /**
     * @return string
     */
    public function getTableName()
    {
        return $this->tableName;
    }

    /**
     * @param string $tableName
     */
    public function setTableName($tableName)
    {
        $this->tableName = $tableName;
    }

    /**
     * Returns the database connection
     *
     * @return \TYPO3\CMS\Core\Database\DatabaseConnection
     */
    protected function getDatabaseConnection()
    {
        return $GLOBALS['TYPO3_DB'];
    }
}
