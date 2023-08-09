<?php

namespace falkm\search\tokenizer;


class StandardTokenizer implements Tokenizer
{
    private const PATTERN = '/[^\p{L}\p{N}\p{Pc}\p{Pd}@]+/u';

    public function tokenize($text, $stopwords = [])
    {
        $text  = mb_strtolower($text);
        $split = preg_split(self::PATTERN, $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_diff($split, $stopwords);
    }
}
