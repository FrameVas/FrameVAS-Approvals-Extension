<?php

namespace Espo\Modules\Approvals\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\Approvals\Tools\Approves\ResolveService;

/**
 * POST /api/v1/Approves/{entityType}/{id}/{field}/resolve
 * Body: {"action": "approve" | "decline", "comment": "optional text"}
 */
class PostResolve implements Action
{
    public function __construct(
        private ResolveService $service,
    ) {}

    public function process(Request $request): Response
    {
        $entityType = $request->getRouteParam('entityType');
        $id = $request->getRouteParam('id');
        $field = $request->getRouteParam('field');

        if (!$entityType || !$id || !$field) {
            throw new BadRequest();
        }

        $body = $request->getParsedBody();

        $action = $body->action ?? null;
        $comment = $body->comment ?? null;

        if (!is_string($action)) {
            throw new BadRequest("No action.");
        }

        if ($comment !== null && !is_string($comment)) {
            throw new BadRequest("Bad comment.");
        }

        $result = $this->service->resolve($entityType, $id, $field, $action, $comment);

        return ResponseComposer::json($result);
    }
}
