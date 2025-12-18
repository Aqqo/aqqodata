<?php

namespace Aqqo\OData\Traits;

use Aqqo\OData\Services\FilterParser;
use Aqqo\OData\Services\FilterExecutor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @template TModelClass of Model
 * @template TRelatedModel of Model
 */
trait FilterTrait
{
    /**
     * @return void
     * @throws \ReflectionException
     */
    public function addFilters(): void
    {
        $filter = $this->request?->input('$filter');

        if ($filter === null || trim((string) $filter) === '') {
            preg_match('/\(([^)]+)\)/', $this->request?->url() ?? '', $matches);
            if (!empty($matches[1])) {
                $filter = "{$this->subject->getModel()->getKeyName()} eq '{$matches[1]}'";
            } else {
                return;
            }
        }

        // When $apply is used, $filter applies to the transformed result => HAVING.
        $statement = property_exists($this, 'hasApply') && $this->hasApply ? 'having' : 'where';
        $this->appendFilterQuery(strval($filter), $this->subject, $statement);
    }

    /**
     * Append filter queries to the builder based on the OData filter string.
     *
     * @param string $filter
     * @param Builder<TModelClass> $builder
     * @param string $statement
     * @return void
     * @throws \ReflectionException
     */
    public function appendFilterQuery(string $filter, Builder $builder, string $statement = 'where'): void
    {
        // Preprocess some OData v4 expression forms that aren't natively parsed yet.
        // This is a stepping stone towards full expression support.
        $filter = $this->preprocessFilter($filter);

        // parse into AST
        $parser   = new FilterParser();
        $ast      = $parser->parse($filter);
        // pass $this (the Query) so we can resolve attribute‐based source mappings
        (new FilterExecutor($this, $builder))->execute($ast, $statement);
    }

    private function preprocessFilter(string $filter): string
    {
        $out = $filter;

        // guid'...'  -> '...'
        $out = preg_replace("/\\bguid'([0-9a-fA-F-]{36})'/", "'$1'", $out) ?? $out;

        // cast(X,Edm.Int64) -> X  (enough for our sqlite/integer test tables)
        $out = preg_replace("/\\bcast\\(\\s*([^,]+?)\\s*,\\s*Edm\\.[^)]+\\)/i", "$1", $out) ?? $out;

        // now() add duration'P<n>D' -> '<now + n days>'
        if (preg_match("/\\bnow\\(\\)\\s+add\\s+duration'P(\\d+)D'/i", $out, $m)) {
            $days = (int) $m[1];
            $future = Carbon::now()->addDays($days)->toDateTimeString();
            $out = preg_replace("/\\bnow\\(\\)\\s+add\\s+duration'P" . $days . "D'/i", "'" . $future . "'", $out) ?? $out;
        }

        // <Field> add duration'P<n>D' lt now()  -> <Field> lt '<now - n days>'
        if (preg_match("/\\b([A-Za-z_][A-Za-z0-9_]*)\\s+add\\s+duration'P(\\d+)D'\\s+lt\\s+now\\(\\)/i", $out, $m)) {
            $field = $m[1];
            $days = (int) $m[2];
            $cutoff = Carbon::now()->subDays($days)->toDateTimeString();
            $out = preg_replace(
                "/\\b" . preg_quote($field, '/') . "\\s+add\\s+duration'P" . $days . "D'\\s+lt\\s+now\\(\\)/i",
                $field . " lt '" . $cutoff . "'",
                $out
            ) ?? $out;
        }

        // <Field> ge now() sub duration'P<n>D' -> <Field> ge '<now - n days>'
        if (preg_match("/\\b([A-Za-z_][A-Za-z0-9_]*)\\s+ge\\s+now\\(\\)\\s+sub\\s+duration'P(\\d+)D'/i", $out, $m)) {
            $field = $m[1];
            $days = (int) $m[2];
            $cutoff = Carbon::now()->subDays($days)->toDateTimeString();
            $out = preg_replace(
                "/\\b" . preg_quote($field, '/') . "\\s+ge\\s+now\\(\\)\\s+sub\\s+duration'P" . $days . "D'/i",
                $field . " ge '" . $cutoff . "'",
                $out
            ) ?? $out;
        }

        // <Field> gt duration'PT<n>M' -> <Field> gt <n>
        $out = preg_replace("/duration'PT(\\d+)M'/i", "$1", $out) ?? $out;
        // duration'PT<n>H' -> <n*60>
        $out = preg_replace_callback("/duration'PT(\\d+)H'/i", function ($m) {
            return (string) (((int) $m[1]) * 60);
        }, $out) ?? $out;

        return $out;
    }
}
