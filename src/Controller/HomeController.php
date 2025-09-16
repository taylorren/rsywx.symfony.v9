<?php

namespace App\Controller;

use App\Service\RsywxApiService;
use App\DTO\Book;
use App\DTO\CollectionStats;
use App\DTO\WordOfTheDay;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;

final class HomeController extends AbstractController
{
    public function __construct(
        private RsywxApiService $apiService,
        private LoggerInterface $logger
    ) {}

    /**
     * Homepage with collection overview using parallel API requests
     */
    public function index(): Response
    {
        try {
            // Define all API requests to be made in parallel
            $requests = [
                'stats' => ['method' => 'GET', 'endpoint' => '/books/status'],
                'latest' => ['method' => 'GET', 'endpoint' => '/books/latest/1'],
                'random' => ['method' => 'GET', 'endpoint' => '/books/random/4'],
                'forgotten' => ['method' => 'GET', 'endpoint' => '/books/forgotten/1'],
                'recent' => ['method' => 'GET', 'endpoint' => '/books/last_visited/1'],
                'wotd' => ['method' => 'GET', 'endpoint' => '/misc/wotd'],
                'reading_summary' => ['method' => 'GET', 'endpoint' => '/readings/summary'],
                'latest_readings' => ['method' => 'GET', 'endpoint' => '/readings/latest/10']
            ];

            // Execute all requests in parallel
            $responses = $this->apiService->makeParallelRequests($requests);

            // Responses are now already processed arrays, not ResponseInterface objects
            $statsData = $responses['stats'];
            $latestBooksData = $responses['latest'];
            $randomBooksData = $responses['random'];
            $forgottenBooksData = $responses['forgotten'];
            $recentlyVisitedBooksData = $responses['recent'];
            $wordOfTheDayData = $responses['wotd'];
            $readingSummaryData = $responses['reading_summary'];
            $latestReadingsData = $responses['latest_readings'];

            // Convert to DTOs
            $stats = $statsData && isset($statsData['success']) && $statsData['success'] && isset($statsData['data']) 
                ? CollectionStats::fromArray($statsData['data']) 
                : null;

            $latestBooks = $latestBooksData && isset($latestBooksData['success']) && $latestBooksData['success'] && isset($latestBooksData['data']) && is_array($latestBooksData['data'])
                ? array_map(fn($bookData) => Book::fromArray($bookData), $latestBooksData['data'])
                : [];

            $randomBooks = $randomBooksData && isset($randomBooksData['success']) && $randomBooksData['success'] && isset($randomBooksData['data']) && is_array($randomBooksData['data'])
                ? array_map(fn($bookData) => Book::fromArray($bookData), $randomBooksData['data'])
                : [];

            $forgottenBooks = $forgottenBooksData && isset($forgottenBooksData['success']) && $forgottenBooksData['success'] && isset($forgottenBooksData['data']) && is_array($forgottenBooksData['data'])
                ? array_map(fn($bookData) => Book::fromArray($bookData), $forgottenBooksData['data'])
                : [];

            $recentlyVisitedBooks = $recentlyVisitedBooksData && isset($recentlyVisitedBooksData['success']) && $recentlyVisitedBooksData['success'] && isset($recentlyVisitedBooksData['data']) && is_array($recentlyVisitedBooksData['data'])
                ? array_map(fn($bookData) => Book::fromArray($bookData), $recentlyVisitedBooksData['data'])
                : [];

            $wordOfTheDay = $wordOfTheDayData && isset($wordOfTheDayData['success']) && $wordOfTheDayData['success'] && isset($wordOfTheDayData['data'])
                ? WordOfTheDay::fromArray($wordOfTheDayData['data'])
                : null;

            // Process reading data
            $readingSummary = $readingSummaryData && isset($readingSummaryData['success']) && $readingSummaryData['success'] && isset($readingSummaryData['data'])
                ? $readingSummaryData['data']
                : null;

            $latestReadings = $latestReadingsData && isset($latestReadingsData['success']) && $latestReadingsData['success'] && isset($latestReadingsData['data']) && is_array($latestReadingsData['data'])
                ? $latestReadingsData['data']
                : [];

            return $this->render('home/index.html.twig', [
                'stats' => $stats,
                'latest_books' => $latestBooks,
                'random_books' => $randomBooks,
                'forgotten_books' => $forgottenBooks,
                'recently_visited_books' => $recentlyVisitedBooks,
                'word_of_the_day' => $wordOfTheDay,
                'readingSummary' => $readingSummary,
                'latestReadings' => $latestReadings,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load homepage: ' . $e->getMessage());

            return $this->render('home/index.html.twig', [
                'error' => 'Unable to load collection data. Please try again later.',
                'stats' => null,
                'latest_books' => [],
                'random_books' => [],
                'forgotten_books' => [],
                'recently_visited_books' => [],
                'word_of_the_day' => null,
                'readingSummary' => null,
                'latestReadings' => [],
            ]);
        }
    }

    /**
     * Get random books for Turbo frame updates
     */
    public function randomBooks(Request $request): Response
    {
        try {
            $refresh = $request->query->get('refresh', 'false') === 'true';
            $randomBooks = $this->getRandomBooks($refresh);
            
            return $this->render('_random_books_frame.html.twig', [
                'random_books' => $randomBooks,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load random books: ' . $e->getMessage());
            
            return $this->render('_random_books_frame.html.twig', [
                'random_books' => [],
            ]);
        }
    }

    /**
     * Extract random books fetching logic for reuse
     */
    private function getRandomBooks(bool $refresh = true): array
    {
        return $this->apiService->getRandomBooks(4, $refresh);
    }

    /**
     * About page
     */
    public function about(): Response
    {
        return $this->render('home/about.html.twig');
    }

    /**
     * Statistics page with detailed collection analytics
     */
    public function stats(): Response
    {
        try {
            $stats = $this->apiService->getCollectionStatus();

            if (!$stats) {
                throw new \Exception('Unable to fetch collection statistics');
            }

            return $this->render('home/stats.html.twig', [
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load statistics: ' . $e->getMessage());

            return $this->render('home/stats.html.twig', [
                'error' => 'Unable to load collection statistics. Please try again later.',
                'stats' => null,
            ]);
        }
    }
}
