<?php

namespace App\Controller;

use App\Service\RsywxApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

#[Route('/books')]
class BookController extends AbstractController
{
    public function __construct(
        private RsywxApiService $apiService,
        private LoggerInterface $logger
    ) {}

    /**
     * Display collection dashboard with statistics
     */
    #[Route('/', name: 'books_dashboard')]
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
    #[Route('/{bookId}', name: 'book_details', requirements: ['bookId' => '.+'])]
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
    #[Route('/search', name: 'books_search', methods: ['GET'])]
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
    #[Route('/latest', name: 'books_latest')]
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
    #[Route('/random', name: 'books_random')]
    public function random(Request $request): Response
    {
        try {
            $count = $request->query->getInt('count', 20);
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
     * Display recently visited books
     */
    #[Route('/recent', name: 'books_recent')]
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
     * Display forgotten books
     */
    #[Route('/forgotten', name: 'books_forgotten')]
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
    #[Route('/today/{month}/{date}', name: 'books_today', requirements: ['month' => '\d+', 'date' => '\d+'])]
    public function today(int $month = null, int $date = null, Request $request): Response
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
}