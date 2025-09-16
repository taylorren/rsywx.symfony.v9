<?php

namespace App\Controller;

use App\Service\RsywxApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;

class ApiController extends AbstractController
{
    public function __construct(
        private RsywxApiService $apiService,
        private LoggerInterface $logger
    ) {}

    /**
     * Add tags to a book
     */
    public function addTagsToBook(string $bookId, Request $request): JsonResponse
    {
        try {
            // Validate request
            if (!$request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Invalid request'], Response::HTTP_BAD_REQUEST);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['tags']) || !is_array($data['tags'])) {
                return new JsonResponse(['error' => 'Tags array is required'], Response::HTTP_BAD_REQUEST);
            }

            $tags = array_filter(array_map('trim', $data['tags']));
            
            if (empty($tags)) {
                return new JsonResponse(['error' => 'At least one tag is required'], Response::HTTP_BAD_REQUEST);
            }

            // Call the API service to add tags
            $result = $this->apiService->addTagsToBook($bookId, $tags);

            if ($result && isset($result['success']) && $result['success']) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Tags added successfully',
                    'data' => $result['data'] ?? null
                ]);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'error' => $result['message'] ?? 'Failed to add tags'
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to add tags to book', [
                'bookId' => $bookId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}