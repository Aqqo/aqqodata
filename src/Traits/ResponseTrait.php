<?php

namespace Aqqo\OData\Traits;

trait ResponseTrait
{
    /**
     * @return array<string, array<int, array<int, string>>|string>
     */
    public function getResponse(): array
    {
        $query = clone $this->subject;
        $query->getQuery()->offset = null;
        $query->getQuery()->limit = null;
        $all_records_count = $query->count();

        $value = $this->get();
        $count = $value->count();
        if (property_exists($model = $this->subject->getModel(), 'resource')) {
            $value = $model->resource::collection($value);
        }

        $response = [
            "@context" => $_SERVER['HTTP_HOST'] ?? "" . 'api/$metadata#' . $this->subject->getModel()->getTable(), // TODO decide whether we want to support $metadata endpoint
            "value" => $value
        ];

        if ($this->add_count) {
            $response['@count'] = $all_records_count;
        }

        if ($all_records_count > ($count + ($this->subject->getQuery()->offset ?? 0))) {
            $uri = $_SERVER['REQUEST_URI'] ?? '';

            if ($this->subject->getQuery()->offset === 0) {
                $skip = $this->subject->getQuery()->limit;
                $response['@nextLink'] = ($_SERVER['HTTP_HOST'] ?? rtrim((string)env('APP_URL'), '/') . '/') . $uri . (str_contains($uri, '?') ? '&' : '?') . "\$skip={$skip}";
            } else {
                $skip = $this->subject->getQuery()->offset + $this->subject->getQuery()->limit;
                $uri = str_replace('$skip=' . $this->subject->getQuery()->offset, "\$skip={$skip}", $uri);
                $response['@nextLink'] = ($_SERVER['HTTP_HOST'] ?? rtrim((string)env('APP_URL'), '/') . '/' ) . $uri;
            }
        }

        return $response;
    }
}
