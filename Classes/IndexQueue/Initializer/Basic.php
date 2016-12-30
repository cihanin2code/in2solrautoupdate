<?php
namespace In2code\In2solrautoupdate\IndexQueue\Initializer;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * (c) 2016 Cihan Yesilöz <cihan@in2code.de>, in2code GmbH
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
use ApacheSolrForTypo3\Solr\IndexQueue\Initializer\AbstractInitializer;


/**
 * Class Basic
 *
 * @package In2code\In2solrautoupdate\IndexQueue\Initializer
 */
abstract class Basic extends AbstractInitializer
{
    /**
     * Returns the table-alias-name for adding the WHERE-condition.
     *
     * @return string
     */
    abstract public function getTableAlias();
}

