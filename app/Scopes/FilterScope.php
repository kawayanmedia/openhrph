<?php

namespace App\Scopes;

trait FilterScope
{
    public function scopeFilter($query, array $filters, $columns = null, $otherFilter = null)
    {
        $query->when($filters['search'] ?? null, function ($query, $search) use ($columns) {
            if ($columns != null) {
                foreach ($columns as $column) {
                    $query->orWhere($column, 'like', '%'.$search.'%');
                }
            }
        })->when($filters['trashed'] ?? null, function ($query, $trashed) {
            if ($trashed === 'with') {
                $query->withTrashed();
            } elseif ($trashed === 'only') {
                $query->onlyTrashed();
            }
        });

        // If have values for filter
        if ($otherFilter) {
            foreach ($otherFilter as $filterColum) {
                if (request($filterColum)) {
                    $query->orWhere($filterColum, 'like', '%'.request($filterColum).'%');
                }
            }
        }
    }

    public function scopeFilterOrder($query, $order = null, $direction = 'asc')
    {
        $query->when($order, function ($query) use ($order, $direction) {
            $query->orderBy($order, $direction);
        });
    }
}
