<?php

namespace App\Repositories;

use Webkul\Core\Eloquent\Repository;

/**
 * TenantAwareRepository
 * 
 * Base repository class that automatically applies tenant scoping to all queries.
 * All tenant-specific repositories should extend this class instead of the base Repository.
 */
abstract class TenantAwareRepository extends Repository
{
    /**
     * Apply tenant scope before query execution.
     *
     * @return void
     */
    protected function applyTenantScope(): void
    {
        if ($this->shouldApplyTenantScope()) {
            $this->scopeQuery(function ($query) {
                if ($tenantId = $this->getCurrentTenantId()) {
                    return $query->where($this->model->getTable() . '.tenant_id', $tenantId);
                }
                return $query;
            });
        }
    }

    /**
     * Check if tenant scope should be applied.
     *
     * @return bool
     */
    protected function shouldApplyTenantScope(): bool
    {
        // Don't apply scope if we're in super admin context
        if (app()->bound('super-admin-context')) {
            return false;
        }

        // Check if the model uses BelongsToTenant trait
        if (method_exists($this->model, 'bootBelongsToTenant')) {
            return true;
        }

        return false;
    }

    /**
     * Get current tenant ID.
     *
     * @return string|null
     */
    protected function getCurrentTenantId(): ?string
    {
        if (app()->bound('tenant')) {
            return app('tenant')->id;
        }

        return null;
    }

    /**
     * Find data by id with tenant scope.
     *
     * @param       $id
     * @param array $columns
     *
     * @return mixed
     */
    public function find($id, $columns = ['*'])
    {
        $this->applyCriteria();
        $this->applyScope();
        $this->applyTenantScope();
        
        $model = $this->model->find($id, $columns);
        $this->resetModel();

        return $this->parserResult($model);
    }

    /**
     * Find data by field and value with tenant scope.
     *
     * @param       $field
     * @param       $value
     * @param array $columns
     *
     * @return mixed
     */
    public function findByField($field, $value = null, $columns = ['*'])
    {
        $this->applyCriteria();
        $this->applyScope();
        $this->applyTenantScope();
        
        $model = $this->model->where($field, '=', $value)->get($columns);
        $this->resetModel();

        return $this->parserResult($model);
    }

    /**
     * Find data by multiple fields with tenant scope.
     *
     * @param array $where
     * @param array $columns
     *
     * @return mixed
     */
    public function findWhere(array $where, $columns = ['*'])
    {
        $this->applyCriteria();
        $this->applyScope();
        $this->applyTenantScope();

        $this->applyConditions($where);

        $model = $this->model->get($columns);
        $this->resetModel();

        return $this->parserResult($model);
    }

    /**
     * Retrieve all data of repository with tenant scope.
     *
     * @param array $columns
     *
     * @return mixed
     */
    public function all($columns = ['*'])
    {
        $this->applyCriteria();
        $this->applyScope();
        $this->applyTenantScope();

        if ($this->model instanceof Builder) {
            $results = $this->model->get($columns);
        } else {
            $results = $this->model->all($columns);
        }

        $this->resetModel();
        $this->resetScope();

        return $this->parserResult($results);
    }

    /**
     * Retrieve all data of repository, paginated with tenant scope.
     *
     * @param null  $limit
     * @param array $columns
     * @param string $method
     *
     * @return mixed
     */
    public function paginate($limit = null, $columns = ['*'], $method = "paginate")
    {
        $this->applyCriteria();
        $this->applyScope();
        $this->applyTenantScope();
        
        $limit = is_null($limit) ? config('repository.pagination.limit', 15) : $limit;
        $results = $this->model->{$method}($limit, $columns);
        $results->appends(app('request')->query());
        $this->resetModel();

        return $this->parserResult($results);
    }

    /**
     * Count results of repository with tenant scope.
     *
     * @param array  $where
     * @param string $columns
     *
     * @return int
     */
    public function count(array $where = [], $columns = '*')
    {
        $this->applyCriteria();
        $this->applyScope();
        $this->applyTenantScope();

        if ($where) {
            $this->applyConditions($where);
        }

        $result = $this->model->count($columns);
        $this->resetModel();
        $this->resetScope();

        return $result;
    }

    /**
     * Create new model with tenant_id.
     *
     * @param array $attributes
     *
     * @return mixed
     */
    public function create(array $attributes)
    {
        // Add tenant_id if not already set
        if ($this->shouldApplyTenantScope() && !isset($attributes['tenant_id'])) {
            $attributes['tenant_id'] = $this->getCurrentTenantId();
        }

        return parent::create($attributes);
    }

    /**
     * Update a entity by id with tenant scope check.
     *
     * @param array $attributes
     * @param       $id
     *
     * @return mixed
     */
    public function update(array $attributes, $id)
    {
        $this->applyScope();
        $this->applyTenantScope();

        $model = $this->model->findOrFail($id);
        $model->fill($attributes);
        $model->save();

        $this->resetModel();

        return $this->parserResult($model);
    }

    /**
     * Delete a entity by id with tenant scope check.
     *
     * @param $id
     *
     * @return int
     */
    public function delete($id)
    {
        $this->applyScope();
        $this->applyTenantScope();

        $model = $this->find($id);
        
        if (!$model) {
            return 0;
        }

        $originalModel = clone $model;

        $this->resetModel();

        $deleted = $model->delete();

        return $this->parserResult($deleted);
    }

    /**
     * Delete multiple entities by given criteria with tenant scope.
     *
     * @param array $where
     *
     * @return int
     */
    public function deleteWhere(array $where)
    {
        $this->applyScope();
        $this->applyTenantScope();

        $this->applyConditions($where);

        $deleted = $this->model->delete();

        $this->resetModel();

        return $this->parserResult($deleted);
    }
}