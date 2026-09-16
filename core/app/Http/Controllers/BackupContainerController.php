<?php

namespace App\Http\Controllers;

use App\Http\Requests\BackupContainerStoreRequest;
use App\Http\Requests\BackupContainerUpdateRequest;
use App\Http\Resources\BackupContainerCollection;
use App\Http\Resources\BackupContainerResource;
use App\Jobs\DeleteBackupContainer;
use App\Models\BackupContainer;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Throwable;

class BackupContainerController extends Controller
{

    #[OA\Get(
        path: '/backup-containers',
        summary: 'List backup containers',
        security: [['bearerAuth' => []]],
        tags: ['Backup Containers'],
        responses: [
            new OA\Response(response: 200, description: 'List of backup containers', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/BackupContainer'))],
            )),
        ],
    )]
    public function index(Request $request): BackupContainerCollection
    {
        /** @var Collection<array-key, BackupContainer> $containers */
        $containers = BackupContainer::query()
            ->orderBy('name')
            ->get();

        return new BackupContainerCollection($containers);
    }

    #[OA\Get(
        path: '/backup-containers/{id}',
        summary: 'Get a backup container',
        security: [['bearerAuth' => []]],
        tags: ['Backup Containers'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Backup container', content: new OA\JsonContent(ref: '#/components/schemas/BackupContainer')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function show(int $id): BackupContainerResource
    {
        /** @var BackupContainer $container */
        $container = BackupContainer::findOrFail($id);
        return new BackupContainerResource($container);
    }

    #[OA\Post(
        path: '/backup-containers',
        summary: 'Create a backup container',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'driver', 'location'],
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'driver', type: 'string', enum: ['local', 's3', 'ftp', 'ftps', 'sftp']),
                new OA\Property(property: 'location', type: 'string'),
                new OA\Property(property: 'credentials', type: 'object', nullable: true),
            ],
        )),
        tags: ['Backup Containers'],
        responses: [
            new OA\Response(response: 200, description: 'Backup container created', content: new OA\JsonContent(ref: '#/components/schemas/BackupContainer')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function store(BackupContainerStoreRequest $request): BackupContainerResource
    {
        /**
         * @var array{
         *   name: string,
         *   driver: string,
         *   location: string,
         *   credentials?: ?array
         * } $validated
         */
        $validated = $request->validated();

        /** @var BackupContainer $container */
        $container = BackupContainer::create([
            'name' => $validated['name'],
            'driver' => $validated['driver'],
            'location' => $validated['location'],
            'credentials' => $request->credentials(),
        ]);

        return new BackupContainerResource($container);
    }

    #[OA\Put(
        path: '/backup-containers/{id}',
        summary: 'Update a backup container',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'driver', type: 'string', enum: ['local', 's3', 'ftp', 'ftps', 'sftp']),
                new OA\Property(property: 'location', type: 'string'),
                new OA\Property(property: 'credentials', type: 'object', nullable: true),
            ],
        )),
        tags: ['Backup Containers'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Backup container updated', content: new OA\JsonContent(ref: '#/components/schemas/BackupContainer')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function update(int $id, BackupContainerUpdateRequest $request): BackupContainerResource
    {
        $container = BackupContainer::findOrFail($id);

        /**
         * @var array{
         *   name?: string,
         *   driver?: string,
         *   location?: string,
         *   credentials?: ?array
         * } $validated
         */
        $validated = $request->validated();

        if (array_key_exists('name', $validated)) {
            $container->name = $validated['name'];
        }
        if (array_key_exists('driver', $validated)) {
            $container->driver = $validated['driver'];
        }
        if (array_key_exists('location', $validated)) {
            $container->location = $validated['location'];
        }
        if (array_key_exists('credentials', $validated)) {
            $container->credentials = $request->normalizeCredentials(
                $container->driver,
                $validated['credentials'],
            );
        } elseif (array_key_exists('driver', $validated) && $validated['driver'] === 'local') {
            $container->credentials = null;
        }

        $container->save();

        return new BackupContainerResource($container);
    }

    #[OA\Delete(
        path: '/backup-containers/{id}',
        summary: 'Delete a backup container',
        security: [['bearerAuth' => []]],
        tags: ['Backup Containers'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Backup container deleted', content: new OA\JsonContent(ref: '#/components/schemas/BackupContainer')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 409, description: 'Container has backups', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function destroy(int $id, Request $request): BackupContainerResource|JsonResponse
    {
        $container = BackupContainer::findOrFail($id);
        $deleteBackups = filter_var($request->query('delete_backups'), FILTER_VALIDATE_BOOL);

        if ($container->backups()->exists()) {
            if (!$deleteBackups) {
                return new JsonResponse([
                    'message' => 'Cannot delete backup container while backups exist',
                ], 409);
            }

            $task = Task::start(
                jobType: DeleteBackupContainer::class,
                queue: 'default',
                username: null,
                details: ['container_id' => $container->id, 'action' => 'delete_container'],
            );
            DeleteBackupContainer::dispatch($container->id)->attachTask($task);

            return new JsonResponse([
                'data' => (new BackupContainerResource($container))->toArray($request),
                'task_id' => $task->id,
            ], 202);
        }

        $container->delete();

        return new BackupContainerResource($container);
    }

    #[OA\Post(
        path: '/backup-containers/{id}/test',
        summary: 'Test backup container connectivity',
        security: [['bearerAuth' => []]],
        tags: ['Backup Containers'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Connection test succeeded', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', properties: [new OA\Property(property: 'ok', type: 'boolean')], type: 'object')],
            )),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Connection test failed', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function test(int $id): JsonResponse
    {
        $container = BackupContainer::findOrFail($id);

        try {
            $container->storage()->test();
        } catch (Throwable $e) {
            return new JsonResponse([
                'message' => $e->getMessage(),
            ], 422);
        }

        return new JsonResponse(['data' => ['ok' => true]]);
    }
}
