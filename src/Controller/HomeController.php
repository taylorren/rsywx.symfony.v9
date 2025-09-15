<?php

namespace App\Controller;

use App\Service\RsywxApiService;
use App\DTO\Book;
use App\DTO\CollectionStats;
use App\DTO\WordOfTheDay;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
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
            // Use the original synchronous methods for now to ensure functionality
            $stats = $this->apiService->getCollectionStatus();
            $latestBooks = $this->apiService->getLatestBooks(4);
            $randomBooks = $this->apiService->getRandomBooks(4);
            $wordOfTheDay = $this->apiService->getWordOfTheDay();

            return $this->render('home/index.html.twig', [
                'stats' => $stats,
                'latest_books' => $latestBooks,
                'random_books' => $randomBooks,
                'word_of_the_day' => $wordOfTheDay,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load homepage: ' . $e->getMessage());
            
            return $this->render('home/index.html.twig', [
                'error' => 'Unable to load collection data. Please try again later.',
                'stats' => null,
                'latest_books' => [],
                'random_books' => [],
                'word_of_the_day' => null,
            ]);
        }
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
