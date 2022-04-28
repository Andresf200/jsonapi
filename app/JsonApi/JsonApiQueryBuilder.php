<?php

namespace App\JsonApi;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;

class JsonApiQueryBuilder
{
    public function allowedSorts(): \Closure
    {
        return function ($allowedStorts) {
            /** @var Builder $this**/
            if (request()->filled('sort')) {
                $sortFields = explode(',', request()->input('sort'));

                foreach ($sortFields as $sortField) {
                    $sortDirection = Str::of($sortField)->startsWith('-') ? 'desc' : 'asc';

                    $sortField = ltrim($sortField, '-');

                    abort_unless(in_array($sortField, $allowedStorts), 400);
                    $this->orderBy($sortField, $sortDirection);
                }
            }

            return $this;
        };
    }

    public function allowedFilters(): \Closure
    {
        return function($allowedFilters){
            foreach (request('filter', []) as $filter => $value) {
                abort_unless(in_array($filter, $allowedFilters), 400);
                $this->hasNamedScope($filter)
                    ? $this->{$filter}($value)
                    : $this
                    ->where($filter, 'LIKE', '%' . $value . '%');
            }
            return $this;
        };
    }

    public function sparseFieldset(): \Closure
    {
        return function() {
            /** @var Builder $this */
            if (request()->isNotFilled('fields')) {
                return $this;
            }

            $fields = explode(',', request('fields.'.$this->getResourceType()));

            $routeKeyName = $this->model->getRouteKeyName();
            if(! in_array($routeKeyName, $fields)) {
                $fields[] = 'slug';
            }

            return $this->addSelect($fields);
        };
    }

    public function jsonPaginate(): \Closure
    {
       return function(){
            return $this->paginate(
            /** @var Builder $this**/
                $perpage = request('page.size',15),
                $columns = ['*'],
                $pagesName = 'page[number]',
                $page = request('page.number',1)
            )->appends(request()->only('sort','filter','page.size'));
        };
    }

    public function getResourceType()
    {
       return   function(){
           /** @var Builder $this*/
           if(property_exists($this->model,'resourceType')){
               return $this->model->resourceType;
           }
           return $this->model->getTable();
       };
    }
}
