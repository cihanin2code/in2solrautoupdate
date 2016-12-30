<?php

########################################################################
# Extension Manager/Repository config file for ext "in2plugins".
########################################################################

$EM_CONF[$_EXTKEY] = array(
    'title' => 'in2solrautoupdate',
    'description' => 'This extension does monitoring and automatic update of the solr-index based on any kind '.
        'table or record',
    'category' => 'misc',
    'author' => 'in2code GmbH',
    'author_email' => 'cihan@in2code.de',
    'state' => 'stable',
    'author_company' => 'in2code GmbH',
    'version' => '1.0.6',
    'constraints' => array(
        'depends' => array(
            'cms' => '6.0.0-7.99.99',
            'typo3' => '6.0.0-7.99.99',
            'solr' => '>=3.1.0-dev'
        ),
        'conflicts' => array(),
        'suggests' => array(),
    ),
);
