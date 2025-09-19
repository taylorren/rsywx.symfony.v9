<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;

class ErrorController extends AbstractController
{
    public function show(FlattenException $exception, DebugLoggerInterface $logger = null): Response
    {
        $statusCode = $exception->getStatusCode();
        
        // Handle 404 errors specifically
        if ($statusCode === 404) {
            return $this->render('bundles/TwigBundle/Exception/error404.html.twig', [
                'status_code' => $statusCode,
                'status_text' => Response::$statusTexts[$statusCode] ?? 'Error',
                'exception' => $exception,
            ]);
        }
        
        // For other errors, use the default error template
        return $this->render('bundles/TwigBundle/Exception/error.html.twig', [
            'status_code' => $statusCode,
            'status_text' => Response::$statusTexts[$statusCode] ?? 'Error',
            'exception' => $exception,
        ]);
    }
}