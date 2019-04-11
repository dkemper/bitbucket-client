<?php

namespace Bitbucket;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class Client
{
    /** @var GuzzleClient $guzzle */
    protected $guzzle;

    /** @var string $url */
    protected $url;

    /** @var string $token */
    protected $token;

    public function __construct(GuzzleClient $guzzle, string $url, string $token)
    {
        $this->guzzle = $guzzle;
        $this->url = $url;
        $this->token = $token;
    }

    /**
     * @param string $branchName
     * @param string $projectKey
     * @param string $repositorySlug
     * @return int|null
     * @throws GuzzleException
     */
    public function findPullRequestsByBranch(string $branchName, string $projectKey, string $repositorySlug): ?int
    {
        $endpoint = sprintf('/projects/%s/repos/%s/pull-requests', $projectKey, $repositorySlug);
        $response = $this->request($endpoint)->getBody();
        $response = json_decode($response, true);

        foreach ($response['values'] as $pullRequest) {
            if ($pullRequest['fromRef']['displayId'] == $branchName) {
                return (int)$pullRequest['id'];
            }
        }
        return null;
    }

    /**
     * @param string $projectKey
     * @param string $repositorySlug
     * @param array $userWhiteList
     * @return array
     * @throws GuzzleException
     */
    public function findBranchesWithOpenPullRequests(string $projectKey, string $repositorySlug, array $userWhiteList = []): array
    {
        $endpoint = sprintf('/projects/%s/repos/%s/pull-requests', $projectKey, $repositorySlug);
        $response = $this->request($endpoint)->getBody();
        $response = json_decode($response, true);
        $branchNames = [];
        foreach ($response['values'] as $pullRequest) {
            $isReviewer = false;
            foreach ($pullRequest['reviewers'] as $reviewer) {
                if (in_array($reviewer['user']['name'], $userWhiteList, true)) {
                    $isReviewer = true;
                    break;
                }
            }

            if (!$isReviewer && !in_array($pullRequest['author']['user']['name'], $userWhiteList, true)) {
                continue;
            }

            $branchNames[$pullRequest['id']] = (string)$pullRequest['fromRef']['displayId'];
        }
        return $branchNames;
    }

    /**
     * @param string $projectKey
     * @param string $repositorySlug
     * @param int $pullRequestId
     * @param string $message
     * @return int
     * @throws GuzzleException
     */
    public function createCommentInPullRequest(string $projectKey, string $repositorySlug, int $pullRequestId, string $message): int
    {
        $endpoint = sprintf(
            '/projects/%s/repos/%s/pull-requests/%s/comments',
            $projectKey,
            $repositorySlug,
            $pullRequestId
        );

        $response = $this->request($endpoint, 'post', ['text' => $message])->getBody();
        $response = json_decode($response, true);

        return $response['id'];
    }

    /**
     * @param int $commentId
     * @param string $message
     * @throws GuzzleException
     */
    public function createTaskForCommentInPullRequest(int $commentId, string $message): void
    {
        $this->request('/tasks', 'post', [
            'anchor' => [
                'id' => $commentId,
                'type' => 'COMMENT',
            ],
            'text' => $message,
        ]);
    }

    /**
     * @param string $endpoint
     * @param string $method
     * @param array|null $jsonBody
     * @return mixed|ResponseInterface
     * @throws GuzzleException
     */
    protected function request(string $endpoint, string $method = 'get', ?array $jsonBody = null)
    {
        $url = $this->url . '/rest/api/1.0' . $endpoint;
        $options = ['headers' => ['Authorization' => 'Bearer ' . $this->token]];
        $jsonBody !== null && $options['json'] = $jsonBody;
        return $this->guzzle->request($method, $url, $options);
    }
}
