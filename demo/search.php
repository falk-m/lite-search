<?php

use falkm\search\filter\HtmlFilter;
use falkm\search\SearchEngine;
use falkm\search\stemmer\GermanStemmer;
use falkm\search\stopwords\StopWords;

//require_once('../vendor/autoload.php');
require_once('../standalone.php');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$indexFile = __DIR__ . '/store/docs.sqlite';
$options = [
    SearchEngine::OPTION_STOPWORDS =>  StopWords::getByLanguage("german"),
    SearchEngine::OPTION_FILTERS => [
        new GermanStemmer(),
        new HtmlFilter()
    ],
    SearchEngine::OPTION_ADDITIONAL_COLUMNS => [
        "date" => "INTEGER NULL",
        "topic" => "VARCHAR(20) NULL"
    ]
];
$searchEngine = new SearchEngine($indexFile, $options);

$query = $_GET["query"] ?? "test";
$fuzzy = !!($_GET["fuzzy"] ?? false);

$where = [
    //"docs.date <" . time()
];

$orderBy = [
    "score DESC",
    "docs.`date` DESC"
];

$result = $searchEngine->search($query, $fuzzy, where: $where, orderBy: $orderBy);

print_r($result);
