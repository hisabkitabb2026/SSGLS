<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponseTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Collection;

/**
 * Base controller for all application controllers.
 *
 * Provides shared traits and helper methods for:
 *  - API response formatting (ApiResponseTrait)
 *  - Authorization (AuthorizesRequests)
 *  - Bulk delete operations (deleteBulk)
 */
abstract class Controller extends BaseController
{
    use ApiResponseTrait;
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;

    /**
     * Helper method to delete multiple records via a service or direct model deletion.
     *
     * Consolidates the common pattern across all bulk-delete endpoints:
     *
     *  1. Fetch IDs from request and scope to company
     *  2. Call service.delete(ids) if service provided, else Model::destroy(ids)
     *  3. Return standardized success response
     *
     * Usage in controller:
     *  public function delete(DeleteItemsRequest $request)
     *  {
     *      $this->authorize('delete multiple items');
     *      return $this->deleteBulk(Item::class, $request->ids, $this->itemService);
     *  }
     *
     * @param  string  $modelClass  Fully-qualified model class name
     * @param  array  $ids  IDs to delete
     * @param  object|null  $service  Optional service with delete(Collection) method
     * @return JsonResponse
     */
    protected function deleteBulk(
        string $modelClass,
        array $ids,
        ?object $service = null
    ) {
        $idsToDelete = $modelClass::whereCompany()
            ->whereIn('id', $ids)
            ->pluck('id');

        if ($service && method_exists($service, 'delete')) {
            $service->delete($idsToDelete);
        } else {
            $modelClass::destroy($idsToDelete);
        }

        return $this->successResponse();
    }
}
