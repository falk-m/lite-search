<?php

namespace falkm\search\tokenizer;

interface Tokenizer
{
    public function tokenize($text, $stopwords);
}
