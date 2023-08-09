<?php

use falkm\search\filter\HtmlFilter;
use falkm\search\SearchEngine;
use falkm\search\stemmer\GermanStemmer;
use falkm\search\stopwords\StopWords;

require_once('../vendor/autoload.php');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(300);

$start = time();
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


$searchEngine->addDocument("h1", "Dies ist ein Test", ["date" => time()]);
$searchEngine->addDocument("h2", "Mein Name ist Falk Müller, Autobahn", ["date" => time(), "topic" => "test"]);

$text = "";
$idx = 0;
$files = scandir(__DIR__ . "/test_data");
foreach ($files as $fileName) {
    if (!str_contains($fileName, ".txt")) {
        continue;
    }

    if ($file = fopen(__DIR__ . "/test_data/{$fileName}", "r")) {
        while (!feof($file)) {
            $line = fgets($file);
            if (strlen($text) < 1000) {
                $text .= " " . $line;
                continue;
            }

            $idx++;
            $searchEngine->addDocument("t{$idx}", $text);
            $text = "";
        }
        fclose($file);
    } else {
        echo "can not open /test_data/text.txt";
    }
}


$end = time();

$diff = $end - $start;

echo "execution time: {$diff} secounds";
