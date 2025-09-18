<?php

namespace App\Controller;

use App\DTO\Book;
use App\Service\RsywxApiService;
use App\DTO\CollectionStats;
use App\DTO\WordOfTheDay;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class BookController extends AbstractController
{
    public function __construct(
        private RsywxApiService $apiService,
        private LoggerInterface $logger,
        private HttpClientInterface $httpClient
    ) {}

    /**
     * Display collection dashboard with statistics using parallel API requests
     */
    public function dashboard(): Response
    {
        try {
            $stats = $this->apiService->getCollectionStatus();
            $latestBooks = $this->apiService->getLatestBooks(6);
            $randomBooks = $this->apiService->getRandomBooks(6);
            $wordOfTheDay = $this->apiService->getWordOfTheDay();

            return $this->render('books/dashboard.html.twig', [
                'stats' => $stats,
                'latest_books' => $latestBooks,
                'random_books' => $randomBooks,
                'word_of_the_day' => $wordOfTheDay,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load dashboard: ' . $e->getMessage());
            
            return $this->render('books/dashboard.html.twig', [
                'error' => 'Unable to load collection data. Please try again later.',
                'stats' => null,
                'latest_books' => [],
                'random_books' => [],
                'word_of_the_day' => null,
            ]);
        }
    }

    /**
     * Display book details
     */
    public function details(string $bookId, Request $request): Response
    {
        try {
            $refresh = $request->query->getBoolean('refresh', false);
            $book = $this->apiService->getBookDetails($bookId, $refresh);

            if (!$book) {
                throw $this->createNotFoundException('Book not found');
            }

            return $this->render('books/details.html.twig', [
                'book' => $book,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load book details: ' . $e->getMessage());
            
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                throw $e;
            }
            
            return $this->render('books/error.html.twig', [
                'error' => 'Unable to load book details. Please try again later.',
            ]);
        }
    }

    /**
     * Search books
     */
    public function search(Request $request): Response
    {
        $query = $request->query->get('q', '');
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20);
        
        $searchResult = null;
        $error = null;

        if ($query) {
            try {
                $searchResult = $this->apiService->searchBooks($query, $page, $limit);
            } catch (\Exception $e) {
                $this->logger->error('Search failed: ' . $e->getMessage());
                $error = 'Search failed. Please try again later.';
            }
        }

        return $this->render('books/search.html.twig', [
            'query' => $query,
            'search_result' => $searchResult,
            'error' => $error,
        ]);
    }

    /**
     * Display latest books
     */
    public function latest(Request $request): Response
    {
        try {
            $count = $request->query->getInt('count', 20);
            $refresh = $request->query->getBoolean('refresh', false);
            
            $books = $this->apiService->getLatestBooks($count, $refresh);

            return $this->render('books/list.html.twig', [
                'title' => 'Latest Books',
                'books' => $books,
                'show_purchase_date' => true,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load latest books: ' . $e->getMessage());
            
            return $this->render('books/list.html.twig', [
                'title' => 'Latest Books',
                'books' => [],
                'error' => 'Unable to load latest books. Please try again later.',
            ]);
        }
    }

    /**
     * Display random books
     */
    public function random(Request $request, int $count = 1): Response
    {
        try {
            $refresh = $request->query->getBoolean('refresh', false);
            
            $books = $this->apiService->getRandomBooks($count, $refresh);

            return $this->render('books/list.html.twig', [
                'title' => 'Random Books',
                'books' => $books,
                'show_random_note' => true,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load random books: ' . $e->getMessage());
            
            return $this->render('books/list.html.twig', [
                'title' => 'Random Books',
                'books' => [],
                'error' => 'Unable to load random books. Please try again later.',
            ]);
        }
    }

    /**
     * Display lucky books - 9 random books for 手气不错 page
     */
    public function lucky(Request $request): Response
    {
        try {
            // Always force refresh for random books to ensure new results each time
            $books = $this->apiService->getRandomBooks(9, true);

            return $this->render('books/lucky.html.twig', [
                'title' => '手气不错',
                'books' => $books,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load lucky books: ' . $e->getMessage());
            
            return $this->render('books/lucky.html.twig', [
                'title' => '手气不错',
                'books' => [],
                'error' => 'Unable to load lucky books. Please try again later.',
            ]);
        }
    }

    /**
     * Display recently visited books
     */
    public function recent(Request $request): Response
    {
        try {
            $count = $request->query->getInt('count', 20);
            $refresh = $request->query->getBoolean('refresh', false);
            
            $books = $this->apiService->getRecentlyVisitedBooks($count, $refresh);

            return $this->render('books/list.html.twig', [
                'title' => 'Recently Visited Books',
                'books' => $books,
                'show_visit_info' => true,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load recent books: ' . $e->getMessage());
            
            return $this->render('books/list.html.twig', [
                'title' => 'Recently Visited Books',
                'books' => [],
                'error' => 'Unable to load recently visited books. Please try again later.',
            ]);
        }
    }

    /**
     * Display popular books (most visited)
     */
    public function popular(Request $request, int $count = 20): Response
    {
        try {
            $refresh = $request->query->getBoolean('refresh', false);
            
            $books = $this->apiService->getPopularBooks($count, $refresh);

            return $this->render('books/list.html.twig', [
                'title' => 'Popular Books',
                'books' => $books,
                'show_visit_info' => true,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load popular books: ' . $e->getMessage());
            
            return $this->render('books/list.html.twig', [
                'title' => 'Popular Books',
                'books' => [],
                'error' => 'Unable to load popular books. Please try again later.',
            ]);
        }
    }

    /**
     * Display forgotten books
     */
    public function forgotten(Request $request): Response
    {
        try {
            $count = $request->query->getInt('count', 20);
            $refresh = $request->query->getBoolean('refresh', false);
            
            $books = $this->apiService->getForgottenBooks($count, $refresh);

            return $this->render('books/list.html.twig', [
                'title' => 'Forgotten Books',
                'books' => $books,
                'show_forgotten_info' => true,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load forgotten books: ' . $e->getMessage());
            
            return $this->render('books/list.html.twig', [
                'title' => 'Forgotten Books',
                'books' => [],
                'error' => 'Unable to load forgotten books. Please try again later.',
            ]);
        }
    }

    /**
     * Display books purchased today (or on specific date)
     */
    public function today(?int $month = null, ?int $date = null, Request $request): Response
    {
        try {
            $refresh = $request->query->getBoolean('refresh', false);
            
            if ($month && $date) {
                $books = $this->apiService->getBooksForDate($month, $date, $refresh);
                $title = sprintf('Books Purchased on %d/%d', $month, $date);
            } else {
                $books = $this->apiService->getTodaysBooks($refresh);
                $title = 'Books Purchased Today';
            }

            return $this->render('books/list.html.twig', [
                'title' => $title,
                'books' => $books,
                'show_purchase_date' => true,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load today\'s books: ' . $e->getMessage());
            
            return $this->render('books/list.html.twig', [
                'title' => $title ?? 'Today\'s Books',
                'books' => [],
                'error' => 'Unable to load books for this date. Please try again later.',
            ]);
        }
    }

    /**
     * Get book tags for Turbo frame updates
     */
    public function bookTags(string $bookId, Request $request): Response
    {
        try {
            // Check if refresh is requested to bypass cache
            $refresh = $request->query->getBoolean('refresh', false);
            $book = $this->apiService->getBookDetails($bookId, $refresh);
            
            if (!$book) {
                throw new \Exception('Book not found');
            }
            
            return $this->render('books/_book_tags_frame.html.twig', [
                'book' => $book,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load book tags: ' . $e->getMessage());
            
            return $this->render('books/_book_tags_frame.html.twig', [
                'book' => null,
            ]);
        }
    }

    /**
     * Display filtered book list with key-value search and pagination
     */
    public function listFiltered(string $key='title', string $value='-', int $page = 1): Response
    {
        try {
            // Call API service to get filtered books using existing endpoint
            $result = $this->apiService->getBooksList($key, $value, $page);
            
            if (!$result) {
                // Return empty result page instead of error
                return $this->render('books/list.html.twig', [
                    'books' => [],
                    'pagination' => null,
                    'searchKey' => $key,
                    'searchValue' => $value,
                    'currentPage' => $page,
                    'page_title' => $this->getSearchTitle($key, $value),
                    'page_description' => $this->getSearchDescription($key, $value),
                    'page_icon' => 'bi bi-search',
                    'no_results' => true
                ]);
            }

            return $this->render('books/list.html.twig', [
                'books' => $this->convertToBooksArray($result['data'] ?? []),
                'pagination' => $result['pagination'] ?? null,
                'searchKey' => $key,
                'searchValue' => $value,
                'currentPage' => $page,
                'page_title' => $this->getSearchTitle($key, $value),
                'page_description' => $this->getSearchDescription($key, $value),
                'page_icon' => 'bi bi-search'
            ]);
        } catch (NotFoundHttpException $e) {
            // Re-throw 404 exceptions
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Failed to load filtered books', [
                'key' => $key,
                'value' => $value,
                'page' => $page,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new NotFoundHttpException('Unable to load book list at this time');
        }
    }

    private function convertToBooksArray(array $booksData): array
    {
        $books = [];
        foreach ($booksData as $bookData) {
            $books[] = Book::fromArray($bookData);
        }
        return $books;
    }

    private function getSearchTitle(string $key, string $value): string
    {
        if ($value === '-') {
            return match($key) {
                'title' => '所有书籍（按ID排序）',
                'author' => '所有书籍（按作者）',
                'tag' => '所有书籍（按标签）',
                'misc' => '所有书籍（按其他）',
                default => '所有书籍'
            };
        }

        return match($key) {
            'title' => "标题包含「{$value}」的书籍",
            'author' => "作者包含「{$value}」的书籍",
            'tag' => "标签包含「{$value}」的书籍",
            'misc' => "其他信息包含「{$value}」的书籍",
            default => "搜索结果"
        };
    }

    /**
     * Display visit records
     */
    public function visitRecords(): Response
    {
        try {
            // Get visit records from API (now returns multiple datasets)
            $visitData = $this->apiService->getVisitRecords();
            
            return $this->render('books/visit_records.html.twig', [
                'visit_data' => $visitData,
                'records' => [], // For now, no table records - only chart data
                'datasets' => $visitData['datasets'] ?? [],
                'page_title' => '访问记录',
                'page_description' => '书籍访问记录和统计',
                'page_icon' => 'bi bi-clock-history'
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load visit records: ' . $e->getMessage());
            
            return $this->render('books/visit_records.html.twig', [
                'error' => 'Unable to load visit records. Please try again later.',
                'visit_data' => ['visit_history' => [], 'datasets' => []],
                'records' => [],
                'datasets' => [],
                'page_title' => '访问记录',
                'page_description' => '书籍访问记录和统计',
                'page_icon' => 'bi bi-clock-history'
            ]);
        }
    }

    /**
     * Display today's books from the remote API
     */
    public function todaysBooks(): Response
    {
        try {
            $response = $this->httpClient->request('GET', $_ENV['RSYWX_API_BASE_URL'] . '/books/today', [
                'headers' => [
                    'X-API-Key' => $_ENV['RSYWX_API_KEY']
                ]
            ]);

            $data = $response->toArray();
            
            if (!$data['success']) {
                throw new \Exception('API returned error: ' . ($data['message'] ?? 'Unknown error'));
            }

            return $this->render('books/todays_books.html.twig', [
                'books' => $data['data'],
                'date_info' => $data['date_info'] ?? null,
                'cached' => $data['cached'] ?? false,
                'error' => null
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load today\'s books: ' . $e->getMessage());
            
            return $this->render('books/todays_books.html.twig', [
                'error' => 'Unable to load today\'s books. Please try again later.',
                'books' => [],
                'date_info' => null,
                'cached' => false
            ]);
        }
    }

    private function getSearchDescription(string $key, string $value): string
    {
        if ($value === '-') {
            return match($key) {
                'title' => '显示所有书籍，按ID降序排列',
                'author' => '显示所有书籍，按作者分组',
                'tag' => '显示所有书籍，按标签分组',
                'misc' => '显示所有书籍，按其他信息分组',
                default => '显示所有书籍'
            };
        }

        return match($key) {
            'title' => "搜索标题中包含「{$value}」的书籍",
            'author' => "搜索作者中包含「{$value}」的书籍",
            'tag' => "搜索标签中包含「{$value}」的书籍",
            'misc' => "搜索其他信息中包含「{$value}」的书籍",
            default => "搜索结果"
        };
    }
}