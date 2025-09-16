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

class RsywxApiService
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private string $baseUrl = 'http://api';
    private string $apiKey;
    private int $maxRetries;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        string $rsywxApiBaseUrl,
        string $rsywxApiKey,
        int $maxRetries = 3
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->baseUrl = $rsywxApiBaseUrl;
        $this->apiKey = $rsywxApiKey;
        $this->maxRetries = $maxRetries;
    }

    /**
     * Get collection statistics (total books, pages, etc.)
     */
    public function getCollectionStatus(bool $refresh = false): ?CollectionStats
    {
        try {
            $response = $this->makeRequestWithRetry('GET', '/books/status', [
                'refresh' => $refresh
            ]);
            
            // Extract data from API response structure
            if ($response && isset($response['success']) && $response['success'] && isset($response['data'])) {
                return CollectionStats::fromArray($response['data']);
            }
            
            return null;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get collection status', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Make async HTTP request and return ResponseInterface for parallel processing
     */
    public function makeAsyncRequest(string $method, string $endpoint, array $queryParams = [], array $body = []): ResponseInterface
    {
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

        // Return the response object immediately without waiting for completion
        return $this->httpClient->request(
            $method,
            $this->baseUrl . $endpoint,
            $options
        );
    }

    /**
     * Make multiple parallel requests and wait for all to complete using true parallelism
     */
    public function makeParallelRequests(array $requests): array
    {
        $responses = [];
        
        // Start all requests without waiting
        foreach ($requests as $key => $request) {
            $responses[$key] = $this->makeAsyncRequest(
                $request['method'],
                $request['endpoint'],
                $request['queryParams'] ?? [],
                $request['body'] ?? []
            );
        }
        
        // Use Symfony's stream() method for true parallel processing
        $results = [];
        foreach ($this->httpClient->stream($responses) as $response => $chunk) {
            if ($chunk->isLast()) {
                // Find the key for this response
                $key = array_search($response, $responses, true);
                if ($key !== false) {
                    // Get the endpoint from the response info for logging
                    $endpoint = $response->getInfo('url') ?? 'unknown';
                    $results[$key] = $this->processAsyncResponse($response, $endpoint);
                }
            }
        }
        
        return $results;
    }

    /**
     * Process async response with error handling
     */
    public function processAsyncResponse(ResponseInterface $response, string $endpoint): array
    {
        try {
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
            $this->logger->error('RSYWX API Response Processing Failed', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
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
        try {
            $response = $this->makeRequestWithRetry('GET', "/books/{$bookId}", [
                'refresh' => $refresh
            ]);
            
            // Extract data from API response structure
            if ($response && isset($response['success']) && $response['success'] && isset($response['data'])) {
                return Book::fromArray($response['data']);
            }
            
            return null;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get book details', [
                'bookId' => $bookId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Get latest purchased books
     */
    public function getLatestBooks(int $count = 5, bool $refresh = false): array
    {
        try {
            $response = $this->makeRequestWithRetry('GET', "/books/latest/{$count}", [
                'refresh' => $refresh
            ]);
            
            // Extract data from API response structure
            if ($response && isset($response['success']) && $response['success'] && isset($response['data']) && is_array($response['data'])) {
                return array_map(fn($bookData) => Book::fromArray($bookData), $response['data']);
            }
            
            return [];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get latest books', [
                'count' => $count,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Get random books from collection
     */
    public function getRandomBooks(int $count = 5, bool $refresh = false): array
    {
        // Don't cache random books as they should be different each time
        try {
            $response = $this->makeRequestWithRetry('GET', "/books/random/{$count}", [
                'refresh' => $refresh
            ]);
            
            // Extract data from API response structure
            if ($response && isset($response['success']) && $response['success'] && isset($response['data']) && is_array($response['data'])) {
                return array_map(fn($bookData) => Book::fromArray($bookData), $response['data']);
            }
            
            return [];
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
     * Get forgotten books (not visited recently)
     */
    public function getForgottenBooks(int $count = 5, bool $refresh = false): array
    {
        try {
            $response = $this->makeRequestWithRetry('GET', "/books/forgotten/{$count}", [
                'refresh' => $refresh
            ]);
            
            // Extract data from API response structure (same as other book methods)
            if ($response && isset($response['success']) && $response['success'] && isset($response['data']) && is_array($response['data'])) {
                return array_map(fn($bookData) => Book::fromArray($bookData), $response['data']);
            }
            
            return [];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get forgotten books', [
                'count' => $count,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Get books purchased on today's date in previous years
     */
    public function getTodaysBooks(bool $refresh = false): array
    {
        try {
            $data = $this->makeRequestWithRetry('GET', '/books/today', [
                'refresh' => $refresh
            ]);
            
            if (!$data || !isset($data['books'])) {
                return [];
            }
            
            return array_map(fn($bookData) => Book::fromArray($bookData), $data['books']);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get today\'s books', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Get books purchased on a specific date
     */
    public function getBooksForDate(int $month, int $date, bool $refresh = false): array
    {
        try {
            $data = $this->makeRequestWithRetry('GET', "/books/today/{$month}/{$date}", [
                'refresh' => $refresh
            ]);
            
            if (!$data || !isset($data['books'])) {
                return [];
            }
            
            return array_map(fn($bookData) => Book::fromArray($bookData), $data['books']);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get books for date', [
                'month' => $month,
                'date' => $date,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
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

        try {
            $data = $this->makeRequestWithRetry('GET', $endpoint);
            return $data ? SearchResult::fromArray($data) : null;
        } catch (\Exception $e) {
            $this->logger->error('Failed to search books', [
                'type' => $type,
                'value' => $value,
                'page' => $page,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
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
        try {
            $data = $this->makeRequestWithRetry('GET', '/misc/wotd');
            return $data ? WordOfTheDay::fromArray($data) : null;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get word of the day', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Get recently visited books
     */
    public function getRecentlyVisitedBooks(int $count = 5, bool $refresh = false): array
    {
        try {
            $response = $this->makeRequestWithRetry('GET', "/books/last_visited", [
                'count' => $count,
                'refresh' => $refresh
            ]);
            
            // Extract data from API response structure
            if ($response && isset($response['success']) && $response['success'] && isset($response['data']) && is_array($response['data'])) {
                return array_map(fn($bookData) => Book::fromArray($bookData), $response['data']);
            }
            
            return [];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get recently visited books', [
                'count' => $count,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Make HTTP request to the API
     */
    private function makeRequest(string $method, string $endpoint, array $queryParams = [], array $body = []): array
    {
        $fullUrl = $this->baseUrl . $endpoint;
        
        $this->logger->info('Making API Request', [
            'method' => $method,
            'endpoint' => $endpoint,
            'full_url' => $fullUrl,
            'base_url' => $this->baseUrl,
            'query_params' => $queryParams
        ]);
        
        try {
            $options = [
                'headers' => [
                    'X-API-Key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
            ];

            if (!empty($queryParams)) {
                // Convert boolean values to string 'true'/'false' for API compatibility
                $processedParams = [];
                foreach ($queryParams as $key => $value) {
                    if (is_bool($value)) {
                        $processedParams[$key] = $value ? 'true' : 'false';
                    } else {
                        $processedParams[$key] = $value;
                    }
                }
                $options['query'] = $processedParams;
            }

            if (!empty($body)) {
                $options['json'] = $body;
            }

            $response = $this->httpClient->request(
                $method,
                $fullUrl,
                $options
            );

            $statusCode = $response->getStatusCode();
            $content = $response->toArray();

            $this->logger->info('API Response Received', [
                'endpoint' => $endpoint,
                'status_code' => $statusCode,
                'response_size' => strlen(json_encode($content))
            ]);

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
                'full_url' => $fullUrl,
                'error' => $e->getMessage(),
                'error_class' => get_class($e)
            ]);
            
            throw $e;
        }
    }

    /**
     * Get books list with filtering and pagination
     */
    public function getBooksList(string $type = 'title', string $value = '-', int $page = 1): ?array
    {
        // Construct endpoint directly: /books/list/{type}/{value}/{page}
        $endpoint = "/books/list/{$type}/" . $value . "/{$page}";
        
        // Debug output to see the final endpoint
        $this->logger->info('API Endpoint Debug', [
            'endpoint' => $endpoint,
            'type' => $type,
            'value' => $value,
            'page' => $page
        ]);

        try {
            $response = $this->makeRequestWithRetry('GET', $endpoint);
            
            // Return the full response structure including data and pagination
            if ($response && isset($response['success']) && $response['success']) {
                return $response;
            }
            
            return null;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get books list', [
                'type' => $type,
                'value' => $value,
                'page' => $page,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}