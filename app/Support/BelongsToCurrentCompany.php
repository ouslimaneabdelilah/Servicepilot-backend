<?php
namespace App\Support;
use Illuminate\Database\Eloquent\Builder;
trait BelongsToCurrentCompany {
    protected static function bootBelongsToCurrentCompany(): void {
        static::addGlobalScope('company', function (Builder $query) {
            if (app()->bound('currentCompanyId')) $query->where($query->getModel()->qualifyColumn('company_id'), app('currentCompanyId'));
        });
        static::creating(function ($model) {
            if (!$model->company_id && app()->bound('currentCompanyId')) $model->company_id = app('currentCompanyId');
        });
    }
}
