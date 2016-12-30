<?php
namespace ApacheSolrForTypo3\Solr\IndexQueue\Initializer;

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
use In2code\In2solrautoupdate\IndexQueue\Initializer\Basic;


/**
 * Class Record
 *
 * @package In2code\In2solrautoupdate\IndexQueue\Initializer
 */
class Record extends Basic
{
    /**
     * Returns the table-alias-name for adding the WHERE-condition.
     *
     * @return string
     */
    public function getTableAlias()
    {
        return $this->type;
    }
}

