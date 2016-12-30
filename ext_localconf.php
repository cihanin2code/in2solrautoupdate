<?php

defined('TYPO3_MODE') or die('Access denied.');

/*
 * Our Hook-processor called after a TCE-form(DataHandler)-operation like move or delete has been performed.
 * The function which has to be defined in the DataHandlerHookProcessor and called by the Hook-executer:
 * processCmdmap_postProcess()
 */
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] =
    'EXT:in2solrautoupdate/Classes/HookProcessor/DataHandlerHookProcessor.php:'.
    'In2code\\In2solrautoupdate\\HookProcessor\\DataHandlerHookProcessor';

/**
 * This Hook-processor can be executed by any third-party-extension in case the operation (like new, move or delete)
 * is not done via TCE-form (DataHandler).
 * The function which has to be defined in the DataHandlerHookProcessor and called by the Hook-executer:
 * processCmdmap_postProcess()
 */
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['in2solrautoupdate']['in2solrautoupdateProcessCmdmapClass'][] =
    'EXT:in2solrautoupdate/Classes/HookProcessor/DataHandlerHookProcessor.php:'.
    'In2code\\In2solrautoupdate\\HookProcessor\\DataHandlerHookProcessor';

/*
 * Our Hook-processor called after an update of fields via TCE-form (DataHandler) has been done
 * The function which has to be defined in the DataHandlerHookProcessor and called by the Hook-executer:
 * processDatamap_postProcessFieldArray()
 */
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] =
    'EXT:in2solrautoupdate/Classes/HookProcessor/DataHandlerHookProcessor.php:'.
    'In2code\\In2solrautoupdate\\HookProcessor\\DataHandlerHookProcessor';

/**
 * This Hook-processor can be executed by any third-party-extension in case the field-update is not done via
 * TCE-form (DataHandler).
 * The function which has to be defined in the DataHandlerHookProcessor and called by the Hook-executer:
 * processDatamap_postProcessFieldArray()
 */
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['in2solrautoupdate']['in2solrautoupdateProcessDatamapClass'][] =
    'EXT:in2solrautoupdate/Classes/HookProcessor/DataHandlerHookProcessor.php:'.
    'In2code\\In2solrautoupdate\\HookProcessor\\DataHandlerHookProcessor';

