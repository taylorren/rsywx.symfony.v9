<?php

namespace App\Controller;

use App\Service\RsywxApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;

final class HomeController extends AbstractController
{
    public function __construct(
        private RsywxApiService $apiService,
        private LoggerInterface $logger
    ) {}

    /**
     * Homepage with collection overview
     */
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        try {
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
    #[Route('/about', name: 'about')]
    public function about(): Response
    {
        return $this->render('home/about.html.twig');
    }

    /**
     * Statistics page with detailed collection analytics
     */
    #[Route('/stats', name: 'stats')]
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
