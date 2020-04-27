<?php

// Global debug function
function dd($val, $json = false) {
    if ($json) {
        die(json_encode($val, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    } else {
        print_r($val); die("\n");
    }
}

require_once __DIR__.'/vendor/autoload.php';

use \SkGovernmentParser\DataSources\FinancialAgentRegister\FinancialAgentRegisterQuery;

# ~

const CIKES_NUMBER = '235741';
const FINGO_NUMBER = '215683';
const BITTARA_NUMBER = '235784';

# ~

$queryResult = FinancialAgentRegisterQuery::network()->byNumber(BITTARA_NUMBER);
echo(json_encode($queryResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
