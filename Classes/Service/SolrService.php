<?php
namespace In2code\In2solrautoupdate\Service;

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
use ApacheSolrForTypo3\Solr\Site;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;
use ApacheSolrForTypo3\Solr\IndexQueue\Indexer;
use In2code\In2solrautoupdate\IndexQueue\Initializer\Basic;
use ApacheSolrForTypo3\Solr\IndexQueue\Initializer\AbstractInitializer;

/**
 * Class SolrService
 *
 * @package in2solrautoupdate
 */
class SolrService implements SingletonInterface
{
    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $itemUid;

    /**
     * @var \ApacheSolrForTypo3\Solr\IndexQueue\Queue
     */
    protected $indexQueue;

    /**
     * @var array
     */
    protected $solrTs;

    /**
     * @var array
     */
    protected $in2solrautoupdateTs;

    /**
     * @var \ApacheSolrForTypo3\Solr\GarbageCollector
     */
    protected $garbageCollector;

    /**
     * General initialization.
     *
     * @param string $type
     * @param string $itemUid
     * @return void
     */
    protected function initialize($type = null, $itemUid = null)
    {
        $this->type = $type;
        $this->itemUid = $itemUid;
    }

    /**
     * Initialize Indexing.
     *
     * @param string $type
     * @param string $itemUid
     * @return void
     */
    public function initializeIndexing($type = null, $itemUid = null)
    {
        $this->initialize($type, $itemUid);
        $this->indexQueue = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\IndexQueue\\Queue');
        $objMgr = GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
        $beConfMgr = $objMgr->get('TYPO3\\CMS\\Extbase\\Configuration\\BackendConfigurationManager');
        $this->solrTs = $beConfMgr->getConfiguration('solr');
        $in2solrautoupdateTs = $beConfMgr->getConfiguration('in2solrautoupdate');
        $this->in2solrautoupdateTs = ObjectAccess::getPropertyPath($in2solrautoupdateTs, 'settings.' . $this->type);
    }

    /**
     * Initialize the deletion operation.
     *
     * @param string $type
     * @param string $itemUid
     * @return void
     */
    public function initializeDeletion($type, $itemUid)
    {
        $this->initialize($type, $itemUid);
        $this->garbageCollector = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\GarbageCollector');
    }

    /**
     * A factory method to get an indexer depending on an item's configuration.
     *
     * By default all items are indexed using the default indexer
     * (ApacheSolrForTypo3\Solr\IndexQueue\Indexer) coming with EXT:solr. Pages by default are
     * configured to be indexed through a dedicated indexer
     * (ApacheSolrForTypo3\Solr\IndexQueue\PageIndexer). In all other cases a dedicated indexer
     * can be specified through TypoScript if needed.
     *
     * @param string $indexingConfigurationName Indexing configuration name.
     * @return Indexer An instance of In2code\\In2plugins\\Utility\\Backend\\Solr\\Indexer or a sub class of it.
     */
    protected function getIndexerByItem($indexingConfigurationName)
    {
        $indexerClass = 'ApacheSolrForTypo3\\Solr\\IndexQueue\\Indexer';
        $indexerOptions = array();

        // allow to overwrite indexers per indexing configuration
        if (isset($this->solrTs['index.']['queue.'][$indexingConfigurationName . '.']['indexer'])) {
            $indexerClass = $this->solrTs['index.']['queue.'][$indexingConfigurationName . '.']['indexer'];
        }

        // get indexer options
        if (isset($this->solrTs['index.']['queue.'][$indexingConfigurationName . '.']['indexer.']) &&
            !empty($this->solrTs['index.']['queue.'][$indexingConfigurationName . '.']['indexer.'])) {
            $indexerOptions = $this->solrTs['index.']['queue.'][$indexingConfigurationName . '.']['indexer.'];
        }

        $indexer = GeneralUtility::makeInstance($indexerClass, $indexerOptions);
        if (!($indexer instanceof Indexer)) {
            throw new \RuntimeException('The indexer class "' . $indexerClass . '" for indexing configuration "' .
                $indexingConfigurationName .
                '" is not a valid indexer. Must be a subclass of ApacheSolrForTypo3\Solr\IndexQueue\Indexer.',
                1260463206);
        }

        return $indexer;
    }

    /**
     * Log the error
     *
     * @param \Exception $e
     * @param Item $indexQueueItem
     * @param string $errorMessage
     * @return void
     */
    protected function handleIndexOperationException(\Exception $e, Item $indexQueueItem, $errorMessage)
    {
        $this->indexQueue->markItemAsFailed($indexQueueItem, $e->getCode() . ': ' . $e->__toString());

        GeneralUtility::devLog($errorMessage, 'solr', LogLevel::ERROR, array(
            'code' => $e->getCode(),
            'message' => $e->getMessage(),
            'trace' => $e->getTrace(),
            'item' => (array)$indexQueueItem
        ));
    }

    /**
     * Indexes an Index-Queue-Item, meaning it writes it to the solr-index.
     *
     * @param Item $indexQueueItem
     * @return \Apache_Solr_Response
     */
    public function indexItem(Item $indexQueueItem)
    {
        // try indexing
        $indexer = $this->getIndexerByItem($indexQueueItem->getIndexingConfigurationName());
        $indexed = $indexer->index($indexQueueItem);

        // update IQ item so that the IQ can determine what's been indexed already
        if ($indexed) {
            $indexQueueItem->updateIndexedTime();
        }

        return $indexed;
    }

    /**
     * Returns the root-page-id for the given type and itemUid.
     *
     * @return mixed
     */
    protected function getRootPageId()
    {
        # Fetch it from in2solrautoupdate-TypoScript if defined there.
        $rootPageId = ObjectAccess::getPropertyPath($this->in2solrautoupdateTs, 'rootPageId');

        if (empty($rootPageId)) {
            # Fetch it based on the page-tree which the item is placed in.
            $record = BackendUtility::getRecord($this->type, $this->itemUid, 'pid');
            $rootPageId = !empty($record) ? Util::getRootPageId($record['pid']) : null;
        }

        return $rootPageId;
    }

    /**
     * Returns the class-path for the Initializer. Either it's defined in tx_solr-TypoScript or we use the default
     * Record-Initializer.
     *
     * @param string $configurationName
     * @return mixed
     */
    protected function getInitializer($configurationName)
    {
        $initializerClass = ObjectAccess::getPropertyPath($this->solrTs,
            "index.queue.$configurationName.initialization");

        if (empty($initializerClass)) {
            $initializerClass = 'ApacheSolrForTypo3\Solr\IndexQueue\Initializer\Record';
        }

        return $initializerClass;
    }

    /**
     * We need this to enforce the Initializer to only update the item with uid=$this->itemUid in the Index-Queue.
     * Otherwise the Initializer would update all Queue-items.
     *
     * @param Basic $basicInitializer
     * @return void
     */
    protected function addItemUidToAdditionalWhereClause(Basic $basicInitializer)
    {
        $tableAlias = $basicInitializer->getTableAlias();
        /**
         * array_filter() removes empty string-elements from array. That would be useful if additionalWhereClause is not
         * set via TypoScript.
         */
        $additionalWhereClause = array_filter(array($this->solrTs['additionalWhereClause'],
            "$tableAlias.uid=" . $this->itemUid));
        $this->solrTs['additionalWhereClause'] = implode(' AND ', $additionalWhereClause);
    }

    /**
     * We enforce
     *
     * @param AbstractInitializer $initializer
     * @throws \Exception
     */
    protected function throwExceptionIfNotExtendingBasic(AbstractInitializer $initializer)
    {
        if (!($initializer instanceof Basic)) {
            throw new \Exception(
                'Your initializer has to extend "In2code\In2solrautoupdate\IndexQueue\Initializer\Basic"!');
        }
    }

    /**
     * Adds an item to the Index-Queue.
     *
     * @return boolean
     */
    protected function addNewItemToQueue()
    {
        $configurationName = ObjectAccess::getPropertyPath($this->in2solrautoupdateTs, 'configurationName');
        $rootPageId = $this->getRootPageId();

        if (empty($rootPageId)) {
            return false;
        }

        $initializer = GeneralUtility::makeInstance($this->getInitializer($configurationName));
        $this->throwExceptionIfNotExtendingBasic($initializer);
        $this->addItemUidToAdditionalWhereClause($initializer);
        $site = Site::getSiteByPageId($rootPageId);
        $initializer->setSite($site);
        $initializer->setType($this->type);
        $initializer->setIndexingConfiguration($this->solrTs);
        $initializer->setIndexingConfigurationName($configurationName);
        $initializer->initialize();

        return true;
    }

    /**
     * Returns Index-Queue-Items and adds them to Index-Queue if there are not there yet.
     *
     * @param string $type
     * @param string $itemUid
     * @param boolean $addToIndexQueueIfNotDone
     * @return array
     */
    protected function getItems($type, $itemUid, $addToIndexQueueIfNotDone = false)
    {
        $indexQueueItems = $this->indexQueue->getItems($type, $itemUid);

        if (empty($indexQueueItems) && $addToIndexQueueIfNotDone) {
            $indexQueueItems = $this->addNewItemToQueue();
        }

        return $indexQueueItems;
    }

    /**
     * Indexes an item from the Index Queue. Internally it first renders our item and then sends it via the solr
     * operation "<add>" to the solr-Server. But if the <doc> (index entry) already exists on the solr-server it is
     * simply overwritten. This way an update can be realized.
     *
     * @param Item $indexQueueItem An index queue item to index
     * @param boolean $index
     * @return boolean TRUE if the item was successfully indexed, FALSE otherwise
     */
    public function updateItem(Item $indexQueueItem, $index = false)
    {
        $updateSuccess = true;

        try {
            # Let's update the "changed"-field, so in case indexing fails, this item is still marked as "to-index"
            # for the next solr-index-scheduler-run.
            $this->indexQueue->updateItem($indexQueueItem->getType(), $indexQueueItem->getRecordUid());

            if ($index) {
                $updateSuccess = $this->indexItem($indexQueueItem);
            }
        } catch (\Exception $e) {
            $this->handleIndexOperationException($e, $indexQueueItem,
                'Failed indexing Index Queue item ' . $indexQueueItem->getIndexQueueUid());
            $updateSuccess = false;
        }

        return $updateSuccess;
    }

    /**
     * Indexes all occurence of a specific item from the Index-Queue to the solr-index.
     *
     * @param string $type
     * @param string $itemUid
     * @param boolean $addToIndexQueueIfNotDone
     * @param boolean $index
     * @return boolean TRUE if all items have been successfully indexed, FALSE otherwise.
     */
    public function updateAllOccurencesOfItem($type, $itemUid, $addToIndexQueueIfNotDone = false, $index = false)
    {
        $this->initializeIndexing($type, $itemUid);
        $updateSuccess = true;

        // record can be indexed for multiple sites
        $indexQueueItems = $this->getItems($type, $itemUid, $addToIndexQueueIfNotDone);

        if (empty($indexQueueItems)) {
            return false;
        }

        foreach ($indexQueueItems as $indexQueueItem) {
            if (!$this->updateItem($indexQueueItem, $index)) {
                $updateSuccess = false;
                break;
            }
        }

        return $updateSuccess;
    }

    /**
     * Deletes all occurences of a specific item of a specific table from sorl-Index and from solr-Queue.
     *
     * @param string $type Is the table name
     * @param string $itemUid Is the table-record
     * @return boolean TRUE if all items have been successfully indexed, FALSE otherwise.
     */
    public function deleteAllOccurencesOfItem($type, $itemUid)
    {
        $this->initializeDeletion($type, $itemUid);
        $this->garbageCollector->collectGarbage($type, $itemUid);

        return true;
    }
}
