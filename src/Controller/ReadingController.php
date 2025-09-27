<?php

namespace App\Controller;

use App\Service\RsywxApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

class ReadingController extends AbstractController
{
    public function __construct(
        private RsywxApiService $apiService,
        private LoggerInterface $logger
    ) {}

    /**
     * Display reading page with book reviews and covers
     */
    public function index(Request $request, int $page = 1): Response
    {
        try {
            $refresh = $request->query->getBoolean('refresh', false);
            
            // Use the proper API method for reading reviews with pagination
            $response = $this->apiService->getReadingReviewsWithPagination($page, $refresh);
            
            // Check if the API response is successful
            if (!$response || !isset($response['success']) || !$response['success']) {
                throw new \Exception('API returned unsuccessful response');
            }
            
            $readingReviews = $response['data'] ?? [];
            $paginationData = $response['pagination'] ?? null;
            
            // Reading reviews have a different structure than Book objects
            // They contain: title, datein, uri, feature, bookid, book_title, cover_uri
            
            // Build pagination info
            $pagination = null;
            if ($paginationData) {
                $pagination = [
                    'current' => $paginationData['current_page'] ?? $page,
                    'total' => $paginationData['total_pages'] ?? 1,
                    'has_previous' => ($paginationData['current_page'] ?? $page) > 1,
                    'has_next' => ($paginationData['current_page'] ?? $page) < ($paginationData['total_pages'] ?? 1),
                    'previous' => ($paginationData['current_page'] ?? $page) > 1 ? ($paginationData['current_page'] ?? $page) - 1 : null,
                    'next' => ($paginationData['current_page'] ?? $page) < ($paginationData['total_pages'] ?? 1) ? ($paginationData['current_page'] ?? $page) + 1 : null,
                    'range' => "Page " . ($paginationData['current_page'] ?? $page) . " of " . ($paginationData['total_pages'] ?? 1)
                ];
            }

            return $this->render('reading/index.html.twig', [
                'readingReviews' => $readingReviews,
                'pagination' => $pagination,
                'current_page' => $page,
                'page_title' => '读书',
                'page_description' => '图书评论和阅读记录',
                'page_icon' => 'bi bi-book',
                'error' => null
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load reading page: ' . $e->getMessage());
            
            return $this->render('reading/index.html.twig', [
                'error' => 'Unable to load reading data. Please try again later.',
                'readingReviews' => [],
                'pagination' => null,
                'current_page' => 1,
                'page_title' => '读书',
                'page_description' => '图书评论和阅读记录',
                'page_icon' => 'bi bi-book'
            ]);
        }
    }
}