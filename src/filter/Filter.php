<?php

namespace falkm\search\filter;

interface Filter
{
    public function filter(string $word);
}
