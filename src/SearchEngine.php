<?php

namespace falkm\search;

use falkm\search\filter\Filter;
use falkm\search\tokenizer\StandardTokenizer;
use falkm\search\tokenizer\Tokenizer;

class SearchEngine
{
    public const OPTION_TOKENIZER = "OPTION_TOKENIZER";
    public const OPTION_STOPWORDS = "OPTION_STOPWORDS";
    public const OPTION_TEXT_FILTERS = "OPTION_TEXT_FILTERS";
    public const OPTION_TERM_FILTERS = "OPTION_TERM_FILTERS";
    public const OPTION_ADDITIONAL_COLUMNS = "OPTION_ADDITIONAL_COLUMNS";


    private DocumentIndex $documentIndex;
    private Tokenizer $tokenizer;
    /** @var Filter[] */
    private array $termfilter;
    /** @var Filter[] */
    private array $textFilter;
    /** @var string[] */
    private array $stopWords;

    public function __construct(string $file, array $options = [])
    {
        $additionalColumns = $options[self::OPTION_ADDITIONAL_COLUMNS] ?? [];
        $this->documentIndex = new DocumentIndex($file, $additionalColumns);

        $this->tokenizer = $options[self::OPTION_TOKENIZER] ?? new StandardTokenizer();
        $this->stopWords = $options[self::OPTION_STOPWORDS] ?? [];
        $this->termfilter = $options[self::OPTION_TERM_FILTERS] ?? [];
        $this->textFilter = $options[self::OPTION_TEXT_FILTERS] ?? [];
    }

    public function addDocument(string $uuid, string $text, array $additionalRows = [])
    {
        $terms = $this->getTerms($text);
        $terms = array_unique($terms);
        $this->documentIndex->saveDoc($uuid, $terms, $additionalRows);
    }

    public function removeDocument(string $uuid)
    {
        $this->documentIndex->deleteDoc($uuid);
    }

    public function getTerms($text)
    {
        $terms = [];

        foreach ($this->textFilter as $filter) {
            $text = $filter->filter($text);
        }

        $tokens = $this->tokenizer->tokenize($text, $this->stopWords);
        foreach ($tokens as $term) {
            foreach ($this->termfilter as $filter) {
                $term = $filter->filter($term);
            }

            if (!empty($term)) {
                $terms[] = $term;
            }
        }

        return  $terms;
    }

    public function search(string $query, int|bool $fuzzy = false, int $totalLimit = 1000, array $where = [], array $orderBy = [])
    {
        $terms = $this->getTerms($query);
        return $this->documentIndex->getDocumentUuidsByTerms($terms, $where, $orderBy, $totalLimit, $fuzzy);
    }
}
