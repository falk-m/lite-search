<?php

namespace falkm\search;

use Exception;
use PDO;

class DocumentIndex
{

    private PDO $index;

    private int $fuzzySearchPrefixLengh = 2;

    /**
     * @var array
     */
    protected array $document_criterias = [];

    public function __construct($indexFile, array $additionalColumns = [])
    {
        $self = $this;
        if (file_exists($indexFile)) {
            $this->index = new PDO('sqlite:' . $indexFile);
        } else {

            $this->createIndex($indexFile, $additionalColumns);
        }

        $this->index->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->index->exec('PRAGMA journal_mode = MEMORY');
        $this->index->exec('PRAGMA synchronous = OFF');
        $this->index->exec('PRAGMA PAGE_SIZE = 4096');

        $this->index->sqliteCreateFunction('document_criteria', function ($funcid, $value) use ($self) {
            return $self->callCriteriaFunction($funcid, $value);
        }, 2);
    }

    /**
     * Execute registred criteria function
     *
     * @param  string $id
     * @param  ?string $document
     * @return boolean
     */
    public function callCriteriaFunction(string $id, ?string $value = null): mixed
    {
        return isset($this->document_criterias[$id]) ? $this->document_criterias[$id]($value) : false;
    }

    /**
     * Register Criteria function
     *
     * @param  mixed $criteria
     * @return mixed
     */
    public function registerCriteriaFunction(mixed $criteria): ?string
    {

        $id = \uniqid('criteria');

        if (!\is_callable($criteria)) {
            throw new Exception("createria function must be callable");
        }
        $this->document_criterias[$id] = $criteria;
        return $id;
    }

    public function createIndex($indexFile, array $additionalColumns = [])
    {
        $this->flushIndex($indexFile);

        $this->index = new PDO('sqlite:' . $indexFile);

        $columns = [
            "id" => "INTEGER PRIMARY KEY",
            "uuid" => "TEXT UNIQUE COLLATE nocase",
            "terms" => "TEXT"
        ];

        foreach ($additionalColumns as $name => $type) {
            if (array_key_exists($name, $columns)) {
                continue;
            }

            $columns[$name] = $type;
        }

        $columnsQuery = implode(", ", array_map(function ($name, $type) {
            return "`{$name}` {$type}";
        }, array_keys($columns), array_values($columns)));

        $this->index->exec("CREATE TABLE IF NOT EXISTS docs ({$columnsQuery})");

        $this->index->exec("CREATE UNIQUE INDEX 'main'.'index' ON docs ('uuid');");
    }

    public function saveDoc(string $uuid, array $terms, array $additionalRows = [])
    {
        $termsText = "|" . implode("|", $terms) . "|";

        $insertColumns = "`uuid`,`terms`";
        $paramsQuery = ":uuid,:terms";
        $updateQuery = "terms = :terms";
        $params = [
            ':uuid' => $uuid,
            ':terms' => $termsText
        ];

        foreach ($additionalRows as $name => $value) {
            $insertColumns .= ",`{$name}`";
            $paramsQuery .= ",:{$name}";
            $updateQuery .= ", `{$name}` = :{$name}";
            $params[":{$name}"] = $value;
        }

        try {
            $this->prepareAndExecuteStatement("INSERT INTO docs({$insertColumns}) VALUES ({$paramsQuery})", $params);
        } catch (\Exception $e) {
            if ($e->getCode() != 23000) {
                throw $e;
            }

            $this->prepareAndExecuteStatement("UPDATE docs SET {$updateQuery} WHERE uuid = :uuid", $params);
        }
    }

    public function deleteDoc(string $uuid)
    {
        $this->prepareAndExecuteStatement("DELETE FROM docs WHERE uuid = :uuid;", [
            ':uuid' => $uuid
        ]);
    }

    private function prepareAndExecuteStatement($query, $params = [])
    {
        $statemnt = $this->index->prepare($query);
        foreach ($params as $key => $value) {
            $statemnt->bindValue($key, $value);
        }
        $statemnt->execute();
        return $statemnt;
    }

    private function getStandardSerachCritera(array $terms)
    {
        return function (string $value) use ($terms) {
            $count = 0;
            foreach ($terms as $term) {
                if (str_contains($value, "|{$term}|")) {
                    $count++;
                }
            }

            return $count;
        };
    }

    private function getFuzzyStandardSerachCritera(array $terms, int $maxDistance)
    {
        $searchTerms = array_map(function ($term) {
            return ["prefix" => substr($term, 0, $this->fuzzySearchPrefixLengh), "term" => $term];
        }, $terms);

        return function (string $value) use ($searchTerms, $maxDistance) {
            $count = 0;
            $docTerms = explode('|', trim($value, '|'));

            foreach ($searchTerms as $searchTerm) {
                foreach ($docTerms as $docTerm) {
                    if (!str_starts_with($docTerm, $searchTerm["prefix"])) {
                        continue;
                    }


                    $distance = levenshtein($docTerm, $searchTerm["term"]);
                    if ($distance > $maxDistance) {
                        continue;
                    }

                    $value = $maxDistance - $distance;
                    $count += $value;
                    break;
                }
            }

            return $count;
        };
    }

    public function getDocumentUuidsByTerms(array $terms, array $where = [], array $orderBy = [], int $totalLimit = 1000, int|bool $fuzzy = false)
    {
        $criteriaField = "";
        if (empty($terms)) {
            $criteriaField = "1";
        } elseif ($fuzzy) {
            $fuzzyDistance = is_bool($fuzzy) ? 2 : $fuzzy;
            $criteriaId = $this->registerCriteriaFunction($this->getFuzzyStandardSerachCritera($terms, $fuzzyDistance));
            $criteriaField = "document_criteria('{$criteriaId}', terms)";
        } else {
            $criteriaId = $this->registerCriteriaFunction($this->getStandardSerachCritera($terms));
            $criteriaField = "document_criteria('{$criteriaId}', terms)";
        }

        $sql = "SELECT uuid, {$criteriaField} AS score 
                FROM docs";

        $where[] = "score > 0";
        $sql .= " WHERE " . implode(" AND ", $where);

        if (empty($orderBy)) {
            $orderBy = ["score DESC"];
        }

        $sql .= " ORDER BY " . implode(", ", $orderBy);

        $sql .= " LIMIT {$totalLimit}";

        $statemnt = $this->index->prepare($sql);
        $statemnt->execute();

        $result = $statemnt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    public function flushIndex($indexFile)
    {
        if (file_exists($indexFile)) {
            unlink($indexFile);
        }
    }
}
