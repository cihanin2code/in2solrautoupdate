## TYPO3 Extension in2solrautoupdate

### What this extension does
* The default **RecordMonitor** of **ext-solr** only recognizes changes/creation of records in case it is done via DataHandler in the TYPO3-Backend.
* As soon as you use a **custom-extension**, which does operations like
	* create/update/delete a record

	without using the **DataHandler** the change is not recognized by the
ext-solr's RecordMonitor.
This could be the case when e.g.:

	* importing the record
	* doing direct database-operations with e.g. exec_UPDATEquery()

* **In2solrautoupdate** monitors and updates the solr-Queue and solr-Index the way you want it to.
* You can configure the desired behaviour dynamically via TypoScript. You can define:
	* Which **table** shall be monitored?
	* Which **table-field** shall be monitored?
	* Which **data-operation** shall be monitored? 
	(create/update/delete)
	* Whether it shall update the solr-Queue only or 
	**solr-Queue** + **solr-Index**
* If there is a "additionalWhereClause" or custom Initializer defined for the monitored type (table) --> It is used for updating the item.
	
### Custom Initializers
* If you want to use your own custom-solr-Initializer you have to extend "**In2code\In2solrautoupdate\IndexQueue\Initializer\Basic**" and implement "function **getTableAlias()**" which returns the table-alias (e.g. sys\_file\_metadata or sfm ..). That's used by in2solrautoupdate for attaching the condition "**uid=$itemUid**" so that the Initializer doesn't update all available items, but only the one single item, which has been created/updated/deleted.


### TypoScript configuration

* **General**:  
	You can find a detailed example-TypoScript-definition in the in2solrautoupdate-TypoScript with detailed description of every single option.

* **tableName**:  
	The name of the table to monitor. Table-specific TypoScript-configuration:

	* **rootPageId**:  
		(Default = $record['pid'])  
		The rootpage used for updating the solr-Queue + Index.

	* **configurationName**:  
		TypoScript configuration-name.
		
	* **automaticAdd/automaticUpdate/automaticDelete**:  
		The operation to monitor. You can use any of these 3 at the same time.

	* **fieldsToCheck**:  
		Which fields of this table shall be monitored. The fields-Ts have to be of this form:  
		fieldname = 1
		
	* **fieldName**:  
	Field-specific TypoScript-configuration:
	
		* **automaticUpdate/automaticDelete**:  
		There is no "automaticAdd"-option on field-base as it makes no sense. Only rows are created and not 
		fields.  
		
			Operation-specific configurations:
		
			* **target**:  
			**q**:  
			Only the TYPO3-Index-Queue is updated.  
			**i**:  
			The TYPO3-Index-Queue and solr-Index-Queue are immediately updated.
					
### What's special about "target = q" and "target = i"
* The option '**i**' is for **critical operations** which require an **immediate update** of the solr-index. To mention a daily usecase, which we were confrontend with:
	* We have a image-database, where only images from solr-index are shown in frontend, for which the 
	currently logged in frontend-user has rights for.
	* As soon as the access-rights are changed in the TYPO3-Backend-settings for a specific image we are immediately updating the solr-index. Otherwise the currently logged in frontend-user could still see the image, he isn't authorized for anymore. But consider, that this slows down Backend user's operation a little bit.
* The option '**q**' is for **non-critical operations** which don't require immediate update and can wait for the next run of the solr-Indexing-Scheduler-Task. This could be e. g. an update of the title of an image.
* **To summarize**:
	* You use "i" for very few critical operations for immediate solr-index-update.
	* You use "q" for non-critical operations. Updating of the solr-index is done in the next solr-Indexing-Scheduler-Task's run.

### Usage of Hooks by your custom extension ###
* **Attention**:  
The HookProcessors are only executed if you have propely enabled monitoring and autoupdate by in2solrautoupdate for the specific table or table-field.

* **Available Hooks in in2solrautoupdate**:  
	* **$GLOBALS['TYPO3\_CONF\_VARS']['SC\_OPTIONS']['t3lib/class.t3lib\_tcemain.php']['processCmdmapClass']**:  
	Our Hook-processor called after a TCE-form(DataHandler)-operation like move or delete has been performed.
	
	* **$GLOBALS['TYPO3\_CONF\_VARS']['SC\_OPTIONS']['in2solrautoupdate']['in2solrautoupdateProcessCmdmapClass']**:  
	This Hook-processor can be executed by any third-party-extension in case the operation (like new, move or delete) is not done via TCE-form (DataHandler).
	
	* **$GLOBALS['TYPO3\_CONF\_VARS']['SC\_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']**:  
	Our Hook-processor called after an update of fields via TCE-form (DataHandler) has been done.
	
	* **$GLOBALS['TYPO3\_CONF\_VARS']['SC\_OPTIONS']['in2solrautoupdate']['in2solrautoupdateProcessDatamapClass']**:  
	This Hook-processor can be executed by any third-party-extension in case the field-update is not done via
	TCE-form (DataHandler).

### FAQ
1. **Why isn't it possible to do update on solr-Index-only?**

	**Answer**:

	- TYPO3-solr-Index-Queue and solr-Index have to be synchronous.
	- It's not possible, that you only update the solr-Index, but not the TYPO3-solr-Index-Queue. 
	That's not the way the whole TYPO3<->solr-mechanism is intended to work.
	- It's also not possible that you remove a record from solr-Index-only but keep the index-Queue-Item on 
	TYPO3-side. The Index-Queue would then still tell us that the record has been indexed on timestamp A, which wouldn't be true, as we would have already removed the associated solr-Index-<doc>.
	- For this reason it only makes sense to either update TYPO3-solr-Index-Queue-only or TYPO3-solr-Index-Queue + solr-Index at the same time.
