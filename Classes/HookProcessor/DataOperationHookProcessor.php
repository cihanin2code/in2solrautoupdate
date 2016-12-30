<?php
namespace In2code\In2solrautoupdate\HookProcessor;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Cihan Yesilöz <cihan@in2code.de>, in2code GmbH
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
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;

/**
 * Class DataOperationHookProcessor
 *
 * We're doing $GLOBALS['TSFE']->getPageRenderer()->setBackPath(''); because some of the solr-extension's functions
 * initializes TSFE. Therefore after execution of our Hook-Processor paths like 'typo3/typo3/.../FormEngine.js are
 * generated which leads to a broken TCE-Form not reacting to user input.
 *
 * @package in2solrautoupdate
 */
class DataOperationHookProcessor
{
    const NOTARGET = 'notarget';
    # Only update the solr-Index-Queue-table "tx_solr_indexqueue_item"
    const TARGET_QUEUE = 'q';
    # Update the "tx_solr_indexqueue_item"-table and the solr-index.
    const TARGET_QUEUE_AND_INDEX = 'i';

    const NOOP = 'nooperation';
    # This only adds newly created records to the Index-Queue-table "tx_solr_indexqueue_item"
    const TARGET_OPERATION_AUTO_ADD = 'automaticAdd';
    # These two apply the changes to "tx_solr_indexqueue_item" and directly to the solr-index
    const TARGET_OPERATION_AUTO_UPDATE = 'automaticUpdate';
    const TARGET_OPERATION_AUTO_DELETE = 'automaticDelete';

    /**
     * @var array
     */
    protected $tsSettings;

    /**
     * @var array
     */
    protected $fieldsToCheck;

    /**
     * @var string
     */
    protected $target;

    /**
     * @var string
     */
    protected $targetOperation;

    /**
     * @var \In2code\In2solrautoupdate\Service\SolrService
     */
    protected $solrService;

    /**
     * This is needed if TSFE is still initialized after the solr-action. In that case our TCE-Form wouldn't react
     * any more.
     *
     * @return void
     */
    protected function unsetBackPath()
    {
        $pageRenderer = !empty($GLOBALS['TSFE']) ? $GLOBALS['TSFE']->getPageRenderer() : null;
        if (!empty($pageRenderer)) {
            $pageRenderer->setBackPath('');
        }
    }

    /**
     * General initialization.
     *
     * @param string $table
     * @return bool
     */
    protected function init($table)
    {
        $this->target = self::NOTARGET;
        $this->targetOperation = self::NOOP;

        $objMgr = GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
        $beConfMgr = $objMgr->get('TYPO3\\CMS\\Extbase\\Configuration\\BackendConfigurationManager');
        $tsSettings = $beConfMgr->getConfiguration('in2solrautoupdate');

        try {
            $tsSettings = ArrayUtility::getValueByPath($tsSettings, "settings/$table");
        } catch (\Exception $e) {
            $tsSettings = null;
        }

        if (empty($tsSettings)) {
            return false;
        }

        $this->tsSettings = $tsSettings;
        $this->solrService = GeneralUtility::makeInstance('In2code\\In2solrautoupdate\\Service\\SolrService');

        return true;
    }

    /**
     * Initializes adding process.
     *
     * @param string $table
     * @return bool
     */
    protected function initAdd($table)
    {
        return $this->init($table) && ((int)ObjectAccess::getPropertyPath($this->tsSettings, 'automaticAdd') == 1);
    }

    /**
     * Initializes the updating process.
     *
     * @return boolean
     */
    protected function initUpdate()
    {
        $fieldsToCheck = ObjectAccess::getPropertyPath($this->tsSettings, 'fieldsToCheck');

        if (empty($fieldsToCheck)) {
            return false;
        }

        $this->fieldsToCheck = array_keys($fieldsToCheck);

        return true;
    }

    /**
     * Initializes the delete process.
     *
     * @param string $table
     * @return boolean
     */
    protected function initDelete($table)
    {
        return $this->init($table) && ((int)ObjectAccess::getPropertyPath($this->tsSettings, 'automaticDelete') == 1);
    }

    /**
     * Extracts the list of field-names which are monitored by this extension and have been updated.
     *
     * @param array $fieldArray
     * @return array
     */
    protected function getUpdatedFields(&$fieldArray)
    {
        return array_intersect($this->fieldsToCheck, array_keys($fieldArray));
    }

    /**
     * Updates an Index-Queue-Item to the Solr-Index. We add the item to Index-Queue if it's not there yet in case
     * the current operation is a "create/new"-operation.
     *
     * @param string $type
     * @param string $itemUid
     * @return bool
     */
    protected function updateSolrIndex($type, $itemUid)
    {
        $result = $this->solrService->updateAllOccurencesOfItem($type, $itemUid,
            ($this->targetOperation == self::TARGET_OPERATION_AUTO_ADD),
            ($this->target == self::TARGET_QUEUE_AND_INDEX));
        $this->unsetBackPath();

        return $result;
    }

    /**
     * Returns the found "target"-TypoScript property for the passed TypoScript configuration.
     *
     * @param array $targetOptionTs
     * @return mixed
     */
    protected function getTarget($targetOptionTs)
    {
        $target = ObjectAccess::getPropertyPath($targetOptionTs, 'target');
        $availableTargets = array(self::TARGET_QUEUE, self::TARGET_QUEUE_AND_INDEX);
        $foundTargetIndex = array_search($target, $availableTargets);

        return ($foundTargetIndex !== false) ? $availableTargets[$foundTargetIndex] : null;
    }

    /**
     * Returns matching target options for the given field or table.
     *
     * $targetOperationsToCheck example:
     * array(self::TARGET_OPERATION_AUTO_UPDATE);
     *
     * $tcaFieldToCheck example:
     * fe_groups
     * --> in case = null --> table-TypoScript (e.g. sys_file_metadata) is checked.
     *
     * Returns target-information. E.g.:
     * array('target'=>self::TARGET_QUEUE_AND_INDEX, 'targetOperation'=>self::TARGET_OPERATION_AUTO_UPDATE,
     *      'typoScript'=>array('target'=>'i'...));
     *
     * @param array $targetOperationsToCheck
     * @param string $tcaFieldToCheck
     * @return array
     */
    protected function getTargetOptions($targetOperationsToCheck, $tcaFieldToCheck = null)
    {
        $targetOptions = array('target' => null, 'targetOperation' => null);

        foreach ($targetOperationsToCheck as $targetOperationToCheck) {
            /**
             * Example:
             *  1) sys_file_metadata.automaticUpdate.target = q
             *  2) sys_file_metadata.fe_groups.automaticUpdate.target = i
             */
            $targetOptionTsPath = (!empty($tcaFieldToCheck) ? "$tcaFieldToCheck." : '') . $targetOperationToCheck;
            $targetOptionTs = ObjectAccess::getPropertyPath($this->tsSettings, $targetOptionTsPath);

            if (!empty($targetOptionTs)) {
                $targetOptions['target'] = $this->getTarget($targetOptionTs);
                $targetOptions['targetOperation'] = !empty($targetOptions['target']) ? $targetOperationToCheck : null;
                $targetOptions['typoScript'] = !empty($targetOptions['target']) ? $targetOptionTs : null;
                break;
            }
        }

        return $targetOptions;
    }

    /**
     * Sets the target variables.
     *
     * @param array $targetOptions
     * @return void
     */
    protected function setTargetOptions($targetOptions)
    {
        if (!empty($targetOptions['target'])) {
            $this->target = $targetOptions['target'];
            $this->targetOperation = $targetOptions['targetOperation'];
        }
    }

    /**
     * Returns matching target options for the given field.
     *
     * @param string $tcaFieldName
     * @return array
     */
    protected function getTargetOptionsForUpdatedField($tcaFieldName)
    {
        $targetOptionsToCheck = array(self::TARGET_OPERATION_AUTO_DELETE, self::TARGET_OPERATION_AUTO_UPDATE);

        return $this->getTargetOptions($targetOptionsToCheck, $tcaFieldName);
    }

    /**
     * Sets target options for updated fields.
     * Important: A TypoScript definition for a solr-delete-operation based on a field is always dominant against
     * a solr-update-operation based on a field. As soon as a updated field matches a TypoScript-field, which tells us
     * to remove the solr-<doc> from solr-queue and solr-index, we abort checking and immediately remove the solr-<doc>.
     *
     * @param array $fieldArray
     * @return void
     */
    protected function setTargetOptionsForUpdatedFields($fieldArray)
    {
        $this->initUpdate();
        $updatedFields = $this->getUpdatedFields($fieldArray);
        $finalOpFound = false;
        $targetOptions = null;

        foreach ($updatedFields as $updatedField) {
            $targetOptions = $this->getTargetOptionsForUpdatedField($updatedField);

            switch ($targetOptions['targetOperation']) {
                case self::TARGET_OPERATION_AUTO_UPDATE:
                    $this->setTargetOptions($targetOptions);
                    break;
                case self::TARGET_OPERATION_AUTO_DELETE:
                    $this->setTargetOptions($targetOptions);
                    $finalOpFound = true;
                    break;
                default:
                    break;
            }

            if ($finalOpFound) {
                break;
            }
        }
    }

    /**
     * Sets target options for table-row.
     *
     * @param array $targetOperationsToCheck
     * @return void
     */
    protected function setTargetOptionsForRow($targetOperationsToCheck)
    {
        $this->setTargetOptions($this->getTargetOptions($targetOperationsToCheck));
    }

    /**
     * Sets target options for updated table-row.
     *
     * @return void
     */
    protected function setTargetOptionsForUpdatedRow()
    {
        $this->setTargetOptionsForRow(array(self::TARGET_OPERATION_AUTO_UPDATE));
    }

    /**
     * Processes the update-operation.
     *
     * @param array $fieldArray
     * @return void
     */
    protected function processUpdate($fieldArray)
    {
        $this->setTargetOptionsForUpdatedFields($fieldArray);

        /**
         * In case there is no specific TypoScript-definition for a field-update we check whether there is a
         * general TypoScript-definition for a table-update for the currently active table.
         */
        if ($this->target == self::NOTARGET) {
            $this->setTargetOptionsForUpdatedRow();
        }
    }

    /**
     * Sets target options for newly created table-row.
     *
     * @return void
     */
    protected function setTargetOptionsForNewRow()
    {
        $this->setTargetOptionsForRow(array(self::TARGET_OPERATION_AUTO_ADD));
    }

    /**
     * Processes the create/new-operation.
     *
     * @return void
     */
    protected function processNew()
    {
        $this->setTargetOptionsForNewRow();
    }

    /**
     * Initialization
     *
     * @param string $status Status (update/new/usw.)
     * @param string $table Table name (sys_file_metadata)
     * @param array $fieldArray all updated fields
     * @return boolean
     */
    protected function processDatamap_init(
        $status,
        $table,
        &$fieldArray = null
    ) {
        if (!$this->init($table)) {
            return false;
        }

        switch ($status) {
            case 'new':
                $this->processNew();
                break;
            case 'update':
                $this->processUpdate($fieldArray);
                break;
            default:
                break;
        }

        return true;
    }

    /**
     * Why we're not doing this operations in the "processDatamap_postProcessFieldArray" instead?
     * Because there the data are not stored in the database yet. As the indexing functions of solr read from database
     * for indexing, doing a reindexing in processDatamap_postProcessFieldArray() would only index the current old
     * state of the record and not the changed one.
     *
     * @param string $status Status (update/new/usw.)
     * @param string $table Table name (sys_file_metadata)
     * @param integer $uid The record's uid
     * @param array $fieldArray all updated fields
     * @param DataHandler $dataHandler
     * @return void
     */
    public function processDatamap_afterDatabaseOperations(
        $status,
        $table,
        $uid,
        &$fieldArray = null,
        DataHandler &$dataHandler = null
    ) {
        if (!($this->processDatamap_init($status, $table, $fieldArray) || ($this->targetOperation === self::NOOP))) {
            return;
        }

        switch ($this->targetOperation) {
            case self::TARGET_OPERATION_AUTO_ADD:
            case self::TARGET_OPERATION_AUTO_UPDATE:
                $this->updateSolrIndex($table, $uid);
                break;
            case self::TARGET_OPERATION_AUTO_DELETE:
                $this->deleteSolrIndex($table, $uid);
                break;
            default:
                break;
        }

        $this->targetOperation = self::NOOP;
    }

    /**
     * Deletes an item from index-Queue and solr-index.
     *
     * @param string $type
     * @param string $itemUid
     * @return bool
     */
    protected function deleteSolrIndex($type, $itemUid)
    {
        $result = $this->solrService->deleteAllOccurencesOfItem($type, $itemUid);
        $this->unsetBackPath();

        return $result;
    }

    /**
     * $dataHandler is null in case this Hook is called by our custom extension and the operation (like new, move or
     * delete) hasn't been done via TCE-form (DataHandler)
     *
     * @param string $command The command.
     * @param string $table The table the record belongs to
     * @param integer $uid The record's uid
     * @param string $value Not used
     * @param DataHandler $dataHandler TYPO3 Core Engine parent object, not used
     * @return void
     */
    public function processCmdmap_postProcess($command, $table, $uid, $value = null, DataHandler $dataHandler = null)
    {
        switch ($command) {
            case 'delete':
                if ($this->initDelete($table)) {
                    $this->deleteSolrIndex($table, $uid);
                }
                break;
            default;
                break;
        }
    }
}
