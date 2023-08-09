PHP Lite Search Engine
======

[![Author](https://img.shields.io/badge/author-falkm-blue.svg?style=flat-square)](https://falk-m.de)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

Lite search is a php full text seaŕach engine. It supports stemming, filter (e.g. remove html tags), stoppwords (remove ofen used words) and fuzzy search results. It needs no dependencys and use SqLite.

The engine is inspired by [TNT Search](https://github.com/teamtnt/tntsearch). The diffrent to TNT Search is that this search engine do not use a seperate term index. So the index process is very fast. Also you can add own columns to the document index. E.g. you can prefiltering you results by data, topics or someting else.

We use the MongoLite approach, similar to [cockpit](https://github.com/Cockpit-HQ/Cockpit/). This approach use a special function from SqLite, to registrate a PHP callable to use them in the data query. 

You find a example in the "demo" directory in the repository.

## Installation

### over composer

The engine is available via Composer. ```composer require falkm/lite-search```

```json
{
    "require": {
        "falkm/lite-search": "1.*"
    }
}
```

### standalone

You can also download the repository and use the standalone outoloader
 ```php
 <?php
 require_once('./standalone.php');
 ```

## Configuration

Default you need only to set the path where the engine cretae the sqlite file.

```php
use falkm\search\SearchEngine;

$indexFile = "./index.sqlite";
$searchEngine = new SearchEngine($indexFile);
```

### options
A second parameter you can set optioal values
```php
use falkm\search\SearchEngine;

$indexFile = "./index.sqlite";
$options = [ /* options */];
$searchEngine = new SearchEngine($indexFile, $options);
```
A fully example for a german search index:
```php
use falkm\search\SearchEngine;
use falkm\search\stopwords\StopWords;

$indexFile = "./index.sqlite";
$options = [  
    SearchEngine::OPTION_TOKENIZER => new falkm\search\tokenizer\StandardTokenizer();
    SearchEngine::OPTION_STOPWORDS =>  StopWords::getByLanguage("german"),
    SearchEngine::OPTION_FILTERS => [
        new falkm\search\stemmer\GermanStemmer(),
        new falkm\search\filter\HtmlFilter()
    ],
    SearchEngine::OPTION_ADDITIONAL_COLUMNS => [
        "date" => "INTEGER NULL",
        "topic" => "VARCHAR(20) NULL"
    ]];

$searchEngine = new SearchEngine($indexFile, $options);
```

available options

key | default | discrition
-- | -- | --
OPTION_TOKENIZER | StandardTokenizer | must implemensts the interface ```falkm\search\tokenizer\Tokenicer```. THe toknizer spit the text in token, e.g. words
OPTION_STOPWORDS | [] | a array of words. for examples often used words lite "and", "are", ... A facory provides a list of typicly stopwords by the language 'german'. ```StopWords::getByLanguage("german")```
OPTION_FILTERS | [] | A array of filter to transform the terms. Must be implement the interface ```falkm\search\filter\Filter```
OPTION_ADDITIONAL_COLUMNS | [] | Array of additional culums for data filtering. the key is the name of the column and the value must be the sqlite datatype (Integer, Text, ...)


## Add documents

```php
$searchEngine->addDocument("DOC1", "This is a example text");
```

The first parameter is the unique id of the document. If the id already exists in the index, then the document is replaced with the new one.

The secount parameter is the text for the search. If your document have difftrent fields, like title, body, ... then concat then to one string (seperated by whitespace)

The third option parameters a values for additional data columns.
In the fully installation example we add additional data columns and can add them seperatly to the index. This data are useful for filtering and are not in the text serach.

```php
$searchEngine->addDocument("DOC2", "This is a ohter text", [
    "date" => time(),
    "topic" => "literature"
]);
```

## Remove documents

Remove docuemts by the unique identifire.
```php
$searchEngine->removeDocument("DOC1");
```

## Search

```php
$query = "eample text";
$result = $searchEngine->search($query);
print_r($result);
```

result
```php
Array
(
    [0] => Array
        (
            [uuid] => DOC1
            [score] => 2
        )

    [1] => Array
        (
            [uuid] => DOC2
            [score] => 1
        )
)
```

The engine find DOC1 becouse it includes wht words "example" and "text". It also find DOC2 becouse it includs also the word "text".

### fuzzy search
```php
$query = "eamble";
$result = $searchEngine->search($query, true);
print_r($result);
```

result
```php
Array
(
    [0] => Array
        (
            [uuid] => DOC1
            [score] => 2
        )
)
```

THe fuzzy search to the serch query "examble" find the document wich include the term "example". The fuzzy search find docs which include terms by a [levenshtein distance](https://en.wikipedia.org/wiki/Levenshtein_distance) smaller or equal 2.

you can also set the distance as parameter
```php
$query = "eamble";
$result = $searchEngine->search($query, fuzzy: 2);
print_r($result);
```

### total limit

The engine returns every docuement whitch is suitable the the query.
The default limit of resonse is 1000 docuement ids. you can also set manually the limit.

```php
$query = "eamble";
$result = $searchEngine->search($query, fuzzy: true, totalLimit: 100);
print_r($result);
```



### use additional columns in the search

```php

$where = [
    "docs.topic ='literature'"
];

$orderBy = [
    "score DESC",
    "docs.`date` DESC"
];

$query = "eamble";
$result = $searchEngine->search($query, fuzzy: true, totalLimit: 100, where: $where, orderBy: $orderBy);
print_r($result);
```

**order by:** the default value is ```["score DESC"]```. the score is the comuted value addicted by the search query. You can also use your additional columsn to sort the result

**where:** you can add additional conditionals to the serach. in the example we filter by the additional data column 'topic'.
