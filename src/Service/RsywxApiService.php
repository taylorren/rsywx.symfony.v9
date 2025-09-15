<?php

namespace App\Service;

use App\DTO\Book;
use App\DTO\CollectionStats;
use App\DTO\SearchResult;
use App\DTO\WordOfTheDay;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class RsywxApiService
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private CacheInterface $cache;
    private string $baseUrl = 'http://api';
    private string $apiKey;
    private int $cacheTimeout;
    private int $maxRetries;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        CacheInterface $cache,
        string $rsywxApiBaseUrl,
        string $rsywxApiKey,
        int $cacheTimeout = 300, // 5 minutes default
        int $maxRetries = 3
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->cache = $cache;
        $this->baseUrl = $rsywxApiBaseUrl;
        $this->apiKey = $rsywxApiKey;
        $this->cacheTimeout = $cacheTimeout;
        $this->maxRetries = $maxRetries;
    }

    /**
     * Get collection statistics (total books, pages, etc.)
     */
    public function getCollectionStatus(bool $refresh = false): ?CollectionStats
    {
        return $this->getCachedData(
            'collection_status',
            fn() => $this->makeRequest('GET', '/books/status', [
                'refresh' => $refresh
            ]),
            fn($data) => CollectionStats::fromArray($data),
            'Failed to get collection status'
        );
    }

    /**
     * Get cached data with error handling
     */
    private function getCachedData(
        string $cacheKey,
        callable $dataProvider,
        callable $transformer,
        string $errorMessage,
        array $logContext = [],
        int $ttl = null
    ) {
        $ttl = $ttl ?? $this->cacheTimeout;
        
        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($dataProvider, $transformer, $ttl) {
                $item->expiresAfter($ttl);
                $data = $dataProvider();
                return $data ? $transformer($data) : null;
            });
        } catch (\Exception $e) {
            $this->logger->error($errorMessage, array_merge($logContext, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]));
            return null;
        }
    }

    /**
     * Make HTTP request with retry logic
     */
    private function makeRequestWithRetry(string $method, string $endpoint, array $queryParams = [], array $body = []): array
    {
        $lastException = null;
        
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                return $this->makeRequest($method, $endpoint, $queryParams, $body);
            } catch (\Exception $e) {
                $lastException = $e;
                
                if ($attempt < $this->maxRetries) {
                    $this->logger->warning('API request failed, retrying', [
                        'attempt' => $attempt,
                        'endpoint' => $endpoint,
                        'error' => $e->getMessage()
                    ]);
                    
                    // Exponential backoff
                    usleep(pow(2, $attempt - 1) * 100000); // 0.1s, 0.2s, 0.4s...
                }
            }
        }
        
        throw $lastException;
    }

    /**
     * Get detailed information for a specific book
     */
    public function getBookDetails(string $bookId, bool $refresh = false): ?Book
    {
        return $this->getCachedData(
            "book_details_{$bookId}",
            fn() => $this->makeRequest('GET', "/books/{$bookId}", [
                'refresh' => $refresh
            ]),
            fn($data) => Book::fromArray($data),
            'Failed to get book details',
            ['bookId' => $bookId]
        );
    }

    /**
     * Get latest purchased books
     */
    public function getLatestBooks(int $count = 5, bool $refresh = false): array
    {
        return $this->getCachedData(
            "latest_books_{$count}",
            fn() => $this->makeRequest('GET', "/books/latest/{$count}", [
                'refresh' => $refresh
            ]),
            fn($data) => $data && isset($data['books']) ? array_map(fn($bookData) => Book::fromArray($bookData), $data['books']) : [],
            'Failed to get latest books',
            ['count' => $count],
            120 // Cache for 2 minutes for frequently changing data
        ) ?? [];
    }

    /**
     * Get random books from collection
     */
    public function getRandomBooks(int $count = 5, bool $refresh = false): array
    {
        // Don't cache random books as they should be different each time
        try {
            $data = $this->makeRequestWithRetry('GET', "/books/random/{$count}", [
                'refresh' => $refresh
            ]);
            
            if (!$data || !isset($data['books'])) {
                return [];
            }
            
            return array_map(fn($bookData) => Book::fromArray($bookData), $data['books']);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get random books', [
                'count' => $count,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Get recently visited books
     */
    public function getRecentlyVisitedBooks(int $count = 5, bool $refresh = false): array
    {
        return $this->getCachedData(
            "recently_visited_books_{$count}",
            fn() => $this->makeRequest('GET', "/books/last_visited/{$count}", [
                'refresh' => $refresh
            ]),
            fn($data) => $data && isset($data['books']) ? array_map(fn($bookData) => Book::fromArray($bookData), $data['books']) : [],
            'Failed to get recently visited books',
            ['count' => $count],
            120 // Cache for 2 minutes for frequently changing data
        ) ?? [];
    }

    /**
     * Get forgotten books (not visited recently)
     */
    public function getForgottenBooks(int $count = 5, bool $refresh = false): array
    {
        return $this->getCachedData(
            "forgotten_books_{$count}",
            fn() => $this->makeRequest('GET', "/books/forgotten/{$count}", [
                'refresh' => $refresh
            ]),
            fn($data) => $data && isset($data['books']) ? array_map(fn($bookData) => Book::fromArray($bookData), $data['books']) : [],
            'Failed to get forgotten books',
            ['count' => $count],
            600 // Cache for 10 minutes as this data changes less frequently
        ) ?? [];
    }

    /**
     * Get books purchased on today's date in previous years
     */
    public function getTodaysBooks(bool $refresh = false): array
    {
        return $this->getCachedData(
            'todays_books_' . date('m-d'),
            fn() => $this->makeRequest('GET', '/books/today', [
                'refresh' => $refresh
            ]),
            fn($data) => $data && isset($data['books']) ? array_map(fn($bookData) => Book::fromArray($bookData), $data['books']) : [],
            'Failed to get today\'s books',
            [],
            3600 // Cache for 1 hour as this is date-specific
        ) ?? [];
    }

    /**
     * Get books purchased on a specific date
     */
    public function getBooksForDate(int $month, int $date, bool $refresh = false): array
    {
        return $this->getCachedData(
            "books_for_date_{$month}_{$date}",
            fn() => $this->makeRequest('GET', "/books/today/{$month}/{$date}", [
                'refresh' => $refresh
            ]),
            fn($data) => $data && isset($data['books']) ? array_map(fn($bookData) => Book::fromArray($bookData), $data['books']) : [],
            'Failed to get books for date',
            ['month' => $month, 'date' => $date],
            3600 // Cache for 1 hour as this is date-specific
        ) ?? [];
    }

    /**
     * Search books by various criteria
     */
    public function searchBooks(string $type, string $value = '', int $page = 1): ?SearchResult
    {
        $endpoint = "/books/search/{$type}";
        if (!empty($value)) {
            $endpoint .= "/{$value}";
        }
        if ($page > 1) {
            $endpoint .= "/{$page}";
        }

        return $this->getCachedData(
            "search_{$type}_{$value}_{$page}",
            fn() => $this->makeRequest('GET', $endpoint),
            fn($data) => SearchResult::fromArray($data),
            'Failed to search books',
            ['type' => $type, 'value' => $value, 'page' => $page],
            300 // Cache for 5 minutes
        );
    }

    /**
     * Get related books for a specific book
     */
    public function getRelatedBooks(string $bookId, int $count = 5, bool $refresh = false): array
    {
        return $this->makeRequest('GET', "/books/{$bookId}/related/{$count}", [
            'refresh' => $refresh
        ]);
    }

    /**
     * Add tags to a book
     */
    public function addTagsToBook(string $bookId, array $tags): array
    {
        return $this->makeRequest('POST', "/books/{$bookId}/tags", [], [
            'tags' => $tags
        ]);
    }

    /**
     * Get visit history statistics
     */
    public function getVisitHistory(int $days = 30, bool $refresh = false): array
    {
        return $this->makeRequest('GET', '/books/visit_history', [
            'days' => $days,
            'refresh' => $refresh
        ]);
    }

    /**
     * Get Word of the Day
     */
    public function getWordOfTheDay(): ?WordOfTheDay
    {
        return $this->getCachedData(
            'word_of_the_day_' . date('Y-m-d'),
            fn() => $this->makeRequest('GET', '/misc/wotd'),
            fn($data) => WordOfTheDay::fromArray($data),
            'Failed to get word of the day',
            [],
            86400 // Cache for 24 hours since it's daily
        );
    }

    /**
     * Make HTTP request to the API
     */
    private function makeRequest(string $method, string $endpoint, array $queryParams = [], array $body = []): array
    {
        try {
            $options = [
                'headers' => [
                    'X-API-Key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
            ];

            if (!empty($queryParams)) {
                $options['query'] = $queryParams;
            }

            if (!empty($body)) {
                $options['json'] = $body;
            }

            $response = $this->httpClient->request(
                $method,
                $this->baseUrl . $endpoint,
                $options
            );

            $statusCode = $response->getStatusCode();
            $content = $response->toArray();

            if ($statusCode >= 400) {
                $this->logger->error('RSYWX API Error', [
                    'endpoint' => $endpoint,
                    'status_code' => $statusCode,
                    'response' => $content
                ]);
                
                throw new \Exception("API request failed with status {$statusCode}");
            }

            return $content;

        } catch (\Exception $e) {
            $this->logger->error('RSYWX API Request Failed', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
}