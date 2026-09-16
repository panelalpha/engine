<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class UsageController extends Controller
{
    #[OA\Get(
        path: '/projects/{username}/usage',
        summary: 'Get resource usage for a user',
        security: [['bearerAuth' => []]],
        tags: ['Usage'],
        parameters: [new OA\Parameter(name: 'username', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'Usage statistics', content: new OA\JsonContent(ref: '#/components/schemas/Usage')),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function getUsage(string $username): JsonResponse
    {
        $user = User::findByUsername($username);
        if (!$user) {
            abort(new JsonResponse([
                'message' => 'User not found',
            ], 404));
        }

        $diskUsage = $user->project()->fileManager()->diskUsage();

        $query = "SELECT ";
        $query .= "(SELECT COUNT(*) FROM domains WHERE user_id = ? AND type = 'addon') AS addon_domains, ";
        $query .= "(SELECT COUNT(*) FROM domains WHERE user_id = ? AND type = 'sub') AS subdomains, ";
        $query .= "(SELECT COUNT(*) FROM ftp_accounts WHERE user_id = ?) AS ftp_accounts, ";
        $query .= "(SELECT COUNT(*) FROM sftp_accounts WHERE user_id = ?) AS sftp_accounts, ";
        $query .= "(SELECT COUNT(*) FROM mysql_databases WHERE user_id = ?) AS mysql_databases";

        $result = DB::select($query, array_fill(0, 5, $user->id));
        /** @var object $counters */
        $counters = $result[0];

        $data = [
            'storage' => [
                'usage' => $diskUsage,
                'maximum' => $user->getDiskSpaceLimit(),
            ],
           'addon_domains' => [
              'usage' => $counters->addon_domains,
              'maximum' => $user->getAddonDomainsLimit(),
            ],
            'subdomains' => [
              'usage' => $counters->subdomains,
              'maximum' => $user->getSubdomainsLimit(),
            ],
            'ftp_accounts' => [
              'usage' => $counters->ftp_accounts,
              'maximum' => $user->getFtpAccountsLimit(),
            ],
            'sftp_accounts' => [
              'usage' => $counters->sftp_accounts,
              'maximum' => $user->getSftpAccountsLimit(),
            ],
            'mysql_databases' => [
              'usage' => $counters->mysql_databases,
              'maximum' => $user->getMysqlDatabasesLimit(),
            ],
        ];

        return new JsonResponse($data);
    }
}
